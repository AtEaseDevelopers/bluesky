<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('orders')) {
            return;
        }

        DB::table('orders')
            ->where('status', 'customer_reviewing')
            ->update(['status' => 'packing']);
    }

    public function down(): void
    {
        // Status removal is not reversible.
    }
};
