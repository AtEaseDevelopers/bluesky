<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Repeatable one-time cleanup: remove all orders and dependent records.
     * Irreversible — down() cannot restore deleted data.
     */
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        DB::transaction(function () {
            $orderIds = DB::table('orders')->pluck('id');

            if ($orderIds->isEmpty()) {
                return;
            }

            $orderProductIds = Schema::hasTable('order_products')
                ? DB::table('order_products')->whereIn('order_id', $orderIds)->pluck('id')
                : collect();

            $orderPaymentIds = Schema::hasTable('order_payments')
                ? DB::table('order_payments')->whereIn('order_id', $orderIds)->pluck('id')
                : collect();

            if (Schema::hasTable('customer_credit_logs')) {
                DB::table('customer_credit_logs')
                    ->where(function ($query) use ($orderIds, $orderPaymentIds) {
                        $query->whereIn('order_id', $orderIds);
                        if ($orderPaymentIds->isNotEmpty()) {
                            $query->orWhereIn('order_payment_id', $orderPaymentIds);
                        }
                    })
                    ->delete();
            }

            if (Schema::hasTable('order_product_options') && $orderProductIds->isNotEmpty()) {
                DB::table('order_product_options')
                    ->whereIn('order_product_id', $orderProductIds)
                    ->delete();
            }

            if (Schema::hasTable('order_payments')) {
                DB::table('order_payments')->whereIn('order_id', $orderIds)->delete();
            }

            if (Schema::hasTable('bulk_payment_orders')) {
                DB::table('bulk_payment_orders')->whereIn('order_id', $orderIds)->delete();
            }

            if (Schema::hasTable('bulk_payments')) {
                DB::table('bulk_payments')->delete();
            }

            if (Schema::hasTable('stock_movements')) {
                DB::table('stock_movements')->whereIn('order_id', $orderIds)->delete();
            }

            if (Schema::hasTable('autocount_sync_logs')) {
                DB::table('autocount_sync_logs')->whereIn('order_id', $orderIds)->delete();
            }

            if (Schema::hasTable('order_products')) {
                DB::table('order_products')->whereIn('order_id', $orderIds)->delete();
            }

            DB::table('orders')->whereIn('id', $orderIds)->delete();
        });
    }

    public function down(): void
    {
        // Data deletion cannot be reversed.
    }
};
