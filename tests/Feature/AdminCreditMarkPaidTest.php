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

class AdminCreditMarkPaidTest extends TestCase
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
    private function makeCreditOrder(User $customer, Admin $admin, float $amount = 30.00, array $attrs = []): Order
    {
        $order = Order::forceCreate(array_merge([
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
        ], $attrs));

        app(OrderService::class)->recordPayment($order->fresh(), 'credit-term', $amount, null, null, $admin->id);

        return $order->fresh();
    }

    /** @test */
    public function marking_paid_records_method_amount_and_stored_proof_on_the_settlement(): void
    {
        Storage::fake('local');

        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeCreditOrder($customer, $admin);

        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.customers.credit.mark-paid', encrypt($customer->id)), [
                'order_ids' => [$order->id],
                'payments' => [
                    $order->id => [
                        'amount' => 30.00,
                        'payment_method' => 'bank-transfer',
                        'payment_proof' => UploadedFile::fake()->image('slip.jpg'),
                    ],
                ],
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
        // Only now — once the credit is actually settled — does it read as paid.
        $this->assertSame(Order::$payment_status['paid'], $order->fresh()->payment_status);
    }

    /** @test */
    public function a_partial_payment_settles_part_and_leaves_the_order_on_credit(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeCreditOrder($customer, $admin);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.customers.credit.mark-paid', encrypt($customer->id)), [
                'order_ids' => [$order->id],
                'payments' => [
                    $order->id => ['amount' => 10.00, 'payment_method' => 'cash'],
                ],
            ])
            ->assertRedirect();

        // 10 of 30 cleared — balance and outstanding both reflect the remainder.
        $this->assertEqualsWithDelta(-20.00, (float) $customer->fresh()->credit_balance, 0.001);
        $this->assertEqualsWithDelta(20.00, $order->fresh()->creditOutstandingAmount(), 0.001);
        $this->assertSame(Order::$status['delivered'], $order->fresh()->status);
        // Part-settled credit orders read as partially paid, never fully paid.
        $this->assertSame(Order::$payment_status['partial'], $order->fresh()->payment_status);
    }

    /** @test */
    public function an_amount_above_the_outstanding_is_clamped_to_what_is_owed(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeCreditOrder($customer, $admin);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.customers.credit.mark-paid', encrypt($customer->id)), [
                'order_ids' => [$order->id],
                'payments' => [
                    $order->id => ['amount' => 999.00, 'payment_method' => 'bank-transfer'],
                ],
            ])
            ->assertRedirect();

        $log = CustomerCreditLog::where('order_id', $order->id)
            ->where('type', 'credit_settlement')
            ->first();

        $this->assertEqualsWithDelta(30.00, (float) $log->amount, 0.001);
        $this->assertEqualsWithDelta(0.00, (float) $customer->fresh()->credit_balance, 0.001);
    }

    /** @test */
    public function a_ticked_order_missing_its_payment_method_fails_validation(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeCreditOrder($customer, $admin);

        $this->actingAs($admin, 'web_admin')
            ->post(route('admin.customers.credit.mark-paid', encrypt($customer->id)), [
                'order_ids' => [$order->id],
                'payments' => [
                    $order->id => ['amount' => 30.00],
                ],
            ])
            ->assertSessionHasErrors("payments.{$order->id}.payment_method");

        // Nothing settled: still owing, still delivered.
        $this->assertSame(
            0,
            CustomerCreditLog::where('order_id', $order->id)->where('type', 'credit_settlement')->count()
        );
        $this->assertEqualsWithDelta(-30.00, (float) $customer->fresh()->credit_balance, 0.001);
        $this->assertSame(Order::$status['delivered'], $order->fresh()->status);
    }

    /** @test */
    public function the_credit_list_shows_the_invoice_number_instead_of_the_order_id(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeCreditOrder($customer, $admin);
        $order->update(['invoice_number' => 'INV-2026-0042']);

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.customers.edit', encrypt($customer->id)))
            ->assertOk()
            ->assertSee('INV-2026-0042')
            // Still links to the order summary.
            ->assertSee(route('admin.orders.summary', $order->id));
    }
}
