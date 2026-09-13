<?php

namespace Tests\Feature;

use App\Admin;
use App\Order;
use App\OrderProduct;
use App\Product;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminOrderWeightUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(string $role = 'superadmin'): Admin
    {
        return Admin::forceCreate([
            'name' => 'Boss',
            'username' => 'boss' . rand(1000, 9999),
            'email' => 'boss' . rand(1000, 9999) . '@example.com',
            'role' => $role,
            'password' => Hash::make('password'),
        ]);
    }

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

    private function makeProduct(float $price, string $sellIn = Product::SELL_IN_WEIGHT, float $weight = 0): Product
    {
        return Product::forceCreate([
            'name' => 'Prawn ' . rand(1000, 9999),
            'sku' => 'SKU' . rand(1000, 9999),
            'price' => $price,
            'weight' => $weight,
            'status' => Product::$status['active'],
            'images' => json_encode(['prawn.jpg']),
            'sell_in' => $sellIn,
        ]);
    }

    private function makeOrder(User $customer, array $attrs = []): Order
    {
        return Order::forceCreate(array_merge([
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

    private function addLine(Order $order, Product $product, ?float $qty, ?float $weight, float $price): OrderProduct
    {
        return OrderProduct::forceCreate([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $qty,
            'weight' => $weight,
            'product_weight' => $weight,
            'unit_price' => $product->price,
            'price' => $price,
            'status' => OrderProduct::$status['active'],
        ]);
    }

    /** @test */
    public function changing_the_weight_of_a_weight_sold_line_recalculates_its_price_and_order_totals(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(45.00, Product::SELL_IN_WEIGHT);
        $order = $this->makeOrder($customer, [
            'delivery_fee' => 5.00,
            'subtotal' => 112.50,
            'total_price' => 117.50,
            'order_weight' => 2.5,
        ]);
        $line = $this->addLine($order, $product, null, 2.5, 112.50); // 45 * 2.5

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.update-order-products-weight'), [
                'orders_id' => encrypt($order->id),
                'do_date' => '2026-09-13',
                'order_product_' . $line->id => 4, // new weight 4 kg
            ])
            ->assertRedirect();

        $line->refresh();
        $order->refresh();

        // price = unit_price 45 * new weight 4 = 180
        $this->assertEquals(180.00, (float) $line->price);
        $this->assertEquals(4.0, (float) $line->weight);
        // subtotal 180, total 180 + delivery fee 5
        $this->assertEquals(180.00, (float) $order->subtotal);
        $this->assertEquals(185.00, (float) $order->total_price);
        $this->assertEquals(4.0, (float) $order->order_weight);
    }

    /** @test */
    public function changing_the_weight_of_a_qty_bill_weight_line_bills_by_the_new_weight(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(20.00, Product::SELL_IN_QTY_BILL_WEIGHT);
        $order = $this->makeOrder($customer, [
            'subtotal' => 50.00,
            'total_price' => 50.00,
        ]);
        $line = $this->addLine($order, $product, 3, 2.5, 50.00); // qty 3, weight 2.5, 20*2.5

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.update-order-products-weight'), [
                'orders_id' => encrypt($order->id),
                'do_date' => '2026-09-13',
                'order_product_' . $line->id => 5, // new weight 5 kg
            ])
            ->assertRedirect();

        $line->refresh();
        $order->refresh();

        // billed by weight: 20 * 5 = 100, quantity untouched
        $this->assertEquals(100.00, (float) $line->price);
        $this->assertEquals(3.0, (float) $line->quantity);
        $this->assertEquals(100.00, (float) $order->subtotal);
    }

    /** @test */
    public function changing_the_weight_of_a_qty_sold_line_does_not_change_its_price(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(10.00, Product::SELL_IN_QTY);
        $order = $this->makeOrder($customer, [
            'subtotal' => 30.00,
            'total_price' => 30.00,
        ]);
        $line = $this->addLine($order, $product, 3, null, 30.00); // qty 3 * 10

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.update-order-products-weight'), [
                'orders_id' => encrypt($order->id),
                'do_date' => '2026-09-13',
                'order_product_' . $line->id => 7, // weight is informational only
            ])
            ->assertRedirect();

        $line->refresh();
        $order->refresh();

        // qty-sold: price stays 10 * 3 = 30 regardless of weight
        $this->assertEquals(30.00, (float) $line->price);
        $this->assertEquals(30.00, (float) $order->subtotal);
    }
}
