<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AdminOrderExport;
use App\Helper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Order;
use App\OrderProduct;
use App\OrderProductOption;
use App\Product;
use App\System;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EditOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function showForm($id)
    {
        $order = Order::find(decrypt($id));
        // payment_method
        $payment_method_options = User::$payment_method;
        foreach ($payment_method_options as $key => $value) {
            $payment_method_options[$key] = trans('user.payment_method.'.$value);
        }
        
        $order->transfer_slip_url = "";
        if ($order->payment_method == User::$payment_method['bank-transfer']) {
            $order->transfer_slip_url = url('/') . '/'.Order::$path.'/'.$order->id.'/'.$order->transfer_slip;
        }
        
        $order_products = DB::table('order_products')
            ->select(
                'order_products.id as order_product_id', 
                'products.id as product_id', 
                'order_products.product_name', 
                'order_products.quantity', 
                'order_products.weight', 
                'order_products.unit_price as price', 
                'order_products.price as total_price',
                'order_products.remark',
            )
            ->leftJoin('orders', 'orders.id', '=', 'order_products.order_id')
            ->leftJoin('products', 'products.id', '=', 'order_products.product_id')
            ->where('order_products.status', OrderProduct::$status['active'])
            ->where('orders.id', $order->id)
            ->get();

        $total = 0;
        foreach ($order_products as $key => $value) {
            $total += $order_products[$key]->price * $value->quantity;
            foreach (OrderProduct::getOption($value->order_product_id) as $itm_key => $itm_value) {
                $order_products[$key]->$itm_key = $itm_value;
            }
            $order_products[$key]->remark = $value->remark? : "";
            unset($order_products[$key]->order_product_id);
        }

        return view('admin.orders.edit', [
                'payment_method_options' => $payment_method_options? : [],
                'shipping_state_options' => System::$country_state['MY'],
                'customer' => $order->customer,
                'order' => $order,
                'products' => $order_products->toArray(),
                'areaList' => Helper::areaList(),
            ]
        );
    }
    
    public function editOrder(Request $request, $id)
    {
        $order = Order::find(decrypt($id));
        $data = $this->validateEditOrder($request, $order);
        if (isset($data['error']) && $data['error']) {
            return redirect()->back()->withInput()->withErrors($data['field_err']);
        }

        $user = User::find($order->user_id);
        $total = 0;
        $order->update(
            [
            "total_price" => $total,
            "attn_name" => $data['attn_name'],
            "attn_contact" => $data['attn_contact'],
            "payment_method" => $data['payment_method'] ?? null,
            "area" => $request['area'],
            "billing_address" => $data['billing_address'],
            "billing_city" => $request['billing_city'],
            "shipping_city" => $request['shipping_city'],
            "billing_postcode" => $data['billing_postcode'] ?? null,
            "billing_state" => $data['billing_state'] ?? null,
            "shipping_address" => $data['shipping_address'],
            "shipping_postcode" => $data['shipping_postcode'] ?? null,
            "shipping_state" => $data['shipping_state'] ?? null,
            ]
        );

        $image = null;
        if (isset($data['transfer_slip']) && $data['transfer_slip']) {
            do {
                $extension = $data['transfer_slip']->getClientOriginalExtension();
                $filename = time().rand().".".$extension;
                $path = Order::$path.'/'.$order->id;
            } while(Storage::disk('local')->exists($path."/".$filename));
            
            Storage::disk('local')->put($path."/".$filename, file_get_contents($data['transfer_slip']));
            $image = $filename;
        }

        if ($image) {
            $order->fill(
                [
                'transfer_slip' => $image
                ]
            )->save();
        }

        // process order product
        // remove all added product first, add back later
        OrderProduct::where('order_id', $order->id)->update(
            [
            'status' => OrderProduct::$status['removed']
            ]
        );

        foreach ($data['product_id'] as $key => $product_id) {
            $product = Product::find($product_id);
            
              
            $qtyWeight = 0;
            
            if($product->sell_in == "qty")
            {
                 $quantity = $data['quantity'][$key];
                 $qtyWeight = $quantity;
            }
            else
            {
                 $weight = $data['weight'][$key];
                 $qtyWeight = $weight;
            }
           
           
            $unit_price = Product::get_today_price($product->id, $user);
            $price = $unit_price * $qtyWeight;
            $order_product = OrderProduct::create(
                [
                "order_id" => $order->id,
                "product_id" => $product_id,
                "product_name" => $product->name,
                "quantity" => $quantity ?? null,
                "weight" => $weight ?? null,
                "unit_price" => $unit_price,
                "price" => $price,
                "remark" => $data['remark'][$key],
                "status" => OrderProduct::$status['active'],
                ]
            );
            
            if (isset($data['product_options'][$key]) && $data['product_options'][$key]) {
                foreach ($data['product_options'][$key] as $opt => $opt_itm) {
                    $order_product_option = OrderProductOption::create(
                        [
                        "order_product_id" => $order_product->id,
                        "option" => $opt,
                        "option_item" => $opt_itm,
                        "status" => OrderProductOption::$status['active'],
                        ]
                    );
                }
            }
            $total += $price;
        }

        $order->fill(
            [
            'total_price' => $total
            ]
        )->save();

        return redirect(route('admin.orders.summary', $order->id))->with('success', "Order has been edited!");

    }

    public function getOrderData(Request $request, Order $order)
    {
        $order->payment_method = json_encode([$order->payment_method]);
        return json_encode(
            [
            'success' => true,
            'order' => $order
            ]
        );
    }

    public function validateEditOrder(Request $request, Order $order)
    {
        if(in_array($order->status, [Order::$status['completed']])) {
            return [
                'error' => "Order cannot be edited.",
                'field_err' => [],
            ];
        }

        $rules = [
            "customer_id" => ['required'],
            "attn_name" => array_merge(Order::$attribute_rules['attn_name'], []),
            "attn_contact" => array_merge(Order::$attribute_rules['attn_contact'], []),
            // "payment_method" => array_merge(Order::$attribute_rules['payment_method'], []),
            "billing_address" => array_merge(Order::$attribute_rules['billing_address'], []),
            // "billing_postcode" => array_merge(Order::$attribute_rules['billing_postcode'], []),
            // "billing_state" => array_merge(Order::$attribute_rules['billing_state'], []),
            "shipping_address" => array_merge(Order::$attribute_rules['shipping_address'], []),
            "shipping_postcode" => array_merge(Order::$attribute_rules['shipping_postcode'], []),
            "shipping_state" => array_merge(Order::$attribute_rules['shipping_state'], []),
            "transfer_slip" => ['nullable', 'mimes:jpg,jpeg,png', 'max:4096'],
            "product_id" => ['array'],
            "product_options" => ['array'],
            "remark" => ['array'],
            "quantity" => ['array'],
            "weight" => ['array'],
        ];

        try {
            $data = $request->validate($rules);
        } catch (ValidationException $err) {
            return [
                'error' => $err->getMessage(),
                'field_err' => $err->validator->errors()->getMessages(),
            ];
        }

        return $data;
    }

    public function getCustomerData(Request $request, User $customer)
    {
        return json_encode(
            [
            'success' => true,
            'customer' => $customer
            ]
        );
    }

    public function getProducts(Request $request, User $customer)
    {
        $products = Product::where('status', Product::$status['active'])->get();
        $products_output = [];
        foreach ($products as $key => $value) {
            $image = json_decode($value->images, true);
            $products[$key]->original_price = $value->price;
            $products[$key]->price = Product::get_today_price($value->id, $customer);
            $products[$key]->image_url = url('/') . '/' . Product::$path."/".$value->id."/".$image[0];
            $products[$key]->product_option = Product::getOption($value->id, true);
            $products_output[$value->id] = $products[$key];
        }

        return json_encode(
            [
            'success' => true,
            'products' => $products_output
            ]
        );
    }
}
