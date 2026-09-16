<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Order;
use App\PdfHelper;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class OrderPdfController extends Controller
{
    /** Resolve the requested PDF language ('cn'/'en') to a supported locale. */
    private function lang(Request $request): string
    {
        return $request->query('lang') === 'en' ? 'en' : 'zh_CN';
    }

    public function invoice(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $this->assertCanViewInvoice($order);

        return PdfHelper::GenerateOrderInvoice($order, false, 'stream', $this->lang($request));
    }

    public function invoiceWithoutPrice(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $this->assertCanViewInvoice($order);

        return PdfHelper::GenerateOrderInvoiceWithoutPrice($order, false, 'stream', $this->lang($request));
    }

    public function deliveryOrder(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $this->assertCanViewDeliveryOrder($order);

        return PdfHelper::GenerateDeliveryOrder($order, false, 'stream', $this->lang($request));
    }

    public function downloadInvoice(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $this->assertCanViewInvoice($order);

        return PdfHelper::GenerateOrderInvoice($order, false, 'download', $this->lang($request));
    }

    public function downloadInvoiceWithoutPrice(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $this->assertCanViewInvoice($order);

        return PdfHelper::GenerateOrderInvoiceWithoutPrice($order, false, 'download', $this->lang($request));
    }

    public function downloadDeliveryOrder(Request $request, $id)
    {
        $order = Order::findOrFail($id);
        $this->assertCanViewDeliveryOrder($order);

        return PdfHelper::GenerateDeliveryOrder($order, false, 'download', $this->lang($request));
    }

    private function assertCanViewInvoice(Order $order): void
    {
        if (!$order->canShowInvoice()) {
            abort(403, 'Invoice is available after payment has been collected.');
        }
    }

    private function assertCanViewDeliveryOrder(Order $order): void
    {
        if (!$order->canAdminShowDeliveryOrder()) {
            abort(403, 'Delivery order is available once the order is in route for delivery.');
        }
    }
}
