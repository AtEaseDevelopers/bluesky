<?php

namespace Tests\Feature;

use App\Product;
use App\ProductDailyPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Regression coverage for the guest (null user) path through the member
 * product flow. The member routes live under `web` (not `auth`) middleware,
 * so an unauthenticated visitor can reach them and previously triggered a
 * TypeError in Product::get_today_price().
 */
class MemberProductGuestTest extends TestCase
{
    use RefreshDatabase;

    protected function makeProduct(array $attrs = []): Product
    {
        $product = Product::forceCreate(array_merge([
            'uom_id' => 1,
            'product_category_id' => 1,
            'name' => 'Tiger Prawn',
            'description' => 'Fresh tiger prawn',
            'sku' => 'TP-' . rand(1000, 9999),
            'price' => 50.00,
            'weight' => 1,
            'images' => null,
            'status' => Product::$status['active'],
            'sell_in' => 'weight',
            'show_weight' => 1,
            'show_qty' => 0,
        ], $attrs));

        \App\ProductStock::forceCreate([
            'product_id' => $product->id,
            'quantity' => 100,
        ]);

        return $product;
    }

    /** @test */
    public function get_today_price_handles_a_null_user()
    {
        $product = $this->makeProduct(['price' => 42.50]);

        // Falls back to the base product price when there is no user category.
        $this->assertSame(42.50, Product::get_today_price($product->id, null));
    }

    /** @test */
    public function get_today_price_with_null_user_uses_global_daily_price()
    {
        $product = $this->makeProduct(['price' => 50.00]);

        ProductDailyPrice::forceCreate([
            'product_id' => $product->id,
            'date' => now()->format('Y-m-d'),
            'user_category' => null,
            'price' => 33.00,
            'status' => ProductDailyPrice::$status['active'],
        ]);

        $this->assertSame(33.00, Product::get_today_price($product->id, null));
    }

    /** @test */
    public function guest_visiting_member_product_page_is_redirected_to_login()
    {
        $product = $this->makeProduct();

        $this->get(route('member.products.show', encrypt($product->id)))
            ->assertRedirect(route('login'));
    }

    /** @test */
    public function guest_storefront_renders_stock_and_price_labels()
    {
        $product = $this->makeProduct(['name' => 'Chicken Breast', 'price' => 50.00]);

        $this->get(route('public.guest.index'))
            ->assertOk()
            ->assertSee('Chicken Breast')
            ->assertSee('Qty: 100')          // stock_label was previously blank
            ->assertSee('RM 50.00 / KG');     // price_label was previously blank
    }
}
