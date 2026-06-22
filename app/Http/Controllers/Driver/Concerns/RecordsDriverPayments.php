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
    /** Payment methods a driver may record (form value => label). */
    public static $driverPaymentMethods = [
        'cash' => 'Cash',
        'qr' => 'QR',
        'transfer' => 'Bank Transfer',
        'credit' => 'Credit Term',
    ];

    /** Map driver-portal form values to canonical payment method keys. */
    public static $driverPaymentMethodMap = [
        'cash' => 'cash',
        'qr' => 'qr',
        'transfer' => 'bank-transfer',
        'credit' => 'credit-term',
    ];

    /** Methods that require a payment proof upload. */
    public static $driverProofRequiredMethods = ['qr', 'transfer'];

    /**
     * Payment methods offered to the driver for a given customer type.
     * Credit Term is only meaningful for credit customers.
     *
     * @return array<string, string>
     */
    public static function driverPaymentMethodsFor(string $customerType): array
    {
        $methods = self::$driverPaymentMethods;

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
        if (!$order->canRecordAdminPayment()) {
            return back()->with('error', $order->isCodCustomer()
                ? 'COD payment can only be recorded when the order is in route or delivered.'
                : 'Payment cannot be recorded for this order in its current status.');
        }

        $data = $request->validate([
            'payment_method' => ['required', 'in:' . implode(',', array_keys(self::$driverPaymentMethods))],
            'paid_amount' => ['required', 'numeric', 'min:0.01'],
            'payment_proof' => [
                'nullable',
                'required_if:payment_method,' . implode(',', self::$driverProofRequiredMethods),
                'file',
                'mimes:jpg,jpeg,png,pdf',
                'max:4096',
            ],
        ], [
            'payment_proof.required_if' => 'Payment proof is required for QR and bank transfer payments.',
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

        return back()->with('success', 'Payment recorded successfully.');
    }
}
