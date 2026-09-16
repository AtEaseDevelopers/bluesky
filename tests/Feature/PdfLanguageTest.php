<?php

namespace Tests\Feature;

use App\Admin;
use App\Order;
use App\OrderProduct;
use App\PdfHelper;
use App\Product;
use App\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use ReflectionMethod;
use Tests\TestCase;

/**
 * The INV and DO PDFs ship in two versions — English and Chinese. Every
 * generation call writes BOTH language files to storage; the templates
 * translate only static labels while dynamic order data stays untouched.
 */
class PdfLanguageTest extends TestCase
{
    use RefreshDatabase;

    private function seedOrder(): Order
    {
        $customer = User::forceCreate([
            'name' => 'Bluesky Live Seafood',
            'email' => 'cust' . rand(1000, 9999) . '@example.com',
            'password' => Hash::make('password'),
            'category' => 'cod',
            'status' => 'active',
            'payment_method' => 'cash',
            'login_code' => 'code' . rand(1000, 9999),
            'sql_customer_code' => '3000-T527',
            'invoice_price_permission' => 1,
            'invoice_visibility' => 1,
            'billing_address' => "LOT 1242 KAWASAN KILANG\n68000 AMPANG",
            'shipping_address' => "LOT 1242 KAWASAN KILANG\n68000 AMPANG",
        ]);

        $order = Order::forceCreate([
            'user_id' => $customer->id,
            'order_type' => 'registered',
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
            'invoice_number' => 'IV-2609-00735',
            'billing_address' => $customer->billing_address,
            'shipping_address' => $customer->shipping_address,
        ]);

        $product = Product::forceCreate([
            'name' => 'CRAYFISH (M SIZE)',
            'sku' => 'SZZ029',
            'price' => 53.00,
            'weight' => 0,
            'status' => Product::$status['active'],
            'images' => json_encode(['x.jpg']),
            'sell_in' => Product::SELL_IN_WEIGHT,
            'show_qty' => 1,
            'show_weight' => 1,
        ]);

        OrderProduct::forceCreate([
            'order_id' => $order->id,
            'product_id' => $product->id,
            'product_name' => $product->name,
            'quantity' => null,
            'weight' => 11,
            'product_weight' => 11,
            'unit_price' => 53.00,
            'price' => 583.00,
            'status' => OrderProduct::$status['active'],
        ]);

        return $order;
    }

    private function invoiceData(Order $order): array
    {
        $products = new ReflectionMethod(PdfHelper::class, 'getProductsData');
        $products->setAccessible(true);

        $view = new ReflectionMethod(PdfHelper::class, 'invoiceViewData');
        $view->setAccessible(true);

        return $view->invoke(null, $order, [
            'invoice_number' => $order->invoice_number,
            'date' => '11/09/2026',
            'time' => '18:35:59',
            'order' => $order,
            'order_items' => $products->invoke(null, 'order', $order->id, OrderProduct::class),
            'void' => false,
            'user' => $order->pdfCustomer(),
            'type' => 'order',
            'payments' => collect(),
            'payment_method_labels' => [],
        ]);
    }

    /** @test */
    public function invoice_pdf_merges_english_and_chinese_into_one_document(): void
    {
        $order = $this->seedOrder();
        $html = view('pdf.invoice', $this->invoiceData($order))->render();

        // Chinese version
        $this->assertStringContainsString('产品描述', $html);
        $this->assertStringContainsString('总金额', $html);
        $this->assertStringContainsString('付款条件', $html);
        // English version, in the SAME document
        $this->assertStringContainsString('Description', $html);
        $this->assertStringContainsString('Total Amount', $html);
        $this->assertStringContainsString('Payment Term', $html);
        // The two language versions are separated by a page break
        $this->assertStringContainsString('page-break-before', $html);
        // Dynamic data is untouched (appears once per language)
        $this->assertStringContainsString('SZZ029', $html);
        $this->assertStringContainsString('IV-2609-00735', $html);
    }

    /** @test */
    public function delivery_order_pdf_merges_english_and_chinese_into_one_document(): void
    {
        $order = $this->seedOrder();
        $data = array_merge($this->invoiceData($order), [
            'do_no' => 'DO-2609-00735',
            'show_prices' => true,
        ]);

        $html = view('pdf.delivery-order', $data)->render();

        $this->assertStringContainsString('送货单', $html);       // Chinese title
        $this->assertStringContainsString('Delivery Order', $html); // English title
        $this->assertStringContainsString('送货单号', $html);
        $this->assertStringContainsString('DO No.', $html);
        $this->assertStringContainsString('page-break-before', $html);
    }

    /** @test */
    public function generate_invoice_writes_single_merged_file(): void
    {
        Storage::fake('local');
        $order = $this->seedOrder();

        PdfHelper::GenerateOrderInvoice($order);

        Storage::disk('local')->assertExists(Order::$path . '/' . $order->id . '/invoice-' . $order->id . '.pdf');
        // No separate per-language file — both languages live in the one PDF
        Storage::disk('local')->assertMissing(Order::$path . '/' . $order->id . '/invoice-' . $order->id . '-en.pdf');
    }

    /** @test */
    public function generate_delivery_order_writes_single_merged_file(): void
    {
        Storage::fake('local');
        $order = $this->seedOrder();

        PdfHelper::GenerateDeliveryOrder($order);

        Storage::disk('local')->assertExists(Order::$path . '/' . $order->id . '/delivery-order-' . $order->id . '.pdf');
        Storage::disk('local')->assertMissing(Order::$path . '/' . $order->id . '/delivery-order-' . $order->id . '-en.pdf');
    }

    /** @test */
    public function admin_invoice_route_downloads_the_merged_pdf(): void
    {
        Storage::fake('local');
        $admin = Admin::forceCreate([
            'name' => 'Boss',
            'username' => 'boss' . rand(1000, 9999),
            'email' => 'boss' . rand(1000, 9999) . '@example.com',
            'role' => 'superadmin',
            'password' => Hash::make('password'),
        ]);

        $order = $this->seedOrder();
        $order->update(['paid_amount' => 583.00]); // canShowInvoice() needs a collected payment

        $this->actingAs($admin, 'web_admin')
            ->get(route('admin.order.invoice.download', $order->id))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf')
            ->assertDownload('invoice-' . $order->id . '.pdf');
    }
}
