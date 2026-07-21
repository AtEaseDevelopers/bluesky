<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revenue_monster_transactions', function (Blueprint $table) {
            $table->id();
            // Order this checkout is collecting payment for (no FK: orders.id
            // signedness has drifted on the dev DB — see schema-drift memory).
            $table->unsignedBigInteger('order_id')->index();
            // Our own reference sent to RM as order.id; echoed back on callback.
            $table->string('reference', 64)->unique();
            // RM identifiers (populated from the create response / callback).
            $table->string('checkout_id', 100)->nullable()->index();
            $table->string('transaction_id', 100)->nullable()->index();
            $table->text('qr_code_url')->nullable();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 10)->default('MYR');
            // pending | paid | failed
            $table->string('status', 20)->default('pending')->index();
            // OrderPayment created once the payment is confirmed (idempotency guard).
            $table->unsignedBigInteger('order_payment_id')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revenue_monster_transactions');
    }
};
