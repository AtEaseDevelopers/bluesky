<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('credit_balance', 12, 2)->default(0)->after('customer_type');
        });

        Schema::create('customer_credit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('type', 30);
            $table->decimal('amount', 12, 2);
            $table->decimal('balance_before', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->unsignedBigInteger('order_id')->nullable();
            $table->unsignedBigInteger('order_payment_id')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->unsignedBigInteger('recorded_by_driver')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            $table->index(['user_id', 'created_at']);
        });

        Schema::table('order_payments', function (Blueprint $table) {
            $table->unsignedBigInteger('recorded_by_driver')->nullable()->after('recorded_by');
        });

        Schema::table('drivers', function (Blueprint $table) {
            $table->string('name')->nullable()->after('id');
            $table->string('phone', 30)->nullable()->unique()->after('name');
            $table->string('pin_hash')->nullable()->after('phone');
            $table->string('api_token', 80)->nullable()->unique()->after('pin_hash');
            $table->boolean('is_active')->default(true)->after('api_token');
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $table->dropColumn(['name', 'phone', 'pin_hash', 'api_token', 'is_active']);
        });

        Schema::table('order_payments', function (Blueprint $table) {
            $table->dropColumn('recorded_by_driver');
        });

        Schema::dropIfExists('customer_credit_logs');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('credit_balance');
        });
    }
};
