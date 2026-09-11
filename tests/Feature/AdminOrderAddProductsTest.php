<?php

namespace Tests\Feature;

use App\Admin;
use App\Order;
use App\OrderPayment;
use App\OrderProduct;
use App\Product;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminOrderAddProductsTest extends TestCase
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

    private function makeProduct(float $price, string $sellIn = Product::SELL_IN_QTY): Product
    {
        return Product::forceCreate([
            'name' => 'Prawn ' . rand(1000, 9999),
            'sku' => 'SKU' . rand(1000, 9999),
            'price' => $price,
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

    private function addExistingLine(Order $order, Product $product, float $qty): OrderProduct
    {
        return OrderProduct::forceCreate([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => $qty,
            'unit_price' => $product->price,
            'price' => $product->price * $qty,
            'status' => OrderProduct::$status['active'],
        ]);
    }

    /** @test */
    public function appending_a_product_creates_an_active_line_and_recalculates_totals(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $existing = $this->makeProduct(10.00);
        $order = $this->makeOrder($customer);
        $this->addExistingLine($order, $existing, 3); // subtotal 30

        $extra = $this->makeProduct(10.00);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.products.add', $order->id), [
                'product_id' => [$extra->id],
                'quantity' => [2],
                'remark' => ['fresh please'],
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id));

        // Existing line is untouched, new line appended.
        $this->assertSame(2, OrderProduct::where('order_id', $order->id)
            ->where('status', OrderProduct::$status['active'])->count());

        $this->assertDatabaseHas('order_products', [
            'order_id' => $order->id,
            'product_id' => $extra->id,
            'quantity' => 2,
            'unit_price' => 10.00,
            'price' => 20.00,
            'remark' => 'fresh please',
            'status' => OrderProduct::$status['active'],
        ]);

        $order->refresh();
        $this->assertEquals(50.00, (float) $order->subtotal);
        $this->assertEquals(50.00, (float) $order->total_price);
    }

    /** @test */
    public function recalculation_keeps_delivery_fee_and_adjustment_and_refreshes_payment_status(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $existing = $this->makeProduct(10.00);
        // Fully paid order: subtotal 30 + fee 5 = 35, paid 35.
        $order = $this->makeOrder($customer, [
            'delivery_fee' => 5.00,
            'total_price' => 35.00,
            'subtotal' => 30.00,
            'paid_amount' => 35.00,
            'payment_status' => 'paid',
        ]);
        $this->addExistingLine($order, $existing, 3);
        // paid_amount is derived from confirmed payments, so record a real one.
        OrderPayment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 35.00,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);

        $extra = $this->makeProduct(10.00);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.products.add', $order->id), [
                'product_id' => [$extra->id],
                'quantity' => [1],
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id));

        $order->refresh();
        // subtotal 40 + fee 5 = 45
        $this->assertEquals(40.00, (float) $order->subtotal);
        $this->assertEquals(45.00, (float) $order->total_price);
        // Was fully paid; now there is an outstanding balance so it is no longer paid.
        $this->assertNotSame('paid', $order->payment_status);
        $this->assertEquals(10.00, $order->balanceDue());
    }

    /** @test */
    public function summary_page_renders_the_add_products_form(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(10.00);
        $order = $this->makeOrder($customer);
        $this->addExistingLine($order, $product, 2);

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders.summary', $order->id))
            ->assertOk()
            ->assertSee(__('orders.add_products_to_order'))
            ->assertSee('id="add-products-form"', false);
    }

    /** @test */
    public function at_least_one_product_is_required(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.products.add', $order->id), [
                'product_id' => [],
            ])
            ->assertSessionHasErrors('product_id');

        $this->assertSame(0, OrderProduct::where('order_id', $order->id)->count());
    }

    /**
     * Regression: with mixed sell types, quantity[]/weight[] must stay aligned
     * with product_id[] by index. The frontend emits sparse indexed arrays
     * (a weight-only product has no quantity at its index, and vice versa);
     * each line must persist its OWN weight/quantity, not a neighbour's.
     *
     * @test
     */
    public function mixed_sell_types_keep_weight_and_quantity_aligned_by_index(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);

        $qty = $this->makeProduct(10.00, Product::SELL_IN_QTY);
        $weight = $this->makeProduct(45.00, Product::SELL_IN_WEIGHT);
        $qbw = $this->makeProduct(28.00, Product::SELL_IN_QTY_BILL_WEIGHT);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.products.add', $order->id), [
                'product_id' => [0 => $qty->id, 1 => $weight->id, 2 => $qbw->id],
                // Sparse, index-aligned: no quantity for the weight-only line (idx 1);
                // no weight for the qty-only line (idx 0).
                'quantity' => [0 => 2, 2 => 3],
                'weight' => [1 => 2.5, 2 => 1.006],
                'remark' => [0 => 'a', 1 => 'b', 2 => 'c'],
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id));

        // Qty line: its own quantity, no stray weight.
        $qtyLine = OrderProduct::where('order_id', $order->id)->where('product_id', $qty->id)->first();
        $this->assertEquals(2, (int) $qtyLine->quantity);
        $this->assertEmpty($qtyLine->weight);

        // Weight line: its own weight (2.5), not the qbw line's 1.006.
        $weightLine = OrderProduct::where('order_id', $order->id)->where('product_id', $weight->id)->first();
        $this->assertEquals(2.5, (float) $weightLine->weight);

        // Qty-bill-weight line: its own quantity (3) and weight (1.006).
        $qbwLine = OrderProduct::where('order_id', $order->id)->where('product_id', $qbw->id)->first();
        $this->assertEquals(3, (int) $qbwLine->quantity);
        $this->assertEquals(1.006, (float) $qbwLine->weight);
    }

    /** @test */
    public function admin_without_orders_edit_permission_is_forbidden(): void
    {
        $admin = $this->makeAdmin('viewer');
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $extra = $this->makeProduct(10.00);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.products.add', $order->id), [
                'product_id' => [$extra->id],
                'quantity' => [1],
            ])
            ->assertForbidden();

        $this->assertSame(0, OrderProduct::where('order_id', $order->id)->count());
    }
}
