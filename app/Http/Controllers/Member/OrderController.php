<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Auth;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Support\Facades\DB;
use App\Exports\MemberOrderExport;
use Illuminate\Http\Request;
use App\OrderProductOption;
use App\OrderProduct;
use Carbon\Carbon;
use App\Product;
use App\System;
use App\Helper;
use App\Order;


class OrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('web');
    }

    public function index(Request $request)
    {
        $user = Auth::guard('web')->user();

        $orders = Order::select(
            'id', 
            'created_at', 
            'payment_method', 
            'total_price', 
            'status'
        )->where('user_id', $user->id);

        if ($filter_fdate = $request->input('fdate')) {
            $orders->where('created_at', '>=', $filter_fdate);
        }

        if ($filter_tdate = $request->input('tdate')) {
            $orders->where('created_at', '<=', $filter_tdate." 23:59:59");
        }

        if ($filter_status = $request->input('status')) {
            $orders->where('status', $filter_status);
        }

        if ($filter_status = $request->input('orderby') ?? 'desc') {
            $orders->orderby('created_at', $filter_status);
        }

        $orders = $orders->paginate(15);
                            
        foreach ($orders as $key => $value) {
            $orders[$key]->invoice_url = url('/') . '/'.Order::$path.'/'.$value->id.'/invoice-' . $value->id . '.pdf';
        }
        
        return view(
            'member.order', [
                'orders' => $orders,
                'user' => $user,
                'input' => $request->all(),
                'query_params' => Helper::query_params($request->input()),
                'status_options' => Order::$status,
            ]
        );
    }

    public function viewSummary($id)
    {
        $order = Order::select(
            "*",
            DB::raw(
                "CONCAT(
                    orders.billing_address, '<br />', 
                    orders.billing_city, '<br />', 
                    orders.billing_postcode, '<br />', 
                    orders.billing_state
                ) AS billing_address"
            ),
            DB::raw(
                "CONCAT(
                orders.shipping_address, '<br />', 
                orders.shipping_city, '<br />', 
                orders.shipping_postcode, '<br />', 
                orders.shipping_state
            ) AS shipping_address"
            ),
        )->where('id', decrypt($id))->first();

        $user = Auth::guard('web')->user();
        if ($user->id != $order->user_id) {
            abort(404);
        }

        $order_products = DB::table('order_products')
            ->select(
                'order_products.id as order_product_id', 
                'products.id as product_id', 
                'order_products.product_name as name', 
                'order_products.quantity', 
                'order_products.weight',
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
            $total += $order_products[$key]->unit_price * ($value->quantity ?? $value->weight);
        }

        // payment_method
        $payment_method = json_decode($user->payment_method, true);

        return view('member.order-summary', [
                'order' => $order,
                'invoice_url' => url('/') . '/'.Order::$path.'/'.$order->id.'/invoice-' . $order->id . '.pdf',
                'delivery_order_url' => url('/') . '/'.Order::$path.'/'.$order->id.'/delivery-order-' . $order->id . '.pdf',
                'delivery_order_download_url' => url('download/').Order::$path.'/'.$order->id.'/delivery-order-' . $order->id . '.pdf',
                'products' => $order_products,
                'total' => number_format($total, 2, '.', ''),
                'customer' => $user
            ]
        );
    }

    public function export(Request $request)
    {   
        $orders = Order::select("*")
            ->where('user_id', Auth::guard('web')->user()->id); 

        if ($filter_fdate = $request->input('fdate')) {
            $orders->where('created_at', '>=', $filter_fdate);
        }

        if ($filter_tdate = $request->input('tdate')) {
            $orders->where('created_at', '<=', $filter_tdate." 23:59:59");
        }

        if ($filter_status = $request->input('status')) {
            $orders->where('status', $filter_status);
        }
        
        $orders = $orders->get();

        foreach ($orders as $key => $order) {
            $data[] = [
                'no' => $key + 1,
                'created_at' => $order->created_at,
                'customer' => $order->customer->name,
                'price' => $order->total_price,
                'payment_method' => $order->payment_method ? __('user.payment_method.'.$order->payment_method) : '',
                'shipping_address' => $order->shipping_address .", ". $order->shipping_postcode .", ". $order->shipping_state,
                'status' => __('order.status.'.$order->status),
                'updated_at' => $order->updated_at,
            ];
        }
        $header = ['No', 'Order At', 'Customer', 'Total Price', 'Payment Method', 'Shipping Address', 'Status', 'Last Updated At']; // Adjust the header based on your data model

        return Excel::download(new MemberOrderExport(collect($data), $header), Carbon::now()->format('YmdHis').'-Order-List.xlsx');
    }
}
