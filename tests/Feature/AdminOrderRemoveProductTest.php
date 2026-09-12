<?php

namespace Tests\Feature;

use App\Admin;
use App\Order;
use App\OrderProduct;
use App\OrderProductOption;
use App\Product;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminOrderRemoveProductTest extends TestCase
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

    private function makeProduct(float $price, string $sellIn = Product::SELL_IN_QTY, float $weight = 0): Product
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
            'total_price' => 30.00,
            'subtotal' => 30.00,
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

    private function addLine(Order $order, Product $product, float $qty, ?float $weight = null): OrderProduct
    {
        return OrderProduct::forceCreate([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $qty,
            'weight' => $weight,
            'unit_price' => $product->price,
            'price' => $product->price * ($weight ?? $qty),
            'status' => OrderProduct::$status['active'],
        ]);
    }

    /** @test */
    public function removing_a_product_soft_deletes_the_line_and_recalculates_totals(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $keep = $this->makeProduct(10.00);
        $drop = $this->makeProduct(10.00);
        $order = $this->makeOrder($customer, ['delivery_fee' => 5.00]);
        $this->addLine($order, $keep, 3);      // 30
        $dropLine = $this->addLine($order, $drop, 2); // 20

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.products.remove', [$order->id, $dropLine->id]))
            ->assertRedirect(route('admin.orders.summary', $order->id));

        // Line is soft-removed, not hard-deleted.
        $this->assertDatabaseHas('order_products', [
            'id' => $dropLine->id,
            'status' => OrderProduct::$status['removed'],
        ]);
        $this->assertSame(1, OrderProduct::where('order_id', $order->id)
            ->where('status', OrderProduct::$status['active'])->count());

        $order->refresh();
        // subtotal 30 + delivery fee 5.
        $this->assertEquals(30.00, (float) $order->subtotal);
        $this->assertEquals(35.00, (float) $order->total_price);
    }

    /** @test */
    public function removing_a_product_also_removes_its_options(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $keep = $this->makeProduct(10.00);
        $drop = $this->makeProduct(10.00);
        $order = $this->makeOrder($customer);
        $this->addLine($order, $keep, 1);
        $dropLine = $this->addLine($order, $drop, 1);
        $option = OrderProductOption::forceCreate([
            'order_product_id' => $dropLine->id,
            'option' => 'Cut',
            'option_item' => 'Sliced',
            'status' => OrderProductOption::$status['active'],
        ]);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.products.remove', [$order->id, $dropLine->id]))
            ->assertRedirect(route('admin.orders.summary', $order->id));

        $this->assertDatabaseHas('order_product_options', [
            'id' => $option->id,
            'status' => OrderProductOption::$status['removed'],
        ]);
    }

    /** @test */
    public function removing_a_weight_line_subtracts_its_weight_from_the_order(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $keep = $this->makeProduct(10.00);
        $drop = $this->makeProduct(45.00, Product::SELL_IN_WEIGHT);
        $order = $this->makeOrder($customer, ['order_weight' => 5.5]);
        $this->addLine($order, $keep, 1);
        $dropLine = $this->addLine($order, $drop, 0, 2.5); // 2.5 kg

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.products.remove', [$order->id, $dropLine->id]))
            ->assertRedirect(route('admin.orders.summary', $order->id));

        $order->refresh();
        $this->assertEquals(3.0, (float) $order->order_weight);
    }

    /** @test */
    public function the_last_active_product_cannot_be_removed(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $only = $this->makeProduct(10.00);
        $order = $this->makeOrder($customer);
        $onlyLine = $this->addLine($order, $only, 1);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.products.remove', [$order->id, $onlyLine->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('order_products', [
            'id' => $onlyLine->id,
            'status' => OrderProduct::$status['active'],
        ]);
    }

    /** @test */
    public function a_line_from_another_order_cannot_be_removed(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(10.00);
        $orderA = $this->makeOrder($customer);
        $orderB = $this->makeOrder($customer);
        $this->addLine($orderA, $product, 1);
        $foreignLine = $this->addLine($orderB, $product, 1);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.products.remove', [$orderA->id, $foreignLine->id]))
            ->assertNotFound();

        $this->assertDatabaseHas('order_products', [
            'id' => $foreignLine->id,
            'status' => OrderProduct::$status['active'],
        ]);
    }

    /** @test */
    public function admin_without_orders_edit_permission_is_forbidden(): void
    {
        $admin = $this->makeAdmin('viewer');
        $customer = $this->makeCustomer();
        $keep = $this->makeProduct(10.00);
        $drop = $this->makeProduct(10.00);
        $order = $this->makeOrder($customer);
        $this->addLine($order, $keep, 1);
        $dropLine = $this->addLine($order, $drop, 1);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.products.remove', [$order->id, $dropLine->id]))
            ->assertForbidden();

        $this->assertDatabaseHas('order_products', [
            'id' => $dropLine->id,
            'status' => OrderProduct::$status['active'],
        ]);
    }
}
