<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Order;
use App\Services\RevenueMonster\Exceptions\RevenueMonsterException;
use App\Services\RevenueMonster\OrderQrPaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Admin back-office Revenue Monster dynamic-QR collection: staff generate a QR
 * for a customer to pay an order's outstanding balance (pay now or pay later).
 */
class OrderQrPaymentController extends Controller
{
    public function __construct(private OrderQrPaymentService $qr)
    {
    }

    /**
     * Generate (or reuse) a QR, then redirect to the display page (POST →
     * redirect → GET, so link-preview bots / prefetch can't trigger RM).
     */
    public function generate(Order $order): RedirectResponse
    {
        $this->authorizeGenerate();

        if ($order->balanceDue() <= 0) {
            return redirect()->route('admin.orders.summary', $order->id)
                ->with('info', __('orders.qr.already_paid'));
        }

        $min = (float) config('revenuemonster.min_amount', 1);
        if ($order->balanceDue() < $min) {
            return redirect()->route('admin.orders.summary', $order->id)
                ->with('error', __('orders.qr.min_amount', ['min' => number_format($min, 2)]));
        }

        try {
            $this->qr->createOrReuse($order);
        } catch (RevenueMonsterException $e) {
            Log::error('Admin Revenue Monster QR create failed', [
                'order_id' => $order->id,
                'rm_code' => $e->getRmErrorCode(),
                'message' => $e->getMessage(),
            ]);
            $detail = $e->getRmErrorCode() ? ($e->getRmErrorCode() . ': ' . $e->getMessage()) : $e->getMessage();

            return redirect()->route('admin.orders.summary', $order->id)
                ->with('error', __('orders.qr.failed') . ' (' . $detail . ')');
        }

        return redirect()->route('admin.orders.qr', $order->id);
    }

    /**
     * Display the current pending QR for the order (read-only; never creates).
     */
    public function show(Order $order)
    {
        $this->authorizeGenerate();

        $balance = $order->balanceDue();
        if ($balance <= 0) {
            return redirect()->route('admin.orders.summary', $order->id)
                ->with('info', __('orders.qr.already_paid'));
        }

        $transaction = $this->qr->currentPending($order);
        if (! $transaction) {
            return redirect()->route('admin.orders.summary', $order->id)
                ->with('error', __('orders.qr.failed'));
        }

        return view('admin.orders.qr', [
            'order' => $order,
            'qr' => $this->qr->present($transaction, $balance),
            'balance' => number_format($balance, 2, '.', ''),
            'statusUrl' => route('admin.orders.qr-status', $order->id),
        ]);
    }

    /**
     * Poll payment status for the order (used by the QR screen).
     */
    public function status(Order $order): JsonResponse
    {
        $this->authorizeGenerate();
        // Fallback reconciliation in case RM's webhook can't reach us.
        $this->qr->reconcileFromGateway($order);
        $order->refresh();

        return response()->json($this->qr->statusPayload($order, request('ref')));
    }

    private function authorizeGenerate(): void
    {
        $admin = Auth::guard('web_admin')->user();

        if (! $admin || ! $admin->canModule('orders', 'edit')) {
            abort(403, 'You do not have permission to collect online payments.');
        }
    }
}
