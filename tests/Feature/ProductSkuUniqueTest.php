<?php

namespace Tests\Feature;

use App\Admin;
use App\Product;
use App\ProductCategory;
use App\Uom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ProductSkuUniqueTest extends TestCase
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

    private ?Uom $uom = null;
    private ?ProductCategory $category = null;

    private function uom(): Uom
    {
        return $this->uom ??= Uom::create(['uom_name' => 'KG']);
    }

    private function category(): ProductCategory
    {
        return $this->category ??= ProductCategory::create(['category_name' => 'ABALONE', 'code' => 'A']);
    }

    private function product(?string $sku): Product
    {
        return Product::forceCreate([
            'product_category_id' => $this->category()->id,
            'name' => 'P-' . rand(1000, 9999),
            'sku' => $sku,
            'price' => 10.00,
            'status' => 'active',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'New Product',
            'description' => 'Fresh',
            'price' => 12.50,
            'status' => 'active',
            'sell_in' => 'weight',
            'remark' => null,
            'nos' => null,
            'uom_id' => $this->uom()->id,
            'product_category_id' => $this->category()->id,
        ], $overrides);
    }

    /** @test */
    public function creating_a_product_with_a_duplicate_sku_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $this->product('A0001');

        $this->actingAs($admin, 'web_admin')
            ->post('/admin/product/add', $this->payload(['sku' => 'A0001']))
            ->assertSessionHasErrors('sku');

        $this->assertSame(1, Product::where('sku', 'A0001')->count());
    }

    /** @test */
    public function creating_a_product_with_a_unique_sku_succeeds(): void
    {
        $admin = $this->makeAdmin();
        $this->product('A0001');

        $this->actingAs($admin, 'web_admin')
            ->post('/admin/product/add', $this->payload(['sku' => 'A0002']))
            ->assertSessionHasNoErrors();

        $this->assertSame(1, Product::where('sku', 'A0002')->count());
    }

    /** @test */
    public function multiple_products_may_have_a_blank_sku(): void
    {
        $admin = $this->makeAdmin();
        $this->product(null);

        $this->actingAs($admin, 'web_admin')
            ->post('/admin/product/add', $this->payload(['sku' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Product::whereNull('sku')->count());
    }

    /** @test */
    public function editing_a_product_keeping_its_own_sku_is_allowed(): void
    {
        $admin = $this->makeAdmin();
        $product = $this->product('A0001');

        $this->actingAs($admin, 'web_admin')
            ->post('/admin/product/edit/' . encrypt($product->id), $this->payload([
                'sku' => 'A0001',
                'name' => 'Renamed',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Renamed', $product->fresh()->name);
    }

    /** @test */
    public function editing_a_product_to_another_products_sku_is_rejected(): void
    {
        $admin = $this->makeAdmin();
        $this->product('A0001');
        $target = $this->product('A0002');

        $this->actingAs($admin, 'web_admin')
            ->post('/admin/product/edit/' . encrypt($target->id), $this->payload([
                'sku' => 'A0001',
            ]))
            ->assertSessionHasErrors('sku');

        $this->assertSame('A0002', $target->fresh()->sku);
    }
}
