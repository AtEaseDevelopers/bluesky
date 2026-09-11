<?php

use App\Product;
use App\ProductCategory;
use App\ProductStock;
use App\Uom;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Dummy products for manually testing integer-only selling quantity.
 *
 * Creates one product per sell_in type (idempotent by SKU):
 *   - qty              : quantity must be a whole number (1, 2, 3, ...)
 *   - qty_bill_weight  : whole-number quantity + decimal weight
 *   - weight           : decimal weight (quantity not used)
 *
 * Run: php artisan db:seed --class=QuantityTestSeeder
 */
class QuantityTestSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            $uomId = Uom::where('uom_name', 'KG')->value('id') ?? Uom::query()->value('id');
            $categoryId = ProductCategory::query()->value('id');

            $specs = [
                ['sku' => 'QTYTEST-1', 'name' => 'TEST Qty Prawn (whole-number qty)', 'sell_in' => Product::SELL_IN_QTY, 'price' => 12.50],
                ['sku' => 'QBWTEST-1', 'name' => 'TEST Qty-Bill-Weight Fish (int qty + decimal weight)', 'sell_in' => Product::SELL_IN_QTY_BILL_WEIGHT, 'price' => 28.00],
                ['sku' => 'WGTTEST-1', 'name' => 'TEST Weight Crab (decimal weight)', 'sell_in' => Product::SELL_IN_WEIGHT, 'price' => 45.00],
            ];

            foreach ($specs as $spec) {
                $product = Product::where('sku', $spec['sku'])->first();

                if ($product) {
                    ProductStock::where('product_id', $product->id)->delete();
                    $product->forceDelete();
                }

                $product = Product::forceCreate([
                    'uom_id' => $uomId,
                    'product_category_id' => $categoryId,
                    'name' => $spec['name'],
                    'description' => 'Dummy product for integer-quantity testing.',
                    'sku' => $spec['sku'],
                    'price' => $spec['price'],
                    'weight' => 1,
                    'images' => null,
                    'status' => Product::$status['active'],
                    'sell_in' => $spec['sell_in'],
                ]);

                // Storefront (member + guest + POS) only lists products carrying stock.
                ProductStock::forceCreate([
                    'product_id' => $product->id,
                    'quantity' => 100,
                    'weight' => 100,
                ]);

                $this->command->line(sprintf(
                    '  #%d  %-10s sell_in=%-16s show_qty=%d show_weight=%d',
                    $product->id,
                    $product->sku,
                    $product->sell_in,
                    $product->show_qty,
                    $product->show_weight
                ));
            }
        });

        $this->command->info('Quantity-test dummy products seeded.');
    }
}
