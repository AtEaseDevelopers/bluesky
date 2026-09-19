<?php

namespace Tests\Feature;

use App\Order;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The delivery-order stage is gone, so the invoice is a document customers may
 * see the moment the order exists — no longer gated behind full payment. It is
 * assigned a real INV-YYYYMM-##### number at creation and the customer-facing
 * gate only respects the per-customer invoice_visibility toggle.
 */
class InvoiceAlwaysVisibleTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(bool $invoiceVisible = true): User
    {
        return User::forceCreate([
            'name' => 'Invoice Tester',
            'email' => 'inv' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'credit',
            'customer_type' => 'credit',
            'invoice_visibility' => $invoiceVisible,
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

    private function makeUnpaidOrder(User $customer): Order
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
            'status' => 'pending',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'credit-term',
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);
    }

    /** @test */
    public function order_receives_a_real_invoice_number_the_moment_it_is_created(): void
    {
        $order = $this->makeUnpaidOrder($this->makeCustomer());

        // Assigned in-memory on the instance returned from create()...
        $this->assertMatchesRegularExpression('/^INV-\d{6}-\d+$/', (string) $order->invoice_number);
        // ...and persisted to the row.
        $this->assertMatchesRegularExpression('/^INV-\d{6}-\d+$/', (string) $order->fresh()->invoice_number);
    }

    /** @test */
    public function unpaid_order_shows_invoice_to_a_visible_customer(): void
    {
        $customer = $this->makeCustomer(true);
        $order = $this->makeUnpaidOrder($customer);

        $this->assertFalse($order->isFullyPaid());
        $this->assertTrue($order->canShowInvoiceToCustomer($customer));
        $this->assertTrue($order->canShowInvoice());
    }

    /** @test */
    public function invoice_visibility_toggle_still_hides_the_invoice(): void
    {
        $customer = $this->makeCustomer(false);
        $order = $this->makeUnpaidOrder($customer);

        $this->assertFalse($order->canShowInvoiceToCustomer($customer));
    }

    /** @test */
    public function order_summary_page_links_the_invoice_for_an_unpaid_order(): void
    {
        $customer = $this->makeCustomer(true);
        $order = $this->makeUnpaidOrder($customer);

        $response = $this->actingAs($customer, 'web')
            ->get('/order/summary/' . Crypt::encrypt($order->id));

        $response->assertOk();
        $response->assertSee('view-pdf', false);
        // The disabled "available after fully paid" placeholder must be gone.
        $response->assertDontSee('Available after the order is fully paid');
    }
}
