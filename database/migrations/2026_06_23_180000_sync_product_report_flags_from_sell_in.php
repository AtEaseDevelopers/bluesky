<?php

use App\Product;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Product::query()->each(function (Product $product) {
            $flags = Product::reportFlagsForSellIn($product->sell_in);
            $product->forceFill($flags)->saveQuietly();
        });
    }

    public function down(): void
    {
        // No rollback — previous manual flags are not preserved.
    }
};
