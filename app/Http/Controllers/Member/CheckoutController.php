<?php

namespace App\Http\Controllers\Member;

use App\Cart;
use App\CartProduct;
use App\DeliverySlot;
use App\Helper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Order;
use App\OrderProduct;
use App\OrderProductOption;
use App\PdfHelper;
use App\Product;
use App\System;
use App\Services\OrderService;
use App\Services\CreditService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CheckoutController extends Controller
{
    public function __construct()
    {
        $this->middleware('web');
    }

    public function viewForm(Request $request, $buy_again = '')
    {
        if ($buy_again == Cart::$status['buy-again']) {
            $status = Cart::$status['buy-again'];
        } else {
            $status = Cart::$status['pending'];
        }

        $user = Auth::guard('web')->user();
        $cart_products = DB::table('cart_products')
            ->select(
                'cart_products.id as cart_product_id', 
                'products.id as product_id', 
                'products.images as images', 
                'products.name as name', 
                'cart_products.quantity', 
                'cart_products.weight',
                'cart_products.unit_price as original_unit_price', 
                'cart_products.price',
                'cart_products.remark'
            )
            ->leftJoin('carts', 'carts.id', '=', 'cart_products.cart_id')
            ->leftJoin('products', 'products.id', '=', 'cart_products.product_id')
            ->where('cart_products.status', CartProduct::$status['active'])
            ->where('carts.user_id', $user->id)
            ->where('carts.status', $status)
            ->get();
        
        if (!$cart_products->count()) {
            return redirect()->to('/products')->with('error', "Nothing to checkout in your cart!");
        }

        $total = 0;
        foreach ($cart_products as $key => $value) {
            if ($value->images != null) {
                $images = json_decode($value->images, true);
                $cart_products[$key]->image_url = url('/') . '/' . Product::$path."/".$value->product_id."/".$images[0];
            } else {
                $cart_products[$key]->image_url = '/assets/images/product-default.jpg';
            }
            $cart_products[$key]->options = CartProduct::getOption($value->cart_product_id);
            $cart_products[$key]->unit_price = Product::get_today_price($value->product_id, $user);
            $total += $cart_products[$key]->unit_price * ($value->quantity ?? $value->weight);
        }

        // payment_method
        $payment_method = json_decode($user->payment_method, true);

        return view('member.checkout', [
                'user' => $user,
                'products' => $cart_products,
                'payment_method' => $payment_method? : [],
                'total' => number_format($total, 2, '.', ''),
                'available_credit' => app(CreditService::class)->availableCredit($user),
                'shipping_state_options' => System::$country_state['MY'],
                'customer' => $user,
                'deliverySlots' => DeliverySlot::availableSlots(),
            ]
        );
    }

    public function checkout(Request $request, $buy_again = '')
    {
        $data = $this->validateCheckout($request);
        if (isset($data['error']) && $data['error']) {
            return redirect()->back()->withInput()->withErrors($data['field_err']);
        }

        if ($buy_again == Cart::$status['buy-again']) {
            $status = Cart::$status['buy-again'];
        } else {
            $status = Cart::$status['pending'];
        }

        $user = Auth::guard('web')->user();
        $cart_products = DB::table('cart_products')
            ->select(
                'cart_products.id as cart_product_id', 
                'products.id as product_id', 
                'products.name as name', 
                'cart_products.quantity', 
                'cart_products.weight',
                'cart_products.price',
                'cart_products.remark',
                'carts.id as cart_id'
            )
            ->leftJoin('carts', 'carts.id', '=', 'cart_products.cart_id')
            ->leftJoin('products', 'products.id', '=', 'cart_products.product_id')
            ->where('cart_products.status', CartProduct::$status['active'])
            ->where('carts.user_id', $user->id)
            ->where('carts.status', $status)
            ->get();
                    
        if (!$cart_products->count()) {
            return redirect()->to('/products')->with('error', "Something went wrong!");
        }
        $total = 0;
        foreach ($cart_products as $key => $value) {
            $cart_products[$key]->options = CartProduct::getOption($value->cart_product_id);
            $cart_products[$key]->unit_price = Product::get_today_price($value->product_id, $user);
            $total += $cart_products[$key]->unit_price * ($value->quantity ?? $value->weight);
        }

        $deliverySlot = DeliverySlot::findOrFail($data['delivery_slot_id']);
        if (!$deliverySlot->isAvailable()) {
            return redirect()->back()->withInput()->with('error', 'Selected delivery slot is no longer available.');
        }

        $cart = Cart::find($cart_products[0]->cart_id);
        $cart->update(
            [
                'status' => Cart::$status['completed'],
            ]
        );

        $order = Order::create(
            [
                "user_id" => $user->id,
                "order_type" => Order::$order_types['registered'],
                "cart_id" => $cart->id,
                "subtotal" => $total,
                "total_price" => $total,
                "delivery_fee" => 0,
                "attn_name" => $data['attn_name'],
                "attn_contact" => $data['attn_contact'],
                "billing_address" => $data['billing_address'],
                "shipping_address" => $data['shipping_address'] ?? "",
                "status" => Order::$status['pending'],
                "payment_status" => Order::$payment_status['unpaid'],
                "driver_id" => $user->default_driver_id,
                "delivery_slot_id" => $deliverySlot->id,
                "delivery_date" => $deliverySlot->slot_date,
                "delivery_time_slot" => $deliverySlot->time_label,
                "is_estimated" => true,
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
        foreach ($cart_products as $key => $value) {
            $product_weight = DB::table('products')->where('id', $value->product_id)->value('weight');

            $order_product = OrderProduct::create(
                [
                    "order_id" => $order->id,
                    "product_id" => $value->product_id,
                    "product_name" => $value->name,
                    "quantity" => $value->quantity ?? null,
                    "weight" => $value->weight ?? null,
                    'product_weight' => $product_weight,
                    "unit_price" => $value->unit_price,
                    "price" => $value->price,
                    "remark" => $value->remark,
                    "status" => OrderProduct::$status['active'],
                ]
            );
            if ($value->quantity != null) {
                $order_weight += $value->quantity * $product_weight;
            } else {
                $order_weight += $value->weight;
            }
            
            foreach ($value->options as $opt => $opt_itm) {
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

        $order->update(
            [
                'order_weight' => $order_weight
            ]
        );

        app(OrderService::class)->assignDoNumber($order);

        $creditApplied = app(CreditService::class)->applyAvailableCredit($order->fresh());
        $order = $order->fresh();

        if (($data['payment_timing'] ?? 'pay_later') === 'pay_now' && $user->isCreditCustomer()) {
            $balanceDue = $order->balanceDue();
            if ($balanceDue > 0 && isset($data['transfer_slip']) && $data['transfer_slip']) {
                app(OrderService::class)->submitCheckoutPrepayment(
                    $order,
                    $user,
                    $data['payment_method'] ?? 'bank-transfer',
                    $balanceDue,
                    $data['transfer_slip']
                );
            }
        }

        $message = "Thank you! Your order has been submitted and is pending review.";
        if ($creditApplied > 0) {
            $message .= ' RM ' . number_format($creditApplied, 2) . ' from your credit balance was applied.';
        }

        return redirect()->to('/order/summary/' . encrypt($order->id))->with('success', $message);
    }

    public function validateCheckout(Request $request)
    {
        $rules = [
            "attn_name" => array_merge(Order::$attribute_rules['attn_name'], []),
            "attn_contact" => array_merge(Order::$attribute_rules['attn_contact'], []),
            // "payment_method" => array_merge(Order::$attribute_rules['payment_method'], []),
            'payment_method' => ['nullable'],
            "billing_address" => array_merge(Order::$attribute_rules['billing_address'], []),
            // "billing_postcode" => array_merge(Order::$attribute_rules['billing_postcode'], []),
            'billing_postcoe' => ['nullable'],
            // "billing_state" => array_merge(Order::$attribute_rules['billing_state'], []),
            'billing_state' => ['nullable'],
            "shipping_address" => array_merge(Order::$attribute_rules['shipping_address'], []),
            // "shipping_postcode" => array_merge(Order::$attribute_rules['shipping_postcode'], []),
            'shipping_postcode' => ['nullable'],
            // "shipping_state" => array_merge(Order::$attribute_rules['shipping_state'], []),
            'shipping_state' => ['nullable'],
            "transfer_slip" => array_merge(Order::$attribute_rules['transfer_slip'], []),
            'delivery_slot_id' => ['required', 'exists:delivery_slots,id'],
            'payment_timing' => ['nullable', 'in:pay_now,pay_later'],
        ];

        try {
            $data = $request->validate($rules);
        } catch (ValidationException $err) {
            return [
                'error' => $err->getMessage(),
                'field_err' => $err->validator->errors()->getMessages(),
            ];
        }

        $user = $request->user('web');
        if (($data['payment_timing'] ?? 'pay_later') === 'pay_now' && $user && $user->isCreditCustomer()) {
            if (!$request->hasFile('transfer_slip')) {
                return [
                    'error' => true,
                    'field_err' => ['transfer_slip' => ['Transfer slip is required when paying now.']],
                ];
            }
            if (empty($data['payment_method'])) {
                return [
                    'error' => true,
                    'field_err' => ['payment_method' => ['Payment method is required when paying now.']],
                ];
            }
        }

        return $data;
    }
}
