<?php

namespace App;

use App\Services\OrderService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;

class PdfHelper extends Model
{
    /**
     * Common method to handle PDF return options
     */
    private static function configurePdf($pdf)
    {
        $pdf->setOption('isFontSubsettingEnabled', true);
        $pdf->setOption('defaultFont', 'Noto Sans SC');

        return $pdf;
    }

    /**
     * A bilingual label for the INV/DO PDFs: the Chinese and English versions of
     * a `pdf.*` translation key rendered together on one line (used by the blade
     * templates so every label shows both languages on the same page).
     */
    public static function bilingual(string $key, string $separator = ' '): string
    {
        $zh = __($key, [], 'zh_CN');
        $en = __($key, [], 'en');

        return $zh === $en ? $zh : $zh . $separator . $en;
    }

    /**
     * Render a bilingual INV/DO document (each label shows both Chinese and
     * English on the same page), store it, and stream/download it or return its
     * storage path.
     */
    private static function renderBilingual(string $view, array $data, string $filename, Order $order, $returnPdf)
    {
        $pdf = self::configurePdf(PDF::loadView($view, $data));
        $pdf->setPaper('a4', 'portrait');

        Storage::disk('local')->put(Order::$path . '/' . $order->id . '/' . $filename, $pdf->output());

        if ($returnPdf === 'stream') {
            return $pdf->stream($filename);
        }

        if ($returnPdf === 'download') {
            return $pdf->download($filename);
        }

        return Order::$path . '/' . $order->id . '/' . $filename;
    }

    /**
     * Ensure the order has a customer to render. General Customer (public)
     * orders have no user account, so build a transient fallback from the
     * order's own guest fields and attach it as the customer relation.
     * Returns the resolved customer (real or fallback).
     */
    private static function resolveCustomer(Order $order)
    {
        if ($order->customer) {
            return $order->customer;
        }

        // Mirror Order::pdfCustomer()/OrderService::displayCustomerName(): a
        // walk-in order stores its name in walk_in_name, so prefer that before
        // falling back to attn_name. Otherwise the invoice/DO customer box
        // renders blank for walk-in orders that never set attn_name.
        $fallback = new User([
            'name' => $order->walk_in_name ?: ($order->attn_name ?: 'Walk-in Customer'),
            'attn_contact' => $order->walk_in_phone ?: $order->attn_contact,
        ]);
        // Public COD invoices should display prices; no per-customer flags exist.
        $fallback->invoice_price_permission = true;
        $fallback->fax_no = null;
        $fallback->sql_customer_code = null;

        $order->setRelation('customer', $fallback);

        return $fallback;
    }

