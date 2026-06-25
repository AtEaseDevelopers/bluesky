<?php

namespace App\Http\Controllers\Driver\Concerns;

use App\Order;
use App\Services\OrderService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Shared payment-recording behaviour for the driver portal. Used by both the
 * delivery-order screen and the assigned-customer screen so a single set of
 * payment methods, validation rules, and persistence logic backs both.
 */
trait RecordsDriverPayments
{
    /** Form values a driver may use when recording a payment. */
    public static $driverPaymentMethodKeys = ['cash', 'qr', 'transfer', 'credit'];

    /** Map driver-portal form values to canonical payment method keys. */
    public static $driverPaymentMethodMap = [
        'cash' => 'cash',
        'qr' => 'qr',
        'transfer' => 'bank-transfer',
        'credit' => 'credit-term',
    ];

    /** Methods that require a payment proof upload. */
    public static $driverProofRequiredMethods = ['qr', 'transfer'];

    /** @return array<string, string> */
    public static function driverPaymentMethodLabels(): array
    {
        return [
            'cash' => __('order.payment_methods.cash'),
            'qr' => __('order.payment_methods.qr'),
            'transfer' => __('order.payment_methods.bank-transfer'),
            'credit' => __('order.payment_methods.credit-term'),
        ];
    }

    /**
     * Payment methods offered to the driver for a given customer type.
     *
     * @return array<string, string>
     */
    public static function driverPaymentMethodsFor(string $customerType): array
    {
        $methods = self::driverPaymentMethodLabels();

        if ($customerType !== 'credit') {
            unset($methods['credit']);
        }

        return $methods;
    }

    /**
     * Validate the payment form and record it against the order on behalf of
     * the logged-in driver. Returns a redirect back with a success/error flash.
     */
    protected function recordDriverPayment(Request $request, Order $order)
    {
        if ($order->isCreditCustomer()) {
            return back()->with('error', __('driver_portal.payment.credit_not_allowed'));
        }

        if (!$order->canRecordAdminPayment()) {
            return back()->with('error', $order->isCodCustomer()
                ? __('driver_portal.payment.cod_status_required')
                : __('driver_portal.payment.cannot_record'));
        }

        $data = $request->validate([
            'payment_method' => ['required', 'in:' . implode(',', self::$driverPaymentMethodKeys)],
            'paid_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_proof' => [
                'nullable',
                'required_if:payment_method,' . implode(',', self::$driverProofRequiredMethods),
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:4096',
            ],
        ], [
            'payment_proof.required_if' => __('driver_portal.payment.proof_required'),
        ]);

        $method = self::$driverPaymentMethodMap[$data['payment_method']] ?? $data['payment_method'];

        try {
            app(OrderService::class)->recordPayment(
                $order,
                $method,
                (float) $data['paid_amount'],
                $request->file('payment_proof'),
                null,
                null,
                Auth::guard('web_driver')->id()
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', __('driver_portal.payment.recorded'));
    }
}
