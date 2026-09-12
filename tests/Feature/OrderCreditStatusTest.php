<?php

namespace Tests\Feature;

use App\Admin;
use App\CustomerCreditLog;
use App\Order;
use App\OrderPayment;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class OrderCreditStatusTest extends TestCase
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
            'payment_method' => json_encode(['credit-term']),
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
            'status' => 'delivered',
            'fulfillment_type' => 'delivery',
            'driver_id' => null,
            'payment_method' => 'credit-term',
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $attrs));
    }

    /** @test */
    public function credit_is_a_registered_order_status(): void
    {
        $this->assertArrayHasKey('credit', Order::$status);
    }

    /** @test */
    public function recording_a_credit_term_payment_on_a_delivered_order_moves_it_to_credit_status(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer);

        app(OrderService::class)->recordPayment(
            $order->fresh(),
            'credit-term',
            30.00,
            null,
            null,
            $admin->id
        );

        $this->assertSame(Order::$status['credit'], $order->fresh()->status);
    }

    /** @test */
    public function delivering_an_order_that_already_has_a_credit_term_charge_enters_credit_status(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        // Credit-term agreed up front while still packing.
        $order = $this->makeOrder($customer, ['status' => 'packing']);

        app(OrderService::class)->recordPayment(
            $order->fresh(),
            'credit-term',
            30.00,
            null,
            null,
            $admin->id
        );

        // Still packing — fulfilment continues normally.
        $this->assertSame(Order::$status['packing'], $order->fresh()->status);

        $service = app(OrderStatusService::class);
        $order->update(['driver_id' => 1]);
        $service->transition($order->fresh(), Order::$status['in_route'], $admin->id);
        $order = $service->transition($order->fresh(), Order::$status['delivered'], $admin->id);

        // Reaching delivered auto-routes into the credit holding state.
        $this->assertSame(Order::$status['credit'], $order->fresh()->status);
    }

    /** @test */
    public function a_credit_order_cannot_be_completed_while_the_customer_still_owes(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer);

        app(OrderService::class)->recordPayment(
            $order->fresh(),
            'credit-term',
            30.00,
            null,
            null,
            $admin->id
        );

        $this->assertSame(Order::$status['credit'], $order->fresh()->status);

        $this->expectException(\InvalidArgumentException::class);
        app(OrderStatusService::class)->transition(
            $order->fresh(),
            Order::$status['completed'],
            $admin->id
        );
    }

    /** @test */
    public function next_statuses_hides_complete_for_an_outstanding_credit_order(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer);

        app(OrderService::class)->recordPayment(
            $order->fresh(),
            'credit-term',
            30.00,
            null,
            null,
            $admin->id
        );

        $next = app(OrderStatusService::class)->nextStatuses($order->fresh());

        $this->assertNotContains(Order::$status['completed'], $next);
        // 'credit' is an automatic state, never offered as a manual action.
        $this->assertNotContains(Order::$status['credit'], $next);
    }

    /** @test */
    public function a_non_credit_paid_order_completes_even_when_the_customer_owes_on_another_order(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();

        // Order A parked on credit (customer now owes 30).
        $creditOrder = $this->makeOrder($customer);
        app(OrderService::class)->recordPayment(
            $creditOrder->fresh(),
            'credit-term',
            30.00,
            null,
            null,
            $admin->id
        );

        // Order B fully paid by bank transfer — nothing to do with the credit account.
        $paidOrder = $this->makeOrder($customer);
        app(OrderService::class)->recordPayment(
            $paidOrder->fresh(),
            'bank-transfer',
            30.00,
            null,
            null,
            $admin->id
        );

        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);

        // Order B completes despite the outstanding balance on Order A.
        $completed = app(OrderStatusService::class)->transition(
            $paidOrder->fresh(),
            Order::$status['completed'],
            $admin->id
        );

        $this->assertSame(Order::$status['completed'], $completed->fresh()->status);
        // Order A remains held on credit.
        $this->assertSame(Order::$status['credit'], $creditOrder->fresh()->status);
    }

    /** @test */
    public function marking_selected_credit_orders_paid_settles_and_completes_each(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();

        $orderA = $this->makeOrder($customer);
        $orderB = $this->makeOrder($customer);

        foreach ([$orderA, $orderB] as $order) {
            app(OrderService::class)->recordPayment(
                $order->fresh(),
                'credit-term',
                30.00,
                null,
                null,
                $admin->id
            );
        }

        $this->assertSame(Order::$status['credit'], $orderA->fresh()->status);
        $this->assertSame(Order::$status['credit'], $orderB->fresh()->status);
        $this->assertEqualsWithDelta(-60.00, (float) $customer->fresh()->credit_balance, 0.001);

        $response = $this->actingAs($admin, 'web_admin')
            ->post(route('admin.customers.credit.mark-paid', encrypt($customer->id)), [
                'order_ids' => [$orderA->id, $orderB->id],
            ]);

        $response->assertRedirect();

        // Ledger settled to zero — one settlement entry per order, tagged to it.
        $this->assertEqualsWithDelta(0.00, (float) $customer->fresh()->credit_balance, 0.001);
        $this->assertSame(
            1,
            CustomerCreditLog::where('order_id', $orderA->id)->where('type', 'credit_settlement')->count()
        );
        $this->assertSame(
            1,
            CustomerCreditLog::where('order_id', $orderB->id)->where('type', 'credit_settlement')->count()
        );

        $this->assertSame(Order::$status['completed'], $orderA->fresh()->status);
        $this->assertSame(Order::$status['completed'], $orderB->fresh()->status);
    }

    /** @test */
    public function marking_one_order_paid_leaves_the_other_on_credit(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();

        $orderA = $this->makeOrder($customer);
        $orderB = $this->makeOrder($customer);

        foreach ([$orderA, $orderB] as $order) {
            app(OrderService::class)->recordPayment(
                $order->fresh(),
                'credit-term',
                30.00,
                null,
                null,
                $admin->id
            );
        }

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.customers.credit.mark-paid', encrypt($customer->id)), [
                'order_ids' => [$orderA->id],
            ])
            ->assertRedirect();

        // Only order A settled and completed; B still owes and stays on credit.
        $this->assertSame(Order::$status['completed'], $orderA->fresh()->status);
        $this->assertSame(Order::$status['credit'], $orderB->fresh()->status);
        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);
        $this->assertEqualsWithDelta(0.00, $orderA->fresh()->creditOutstandingAmount(), 0.001);
        $this->assertEqualsWithDelta(30.00, $orderB->fresh()->creditOutstandingAmount(), 0.001);
    }

    /** @test */
    public function customer_edit_page_lists_credit_orders_with_links(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer);

        app(OrderService::class)->recordPayment(
            $order->fresh(),
            'credit-term',
            30.00,
            null,
            null,
            $admin->id
        );

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.customers.edit', encrypt($customer->id)))
            ->assertOk()
            ->assertSee(route('admin.orders.summary', $order->id));
    }

    /** @test */
    public function marking_paid_with_no_orders_selected_does_nothing(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer);

        app(OrderService::class)->recordPayment(
            $order->fresh(),
            'credit-term',
            30.00,
            null,
            null,
            $admin->id
        );

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.customers.credit.mark-paid', encrypt($customer->id)), [])
            ->assertRedirect();

        // Unchanged: still owing, still in credit status.
        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);
        $this->assertSame(Order::$status['credit'], $order->fresh()->status);
    }

    /** A pre-feature order: completed with an unsettled credit-term charge. */
    private function makeLegacyCompletedCreditOrder(User $customer, float $amount = 30.00): Order
    {
        $order = $this->makeOrder($customer, [
            'total_price' => $amount,
            'subtotal' => $amount,
            'paid_amount' => $amount,
            'status' => Order::$status['completed'],
            'payment_status' => Order::$payment_status['paid'],
            'completed_at' => now()->subDays(3),
        ]);

        OrderPayment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => 'credit-term',
            'amount' => $amount,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);

        return $order->fresh();
    }

    /** @test */
    public function backfill_dry_run_reports_but_leaves_orders_completed(): void
    {
        $customer = $this->makeCreditCustomer();
        $order = $this->makeLegacyCompletedCreditOrder($customer);

        $this->artisan('credit:backfill-credit-status', ['--dry-run' => true])
            ->assertExitCode(0);

        $this->assertSame(Order::$status['completed'], $order->fresh()->status);
        $this->assertNotNull($order->fresh()->completed_at);
    }

    /** @test */
    public function backfill_moves_unsettled_completed_credit_orders_to_credit(): void
    {
        $customer = $this->makeCreditCustomer();
        $order = $this->makeLegacyCompletedCreditOrder($customer);

        $this->artisan('credit:backfill-credit-status')->assertExitCode(0);

        $this->assertSame(Order::$status['credit'], $order->fresh()->status);
        $this->assertNull($order->fresh()->completed_at);
    }

    /** @test */
    public function backfill_moves_delivered_credit_orders_that_never_advanced(): void
    {
        $customer = $this->makeCreditCustomer();

        // Delivered credit-term order that predates the auto-credit trigger.
        $order = $this->makeOrder($customer, [
            'status' => Order::$status['delivered'],
            'payment_status' => Order::$payment_status['paid'],
            'paid_amount' => 30.00,
        ]);
        OrderPayment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => 'credit-term',
            'amount' => 30.00,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);

        $this->artisan('credit:backfill-credit-status')->assertExitCode(0);

        $this->assertSame(Order::$status['credit'], $order->fresh()->status);
    }

    /** @test */
    public function backfill_skips_a_completed_credit_order_that_was_settled(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeLegacyCompletedCreditOrder($customer, 30.00);

        // Its credit-term charge has already been cleared on the ledger.
        CustomerCreditLog::forceCreate([
            'user_id' => $customer->id,
            'type' => 'credit_settlement',
            'amount' => 30.00,
            'balance_before' => -30.00,
            'balance_after' => 0,
            'order_id' => $order->id,
            'recorded_by' => $admin->id,
        ]);

        $this->assertEqualsWithDelta(0.00, $order->fresh()->creditOutstandingAmount(), 0.001);

        $this->artisan('credit:backfill-credit-status')->assertExitCode(0);

        $this->assertSame(Order::$status['completed'], $order->fresh()->status);
    }

    /** @test */
    public function backfill_ignores_completed_orders_without_a_credit_term_charge(): void
    {
        $customer = $this->makeCreditCustomer();

        $order = $this->makeOrder($customer, [
            'status' => Order::$status['completed'],
            'payment_status' => Order::$payment_status['paid'],
            'paid_amount' => 30.00,
        ]);
        OrderPayment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => 'bank-transfer',
            'amount' => 30.00,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);

        $this->artisan('credit:backfill-credit-status')->assertExitCode(0);

        $this->assertSame(Order::$status['completed'], $order->fresh()->status);
    }
}
