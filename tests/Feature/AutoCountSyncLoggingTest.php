<?php

namespace Tests\Feature;

use App\Order;
use App\Services\AutoCountApiService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class AutoCountSyncLoggingTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(string $customerType = 'credit'): User
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

    private function makeOrder(User $customer, string $status = 'pending_sync'): Order
    {
        return Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 20.00,
            'subtotal' => 20.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 20.00,
            'status' => Order::$status['completed'],
            'fulfillment_type' => 'delivery',
            'payment_method' => 'credit-term',
            'payment_status' => Order::$payment_status['paid'],
            'autocount_sync_status' => $status,
            'invoice_number' => 'INV-0001',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);
    }

    /** @test */
    public function handing_out_a_pending_order_writes_a_trace_log(): void
    {
        $order = $this->makeOrder($this->makeCustomer());

        Log::shouldReceive('channel')->with('autocount')->andReturnSelf();
        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        app(AutoCountApiService::class)->nextPendingOrder();
    }

    /** @test */
    public function a_document_write_back_writes_a_trace_log(): void
    {
        $order = $this->makeOrder($this->makeCustomer(), 'do_created');
        $order->update(['api_do_id' => 'DO-0001']);

        Log::shouldReceive('channel')->with('autocount')->andReturnSelf();
        Log::shouldReceive('info')->atLeast()->once();
        Log::shouldReceive('warning')->zeroOrMoreTimes();

        app(AutoCountApiService::class)->applyDocumentUpdate([
            'id' => $order->id,
            'type' => 'INV',
            'number' => 'INV-0001',
        ]);
    }

    /** @test */
    public function a_plugin_error_writes_an_error_trace_log(): void
    {
        $order = $this->makeOrder($this->makeCustomer());

        Log::shouldReceive('channel')->with('autocount')->andReturnSelf();
        Log::shouldReceive('error')->atLeast()->once();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        app(AutoCountApiService::class)->logError([
            'message' => 'DO not found in AutoCount, save aborted.',
            'model' => ['order' => ['id' => $order->id]],
        ]);
    }

    /** @test */
    public function a_write_back_for_a_missing_order_is_logged_before_failing(): void
    {
        Log::shouldReceive('channel')->with('autocount')->andReturnSelf();
        Log::shouldReceive('warning')->atLeast()->once();
        Log::shouldReceive('info')->zeroOrMoreTimes();

        $this->expectException(\InvalidArgumentException::class);

        app(AutoCountApiService::class)->applyDocumentUpdate([
            'id' => 999999,
            'type' => 'INV',
            'number' => 'INV-9999',
        ]);
    }
}
