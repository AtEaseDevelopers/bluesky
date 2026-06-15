<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Order;
use App\PdfHelper;
use Illuminate\Http\Request;

class OrderPdfController extends Controller
{
    public function invoice($id)
    {
        $order = Order::findOrFail($id);
        return PdfHelper::GenerateOrderInvoice($order, false, 'stream');
    }

    public function invoiceWithoutPrice($id)
    {
        $order = Order::findOrFail($id);
        return PdfHelper::GenerateOrderInvoiceWithoutPrice($order, false, 'stream');
    }

    public function deliveryOrder($id)
    {
        $order = Order::findOrFail($id);
        return PdfHelper::GenerateDeliveryOrder($order, false, 'stream');
    }
    
    // Download endpoints
    public function downloadInvoice($id)
    {
        $order = Order::findOrFail($id);
        return PdfHelper::GenerateOrderInvoice($order, false, 'download');
    }

    public function downloadInvoiceWithoutPrice($id)
    {
        $order = Order::findOrFail($id);
        return PdfHelper::GenerateOrderInvoiceWithoutPrice($order, false, 'download');
    }

    public function downloadDeliveryOrder($id)
    {
        $order = Order::findOrFail($id);
        return PdfHelper::GenerateDeliveryOrder($order, false, 'download');
    }
}