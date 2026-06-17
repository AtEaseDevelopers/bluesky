<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use Illuminate\Support\Facades\Response;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use App\Exports\AdminOrderExport;
use Illuminate\Http\Request;
use App\OrderProduct;
use Carbon\Carbon;
use App\System;
use App\Helper;
use ZipArchive;
use App\Order;
use App\OrderPayment;
use App\Services\OrderService;
use App\Services\OrderStatusService;
use App\User;
use Illuminate\Support\Facades\Auth;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function index(Request $request)
    {
        app(OrderService::class)->syncOverduePaymentStatuses();

        $orders = Order::select(
            "*",
            DB::raw(
                "CONCAT_WS('<br />',
                    NULLIF(orders.billing_address, ''),
                    NULLIF(orders.billing_city, ''),
                    NULLIF(orders.billing_postcode, ''),
                    NULLIF(orders.billing_state, '')
                ) AS billing_address"
            ),
            DB::raw(
                "CONCAT_WS('<br />',
                    NULLIF(orders.shipping_address, ''),
                    NULLIF(orders.shipping_city, ''),
                    NULLIF(orders.shipping_postcode, ''),
                    NULLIF(orders.shipping_state, '')
                ) AS shipping_address"
            )
        );

        if ($filter_id = $request->input('id')) {
            $orders->where('id', $filter_id);
        }

        if ($filter_user_id = $request->input('customer')) {
            $orders->where('user_id', $filter_user_id);
        }

        if ($filter_fdate = $request->input('fdate')) {
            $orders->where('created_at', '>=', $filter_fdate);
        }

        if ($filter_tdate = $request->input('tdate')) {
            $orders->where('created_at', '<=', $filter_tdate." 23:59:59");
        }

        if ($filter_price_range = $request->input('price_range')) {
            $filter_price_range = explode(',', $filter_price_range);
            $from_price = $filter_price_range[0];
            $to_price = $filter_price_range[1];
            $orders->where('total_price', '>=', $from_price);
            $orders->where('total_price', '<=', $to_price);
        }

        if ($area = $request->input('area')) {
            $orders->where('area', $area);
        }

        if ($lorry = $request->input('lorry')) {
            $orders->where('driver_id', $lorry);
        }

        if ($filter_status = $request->input('status')) {
            $orders->where('status', $filter_status);
        }

        if ($filter_payment_status = $request->input('payment_status')) {
            $orders->where('payment_status', $filter_payment_status);
        }

        if ($filter_status = $request->input('orderby') === 'asc' || $request->input('orderby') === 'desc') {
            $orders->orderby('created_at', $request->input('orderby'));
        } else if ($filter_status = $request->input('orderby') === 'do_no_asc' || $request->input('orderby') == 'do_no_desc') {
            $orders->orderby('do_no', $request->input('orderby') === 'do_no_asc' ? 'desc' : 'asc');
        } 

        $orders = $orders->orderBy('id', 'desc')->with('customer')->paginate(15);
        $minPrice = Order::min('total_price');
        $maxPrice = Order::max('total_price');
                            
        foreach ($orders as $key => $value) {
            $orders[$key]->invoice_url = url('/') . '/' . Order::$path.'/' . $value->id . '/invoice-' . $value->id . '.pdf';
            $orders[$key]->invoice_download_url = url('download/') . Order::$path . '/' . $value->id . '/invoice-' . $value->id . '.pdf';
            $orders[$key]->delivery_order_url = url('/') . '/' . Order::$path . '/' . $value->id.'/delivery-order-' . $value->id . '.pdf';
            
            
            // $orders[$key]->order_products = DB::table('order_products')
            //     ->leftJoin('products', 'products.id', '=', 'order_products.product_id')
            //     ->where('order_id', $value->id)
            //     ->select(DB::raw("GROUP_CONCAT(CONCAT(products.name, ': ', IFNULL(CONCAT(order_products.weight, 'KG'), '')) SEPARATOR '<br />') as product_info"))
            //     ->value('product_info');
            // $orders[$key]->order_qtys = DB::table('order_products')
            //     ->leftJoin('products', 'products.id', '=', 'order_products.product_id')
            //     ->where('order_id', $value->id)
            //     ->select(DB::raw("GROUP_CONCAT(CONCAT('Qty: ', IFNULL(CONCAT(order_products.quantity), '0')) SEPARATOR '<br />') as product_info"))
            //     ->value('product_info');
            
            $order_products = DB::table('order_products')->where('status', 'active')->where('order_id', $value->id)->get();
            
            $ord_prod = null;
            $ord_qty = null;
            for ($i=0; $i < count($order_products); $i++) {
                if ($order_products[$i]->quantity != null && $order_products[$i]->product_weight != null) {
                    $ord_prod .= $order_products[$i]->product_name . ': ' . ($order_products[$i]->quantity * $order_products[$i]->product_weight) . 'KG';
                } else {
                    $ord_prod .= $order_products[$i]->product_name . ': ' . $order_products[$i]->weight . 'KG';
                }
                $ord_qty .= ('Qty:' . ($order_products[$i]->quantity ?? '-'));
                if ($i + 1 != count($order_products)) {
                    $ord_prod .= '<br>';
                    $ord_qty .= '<br>';
                }
            }
            $orders[$key]->order_products = $ord_prod;
            $orders[$key]->order_qtys = $ord_qty;
        }

        $statuses = collect(Order::$status)->mapWithKeys(function ($value, $key) {
            return [$key => __('order.status.' . $key)];
        })->toArray();

        $drivers_arr = [];
        $drivers = DB::table('drivers')->select('id', 'lorry_number')->get()->toArray();
        foreach ($drivers as $driver) {
            $drivers_arr[$driver->id] = $driver->lorry_number;
        }
        
        return view('admin.orders.index', [
                'orders' => $orders,
                'statuses' => $statuses,
                'drivers' => $drivers_arr,
                'input' => $request->all() + ['min_price' => $minPrice, 'max_price' => $maxPrice, 'from_price' => $from_price ?? $minPrice, 'to_price' => $to_price ?? $maxPrice],
                'query_params' => Helper::query_params($request->input()),
                'shipping_state_options' => System::$country_state['MY'],
                'status_options' => Order::$status,
                'payment_status_options' => Order::$payment_status,
                'areaList' => Helper::areaList(),
                'customers_list' => DB::table('users')->select('id', 'name')->get()->toArray(),
            ]
        );
    }

    public function change_order_status(Request $request)
    {
        $ids = explode(',', $request->orders_id);
        if ($ids) {
            foreach ($ids as $id) {
                if ($request->status != 'in_route' || ($request->status == 'in_route' && !DB::table('order_products')->where('weight', null)->where('order_id', $id)->first())) {
                    $order = Order::find($id);
                    if (!$order) {
                        continue;
                    }

                    try {
                        app(OrderStatusService::class)->transition(
                            $order,
                            $request->status,
                            Auth::guard('web_admin')->id()
                        );
                    } catch (\InvalidArgumentException $e) {
                        return back()->with('error', $e->getMessage());
                    }
                }
            }
        }

        return back()->with('success', 'Order status changed successfully.');
    }

    public function order_products_list(Request $request)
    {
        $products = DB::table('order_products')
            ->leftJoin('products', 'products.id', 'order_products.product_id')
            ->select('order_products.id', 'products.name', 'order_products.weight')
            ->where('order_products.order_id', decrypt($request['id']))
            ->where('order_products.status', 'active')
            ->get()
            ->toArray();

        $order = Order::find(decrypt($request['id']));
        if($order->do_date == '0000-00-00')
            {
                $order->do_date = $order->created_at->format("Y-m-d");
            }
        
        $view = view('admin.orders.order_products_list', compact('products','order'))->render();
        return Response::json(
            [
                'success' => true, 
                'view' => $view
            ]
        );
    }

    public function update_order_products_weight(Request $request)
    {
        $order = Order::find(decrypt($request['orders_id']));
        $products = DB::table('order_products')
            ->select('id')
            ->where('order_id', $order->id)
            ->get()
            ->toArray();

        $order_weight = 0;
        foreach ($products as $product) {
            $weight = $request->input('order_product_' . $product->id);
            DB::table('order_products')->where('id', $product->id)->update(
                [
                    'weight' => $weight
                ]
            );
            $order_weight += $weight;
        }

        // update order weight
        $order->update(
            [
            'order_weight' => $order_weight,
            'do_date' => $request['do_date']
            ]
        );

        return back()->with('success', 'Order products weight updated successfully.');
    }

    public function change_order_lorry(Request $request)
    {
        DB::table('orders')->where('id', decrypt($request['orders_id']))->update(
            [
                'driver_id' => $request->input('driver_id') ?: null,
            ]
        );

        return back()->with('success', 'Driver has been updated successfully.');
    }

    public function assign_order_driver(Request $request)
    {
        $ids = explode(',', $request->orders_id);
        if ($ids) {
            DB::table('orders')->whereIn('id', $ids)->update(
                [
                    'driver_id' => $request->input('driver_id') ?: null,
                ]
            );
        }

        return back()->with('success', 'Driver has been updated successfully.');
    }

    public function viewSummary($id)
    {
        $order = Order::select(
            "*",
            DB::raw(
                "CONCAT_WS('<br />',
                    NULLIF(orders.billing_address, ''),
                    NULLIF(orders.billing_city, ''),
                    NULLIF(orders.billing_postcode, ''),
                    NULLIF(orders.billing_state, '')
                ) AS billing_address"
            ),
            DB::raw(
                "CONCAT_WS('<br />',
                    NULLIF(orders.shipping_address, ''),
                    NULLIF(orders.shipping_city, ''),
                    NULLIF(orders.shipping_postcode, ''),
                    NULLIF(orders.shipping_state, '')
                ) AS shipping_address"
            ),
        )->where('id', $id)->first();

        if (!$order) {
            abort(404);
        }

        app(OrderService::class)->refreshPaymentStatus($order);
        $order = $order->fresh();
        $order->load('customer');

        $order_products = DB::table('order_products')
            ->select(
                'order_products.id as order_product_id', 
                'products.id as product_id', 
                'products.show_qty',
                'products.show_weight',
                'orders.id as order_id', 
                'orders.transfer_slip as transfer_slip', 
                'order_products.product_name as name', 
                'order_products.quantity', 
                'order_products.weight',
                'order_products.product_weight',
                'order_products.unit_price', 
                'order_products.price',
                'order_products.remark'
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
                $transfer_slip_url = url('/') . '/'.Order::$path.'/'.$value->order_id.'/'.$value->transfer_slip;
            }
            $order_products[$key]->transfer_slip_url = $transfer_slip_url;
            
            $total += $order_products[$key]->unit_price * $value->quantity;
        }

        return view('admin.orders.order-summary', [
                'order' => $order,
                'invoice_url' => url('/') . '/'.Order::$path.'/'.$order->id.'/invoice-' . $order->id . '.pdf',
                'invoice_download_url' => url('download/').Order::$path.'/'.$order->id.'/invoice-' . $order->id . '.pdf',
                'invoice2_url' => url('/') . '/'.Order::$path.'/'.$order->id.'/invoice2-' . $order->id . '.pdf',
                'invoice2_download_url' => url('download/').Order::$path.'/'.$order->id.'/invoice2-' . $order->id . '.pdf',
                'delivery_order_url' => url('/') . '/'.Order::$path.'/'.$order->id.'/delivery-order-' . $order->id . '.pdf',
                'delivery_order_download_url' => url('download/').Order::$path.'/'.$order->id.'/delivery-order-' . $order->id . '.pdf',
                'products' => $order_products,
                'total' => number_format($total, 2, '.', ''),
                'customer' => $order->customer,
                'customerName' => app(OrderService::class)->displayCustomerName($order),
                'payments' => $order->payments()->with(['recorder', 'submitter'])->orderByDesc('id')->get(),
                'paymentMethods' => $order->allowedAdminPaymentMethods(),
                'allPaymentMethods' => OrderPayment::$payment_methods,
                'paymentStatusLabels' => OrderPayment::$status_labels,
                'nextStatuses' => app(OrderStatusService::class)->nextStatuses($order->status),
                'isCreditCustomer' => $order->isCreditCustomer(),
                'drivers' => $this->driverOptionsForOrder($order),
            ]
        );
    }

    private function driverOptionsForOrder(Order $order): array
    {
        return DB::table('drivers')
            ->select('id', 'name', 'lorry_number', 'is_active')
            ->where(function ($query) use ($order) {
                $query->where('is_active', true);
                if ($order->driver_id) {
                    $query->orWhere('id', $order->driver_id);
                }
            })
            ->orderBy('lorry_number')
            ->get()
            ->mapWithKeys(function ($driver) {
                $label = $driver->lorry_number;
                if ($driver->name) {
                    $label = $driver->name . ' (' . $driver->lorry_number . ')';
                }
                if (!$driver->is_active) {
                    $label .= ' [Inactive]';
                }

                return [$driver->id => $label];
            })
            ->all();
    }

    public function updatePaymentDueDate(Request $request, $id)
    {
        $order = Order::with('customer')->findOrFail($id);

        if (!$order->isCreditCustomer()) {
            return back()->with('error', 'Payment due date applies to credit customers only.');
        }

        $request->validate([
            'payment_due_date' => 'nullable|date',
        ]);

        try {
            app(OrderService::class)->updatePaymentDueDate(
                $order,
                $request->input('payment_due_date')
            );
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Payment due date updated successfully.');
    }

    public function export(Request $request)
    {
        return Excel::download(new \App\Exports\OrdersExport(), 'Orders Report - ' . date('Y-m-d') . '.xlsx');

        $orders = Order::select("*");

        if ($filter_id = $request->input('id')) {
            $orders->where('id', $filter_id);
        }

        if ($filter_user_id = $request->input('customer')) {
            $orders->where('user_id', $filter_user_id);
        } else {
            $ids = explode(',', $request->orders_id);
            if ($ids) {
                $orders->whereIn('id', $ids);
            }
        }

        if ($filter_fdate = $request->input('fdate')) {
            $orders->where('created_at', '>=', $filter_fdate);
        }

        if ($filter_tdate = $request->input('tdate')) {
            $orders->where('created_at', '<=', $filter_tdate." 23:59:59");
        }

        if ($filter_price_range = $request->input('price_range')) {
            $filter_price_range = explode(',', $filter_price_range);
            $from_price = $filter_price_range[0];
            $to_price = $filter_price_range[1];
            $orders->where('total_price', '>=', $from_price);
            $orders->where('total_price', '<=', $to_price);
        }

        if ($filter_shipping_state = $request->input('shipping_state')) {
            $orders->where('shipping_state', $filter_shipping_state);
        }

        if ($filter_status = $request->input('status')) {
            $orders->where('status', $filter_status);
        }

        $orders = $orders->get();
        $data = [];

        foreach ($orders as $key => $order) {
            $data[] = [
                'no' => $key + 1,
                'created_at' => $order->created_at,
                'customer' => $order->customer->name,
                'price' => $order->total_price,
                'payment_method' => $order->payment_method ? __('user.payment_method.'.$order->payment_method) : '',
                'billing_address' => $order->billing_address .", ". $order->billing_postcode .", ". $order->billing_state,
                'shipping_address' => $order->shipping_address .", ". $order->shipping_postcode .", ". $order->shipping_state,
                'status' => __('order.status.'.$order->status),
                'updated_at' => $order->updated_at,
            ];
        }

        if ($data) {
            $header = ['No', 'Order At', 'Customer', 'Total Price', 'Payment Method', 'Billing Address', 'Shipping Address', 'Status', 'Last Updated At']; // Adjust the header based on your data model
            return Excel::download(new AdminOrderExport(collect($data), $header), Carbon::now()->format('YmdHis').'-Order-List.xlsx');
        }
    }

    public function downloadInvoiceDoAsZip(Request $request)
    {
        $zip = new ZipArchive();
        $zipFileName = storage_path('app/public/invoices_and_delivery_orders'. Carbon::now()->format('YmdHis') .'.zip');
    
        if ($zip->open($zipFileName, ZipArchive::CREATE) === true) {
            $orders = Order::whereIn('id', $request->order_ids);
    
            foreach ($orders->get() as $order) {
                if ($order->canShowDeliveryOrder()) {
                    $orderFile = storage_path('app/orders/' . $order->id . '/delivery-order-' . $order->id . '.pdf');
                    if (file_exists($orderFile)) {
                        $zip->addFile($orderFile, 'do/delivery-order-' . $order->id . '.pdf');
                    }
                }

                if ($order->canShowInvoice()) {
                    $orderFile = storage_path('app/orders/' . $order->id . '/invoice-' . $order->id . '.pdf');
                    if (file_exists($orderFile)) {
                        $zip->addFile($orderFile, 'invoice/invoice-' . $order->id . '.pdf');
                    }

                    $orderFile = storage_path('app/orders/' . $order->id . '/invoice2-' . $order->id . '.pdf');
                    if (file_exists($orderFile)) {
                        $zip->addFile($orderFile, 'invoice/invoicewoprice-' . $order->id . '.pdf');
                    }
                }
            }
    
            $zip->close();

            $orders->update(
                [
                'status' => Order::$status['paid_completed']
                ]
            );
    
            return response()->download($zipFileName)->deleteFileAfterSend(true);
        }
    }

    public function download_do_zip(Request $request)
    {
        // format current date or from and to date
        $fdate = $request->fdate;
        $tdate = $request->tdate;

        $today = now()->toDateString();
        $startDate = $fdate ?: $today;
        $endDate = $tdate ?: $today;
        $startDate = min($startDate, $endDate);

        $zip = new ZipArchive();
        $zipFileName = storage_path('app/public/DO_Report_orders'. Carbon::now()->format('YmdHis') .'.zip');
    
        if ($zip->open($zipFileName, ZipArchive::CREATE) === true) {
            $orders = DB::table('orders')
                ->select('id')
                ->whereBetween('orders.created_at', [$startDate, $endDate . " 23:59:59"])
                ->when(
                    $request->id, function ($q) {
                        return $q->where('orders.id', request()->id);
                    }
                )
            ->when(
                $request->status, function ($q) {
                    return $q->where('orders.status', request()->status);
                }
            )
            ->when(
                $request->driver, function ($q) {
                    return $q->where('orders.driver_id', request()->driver);
                }
            )
            ->when(
                $request->customer, function ($q) {
                    return $q->where('orders.user_id', request()->customer);
                }
            )
            ->when(
                $request->shipping_state, function ($q) {
                    return $q->where('orders.shipping_state', 'LIKE', '%' . request()->shipping_state . '%');
                }
            )
            ->get()
            ->toArray();
    
            foreach ($orders as $orderRow) {
                $order = Order::find($orderRow->id);
                if (!$order || !$order->canShowDeliveryOrder()) {
                    continue;
                }

                $orderFile = storage_path('app/orders/' . $order->id . '/delivery-order-' . $order->id . '.pdf');
                if (file_exists($orderFile)) {
                    $zip->addFile($orderFile, 'do/delivery-order-' . $order->id . '.pdf');
                }
            }

            $zip->close();
    
            return response()->download($zipFileName)->deleteFileAfterSend(true);
        }
    }
}
