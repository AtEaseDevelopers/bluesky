<?php

namespace Tests\Feature;

use App\Product;
use App\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReorganizeProductCategoriesTest extends TestCase
{
    use RefreshDatabase;

    private function seedCategory(string $name): ProductCategory
    {
        return ProductCategory::create(['category_name' => $name]);
    }

    private function seedProduct(int $categoryId): Product
    {
        return Product::forceCreate([
            'product_category_id' => $categoryId,
            'name' => 'P' . $categoryId . '-' . rand(1000, 9999),
            'price' => 10.00,
            'status' => 'active',
        ]);
    }

    private function seedBaseline(): void
    {
        foreach ([
            'ABALONE 鲍鱼', 'CRAB 蟹', 'LOBSTER 龙虾', 'OYSTER 生蚝', 'SALT 海盐',
            'SNAIL 螺类', 'CLAM 贝类', 'FISH 鱼', 'EEL 鳗鱼',
            'PRAWN 虾', 'MANTIS SHRIMP 尿虾皮皮虾',
        ] as $name) {
            $this->seedCategory($name);
        }
    }

    /** @test */
    public function dry_run_makes_no_changes(): void
    {
        $this->seedBaseline();
        $before = ProductCategory::count();

        $this->artisan('categories:reorganize --dry-run')
            ->assertExitCode(0);

        $this->assertSame($before, ProductCategory::count());
        $this->assertNull(ProductCategory::where('category_name', 'ABALONE 鲍鱼')->first()->code);
        $this->assertNull(ProductCategory::where('category_name', 'frozen fish')->first());
    }

    /** @test */
    public function without_force_it_refuses_to_apply(): void
    {
        $this->seedBaseline();

        $this->artisan('categories:reorganize')
            ->assertExitCode(1);

        $this->assertNull(ProductCategory::where('category_name', 'ABALONE 鲍鱼')->first()->code);
    }

    /** @test */
    public function force_assigns_codes_creates_and_merges(): void
    {
        $this->seedBaseline();

        // Products spread across merge sources.
        $snail = ProductCategory::where('category_name', 'SNAIL 螺类')->first();
        $clam  = ProductCategory::where('category_name', 'CLAM 贝类')->first();
        $this->seedProduct($snail->id);
        $this->seedProduct($snail->id);
        $this->seedProduct($clam->id);

        $this->artisan('categories:reorganize --force')->assertExitCode(0);

        // 1. Kept categories get codes.
        $this->assertSame('A', ProductCategory::where('category_name', 'ABALONE 鲍鱼')->first()->code);
        $this->assertSame('Z', ProductCategory::where('category_name', 'SALT 海盐')->first()->code);

        // 2. New frozen categories created with codes.
        $this->assertSame('FF', ProductCategory::where('category_name', 'frozen fish')->first()->code);
        $this->assertSame('FS', ProductCategory::where('category_name', 'frozen shell')->first()->code);

        // 3. Merge: combined category exists, sources gone, products moved.
        $merged = ProductCategory::where('category_name', 'SNAIL 螺类，CLAM 贝类')->first();
        $this->assertNotNull($merged);
        $this->assertSame('S', $merged->code);
        $this->assertNull(ProductCategory::where('category_name', 'SNAIL 螺类')->first());
        $this->assertNull(ProductCategory::where('category_name', 'CLAM 贝类')->first());
        $this->assertSame(3, DB::table('products')->where('product_category_id', $merged->id)->count());
    }

    /** @test */
    public function running_twice_is_safe(): void
    {
        $this->seedBaseline();
        $this->artisan('categories:reorganize --force')->assertExitCode(0);
        $this->artisan('categories:reorganize --force')->assertExitCode(0);

        // No duplicate frozen categories.
        $this->assertSame(1, ProductCategory::where('category_name', 'frozen fish')->count());
    }
}
