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
     * Common method to handle PDF return options
     */
    private static function handlePdfReturn($pdf, $filename, $path, $id, $returnPdf)
    {
        // Always save to storage
        Storage::disk('local')->put($path . '/' . $id . '/' . $filename, $pdf->output());

        // Handle return behavior
        if ($returnPdf === 'stream') {
            return $pdf->stream($filename);
        }

        if ($returnPdf === 'download') {
            return $pdf->download($filename);
        }

        // Default return path
        return $path . '/' . $id . '/' . $filename;
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

        $fallback = new User([
            'name' => $order->attn_name,
            'attn_contact' => $order->attn_contact,
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
                "{$type}s.id as {$type}_id", 
                "{$type}s.transfer_slip as transfer_slip", 
                "{$type}_products.product_name as name", 
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
            ->get();
    }

    private static function invoiceViewData(Order $order, array $data = []): array
    {
        self::resolveCustomer($order);
        $customer = $order->pdfCustomer();

        return array_merge([
            'company' => config('portal.company'),
            'customer_phone' => $order->walk_in_phone ?: ($order->attn_contact ?: ($customer->attn_contact ?? '')),
        ], $data);
    }

    // Order specific methods (keep original structure but use common helpers)
    public static function GenerateOrderInvoice(Order $order, $void = false, $returnPdf = false)
    {
        $order_products = self::getProductsData('order', $order->id, OrderProduct::class);
        $data = self::invoiceViewData($order, [
            'invoice_number' => $order->invoice_number ?: ('INV-' . $order->id),
            'date' => now()->format('d/m/Y'),
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

        $pdf = self::configurePdf(PDF::loadView('pdf.invoice', $data));
        $pdf->setPaper('a4', 'portrait');

        $invoiceFilename = 'invoice-' . $order->id . '.pdf';

        return self::handlePdfReturn($pdf, $invoiceFilename, Order::$path, $order->id, $returnPdf);
    }

    public static function GenerateOrderInvoiceWithoutPrice(Order $order, $void = false, $returnPdf = false)
    {
        $order_products = self::getProductsData('order', $order->id, OrderProduct::class);
        $data = self::invoiceViewData($order, [
            'invoice_number' => $order->invoice_number ?: ('INV-' . $order->id),
            'date' => now()->format('d/m/Y'),
            'order' => $order,
            'order_items' => $order_products,
            // 'total' => $total,
            'void' => $void,
            'user' => $order->pdfCustomer(),
            'type' => 'order',
        ]);

        $pdf = self::configurePdf(PDF::loadView('pdf.invoicewithoutprice', $data));
        $pdf->setPaper('a4', 'portrait');

        $invoiceFilename = 'invoice2-' . $order->id . '.pdf';

        return self::handlePdfReturn($pdf, $invoiceFilename, Order::$path, $order->id, $returnPdf);
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
        ], $data);
    }

    public static function GenerateDeliveryOrder(Order $order, $void = false, $returnPdf = false)
    {
        self::resolveCustomer($order);
        $order_products = self::getProductsData('order', $order->id, OrderProduct::class);
        $data = self::deliveryViewData($order, [
            'invoice_number' => $order->invoice_number ?: ('INV-' . $order->id),
            'date' => $order->created_at,
            'order' => $order,
            'order_items' => $order_products,
            'void' => $void,
            'show_prices' => OrderFieldSetting::deliveryOrderShowsPrices(),
        ]);

        $pdf = self::configurePdf(PDF::loadView('pdf.delivery-order', $data));
        $pdf->setPaper('a4', 'portrait');

        $invoiceFilename = 'delivery-order-' . $order->id . '.pdf';

        return self::handlePdfReturn($pdf, $invoiceFilename, Order::$path, $order->id, $returnPdf);
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
            'date' => $order->do_date,
            'order' => $order,
            'order_items' => $order_products,
            'total' => $total,
            'void' => $void,
            'show_prices' => OrderFieldSetting::deliveryOrderShowsPrices(),
        ]);

        $pdf = self::configurePdf(PDF::loadView('pdf.delivery-order2', $data));
        $pdf->setPaper('a4', 'portrait'); // A4 size in portrait mode
    
        $invoiceFilename = 'delivery-order-' . $order->id . '.pdf';
    
        Storage::disk('local')->put(Order::$path.'/'.$order->id.'/'.$invoiceFilename, $pdf->output());
    }
}