    /**
     * Common method to get products for orders or quotations
     */
    private static function getProductsData($type, $id, $productModel)
    {
        return DB::table("{$type}_products")
            ->select(
                "{$type}_products.id as {$type}_product_id", 
                'products.id as product_id', 
                'products.show_qty as show_qty',
                'products.show_weight as show_weight',
                'products.sku as sku',
                'products.description as product_description',
                "{$type}s.id as {$type}_id", 
                "{$type}s.transfer_slip as transfer_slip", 
                "{$type}_products.product_name as name", 
                "{$type}_products.product_name",
                "{$type}_products.quantity", 
                "{$type}_products.unit_price", 
                "{$type}_products.price",
                "{$type}_products.remark",
                "{$type}_products.nos",
                "{$type}_products.weight",
                "{$type}_products.product_weight",
                DB::raw("(SELECT GROUP_CONCAT(
                        CONCAT(`option`, ': ', `option_item`) 
                        SEPARATOR ', '
                    ) 
                    FROM {$type}_product_options 
                    WHERE {$type}_product_options.{$type}_product_id = {$type}_products.id 
                    AND {$type}_product_options.status = 'active') as product_options"
                )
            )
            ->leftJoin("{$type}s", "{$type}s.id", '=', "{$type}_products.{$type}_id")
            ->leftJoin('products', 'products.id', '=', "{$type}_products.product_id")
            ->where("{$type}_products.status", $productModel::$status['active'])
            ->where("{$type}s.id", $id)
            ->get()
            ->map(function ($line) {
                $line->name = OrderProduct::displayName($line);

                return $line;
            });
    }

    private static function invoiceViewData(Order $order, array $data = []): array
    {
        self::resolveCustomer($order);
        $customer = $order->pdfCustomer();

        return array_merge([
            'company' => config('portal.company'),
            'customer_phone' => $order->walk_in_phone ?: ($order->attn_contact ?: ($customer->attn_contact ?? '')),
            'payment_term' => $order->preferredPaymentMethodLabel() ?: '-',
            'customer_code' => $customer->sql_customer_code ?? '-',
            'fulfillment' => $order->fulfillmentTypeLabel(),
            'currency' => 'MYR',
        ], $data);
    }

    // Order specific methods (keep original structure but use common helpers)
    public static function GenerateOrderInvoice(Order $order, $void = false, $returnPdf = false)
    {
        $order_products = self::getProductsData('order', $order->id, OrderProduct::class);
        $data = self::invoiceViewData($order, [
            'invoice_number' => $order->invoice_number ?: ('INV-' . $order->id),
            'date' => now()->format('d/m/Y'),
            'time' => now()->format('H:i:s'),
            'order' => $order,
            'order_items' => $order_products,
            'void' => $void,
            'user' => $order->pdfCustomer(),
            'type' => 'order',
            'payments' => $order->payments()
                ->where('status', OrderPayment::STATUS_CONFIRMED)
                ->orderBy('id')
                ->get(),
            'payment_method_labels' => OrderPayment::$payment_methods,
        ]);

        return self::renderBilingual('pdf.invoice', $data, 'invoice-' . $order->id . '.pdf', $order, $returnPdf);
    }

    public static function GenerateOrderInvoiceWithoutPrice(Order $order, $void = false, $returnPdf = false)
    {
        $order_products = self::getProductsData('order', $order->id, OrderProduct::class);
        $data = self::invoiceViewData($order, [
            'invoice_number' => $order->invoice_number ?: ('INV-' . $order->id),
            'date' => now()->format('d/m/Y'),
            'time' => now()->format('H:i:s'),
            'order' => $order,
            'order_items' => $order_products,
            // 'total' => $total,
            'void' => $void,
            'user' => $order->pdfCustomer(),
            'type' => 'order',
        ]);

        return self::renderBilingual('pdf.invoicewithoutprice', $data, 'invoice2-' . $order->id . '.pdf', $order, $returnPdf);
    }

    private static function deliveryViewData(Order $order, array $data = []): array
    {
        self::resolveCustomer($order);
        app(OrderService::class)->assignDoNumber($order);
        $order->refresh();
        $customer = $order->pdfCustomer();

        return array_merge([
            'company' => config('portal.company'),
            'customer_phone' => $order->walk_in_phone ?: ($order->attn_contact ?: ($customer->attn_contact ?? '')),
            'do_no' => $order->do_no,
            'payment_term' => $order->preferredPaymentMethodLabel() ?: '-',
            'customer_code' => $customer->sql_customer_code ?? '-',
            'fulfillment' => $order->fulfillmentTypeLabel(),
            'currency' => 'MYR',
        ], $data);
    }

    public static function GenerateDeliveryOrder(Order $order, $void = false, $returnPdf = false)
    {
        self::resolveCustomer($order);
        $order_products = self::getProductsData('order', $order->id, OrderProduct::class);
        $data = self::deliveryViewData($order, [
            'invoice_number' => $order->invoice_number ?: ('INV-' . $order->id),
            'date' => optional($order->created_at)->format('d/m/Y'),
            'time' => optional($order->created_at)->format('H:i:s'),
            'order' => $order,
            'order_items' => $order_products,
            'void' => $void,
            'show_prices' => OrderFieldSetting::deliveryOrderShowsPrices(),
            'payments' => $order->payments()
                ->where('status', OrderPayment::STATUS_CONFIRMED)
                ->orderBy('id')
                ->get(),
            'payment_method_labels' => OrderPayment::$payment_methods,
        ]);

        return self::renderBilingual('pdf.delivery-order', $data, 'delivery-order-' . $order->id . '.pdf', $order, $returnPdf);
    }

    public static function UpdateDeliveryOrder(Order $order, $void = false, $custom_date = null)
    {
        $order_products = self::getProductsData('order', $order->id, OrderProduct::class);
        $total = 0;
        foreach ($order_products as $value) {
            $total += $value->unit_price * $value->quantity;
        }

        $data = self::deliveryViewData($order, [
            'invoice_number' => $order->invoice_number ?: ('INV-' . $order->id),
            'date' => $custom_date ? \Illuminate\Support\Carbon::parse($custom_date)->format('d/m/Y') : optional($order->do_date)->format('d/m/Y'),
            'time' => optional($order->do_date)->format('H:i:s'),
            'order' => $order,
            'order_items' => $order_products,
            'total' => $total,
            'void' => $void,
            'show_prices' => OrderFieldSetting::deliveryOrderShowsPrices(),
        ]);

        self::renderBilingual('pdf.delivery-order2', $data, 'delivery-order-' . $order->id . '.pdf', $order, false);
    }
}
