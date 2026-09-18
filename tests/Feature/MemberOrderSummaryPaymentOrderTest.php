<?php

namespace Tests\Feature;

use App\Order;
use App\OrderPayment;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MemberOrderSummaryPaymentOrderTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): User
    {
        return User::forceCreate([
            'name' => 'Member Tester',
            'email' => 'member' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'credit',
            'customer_type' => 'credit',
            'invoice_visibility' => true,
            'credit_balance' => 0,
            'status' => 'active',
            'registration_completed_at' => now(),
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

    private function makeOrder(User $customer): Order
    {
        return Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 50.00,
            'subtotal' => 50.00,
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
        ]);
    }

    /**
     * The member payment history must be ordered newest-first by id, matching the
     * admin order summary — not by created_at, which can tie or run out of step
     * with insertion order.
     *
     * @test
     */
    public function member_payment_history_is_ordered_by_id_desc_like_admin(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);

        // Lower id but LATER created_at.
        $first = OrderPayment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => 'bank-transfer',
            'amount' => 20.00,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);
        $first->forceFill(['created_at' => '2026-09-10 10:00:00'])->save();

        // Higher id but EARLIER created_at.
        $second = OrderPayment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => 'e-wallet',
            'amount' => 30.00,
            'status' => OrderPayment::STATUS_CONFIRMED,
        ]);
        $second->forceFill(['created_at' => '2026-09-01 10:00:00'])->save();

        $response = $this->actingAs($customer, 'web')
            ->get('/order/summary/' . Crypt::encrypt($order->id));

        $response->assertOk();

        $ids = collect($response->viewData('payments'))->pluck('id')->all();

        // id desc => [second, first]; created_at desc would wrongly give [first, second].
        $this->assertSame([$second->id, $first->id], $ids);
    }
}
