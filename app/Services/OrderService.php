<?php

namespace App\Services;

use App\Order;
use App\OrderPayment;
use App\OrderProduct;
use App\PdfHelper;
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
        $paid = (float) OrderPayment::where('order_id', $order->id)->sum('amount');

        if ($paid >= $total && $total > 0) {
            $status = Order::$payment_status['paid'];
        } elseif ($this->isPaymentOverdue($order, $paid, $total)) {
            $status = Order::$payment_status['payment_due'];
        } elseif ($paid > 0) {
            $status = Order::$payment_status['partial'];
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
        ?int $adminId
    ): OrderPayment {
        $balanceDue = $order->balanceDue();
        if ($amount > $balanceDue + 0.0001) {
            throw new \InvalidArgumentException(
                'Payment amount exceeds balance due (RM ' . number_format($balanceDue, 2) . ').'
            );
        }

        $proofPath = null;
        if ($proof) {
            $extension = $proof->getClientOriginalExtension();
            $filename = time() . rand() . '.' . $extension;
            $path = Order::$path . '/' . $order->id . '/payments';
            Storage::disk('local')->put($path . '/' . $filename, file_get_contents($proof));
            $proofPath = $filename;
        }

        $payment = OrderPayment::create([
            'order_id' => $order->id,
            'payment_method' => $method,
            'amount' => $amount,
            'payment_proof' => $proofPath,
            'recorded_by' => $adminId,
            'notes' => $notes,
        ]);

        $this->refreshPaymentStatus($order->fresh());

        return $payment;
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
        if ($order->order_type === Order::$order_types['walk_in'] || $order->order_type === Order::$order_types['public']) {
            return $order->walk_in_name ?: 'Walk-in Customer';
        }

        return $order->customer->name ?? '-';
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
