<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->text('weight_presets')->nullable()->after('sell_in');
        });

        if (Schema::hasTable('order_field_settings')) {
            $globalPresets = DB::table('order_field_settings')
                ->where('key', 'weight_presets')
                ->value('value');

            if ($globalPresets) {
                DB::table('products')
                    ->whereIn('sell_in', ['weight', 'qty_bill_weight'])
                    ->whereNull('weight_presets')
                    ->update(['weight_presets' => $globalPresets]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('weight_presets');
        });
    }
};
