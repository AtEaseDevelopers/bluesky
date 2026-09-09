<?php

namespace App\Services;

use App\Order;
use App\ProductVisibility;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerLifecycleService
{
    public function hasOrders(User $customer): bool
    {
        return Order::query()->where('user_id', $customer->id)->exists();
    }

    public function canDelete(User $customer): bool
    {
        return !$this->hasOrders($customer);
    }

    public function delete(User $customer): void
    {
        if (!$this->canDelete($customer)) {
            throw new \InvalidArgumentException(__('customers.delete_blocked_has_orders'));
        }

        DB::transaction(function () use ($customer) {
            ProductVisibility::query()->where('user_id', $customer->id)->delete();

            if (Schema::hasTable('customer_drivers')) {
                DB::table('customer_drivers')->where('user_id', $customer->id)->delete();
            }

            $cartIds = DB::table('carts')->where('user_id', $customer->id)->pluck('id');
            if ($cartIds->isNotEmpty()) {
                $cartProductIds = DB::table('cart_products')->whereIn('cart_id', $cartIds)->pluck('id');
                if ($cartProductIds->isNotEmpty() && Schema::hasTable('cart_product_options')) {
                    DB::table('cart_product_options')->whereIn('cart_product_id', $cartProductIds)->delete();
                }
                if ($cartProductIds->isNotEmpty()) {
                    DB::table('cart_products')->whereIn('id', $cartProductIds)->delete();
                }
                DB::table('carts')->whereIn('id', $cartIds)->delete();
            }

            if (Schema::hasTable('customer_credit_logs')) {
                DB::table('customer_credit_logs')->where('user_id', $customer->id)->delete();
            }

            $customer->delete();
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function buildStatusUpdates(User $customer, string $adminStatus): array
    {
        $willBeActive = $adminStatus !== 'inactive';
        $wasActive = $customer->isActiveCustomer();
        $hasAccNo = trim((string) $customer->sql_customer_code) !== '';

        if ($wasActive === $willBeActive) {
            return [
                'status' => $willBeActive
                    ? User::$user_status['active']
                    : User::$user_status['inactive'],
            ];
        }

        if ($willBeActive) {
            $updates = ['status' => User::$user_status['active']];
            if ($hasAccNo) {
                $updates['autocount_sync_status'] = 'pending_sync';
                $updates['autocount_synced_at'] = null;
            }

            return $updates;
        }

        $updates = ['status' => User::$user_status['inactive']];
        if ($hasAccNo) {
            $updates['autocount_sync_status'] = 'pending_inactive';
            $updates['autocount_synced_at'] = null;
        }

        return $updates;
    }

    public function deactivate(User $customer): void
    {
        if (!$this->hasOrders($customer)) {
            throw new \InvalidArgumentException(__('customers.deactivate_use_delete'));
        }

        if (!$customer->isActiveCustomer()) {
            return;
        }

        $customer->update($this->buildStatusUpdates($customer, 'inactive'));
    }
}
