<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDriverPaymentFieldsToOrdersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('paid_amount', 15, 2)->nullable()->after('transfer_slip');
            $table->string('payment_proof')->nullable()->after('paid_amount');
            $table->timestamp('payment_collected_at')->nullable()->after('payment_proof');
            $table->unsignedBigInteger('payment_collected_by')->nullable()->after('payment_collected_at');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['paid_amount', 'payment_proof', 'payment_collected_at', 'payment_collected_by']);
        });
    }
}
