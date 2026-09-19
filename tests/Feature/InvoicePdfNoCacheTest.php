<?php

namespace Tests\Feature;

use App\Admin;
use App\Order;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The customer "View Invoice" button links to a static-looking URL that ends in
 * invoice-{id}.pdf. FileController regenerates the file fresh on every request,
 * but if the response is cacheable the browser keeps serving the stale copy by
 * its unchanging URL — so the PDF shows old values while the dynamic HTML
 * summary (never cached) is correct. The served document must be non-cacheable.
 */
class InvoicePdfNoCacheTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): User
    {
        return User::forceCreate([
            'name' => 'NoCache Tester',
            'email' => 'nocache' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'normal',
            'customer_type' => 'normal',
            'invoice_visibility' => true,
            'credit_balance' => 0,
            'status' => 'active',
            'registration_completed_at' => now(),
            'payment_method' => 'cash',
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
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
            'status' => 'pending',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);
    }

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

    /** @test */
    public function served_invoice_pdf_is_not_cacheable_by_the_browser(): void
    {
        Storage::fake('local');

        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);

        $url = '/' . Order::$path . '/' . $order->id . '/invoice-' . $order->id . '.pdf';

        $response = $this->actingAs($customer, 'web')->get($url);

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        // Without an explicit no-store/no-cache directive the browser reuses the
        // cached PDF (same URL every time) and shows stale prices/totals.
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
    }

    /** @test */
    public function admin_streamed_invoice_pdf_is_not_cacheable_by_the_browser(): void
    {
        Storage::fake('local');

        $order = $this->makeOrder($this->makeCustomer());

        $response = $this->actingAs($this->makeAdmin(), 'web_admin')
            ->get(route('admin.order.invoice', $order->id));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');

        // An admin who edits an order and re-opens the invoice at the same route
        // must never be shown the previously cached PDF.
        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $cacheControl);
        $this->assertStringContainsString('no-cache', $cacheControl);
    }
}
