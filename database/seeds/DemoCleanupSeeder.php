<?php

use App\Product;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DemoCleanupSeeder extends Seeder
{
    public function run(): void
    {
        Product::query()->update(['status' => Product::$status['removed']]);

        DB::table('product_visibilities')->delete();
        DB::table('product_daily_prices')->delete();
        DB::table('customer_category_products')->delete();

        $uomId = DB::table('uoms')->where('uom_name', 'KG')->value('id')
            ?? DB::table('uoms')->value('id');

        $seafood = [
            ['name' => 'Live Tiger Prawns', 'sku' => 'SF-PRAWN', 'price' => 85.00],
            ['name' => 'Live Garoupa', 'sku' => 'SF-GAROUPA', 'price' => 120.00],
            ['name' => 'Live Mud Crab', 'sku' => 'SF-CRAB', 'price' => 95.00],
            ['name' => 'Live Squid', 'sku' => 'SF-SQUID', 'price' => 45.00],
            ['name' => 'Live Clams', 'sku' => 'SF-CLAM', 'price' => 28.00],
        ];

        foreach ($seafood as $item) {
            $product = Product::create([
                'uom_id' => $uomId,
                'name' => $item['name'],
                'sku' => $item['sku'],
                'price' => $item['price'],
                'weight' => 1,
                'status' => Product::$status['active'],
                'show_weight' => 1,
                'show_qty' => 0,
                'sell_in' => 'qty_bill_weight',
            ]);

            DB::table('product_stocks')->insert([
                'product_id' => $product->id,
                'quantity' => 50,
                'weight' => 50,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $productIds = Product::where('status', Product::$status['active'])->pluck('id');
        $userIds = DB::table('users')->pluck('id');

        foreach ($userIds as $userId) {
            foreach ($productIds as $productId) {
                DB::table('product_visibilities')->insert([
                    'user_id' => $userId,
                    'product_id' => $productId,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        if (!DB::table('uoms')->where('uom_name', 'KG')->exists()) {
            DB::table('uoms')->insert([
                'uom_name' => 'KG',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
