<?php

namespace Tests\Feature;

use App\Admin;
use App\Driver;
use App\Order;
use App\OrderPayment;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * A payment collected by a driver in the driver portal must appear in the admin
 * order-summary Payment History table and be attributed to that driver (rather
 * than falling back to "System", which is what happens when neither the admin
 * recorder nor the customer submitter is set).
 */
class DriverPaymentRecordedByTest extends TestCase
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

    private function makeDriver(): Driver
    {
        return Driver::create([
            'name' => 'Ali Driver',
            'phone' => '0123456789',
            'lorry_number' => 'LRY-1',
            'username' => 'driver' . rand(1000, 9999),
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
    }

    private function makeCustomer(): User
    {
        return User::forceCreate([
            'name' => 'Acme Seafood',
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'cod',
            'customer_type' => 'cod',
            'status' => 'active',
            'payment_method' => 'cod',
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    private function makeOrder(Driver $driver, User $customer): Order
    {
        return Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 150.00,
            'subtotal' => 150.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => 'in_route',
            'fulfillment_type' => Order::$fulfillment_types['delivery'],
            'payment_method' => 'cod',
            'payment_status' => 'unpaid',
            'driver_id' => $driver->id,
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);
    }

    /** @test */
    public function driver_recorder_relationship_resolves_to_the_driver(): void
    {
        $driver = $this->makeDriver();
        $payment = new OrderPayment(['recorded_by_driver' => $driver->id]);

        $this->assertNotNull($payment->recorderDriver);
        $this->assertSame($driver->id, $payment->recorderDriver->id);
        $this->assertSame('Ali Driver', $payment->recorderName());
    }

    /** @test */
    public function driver_payment_is_attributed_to_the_driver_in_admin_summary(): void
    {
        $driver = $this->makeDriver();
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($driver, $customer);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.orders.record-payment', $order->id), [
                'payment_method' => 'cash',
                'paid_amount' => 150.00,
            ])->assertRedirect();

        $payment = OrderPayment::where('order_id', $order->id)->firstOrFail();
        $this->assertSame($driver->id, (int) $payment->recorded_by_driver);
        $this->assertNull($payment->recorded_by);

        $admin = $this->makeAdmin();
        $html = $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders.summary', $order->id))
            ->assertOk()
            ->getContent();

        // Isolate the payment table row for this RM 150.00 payment.
        $pos = strpos($html, 'Payment History');
        $region = substr($html, $pos, 8000);
        $rowStart = strpos($region, '150.00');
        $row = strip_tags(substr($region, $rowStart, 3000));

        // The driver's name must be shown as the recorder, not "System".
        $this->assertStringContainsString('Ali Driver', $row);
        $this->assertStringNotContainsString('System', $row);
    }
}
