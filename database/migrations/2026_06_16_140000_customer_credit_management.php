<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'credit_balance')) {
                $table->decimal('credit_balance', 12, 2)->default(0)->after('customer_type');
            }
        });

        if (!Schema::hasTable('customer_credit_logs')) {
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
        }

        Schema::table('order_payments', function (Blueprint $table) {
            if (!Schema::hasColumn('order_payments', 'recorded_by_driver')) {
                $table->unsignedBigInteger('recorded_by_driver')->nullable()->after('recorded_by');
            }
        });

        Schema::table('drivers', function (Blueprint $table) {
            if (!Schema::hasColumn('drivers', 'name')) {
                $table->string('name')->nullable()->after('id');
            }
            if (!Schema::hasColumn('drivers', 'phone')) {
                $table->string('phone', 30)->nullable()->unique()->after('name');
            }
            if (!Schema::hasColumn('drivers', 'pin_hash')) {
                $table->string('pin_hash')->nullable()->after('phone');
            }
            if (!Schema::hasColumn('drivers', 'api_token')) {
                $table->string('api_token', 80)->nullable()->unique()->after('pin_hash');
            }
            if (!Schema::hasColumn('drivers', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('api_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('drivers', function (Blueprint $table) {
            $columns = array_filter(
                ['name', 'phone', 'pin_hash', 'api_token', 'is_active'],
                fn ($column) => Schema::hasColumn('drivers', $column)
            );

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('order_payments', function (Blueprint $table) {
            if (Schema::hasColumn('order_payments', 'recorded_by_driver')) {
                $table->dropColumn('recorded_by_driver');
            }
        });

        Schema::dropIfExists('customer_credit_logs');

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'credit_balance')) {
                $table->dropColumn('credit_balance');
            }
        });
    }
};
