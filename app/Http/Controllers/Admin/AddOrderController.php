<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Exports\AdminOrderExport;
use Illuminate\Http\Request;
use App\OrderProductOption;
use App\OrderProduct;
use Carbon\Carbon;
use App\Product;
use App\System;
use App\Helper;
use App\Order;
use App\User;

class AddOrderController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function showForm(Request $request)
    {
        // payment_method
        $payment_method_options = User::$payment_method;
        foreach ($payment_method_options as $key => $value) {
            $payment_method_options[$key] = trans('user.payment_method.'.$value);
        }
        
        return view('admin.orders.create', [
                'payment_method_options' => $payment_method_options? : [],
                'shipping_state_options' => System::$country_state['MY'],
                'customers_list' => User::all(),
                'areaList' => Helper::areaList(),
            ]
        );
    }
    
    public function addOrder(Request $request)
    {
        $data = $this->validateAddOrder($request);
        if (isset($data['error']) && $data['error']) {
            return redirect()->back()->withInput()->withErrors($data['field_err']);
        }

        $user = User::find($data['customer_id']);
        $total = 0;
        $order = Order::create(
            [
                "user_id" => $user->id,
                "total_price" => $total,
                "attn_name" => $data['attn_name'],
                "attn_contact" => $data['attn_contact'],
                "payment_method" => $data['payment_method'] ?? null,
                "area" => $request['area'],
                "billing_address" => $data['billing_address'],
                "billing_city" => $request['billing_city'] ?? null,
                "shipping_city" => $request['shipping_city'],
                "billing_postcode" => $data['billing_postcode'] ?? null,
                "billing_state" => $data['billing_state'] ?? null,
                "shipping_address" => $data['shipping_address'],
                "shipping_postcode" => $data['shipping_postcode'] ?? null,
                "shipping_state" => $data['shipping_state'] ?? null,
                "status" => Order::$status['processing'],
                "driver_id" => $user->default_driver_id,
            ]
        );

        $image = null;
        if (isset($data['transfer_slip']) && $data['transfer_slip']) {
            do {
                $extension = $data['transfer_slip']->getClientOriginalExtension();
                $filename = time().rand().".".$extension;
                $path = Order::$path.'/'.$order->id;
            } while (Storage::disk('local')->exists($path."/".$filename));
            
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

        $order_weight = 0;
        // process order product
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
                 $weight = $data['quantity'][$key];
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
                    "weight" => $weight ?? null, //$product->weight == '' ? 0 : $product->weight * $quantity,
                    "unit_price" => $unit_price,
                    "price" => $price,
                    "remark" => $data['remark'][$key],
                    'nos' => null,
                    "status" => OrderProduct::$status['active'],
                ]
            );
            $order_weight += ($product->weight == '' ? 0 : $product->weight * $quantity);
            
            if (isset($data['product_options'][$key]) && $data['product_options'][$key]) {
                foreach ($data['product_options'][$key] as $opt => $opt_itm) {
                    if ($opt_itm) {
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
            }
            $total += $price;
        }

        $order->fill(
            [
                'total_price' => $total,
                'order_weight' => $order_weight
            ]
        )->save();

        $this->generateDoNumber($order);

        return redirect(route('admin.orders.summary', $order->id))->with('success', "Order has been created!");
    }

    public function validateAddOrder(Request $request)
    {
        $rules = [
            "customer_id" => ['required'],
            "attn_name" => array_merge(Order::$attribute_rules['attn_name'], []),
            "attn_contact" => array_merge(Order::$attribute_rules['attn_contact'], []),
            // "payment_method" => array_merge(Order::$attribute_rules['payment_method'], []),
            'payment_method' => ['nullable'],
            "billing_address" => array_merge(Order::$attribute_rules['billing_address'], []),
            // "billing_postcode" => array_merge(Order::$attribute_rules['billing_postcode'], []),
            'billing_postcode' => ['nullable'],
            // "billing_state" => array_merge(Order::$attribute_rules['billing_state'], []),
            'billing_state' => ['nullable'],
            "shipping_address" => array_merge(Order::$attribute_rules['shipping_address'], []),
            // "shipping_postcode" => array_merge(Order::$attribute_rules['shipping_postcode'], []),
            'shipping_postcode' => ['nullable'],
            // "shipping_state" => array_merge(Order::$attribute_rules['shipping_state'], []),
            'shipping_state' => ['nullable'],
            // "transfer_slip" => array_merge(Order::$attribute_rules['transfer_slip'], []),
            'transfer_slip' => ['nullable'],
            "product_id" => ['array'],
            "product_options" => ['array'],
            "remark" => ['array'],
            'nos' => ['array'],
            "quantity" => ['array'],
            "weight" => ['array']
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

    public function getCustomerData(Request $request)
    {
        $customer = User::find($request['id']);
        return response()->json(
            [
                'success' => true,
                'customer' => $customer
            ]
        );
    }

    public function getProducts(Request $request, $id)
    {
        if ($id != 'products_visibility') {
            $customer = User::find($id);
        }

        $products = Product::select('id', 'name', 'sku', 'price', 'images')
            ->where('status', Product::$status['active'])
            ->get();

        $products_output = [];
        foreach ($products as $key => $value) {
            $image = json_decode($value->images, true);
            $products[$key]->original_price = $value->price;
            if ($id != 'products_visibility') {
                $products[$key]->price = Product::get_today_price($value->id, $customer);
            }
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

    /**
     * Common method to generate DO number
     */
    private static function generateDoNumber($order)
    {
        $prefix = 'DO' . now()->format('ym');
        $latest = Order::where('do_no', 'like', $prefix . '%')
            ->orderBy('do_no', 'desc')
            ->first();

        $do_no_idx = $latest ? ((int) substr($latest->do_no, strlen($prefix)) + 1) : 1;
        $ending_digit = sprintf('%04d', $do_no_idx);

        $order->do_no = $prefix . $ending_digit;
        $order->save();
    }
}
