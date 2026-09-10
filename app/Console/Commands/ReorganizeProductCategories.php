<?php

namespace App\Console\Commands;

use App\ProductCategory;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ReorganizeProductCategories extends Command
{
    protected $signature = 'categories:reorganize
                            {--dry-run : Show the plan only; make no changes}
                            {--force : Required to actually apply the changes}';

    protected $description = 'Assign codes, create new categories, and merge old product categories into combined ones.';

    /**
     * Existing categories to keep as-is, only assigning a code.
     * category_name => code
     */
    private array $keep = [
        'ABALONE 鲍鱼' => 'A',
        'CRAB 蟹'      => 'C',
        'LOBSTER 龙虾' => 'L',
        'OYSTER 生蚝'  => 'O',
        'SALT 海盐'    => 'Z',
    ];

    /**
     * Brand-new categories to create (no products moved).
     * category_name => code
     */
    private array $create = [
        'frozen fish'    => 'FF',
        'frozen prawn'   => 'FP',
        'frozen crab'    => 'FC',
        'frozen lobster' => 'FL',
        'frozen shell'   => 'FS',
    ];

    /**
     * Merges: create a fresh combined category, move products from every source
     * into it, then delete the source categories.
     */
    private array $merges = [
        ['name' => 'SNAIL 螺类，CLAM 贝类',                'code' => 'S', 'sources' => ['SNAIL 螺类', 'CLAM 贝类']],
        ['name' => 'FISH 鱼，EEL 鳗鱼',                    'code' => 'F', 'sources' => ['FISH 鱼', 'EEL 鳗鱼']],
        ['name' => 'PRAWN 虾，MANTIS SHRIMP 尿虾皮皮虾',   'code' => 'M', 'sources' => ['PRAWN 虾', 'MANTIS SHRIMP 尿虾皮皮虾']],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $this->line('');
        $this->info('Product category reorganization plan');
        $this->line('====================================');

        // --- Build plan -----------------------------------------------------
        $keepRows   = [];
        $createRows = [];
        $mergeRows  = [];
        $warnings   = [];

        foreach ($this->keep as $name => $code) {
            $cat = ProductCategory::where('category_name', $name)->first();
            if (! $cat) {
                $warnings[] = "KEEP: category \"{$name}\" not found — will be skipped.";
                continue;
            }
            $keepRows[] = [$cat->id, $name, $cat->code ?: '—', $code];
        }

        foreach ($this->create as $name => $code) {
            $existing = ProductCategory::where('category_name', $name)->first();
            $createRows[] = [$existing->id ?? 'new', $name, $code, $existing ? 'set code on existing' : 'create'];
        }

        foreach ($this->merges as $merge) {
            $sourceInfo = [];
            $movedTotal = 0;
            foreach ($merge['sources'] as $sourceName) {
                $src = ProductCategory::where('category_name', $sourceName)->first();
                if (! $src) {
                    $warnings[] = "MERGE \"{$merge['name']}\": source \"{$sourceName}\" not found — skipped.";
                    continue;
                }
                $count = DB::table('products')->where('product_category_id', $src->id)->count();
                $movedTotal += $count;
                $sourceInfo[] = "#{$src->id} {$sourceName} ({$count} products)";
            }
            $mergeRows[] = [
                $merge['name'],
                $merge['code'],
                implode(' + ', $sourceInfo) ?: '(no existing sources)',
                $movedTotal,
            ];
        }

        // --- Show plan ------------------------------------------------------
        $this->line('');
        $this->comment('1. Keep + assign code');
        $this->table(['ID', 'Category', 'Current code', 'New code'], $keepRows);

        $this->comment('2. Create new categories');
        $this->table(['ID', 'Category', 'Code', 'Action'], $createRows);

        $this->comment('3. Merge into new combined categories');
        $this->table(['New category', 'Code', 'Sources (deleted after move)', 'Products moved'], $mergeRows);

        // Orphan check — products pointing at a category id that no longer exists.
        $orphans = DB::table('products')
            ->whereNotNull('product_category_id')
            ->whereNotIn('product_category_id', ProductCategory::query()->select('id'))
            ->select('product_category_id', DB::raw('COUNT(*) as c'))
            ->groupBy('product_category_id')
            ->get();
        foreach ($orphans as $o) {
            $warnings[] = "ORPHAN: {$o->c} product(s) reference missing category id {$o->product_category_id} — left untouched.";
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

        if (! $this->option('force')) {
            $this->line('');
            $this->error('This modifies product categories and reassigns products. Re-run with --dry-run to preview or --force to apply.');

            return self::FAILURE;
        }

        // --- Apply ----------------------------------------------------------
        DB::transaction(function () {
            foreach ($this->keep as $name => $code) {
                ProductCategory::where('category_name', $name)->update(['code' => $code]);
            }

            foreach ($this->create as $name => $code) {
                ProductCategory::updateOrCreate(
                    ['category_name' => $name],
                    ['code' => $code]
                );
            }

            foreach ($this->merges as $merge) {
                $target = ProductCategory::updateOrCreate(
                    ['category_name' => $merge['name']],
                    ['code' => $merge['code']]
                );

                foreach ($merge['sources'] as $sourceName) {
                    $src = ProductCategory::where('category_name', $sourceName)->first();
                    if (! $src) {
                        continue;
                    }
                    DB::table('products')
                        ->where('product_category_id', $src->id)
                        ->update(['product_category_id' => $target->id]);
                    $src->delete();
                }
            }
        });

        $this->line('');
        $this->info('Done. Product categories reorganized.');

        return self::SUCCESS;
    }
}
