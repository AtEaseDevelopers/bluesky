<?php

namespace Tests\Feature;

use App\Order;
use App\Services\AutoCountSyncService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Sync eligibility gate:
 *   - Credit orders push to AutoCount once DELIVERED (the invoice is carried on
 *     the credit account and settled later).
 *   - Cash / COD orders push only once COMPLETED (which enforces fully-paid).
 */
class AutoCountSyncEligibilityTest extends TestCase
{
    use RefreshDatabase;

    private function service(): AutoCountSyncService
    {
        return app(AutoCountSyncService::class);
    }

    private function makeCustomer(string $customerType = 'cod'): User
    {
        return User::forceCreate([
            'name' => 'Acme Seafood',
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => $customerType,
            'customer_type' => $customerType,
            'status' => 'active',
            'payment_method' => $customerType === 'credit' ? 'term' : 'cash',
            'login_code' => 'code' . rand(1000, 9999),
            'sql_customer_code' => '300-' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    private function makeOrder(User $customer, array $attributes = []): Order
    {
        return Order::forceCreate(array_merge([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 20.00,
            'subtotal' => 20.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 20.00,
            'status' => Order::$status['delivered'],
            'fulfillment_type' => 'delivery',
            'payment_method' => $customer->customer_type === 'credit' ? 'credit-term' : 'cash',
            'payment_status' => Order::$payment_status['paid'],
            'autocount_sync_status' => 'pending',
            'invoice_number' => 'INV-' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $attributes));
    }

    /** @test */
    public function credit_order_can_sync_once_delivered(): void
    {
        $order = $this->makeOrder($this->makeCustomer('credit'), [
            'status' => Order::$status['delivered'],
        ]);

        $this->assertTrue($order->canSyncToAutoCount());

        $this->service()->syncIfEligible($order);

        $this->assertSame('pending_sync', $order->fresh()->autocount_sync_status);
    }

    /** @test */
    public function credit_order_syncs_at_delivered_even_when_balance_unpaid(): void
    {
        $order = $this->makeOrder($this->makeCustomer('credit'), [
            'status' => Order::$status['delivered'],
            'payment_status' => Order::$payment_status['payment_due'],
        ]);

        $this->assertTrue($order->canSyncToAutoCount());

        $this->service()->syncIfEligible($order);

        $this->assertSame('pending_sync', $order->fresh()->autocount_sync_status);
    }

    /** @test */
    public function credit_order_not_yet_delivered_is_skipped(): void
    {
        $order = $this->makeOrder($this->makeCustomer('credit'), [
            'status' => Order::$status['in_route'],
        ]);

        $this->assertFalse($order->canSyncToAutoCount());

        $this->service()->syncIfEligible($order);

        $this->assertSame('skipped', $order->fresh()->autocount_sync_status);
    }

    /** @test */
    public function cash_order_at_delivered_is_not_yet_eligible(): void
    {
        $order = $this->makeOrder($this->makeCustomer('cod'), [
            'status' => Order::$status['delivered'],
        ]);

        $this->assertFalse($order->canSyncToAutoCount());

        $this->service()->syncIfEligible($order);

        $this->assertSame('skipped', $order->fresh()->autocount_sync_status);
    }

    /** @test */
    public function cash_order_can_sync_once_completed_and_paid(): void
    {
        $order = $this->makeOrder($this->makeCustomer('cod'), [
            'status' => Order::$status['completed'],
            'payment_status' => Order::$payment_status['paid'],
        ]);

        $this->assertTrue($order->canSyncToAutoCount());

        $this->service()->syncIfEligible($order);

        $this->assertSame('pending_sync', $order->fresh()->autocount_sync_status);
    }

    /** @test */
    public function cash_order_completed_but_unpaid_is_skipped(): void
    {
        $order = $this->makeOrder($this->makeCustomer('cod'), [
            'status' => Order::$status['completed'],
            'payment_status' => Order::$payment_status['unpaid'],
        ]);

        $this->assertFalse($order->canSyncToAutoCount());

        $this->service()->syncIfEligible($order);

        $this->assertSame('skipped', $order->fresh()->autocount_sync_status);
    }
}
