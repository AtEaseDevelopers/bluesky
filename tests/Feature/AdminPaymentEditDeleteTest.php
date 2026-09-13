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

    /**
     * A credit-term payment whose charge is already posted to the customer credit
     * ledger — mirrors what recording a credit-term payment produces.
     */
    private function makeCreditTermPayment(Order $order, User $customer, float $amount = 30.00): OrderPayment
    {
        $customer->update(['credit_balance' => -$amount]);
        $payment = $this->makePayment($order, 'credit-term', $amount);
        CustomerCreditLog::forceCreate([
            'user_id' => $customer->id,
            'order_id' => $order->id,
            'order_payment_id' => $payment->id,
            'type' => 'credit_term',
            'amount' => -$amount,
            'balance_before' => 0,
            'balance_after' => -$amount,
        ]);

        return $payment;
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
    public function editing_a_credit_term_payment_reverses_and_reposts_the_ledger_charge(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer('credit');
        $order = $this->makeOrder($customer);
        $payment = $this->makeCreditTermPayment($order, $customer, 30.00);

        // Reduce the credit-term amount: the old RM30 charge is reversed and a
        // fresh RM20 charge posted, leaving the customer owing exactly RM20.
        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payments.update', [$order->id, $payment->id]), [
                'payment_method' => 'credit-term',
                'amount' => 20.00,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('order_payments', [
            'id' => $payment->id,
            'payment_method' => 'credit-term',
            'amount' => 20.00,
        ]);
        $this->assertEqualsWithDelta(-20.00, (float) $customer->fresh()->credit_balance, 0.001);
        $this->assertDatabaseHas('customer_credit_logs', [
            'order_payment_id' => $payment->id,
            'type' => 'credit_reversal',
        ]);
    }

    /** @test */
    public function editing_a_credit_term_payment_to_cash_clears_the_ledger_charge(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer('credit');
        $order = $this->makeOrder($customer);
        $payment = $this->makeCreditTermPayment($order, $customer, 30.00);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payments.update', [$order->id, $payment->id]), [
                'payment_method' => 'cash',
                'amount' => 30.00,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('order_payments', [
            'id' => $payment->id,
            'payment_method' => 'cash',
        ]);
        // Charge fully reversed — the customer no longer owes on account.
        $this->assertEqualsWithDelta(0.00, (float) $customer->fresh()->credit_balance, 0.001);
    }

    /** @test */
    public function admin_can_convert_a_recorded_payment_to_credit_term_and_it_posts_to_the_ledger(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer('credit');
        $order = $this->makeOrder($customer);
        $payment = $this->makePayment($order, 'cash', 30.00);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payments.update', [$order->id, $payment->id]), [
                'payment_method' => 'credit-term',
                'amount' => 30.00,
                'notes' => 'on account',
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('order_payments', [
            'id' => $payment->id,
            'payment_method' => 'credit-term',
            'amount' => 30.00,
        ]);

        // A credit-term charge is mirrored onto the customer credit ledger,
        // linked back to this payment, so the amount shows as owed on account.
        $this->assertDatabaseHas('customer_credit_logs', [
            'order_id' => $order->id,
            'order_payment_id' => $payment->id,
        ]);
        $this->assertTrue($payment->fresh()->isLedgerBacked());
    }

    /** @test */
    public function a_converted_credit_term_payment_can_be_edited_again(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer('credit');
        $order = $this->makeOrder($customer);
        $payment = $this->makePayment($order, 'cash', 30.00);

        // Convert cash -> credit-term (posts a RM30 charge).
        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payments.update', [$order->id, $payment->id]), [
                'payment_method' => 'credit-term',
                'amount' => 30.00,
            ])
            ->assertSessionHas('success');
        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);

        // Re-edit down to RM20: charge reversed and re-posted, net owed RM20.
        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payments.update', [$order->id, $payment->id]), [
                'payment_method' => 'credit-term',
                'amount' => 20.00,
            ])
            ->assertSessionHas('success');

        $this->assertDatabaseHas('order_payments', [
            'id' => $payment->id,
            'payment_method' => 'credit-term',
            'amount' => 20.00,
        ]);
        $this->assertEqualsWithDelta(-20.00, (float) $customer->fresh()->credit_balance, 0.001);
    }

    /** @test */
    public function deleting_a_credit_term_payment_reverses_the_ledger_charge(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCustomer('credit');
        $order = $this->makeOrder($customer);
        $payment = $this->makeCreditTermPayment($order, $customer, 30.00);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.payments.destroy', [$order->id, $payment->id]))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('order_payments', ['id' => $payment->id]);
        // The charge is reversed so the customer no longer owes on account.
        $this->assertEqualsWithDelta(0.00, (float) $customer->fresh()->credit_balance, 0.001);
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
