<?php

namespace Tests\Feature;

use App\Order;
use App\OrderProduct;
use App\PdfHelper;
use App\Product;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use ReflectionMethod;
use Tests\TestCase;

/**
 * Renders the redesigned INV / DO PDF blade templates through the real
 * PdfHelper data pipeline and asserts the new Chinese invoice format.
 */
class PdfDocumentFormatTest extends TestCase
{
    use RefreshDatabase;

    private function makeCustomer(): User
    {
        return User::forceCreate([
            'name' => 'Bluesky Live Seafood',
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'cod',
            'status' => 'active',
            'payment_method' => 'cash',
            'login_code' => 'code' . rand(1000, 9999),
            'sql_customer_code' => '3000-T527',
            'invoice_price_permission' => 1,
            'billing_address' => "LOT 1242 KAWASAN KILANG\nJALAN 11 KG BARU AMPANG\n68000 AMPANG",
            'billing_postcode' => '68000',
            'billing_state' => 'Selangor',
            'shipping_address' => "LOT 1242 KAWASAN KILANG\nJALAN 11 KG BARU AMPANG\n68000 AMPANG",
            'shipping_postcode' => '68000',
            'shipping_state' => 'Selangor',
        ]);
    }

    private function makeProduct(string $sku, string $name, float $price): Product
    {
        return Product::forceCreate([
            'name' => $name,
            'sku' => $sku,
            'price' => $price,
            'weight' => 0,
            'status' => Product::$status['active'],
            'images' => json_encode(['x.jpg']),
            'sell_in' => Product::SELL_IN_WEIGHT,
            'show_qty' => 1,
            'show_weight' => 1,
        ]);
    }

