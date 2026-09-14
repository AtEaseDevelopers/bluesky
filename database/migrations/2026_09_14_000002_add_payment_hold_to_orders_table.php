<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (!Schema::hasColumn('orders', 'payment_held_at')) {
                $table->timestamp('payment_held_at')->nullable()->after('paid_amount');
            }
            if (!Schema::hasColumn('orders', 'payment_held_by')) {
                $table->unsignedBigInteger('payment_held_by')->nullable()->after('payment_held_at');
            }
        });

        // "on_hold" is no longer a delivery status — it is now a payment_status.
        // Any legacy order parked in the old delivery-hold state was in route with
        // the goods still on the driver (never delivered), so the truthful mapping
        // is back to in_route. It can be re-delivered under the new payment-hold flow.
        DB::table('orders')->where('status', 'on_hold')->update(['status' => 'in_route']);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            foreach (['payment_held_by', 'payment_held_at'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
