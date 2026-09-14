<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * The `credit` order status is retired: a delivered credit-term order stays in
 * `delivered` and is gated from completion by its unsettled credit-term amount
 * instead of a distinct holding state. Fold every order parked in `credit` back
 * into `delivered`; their credit-term charges and ledger entries are untouched,
 * so the settlement flow and outstanding balances carry over unchanged.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('orders')
            ->where('status', 'credit')
            ->update(['status' => 'delivered']);
    }

    public function down(): void
    {
        // Re-park delivered orders that still owe on a confirmed credit-term
        // charge — the exact set that carried the `credit` status before.
        $ids = DB::table('orders')
            ->where('orders.status', 'delivered')
            ->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('order_payments')
                    ->whereColumn('order_payments.order_id', 'orders.id')
                    ->where('order_payments.status', 'confirmed')
                    ->where('order_payments.payment_method', 'credit-term');
            })
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            DB::table('orders')->whereIn('id', $ids)->update(['status' => 'credit']);
        }
    }
};
