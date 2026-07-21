<?php

namespace App\Services\RevenueMonster;

use App\Order;
use App\RevenueMonsterTransaction;
use App\Services\OrderService;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

/**
 * Shared Revenue Monster dynamic-QR collection logic used by both the driver
 * portal and the admin back-office: creates (or reuses) a pending QR checkout
 * for an order's outstanding balance and renders it for display.
 */
class OrderQrPaymentService
{
    /** A pending checkout older than this is stale and not reused/shown. */
    private const REUSE_WINDOW_MINUTES = 60;

    /** Payment method key used for gateway-collected payments. */
    private const PAYMENT_METHOD = 'payment-gateway';

    public function __construct(
        private RevenueMonsterService $rm,
        private OrderService $orders
    ) {
    }

    /**
     * Reconcile an order against Revenue Monster's API (a fallback for when the
     * notifyUrl webhook can't reach us, e.g. localhost, or was missed). Queries
     * RM for the pending checkout's status and settles the order if it's paid.
     * Throttled per-order so polling doesn't hammer RM. Returns true if paid.
     */
    public function reconcileFromGateway(Order $order): bool
    {
        if ($order->balanceDue() <= 0) {
            return true;
        }

        // At most one RM lookup per order every 8s (poll interval is ~4s).
        if (! Cache::add('rm-reconcile-' . $order->id, 1, 8)) {
            return false;
        }

        $transaction = RevenueMonsterTransaction::where('order_id', $order->id)
            ->where('status', RevenueMonsterTransaction::STATUS_PENDING)
            ->whereNotNull('reference')
            ->latest('id')
            ->first();

        if (! $transaction) {
            return false;
        }

        try {
            $result = $this->rm->getTransactionByOrderId($transaction->reference);
        } catch (\Throwable $e) {
            // Not found yet / RM unreachable — nothing to reconcile this round.
            return false;
        }

        if (strtoupper((string) data_get($result, 'status')) !== 'SUCCESS') {
            return false;
        }

        $this->settlePaid($transaction, data_get($result, 'transactionId'), json_decode(json_encode($result), true));

        return $order->fresh()->balanceDue() <= 0;
    }

    /**
     * Record a confirmed gateway payment against the order and mark the
     * transaction paid, under a row lock and idempotently. Shared by the
     * webhook and the polling reconciler.
     */
    public function settlePaid(RevenueMonsterTransaction $transaction, ?string $transactionId, ?array $payload = null): void
    {
        DB::transaction(function () use ($transaction, $transactionId, $payload) {
            $transaction = RevenueMonsterTransaction::whereKey($transaction->getKey())->lockForUpdate()->first();
            if (! $transaction || $transaction->isPaid()) {
                return;
            }

            $order = $transaction->order;
            if (! $order) {
                return;
            }

            $amountToRecord = min((float) $transaction->amount, $order->balanceDue());
            if ($amountToRecord > 0 && $order->canRecordAdminPayment()) {
                try {
                    $payment = $this->orders->recordPayment(
                        $order,
                        self::PAYMENT_METHOD,
                        $amountToRecord,
                        null,
                        'Revenue Monster · txn ' . ($transactionId ?: $transaction->reference),
                        null,
                        null
                    );
                    $transaction->order_payment_id = $payment?->id;
                } catch (\InvalidArgumentException $e) {
                    Log::error('Revenue Monster settle: could not record payment', [
                        'reference' => $transaction->reference,
                        'message' => $e->getMessage(),
                    ]);

                    return;
                }
            }

            if ($transactionId) {
                $transaction->transaction_id = $transactionId;
            }
            if ($payload !== null) {
                $transaction->payload = $payload;
            }
            $transaction->status = RevenueMonsterTransaction::STATUS_PAID;
            $transaction->save();
        });
    }

    /**
     * The most recent still-usable pending checkout for an order, if any.
     */
    public function currentPending(Order $order): ?RevenueMonsterTransaction
    {
        return RevenueMonsterTransaction::where('order_id', $order->id)
            ->where('status', RevenueMonsterTransaction::STATUS_PENDING)
            ->whereNotNull('qr_code_url')
            ->where('created_at', '>=', now()->subMinutes(self::REUSE_WINDOW_MINUTES))
            ->latest('id')
            ->first();
    }

