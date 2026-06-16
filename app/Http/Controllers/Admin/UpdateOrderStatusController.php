<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AdminOrderExport;
use App\Helper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Order;
use App\OrderProduct;
use App\PdfHelper;
use App\Services\StockService;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class UpdateOrderStatusController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function index(Request $request, Order $order)
    {
        $success = false;
        $status = $request->status;
        if (!in_array($status, Order::$status)) {
            $success = false;
        }
        
        $prev_status = $order->status;
        $order->update(
            [
            'status' => $status,
            ]
        );
        $success = true;

        app(StockService::class)->handleOrderStatusChange(
            $order->fresh(),
            $prev_status,
            $status,
            Auth::guard('web_admin')->id()
        );

        if ($status == Order::$status['processing']) {
            // generate invoice and DO
            PdfHelper::GenerateOrderInvoice($order);
            PdfHelper::GenerateOrderInvoiceWithoutPrice($order);
            // PdfHelper::GenerateDeliveryOrder($order);
        }

        if ($status == Order::$status['cancelled']) {
            // void invoice
            PdfHelper::GenerateOrderInvoice($order);
            PdfHelper::GenerateOrderInvoiceWithoutPrice($order);
            // PdfHelper::GenerateDeliveryOrder($order);
        }
        
        return json_encode(['success' => $success]);
    }

    public function batchUpdate(Request $request, Order $order)
    {
        $success = false;

        $status = $request->status;
        if (!in_array($status, Order::$status)) {
            $success = false;
        }
        
        foreach ($request->order_ids as $key => $order_id) {
            $order = Order::find($order_id);

            $prev_status = $order->status;
            $order->update(
                [
                'status' => $status,
                ]
            );
            $success = true;

            app(StockService::class)->handleOrderStatusChange(
                $order->fresh(),
                $prev_status,
                $status,
                Auth::guard('web_admin')->id()
            );
    
            if ($status == Order::$status['processing']) {
                // generate invoice and DO
                PdfHelper::GenerateOrderInvoice($order);
                PdfHelper::GenerateOrderInvoiceWithoutPrice($order);
                // PdfHelper::GenerateDeliveryOrder($order);
            }
    
            if ($status == Order::$status['cancelled']) {
                // void invoice
                PdfHelper::GenerateOrderInvoice($order);
                PdfHelper::GenerateOrderInvoiceWithoutPrice($order);
                // PdfHelper::GenerateDeliveryOrder($order);
            }
        }
        
        return json_encode(['success' => $success]);
    }
}
