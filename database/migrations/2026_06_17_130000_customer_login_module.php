<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class CustomerLoginModule extends Migration
{
    public function up()
    {
        if (!Schema::hasTable('order_field_settings')) {
            Schema::create('order_field_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value');
                $table->timestamps();
            });

            DB::table('order_field_settings')->insert([
                [
                    'key' => 'weight_presets',
                    'value' => json_encode(['1', '1.5', '2', '2.5', '3']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'key' => 'situation_options',
                    'value' => json_encode(['live', 'kill', 'clean']),
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'key' => 'situation_label',
                    'value' => 'Situation',
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'registration_token')) {
                $table->string('registration_token', 100)->nullable()->unique()->after('login_code');
            }
            if (!Schema::hasColumn('users', 'registration_token_expires_at')) {
                $table->timestamp('registration_token_expires_at')->nullable()->after('registration_token');
            }
            if (!Schema::hasColumn('users', 'registration_completed_at')) {
                $table->timestamp('registration_completed_at')->nullable()->after('registration_token_expires_at');
            }
        });

        if (!Schema::hasTable('bulk_payments')) {
            Schema::create('bulk_payments', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id');
                $table->decimal('total_amount', 12, 2);
                $table->string('payment_method', 30);
                $table->string('payment_proof')->nullable();
                $table->string('status', 20)->default('pending');
                $table->text('notes')->nullable();
                $table->timestamps();

                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('bulk_payment_orders')) {
            Schema::create('bulk_payment_orders', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('bulk_payment_id');
                $table->unsignedBigInteger('order_id');
                $table->decimal('amount', 12, 2);
                $table->timestamps();

                $table->foreign('bulk_payment_id')->references('id')->on('bulk_payments')->onDelete('cascade');
                $table->foreign('order_id')->references('id')->on('orders');
            });
        }

        if (!Schema::hasColumn('order_payments', 'bulk_payment_id')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->unsignedBigInteger('bulk_payment_id')->nullable()->after('submitted_by_user_id');
            });
        }
    }

    public function down()
    {
        if (Schema::hasColumn('order_payments', 'bulk_payment_id')) {
            Schema::table('order_payments', function (Blueprint $table) {
                $table->dropColumn('bulk_payment_id');
            });
        }

        Schema::dropIfExists('bulk_payment_orders');
        Schema::dropIfExists('bulk_payments');
        Schema::dropIfExists('order_field_settings');

        Schema::table('users', function (Blueprint $table) {
            $columns = ['registration_completed_at', 'registration_token_expires_at', 'registration_token'];
            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
}
