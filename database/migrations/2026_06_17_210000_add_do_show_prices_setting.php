<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('order_field_settings')) {
            return;
        }

        $exists = DB::table('order_field_settings')->where('key', 'do_show_prices')->exists();
        if (!$exists) {
            DB::table('order_field_settings')->insert([
                'key' => 'do_show_prices',
                'value' => '0',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('order_field_settings')) {
            DB::table('order_field_settings')->where('key', 'do_show_prices')->delete();
        }
    }
};
