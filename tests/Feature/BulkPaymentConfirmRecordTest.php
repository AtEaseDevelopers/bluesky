<?php

namespace Tests\Feature;

use App\Admin;
use App\CustomerCreditLog;
use App\Order;
use App\OrderPayment;
use App\Services\BulkPaymentService;
use App\Services\CreditService;
use App\Services\OrderService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class BulkPaymentConfirmRecordTest extends TestCase
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
            'total_price' => 99.00,
            'subtotal' => 99.00,
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

    /** Put a credit-term charge on the order so it carries an outstanding ledger balance. */
    private function chargeOnCredit(Order $order, Admin $admin, float $amount): void
    {
        app(OrderService::class)->recordPayment($order->fresh(), 'credit-term', $amount, null, null, $admin->id);
    }

    /** @test */
    public function confirming_a_bulk_payment_keeps_a_visible_settlement_record(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer);
        $this->chargeOnCredit($order, $admin, 99.00);

        // Sanity: order owes 99 on credit, nothing settled yet.
        $this->assertEqualsWithDelta(99.00, $order->fresh()->outstandingForCustomer(), 0.001);
        $this->assertEqualsWithDelta(-99.00, (float) $customer->fresh()->credit_balance, 0.001);

        $bulk = app(BulkPaymentService::class)->submit(
            $customer->fresh(),
            [$order->id],
            'e-wallet',
            99.00,
            UploadedFile::fake()->image('proof.jpg'),
            null
        );

        app(BulkPaymentService::class)->confirm($bulk->fresh(), $admin->id);

        // The confirmed bulk payment must leave a visible settlement ledger row.
        $settlement = CustomerCreditLog::where('user_id', $customer->id)
            ->where('type', 'credit_settlement')
            ->where('order_id', $order->id)
            ->first();

        $this->assertNotNull($settlement, 'A credit_settlement ledger row must exist after confirming the bulk payment.');
        $this->assertEqualsWithDelta(99.00, (float) $settlement->amount, 0.001);
        $this->assertStringContainsString('Bulk payment', (string) $settlement->notes);

        // Balance is cleared and the order reads as settled.
        $this->assertEqualsWithDelta(0.00, (float) $customer->fresh()->credit_balance, 0.001);
        $this->assertEqualsWithDelta(0.00, $order->fresh()->creditOutstandingAmount(), 0.001);
    }

    /** @test */
    public function reassigning_after_a_bulk_payment_does_not_lose_the_settlement_record(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $oldCustomer = $this->makeCreditCustomer();
        $newCustomer = $this->makeCreditCustomer();
        $order = $this->makeOrder($oldCustomer);
        $this->chargeOnCredit($order, $admin, 99.00);

        $bulk = app(BulkPaymentService::class)->submit(
            $oldCustomer->fresh(),
            [$order->id],
            'e-wallet',
            99.00,
            UploadedFile::fake()->image('proof.jpg'),
            null
        );
        app(BulkPaymentService::class)->confirm($bulk->fresh(), $admin->id);

        // Settlement exists on the old customer at this point.
        $this->assertNotNull(
            CustomerCreditLog::where('user_id', $oldCustomer->id)->where('type', 'credit_settlement')->first()
        );

        // Reassign the order to another credit customer.
        $order->update(['user_id' => $newCustomer->id]);
        app(CreditService::class)->reassignOrderCredit($order->fresh(), $oldCustomer->fresh(), $newCustomer->fresh(), $admin->id);

        // The real money the customer paid must remain accounted for somewhere —
        // it must not silently vanish from every customer's ledger.
        $settlementForOrder = CustomerCreditLog::where('order_id', $order->id)
            ->where('type', 'credit_settlement')
            ->get();

        $this->assertTrue(
            $settlementForOrder->isNotEmpty(),
            'The settlement record for the paid order must still be retrievable after reassignment.'
        );

        // Whoever now owns the ledger row, its balance math must stay consistent
        // (no negative available credit conjured, no payment double-lost).
        $owner = User::find($settlementForOrder->first()->user_id);
        $this->assertNotNull($owner, 'The settlement row must belong to an existing customer.');
    }

    /**
     * Confirming a bulk payment is an admin approval of the customer's payment,
     * so the per-order payment record (with its proof) must stay visible on the
     * order — as a confirmed row — rather than being deleted. It is flagged as a
     * credit settlement so it is not double-counted into the order's paid amount.
     *
     * @test
     */
    public function bulk_payment_confirm_keeps_an_approved_payment_row_on_the_order(): void
    {
        Storage::fake('local');
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer);
        $this->chargeOnCredit($order, $admin, 99.00);

        $bulk = app(BulkPaymentService::class)->submit(
            $customer->fresh(), [$order->id], 'e-wallet', 99.00,
            UploadedFile::fake()->image('proof.jpg'), null
        );
        $pendingId = OrderPayment::where('bulk_payment_id', $bulk->id)->first()->id;

        app(BulkPaymentService::class)->confirm($bulk->fresh(), $admin->id);

        // The approved payment record is retained on the order, marked confirmed,
        // with its proof and bulk-payment note intact.
        $kept = OrderPayment::find($pendingId);
        $this->assertNotNull($kept, 'The confirmed bulk payment must remain on the order.');
        $this->assertSame(OrderPayment::STATUS_CONFIRMED, $kept->status);
        $this->assertTrue((bool) $kept->settles_credit, 'The retained row must be flagged as a credit settlement.');
        $this->assertNotNull($kept->payment_proof);
        $this->assertStringContainsString('Bulk payment', (string) $kept->notes);

        // It still posts the ledger settlement...
        $this->assertSame(1, CustomerCreditLog::where('order_id', $order->id)->where('type', 'credit_settlement')->count());

        // ...but must NOT double-count into the order's paid amount (order is
        // RM 99, already covered by the credit-term charge).
        $fresh = $order->fresh();
        $this->assertEqualsWithDelta(99.00, (float) $fresh->paid_amount, 0.001);
        $this->assertEqualsWithDelta(0.00, $fresh->balanceDue(), 0.001);
        $this->assertEqualsWithDelta(0.00, $fresh->creditOutstandingAmount(), 0.001);
        $this->assertSame(Order::$payment_status['paid'], $fresh->payment_status);
    }
}
