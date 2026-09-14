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
        $this->assertStringContainsString('货币', $html);
        $this->assertStringContainsString('MYR', $html);
        $this->assertStringContainsString('客户代码', $html);
        $this->assertStringContainsString('3000-T527', $html);

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
