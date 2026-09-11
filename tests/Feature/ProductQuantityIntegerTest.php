<?php

namespace Tests\Feature;

use App\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Products sold by quantity must use whole-number counts (1, 2, 3, ...).
 * Decimal quantities like 1.001 are rejected. Weight-sold products keep
 * their decimal weight input untouched.
 */
class ProductQuantityIntegerTest extends TestCase
{
    use RefreshDatabase;

    protected function carrySession($response)
    {
        foreach ($response->baseResponse->headers->getCookies() as $cookie) {
            $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }

        return $response;
    }

    protected function addToCart(Product $product, array $payload)
    {
        return $this->carrySession(
            $this->post(route('public.guest.add-to-cart', $product->id), $payload)
        );
    }

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
            'sell_in' => 'qty',
        ], $attrs));

        \App\ProductStock::forceCreate([
            'product_id' => $product->id,
            'quantity' => 100,
            'weight' => 100,
        ]);

        return $product;
    }

    /** @test */
    public function qty_product_rejects_decimal_quantity()
    {
        $product = $this->makeProduct(['sell_in' => 'qty']);

        $this->addToCart($product, ['quantity' => 1.5])
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseMissing('cart_products', [
            'product_id' => $product->id,
        ]);
    }

    /** @test */
    public function qty_product_accepts_integer_quantity()
    {
        $product = $this->makeProduct(['sell_in' => 'qty']);

        $this->addToCart($product, ['quantity' => 3])->assertRedirect();

        $this->assertDatabaseHas('cart_products', [
            'product_id' => $product->id,
            'quantity' => 3,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function qty_bill_weight_product_rejects_decimal_quantity()
    {
        $product = $this->makeProduct(['sell_in' => 'qty_bill_weight']);

        $this->addToCart($product, ['quantity' => 2.25, 'weight' => 3.5])
            ->assertSessionHasErrors('quantity');

        $this->assertDatabaseMissing('cart_products', [
            'product_id' => $product->id,
        ]);
    }

    /** @test */
    public function qty_bill_weight_product_accepts_integer_quantity_with_decimal_weight()
    {
        $product = $this->makeProduct(['sell_in' => 'qty_bill_weight']);

        $this->addToCart($product, ['quantity' => 2, 'weight' => 3.5])->assertRedirect();

        $this->assertDatabaseHas('cart_products', [
            'product_id' => $product->id,
            'quantity' => 2,
            'weight' => 3.5,
        ]);
    }

    /** @test */
    public function weight_product_still_accepts_decimal_weight()
    {
        $product = $this->makeProduct(['sell_in' => 'weight']);

        $this->addToCart($product, ['weight' => 2.5])->assertRedirect();

        $this->assertDatabaseHas('cart_products', [
            'product_id' => $product->id,
            'weight' => 2.5,
        ]);
    }
}