    private function makeOrder(User $customer): Order
    {
        return Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => 'registered',
            'total_price' => 1219.00,
            'subtotal' => 1219.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 23,
            'paid_amount' => 0,
            'status' => 'pending',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'invoice_number' => 'IV-2609-00735',
            'billing_address' => $customer->billing_address,
            'billing_postcode' => '68000',
            'billing_state' => 'Selangor',
            'shipping_address' => $customer->shipping_address,
        ]);
    }

    private function addLine(Order $order, Product $product, float $weight, float $price): OrderProduct
    {
        return OrderProduct::forceCreate([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => null,
            'weight' => $weight,
            'product_weight' => $weight,
            'unit_price' => $product->price,
            'price' => $price,
            'status' => OrderProduct::$status['active'],
        ]);
    }

    private function orderItems(Order $order)
    {
        $ref = new ReflectionMethod(PdfHelper::class, 'getProductsData');
        $ref->setAccessible(true);

        return $ref->invoke(null, 'order', $order->id, OrderProduct::class);
    }

    private function invoiceData(Order $order): array
    {
        $ref = new ReflectionMethod(PdfHelper::class, 'invoiceViewData');
        $ref->setAccessible(true);

        return $ref->invoke(null, $order, [
            'invoice_number' => $order->invoice_number,
            'date' => '11/09/2026',
            'time' => '18:35:59',
            'order' => $order,
            'order_items' => $this->orderItems($order),
            'void' => false,
            'user' => $order->pdfCustomer(),
            'type' => 'order',
            'payments' => collect(),
            'payment_method_labels' => [],
        ]);
    }

    private function seedOrder(): Order
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        $this->addLine($order, $this->makeProduct('SZZ029', 'CRAYFISH (M SIZE)', 53.00), 11, 583.00);
        $this->addLine($order, $this->makeProduct('SZZ030', 'CRAYFISH (L SIZE)', 53.00), 12, 636.00);

        return $order;
    }

    /** Walk-in order with a name but no registered customer and no attn_name. */
    private function seedWalkInOrder(): Order
    {
        $order = Order::forceCreate([
            'user_id' => null,
            'order_type' => Order::$order_types['walk_in'],
            'walk_in_name' => 'John Tan',
            'walk_in_phone' => '0123456789',
            'attn_name' => null,
            'attn_contact' => null,
            'total_price' => 583.00,
            'subtotal' => 583.00,
            'delivery_fee' => 0,
            'amount_adjustment' => 0,
            'order_weight' => 11,
            'paid_amount' => 0,
            'status' => 'pending',
            'fulfillment_type' => 'delivery',
            'payment_method' => 'cash',
            'payment_status' => 'unpaid',
            'invoice_number' => 'IV-2609-00736',
            'billing_address' => "12 JALAN WALK IN\n50000 KUALA LUMPUR",
            'billing_postcode' => '50000',
            'billing_state' => 'WP',
            'shipping_address' => "12 JALAN WALK IN\n50000 KUALA LUMPUR",
        ]);
        $this->addLine($order, $this->makeProduct('SZZ029', 'CRAYFISH (M SIZE)', 53.00), 11, 583.00);

        return $order;
    }

    /** @test */
    public function invoice_renders_chinese_format_with_meta_addresses_and_product_codes(): void
    {
        $order = $this->seedOrder();
        $html = view('pdf.invoice', $this->invoiceData($order))->render();

        // Title + meta labels
        $this->assertStringContainsString('发票', $html);
        $this->assertStringContainsString('发票编号', $html);
        $this->assertStringContainsString('IV-2609-00735', $html);
        $this->assertStringContainsString('付款条件', $html);
        $this->assertStringContainsString('11/09/2026 18:35:59', $html);
        $this->assertStringNotContainsString('货币', $html);
        $this->assertStringNotContainsString('客户代码', $html);

        $this->assertStringContainsString('送货方式', $html);
        $this->assertStringContainsString($order->fulfillmentTypeLabel(), $html);

        // Address boxes
        $this->assertStringContainsString('账单地址', $html);
        $this->assertStringContainsString('送货地址', $html);

        // Item table headers + product codes (SKU)
        $this->assertStringContainsString('产品编号', $html);
        $this->assertStringContainsString('产品描述', $html);
        $this->assertStringContainsString('小计', $html);
        $this->assertStringContainsString('SZZ029', $html);
        $this->assertStringContainsString('SZZ030', $html);

        // Totals
        $this->assertStringContainsString('总金额', $html);
        $this->assertStringContainsString('1,219.00', $html);
    }

    /** @test */
    public function invoice_without_price_hides_price_columns_but_keeps_format(): void
    {
        $order = $this->seedOrder();
        $html = view('pdf.invoicewithoutprice', $this->invoiceData($order))->render();

        $this->assertStringContainsString('发票', $html);
        $this->assertStringContainsString('产品编号', $html);
        $this->assertStringContainsString('SZZ029', $html);
        // No price columns / amounts
        $this->assertStringNotContainsString('小计', $html);
        $this->assertStringNotContainsString('总金额', $html);
        $this->assertStringNotContainsString('583.00', $html);
        // Weight total still shown
        $this->assertStringContainsString('总重量', $html);
    }

    /** @test */
    public function delivery_order_renders_chinese_format(): void
    {
        $order = $this->seedOrder();
        $data = $this->invoiceData($order);
        $data['do_no'] = 'DO-2609-00735';
        $data['show_prices'] = true;

        $html = view('pdf.delivery-order', $data)->render();

        $this->assertStringContainsString('送货单', $html);
        $this->assertStringContainsString('送货单号', $html);
        $this->assertStringContainsString('DO-2609-00735', $html);
        $this->assertStringContainsString('产品编号', $html);
        $this->assertStringContainsString('SZZ029', $html);
        $this->assertStringContainsString('送货方式', $html);
        $this->assertStringContainsString($order->fulfillmentTypeLabel(), $html);
    }

    /** @test */
    public function delivery_order_shows_assigned_driver_name_for_delivery(): void
    {
        $order = $this->seedOrder();
        $driver = \App\Driver::forceCreate([
            'name' => 'Driver Ahmad',
            'username' => 'driverahmad',
            'password' => Hash::make('password'),
            'is_active' => true,
        ]);
        $order->update(['driver_id' => $driver->id]);
        $order->refresh();

        $data = $this->invoiceData($order);
        $data['do_no'] = 'DO-2609-00999';
        $data['show_prices'] = true;
        $data['fulfillment'] = $order->pdfFulfillmentDisplayLabel();

        $html = view('pdf.delivery-order', $data)->render();

        $this->assertStringContainsString('Driver Ahmad', $html);
    }

    /** @test */
    public function documents_show_lalamove_fulfillment_for_courier_orders(): void
    {
        $order = $this->seedOrder();
        $order->update(['fulfillment_type' => 'courier']);
        $order->refresh();

        $invoiceHtml = view('pdf.invoice', $this->invoiceData($order))->render();
        $this->assertStringContainsString('送货方式', $invoiceHtml);
        $this->assertStringContainsString('Lalamove', $invoiceHtml);

        $doData = $this->invoiceData($order);
        $doData['do_no'] = 'DO-2609-00737';
        $doData['show_prices'] = true;
        $doHtml = view('pdf.delivery-order', $doData)->render();
        $this->assertStringContainsString('送货方式', $doHtml);
        $this->assertStringContainsString('Lalamove', $doHtml);
    }

    /** @test */
    public function invoice_shows_walk_in_name_when_attn_name_is_empty(): void
    {
        $order = $this->seedWalkInOrder();
        $html = view('pdf.invoice', $this->invoiceData($order))->render();

        $this->assertStringContainsString('John Tan', $html);
    }

    /** @test */
    public function delivery_order_shows_walk_in_name_when_attn_name_is_empty(): void
    {
        $order = $this->seedWalkInOrder();
        $data = $this->invoiceData($order);
        $data['do_no'] = 'DO-2609-00736';
        $data['show_prices'] = true;

        $html = view('pdf.delivery-order', $data)->render();

        $this->assertStringContainsString('John Tan', $html);
    }

    /** Seed an order carrying an arbitrary number of distinct line items. */
    private function seedOrderWithLines(int $count): Order
    {
        $customer = $this->makeCustomer();
        $order = $this->makeOrder($customer);
        for ($i = 1; $i <= $count; $i++) {
            $sku = 'SKU' . str_pad((string) $i, 3, '0', STR_PAD_LEFT);
            $this->addLine($order, $this->makeProduct($sku, 'PRODUCT ' . $i, 10.00), 1, 10.00);
        }

        return $order;
    }

    /** @test */
    public function invoice_paginates_at_ten_items_per_page(): void
    {
        $order = $this->seedOrderWithLines(20);
        $html = view('pdf.invoice', $this->invoiceData($order))->render();

        // 20 items -> 2 pages (10 + 10) -> 1 page break between them.
        $this->assertSame(1, substr_count($html, 'page-break-after: always'));

        // Document header + item-table column header repeat once per page.
        $this->assertSame(2, substr_count($html, \App\PdfHelper::bilingual('pdf.meta.invoice_no')));
        $this->assertSame(2, substr_count($html, \App\PdfHelper::bilingual('pdf.items.sku')));
        $this->assertSame(2, substr_count($html, \App\PdfHelper::bilingual('pdf.addr.billing')));

        // Pure pagination: every line still rendered, numbered continuously.
        for ($i = 1; $i <= 20; $i++) {
            $this->assertStringContainsString('SKU' . str_pad((string) $i, 3, '0', STR_PAD_LEFT), $html);
        }
        $this->assertStringContainsString('>20<', $html); // last row number

        // Totals block lives on the last page only.
        $this->assertSame(1, substr_count($html, \App\PdfHelper::bilingual('pdf.totals.total_amount')));
    }

    /** @test */
    public function invoice_with_exactly_ten_items_stays_on_one_page(): void
    {
        $order = $this->seedOrderWithLines(10);
        $html = view('pdf.invoice', $this->invoiceData($order))->render();

        $this->assertSame(0, substr_count($html, 'page-break-after: always'));
        $this->assertSame(1, substr_count($html, \App\PdfHelper::bilingual('pdf.items.sku')));
    }

    /** @test */
    public function invoice_with_eleven_items_breaks_to_two_pages(): void
    {
        $order = $this->seedOrderWithLines(11);
        $html = view('pdf.invoice', $this->invoiceData($order))->render();

        $this->assertSame(1, substr_count($html, 'page-break-after: always'));
        $this->assertSame(2, substr_count($html, \App\PdfHelper::bilingual('pdf.items.sku')));
    }

    /** @test */
    public function delivery_order2_paginates_but_keeps_single_signature_block(): void
    {
        $order = $this->seedOrderWithLines(20);
        $data = $this->invoiceData($order);
        $data['do_no'] = 'DO-PAGINATE';
        $data['show_prices'] = true;

        $html = view('pdf.delivery-order2', $data)->render();

        $this->assertSame(1, substr_count($html, 'page-break-after: always'));
        // Signature acknowledgement renders once, on the final page.
        $this->assertSame(1, substr_count($html, \App\PdfHelper::bilingual('pdf.do2.sign_authorised')));
    }

    /** @test */
    public function delivery_order_repeats_header_and_addresses_on_continuation_pages(): void
    {
        $order = $this->seedOrderWithLines(11);
        $data = $this->invoiceData($order);
        $data['do_no'] = 'DO-MULTIPAGE';
        $data['show_prices'] = true;

        $html = view('pdf.delivery-order', $data)->render();

        $this->assertSame(1, substr_count($html, 'page-break-after: always'));
        $this->assertSame(2, substr_count($html, \App\PdfHelper::bilingual('pdf.doc.do_title')));
        $this->assertSame(2, substr_count($html, \App\PdfHelper::bilingual('pdf.addr.billing')));
        $this->assertSame(1, substr_count($html, \App\PdfHelper::bilingual('pdf.totals.total_amount')));
    }

    /** @test */
    public function delivery_order2_renders_chinese_format_with_signature_block(): void
    {
        $order = $this->seedOrder();
        $data = $this->invoiceData($order);
        $data['do_no'] = 'DO-2609-00735';
        $data['show_prices'] = true;

        $html = view('pdf.delivery-order2', $data)->render();

        $this->assertStringContainsString('送货单', $html);
        $this->assertStringContainsString('授权签名', $html);
        $this->assertStringContainsString('客户公司盖章及签名', $html);
        // Stale wrong-company boilerplate must be gone
        $this->assertStringNotContainsString('AYAM HEBAT', $html);
    }
}
