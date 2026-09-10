<?php

namespace Tests\Feature;

use App\Admin;
use App\Product;
use App\ProductCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductNextSkuTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(): Admin
    {
        return Admin::forceCreate([
            'name' => 'Boss',
            'username' => 'boss' . rand(1000, 9999),
            'email' => 'boss' . rand(1000, 9999) . '@example.com',
            'role' => 'superadmin',
            'password' => Hash::make('password'),
        ]);
    }

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
    public function first_sku_for_a_code_is_0001(): void
    {
        $cat = $this->category('ABALONE 鲍鱼', 'A');

        $this->assertSame('A0001', Product::nextSkuForCategory($cat->id));
    }

    /** @test */
    public function it_increments_from_the_highest_existing_suffix(): void
    {
        $cat = $this->category('ABALONE 鲍鱼', 'A');
        $this->product($cat->id, 'A0001');
        $this->product($cat->id, 'A0007');
        $this->product($cat->id, 'A0003');

        $this->assertSame('A0008', Product::nextSkuForCategory($cat->id));
    }

    /** @test */
    public function a_single_letter_code_is_not_polluted_by_longer_codes(): void
    {
        // "F" and "FF" share a prefix; each must keep its own sequence.
        $fish = $this->category('FISH 鱼，EEL 鳗鱼', 'F');
        $frozenFish = $this->category('frozen fish', 'FF');
        $this->product($fish->id, 'F0002');
        $this->product($frozenFish->id, 'FF0009');

        $this->assertSame('F0003', Product::nextSkuForCategory($fish->id));
        $this->assertSame('FF0010', Product::nextSkuForCategory($frozenFish->id));
    }

    /** @test */
    public function it_ignores_non_numeric_suffixes(): void
    {
        $cat = $this->category('CRAB 蟹', 'C');
        $this->product($cat->id, 'C-SPECIAL');
        $this->product($cat->id, 'C0004');

        $this->assertSame('C0005', Product::nextSkuForCategory($cat->id));
    }

    /** @test */
    public function it_returns_null_when_category_has_no_code(): void
    {
        $cat = $this->category('SALT 海盐', null);

        $this->assertNull(Product::nextSkuForCategory($cat->id));
    }

    /** @test */
    public function endpoint_returns_next_sku_for_category(): void
    {
        $admin = $this->makeAdmin();
        $cat = $this->category('ABALONE 鲍鱼', 'A');
        $this->product($cat->id, 'A0001');

        $this->actingAs($admin, 'web_admin')
            ->post('/admin/product/next-sku', ['product_category_id' => $cat->id])
            ->assertOk()
            ->assertJson(['sku' => 'A0002']);
    }

    /** @test */
    public function endpoint_returns_null_sku_without_category(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'web_admin')
            ->post('/admin/product/next-sku', [])
            ->assertOk()
            ->assertJson(['sku' => null]);
    }
}
