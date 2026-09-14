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
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Inline weight + delivery-fee editing on the admin order summary page. The
 * key requirement: it must work on ANY order status (including completed,
 * paid, and cancelled) and reflect the recalculated price.
 */
class AdminOrderSummaryWeightFeeTest extends TestCase
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
            'sql_customer_code' => '3000-T527',
            'invoice_price_permission' => 1,
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
    public function admin_can_edit_weight_and_delivery_fee_on_a_completed_and_paid_order(): void
    {
        Storage::fake('local');

        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(40.00, Product::SELL_IN_WEIGHT);
        // subtotal 100 (40 * 2.5) + fee 5 = 105, fully paid.
        $order = $this->makeOrder($customer, [
            'status' => 'completed',
            'payment_status' => 'paid',
            'delivery_fee' => 5.00,
            'subtotal' => 100.00,
            'total_price' => 105.00,
            'paid_amount' => 105.00,
            'order_weight' => 2.5,
        ]);
        $line = $this->addLine($order, $product, null, 2.5, 100.00);
        OrderPayment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 105.00,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.weight-fee', $order->id), [
                'delivery_fee' => 10.00,
                'line_items' => [$line->id => ['weight' => 4]],
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id))
            ->assertSessionHas('success');

        $line->refresh();
        $order->refresh();

        // Line price recomputed from the new weight: 40 * 4 = 160.
        $this->assertEquals(160.00, (float) $line->price);
        $this->assertEquals(4.0, (float) $line->weight);
        // subtotal 160 + new fee 10 = 170.
        $this->assertEquals(160.00, (float) $order->subtotal);
        $this->assertEquals(10.00, (float) $order->delivery_fee);
        $this->assertEquals(170.00, (float) $order->total_price);
        $this->assertEquals(4.0, (float) $order->order_weight);
        // Balance re-opened: was paid 105, now owes 170.
        $this->assertNotSame('paid', $order->payment_status);
        $this->assertEquals(65.00, $order->balanceDue());
    }

    /** @test */
    public function admin_can_edit_weight_on_a_cancelled_order(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(30.00, Product::SELL_IN_WEIGHT);
        $order = $this->makeOrder($customer, [
            'status' => 'cancelled',
            'subtotal' => 60.00,
            'total_price' => 60.00,
            'order_weight' => 2.0,
        ]);
        $line = $this->addLine($order, $product, null, 2.0, 60.00);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.weight-fee', $order->id), [
                'line_items' => [$line->id => ['weight' => 5]],
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id));

        $line->refresh();
        $order->refresh();

        $this->assertEquals(150.00, (float) $line->price); // 30 * 5
        $this->assertEquals(150.00, (float) $order->subtotal);
        $this->assertEquals(150.00, (float) $order->total_price);
        $this->assertEquals(5.0, (float) $order->order_weight);
    }

    /** @test */
    public function updating_only_the_delivery_fee_recalculates_the_total(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(20.00, Product::SELL_IN_WEIGHT);
        $order = $this->makeOrder($customer, [
            'subtotal' => 40.00,
            'total_price' => 40.00,
            'order_weight' => 2.0,
        ]);
        $line = $this->addLine($order, $product, null, 2.0, 40.00);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.weight-fee', $order->id), [
                'delivery_fee' => 8.00,
                'line_items' => [$line->id => ['weight' => 2]],
            ])
            ->assertRedirect(route('admin.orders.summary', $order->id));

        $line->refresh();
        $order->refresh();

        $this->assertEquals(40.00, (float) $line->price); // unchanged weight
        $this->assertEquals(40.00, (float) $order->subtotal);
        $this->assertEquals(8.00, (float) $order->delivery_fee);
        $this->assertEquals(48.00, (float) $order->total_price);
    }

    /** @test */
    public function a_weight_sold_line_requires_a_positive_weight(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(20.00, Product::SELL_IN_WEIGHT);
        $order = $this->makeOrder($customer, ['subtotal' => 40.00, 'total_price' => 40.00]);
        $line = $this->addLine($order, $product, null, 2.0, 40.00);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.weight-fee', $order->id), [
                'line_items' => [$line->id => ['weight' => '']],
            ])
            ->assertSessionHasErrors('line_items.' . $line->id . '.weight');

        $line->refresh();
        $this->assertEquals(40.00, (float) $line->price); // untouched
    }

    /** @test */
    public function admin_without_orders_edit_permission_is_forbidden(): void
    {
        $admin = $this->makeAdmin('viewer');
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(20.00, Product::SELL_IN_WEIGHT);
        $order = $this->makeOrder($customer, ['subtotal' => 40.00, 'total_price' => 40.00]);
        $line = $this->addLine($order, $product, null, 2.0, 40.00);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.weight-fee', $order->id), [
                'delivery_fee' => 99.00,
                'line_items' => [$line->id => ['weight' => 9]],
            ])
            ->assertForbidden();

        $line->refresh();
        $this->assertEquals(40.00, (float) $line->price);
    }

    /** @test */
    public function summary_page_renders_editable_weight_and_delivery_fee_inputs(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $product = $this->makeProduct(20.00, Product::SELL_IN_WEIGHT);
        $order = $this->makeOrder($customer, ['subtotal' => 40.00, 'total_price' => 40.00]);
        $line = $this->addLine($order, $product, null, 2.0, 40.00);

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders.summary', $order->id))
            ->assertOk()
            ->assertSee('id="weight-fee-form"', false)
            ->assertSee('id="sm-delivery-fee"', false)
            ->assertSee('name="line_items[' . $line->id . '][weight]"', false);
    }
}
