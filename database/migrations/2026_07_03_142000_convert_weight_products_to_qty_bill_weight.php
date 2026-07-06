<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

class ConvertWeightProductsToQtyBillWeight extends Migration
{
    public function up(): void
    {
        DB::table('products')
            ->where('sell_in', 'weight')
            ->update([
                'sell_in' => 'qty_bill_weight',
                'show_qty' => 1,
                'show_weight' => 1,
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        DB::table('products')
            ->where('sell_in', 'qty_bill_weight')
            ->update([
                'sell_in' => 'weight',
                'show_qty' => 0,
                'updated_at' => now(),
            ]);
    }
}
