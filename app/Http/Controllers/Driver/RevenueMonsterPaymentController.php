<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use App\Order;
use App\Services\RevenueMonster\Exceptions\RevenueMonsterException;
use App\Services\RevenueMonster\OrderQrPaymentService;
use App\Services\RolePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

/**
 * Driver-facing Revenue Monster collection. The driver taps "Make Payment" on
 * an order; we create a hosted-checkout session for the outstanding balance and
 * render a QR of the payment URL. The customer scans it, lands on RM's hosted
 * page, and pays; the notifyUrl callback reconciles the payment onto the order.
 */
class RevenueMonsterPaymentController extends Controller
{
    public function __construct(private OrderQrPaymentService $qr)
    {
    }

    /**
     * Generate (or reuse) a hosted-checkout QR, then redirect to the display
     * page (POST → redirect → GET, so link-preview bots / prefetch can't
     * trigger an RM checkout on a plain GET).
     */
    public function generate($id): RedirectResponse
    {
        $order = $this->findAssignedOrder($id);
        $this->authorizeMakePayment();

        if ($order->balanceDue() <= 0) {
            return redirect()->route('driver.orders.show', $order->id)
                ->with('error', __('driver_portal.deliveries.rm_already_paid'));
        }

        $min = (float) config('revenuemonster.min_amount', 1);
        if ($order->balanceDue() < $min) {
            return redirect()->route('driver.orders.show', $order->id)
                ->with('error', __('driver_portal.deliveries.rm_min_amount', ['min' => number_format($min, 2)]));
        }

        try {
            $this->qr->createOrReuse($order);
        } catch (RevenueMonsterException $e) {
            Log::error('Revenue Monster checkout create failed', [
                'order_id' => $order->id,
                'rm_code' => $e->getRmErrorCode(),
                'message' => $e->getMessage(),
            ]);
            $detail = $e->getRmErrorCode() ? ($e->getRmErrorCode() . ': ' . $e->getMessage()) : $e->getMessage();

            return redirect()->route('driver.orders.show', $order->id)
                ->with('error', __('driver_portal.deliveries.rm_create_failed') . ' (' . $detail . ')');
        }

        return redirect()->route('driver.orders.rm-qr', $order->id);
    }

    /**
     * Display the current pending QR for the order (read-only; never creates).
     */
    public function show($id)
    {
        $order = $this->findAssignedOrder($id);
        $this->authorizeMakePayment();

        $balance = $order->balanceDue();
        if ($balance <= 0) {
            return redirect()->route('driver.orders.show', $order->id)
                ->with('error', __('driver_portal.deliveries.rm_already_paid'));
        }

        $transaction = $this->qr->currentPending($order);
        if (! $transaction) {
            return redirect()->route('driver.orders.show', $order->id)
                ->with('error', __('driver_portal.deliveries.rm_create_failed'));
        }

        return view('driver.orders.qr', [
            'order' => $order,
            'qr' => $this->qr->present($transaction, $balance),
            'balance' => number_format($balance, 2, '.', ''),
            'statusUrl' => route('driver.orders.rm-status', $order->id),
        ]);
    }

    /**
     * Poll payment status for the order (used by the QR screen).
     */
    public function status($id): JsonResponse
    {
        $order = $this->findAssignedOrder($id);
        // Fallback reconciliation in case RM's webhook can't reach us.
        $this->qr->reconcileFromGateway($order);
        $order->refresh();

        return response()->json($this->qr->statusPayload($order, request('ref')));
    }

    private function findAssignedOrder($id): Order
    {
        $order = Order::where('id', $id)
            ->where('driver_id', Auth::guard('web_driver')->id())
            ->where('fulfillment_type', Order::$fulfillment_types['delivery'])
            ->firstOrFail();

        return $order->ensureDoNumber();
    }

    private function authorizeMakePayment(): void
    {
        $roleSlug = Auth::guard('web_driver')->user()->role_slug ?? 'driver';

        if (! app(RolePermissionService::class)->can($roleSlug, 'make_payment')) {
            abort(403, 'You do not have permission to collect online payments.');
        }
    }
}
