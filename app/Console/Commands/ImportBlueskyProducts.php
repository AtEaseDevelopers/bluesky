<?php

namespace App\Console\Commands;

use App\CustomerCategoryProduct;
use App\Product;
use App\ProductCategory;
use App\ProductStock;
use App\Uom;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ImportBlueskyProducts extends Command
{
    protected $signature = 'products:import-bluesky
                            {file : Path to BLUESKY PRODUCT NAME.xlsx}
                            {--dry-run : Parse and preview without writing}
                            {--update : Update existing products matched by SKU}
                            {--default-price=0 : Default product price when not in the file}';

    protected $description = 'Import products from the Bluesky product name Excel file (UOM, SKU, category, name).';

    public function handle(): int
    {
        $path = $this->argument('file');

        if (!is_readable($path)) {
            $this->error("File not found or not readable: {$path}");

            return 1;
        }

        $rows = IOFactory::load($path)->getActiveSheet()->toArray(null, true, true, false);
        $products = $this->parseRows($rows);

        if ($products === []) {
            $this->error('No product rows found. Expected columns: UOM, PRODUCT CODE, PRODUCT CATEGORY, NAME.');

            return 1;
        }

        $this->info('Parsed ' . count($products) . ' product row(s).');

        if ($this->option('dry-run')) {
            $this->table(
                ['UOM', 'SKU', 'Category', 'Name', 'Sell In', 'Description'],
                array_map(fn ($row) => [
                    $row['uom'],
                    $row['sku'],
                    $row['category'],
                    $row['name'],
                    $row['sell_in'],
                    $row['description'],
                ], array_slice($products, 0, 15))
            );

            if (count($products) > 15) {
                $this->line('... and ' . (count($products) - 15) . ' more row(s).');
            }

            $this->warn('Dry run only — no database changes made.');

            return 0;
        }

        $defaultPrice = (float) $this->option('default-price');
        $update = (bool) $this->option('update');
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($products, $defaultPrice, $update, &$created, &$updated, &$skipped) {
            foreach ($products as $row) {
                $uom = Uom::firstOrCreate(['uom_name' => $row['uom']]);
                $category = ProductCategory::firstOrCreate(['category_name' => $row['category']]);

                $existing = Product::where('sku', $row['sku'])->first();

                if ($existing && !$update) {
                    $skipped++;
                    continue;
                }

                $payload = [
                    'uom_id' => $uom->id,
                    'product_category_id' => $category->id,
                    'name' => $row['name'],
                    'description' => $row['description'],
                    'sku' => $row['sku'],
                    'price' => $defaultPrice,
                    'weight' => 1,
                    'images' => json_encode([]),
                    'status' => Product::$status['active'],
                    'remark' => '',
                    'nos' => 1,
                    'show_weight' => $row['show_weight'],
                    'show_qty' => $row['show_qty'],
                    'sell_in' => $row['sell_in'],
                ];

                if ($existing) {
                    $existing->update($payload);
                    $product = $existing->fresh();
                    $updated++;
                } else {
                    $product = Product::create($payload);
                    $created++;
                }

                ProductStock::firstOrCreate(
                    ['product_id' => $product->id],
                    ['quantity' => 0, 'weight' => 0]
                );

                foreach (DB::table('customer_categories')->pluck('id') as $categoryId) {
                    CustomerCategoryProduct::firstOrCreate([
                        'customer_category_id' => $categoryId,
                        'product_id' => $product->id,
                    ]);
                }
            }
        });

        $this->info("Import complete. Created: {$created}, updated: {$updated}, skipped: {$skipped}.");

        if ($skipped > 0 && !$update) {
            $this->line('Re-run with --update to refresh existing SKUs.');
        }

        return 0;
    }

    /**
     * @return list<array{uom:string,sku:string,category:string,name:string,description:string,sell_in:string,show_weight:int,show_qty:int}>
     */
    private function parseRows(array $rows): array
    {
        $products = [];
        $seenSkus = [];

        foreach ($rows as $index => $row) {
            if ($index < 2) {
                continue;
            }

            $uom = $this->cell($row, 0);
            $sku = $this->cell($row, 1);
            $category = $this->cell($row, 2);
            $name = $this->cell($row, 3);
            $chinese = $this->cell($row, 4);

            if ($sku === '' || $name === '') {
                continue;
            }

            if (strcasecmp($sku, 'PRODUCT CODE') === 0) {
                continue;
            }

            $sku = strtoupper($sku);
            if (isset($seenSkus[$sku])) {
                $this->warn("Duplicate SKU skipped on row " . ($index + 1) . ": {$sku}");

                continue;
            }
            $seenSkus[$sku] = true;

            $uom = strtoupper(trim($uom));
            $sellIn = $this->sellInForUom($uom);
            $flags = Product::reportFlagsForSellIn($sellIn);

            $description = $chinese !== '' ? trim($name . ' / ' . $chinese) : $name;

            $products[] = [
                'uom' => $uom !== '' ? $uom : 'KG',
                'sku' => $sku,
                'category' => $this->titleCategory($category),
                'name' => preg_replace('/\s+/', ' ', trim($name)) ?: $sku,
                'description' => mb_substr($description, 0, 200),
                'sell_in' => $sellIn,
                'show_weight' => $flags['show_weight'] ? 1 : 0,
                'show_qty' => $flags['show_qty'] ? 1 : 0,
            ];
        }

        return $products;
    }

    private function cell(array $row, int $index): string
    {
        $value = $row[$index] ?? '';

        return trim((string) $value);
    }

    private function sellInForUom(string $uom): string
    {
        return match ($uom) {
            'PCS', 'BAG', 'BOX', 'PKT', 'PACK' => Product::SELL_IN_QTY,
            default => Product::SELL_IN_WEIGHT,
        };
    }

    private function titleCategory(string $category): string
    {
        $category = preg_replace('/\s+/', ' ', trim($category));

        return $category !== '' ? $category : 'UNCATEGORIZED';
    }
}
