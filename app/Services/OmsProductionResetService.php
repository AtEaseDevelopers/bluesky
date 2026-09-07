<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class OmsProductionResetService
{
    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return [
            'orders' => $this->count('orders'),
            'order_products' => $this->count('order_products'),
            'order_payments' => $this->count('order_payments'),
            'stock_movements' => $this->count('stock_movements'),
            'revenue_monster_transactions' => $this->count('revenue_monster_transactions'),
            'carts' => $this->count('carts'),
            'customers' => $this->count('users'),
        ];
    }

    /**
     * @return array<string, int>
     */
    public function purgeOrdersAndRelated(): array
    {
        $deleted = [];

        DB::transaction(function () use (&$deleted) {
            if (!Schema::hasTable('orders')) {
                return;
            }

            $orderIds = DB::table('orders')->pluck('id');

            if ($orderIds->isEmpty()) {
                $deleted['orders'] = 0;

                return;
            }

            $orderProductIds = Schema::hasTable('order_products')
                ? DB::table('order_products')->whereIn('order_id', $orderIds)->pluck('id')
                : collect();

            $orderPaymentIds = Schema::hasTable('order_payments')
                ? DB::table('order_payments')->whereIn('order_id', $orderIds)->pluck('id')
                : collect();

            $deleted['customer_credit_logs'] = $this->deleteIfExists('customer_credit_logs', function ($query) use ($orderIds, $orderPaymentIds) {
                $query->where(function ($inner) use ($orderIds, $orderPaymentIds) {
                    $inner->whereIn('order_id', $orderIds);
                    if ($orderPaymentIds->isNotEmpty()) {
                        $inner->orWhereIn('order_payment_id', $orderPaymentIds);
                    }
                });
            });

            if (Schema::hasTable('order_product_options') && $orderProductIds->isNotEmpty()) {
                $deleted['order_product_options'] = DB::table('order_product_options')
                    ->whereIn('order_product_id', $orderProductIds)
                    ->delete();
            }

            $deleted['revenue_monster_transactions'] = $this->deleteIfExists('revenue_monster_transactions', function ($query) use ($orderIds) {
                $query->whereIn('order_id', $orderIds);
            });

            $deleted['order_payments'] = $this->deleteIfExists('order_payments', function ($query) use ($orderIds) {
                $query->whereIn('order_id', $orderIds);
            });

            $deleted['bulk_payment_orders'] = $this->deleteIfExists('bulk_payment_orders', function ($query) use ($orderIds) {
                $query->whereIn('order_id', $orderIds);
            });

            $deleted['bulk_payments'] = $this->deleteIfExists('bulk_payments');

            $deleted['stock_movements'] = $this->deleteIfExists('stock_movements', function ($query) use ($orderIds) {
                $query->whereIn('order_id', $orderIds);
            });

            $deleted['autocount_sync_logs'] = $this->deleteIfExists('autocount_sync_logs', function ($query) use ($orderIds) {
                $query->whereIn('order_id', $orderIds);
            });

            $deleted['public_order_links'] = $this->deleteIfExists('public_order_links', function ($query) use ($orderIds) {
                $query->whereIn('order_id', $orderIds);
            });

            $deleted['order_products'] = $this->deleteIfExists('order_products', function ($query) use ($orderIds) {
                $query->whereIn('order_id', $orderIds);
            });

            $deleted['orders'] = DB::table('orders')->whereIn('id', $orderIds)->delete();
        });

        $deleted['order_files'] = $this->purgeOrderStorage();

        return $deleted;
    }

    /**
     * @return array<string, int>
     */
    public function purgeCustomersAndCarts(): array
    {
        $deleted = [];

        DB::transaction(function () use (&$deleted) {
            if (Schema::hasTable('cart_product_options') && Schema::hasTable('cart_products')) {
                $cartProductIds = DB::table('cart_products')->pluck('id');
                if ($cartProductIds->isNotEmpty()) {
                    $deleted['cart_product_options'] = DB::table('cart_product_options')
                        ->whereIn('cart_product_id', $cartProductIds)
                        ->delete();
                }
            }

            $deleted['cart_products'] = $this->deleteIfExists('cart_products');
            $deleted['carts'] = $this->deleteIfExists('carts');
            $deleted['customer_credit_logs'] = $this->deleteIfExists('customer_credit_logs');
            $deleted['product_visibilities'] = $this->deleteIfExists('product_visibilities');
            $deleted['customer_drivers'] = $this->deleteIfExists('customer_drivers');
            $deleted['revenue_monster_transactions'] = $this->deleteIfExists('revenue_monster_transactions');
            $deleted['public_order_links'] = $this->deleteIfExists('public_order_links');
            $deleted['users'] = $this->deleteIfExists('users');
        });

        return $deleted;
    }

    private function purgeOrderStorage(): int
    {
        if (!Storage::disk('local')->exists('orders')) {
            return 0;
        }

        $files = Storage::disk('local')->allFiles('orders');

        foreach ($files as $file) {
            Storage::disk('local')->delete($file);
        }

        return count($files);
    }

    private function count(string $table): int
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        return (int) DB::table($table)->count();
    }

    /**
     * @param  callable(\Illuminate\Database\Query\Builder): void|null  $constraint
     */
    private function deleteIfExists(string $table, ?callable $constraint = null): int
    {
        if (!Schema::hasTable($table)) {
            return 0;
        }

        $query = DB::table($table);
        if ($constraint !== null) {
            $constraint($query);
        }

        return (int) $query->delete();
    }
}
