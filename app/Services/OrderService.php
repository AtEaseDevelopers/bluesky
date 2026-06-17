<?php

namespace App\Services;

use App\Order;
use App\OrderPayment;
use App\OrderProduct;
use App\PdfHelper;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class OrderService
{
    public function recalculateTotals(Order $order): Order
    {
        $subtotal = OrderProduct::where('order_id', $order->id)
            ->where('status', OrderProduct::$status['active'])
            ->sum('price');

        $total = (float) $subtotal
            + (float) $order->delivery_fee
            + (float) $order->amount_adjustment;

        $order->update([
            'subtotal' => $subtotal,
            'total_price' => max(0, $total),
        ]);

        $this->refreshPaymentStatus($order->fresh());

        return $order->fresh();
    }

    public function refreshPaymentStatus(Order $order): Order
    {
        $total = (float) $order->total_price;
        $paid = (float) OrderPayment::where('order_id', $order->id)
            ->where('status', OrderPayment::STATUS_CONFIRMED)
            ->sum('amount');

        $hasPendingProof = OrderPayment::where('order_id', $order->id)
            ->where('status', OrderPayment::STATUS_PENDING)
            ->exists();

        if ($paid >= $total && $total > 0) {
            $status = Order::$payment_status['paid'];
        } elseif ($hasPendingProof) {
            $status = Order::$payment_status['pending'];
        } elseif ($this->isPaymentOverdue($order, $paid, $total)) {
            $status = Order::$payment_status['payment_due'];
        } else {
            $status = Order::$payment_status['unpaid'];
        }

        $order->update([
            'paid_amount' => $paid,
            'payment_status' => $status,
        ]);

        return $order->fresh();
    }

    public function syncOverduePaymentStatuses(): void
    {
        Order::query()
            ->whereNotNull('payment_due_date')
            ->where('payment_due_date', '<=', now()->toDateString())
            ->where('payment_status', '!=', Order::$payment_status['paid'])
            ->where('status', '!=', Order::$status['cancelled'])
            ->chunkById(100, function ($orders) {
                foreach ($orders as $order) {
                    $this->refreshPaymentStatus($order);
                }
            });
    }

    public function updatePaymentDueDate(Order $order, ?string $paymentDueDate): Order
    {
        if (!$order->isCreditCustomer()) {
            throw new \InvalidArgumentException('Payment due date applies to credit customers only.');
        }

        if ($order->payment_status === Order::$payment_status['paid']) {
            throw new \InvalidArgumentException('Payment due date cannot be changed on a fully paid order.');
        }

        $order->update(['payment_due_date' => $paymentDueDate ?: null]);

        return $this->refreshPaymentStatus($order->fresh());
    }

    private function isPaymentOverdue(Order $order, float $paid, float $total): bool
    {
        if (!$order->payment_due_date || $paid >= $total) {
            return false;
        }

        return $order->payment_due_date->toDateString() <= now()->toDateString();
    }

    public function recordPayment(
        Order $order,
        string $method,
        float $amount,
        ?UploadedFile $proof,
        ?string $notes,
        ?int $adminId,
        ?int $driverId = null,
        bool $splitLine = false
    ): OrderPayment {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $this->assertAdminPaymentMethod($order, $method);

        $balanceDue = $order->balanceDue();
        $this->assertPaymentAmount($order, $amount, $balanceDue, $splitLine);

        $amountToOrder = min($amount, $balanceDue);
        $overpayment = $order->allowsOverpayment()
            ? max(0, round($amount - $balanceDue, 2))
            : 0;

        $proofPath = null;
        if ($proof) {
            OrderPayment::assertValidProof($proof, false);
            $extension = $proof->getClientOriginalExtension();
            $filename = time() . rand() . '.' . $extension;
            $path = Order::$path . '/' . $order->id . '/payments';
            Storage::disk('local')->put($path . '/' . $filename, file_get_contents($proof));
            $proofPath = $filename;
        }

        $payment = null;
        if ($amountToOrder > 0) {
            $payment = OrderPayment::create([
                'order_id' => $order->id,
                'payment_method' => $method,
                'amount' => $amountToOrder,
                'status' => OrderPayment::STATUS_CONFIRMED,
                'payment_proof' => $proofPath,
                'recorded_by' => $adminId,
                'recorded_by_driver' => $driverId,
                'notes' => $notes,
            ]);
        }

        if ($overpayment > 0 && $order->user_id && $order->allowsOverpayment()) {
            $customer = $order->customer;
            if ($customer) {
                app(CreditService::class)->recordOverpayment(
                    $customer,
                    $overpayment,
                    $order,
                    $adminId,
                    $driverId,
                    $notes
                );
            }
        }

        if ($amountToOrder <= 0 && $overpayment <= 0) {
            throw new \InvalidArgumentException('No balance due on this order.');
        }

        if (!$payment && $overpayment > 0) {
            $payment = OrderPayment::create([
                'order_id' => $order->id,
                'payment_method' => $method,
                'amount' => 0,
                'status' => OrderPayment::STATUS_CONFIRMED,
                'payment_proof' => $proofPath,
                'recorded_by' => $adminId,
                'recorded_by_driver' => $driverId,
                'notes' => trim(($notes ?? '') . ' Overpayment RM ' . number_format($overpayment, 2) . ' added to customer credit.'),
            ]);
        }

        $this->refreshPaymentStatus($order->fresh());

        return $payment;
    }

    public function recordPayments(
        Order $order,
        array $payments,
        ?int $adminId = null,
        ?int $driverId = null
    ): array {
        if (!$order->canRecordAdminPayment()) {
            throw new \InvalidArgumentException(
                $order->isCodCustomer()
                    ? 'COD payment can only be recorded when the order is in route or delivered.'
                    : 'Payment cannot be recorded for this order in its current status.'
            );
        }

        $totalAmount = 0;
        foreach ($payments as $paymentData) {
            $totalAmount += (float) ($paymentData['amount'] ?? 0);
        }

        if ($order->requiresExactPayment()) {
            $balanceDue = $order->balanceDue();
            if (abs($totalAmount - $balanceDue) > 0.009) {
                throw new \InvalidArgumentException(
                    'COD orders require the full exact balance (RM ' . number_format($balanceDue, 2) . '). Partial or excess payment is not allowed.'
                );
            }
        }

        $recorded = [];

        foreach ($payments as $paymentData) {
            $amount = (float) ($paymentData['amount'] ?? 0);
            if ($amount <= 0) {
                continue;
            }

            $recorded[] = $this->recordPayment(
                $order->fresh(),
                $paymentData['payment_method'],
                $amount,
                $paymentData['proof'] ?? null,
                $paymentData['notes'] ?? null,
                $adminId,
                $driverId,
                $order->requiresExactPayment()
            );
        }

        if (empty($recorded)) {
            throw new \InvalidArgumentException('At least one valid payment is required.');
        }

        return $recorded;
    }

    public function submitCustomerPaymentProof(
        Order $order,
        User $customer,
        string $method,
        float $amount,
        UploadedFile $proof,
        ?string $notes = null
    ): OrderPayment {
        if (!$order->canSubmitPaymentProof()) {
            throw new \InvalidArgumentException('Payment proof cannot be submitted for this order.');
        }

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        $allowedMethods = $order->allowedCustomerPaymentMethods();
        if (!array_key_exists($method, $allowedMethods)) {
            throw new \InvalidArgumentException('Invalid payment method for this customer type.');
        }

        if ($order->requiresExactPayment()) {
            $balanceDue = $order->balanceDue();
            if (abs($amount - $balanceDue) > 0.009) {
                throw new \InvalidArgumentException(
                    'COD orders require payment of the exact balance due (RM ' . number_format($balanceDue, 2) . ').'
                );
            }
        }

        OrderPayment::assertValidProof($proof, true);

        $extension = $proof->getClientOriginalExtension();
        $filename = 'customer_' . time() . rand() . '.' . $extension;
        $path = Order::$path . '/' . $order->id . '/payments';
        Storage::disk('local')->put($path . '/' . $filename, file_get_contents($proof));

        $payment = OrderPayment::create([
            'order_id' => $order->id,
            'payment_method' => $method,
            'amount' => $amount,
            'status' => OrderPayment::STATUS_PENDING,
            'payment_proof' => $filename,
            'submitted_by_user_id' => $customer->id,
            'notes' => $notes,
        ]);

        $this->refreshPaymentStatus($order->fresh());

        return $payment;
    }

    public function confirmPendingPayment(OrderPayment $payment, int $adminId): OrderPayment
    {
        if (!$payment->isPending()) {
            throw new \InvalidArgumentException('This payment submission has already been processed.');
        }

        $order = $payment->order;
        if (!$order) {
            throw new \InvalidArgumentException('Order not found for this payment.');
        }

        return DB::transaction(function () use ($payment, $order, $adminId) {
            $amount = (float) $payment->amount;
            $balanceDue = $order->balanceDue();

            if ($order->requiresExactPayment() && abs($amount - $balanceDue) > 0.009) {
                throw new \InvalidArgumentException(
                    'COD payment must match the exact balance due (RM ' . number_format($balanceDue, 2) . ').'
                );
            }

            $amountToOrder = $order->requiresExactPayment()
                ? $balanceDue
                : min($amount, $balanceDue);
            $overpayment = $order->allowsOverpayment()
                ? max(0, round($amount - $balanceDue, 2))
                : 0;

            if (!$order->allowsOverpayment() && $amount > $balanceDue + 0.009) {
                throw new \InvalidArgumentException('COD orders cannot accept overpayment.');
            }

            $payment->update([
                'amount' => $amountToOrder > 0 ? $amountToOrder : 0,
                'status' => OrderPayment::STATUS_CONFIRMED,
                'recorded_by' => $adminId,
            ]);

            if ($overpayment > 0 && $order->user_id && $order->allowsOverpayment()) {
                $customer = $order->customer;
                if ($customer) {
                    app(CreditService::class)->recordOverpayment(
                        $customer,
                        $overpayment,
                        $order,
                        $adminId,
                        null,
                        $payment->notes
                    );
                }
            }

            $this->refreshPaymentStatus($order->fresh());

            return $payment->fresh();
        });
    }

    public function rejectPendingPayment(OrderPayment $payment, int $adminId, ?string $reason = null): OrderPayment
    {
        if (!$payment->isPending()) {
            throw new \InvalidArgumentException('This payment submission has already been processed.');
        }

        $payment->update([
            'status' => OrderPayment::STATUS_REJECTED,
            'recorded_by' => $adminId,
            'notes' => trim(($payment->notes ? $payment->notes . ' — ' : '') . 'Rejected: ' . ($reason ?: 'Payment proof not accepted')),
        ]);

        $this->refreshPaymentStatus($payment->order->fresh());

        return $payment->fresh();
    }

    private function assertAdminPaymentMethod(Order $order, string $method): void
    {
        $allowed = $order->allowedAdminPaymentMethods();

        if ($method === 'customer-credit') {
            return;
        }

        if (!array_key_exists($method, $allowed)) {
            throw new \InvalidArgumentException(
                'Payment method not allowed for ' . strtoupper($order->customerType()) . ' customers.'
            );
        }
    }

    private function assertPaymentAmount(Order $order, float $amount, float $balanceDue, bool $splitLine = false): void
    {
        if ($balanceDue <= 0) {
            throw new \InvalidArgumentException('No balance due on this order.');
        }

        if ($order->requiresExactPayment()) {
            if ($splitLine) {
                if ($amount > $balanceDue + 0.009) {
                    throw new \InvalidArgumentException(
                        'COD payment line cannot exceed balance due (RM ' . number_format($balanceDue, 2) . ').'
                    );
                }
                return;
            }

            if (abs($amount - $balanceDue) > 0.009) {
                throw new \InvalidArgumentException(
                    'COD orders require the exact balance due (RM ' . number_format($balanceDue, 2) . ').'
                );
            }
            return;
        }

        if ($amount > $balanceDue + 0.009 && !$order->allowsOverpayment()) {
            throw new \InvalidArgumentException('Payment amount exceeds balance due.');
        }
    }

    public function generateInvoiceNumber(Order $order): Order
    {
        if ($order->invoice_number) {
            return $order;
        }

        $number = 'INV-' . date('Ymd') . '-' . str_pad((string) $order->id, 5, '0', STR_PAD_LEFT);
        $order->update(['invoice_number' => $number]);

        return $order->fresh();
    }

    public function applyReviewAdjustments(Order $order, array $lineItems, array $data, ?int $adminId): Order
    {
        return DB::transaction(function () use ($order, $lineItems, $data, $adminId) {
            foreach ($lineItems as $orderProductId => $item) {
                $orderProduct = OrderProduct::where('order_id', $order->id)
                    ->where('id', $orderProductId)
                    ->first();

                if (!$orderProduct) {
                    continue;
                }

                $quantity = (float) ($item['quantity'] ?? $orderProduct->quantity);
                $weight = isset($item['weight']) && $item['weight'] !== ''
                    ? (float) $item['weight']
                    : null;

                $lineTotal = (float) $orderProduct->unit_price * $quantity;

                $orderProduct->update([
                    'quantity' => $quantity,
                    'weight' => $weight,
                    'product_weight' => $weight,
                    'price' => $lineTotal,
                ]);
            }

            $order->update([
                'delivery_fee' => (float) ($data['delivery_fee'] ?? 0),
                'amount_adjustment' => (float) ($data['amount_adjustment'] ?? 0),
                'adjustment_remark' => $data['adjustment_remark'] ?? null,
                'payment_due_date' => $order->isCreditCustomer()
                    ? $this->resolvePaymentDueDate($order, $data['payment_due_date'] ?? null)
                    : null,
                'driver_id' => $data['driver_id'] ?? $order->driver_id,
            ]);

            $this->recalculateTotals($order->fresh());

            if (!empty($data['send_to_customer'])) {
                $order = $order->fresh();
                if ($order->status === Order::$status['pending']) {
                    app(OrderStatusService::class)->transition(
                        $order,
                        Order::$status['customer_reviewing'],
                        $adminId
                    );
                } else {
                    PdfHelper::GenerateOrderInvoice($order);
                    PdfHelper::GenerateOrderInvoiceWithoutPrice($order);
                }
            }

            return $order->fresh();
        });
    }

    public function updateDeliveryFee(Order $order, float $deliveryFee): Order
    {
        if (!Order::canEditDeliveryFee($order->status)) {
            throw new \InvalidArgumentException('Delivery fee cannot be changed after the order has been delivered.');
        }

        $order->update(['delivery_fee' => $deliveryFee]);
        $order = $this->recalculateTotals($order->fresh());

        if (in_array($order->status, [
            Order::$status['customer_reviewing'],
            Order::$status['in_route'],
        ], true)) {
            PdfHelper::GenerateOrderInvoice($order);
            PdfHelper::GenerateOrderInvoiceWithoutPrice($order);
        }

        return $order;
    }

    public function displayCustomerName(Order $order): string
    {
        if ($order->customer) {
            return $order->customer->name ?? '-';
        }

        if ($order->walk_in_name) {
            return $order->walk_in_name;
        }

        if ($order->attn_name) {
            return $order->attn_name;
        }

        return 'Walk-in Customer';
    }

    public function assignDoNumber(Order $order): Order
    {
        if ($order->do_no) {
            return $order;
        }

        $prefix = 'AHP' . now()->format('ym');
        $latest = Order::where('do_no', 'like', $prefix . '%')
            ->orderBy('do_no', 'desc')
            ->first();

        $nextIndex = $latest ? ((int) substr($latest->do_no, strlen($prefix)) + 1) : 1;
        $order->update(['do_no' => $prefix . sprintf('%04d', $nextIndex)]);

        return $order->fresh();
    }

    public function canAdjustAmount($admin): bool
    {
        return in_array($admin->role ?? '', ['superadmin', 'management'], true);
    }

    private function resolvePaymentDueDate(Order $order, ?string $requestedDate): ?string
    {
        if ($requestedDate) {
            return $requestedDate;
        }

        $customer = $order->customer;
        if ($customer && ($customer->customer_type ?? 'cod') === 'credit') {
            return now()->addDays(30)->toDateString();
        }

        return null;
    }
}
