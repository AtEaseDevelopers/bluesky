<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders') || !Schema::hasColumn('orders', 'billing_address')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE orders MODIFY billing_address VARCHAR(255) NULL');
        } else {
            Schema::table('orders', function ($table) {
                $table->string('billing_address')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('orders') || !Schema::hasColumn('orders', 'billing_address')) {
            return;
        }

        if (DB::connection()->getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE orders MODIFY billing_address VARCHAR(255) NOT NULL');
        } else {
            Schema::table('orders', function ($table) {
                $table->string('billing_address')->nullable(false)->change();
            });
        }
    }
};
