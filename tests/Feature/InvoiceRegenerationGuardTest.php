<?php

namespace Tests\Feature;

use App\Order;
use App\OrderPayment;
use App\Services\OrderService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class InvoiceRegenerationGuardTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): User
    {
        return User::forceCreate([
            'name' => 'Acme ' . rand(1000, 9999),
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'normal',
            'customer_type' => 'normal',
            'credit_balance' => 0,
            'status' => 'active',
            'payment_method' => 'cash',
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);
    }

    private function makeFulfilledPaidOrder(User $customer): Order
    {
        $order = Order::forceCreate([
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
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);

        OrderPayment::forceCreate([
            'order_id' => $order->id,
            'amount' => 30.00,
            'status' => OrderPayment::STATUS_CONFIRMED,
            'settles_credit' => false,
            'payment_method' => 'cash',
        ]);

        return $order->fresh();
    }

    private function invoicePath(Order $order): string
    {
        return Order::$path . '/' . $order->id . '/invoice-' . $order->id . '.pdf';
    }

    /** @test */
    public function it_seeds_the_invoice_pdf_when_the_file_is_missing(): void
    {
        Storage::fake('local');
        $order = $this->makeFulfilledPaidOrder($this->makeCustomer());

        $this->assertFalse(Storage::disk('local')->exists($this->invoicePath($order)));

        app(OrderService::class)->refreshPaymentStatus($order);

        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertTrue(Storage::disk('local')->exists($this->invoicePath($order)));
    }

    /** @test */
    public function it_does_not_re_render_an_existing_invoice_pdf(): void
    {
        Storage::fake('local');
        $order = $this->makeFulfilledPaidOrder($this->makeCustomer());

        // A previously-rendered invoice already on disk.
        Storage::disk('local')->put($this->invoicePath($order), 'ALREADY-RENDERED');

        app(OrderService::class)->refreshPaymentStatus($order);

        // Status is still reconciled, but the expensive re-render is skipped:
        // the existing file is left byte-for-byte untouched.
        $this->assertSame('paid', $order->fresh()->payment_status);
        $this->assertSame('ALREADY-RENDERED', Storage::disk('local')->get($this->invoicePath($order)));
    }
}