    /**
     * Create a new pending QR checkout for the order's balance, or reuse an
     * existing pending one for the same amount (avoids spawning duplicates).
     *
     * @throws \App\Services\RevenueMonster\Exceptions\RevenueMonsterException on RM failure
     */
    public function createOrReuse(Order $order): RevenueMonsterTransaction
    {
        // Serialise per-order so rapid double-clicks can't spawn two checkouts.
        // If the lock can't be acquired (contention / lockless cache driver),
        // fall back to an unlocked run rather than failing the request.
        try {
            return Cache::lock('rm-qr-order-' . $order->id, 15)->block(10, function () use ($order) {
                return $this->createOrReuseLocked($order);
            });
        } catch (LockTimeoutException $e) {
            return $this->createOrReuseLocked($order);
        }
    }

    private function createOrReuseLocked(Order $order): RevenueMonsterTransaction
    {
        $balance = $order->balanceDue();
        $amount = number_format($balance, 2, '.', '');

        // Only reuse a recent pending checkout — never a stale one (e.g. left
        // over from a previous integration/endpoint), which would serve an
        // outdated payment URL.
        $existing = RevenueMonsterTransaction::where('order_id', $order->id)
            ->where('status', RevenueMonsterTransaction::STATUS_PENDING)
            ->where('amount', $amount)
            ->where('created_at', '>=', now()->subMinutes(self::REUSE_WINDOW_MINUTES))
            ->latest('id')
            ->first();

        if ($existing && $existing->qr_code_url) {
            return $existing;
        }

        $reference = 'RM' . $order->id . '-' . strtoupper(Str::random(10));

        $transaction = RevenueMonsterTransaction::create([
            'order_id' => $order->id,
            'reference' => $reference,
            'amount' => $amount,
            'currency' => 'MYR',
            'status' => RevenueMonsterTransaction::STATUS_PENDING,
        ]);

        try {
            // Hosted checkout (/v3/payment/online): amount + currencyType are
            // nested inside order; method is restricted to DuitNow + e-wallets.
            $response = $this->rm->createWebPayment([
                'order' => [
                    'id' => $reference,
                    'title' => Str::limit(($order->do_no ?: ('Order #' . $order->id)), 30, ''),
                    'detail' => __('driver_portal.deliveries.rm_payment_detail', ['id' => $order->do_no ?: $order->id]),
                    'amount' => (int) round($balance * 100),
                    'currencyType' => 'MYR',
                ],
                'type' => 'WEB_PAYMENT',
                'layoutVersion' => 'v4',
                'method' => (array) config('revenuemonster.methods', []),
                'redirectUrl' => trim((string) config('revenuemonster.redirect_url')) ?: route('rm.return'),
                'notifyUrl' => route('webhooks.revenue-monster'),
            ]);
        } catch (\Throwable $e) {
            $transaction->update(['status' => RevenueMonsterTransaction::STATUS_FAILED]);
            throw $e;
        }

        $transaction->update([
            'checkout_id' => data_get($response, 'checkoutId') ?? data_get($response, 'code'),
            'qr_code_url' => data_get($response, 'qrCodeUrl') ?? data_get($response, 'url'),
        ]);

        return $transaction->refresh();
    }

    /**
     * Polling payload for the QR screen. When a transaction reference is given,
     * reports whether that checkout has failed so the page can stop waiting.
     *
     * @return array<string, mixed>
     */
    public function statusPayload(Order $order, ?string $reference = null): array
    {
        $balance = $order->balanceDue();

        $failed = false;
        if ($reference) {
            $transaction = RevenueMonsterTransaction::where('order_id', $order->id)
                ->where('reference', $reference)
                ->first();
            $failed = $transaction !== null && $transaction->status === RevenueMonsterTransaction::STATUS_FAILED;
        }

        return [
            'paid' => $balance <= 0,
            'failed' => $failed,
            'balance' => number_format($balance, 2, '.', ''),
            'paid_amount' => number_format((float) $order->paid_amount, 2, '.', ''),
            'order_status' => $order->status,
        ];
    }

    /**
     * Presentation payload for a QR (reference, scannable image, amount, status).
     *
     * @return array<string, mixed>
     */
    public function present(RevenueMonsterTransaction $transaction, float $balance): array
    {
        return [
            'reference' => $transaction->reference,
            'qr_code_url' => $transaction->qr_code_url,
            'qr_image' => $this->qrImage($transaction->qr_code_url),
            'amount' => number_format($balance, 2, '.', ''),
            'status' => $transaction->status,
        ];
    }

    /**
     * Render RM's payment URL as a scannable QR (SVG data URI).
     */
    public function qrImage(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $svg = QrCode::format('svg')->size(240)->margin(1)->errorCorrection('M')->generate($url);

        return 'data:image/svg+xml;base64,' . base64_encode((string) $svg);
    }
}
