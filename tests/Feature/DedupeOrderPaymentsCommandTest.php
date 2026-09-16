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

class DedupeOrderPaymentsCommandTest extends TestCase
{
    use RefreshDatabase;

    private function makeCreditCustomer(): User
    {
        return User::forceCreate([
            'name' => 'Credit Co',
            'email' => 'credit' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'restaurant',
            'customer_type' => 'credit',
            'payment_term_days' => 30,
            'status' => 'active',
            'payment_method' => json_encode(['term']),
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    private function makeCreditOrder(User $customer): Order
    {
        return Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 500.00,
            'subtotal' => 500.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => 'delivered',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'term',
            'payment_status' => 'unpaid',
            'payment_due_date' => now()->addDays(30)->toDateString(),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);
    }

    /** @test */
    public function it_reverses_a_duplicate_credit_term_payment_and_reconciles(): void
    {
        $admin = Admin::forceCreate([
            'name' => 'Nicole', 'username' => 'nicole' . rand(1000, 9999),
            'email' => 'nicole' . rand(1000, 9999) . '@example.com',
            'role' => 'superadmin', 'password' => Hash::make('password'),
        ]);
        $order = $this->makeCreditOrder($this->makeCreditCustomer());
        $svc = app(OrderService::class);

        // Two identical credit-term charges — the double-submit scenario.
        $svc->recordPayment($order->fresh(), 'credit-term', 500.00, null, null, $admin->id, null, false, true);
        $svc->recordPayment($order->fresh(), 'credit-term', 500.00, null, null, $admin->id, null, false, true);

        $this->assertSame(2, OrderPayment::where('order_id', $order->id)->count());
        $this->assertEquals(1000.00, (float) $order->fresh()->paid_amount);

        $this->artisan('orders:dedupe-payments', ['ids' => [$order->id]])
            ->expectsConfirmation('Reverse the 1 duplicate payment(s) above?', 'yes')
            ->assertExitCode(0);

        // One payment left, paid_amount back to the single charge, ledger reversed.
        $this->assertSame(1, OrderPayment::where('order_id', $order->id)->count());
        $this->assertEquals(500.00, (float) $order->fresh()->paid_amount);
        $this->assertTrue(
            CustomerCreditLog::where('order_id', $order->id)->where('type', 'credit_reversal')->exists(),
            'a credit_reversal ledger entry should be posted for the removed duplicate'
        );
    }

    /** @test */
    public function dry_run_writes_nothing(): void
    {
        $admin = Admin::forceCreate([
            'name' => 'Nicole', 'username' => 'nicole' . rand(1000, 9999),
            'email' => 'nicole' . rand(1000, 9999) . '@example.com',
            'role' => 'superadmin', 'password' => Hash::make('password'),
        ]);
        $order = $this->makeCreditOrder($this->makeCreditCustomer());
        $svc = app(OrderService::class);
        $svc->recordPayment($order->fresh(), 'credit-term', 500.00, null, null, $admin->id, null, false, true);
        $svc->recordPayment($order->fresh(), 'credit-term', 500.00, null, null, $admin->id, null, false, true);

        $this->artisan('orders:dedupe-payments', ['ids' => [$order->id], '--dry-run' => true])
            ->assertExitCode(0);

        // Untouched.
        $this->assertSame(2, OrderPayment::where('order_id', $order->id)->count());
    }
}
