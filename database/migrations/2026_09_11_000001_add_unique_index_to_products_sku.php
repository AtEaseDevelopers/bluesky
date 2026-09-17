<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Enforce that no two products share the same SKU.
     * Blank SKUs are stored as NULL (see ConvertEmptyStringsToNull), and MySQL
     * allows multiple NULLs in a unique index, so products without a SKU are unaffected.
     */
    public function up(): void
    {
        $duplicateSkus = DB::table('products')
            ->select('sku')
            ->whereNotNull('sku')
            ->where('sku', '!=', '')
            ->groupBy('sku')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('sku');

        foreach ($duplicateSkus as $sku) {
            $ids = DB::table('products')
                ->where('sku', $sku)
                ->orderBy('id')
                ->pluck('id');

            // Keep the oldest product's SKU; clear duplicates so admin can assign correct codes.
            foreach ($ids->slice(1) as $duplicateId) {
                DB::table('products')
                    ->where('id', $duplicateId)
                    ->update(['sku' => null]);
            }
        }

        Schema::table('products', function (Blueprint $table) {
            $table->unique('sku');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique('products_sku_unique');
        });
    }
};
