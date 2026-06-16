<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Order;
use App\PdfHelper;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrderPdfController extends Controller
{
    public function invoice($id)
    {
        $order = Order::findOrFail($id);
        $this->assertCanViewInvoice($order);

        return PdfHelper::GenerateOrderInvoice($order, false, 'stream');
    }

    public function invoiceWithoutPrice($id)
    {
        $order = Order::findOrFail($id);
        $this->assertCanViewInvoice($order);

        return PdfHelper::GenerateOrderInvoiceWithoutPrice($order, false, 'stream');
    }

    public function deliveryOrder($id)
    {
        $order = Order::findOrFail($id);
        $this->assertCanViewDeliveryOrder($order);

        return PdfHelper::GenerateDeliveryOrder($order, false, 'stream');
    }

    public function downloadInvoice($id)
    {
        $order = Order::findOrFail($id);
        $this->assertCanViewInvoice($order);

        return PdfHelper::GenerateOrderInvoice($order, false, 'download');
    }

    public function downloadInvoiceWithoutPrice($id)
    {
        $order = Order::findOrFail($id);
        $this->assertCanViewInvoice($order);

        return PdfHelper::GenerateOrderInvoiceWithoutPrice($order, false, 'download');
    }

    public function downloadDeliveryOrder($id)
    {
        $order = Order::findOrFail($id);
        $this->assertCanViewDeliveryOrder($order);

        return PdfHelper::GenerateDeliveryOrder($order, false, 'download');
    }

    private function assertCanViewInvoice(Order $order): void
    {
        if (!$order->canShowInvoice()) {
            abort(403, 'Invoice is available after payment has been collected.');
        }
    }

    private function assertCanViewDeliveryOrder(Order $order): void
    {
        if (!$order->canShowDeliveryOrder()) {
            abort(403, 'Delivery order is available once the order is in route for delivery.');
        }
    }
}
