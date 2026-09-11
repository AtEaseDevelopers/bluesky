<?php

namespace Tests\Feature;

use App\Admin;
use App\CustomerCreditLog;
use App\Order;
use App\OrderPayment;
use App\Services\OrderService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CreditTermPaymentTest extends TestCase
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

    private function makeCreditCustomer(): User
    {
        return User::forceCreate([
            'name' => 'Acme Seafood',
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'credit',
            'customer_type' => 'credit',
            'credit_balance' => 0,
            'status' => 'active',
            'payment_method' => 'credit-term',
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
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
            'status' => 'packing',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'credit-term',
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $attrs));
    }

    /** @test */
    public function recording_a_credit_term_payment_posts_an_outstanding_ledger_entry(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer);

        $payment = app(OrderService::class)->recordPayment(
            $order->fresh(),
            'credit-term',
            30.00,
            null,
            null,
            $admin->id
        );

        // Order balance is knocked off by the credit-term payment.
        $this->assertSame('credit-term', $payment->payment_method);
        $this->assertEqualsWithDelta(30.00, (float) $payment->amount, 0.001);
        $this->assertEqualsWithDelta(0.00, $order->fresh()->balanceDue(), 0.001);

        // A ledger entry is posted against the customer profile as outstanding.
        $log = CustomerCreditLog::where('user_id', $customer->id)
            ->where('type', 'credit_term')
            ->first();

        $this->assertNotNull($log, 'A credit_term ledger entry should be created.');
        $this->assertEqualsWithDelta(-30.00, (float) $log->amount, 0.001);
        $this->assertEqualsWithDelta(-30.00, (float) $log->balance_after, 0.001);
        $this->assertSame($order->id, $log->order_id);
        $this->assertSame($payment->id, $log->order_payment_id);
        $this->assertSame($admin->id, $log->recorded_by);

        // Customer credit balance goes negative (they now owe it).
        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);
    }

    /** @test */
    public function a_non_credit_term_payment_does_not_touch_the_credit_ledger(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer);

        app(OrderService::class)->recordPayment(
            $order->fresh(),
            'bank-transfer',
            30.00,
            null,
            null,
            $admin->id
        );

        $this->assertSame(0, CustomerCreditLog::where('user_id', $customer->id)->count());
        $this->assertEqualsWithDelta(0.00, (float) $customer->fresh()->credit_balance, 0.001);
    }

    /** @test */
    public function confirming_a_customer_submitted_credit_term_proof_posts_the_outstanding_entry(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer);

        $payment = OrderPayment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => 'credit-term',
            'amount' => 30.00,
            'status' => OrderPayment::STATUS_PENDING,
            'submitted_by_user_id' => $customer->id,
        ]);

        app(OrderService::class)->confirmPendingPayment($payment->fresh(), $admin->id);

        $log = CustomerCreditLog::where('user_id', $customer->id)
            ->where('type', 'credit_term')
            ->first();

        $this->assertNotNull($log, 'Confirming a credit-term proof should post the outstanding entry.');
        $this->assertEqualsWithDelta(-30.00, (float) $log->amount, 0.001);
        $this->assertSame($payment->id, $log->order_payment_id);
        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);
    }

    /** Simulate a pre-fix credit-term payment: order settled, no ledger entry. */
    private function makeLegacyCreditTermPayment(User $customer, Order $order, float $amount): OrderPayment
    {
        return OrderPayment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => 'credit-term',
            'amount' => $amount,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);
    }

    /** @test */
    public function backfill_dry_run_reports_but_writes_nothing(): void
    {
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer);
        $this->makeLegacyCreditTermPayment($customer, $order, 30.00);

        $this->artisan('credit:backfill-credit-term', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame(0, CustomerCreditLog::where('user_id', $customer->id)->count());
        $this->assertEqualsWithDelta(0.00, (float) $customer->fresh()->credit_balance, 0.001);
    }

    /** @test */
    public function backfill_creates_missing_ledger_entries_and_is_idempotent(): void
    {
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer);
        $payment = $this->makeLegacyCreditTermPayment($customer, $order, 30.00);

        $this->artisan('credit:backfill-credit-term')->assertExitCode(0);

        $log = CustomerCreditLog::where('order_payment_id', $payment->id)
            ->where('type', 'credit_term')
            ->first();

        $this->assertNotNull($log, 'Backfill should create the missing ledger entry.');
        $this->assertEqualsWithDelta(-30.00, (float) $log->amount, 0.001);
        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);

        // Running again must not double-post.
        $this->artisan('credit:backfill-credit-term')->assertExitCode(0);

        $this->assertSame(1, CustomerCreditLog::where('order_payment_id', $payment->id)->count());
        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);
    }
}
