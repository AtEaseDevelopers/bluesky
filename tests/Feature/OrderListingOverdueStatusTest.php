<?php

namespace Tests\Feature;

use App\Order;
use App\Services\OrderService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderListingOverdueStatusTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): User
    {
        return User::forceCreate([
            'name' => 'Acme ' . rand(1000, 9999),
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

    private function makeOrder(User $customer, array $overrides = []): Order
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
            'status' => 'delivered',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'credit-term',
            'payment_status' => 'unpaid',
            'payment_due_date' => now()->subDay()->toDateString(),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $overrides));
    }

    /** @test */
    public function it_flags_overdue_unpaid_orders_as_payment_due(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, ['payment_due_date' => now()->subDay()->toDateString()]);

        $flagged = app(OrderService::class)->markOverduePaymentsDue();

        $this->assertSame(1, $flagged);
        $this->assertSame('payment_due', $order->fresh()->payment_status);
    }

    /** @test */
    public function it_flags_orders_due_today(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, ['payment_due_date' => now()->toDateString()]);

        app(OrderService::class)->markOverduePaymentsDue();

        $this->assertSame('payment_due', $order->fresh()->payment_status);
    }

    /** @test */
    public function it_leaves_orders_not_yet_due_alone(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, ['payment_due_date' => now()->addWeek()->toDateString()]);

        app(OrderService::class)->markOverduePaymentsDue();

        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    /** @test */
    public function it_ignores_orders_without_a_due_date(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, ['payment_due_date' => null]);

        app(OrderService::class)->markOverduePaymentsDue();

        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    /** @test */
    public function it_leaves_non_unpaid_and_cancelled_orders_alone(): void
    {
        $customer = $this->makeCustomer();
        $paid = $this->makeOrder($customer, ['payment_status' => 'paid']);
        $pending = $this->makeOrder($customer, ['payment_status' => 'pending']);
        $cancelled = $this->makeOrder($customer, ['status' => 'cancelled']);

        app(OrderService::class)->markOverduePaymentsDue();

        $this->assertSame('paid', $paid->fresh()->payment_status);
        $this->assertSame('pending', $pending->fresh()->payment_status);
        $this->assertSame('unpaid', $cancelled->fresh()->payment_status);
    }

    /** @test */
    public function it_flags_the_whole_overdue_set_in_a_single_query(): void
    {
        $customer = $this->makeCustomer();
        $this->makeOrder($customer);
        $this->makeOrder($customer);
        $this->makeOrder($customer);

        DB::enableQueryLog();
        app(OrderService::class)->markOverduePaymentsDue();
        $writes = collect(DB::getQueryLog())
            ->filter(fn ($q) => str_starts_with(strtolower(trim($q['query'])), 'update'))
            ->count();
        DB::disableQueryLog();

        $this->assertSame(1, $writes, 'Overdue orders must be flagged with one bulk UPDATE, not per-order writes.');
    }

    /** A confirmed non-credit payment covering the whole order — enough for the
     * full reconciliation to derive 'paid'. */
    private function payInFull(Order $order): void
    {
        \App\OrderPayment::forceCreate([
            'order_id' => $order->id,
            'amount' => (float) $order->total_price,
            'status' => \App\OrderPayment::STATUS_CONFIRMED,
            'settles_credit' => false,
            'payment_method' => 'bank-transfer',
        ]);
    }

    /** @test */
    public function per_page_refresh_runs_the_full_reconciliation_not_just_payment_due(): void
    {
        $customer = $this->makeCustomer();
        // Fully paid but its stored label drifted to 'unpaid' (what only the full
        // per-order reconciliation — not the payment_due flip — can correct).
        $order = $this->makeOrder($customer, ['payment_status' => 'unpaid']);
        $this->payInFull($order);

        app(OrderService::class)->refreshPaymentStatusesForPage([$order]);

        $this->assertSame('paid', $order->fresh()->payment_status);
        // The in-memory row the listing renders is updated too, no reload needed.
        $this->assertSame('paid', $order->payment_status);
    }

    /** @test */
    public function per_page_refresh_only_touches_the_given_page_rows(): void
    {
        $customer = $this->makeCustomer();
        $onPage = $this->makeOrder($customer, ['payment_status' => 'unpaid']);
        $offPage = $this->makeOrder($customer, ['payment_status' => 'unpaid']);
        $this->payInFull($onPage);
        $this->payInFull($offPage);

        app(OrderService::class)->refreshPaymentStatusesForPage([$onPage]);

        $this->assertSame('paid', $onPage->fresh()->payment_status);
        // Off-page order is left for the daily cron — not refreshed here.
        $this->assertSame('unpaid', $offPage->fresh()->payment_status);
    }

    /** @test */
    public function per_page_refresh_leaves_paid_rows_untouched_like_the_old_sync(): void
    {
        $customer = $this->makeCustomer();
        // Labelled 'paid' but carrying no payments — a full reconciliation would
        // flip it to unpaid. The old global sync excluded paid orders, so this
        // must stay 'paid' (and skip any invoice regeneration).
        $order = $this->makeOrder($customer, ['payment_status' => 'paid']);

        app(OrderService::class)->refreshPaymentStatusesForPage([$order]);

        $this->assertSame('paid', $order->fresh()->payment_status);
    }

    /** @test */
    public function per_page_refresh_skips_rows_not_yet_due(): void
    {
        $customer = $this->makeCustomer();
        // Unpaid but not yet due, yet actually fully paid — the old sync would not
        // have reconciled it (due date not passed), so it stays unpaid here.
        $order = $this->makeOrder($customer, [
            'payment_status' => 'unpaid',
            'payment_due_date' => now()->addWeek()->toDateString(),
        ]);
        $this->payInFull($order);

        app(OrderService::class)->refreshPaymentStatusesForPage([$order]);

        $this->assertSame('unpaid', $order->fresh()->payment_status);
    }

    /** @test */
    public function per_page_refresh_handles_an_empty_page(): void
    {
        app(OrderService::class)->refreshPaymentStatusesForPage([]);

        $this->assertTrue(true);
    }
}
