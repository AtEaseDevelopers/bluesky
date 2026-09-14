<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddPaymentDetailsToCustomerCreditLogsTable extends Migration
{
    public function up()
    {
        Schema::table('customer_credit_logs', function (Blueprint $table) {
            // Real-money settlement details captured when clearing a credit order:
            // how the customer paid and the uploaded proof filename (stored under
            // the related order's payments folder).
            $table->string('payment_method')->nullable()->after('order_payment_id');
            $table->string('payment_proof')->nullable()->after('payment_method');
        });
    }

    public function down()
    {
        Schema::table('customer_credit_logs', function (Blueprint $table) {
            $table->dropColumn(['payment_method', 'payment_proof']);
        });
    }
}
