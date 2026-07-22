<?php

namespace App\Http\Controllers;

use App\RevenueMonsterTransaction;
use App\Services\OrderService;
use App\Services\RevenueMonster\RevenueMonsterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Receives Revenue Monster payment notifications (notifyUrl). Verifies the RM
 * signature, then records a confirmed payment against the matching order. The
 * endpoint is idempotent — RM may retry the same notification.
 */
class RevenueMonsterWebhookController extends Controller
{
    /** Payment method key used for gateway-collected payments. */
    private const PAYMENT_METHOD = 'payment-gateway';

    public function __construct(
        private RevenueMonsterService $rm,
        private OrderService $orders
    ) {
    }

    public function notify(Request $request): JsonResponse
    {
        if (! $this->signatureValid($request)) {
            Log::warning('Revenue Monster webhook: invalid signature', ['ip' => $request->ip()]);

            return response()->json(['message' => 'invalid signature'], 401);
        }

        $body = $request->all();
        $reference = data_get($body, 'data.order.id') ?? data_get($body, 'order.id');
        $status = strtoupper((string) (data_get($body, 'data.status') ?? data_get($body, 'status') ?? ''));
        $transactionId = data_get($body, 'data.transactionId') ?? data_get($body, 'transactionId');

        Log::info('Revenue Monster webhook received (signature ok)', [
            'reference' => $reference,
            'status' => $status,
            'transactionId' => $transactionId,
        ]);

        if (! $reference) {
            return response()->json(['message' => 'missing reference'], 422);
        }

        $transaction = RevenueMonsterTransaction::where('reference', $reference)->first();
        if (! $transaction) {
            Log::warning('Revenue Monster webhook: unknown reference', ['reference' => $reference]);

            return response()->json(['message' => 'unknown reference'], 404);
        }

        // Reconcile under a row lock so concurrent RM retries can't double-record.
        return DB::transaction(function () use ($transaction, $status, $transactionId, $body, $reference) {
            $transaction = RevenueMonsterTransaction::whereKey($transaction->getKey())->lockForUpdate()->first();

            // Idempotency — already reconciled, acknowledge and stop.
            if ($transaction->isPaid()) {
                return response()->json(['message' => 'already processed']);
            }

            $transaction->fill([
                'transaction_id' => $transactionId,
                'payload' => $body,
            ]);

            if ($status !== 'SUCCESS') {
                $transaction->status = RevenueMonsterTransaction::STATUS_FAILED;
                $transaction->save();

                return response()->json(['message' => 'noted']);
            }

            $order = $transaction->order;
            if (! $order) {
                $transaction->save();

                return response()->json(['message' => 'order not found'], 404);
            }

            $amountToRecord = min((float) $transaction->amount, $order->balanceDue());

            if ($amountToRecord > 0 && $order->canSettleGatewayPayment()) {
                try {
                    $payment = $this->orders->recordPayment(
                        $order,
                        self::PAYMENT_METHOD,
                        $amountToRecord,
                        null,
                        'Revenue Monster QR · txn ' . ($transactionId ?: $transaction->reference),
                        null,
                        null
                    );
                    $transaction->order_payment_id = $payment?->id;
                } catch (\InvalidArgumentException $e) {
                    Log::error('Revenue Monster webhook: could not record payment', [
                        'reference' => $reference,
                        'message' => $e->getMessage(),
                    ]);
                    $transaction->save();

                    return response()->json(['message' => 'could not record payment'], 422);
                }
            }

            $transaction->status = RevenueMonsterTransaction::STATUS_PAID;
            $transaction->save();

            return response()->json(['message' => 'ok']);
        });
    }

    /**
     * Verify the RM signature over the raw request body using RM's public key.
     */
    private function signatureValid(Request $request): bool
    {
        $signature = (string) $request->header('X-Signature', '');
        $nonceStr = (string) $request->header('X-Nonce-Str', '');
        $timestamp = (string) $request->header('X-Timestamp', '');

        if ($signature === '' || $nonceStr === '' || $timestamp === '') {
            return false;
        }

        // Replay guard: reject callbacks whose timestamp is too far from now.
        $tolerance = (int) config('revenuemonster.callback_tolerance', 300);
        if ($tolerance > 0 && abs(time() - (int) $timestamp) > $tolerance) {
            Log::warning('Revenue Monster webhook: stale timestamp', ['timestamp' => $timestamp]);

            return false;
        }

        // Fail closed: a misconfigured/placeholder public key throws inside
        // openssl — treat that as an invalid signature (401), never a 500.
        try {
            // RM signs the exact body bytes it sends; also try our re-canonicalised
            // form. And the docs are ambiguous on whether callback signatures
            // include requestUrl, so try with the notifyUrl and omitted.
            $dataCandidates = array_unique([
                base64_encode($request->getContent()),
                $this->rm->client()->canonicalizeData($request->all()),
            ]);
            $urlCandidates = [route('webhooks.revenue-monster'), ''];

            foreach ($dataCandidates as $base64Data) {
                foreach ($urlCandidates as $requestUrl) {
                    if ($this->rm->verifyCallback($signature, 'post', $requestUrl, $nonceStr, $timestamp, $base64Data)) {
                        return true;
                    }
                }
            }

            Log::warning('Revenue Monster webhook: signature mismatch', [
                'nonceStr' => $nonceStr,
                'timestamp' => $timestamp,
                'signature' => $signature,
                'raw_body' => $request->getContent(),
            ]);

            return false;
        } catch (\Throwable $e) {
            Log::warning('Revenue Monster webhook: signature verification error', ['message' => $e->getMessage()]);

            return false;
        }
    }
}
