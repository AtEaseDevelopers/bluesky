<?php

namespace App\Services;

use App\BulkPayment;
use App\BulkPaymentOrder;
use App\Order;
use App\OrderPayment;
use App\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class BulkPaymentService
{
    public function openOrdersFor(User $user)
    {
        return Order::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', Order::$status['cancelled'])
            ->where('payment_status', '!=', Order::$payment_status['paid'])
            ->orderBy('created_at')
            ->get()
            ->filter(function (Order $order) {
                return $order->balanceDue() > 0;
            })
            ->values();
    }

    public function submit(User $user, array $orderIds, string $method, float $amount, UploadedFile $proof, ?string $notes = null): BulkPayment
    {
        if (!$user->isCreditCustomer()) {
            throw new \InvalidArgumentException('Bulk payment is available for credit customers only.');
        }

        $orders = Order::query()
            ->where('user_id', $user->id)
            ->whereIn('id', $orderIds)
            ->orderBy('created_at')
            ->get()
            ->filter(function (Order $order) {
                return $order->balanceDue() > 0;
            })
            ->values();

        if ($orders->isEmpty()) {
            throw new \InvalidArgumentException('Select at least one order with an outstanding balance.');
        }

        $selectedBalance = $orders->sum(function (Order $order) {
            return $order->balanceDue();
        });

        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero.');
        }

        OrderPayment::assertValidProof($proof, true);

        return DB::transaction(function () use ($user, $orders, $method, $amount, $proof, $notes, $selectedBalance) {
            $proofFilename = $this->storeProof($proof);

            $bulkPayment = BulkPayment::create([
                'user_id' => $user->id,
                'total_amount' => $amount,
                'payment_method' => $method,
                'payment_proof' => $proofFilename,
                'status' => BulkPayment::STATUS_PENDING,
                'notes' => $notes,
            ]);

            $remaining = round($amount, 2);
            $allocations = [];

            foreach ($orders as $order) {
                if ($remaining <= 0) {
                    break;
                }

                $balance = round($order->balanceDue(), 2);
                $allocated = min($balance, $remaining);
                if ($allocated <= 0) {
                    continue;
                }

                $allocations[] = BulkPaymentOrder::create([
                    'bulk_payment_id' => $bulkPayment->id,
                    'order_id' => $order->id,
                    'amount' => $allocated,
                ]);

                OrderPayment::create([
                    'order_id' => $order->id,
                    'payment_method' => $method,
                    'amount' => $allocated,
                    'status' => OrderPayment::STATUS_PENDING,
                    'payment_proof' => $this->copyProofForOrder($proofFilename, $order->id),
                    'submitted_by_user_id' => $user->id,
                    'bulk_payment_id' => $bulkPayment->id,
                    'notes' => trim('Bulk payment #' . $bulkPayment->id . ($notes ? ' — ' . $notes : '')),
                ]);

                $remaining = round($remaining - $allocated, 2);
            }

            if ($remaining > 0.009 && $user->isCreditCustomer()) {
                app(CreditService::class)->recordOverpayment(
                    $user,
                    $remaining,
                    $orders->last(),
                    null,
                    null,
                    'Bulk payment #' . $bulkPayment->id . ' overpayment retained as credit.'
                );
            }

            return $bulkPayment->fresh(['allocations.order']);
        });
    }

    public function confirm(BulkPayment $bulkPayment, int $adminId): BulkPayment
    {
        if (!$bulkPayment->isPending()) {
            throw new \InvalidArgumentException('This bulk payment has already been processed.');
        }

        return DB::transaction(function () use ($bulkPayment, $adminId) {
            foreach ($bulkPayment->payments()->where('status', OrderPayment::STATUS_PENDING)->get() as $payment) {
                app(OrderService::class)->confirmPendingPayment($payment, $adminId);
            }

            $bulkPayment->update(['status' => BulkPayment::STATUS_CONFIRMED]);

            return $bulkPayment->fresh();
        });
    }

    public function reject(BulkPayment $bulkPayment, int $adminId, ?string $reason = null): BulkPayment
    {
        if (!$bulkPayment->isPending()) {
            throw new \InvalidArgumentException('This bulk payment has already been processed.');
        }

        return DB::transaction(function () use ($bulkPayment, $adminId, $reason) {
            foreach ($bulkPayment->payments()->where('status', OrderPayment::STATUS_PENDING)->get() as $payment) {
                app(OrderService::class)->rejectPendingPayment($payment, $adminId, $reason);
            }

            $bulkPayment->update(['status' => BulkPayment::STATUS_REJECTED]);

            return $bulkPayment->fresh();
        });
    }

    private function storeProof(UploadedFile $proof): string
    {
        $extension = $proof->getClientOriginalExtension();
        $filename = 'bulk-' . time() . rand(1000, 9999) . '.' . $extension;
        $path = 'bulk-payments';

        Storage::disk('local')->put($path . '/' . $filename, file_get_contents($proof));

        return $filename;
    }

    private function copyProofForOrder(string $sourceFilename, int $orderId): string
    {
        $sourcePath = 'bulk-payments/' . $sourceFilename;
        $extension = pathinfo($sourceFilename, PATHINFO_EXTENSION);
        $filename = time() . rand(1000, 9999) . '.' . $extension;
        $destPath = Order::$path . '/' . $orderId . '/payments/' . $filename;

        if (Storage::disk('local')->exists($sourcePath)) {
            Storage::disk('local')->put($destPath, Storage::disk('local')->get($sourcePath));
        }

        return $filename;
    }
}
