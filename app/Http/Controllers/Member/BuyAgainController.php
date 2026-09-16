<?php

namespace App\Http\Controllers\Member;

use App\Cart;
use App\CartProduct;
use App\CartProductOption;
use App\Helper;
use Illuminate\Http\Request;
use App\Exports\MemberOrderExport;

use App\Http\Controllers\Controller;
use App\Order;
use App\OrderProduct;
use App\OrderProductOption;
use App\Product;
use App\System;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

class BuyAgainController extends Controller
{
    public function __construct()
    {
        $this->middleware('web');
    }

    public function index($id)
    {
        $user = Auth::guard('web')->user();
        $order = Order::where('id', decrypt($id))
            ->where('user_id', $user->id)
            ->firstOrFail();

        Cart::where('user_id', $user->id)
            ->where('status', Cart::$status['buy-again'])
            ->update(['status' => Cart::$status['aborted']]);

        $newCart = Cart::create([
            'user_id' => $user->id,
            'replicated_id' => $order->cart_id,
            'status' => Cart::$status['buy-again'],
        ]);

        $orderProducts = OrderProduct::where('status', OrderProduct::$status['active'])
            ->where('order_id', $order->id)
            ->get();

        $addedCount = 0;

        foreach ($orderProducts as $prevOrderProduct) {
            $product = Product::query()
                ->memberCatalog($user)
                ->where('products.id', $prevOrderProduct->product_id)
                ->first();

            if (!$product) {
                continue;
            }

            $quantity = ($prevOrderProduct->quantity !== null && $prevOrderProduct->quantity !== '')
                ? (float) $prevOrderProduct->quantity
                : null;
            $weight = ($prevOrderProduct->weight !== null && $prevOrderProduct->weight !== '')
                ? (float) $prevOrderProduct->weight
                : null;
            $unitPrice = Product::get_today_price($product->id, $user);
            $linePrice = $product->calculateLinePrice($unitPrice, $quantity, $weight);

            $newCartProduct = CartProduct::create([
                'cart_id' => $newCart->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'weight' => $weight,
                'unit_price' => $unitPrice,
                'price' => $linePrice,
                'remark' => $prevOrderProduct->remark,
                'status' => CartProduct::$status['active'],
            ]);

            $previousOptions = OrderProductOption::where('status', OrderProductOption::$status['active'])
                ->where('order_product_id', $prevOrderProduct->id)
                ->get();

            $productOptions = Product::getOption($product->id, true)['product_option'] ?? [];

            foreach ($previousOptions as $prevOption) {
                if (
                    isset($productOptions[$prevOption->option])
                    && in_array($prevOption->option_item, $productOptions[$prevOption->option], true)
                ) {
                    CartProductOption::create([
                        'cart_product_id' => $newCartProduct->id,
                        'option' => $prevOption->option,
                        'option_item' => $prevOption->option_item,
                        'status' => CartProductOption::$status['active'],
                    ]);
                }
            }

            $addedCount++;
        }

        if ($addedCount === 0) {
            $newCart->update(['status' => Cart::$status['aborted']]);

            return redirect()->route('member.orders')
                ->with('error', __('orders.member.buy_again_unavailable'));
        }

        return redirect('/checkout/buy-again');
    }

    public function viewSummary(Request $request, Order $order)
    {
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
            $total += $order_products[$key]->unit_price * $value->quantity;
        }

        // payment_method
        $payment_method = json_decode($user->payment_method, true);

        return view(
            'member.order-summary', [
            'order' => $order,
            'invoice_url' => url('/') . '/'.Order::$path.'/'.$order->id.'/invoice-' . $order->id . '.pdf',
            'delivery_order_url' => url('/') . '/'.Order::$path.'/'.$order->id.'/delivery-order-' . $order->id . '.pdf',
            'products' => $order_products,
            'total' => number_format($total, 2, '.', ''),
            'customer' => $user
            ]
        );
    }

    public function export(Request $request)
    {   
        $user = Auth::guard('web')->user();
        $orders = Order::select("*")
            ->where('user_id', $user->id); 

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
