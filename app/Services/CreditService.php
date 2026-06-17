<?php

namespace App\Services;

use App\CustomerCreditLog;
use App\Order;
use App\OrderPayment;
use App\User;
use Illuminate\Support\Facades\DB;

class CreditService
{
    public function balance(User $user): float
    {
        if (!$user->isCreditCustomer()) {
            return 0;
        }

        return (float) $user->credit_balance;
    }

    public function availableCredit(User $user): float
    {
        return max(0, $this->balance($user));
    }

    public function applyAvailableCredit(Order $order): float
    {
        if (!$order->user_id) {
            return 0;
        }

        $customer = User::find($order->user_id);
        if (!$customer || !$customer->isCreditCustomer()) {
            return 0;
        }

        $available = $this->availableCredit($customer);
        if ($available <= 0) {
            return 0;
        }

        $amountToApply = min($available, $order->balanceDue());
        if ($amountToApply <= 0) {
            return 0;
        }

        return DB::transaction(function () use ($customer, $order, $amountToApply) {
            $customer = User::lockForUpdate()->find($customer->id);
            $applyAmount = min($this->availableCredit($customer), $order->fresh()->balanceDue());

            if ($applyAmount <= 0) {
                return 0;
            }

            $payment = OrderPayment::create([
                'order_id' => $order->id,
                'payment_method' => 'customer-credit',
                'amount' => $applyAmount,
                'status' => OrderPayment::STATUS_CONFIRMED,
                'notes' => 'Customer credit balance applied automatically.',
            ]);

            $this->adjustBalance(
                $customer,
                -$applyAmount,
                'applied_to_order',
                $order->id,
                $payment->id,
                null,
                null,
                'Credit applied to order #' . $order->id
            );

            app(OrderService::class)->refreshPaymentStatus($order->fresh());

            return $applyAmount;
        });
    }

    public function recordOverpayment(User $user, float $amount, Order $order, ?int $adminId = null, ?int $driverId = null, ?string $notes = null): ?CustomerCreditLog
    {
        if ($amount <= 0) {
            return null;
        }

        $this->assertCreditCustomer($user);

        return $this->adjustBalance(
            $user,
            $amount,
            'overpayment',
            $order->id,
            null,
            $adminId,
            $driverId,
            $notes ?: 'Overpayment on order #' . $order->id
        );
    }

    public function manualAdjust(User $user, float $amount, string $notes, int $adminId): CustomerCreditLog
    {
        if ($amount == 0) {
            throw new \InvalidArgumentException('Adjustment amount cannot be zero.');
        }

        $this->assertCreditCustomer($user);

        return $this->adjustBalance(
            $user,
            $amount,
            'manual_adjustment',
            null,
            null,
            $adminId,
            null,
            $notes
        );
    }

    public function adjustBalance(
        User $user,
        float $amount,
        string $type,
        ?int $orderId = null,
        ?int $orderPaymentId = null,
        ?int $adminId = null,
        ?int $driverId = null,
        ?string $notes = null
    ): CustomerCreditLog {
        $this->assertCreditCustomer($user);

        return DB::transaction(function () use ($user, $amount, $type, $orderId, $orderPaymentId, $adminId, $driverId, $notes) {
            $customer = User::lockForUpdate()->find($user->id);
            $balanceBefore = (float) $customer->credit_balance;
            $balanceAfter = round($balanceBefore + $amount, 2);

            $customer->update(['credit_balance' => $balanceAfter]);

            return CustomerCreditLog::create([
                'user_id' => $customer->id,
                'type' => $type,
                'amount' => $amount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'order_id' => $orderId,
                'order_payment_id' => $orderPaymentId,
                'notes' => $notes,
                'recorded_by' => $adminId,
                'recorded_by_driver' => $driverId,
            ]);
        });
    }

    public function logsForCustomer(int $userId, int $limit = 50)
    {
        return CustomerCreditLog::where('user_id', $userId)
            ->with(['order:id'])
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }

    public function clearBalanceForCodCustomer(User $user, ?int $adminId = null): void
    {
        if ($user->isCreditCustomer()) {
            return;
        }

        $balance = (float) $user->credit_balance;
        if ($balance == 0) {
            return;
        }

        DB::transaction(function () use ($user, $balance, $adminId) {
            $customer = User::lockForUpdate()->find($user->id);
            if (!$customer || $customer->isCreditCustomer()) {
                return;
            }

            $currentBalance = (float) $customer->credit_balance;
            if ($currentBalance == 0) {
                return;
            }

            $customer->update(['credit_balance' => 0]);

            CustomerCreditLog::create([
                'user_id' => $customer->id,
                'type' => 'manual_adjustment',
                'amount' => -$currentBalance,
                'balance_before' => $currentBalance,
                'balance_after' => 0,
                'order_id' => null,
                'order_payment_id' => null,
                'notes' => 'Credit cleared — COD customers pay on delivery.',
                'recorded_by' => $adminId,
                'recorded_by_driver' => null,
            ]);
        });
    }

    private function assertCreditCustomer(User $user): void
    {
        if (!$user->isCreditCustomer()) {
            throw new \InvalidArgumentException('Credit is only available for credit customers. COD customers pay on delivery.');
        }
    }
}
