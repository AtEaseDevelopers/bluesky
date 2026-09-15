<?php

namespace Tests\Feature;

use App\Admin;
use App\CustomerCreditLog;
use App\Order;
use App\Services\OrderService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Settling a credit order's outstanding balance straight from its own summary
 * page — the single-order counterpart to the customer-profile mark-paid list.
 */
class AdminOrderSummaryMarkCreditPaidTest extends TestCase
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

    /** A delivered credit-term order parked on the credit account (customer owes). */
    private function makeCreditOrder(User $customer, Admin $admin, float $amount = 30.00): Order
    {
        $order = Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => $amount,
            'subtotal' => $amount,
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
        ]);

        app(OrderService::class)->recordPayment($order->fresh(), 'credit-term', $amount, null, null, $admin->id);

        return $order->fresh();
    }

    /** @test */
    public function the_summary_page_shows_the_settle_credit_form_for_an_outstanding_credit_order(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeCreditOrder($customer, $admin);

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.orders.summary', $order->id))
            ->assertOk()
            ->assertSee(route('admin.orders.credit.mark-paid', $order->id));
    }

    /** @test */
    public function marking_paid_records_method_amount_and_stored_proof_then_completes(): void
    {
        Storage::fake('local');

        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeCreditOrder($customer, $admin);

        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.credit.mark-paid', $order->id), [
                'amount' => 30.00,
                'payment_method' => 'bank-transfer',
                'payment_proof' => UploadedFile::fake()->image('slip.jpg'),
            ])
            ->assertRedirect();

        $log = CustomerCreditLog::where('order_id', $order->id)
            ->where('type', 'credit_settlement')
            ->first();

        $this->assertNotNull($log);
        $this->assertSame('bank-transfer', $log->payment_method);
        $this->assertNotNull($log->payment_proof);
        Storage::disk('local')->assertExists(Order::$path . '/' . $order->id . '/payments/' . $log->payment_proof);

        // Fully settled and completed.
        $this->assertEqualsWithDelta(0.00, (float) $customer->fresh()->credit_balance, 0.001);
        $this->assertEqualsWithDelta(0.00, $order->fresh()->creditOutstandingAmount(), 0.001);
        $this->assertSame(Order::$status['completed'], $order->fresh()->status);
        $this->assertSame(Order::$payment_status['paid'], $order->fresh()->payment_status);
    }

    /** @test */
    public function a_partial_payment_settles_part_and_leaves_the_order_on_credit(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeCreditOrder($customer, $admin);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.credit.mark-paid', $order->id), [
                'amount' => 10.00,
                'payment_method' => 'cash',
            ])
            ->assertRedirect();

        $this->assertEqualsWithDelta(-20.00, (float) $customer->fresh()->credit_balance, 0.001);
        $this->assertEqualsWithDelta(20.00, $order->fresh()->creditOutstandingAmount(), 0.001);
        $this->assertSame(Order::$status['delivered'], $order->fresh()->status);
        $this->assertSame(Order::$payment_status['partial'], $order->fresh()->payment_status);
    }

    /** @test */
    public function an_amount_above_the_outstanding_is_clamped_to_what_is_owed(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeCreditOrder($customer, $admin);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.credit.mark-paid', $order->id), [
                'amount' => 999.00,
                'payment_method' => 'bank-transfer',
            ])
            ->assertRedirect();

        $log = CustomerCreditLog::where('order_id', $order->id)
            ->where('type', 'credit_settlement')
            ->first();

        $this->assertEqualsWithDelta(30.00, (float) $log->amount, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $customer->fresh()->credit_balance, 0.001);
    }

    /** @test */
    public function a_missing_payment_method_fails_validation_and_settles_nothing(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeCreditOrder($customer, $admin);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.credit.mark-paid', $order->id), [
                'amount' => 30.00,
            ])
            ->assertSessionHasErrors('payment_method');

        $this->assertSame(
            0,
            CustomerCreditLog::where('order_id', $order->id)->where('type', 'credit_settlement')->count()
        );
        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);
        $this->assertSame(Order::$status['delivered'], $order->fresh()->status);
    }

    /** @test */
    public function an_order_with_nothing_outstanding_settles_nothing(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeCreditOrder($customer, $admin);

        // Clear it fully first.
        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.credit.mark-paid', $order->id), [
                'amount' => 30.00,
                'payment_method' => 'cash',
            ])->assertRedirect();

        // A second attempt has nothing left to settle — no new settlement log.
        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.orders.credit.mark-paid', $order->id), [
                'amount' => 30.00,
                'payment_method' => 'cash',
            ])->assertRedirect();

        $this->assertSame(
            1,
            CustomerCreditLog::where('order_id', $order->id)->where('type', 'credit_settlement')->count()
        );
    }
}
