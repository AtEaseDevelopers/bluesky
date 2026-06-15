<?php

namespace App\Imports;

use App\Product;
use App\ProductCategory;
use App\ProductOption;
use App\ProductOptionItem;
use App\Uom;
use Exception;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;

class ProductsImport implements ToCollection
{
    /**
     * @param Collection $collection
     */
    public function collection(Collection $collection)
    {
        DB::beginTransaction();

        try {
            foreach ($collection as $key => $row) {
                if ($key === 0) {
                    continue; // Skip header row
                }

                // uom
                $uom = Uom::firstOrCreate(['uom_name' => trim($row[0])]);

                // product category
                $category = ProductCategory::firstOrCreate(['category_name' => trim($row[1])]);

                // Handle images
                $images = $this->parseImages($row[7]);
                
                $show_weight = (strtolower(trim($row[11])) == 'yes') ? 1 : 0;
                $show_qty = (strtolower(trim($row[12])) == 'yes') ? 1 : 0;

                $product = Product::create(
                    [
                        'uom_id' => $uom->id,
                        'product_category_id' => $category->id,
                        'name' => trim($row[2]),
                        'description' => trim($row[3]),
                        'sku' => trim($row[4]),
                        'price' => floatval($row[5]),
                        'weight' => floatval($row[6]),
                        'images' => json_encode($images),
                        'status' => trim($row[8]),
                        'remark' => trim($row[9]),
                        'nos' => intval($row[10]),
                        'show_weight' => $show_weight,
                        'show_qty' => $show_qty,
                        'sell_in' => strtolower(trim($row[13]))
                    ]
                );

                // Handle options
                $this->processOptions($product, $row[14]);
            }

            DB::commit();
            return back()->with('success', 'Products imported successfully!');
            
        } catch (Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Products import failed: ' . $e->getMessage());
        }
    }

    /**
     * Parse images from various formats
     */
    private function parseImages($imagesInput): array
    {
        if (empty($imagesInput)) {
            return [];
        }

        if (is_array($imagesInput)) {
            return array_filter($imagesInput);
        }

        // Try JSON decoding first
        $decoded = json_decode($imagesInput, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
            return array_filter($decoded);
        }

        // Handle comma-separated string
        return array_filter(array_map('trim', explode(',', $imagesInput)));
    }

    /**
     * Process product options
     */
    private function processOptions(Product $product, $optionsInput): void
    {
        if (empty($optionsInput)) {
            return;
        }

        $optionsArray = [];

        // Try JSON decoding
        if (is_string($optionsInput)) {
            $decoded = json_decode($optionsInput, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                $optionsArray = $decoded;
            }
        } elseif (is_array($optionsInput)) {
            $optionsArray = $optionsInput;
        }

        foreach ($optionsArray as $optionName => $items) {
            if (!is_array($items)) {
                continue;
            }

            $productOption = ProductOption::create(
                [
                    'product_id' => $product->id,
                    'name' => trim($optionName),
                    'mandatory' => 1,
                    'status' => ProductOption::$status['active'],
                ]
            );

            foreach ($items as $item) {
                if (!empty(trim($item))) {
                    ProductOptionItem::create(
                        [
                            'product_id' => $product->id,
                            'product_option_id' => $productOption->id,
                            'name' => trim($item),
                            'status' => ProductOptionItem::$status['active'],
                        ]
                    );
                }
            }
        }
    }
}
