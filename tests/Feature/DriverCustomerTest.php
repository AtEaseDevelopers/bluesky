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

class DriverCustomerTest extends TestCase
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

    protected function makeCustomer(array $attrs = []): User
    {
        // forceCreate to satisfy NOT NULL columns regardless of $fillable.
        return User::forceCreate(array_merge([
            'name' => 'Acme Seafood',
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'cod',
            'customer_type' => 'cod',
            'status' => 'active',
            'payment_method' => 'cash',
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ], $attrs));
    }

    protected function makeOrder(User $customer, array $attrs = []): Order
    {
        return Order::forceCreate(array_merge([
            'user_id' => $customer->id,
            'total_price' => 150.00,
            'paid_amount' => 0,
            'status' => 'delivered',
            'payment_status' => 'unpaid',
            'payment_method' => 'cash',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $attrs));
    }

    /** @test */
    public function guests_are_redirected_to_login_from_customers_index()
    {
        $this->get(route('driver.customers.index'))
            ->assertRedirect(route('driver.login'));
    }

    /** @test */
    public function driver_only_sees_customers_assigned_to_them()
    {
        $driver = $this->makeDriver();
        $other = $this->makeDriver();

        $this->makeCustomer(['name' => 'MINE-CUSTOMER', 'default_driver_id' => $driver->id]);
        $this->makeCustomer(['name' => 'THEIRS-CUSTOMER', 'default_driver_id' => $other->id]);
        $this->makeCustomer(['name' => 'UNASSIGNED-CUSTOMER', 'default_driver_id' => null]);

        $this->actingAs($driver, 'web_driver')
            ->get(route('driver.customers.index'))
            ->assertOk()
            ->assertSee('MINE-CUSTOMER')
            ->assertDontSee('THEIRS-CUSTOMER')
            ->assertDontSee('UNASSIGNED-CUSTOMER');
    }

    /** @test */
    public function driver_cannot_view_a_customer_assigned_to_another_driver()
    {
        $driver = $this->makeDriver();
        $other = $this->makeDriver();
        $foreign = $this->makeCustomer(['default_driver_id' => $other->id]);

        $this->actingAs($driver, 'web_driver')
            ->get(route('driver.customers.show', $foreign->id))
            ->assertNotFound();
    }

    /** @test */
    public function customer_detail_lists_invoices_with_payment_status_and_due_date()
    {
        $driver = $this->makeDriver();
        $customer = $this->makeCustomer([
            'default_driver_id' => $driver->id,
            'customer_type' => 'credit',
            'payment_term_days' => 30,
        ]);

        // Fully paid invoice.
        $this->makeOrder($customer, [
            'invoice_number' => 'INV-PAID-1',
            'total_price' => 100.00,
            'paid_amount' => 100.00,
            'payment_status' => 'paid',
            'status' => 'delivered',
        ]);

        // Overdue, outstanding invoice.
        $this->makeOrder($customer, [
            'invoice_number' => 'INV-DUE-2',
            'total_price' => 200.00,
            'paid_amount' => 0,
            'payment_status' => 'unpaid',
            'status' => 'in_route',
            'payment_due_date' => now()->subDays(3)->toDateString(),
        ]);

        // Cancelled invoice must not appear.
        $this->makeOrder($customer, [
            'invoice_number' => 'INV-CANCELLED-3',
            'status' => 'cancelled',
        ]);

        $this->actingAs($driver, 'web_driver')
            ->get(route('driver.customers.show', $customer->id))
            ->assertOk()
            ->assertSee('INV-PAID-1')
            ->assertSee('INV-DUE-2')
            ->assertDontSee('INV-CANCELLED-3')
            ->assertSee('Paid')
            ->assertSee('Overdue');
    }

    /** @test */
    public function customer_list_surfaces_total_outstanding_balance()
    {
        $driver = $this->makeDriver();
        $customer = $this->makeCustomer([
            'name' => 'OUTSTANDING-CUSTOMER',
            'default_driver_id' => $driver->id,
            'customer_type' => 'credit',
        ]);

        $this->makeOrder($customer, [
            'total_price' => 200.00,
            'paid_amount' => 50.00,
            'payment_status' => 'partial',
            'status' => 'in_route',
        ]);

        $this->actingAs($driver, 'web_driver')
            ->get(route('driver.customers.index'))
            ->assertOk()
            ->assertSee('OUTSTANDING-CUSTOMER')
            ->assertSee('150.00');
    }

    /** @test */
    public function customer_index_shows_grand_total_outstanding_across_all_assigned_customers()
    {
        $driver = $this->makeDriver();
        $other = $this->makeDriver();

        $a = $this->makeCustomer(['default_driver_id' => $driver->id, 'customer_type' => 'credit']);
        $b = $this->makeCustomer(['default_driver_id' => $driver->id, 'customer_type' => 'credit']);
        $foreign = $this->makeCustomer(['default_driver_id' => $other->id, 'customer_type' => 'credit']);

        $this->makeOrder($a, ['total_price' => 200.00, 'paid_amount' => 50.00, 'status' => 'in_route']);   // 150
        $this->makeOrder($b, ['total_price' => 300.00, 'paid_amount' => 100.00, 'status' => 'delivered']); // 200

        // Cancelled invoice is excluded from the grand total.
        $this->makeOrder($b, ['total_price' => 999.00, 'paid_amount' => 0, 'status' => 'cancelled']);

        // Another driver's customer must not contribute to this driver's total.
        $this->makeOrder($foreign, ['total_price' => 500.00, 'paid_amount' => 0, 'status' => 'in_route']);

        $this->actingAs($driver, 'web_driver')
            ->get(route('driver.customers.index'))
            ->assertOk()
            ->assertSee('Total Outstanding')
            ->assertSee('RM 350.00');
    }

    /** @test */
    public function customer_detail_shows_record_payment_action_for_an_outstanding_invoice()
    {
        $driver = $this->makeDriver();
        $customer = $this->makeCustomer(['default_driver_id' => $driver->id]);

        $this->makeOrder($customer, [
            'invoice_number' => 'INV-OPEN-1',
            'status' => 'in_route',
            'paid_amount' => 0,
        ]);

        $this->actingAs($driver, 'web_driver')
            ->get(route('driver.customers.show', $customer->id))
            ->assertOk()
            ->assertSee('Record Payment');
    }

    /** @test */
    public function driver_can_record_a_cod_customer_payment_from_the_customer_page()
    {
        $driver = $this->makeDriver();
        $customer = $this->makeCustomer(['default_driver_id' => $driver->id]); // COD

        $order = $this->makeOrder($customer, [
            'invoice_number' => 'INV-COD-1',
            'total_price' => 150.00,
            'paid_amount' => 0,
            'status' => 'in_route',
            'payment_status' => 'unpaid',
        ]);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.customers.record-payment', [$customer->id, $order->id]), [
                'payment_method' => 'cash',
                'paid_amount' => 150.00,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

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
    public function cod_customer_payment_must_be_the_exact_balance_due()
    {
        $driver = $this->makeDriver();
        $customer = $this->makeCustomer(['default_driver_id' => $driver->id]);

        $order = $this->makeOrder($customer, [
            'total_price' => 150.00,
            'paid_amount' => 0,
            'status' => 'in_route',
        ]);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.customers.record-payment', [$customer->id, $order->id]), [
                'payment_method' => 'cash',
                'paid_amount' => 50.00,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertEquals(0.0, (float) $order->fresh()->paid_amount);
        $this->assertDatabaseMissing('order_payments', ['order_id' => $order->id]);
    }

    /** @test */
    public function qr_payment_from_the_customer_page_requires_proof()
    {
        $driver = $this->makeDriver();
        $customer = $this->makeCustomer(['default_driver_id' => $driver->id]);

        $order = $this->makeOrder($customer, [
            'total_price' => 150.00,
            'paid_amount' => 0,
            'status' => 'in_route',
        ]);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.customers.record-payment', [$customer->id, $order->id]), [
                'payment_method' => 'qr',
                'paid_amount' => 150.00,
            ])
            ->assertSessionHasErrors('payment_proof');

        $this->assertDatabaseMissing('order_payments', ['order_id' => $order->id]);
    }

    /** @test */
    public function driver_can_record_a_qr_payment_with_proof_from_the_customer_page()
    {
        Storage::fake('local');

        $driver = $this->makeDriver();
        $customer = $this->makeCustomer(['default_driver_id' => $driver->id]);

        $order = $this->makeOrder($customer, [
            'total_price' => 150.00,
            'paid_amount' => 0,
            'status' => 'in_route',
        ]);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.customers.record-payment', [$customer->id, $order->id]), [
                'payment_method' => 'qr',
                'paid_amount' => 150.00,
                'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $payment = \App\OrderPayment::where('order_id', $order->id)->first();
        $this->assertNotNull($payment);
        $this->assertEquals('qr', $payment->payment_method);
        $this->assertEquals($driver->id, $payment->recorded_by_driver);
        $this->assertNotNull($payment->payment_proof);
        Storage::disk('local')->assertExists(Order::$path . '/' . $order->id . '/payments/' . $payment->payment_proof);
    }

    /** @test */
    public function driver_can_record_a_partial_credit_payment_from_the_customer_page()
    {
        $driver = $this->makeDriver();
        $customer = $this->makeCustomer([
            'default_driver_id' => $driver->id,
            'customer_type' => 'credit',
            'payment_term_days' => 30,
        ]);

        $order = $this->makeOrder($customer, [
            'invoice_number' => 'INV-CREDIT-1',
            'total_price' => 150.00,
            'paid_amount' => 0,
            'status' => 'delivered',
            'payment_status' => 'unpaid',
            'payment_method' => 'term',
        ]);

        // Credit customers may collect less than the full balance (unlike COD).
        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.customers.record-payment', [$customer->id, $order->id]), [
                'payment_method' => 'credit',
                'paid_amount' => 50.00,
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $fresh = $order->fresh();
        $this->assertEquals(50.00, (float) $fresh->paid_amount);
        $this->assertEquals(100.00, $fresh->balanceDue());
        $this->assertFalse($fresh->isFullyPaid());
        $this->assertDatabaseHas('order_payments', [
            'order_id' => $order->id,
            'payment_method' => 'credit-term',
            'amount' => 50.00,
            'status' => 'confirmed',
            'recorded_by_driver' => $driver->id,
        ]);
    }

    /** @test */
    public function credit_term_method_is_rejected_for_a_cod_customer()
    {
        $driver = $this->makeDriver();
        $customer = $this->makeCustomer(['default_driver_id' => $driver->id]); // COD

        $order = $this->makeOrder($customer, [
            'total_price' => 150.00,
            'paid_amount' => 0,
            'status' => 'in_route',
        ]);

        // Credit Term is not a valid collection method for COD customers; the
        // service rejects it even though the request passes form validation.
        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.customers.record-payment', [$customer->id, $order->id]), [
                'payment_method' => 'credit',
                'paid_amount' => 150.00,
            ])
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertEquals(0.0, (float) $order->fresh()->paid_amount);
        $this->assertDatabaseMissing('order_payments', ['order_id' => $order->id]);
    }

    /** @test */
    public function driver_cannot_record_payment_for_another_drivers_customer()
    {
        $driver = $this->makeDriver();
        $other = $this->makeDriver();
        $foreign = $this->makeCustomer(['default_driver_id' => $other->id]);

        $order = $this->makeOrder($foreign, ['status' => 'in_route']);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.customers.record-payment', [$foreign->id, $order->id]), [
                'payment_method' => 'cash',
                'paid_amount' => 150.00,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('order_payments', ['order_id' => $order->id]);
    }

    /** @test */
    public function driver_cannot_record_payment_on_a_cancelled_invoice()
    {
        $driver = $this->makeDriver();
        $customer = $this->makeCustomer(['default_driver_id' => $driver->id]);

        $order = $this->makeOrder($customer, ['status' => 'cancelled']);

        $this->actingAs($driver, 'web_driver')
            ->post(route('driver.customers.record-payment', [$customer->id, $order->id]), [
                'payment_method' => 'cash',
                'paid_amount' => 150.00,
            ])
            ->assertNotFound();

        $this->assertDatabaseMissing('order_payments', ['order_id' => $order->id]);
    }
}
