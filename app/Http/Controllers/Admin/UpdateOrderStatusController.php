<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AdminOrderExport;
use App\Helper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Order;
use App\OrderProduct;
use App\PdfHelper;
use App\Services\OrderStatusService;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class UpdateOrderStatusController extends Controller
{
    protected OrderStatusService $orderStatusService;

    public function __construct(OrderStatusService $orderStatusService)
    {
        $this->middleware('auth_admin');
        $this->orderStatusService = $orderStatusService;
    }

    public function index(Request $request, Order $order)
    {
        $status = $request->status;
        if (!in_array($status, Order::$status)) {
            return response()->json(['success' => false, 'message' => 'Invalid status']);
        }

        try {
            if ($status === Order::$status['in_route'] && $order->isDelivery()) {
                $request->validate([
                    'driver_id' => 'required|exists:drivers,id',
                ]);

                $order->update(['driver_id' => $request->input('driver_id')]);
                $order = $order->fresh();
            }

            $this->orderStatusService->transition(
                $order,
                $status,
                Auth::guard('web_admin')->id()
            );

            return response()->json(['success' => true]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->validator->errors()->first(),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()]);
        }
    }

    public function batchUpdate(Request $request, Order $order)
    {
        $status = $request->status;
        if (!in_array($status, Order::$status)) {
            return json_encode(['success' => false]);
        }

        $success = true;
        foreach ($request->order_ids as $order_id) {
            $order = Order::find($order_id);
            if (!$order) {
                continue;
            }

            try {
                $this->orderStatusService->transition(
                    $order,
                    $status,
                    Auth::guard('web_admin')->id()
                );
            } catch (\InvalidArgumentException $e) {
                $success = false;
            }
        }

        return json_encode(['success' => $success]);
    }
}
