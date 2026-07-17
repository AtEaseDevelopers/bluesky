<?php

namespace Tests\Feature;

use App\Admin;
use App\Cart;
use App\Order;
use App\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicOrderTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Carry the session cookie from a response into subsequent requests so the
     * guest cart (keyed by session id) survives across requests. The test
     * client does not maintain a cookie jar automatically; a real browser does.
     */
    protected function carrySession($response)
    {
        foreach ($response->baseResponse->headers->getCookies() as $cookie) {
            $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue());
        }

        return $response;
    }

    /** Add a product to the guest cart, preserving the session for later requests. */
    protected function addToCart(Product $product, array $payload)
    {
        return $this->carrySession(
            $this->post(route('public.guest.add-to-cart', $product->id), $payload)
        );
    }

    protected function makeProduct(array $attrs = []): Product
    {
        // forceCreate to satisfy NOT NULL columns regardless of $fillable.
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

        // Storefront (member + guest) only lists products that carry stock.
        \App\ProductStock::forceCreate([
            'product_id' => $product->id,
            'quantity' => 100,
        ]);

        return $product;
    }

    /** @test */
    public function storefront_is_accessible_to_guests_and_lists_active_products()
    {
        $this->makeProduct(['name' => 'PUBLIC-PRAWN']);
        $this->makeProduct(['name' => 'HIDDEN-FISH', 'status' => Product::$status['inactive']]);

        $this->get(route('public.guest.index'))
            ->assertOk()
            ->assertSee('PUBLIC-PRAWN')
            ->assertDontSee('HIDDEN-FISH');
    }

    /** @test */
    public function guest_can_add_a_product_to_a_session_cart()
    {
        $product = $this->makeProduct();

        $this->addToCart($product, ['weight' => 2])->assertRedirect();

        $this->assertDatabaseHas('carts', [
            'user_id' => null,
            'status' => Cart::$status['pending'],
        ]);
        $this->assertDatabaseHas('cart_products', [
            'product_id' => $product->id,
            'weight' => 2,
            'status' => 'active',
        ]);
    }

    /** @test */
    public function guest_cart_shows_added_items_and_total()
    {
        $product = $this->makeProduct(['name' => 'CART-PRAWN', 'price' => 30.00]);

        $this->addToCart($product, ['weight' => 3]);

        $this->get(route('public.guest.cart'))
            ->assertOk()
            ->assertSee('CART-PRAWN');
    }

    /** @test */
    public function checkout_requires_name_contact_and_delivery_address()
    {
        $product = $this->makeProduct();
        $this->addToCart($product, ['weight' => 1]);

        $this->post(route('public.guest.checkout.submit'), [])
            ->assertSessionHasErrors(['attn_name', 'attn_contact', 'billing_address']);

        $this->assertSame(0, Order::count());
    }

    /** @test */
    public function guest_can_place_a_cod_order()
    {
        $product = $this->makeProduct(['price' => 40.00]);
        $this->addToCart($product, ['weight' => 2.5]);

        $this->post(route('public.guest.checkout.submit'), [
            'attn_name' => 'Walk In Wong',
            'attn_contact' => '0191234567',
            'contact_method' => 'whatsapp',
            'billing_address' => '88 Pasar Road, KL',
            'payment_method' => 'cash',
        ])->assertRedirect();

        $order = Order::first();
        $this->assertNotNull($order);
        $this->assertNull($order->user_id);
        $this->assertEquals(1, (int) $order->is_general);
        $this->assertSame('pending', $order->status);
        $this->assertSame('cash', $order->payment_method);
        $this->assertSame('Walk In Wong', $order->attn_name);
        $this->assertSame('0191234567', $order->attn_contact);
        $this->assertSame('88 Pasar Road, KL', $order->shipping_address);

        $this->assertDatabaseHas('order_products', [
            'order_id' => $order->id,
            'product_id' => $product->id,
        ]);

        // Cart is consumed.
        $this->assertDatabaseHas('carts', [
            'id' => $order->cart_id,
            'status' => Cart::$status['completed'],
        ]);
    }

    /** @test */
    public function public_order_rejects_invalid_cod_payment_preference()
    {
        $product = $this->makeProduct();
        $this->addToCart($product, ['weight' => 1]);

        $this->post(route('public.guest.checkout.submit'), [
            'attn_name' => 'Sneaky Sam',
            'attn_contact' => '0170000000',
            'contact_method' => 'whatsapp',
            'billing_address' => '1 Term St',
            'payment_method' => 'term',
        ])->assertSessionHasErrors('payment_method');

        $this->assertNull(Order::first());
    }

    /** @test */
    public function checkout_with_empty_cart_does_not_create_order()
    {
        $this->post(route('public.guest.checkout.submit'), [
            'attn_name' => 'No Cart',
            'attn_contact' => '0170000000',
            'billing_address' => '1 Empty St',
        ])->assertRedirect();

        $this->assertSame(0, Order::count());
    }

    /** @test */
    public function guest_order_is_visible_to_admin_in_order_list()
    {
        $product = $this->makeProduct();
        $this->addToCart($product, ['weight' => 1]);
        $this->post(route('public.guest.checkout.submit'), [
            'attn_name' => 'ADMIN-VISIBLE-GUEST',
            'attn_contact' => '0181112222',
            'contact_method' => 'whatsapp',
            'billing_address' => '5 Admin Way',
            'payment_method' => 'cash',
        ]);

        $admin = Admin::forceCreate([
            'name' => 'Boss',
            'username' => 'boss' . rand(1000, 9999),
            'email' => 'boss' . rand(1000, 9999) . '@example.com',
            'role' => 'superadmin',
            'password' => Hash::make('password'),
        ]);

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders'))
            ->assertOk()
            ->assertSee('ADMIN-VISIBLE-GUEST');
    }

    /** @test */
    public function admin_order_summary_shows_guest_delivery_address()
    {
        $product = $this->makeProduct();
        $this->addToCart($product, ['weight' => 1]);
        $this->post(route('public.guest.checkout.submit'), [
            'attn_name' => 'Summary Guest',
            'attn_contact' => '0181113333',
            'contact_method' => 'whatsapp',
            'billing_address' => '77 Delivery Lane, KL',
            'payment_method' => 'qr',
        ]);

        $order = Order::first();
        $admin = Admin::forceCreate([
            'name' => 'Boss',
            'username' => 'boss' . rand(1000, 9999),
            'email' => 'boss' . rand(1000, 9999) . '@example.com',
            'role' => 'superadmin',
            'password' => Hash::make('password'),
        ]);

        // CONCAT_WS must not be nulled out by the guest's empty city/postcode/state.
        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders.summary', $order->id))
            ->assertOk()
            ->assertSee('Summary Guest')
            ->assertSee('0181113333')
            ->assertSee('77 Delivery Lane, KL');
    }
}
