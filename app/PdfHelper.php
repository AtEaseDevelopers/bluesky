<?php

namespace App;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use PDF;
use Auth;
use Carbon\Carbon;

class PdfHelper extends Model
{
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

    // Order specific methods (keep original structure but use common helpers)
    public static function GenerateOrderInvoice(Order $order, $void = false, $returnPdf = false)
    {
        $order_products = self::getProductsData('order', $order->id, OrderProduct::class);
        $data = [
            'invoice_number' => 'INV-' . $order->id,
            'date' => now()->format('d/m/Y'),
            'order' => $order,
            'order_items' => $order_products,
            'void' => $void,
            'user' => $order->customer,
            'type' => 'order',
        ];

        $pdf = PDF::loadView('pdf.invoice', $data);
        $pdf->setPaper('a4', 'portrait');

        $invoiceFilename = 'invoice-' . $order->id . '.pdf';

        return self::handlePdfReturn($pdf, $invoiceFilename, Order::$path, $order->id, $returnPdf);
    }

    public static function GenerateOrderInvoiceWithoutPrice(Order $order, $void = false, $returnPdf = false)
    {
        $order_products = self::getProductsData('order', $order->id, OrderProduct::class);
        $data = [
            'invoice_number' => 'INV-' . $order->id,
            'date' => now()->format('d/m/Y'),
            'order' => $order,
            'order_items' => $order_products,
            // 'total' => $total,
            'void' => $void,
            'user' => $order->customer,
            'type' => 'order',
        ];

        $pdf = PDF::loadView('pdf.invoicewithoutprice', $data);
        $pdf->setPaper('a4', 'portrait');

        $invoiceFilename = 'invoice-' . $order->id . '.pdf';

        return self::handlePdfReturn($pdf, $invoiceFilename, Order::$path, $order->id, $returnPdf);
    }

    public static function GenerateDeliveryOrder(Order $order, $void = false, $returnPdf = false)
    {
        $order_products = self::getProductsData('order', $order->id, OrderProduct::class);
        $data = [
            'invoice_number' => 'INV-' . $order->id,
            'date' => $order->created_at,
            'order' => $order,
            'order_items' => $order_products,
            // 'total' => $total,
            'void' => $void,
            'do_no' => $order->do_no,
        ];

        $pdf = PDF::loadView('pdf.delivery-order', $data);
        $pdf->setPaper('a4', 'portrait');

        $invoiceFilename = 'delivery-order-' . $order->id . '.pdf';

        return self::handlePdfReturn($pdf, $invoiceFilename, Order::$path, $order->id, $returnPdf);
    }
  
    public static function UpdateDeliveryOrder(Order $order, $void = false, $custom_date = null)
    {
        $order_products = DB::table('order_products')
            ->select(
                'order_products.id as order_product_id', 
                'products.id as product_id', 
                'products.show_qty as show_qty',
                'products.show_weight as show_weight',
                'orders.id as order_id', 
                'orders.transfer_slip as transfer_slip', 
                'order_products.product_name as name', 
                'order_products.quantity', 
                'order_products.unit_price', 
                'order_products.price',
                'order_products.remark',
                'order_products.nos',
                'order_products.weight',
                'order_products.product_weight'
            )
            ->leftJoin('orders', 'orders.id', '=', 'order_products.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_products.product_id')
            ->where('order_products.status', OrderProduct::$status['active'])
            ->where('orders.id', $order->id)
            ->get();

        $total = 0;
        foreach ($order_products as $key => $value) {
            $order_products[$key]->options = OrderProduct::getOption($value->order_product_id);

            $transfer_slip_url = "";
            if ($order->payment_method == User::$payment_method['bank-transfer']) {
                $transfer_slip_url = url('/').Order::$path.'/'.$value->order_id.'/'.$value->transfer_slip;
            }
            $order_products[$key]->transfer_slip_url = $transfer_slip_url;
            
            $total += $order_products[$key]->unit_price * $value->quantity;
        }
        
        if ($order->do_no == null) {
            if ($custom_date != null) {
                $prefix = 'DO' . Carbon::parse($custom_date)->format('ym'); // Prefix for the format AHP2501
            } else {
                $prefix = 'DO' . now()->format('ym'); // Prefix for the format AHP2501
            }
            $latestOrder = Order::where('do_no', 'like', $prefix . '%')
                ->orderBy('do_no', 'desc')
                ->first();
            
            if ($latestOrder) {
                // Extract the running number part and increment it
                $lastNumber = (int) substr($latestOrder->do_no, strlen($prefix));
                $do_no_idx = $lastNumber + 1;
            } else {
                // Start from 1 if no previous record exists
                $do_no_idx = 1;
            }
    
            //$do_no_idx = Order::where('do_no', 'like', 'AHP'. now()->format('ym') . '%')->count();
            //$do_no_idx++;
            
            $ending_digit = sprintf('%04d', $do_no_idx);
            $do_no = $prefix . $ending_digit;
            $order->do_no = $do_no;
            $order->save();
        }
        
        $data = [
            'invoice_number' => 'INV-'.$order->id,
            'date' => $order->do_date,
            'order' => $order,
            'order_items' => $order_products,
            'total' => $total,
            'void' => $void,
            'do_no' => $order->do_no,
            // Add more invoice data as needed
        ];
    
        $pdf = PDF::loadView('pdf.delivery-order2', $data);
        $pdf->setPaper('a4', 'portrait'); // A4 size in portrait mode
    
        $invoiceFilename = 'delivery-order-' . $order->id . '.pdf';
    
        Storage::disk('local')->put(Order::$path.'/'.$order->id.'/'.$invoiceFilename, $pdf->output());
    }
}
