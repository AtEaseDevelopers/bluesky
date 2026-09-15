<?php

namespace Tests\Feature;

use App\Admin;
use App\Order;
use App\Services\OrderService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class BackfillCreditOrderPaymentStatusTest extends TestCase
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
            'name' => 'Acme Seafood ' . rand(1000, 9999),
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
        ]);
    }

    /** A credit-term order carrying an outstanding balance, forced back to the
     * pre-fix 'paid' state to simulate legacy data. */
    private function makeMislabelledCreditOrder(User $customer, Admin $admin): Order
    {
        $order = Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 30.00,
            'subtotal' => 30.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => 'delivered',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'credit-term',
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);

        app(OrderService::class)->recordPayment($order->fresh(), 'credit-term', 30.00, null, null, $admin->id);

        // Simulate the legacy bug: outstanding on the ledger but flagged paid.
        $order->forceFill(['payment_status' => 'paid'])->saveQuietly();

        return $order->fresh();
    }

    /** @test */
    public function dry_run_reports_mislabelled_orders_without_changing_them(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeMislabelledCreditOrder($customer, $admin);

        $this->artisan('orders:backfill-credit-payment-status --dry-run')
            ->assertExitCode(0);

        // Untouched by a dry run.
        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    /** @test */
    public function it_re_derives_mislabelled_credit_orders_to_unpaid(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeMislabelledCreditOrder($customer, $admin);

        $this->artisan('orders:backfill-credit-payment-status')
            ->assertExitCode(0);

        $this->assertSame('unpaid', $order->fresh()->payment_status);
        // Order-level plumbing is left alone — this only corrects the label.
        $this->assertEqualsWithDelta(30.00, (float) $order->fresh()->paid_amount, 0.001);
    }

    /** @test */
    public function it_leaves_fully_settled_credit_orders_alone(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeMislabelledCreditOrder($customer, $admin);

        // Settle the whole outstanding amount, then flag paid (as settlement does).
        app(\App\Services\CreditService::class)->settleOrderCredit($order->fresh(), $admin->id, null, 30.00, 'bank-transfer');
        $order->forceFill(['payment_status' => 'paid'])->saveQuietly();

        $this->artisan('orders:backfill-credit-payment-status')
            ->assertExitCode(0);

        $this->assertSame('paid', $order->fresh()->payment_status);
    }
}
