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
    public function sales_summary_totals_orders_quantity_and_amount(): void
    {
        $customer = $this->makeCustomer();

        // Two orders, each with one line: qty 1, price 45.00.
        $this->makeOrder($customer, 'cod');
        $this->makeOrder($customer, 'cod');

        $summary = app(DailySalesReportService::class)
            ->salesSummary(Request::create('/', 'GET', []));

        $this->assertSame(2, $summary['total_orders']);
        $this->assertSame(2.0, $summary['total_quantity']);
        $this->assertSame(90.0, $summary['total_sales']);
    }

    /** @test */
    public function sales_summary_total_sales_matches_the_dashboard_definition(): void
    {
        $customer = $this->makeCustomer();

        // A completed order counts; a cancelled one is excluded (dashboard parity).
        $this->makeOrder($customer, 'cod');
        $cancelled = $this->makeOrder($customer, 'cod');
        $cancelled->update(['status' => Order::$status['cancelled']]);

        $summary = app(DailySalesReportService::class)
            ->salesSummary(Request::create('/', 'GET', []));

        $this->assertSame(1, $summary['total_orders']);
        // total_price of the single non-cancelled order.
        $this->assertSame(45.0, $summary['total_sales']);
    }

    /** @test */
    public function sales_summary_respects_the_payment_method_filter(): void
    {
        $customer = $this->makeCustomer();

        $cashPaid = $this->makeOrder($customer, 'cod');
        $this->recordPayment($cashPaid, 'cash');

        $qrPaid = $this->makeOrder($customer, 'cod');
        $this->recordPayment($qrPaid, 'qr');

        $summary = app(DailySalesReportService::class)
            ->salesSummary(Request::create('/', 'GET', ['payment_method' => 'cash']));

        // Only the cash-paid order is counted.
        $this->assertSame(1, $summary['total_orders']);
        $this->assertSame(45.0, $summary['total_sales']);
    }

    /** @test */
    public function payment_collection_summary_buckets_confirmed_payments_by_category(): void
    {
        $customer = $this->makeCustomer();

        $cash = $this->makeOrder($customer, 'cod');
        $this->recordPayment($cash, 'cash');                 // -> cash

        $qr = $this->makeOrder($customer, 'cod');
        $this->recordPayment($qr, 'qr');                     // -> qr

        $transfer = $this->makeOrder($customer, 'cod');
        $this->recordPayment($transfer, 'bank-transfer');    // -> transfer

        // Pending payments are not collections and must be ignored.
        $pending = $this->makeOrder($customer, 'cod');
        $this->recordPayment($pending, 'cash', OrderPayment::STATUS_PENDING);

        $summary = app(DailySalesReportService::class)
            ->paymentCollectionSummary(Request::create('/', 'GET', []));

        $this->assertSame(45.0, $summary['cash']['total']);
        $this->assertSame(1, $summary['cash']['count']);
        $this->assertSame(45.0, $summary['qr']['total']);
        $this->assertSame(45.0, $summary['transfer']['total']);
    }

    /** @test */
    public function payment_collection_grand_total_excludes_credit_term(): void
    {
        $customer = $this->makeCustomer();

        $cash = $this->makeOrder($customer, 'cod');
        $this->recordPayment($cash, 'cash');                 // real money: 45.00

        // A credit-term "buy now, pay later" charge is booked as a confirmed
        // payment, but it is an IOU — not money collected — so it must stay out
        // of the "Total Collected" grand total (while still showing as a row).
        $credit = $this->makeOrder($customer, 'term');
        $this->recordPayment($credit, 'credit-term');        // receivable: 45.00

        $summary = app(DailySalesReportService::class)
            ->paymentCollectionSummary(Request::create('/', 'GET', []));

        // The credit-term row is still reported for reference.
        $this->assertSame(45.0, $summary['credit-term']['total']);
        $this->assertSame(1, $summary['credit-term']['count']);

        // ...but the grand total reflects only real money collected.
        $this->assertSame(45.0, $summary['grand_total']['total']);
        $this->assertSame(1, $summary['grand_total']['count']);
    }

    /** @test */
    public function payment_collection_summary_excludes_cancelled_orders_like_sales(): void
    {
        $customer = $this->makeCustomer();

        $live = $this->makeOrder($customer, 'cod');
        $this->recordPayment($live, 'cash');                 // counts: 45.00

        // A payment on a cancelled order must not inflate collections — the
        // sales side already drops cancelled orders, so collections follow suit.
        $cancelled = $this->makeOrder($customer, 'cod');
        $this->recordPayment($cancelled, 'cash');
        $cancelled->update(['status' => Order::$status['cancelled']]);

        $summary = app(DailySalesReportService::class)
            ->paymentCollectionSummary(Request::create('/', 'GET', []));

        $this->assertSame(45.0, $summary['cash']['total']);
        $this->assertSame(1, $summary['cash']['count']);
        $this->assertSame(45.0, $summary['grand_total']['total']);
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
