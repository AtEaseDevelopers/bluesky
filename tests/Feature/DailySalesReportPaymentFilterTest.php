<?php

namespace Tests\Feature;

use App\Order;
use App\OrderPayment;
use App\OrderProduct;
use App\Product;
use App\Services\DailySalesReportService;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * The daily sales report is keyed on *recorded* payments (order_payments), the
 * same vocabulary as the filter dropdown and the Payment Collection Summary.
 * The Sales Detail "Payment Method" column shows the recorded tender (Cash / QR
 * / Transfer / Credit Term), NOT the order's checkout/billing method, and the
 * filter selects orders by their confirmed recorded payments.
 */
class DailySalesReportPaymentFilterTest extends TestCase
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
            'payment_method' => 'cod',
            'login_code' => 'code' . rand(1000, 9999),
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => '1 Market St',
            'shipping_postcode' => '50000',
            'shipping_state' => 'WP',
        ]);
    }

    private function makeProduct(): Product
    {
        return Product::forceCreate([
            'name' => 'Prawn ' . rand(1000, 9999),
            'sku' => 'SKU' . rand(1000, 9999),
            'price' => 45.00,
            'weight' => 0,
            'status' => Product::$status['active'],
            'images' => json_encode(['prawn.jpg']),
            'sell_in' => Product::SELL_IN_WEIGHT,
        ]);
    }

    private function makeOrder(User $customer, string $billingMethod): Order
    {
        $order = Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 45.00,
            'subtotal' => 45.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 0,
            'paid_amount' => 0,
            'status' => 'completed',
            'fulfillment_type' => 'delivery',
            'payment_method' => $billingMethod,
            'payment_status' => 'unpaid',
            'billing_address' => '1 Market St',
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
        ]);

        OrderProduct::forceCreate([
            'order_id' => $order->id,
            'product_id' => $this->makeProduct()->id,
            'product_name' => 'Prawn',
            'quantity' => 1,
            'weight' => 0,
            'product_weight' => 0,
            'unit_price' => 45.00,
            'price' => 45.00,
            'status' => OrderProduct::$status['active'],
        ]);

        return $order;
    }

    private function recordPayment(Order $order, string $method, string $status = OrderPayment::STATUS_CONFIRMED): void
    {
        OrderPayment::forceCreate([
            'order_id' => $order->id,
            'payment_method' => $method,
            'amount' => 45.00,
            'status' => $status,
        ]);
    }

    private function salesLines(?string $paymentMethod = null)
    {
        $params = $paymentMethod ? ['payment_method' => $paymentMethod] : [];

        return app(DailySalesReportService::class)
            ->salesLines(Request::create('/', 'GET', $params));
    }

    private function orderIds(?string $paymentMethod = null): array
    {
        return $this->salesLines($paymentMethod)->pluck('id')->unique()->values()->all();
    }

    /** @test */
    public function the_filter_matches_confirmed_recorded_payments(): void
    {
        $customer = $this->makeCustomer();

        // Billed on credit terms, but the money was collected as cash.
        $cashPaid = $this->makeOrder($customer, 'term');
        $this->recordPayment($cashPaid, 'cash');

        // Paid by QR.
        $qrPaid = $this->makeOrder($customer, 'cod');
        $this->recordPayment($qrPaid, 'qr');

        $cashIds = $this->orderIds('cash');
        $this->assertContains($cashPaid->id, $cashIds);
        $this->assertNotContains($qrPaid->id, $cashIds);

        $this->assertContains($qrPaid->id, $this->orderIds('qr'));
        // The credit-term billing method does NOT make it match "Credit Term".
        $this->assertNotContains($cashPaid->id, $this->orderIds('credit-term'));
    }

    /** @test */
    public function orders_without_a_confirmed_payment_are_excluded_by_the_filter(): void
    {
        $customer = $this->makeCustomer();

        $unpaid = $this->makeOrder($customer, 'cod');            // no payment recorded
        $pending = $this->makeOrder($customer, 'cod');
        $this->recordPayment($pending, 'cash', OrderPayment::STATUS_PENDING);

        $cashIds = $this->orderIds('cash');
        $this->assertNotContains($unpaid->id, $cashIds);
        $this->assertNotContains($pending->id, $cashIds);

        // Unfiltered, the order still appears — with a blank recorded method.
        $row = $this->salesLines()->firstWhere('id', $unpaid->id);
        $this->assertNotNull($row);
        $this->assertSame('', app(DailySalesReportService::class)->recordedPaymentLabel($row->recorded_payment_methods));
    }

    /** @test */
    public function the_detail_column_shows_the_recorded_payment_category(): void
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer, 'term');
        $this->recordPayment($order, 'bank-transfer');

        $service = app(DailySalesReportService::class);
        $row = $this->salesLines()->firstWhere('id', $order->id);

        // Recorded as bank-transfer, so the column reads the "Transfer" category.
        $this->assertSame(
            $service->summaryCategoryLabels()['transfer'],
            $service->recordedPaymentLabel($row->recorded_payment_methods)
        );
    }

    /** @test */
    public function recorded_payment_label_buckets_and_dedupes_methods(): void
    {
        $service = app(DailySalesReportService::class);
        $labels = $service->summaryCategoryLabels();

        $this->assertSame('', $service->recordedPaymentLabel(null));
        $this->assertSame($labels['cash'], $service->recordedPaymentLabel('cash'));
        $this->assertSame($labels['transfer'], $service->recordedPaymentLabel('bank-transfer,e-wallet'));
        $this->assertSame(
            $labels['cash'] . ', ' . $labels['qr'],
            $service->recordedPaymentLabel('cash,qr')
        );
    }
}
