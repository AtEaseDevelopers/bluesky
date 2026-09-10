<?php

namespace App\Console\Commands;

use App\Product;
use App\ProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReindexProductSkus extends Command
{
    protected $signature = 'products:reindex-sku
                            {--category= : Limit to a single product category id}
                            {--dry-run : Show the plan only; make no changes}
                            {--force : Required to actually apply the changes}';

    protected $description = 'Reassign product SKUs to a clean sequential run per category code (e.g. A0001, A0002).';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $categories = ProductCategory::query()
            ->whereNotNull('code')
            ->when($this->option('category'), fn ($q, $id) => $q->where('id', $id))
            ->orderBy('code')
            ->get();

        $this->line('');
        $this->info('Product SKU reindex plan');
        $this->line('========================');

        // --- Build plan -----------------------------------------------------
        $changes  = [];   // rows to display + apply: [id, name, category, old, new]
        $warnings = [];

        foreach ($categories as $category) {
            $products = Product::where('product_category_id', $category->id)
                ->orderBy('id')
                ->get(['id', 'name', 'sku']);

            $seq = 0;
            foreach ($products as $product) {
                $seq++;
                $new = $category->code . str_pad((string) $seq, 4, '0', STR_PAD_LEFT);
                if ($product->sku === $new) {
                    continue;
                }
                $changes[] = [
                    'id'   => $product->id,
                    'name' => $product->name,
                    'cat'  => $category->code,
                    'old'  => $product->sku ?: '—',
                    'new'  => $new,
                ];
            }
        }

        // Products left untouched because their category has no code.
        $skipped = Product::query()
            ->whereNotNull('product_category_id')
            ->whereIn('product_category_id', ProductCategory::whereNull('code')->select('id'))
            ->count();
        if ($skipped) {
            $warnings[] = "{$skipped} product(s) sit in categories without a code — SKU left untouched.";
        }

        $noCategory = Product::query()->whereNull('product_category_id')->count();
        if ($noCategory) {
            $warnings[] = "{$noCategory} product(s) have no category — SKU left untouched.";
        }

        // --- Show plan ------------------------------------------------------
        $this->line('');
        if ($changes) {
            $this->comment(count($changes) . ' SKU(s) to reassign');
            $this->table(
                ['Product ID', 'Name', 'Code', 'Old SKU', 'New SKU'],
                array_map(fn ($c) => [$c['id'], $c['name'], $c['cat'], $c['old'], $c['new']], $changes)
            );
        } else {
            $this->info('All SKUs already in sequence — nothing to reassign.');
        }

        if ($warnings) {
            $this->line('');
            $this->warn('Warnings:');
            foreach ($warnings as $w) {
                $this->line('  • ' . $w);
            }
        }

        // --- Gate -----------------------------------------------------------
        if ($dryRun) {
            $this->line('');
            $this->warn('Dry run only — no changes made. Re-run with --force to apply.');

            return self::SUCCESS;
        }

        if (! $changes) {
            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->line('');
            $this->error('This rewrites product SKUs. Re-run with --dry-run to preview or --force to apply.');

            return self::FAILURE;
        }

        // --- Apply ----------------------------------------------------------
        DB::transaction(function () use ($changes) {
            foreach ($changes as $c) {
                DB::table('products')->where('id', $c['id'])->update(['sku' => $c['new']]);
            }
        });

        $this->line('');
        $this->info('Done. ' . count($changes) . ' SKU(s) reindexed.');

        return self::SUCCESS;
    }
}
