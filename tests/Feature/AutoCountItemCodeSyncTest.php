<?php

namespace Tests\Feature;

use App\Order;
use App\OrderProduct;
use App\Product;
use App\Services\AutoCountApiService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AutoCountItemCodeSyncTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): User
    {
        return User::forceCreate([
            'name' => 'Acme Seafood',
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'cod',
            'customer_type' => 'cod',
            'status' => 'active',
            'payment_method' => 'cash',
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    private function makeProduct(string $sku): Product
    {
        return Product::forceCreate([
            'name' => 'Prawn ' . rand(1000, 9999),
            'sku' => $sku,
            'price' => 10.00,
            'status' => Product::$status['active'],
            'sell_in' => Product::SELL_IN_QTY,
        ]);
    }

    private function makeEligibleOrder(User $customer): Order
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
            'payment_method' => 'cash',
            'payment_status' => Order::$payment_status['paid'],
            'autocount_sync_status' => 'pending_sync',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);
    }

    private function addLine(Order $order, Product $product, string $name, float $qty): void
    {
        OrderProduct::forceCreate([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $name,
            'quantity' => $qty,
            'unit_price' => $product->price,
            'price' => $product->price * $qty,
            'status' => OrderProduct::$status['active'],
        ]);
    }

    /** @test */
    public function every_line_syncs_without_an_item_code_but_keeps_its_description_and_value(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeEligibleOrder($customer);
        $product = $this->makeProduct('M0009');
        $this->addLine($order, $product, 'Non-stock Item', 2);

        $payload = app(AutoCountApiService::class)->nextPendingOrder();
        $line = collect($payload['detail'])->firstWhere('Description', 'Non-stock Item');

        // No item code is sent, so AutoCount does not abort on a missing code.
        $this->assertSame('', $line['Item']);

        // The name and value must still post so the invoice total stays correct.
        $this->assertSame('Non-stock Item', $line['Description']);
        $this->assertSame('10.00', $line['UnitPrice']);
        $this->assertEquals(20.0, $line['SubTotal']);
    }

    /** @test */
    public function every_line_is_stamped_with_the_configured_location(): void
    {
        config()->set('autocount.default_location', 'Penang');

        $customer = $this->makeCustomer();
        $order = $this->makeEligibleOrder($customer);
        $product = $this->makeProduct('M0010');
        $this->addLine($order, $product, 'Located Item', 3);

        $payload = app(AutoCountApiService::class)->nextPendingOrder();
        $line = collect($payload['detail'])->firstWhere('Description', 'Located Item');

        // AutoCount rejects the DO when a line has no location, so it must be set.
        $this->assertSame('Penang', $line['Location']);
        $this->assertNotSame('', $line['Location']);
    }
}
