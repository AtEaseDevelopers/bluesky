<?php

namespace Tests\Feature;

use App\Admin;
use App\BulkPayment;
use App\CustomerCreditLog;
use App\Order;
use App\OrderPayment;
use App\Services\OrderService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MemberBulkPaymentPageTest extends TestCase
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
            'payment_method' => 'credit-term',
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ], $attrs));
    }

    /** Charge the order to the customer's credit-term account (the real flow). */
    private function chargeToCredit(Order $order, Admin $admin): Order
    {
        app(OrderService::class)->recordPayment(
            $order->fresh(),
            'credit-term',
            (float) $order->total_price,
            null,
            null,
            $admin->id
        );

        return $order->fresh();
    }

    /** @test */
    public function bulk_payment_page_lists_credit_term_orders_with_outstanding_credit(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer);
        $this->chargeToCredit($order, $admin);

        // A credit-term order reads balanceDue()==0 but still owes on the ledger.
        $this->assertEqualsWithDelta(0.0, $order->fresh()->balanceDue(), 0.001);
        $this->assertEqualsWithDelta(30.0, $order->fresh()->creditOutstandingAmount(), 0.001);

        $response = $this->actingAs($customer, 'web')->get('/bulk-payments');

        $response->assertStatus(200);
        $response->assertSee('#' . $order->id);
        $response->assertSee('Submit Bulk Payment');

        // Credit-term is the owing side, not a way to settle — it must not be an
        // option in the bulk payment method dropdown.
        $response->assertSee('Bank Transfer');
        $response->assertDontSee('>Credit Term<', false);
    }

    /** @test */
    public function invoice_number_is_a_link_that_opens_the_pdf_in_a_new_tab(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->makeOrder($customer, ['invoice_number' => 'INV-2001']);
        $this->chargeToCredit($order, $admin);

        $response = $this->actingAs($customer, 'web')->get('/bulk-payments');

        $response->assertStatus(200);
        $invoiceUrl = url('/') . '/' . Order::$path . '/' . $order->id . '/invoice-' . $order->id . '.pdf';
        $response->assertSee('href="' . $invoiceUrl . '"', false);
        $response->assertSee('target="_blank"', false);
        $response->assertSee('INV-2001');
    }

    /** @test */
    public function invoice_number_stays_plain_text_when_invoice_is_hidden_from_customer(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $customer->forceFill(['invoice_visibility' => false])->save();
        $order = $this->makeOrder($customer, ['invoice_number' => 'INV-2002']);
        $this->chargeToCredit($order, $admin);

        $response = $this->actingAs($customer, 'web')->get('/bulk-payments');

        $response->assertStatus(200);
        $response->assertSee('INV-2002');
        $invoiceUrl = url('/') . '/' . Order::$path . '/' . $order->id . '/invoice-' . $order->id . '.pdf';
        $response->assertDontSee('href="' . $invoiceUrl . '"', false);
    }

    /** @test */
    public function credit_term_is_rejected_as_a_bulk_payment_method(): void
    {
        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $order = $this->chargeToCredit($this->makeOrder($customer), $admin);

        $response = $this->actingAs($customer, 'web')->post('/bulk-payments', [
            'order_ids' => [$order->id],
            'payment_method' => 'credit-term',
            'amount' => 25.00,
            'payment_proof' => \Illuminate\Http\UploadedFile::fake()->image('proof.jpg'),
        ]);

        $response->assertSessionHasErrors('payment_method');
    }

    /** @test */
    public function submitting_a_bulk_payment_records_pending_payments_that_settle_credit_on_confirm(): void
    {
        Storage::fake('local');

        $admin = $this->makeAdmin();
        $customer = $this->makeCreditCustomer();
        $orderA = $this->chargeToCredit($this->makeOrder($customer, ['total_price' => 30, 'subtotal' => 30]), $admin);
        $orderB = $this->chargeToCredit($this->makeOrder($customer, ['total_price' => 20, 'subtotal' => 20]), $admin);

        $this->assertEqualsWithDelta(-50.0, (float) $customer->fresh()->credit_balance, 0.001);

        $response = $this->actingAs($customer, 'web')->post('/bulk-payments', [
            'order_ids' => [$orderA->id, $orderB->id],
            'payment_method' => 'bank-transfer',
            'amount' => 50.00,
            'payment_proof' => UploadedFile::fake()->image('proof.jpg'),
            'notes' => 'June settlement',
        ]);

        $response->assertRedirect(route('member.orders'));

        $bulk = BulkPayment::where('user_id', $customer->id)->first();
        $this->assertNotNull($bulk);
        $this->assertSame(BulkPayment::STATUS_PENDING, $bulk->status);

        // One pending payment per order, allocated against credit outstanding.
        $pending = OrderPayment::whereIn('order_id', [$orderA->id, $orderB->id])
            ->where('status', OrderPayment::STATUS_PENDING)
            ->get();
        $this->assertCount(2, $pending);

        // Admin confirms each pending payment -> settles the order's credit.
        foreach ($pending as $payment) {
            app(OrderService::class)->confirmPendingPayment($payment->fresh(), $admin->id);
        }

        $this->assertEqualsWithDelta(0.0, $orderA->fresh()->creditOutstandingAmount(), 0.001);
        $this->assertEqualsWithDelta(0.0, $orderB->fresh()->creditOutstandingAmount(), 0.001);
        $this->assertSame('paid', $orderA->fresh()->payment_status);
        $this->assertSame('paid', $orderB->fresh()->payment_status);

        // Customer no longer owes on the credit account.
        $this->assertEqualsWithDelta(0.0, (float) $customer->fresh()->credit_balance, 0.001);

        // The settlement is recorded on the credit ledger with the real amount
        // (not left as a confusing RM 0.00 confirmed order payment).
        $this->assertEqualsWithDelta(30.0, (float) CustomerCreditLog::where('order_id', $orderA->id)
            ->where('type', 'credit_settlement')->sum('amount'), 0.001);
        $this->assertEqualsWithDelta(20.0, (float) CustomerCreditLog::where('order_id', $orderB->id)
            ->where('type', 'credit_settlement')->sum('amount'), 0.001);

        // No zero-amount confirmed payment rows linger on the orders.
        $this->assertSame(0, OrderPayment::whereIn('order_id', [$orderA->id, $orderB->id])
            ->where('status', OrderPayment::STATUS_CONFIRMED)
            ->where('payment_method', '!=', 'credit-term')
            ->count());

        // paid_amount matches the order totals exactly (no double counting).
        $this->assertEqualsWithDelta(30.0, (float) $orderA->fresh()->paid_amount, 0.001);
        $this->assertEqualsWithDelta(20.0, (float) $orderB->fresh()->paid_amount, 0.001);
    }
}
