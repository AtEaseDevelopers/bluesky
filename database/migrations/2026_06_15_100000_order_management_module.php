<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_slots', function (Blueprint $table) {
            $table->id();
            $table->date('slot_date');
            $table->time('time_start');
            $table->time('time_end');
            $table->unsignedInteger('max_orders')->nullable();
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();

            $table->index(['slot_date', 'is_enabled']);
        });

        Schema::create('order_payments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('order_id');
            $table->string('payment_method', 30);
            $table->decimal('amount', 15, 2);
            $table->string('payment_proof')->nullable();
            $table->unsignedBigInteger('recorded_by')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign('order_id')->references('id')->on('orders');
            $table->foreign('recorded_by')->references('id')->on('admins');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('customer_type', 15)->default('cod')->after('category');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('order_type', 20)->default('registered')->after('user_id');
            $table->string('walk_in_name')->nullable()->after('order_type');
            $table->string('walk_in_phone')->nullable()->after('walk_in_name');
            $table->unsignedBigInteger('delivery_slot_id')->nullable()->after('driver_id');
            $table->date('delivery_date')->nullable()->after('delivery_slot_id');
            $table->string('delivery_time_slot', 50)->nullable()->after('delivery_date');
            $table->decimal('subtotal', 15, 2)->default(0)->after('total_price');
            $table->decimal('delivery_fee', 15, 2)->default(0)->after('subtotal');
            $table->decimal('amount_adjustment', 15, 2)->default(0)->after('delivery_fee');
            $table->text('adjustment_remark')->nullable()->after('amount_adjustment');
            $table->date('payment_due_date')->nullable()->after('payment_method');
            $table->string('payment_status', 20)->default('unpaid')->after('payment_due_date');
            $table->decimal('paid_amount', 15, 2)->default(0)->after('payment_status');
            $table->string('invoice_number')->nullable()->after('paid_amount');
            $table->boolean('is_estimated')->default(true)->after('invoice_number');
            $table->timestamp('completed_at')->nullable()->after('is_estimated');
        });

        // user_id was already made nullable by an earlier migration; this raw
        // statement only applies to MySQL (SQLite, used in tests, rejects it).
        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE orders MODIFY user_id BIGINT UNSIGNED NULL');
        }

        DB::table('orders')->where('status', 'processing')->update(['status' => 'pending']);
        DB::table('orders')->where('status', 'delivering')->update(['status' => 'in_route']);
        DB::table('orders')->where('status', 'completed')->update(['status' => 'paid_completed']);

        DB::table('orders')->update([
            'subtotal' => DB::raw('total_price'),
        ]);

        DB::table('delivery_slots')->insert([
            [
                'slot_date' => now()->addDay()->toDateString(),
                'time_start' => '09:00:00',
                'time_end' => '12:00:00',
                'max_orders' => 50,
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'slot_date' => now()->addDay()->toDateString(),
                'time_start' => '14:00:00',
                'time_end' => '18:00:00',
                'max_orders' => 50,
                'is_enabled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    public function down(): void
    {
        DB::table('orders')->where('status', 'pending')->update(['status' => 'processing']);
        DB::table('orders')->where('status', 'in_route')->update(['status' => 'delivering']);
        DB::table('orders')->where('status', 'paid_completed')->update(['status' => 'completed']);

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'order_type', 'walk_in_name', 'walk_in_phone', 'delivery_slot_id',
                'delivery_date', 'delivery_time_slot', 'subtotal', 'delivery_fee',
                'amount_adjustment', 'adjustment_remark', 'payment_due_date',
                'payment_status', 'paid_amount', 'invoice_number', 'is_estimated', 'completed_at',
            ]);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('customer_type');
        });

        Schema::dropIfExists('order_payments');
        Schema::dropIfExists('delivery_slots');
    }
};
