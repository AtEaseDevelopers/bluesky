<?php

namespace Tests\Feature;

use App\Order;
use App\OrderProduct;
use App\Product;
use App\Services\OrderService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderDiscountFlattenTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): User
    {
        return User::forceCreate([
            'name' => 'Acme Seafood',
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'cod',
            'status' => 'active',
            'payment_method' => 'cash',
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    private function makeProduct(float $price): Product
    {
        return Product::forceCreate([
            'name' => 'Prawn ' . rand(1000, 9999),
            'sku' => 'SKU' . rand(1000, 9999),
            'price' => $price,
            'weight' => 0,
            'status' => Product::$status['active'],
            'images' => json_encode(['prawn.jpg']),
            'sell_in' => Product::SELL_IN_WEIGHT,
        ]);
    }

    /**
     * Create an order. Orders default to an id at/after the flattening go-live
     * so the common cases exercise the flattening; tests that probe the id
     * gating pass an explicit lower id.
     */
    private function makeOrder(User $customer, array $attrs = []): Order
    {
        return Order::forceCreate(array_merge([
            'id' => Order::DISCOUNT_EFFECTIVE_ORDER_ID,
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 0,
            'subtotal' => 0,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => 'pending',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $attrs));
    }

    /** @test */
    public function saving_an_order_with_a_decimal_total_flattens_it_and_records_the_discount(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, ['total_price' => 123.45, 'subtotal' => 123.45]);

        $order->refresh();

        $this->assertEquals(123.00, (float) $order->total_price);
        $this->assertEquals(0.45, round((float) $order->discount, 2));
        // Balance due is the flattened, whole-ringgit total.
        $this->assertEquals(123.00, $order->balanceDue());
    }

    /** @test */
    public function a_whole_ringgit_total_leaves_no_discount(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, ['total_price' => 200.00, 'subtotal' => 200.00]);

        $order->refresh();

        $this->assertEquals(200.00, (float) $order->total_price);
        $this->assertEquals(0.00, (float) $order->discount);
    }

    /** @test */
    public function recalculating_totals_flattens_the_grand_total(): void
    {
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(45.00);
        $order = $this->makeOrder($customer, ['delivery_fee' => 5.75]);

        OrderProduct::forceCreate([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => null,
            'weight' => 2.5,
            'product_weight' => 2.5,
            'unit_price' => 45.00,
            'price' => 112.30, // 112.30 + 5.75 delivery = 118.05
            'status' => OrderProduct::$status['active'],
        ]);

        $fresh = app(OrderService::class)->recalculateTotals($order->fresh());

        // subtotal keeps its cents; grand total is floored to whole ringgit.
        $this->assertEquals(112.30, (float) $fresh->subtotal);
        $this->assertEquals(118.00, (float) $fresh->total_price);
        $this->assertEquals(0.05, round((float) $fresh->discount, 2));
    }

    /** @test */
    public function unrelated_saves_do_not_wipe_the_recorded_discount(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, ['total_price' => 99.90, 'subtotal' => 99.90]);
        $order->refresh();
        $this->assertEquals(0.90, round((float) $order->discount, 2));

        // A save that does not touch the total must preserve the discount.
        $order->update(['adjustment_remark' => 'note']);
        $order->refresh();

        $this->assertEquals(99.00, (float) $order->total_price);
        $this->assertEquals(0.90, round((float) $order->discount, 2));
    }

    /** @test */
    public function an_order_below_the_effective_id_is_not_flattened(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, [
            'id' => Order::DISCOUNT_EFFECTIVE_ORDER_ID - 1,
            'total_price' => 123.45,
            'subtotal' => 123.45,
        ]);
        $order->refresh();

        // Total keeps its cents and no discount is recorded.
        $this->assertEquals(123.45, (float) $order->total_price);
        $this->assertEquals(0.00, (float) $order->discount);
    }

    /** @test */
    public function an_order_at_the_effective_id_is_flattened(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, [
            'id' => Order::DISCOUNT_EFFECTIVE_ORDER_ID,
            'total_price' => 123.45,
            'subtotal' => 123.45,
        ]);
        $order->refresh();

        $this->assertEquals(123.00, (float) $order->total_price);
        $this->assertEquals(0.45, round((float) $order->discount, 2));
    }

    /** @test */
    public function a_below_effective_order_edited_later_is_still_not_flattened(): void
    {
        $customer = $this->makeCustomer();
        // created below the go-live id, total 0
        $order = $this->makeOrder($customer, ['id' => Order::DISCOUNT_EFFECTIVE_ORDER_ID - 1]);

        // Later the total changes — but eligibility is decided by the id, so it
        // must not flatten.
        $order->update(['total_price' => 150.45, 'subtotal' => 150.45]);
        $order->refresh();

        $this->assertEquals(150.45, (float) $order->total_price);
        $this->assertEquals(0.00, (float) $order->discount);
    }
}
