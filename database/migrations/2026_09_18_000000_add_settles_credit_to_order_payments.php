<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            // Marks a confirmed payment that settled the customer's credit-term
            // balance on the ledger rather than the order's own balance. It stays
            // visible on the order as an approved payment, but must be excluded
            // from paid_amount / paymentBreakdown so it is not double-counted.
            $table->boolean('settles_credit')->default(false)->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropColumn('settles_credit');
        });
    }
};
