<?php

namespace Tests\Feature;

use App\Admin;
use App\CustomerCreditLog;
use App\Order;
use App\OrderPayment;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPaymentEditDeleteTest extends TestCase
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

    private function makeCustomer(string $customerType = 'cod'): User
    {
        return User::forceCreate([
            'name' => 'Acme Seafood',
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'cod',
            'customer_type' => $customerType,
            'status' => 'active',
            'payment_method' => $customerType === 'credit' ? 'term' : 'cod',
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    private function makeOrder(User $customer): Order
    {
        return Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 30.00,
            'subtotal' => 30.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 30.00,
            'status' => 'in_route',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'cod',
            'payment_status' => 'paid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);
    }

    private function makePayment(Order $order, string $method = 'cash', float $amount = 30.00): OrderPayment
    {
        return OrderPayment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => $method,
            'amount' => $amount,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);
    }

    /** @test */
    public function admin_can_edit_a_recorded_payment_and_totals_recalculate(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer());
        $payment = $this->makePayment($order, 'cash', 30.00);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payments.update', [$order->id, $payment->id]), [
                'payment_method' => 'bank-transfer',
                'amount' => 20.00,
                'notes' => 'corrected',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('order_payments', [
            'id' => $payment->id,
            'payment_method' => 'bank-transfer',
            'amount' => 20.00,
            'notes' => 'corrected',
        ]);
        // paid_amount is recomputed from confirmed payments.
        $this->assertEquals(20.00, (float) $order->fresh()->paid_amount);
    }

    /** @test */
    public function admin_can_attach_a_proof_when_editing_a_payment(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer());
        $payment = $this->makePayment($order, 'cash', 30.00);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payments.update', [$order->id, $payment->id]), [
                'payment_method' => 'bank-transfer',
                'amount' => 30.00,
                'payment_proof' => UploadedFile::fake()->image('slip.jpg'),
            ])
            ->assertSessionHas('success');

        $stored = $payment->fresh()->payment_proof;
        $this->assertNotEmpty($stored);
        Storage::disk('local')->assertExists(\App\Order::$path . '/' . $order->id . '/payments/' . $stored);
    }

    /** @test */
    public function admin_can_delete_a_recorded_payment_and_totals_recalculate(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer());
        $payment = $this->makePayment($order, 'cash', 30.00);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payments.destroy', [$order->id, $payment->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('order_payments', ['id' => $payment->id]);
        $this->assertEquals(0.00, (float) $order->fresh()->paid_amount);
    }

    /** @test */
    public function editing_a_credit_term_payment_is_refused(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer('credit'));
        $payment = $this->makePayment($order, 'credit-term', 30.00);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payments.update', [$order->id, $payment->id]), [
                'payment_method' => 'cash',
                'amount' => 20.00,
            ])
            ->assertSessionHas('error');

        $this->assertDatabaseHas('order_payments', [
            'id' => $payment->id,
            'payment_method' => 'credit-term',
            'amount' => 30.00,
        ]);
    }

    /** @test */
    public function deleting_a_credit_term_payment_is_refused(): void
    {
        $admin = $this->makeAdmin();
        $order = $this->makeOrder($this->makeCustomer('credit'));
        $payment = $this->makePayment($order, 'credit-term', 30.00);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payments.destroy', [$order->id, $payment->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('order_payments', ['id' => $payment->id]);
    }

    /** @test */
    public function deleting_a_payment_with_a_linked_credit_ledger_entry_is_refused(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer('credit');
        $order = $this->makeOrder($customer);
        // A cash payment that produced an overpayment credit entry (ledger-linked).
        $payment = $this->makePayment($order, 'cash', 30.00);
        CustomerCreditLog::forceCreate([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'order_payment_id' => $payment->id,
            'type' => 'applied_to_order',
            'amount' => -5.00,
            'balance_after' => 0,
        ]);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payments.destroy', [$order->id, $payment->id]))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('order_payments', ['id' => $payment->id]);
    }

    /** @test */
    public function a_payment_from_another_order_cannot_be_edited(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer();
        $orderA = $this->makeOrder($customer);
        $orderB = $this->makeOrder($customer);
        $foreign = $this->makePayment($orderB, 'cash', 30.00);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payments.update', [$orderA->id, $foreign->id]), [
                'payment_method' => 'cash',
                'amount' => 10.00,
            ])
            ->assertNotFound();
    }
}
