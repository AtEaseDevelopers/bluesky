<?php

namespace Tests\Feature;

use App\Driver;
use App\Order;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DriverPortalTest extends TestCase
{
    use RefreshDatabase;

    protected function makeDriver(array $attrs = []): Driver
    {
        return Driver::create(array_merge([
            'name' => 'John Driver',
            'phone' => '0123456789',
            'lorry_number' => 'LRY-1',
            'username' => 'driver' . rand(1000, 9999),
            'password' => Hash::make('password'),
            'is_active' => true,
        ], $attrs));
    }

    protected function makeCustomer(): User
    {
        // forceCreate to satisfy NOT NULL columns regardless of $fillable.
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

    protected function makeOrder(Driver $driver, array $attrs = []): Order
    {
        $customer = $this->makeCustomer();

        return Order::forceCreate(array_merge([
            'user_id' => $customer->id,
            'total_price' => 150.00,
            'status' => 'processing',
            'payment_method' => 'cash',
            'driver_id' => $driver->id,
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $attrs));
    }

    /** @test */
    public function login_page_is_accessible_to_guests()
    {
        $this->get(route('driver.login'))
            ->assertOk()
            ->assertSee('Driver Portal');
    }

    /** @test */
    public function guests_are_redirected_to_driver_login()
    {
        $this->get(route('driver.orders.index'))
            ->assertRedirect(route('driver.login'));
    }

    /** @test */
    public function active_driver_can_login_with_valid_credentials()
    {
        $driver = $this->makeDriver(['username' => 'activedriver', 'password' => Hash::make('secret123')]);

        $this->post(route('driver.login.submit'), [
            'username' => 'activedriver',
            'password' => 'secret123',
        ])->assertRedirect(route('driver.orders.index'));

        $this->assertAuthenticatedAs($driver, 'web_driver');
    }

    /** @test */
    public function login_fails_with_wrong_password()
    {
        $this->makeDriver(['username' => 'someone', 'password' => Hash::make('secret123')]);

        $this->post(route('driver.login.submit'), [
            'username' => 'someone',
            'password' => 'wrong-password',
        ])->assertRedirect();

        $this->assertGuest('web_driver');
    }

    /** @test */
    public function inactive_driver_cannot_login()
    {
        $this->makeDriver(['username' => 'inactive', 'password' => Hash::make('secret123'), 'is_active' => false]);

        $this->post(route('driver.login.submit'), [
            'username' => 'inactive',
            'password' => 'secret123',
        ]);

        $this->assertGuest('web_driver');
    }

    /** @test */
    public function driver_only_sees_orders_assigned_to_them()
    {
        $driver = $this->makeDriver();
        $other = $this->makeDriver();

        $mine = $this->makeOrder($driver, ['attn_name' => 'MINE-CUSTOMER']);
        $theirs = $this->makeOrder($other, ['attn_name' => 'THEIRS-CUSTOMER']);

        $this->actingAs($driver, 'web_driver')
            ->get(route('driver.orders.index'))
            ->assertOk()
            ->assertSee('MINE-CUSTOMER')
            ->assertDontSee('THEIRS-CUSTOMER');
    }

    /** @test */
    public function driver_cannot_view_another_drivers_order_detail()
    {
        $driver = $this->makeDriver();
        $other = $this->makeDriver();
        $foreignOrder = $this->makeOrder($other);

        $this->actingAs($driver, 'web_driver')
            ->get(route('driver.orders.show', $foreignOrder->id))
            ->assertNotFound();
    }

    /** @test */
    public function driver_can_update_delivery_status_to_in_route()
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder($driver, ['status' => 'customer_reviewing']);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.orders.update-status', $order->id), ['status' => 'in_route'])
            ->assertRedirect();

        $this->assertSame('in_route', $order->fresh()->status);
    }

    /** @test */
    public function invalid_delivery_status_is_rejected()
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder($driver);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.orders.update-status', $order->id), ['status' => 'teleported'])
            ->assertSessionHasErrors('status');

        $this->assertSame('processing', $order->fresh()->status);
    }

    /** @test */
    public function driver_can_record_a_cash_payment_without_proof()
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder($driver, [
            'total_price' => 150.00,
            'status' => 'in_route',
        ]);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.orders.record-payment', $order->id), [
                'payment_method' => 'cash',
                'paid_amount' => 150.00,
            ])->assertRedirect();

        $fresh = $order->fresh();
        $this->assertEquals(150.00, (float) $fresh->paid_amount);
        $this->assertEquals('paid', $fresh->payment_status);
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'payment_method' => 'cash',
            'amount' => 150.00,
            'status' => 'confirmed',
            'recorded_by_driver' => $driver->id,
        ]);
    }

    /** @test */
    public function transfer_payment_requires_proof()
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder($driver, ['status' => 'in_route']);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.orders.record-payment', $order->id), [
                'payment_method' => 'transfer',
                'paid_amount' => 150.00,
            ])->assertSessionHasErrors('payment_proof');

        $this->assertEquals(0.0, (float) $order->fresh()->paid_amount);
        $this->assertDatabaseMissing('order_payments', ['order_id' => $order->id]);
    }

    /** @test */
    public function driver_can_record_transfer_payment_with_proof_upload()
    {
        Storage::fake('local');

        $driver = $this->makeDriver();
        $order = $this->makeOrder($driver, ['status' => 'in_route']);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.orders.record-payment', $order->id), [
                'payment_method' => 'transfer',
                'paid_amount' => 150.00,
                'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])->assertRedirect();

        $payment = \App\OrderPayment::where('order_id', $order->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('bank-transfer', $payment->payment_method);
        $this->assertNotNull($payment->payment_proof);
        Storage::disk('local')->assertExists(Order::$path . '/' . $order->id . '/payments/' . $payment->payment_proof);
    }

    /** @test */
    public function driver_can_adjust_order_weight_during_delivery()
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder($driver, [
            'status' => 'in_route',
            'total_price' => 95.00,
            'subtotal' => 95.00,
            'delivery_fee' => 0,
        ]);

        $product = \App\Product::forceCreate([
            'name' => 'Live Mud Crab',
            'sku' => 'CRAB-1',
            'sell_in' => 'weight',
            'price' => 95.00,
            'status' => 'active',
        ]);

        $orderProduct = \App\OrderProduct::forceCreate([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'weight' => 1,
            'product_weight' => 1,
            'unit_price' => 95.00,
            'price' => 95.00,
            'status' => 'active',
        ]);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.orders.adjust', $order->id), [
                'line_items' => [
                    $orderProduct->id => ['weight' => 1.5],
                ],
            ])->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $order->fresh();
        $this->assertEquals(142.50, (float) $fresh->total_price);
        $this->assertEquals(1.5, (float) $orderProduct->fresh()->weight);
    }

    /** @test */
    public function driver_can_adjust_order_quantity_for_qty_products()
    {
        $driver = $this->makeDriver();
        $order = $this->makeOrder($driver, [
            'status' => 'in_route',
            'total_price' => 30.00,
            'subtotal' => 30.00,
            'delivery_fee' => 0,
        ]);

        $product = \App\Product::forceCreate([
            'name' => 'Frozen Prawn Pack',
            'sku' => 'PRAWN-1',
            'sell_in' => 'qty',
            'show_qty' => true,
            'show_weight' => false,
            'price' => 10.00,
            'status' => 'active',
        ]);

        $orderProduct = \App\OrderProduct::forceCreate([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => 3,
            'unit_price' => 10.00,
            'price' => 30.00,
            'status' => 'active',
        ]);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.orders.adjust', $order->id), [
                'line_items' => [
                    $orderProduct->id => ['quantity' => 5],
                ],
            ])->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $order->fresh();
        $this->assertEquals(50.00, (float) $fresh->total_price);
        $this->assertEquals(5, (float) $orderProduct->fresh()->quantity);
    }
}
