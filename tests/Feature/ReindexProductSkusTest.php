<?php

namespace Tests\Feature;

use App\Product;
use App\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReindexProductSkusTest extends TestCase
{
    use RefreshDatabase;

    private function category(string $name, ?string $code): ProductCategory
    {
        return ProductCategory::create(['category_name' => $name, 'code' => $code]);
    }

    private function product(int $categoryId, ?string $sku): Product
    {
        return Product::forceCreate([
            'product_category_id' => $categoryId,
            'name' => 'P-' . rand(1000, 9999),
            'sku' => $sku,
            'price' => 10.00,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function dry_run_makes_no_changes(): void
    {
        $cat = $this->category('ABALONE 鲍鱼', 'A');
        $p1 = $this->product($cat->id, 'A0009');
        $p2 = $this->product($cat->id, null);

        $this->artisan('products:reindex-sku --dry-run')->assertExitCode(0);

        $this->assertSame('A0009', $p1->fresh()->sku);
        $this->assertNull($p2->fresh()->sku);
    }

    /** @test */
    public function without_force_it_refuses_to_apply(): void
    {
        $cat = $this->category('ABALONE 鲍鱼', 'A');
        $p1 = $this->product($cat->id, 'A0009');

        $this->artisan('products:reindex-sku')->assertExitCode(1);

        $this->assertSame('A0009', $p1->fresh()->sku);
    }

    /** @test */
    public function force_reindexes_sequentially_by_id_within_each_category(): void
    {
        $abalone = $this->category('ABALONE 鲍鱼', 'A');
        $crab = $this->category('CRAB 蟹', 'C');

        $a1 = $this->product($abalone->id, 'A0050');
        $a2 = $this->product($abalone->id, null);
        $a3 = $this->product($abalone->id, 'ZZZ');
        $c1 = $this->product($crab->id, 'C0002');

        $this->artisan('products:reindex-sku --force')->assertExitCode(0);

        $this->assertSame('A0001', $a1->fresh()->sku);
        $this->assertSame('A0002', $a2->fresh()->sku);
        $this->assertSame('A0003', $a3->fresh()->sku);
        $this->assertSame('C0001', $c1->fresh()->sku);
    }

    /** @test */
    public function it_skips_products_in_categories_without_a_code(): void
    {
        $salt = $this->category('SALT 海盐', null);
        $p1 = $this->product($salt->id, 'KEEP-ME');

        $this->artisan('products:reindex-sku --force')->assertExitCode(0);

        $this->assertSame('KEEP-ME', $p1->fresh()->sku);
    }

    /** @test */
    public function running_twice_is_stable(): void
    {
        $cat = $this->category('ABALONE 鲍鱼', 'A');
        $this->product($cat->id, 'A0007');
        $this->product($cat->id, 'A0008');

        $this->artisan('products:reindex-sku --force')->assertExitCode(0);
        $skus = Product::orderBy('id')->pluck('sku')->all();

        $this->artisan('products:reindex-sku --force')->assertExitCode(0);
        $this->assertSame($skus, Product::orderBy('id')->pluck('sku')->all());
        $this->assertSame(['A0001', 'A0002'], $skus);
    }
}
