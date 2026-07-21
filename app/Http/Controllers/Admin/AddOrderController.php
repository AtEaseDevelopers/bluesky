<?php

namespace App\Http\Controllers\Admin;

use App\Area;
use App\DeliverySlot;
use App\Driver;
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
use App\Services\OrderService;
use App\Services\CreditService;

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
                'areas' => Area::optionsForSelect(),
                'drivers' => Driver::optionsForSelect(),
                'deliveryDates' => DeliverySlot::availableDates(),
                'deliverySlotsUrl' => route('admin.delivery-slots.for-date'),
            ]
        );
    }
    
    public function addOrder(Request $request)
    {
        $data = $this->validateAddOrder($request);
        if (isset($data['error']) && $data['error']) {
            return redirect()->back()->withInput()->withErrors($data['field_err']);
        }

        $isWalkIn = $request->boolean('is_walk_in');

        if ($isWalkIn) {
            $request->validate([
                'walk_in_name' => 'required|string|max:100',
                'walk_in_phone' => 'nullable|string|max:30',
                'billing_address' => 'nullable|string|max:200',
                'shipping_address' => 'nullable|string|max:200',
            ]);
            $user = null;
            $data['customer_id'] = null;
        } else {
            $user = User::find($data['customer_id']);
        }

        $allowedPaymentMethods = $isWalkIn
            ? User::walkInOrderPaymentMethodKeys()
            : User::adminOrderPaymentMethodKeys($user);

        if ($request->filled('payment_method') && !in_array($request->input('payment_method'), $allowedPaymentMethods, true)) {
            return redirect()->back()->withInput()->withErrors([
                'payment_method' => __('orders.invalid_payment_method'),
            ]);
        }

        $total = 0;

        $orderData = [
            "order_type" => $isWalkIn ? Order::$order_types['walk_in'] : Order::$order_types['registered'],
            "user_id" => $isWalkIn ? null : $user->id,
            "walk_in_name" => $isWalkIn ? $request->input('walk_in_name') : null,
            "walk_in_phone" => $isWalkIn ? $request->input('walk_in_phone') : null,
            "total_price" => $total,
            "subtotal" => $total,
            "delivery_fee" => 0,
            "attn_name" => $data['attn_name'],
            "attn_contact" => $data['attn_contact'],
            "payment_method" => $data['payment_method'] ?? null,
            "area" => Area::orderStorageValue($request->input('area')),
            "billing_address" => $data['billing_address'],
            "billing_city" => $request['billing_city'] ?? null,
            "shipping_city" => $request['shipping_city'],
            "billing_postcode" => $data['billing_postcode'] ?? null,
            "billing_state" => $data['billing_state'] ?? null,
            "shipping_address" => $data['shipping_address'],
            "shipping_postcode" => $data['shipping_postcode'] ?? null,
            "shipping_state" => $data['shipping_state'] ?? null,
            "status" => Order::$status['pending'],
            "payment_status" => Order::$payment_status['unpaid'],
            "driver_id" => $isWalkIn
                ? null
                : ($request->input('fulfillment_type') === Order::$fulfillment_types['pickup']
                    ? null
                    : ($request->input('driver_id') ?: null)),
            "fulfillment_type" => $isWalkIn
                ? Order::$fulfillment_types['pickup']
                : $request->input('fulfillment_type', Order::$fulfillment_types['delivery']),
            "is_estimated" => true,
        ];

        if ($request->filled('delivery_slot_id') && $request->filled('delivery_date')
            && ($data['payment_method'] ?? null) !== User::$payment_method['in-store']) {
            $slot = DeliverySlot::find($request->input('delivery_slot_id'));
            if ($slot && $slot->isAvailableForDate($request->input('delivery_date'))) {
                $orderData['delivery_slot_id'] = $slot->id;
                $orderData['delivery_date'] = $request->input('delivery_date');
                $orderData['delivery_time_slot'] = $slot->time_label;
            }
        } else {
            $orderData['fulfillment_type'] = Order::$fulfillment_types['pickup'];
            $orderData['driver_id'] = null;
        }

        $order = Order::create($orderData);

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

            if (in_array($product->sell_in, [Product::SELL_IN_WEIGHT, Product::SELL_IN_QTY_BILL_WEIGHT], true)) {
                $rawWeight = $data['weight'][$key] ?? null;
                $line = $product->resolveLineInputs(
                    (float) ($data['quantity'][$key] ?? 0),
                    ($rawWeight !== null && $rawWeight !== '') ? (float) $rawWeight : null,
                    true
                );
            } else {
                $line = $product->resolveLineInputs((float) ($data['quantity'][$key] ?? 0), null);
            }

            $unit_price = Product::get_today_price($product->id, $user);
            $price = $unit_price * $line['bill_amount'];
            $order_product = OrderProduct::create(
                [
                    "order_id" => $order->id,
                    "product_id" => $product_id,
                    "product_name" => $product->name,
                    "quantity" => $line['quantity'],
                    "weight" => $line['weight'],
                    "product_weight" => $line['product_weight'],
                    "unit_price" => $unit_price,
                    "price" => $price,
                    "remark" => $data['remark'][$key],
                    'nos' => null,
                    "status" => OrderProduct::$status['active'],
                ]
            );
            $order_weight += $line['order_weight'];
            
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
                'subtotal' => $total,
                'total_price' => $total,
                'order_weight' => $order_weight
            ]
        )->save();

        app(OrderService::class)->assignDoNumber($order);

        if (!$isWalkIn && $user && $order->shouldAutoApplyCredit()) {
            app(CreditService::class)->applyAvailableCredit($order->fresh());
        }

        app(OrderService::class)->applyDefaultPaymentDueDate($order->fresh());

        if ($request->input('generate_qr') === '1' && $order->fresh()->balanceDue() > 0) {
            try {
                app(\App\Services\RevenueMonster\OrderQrPaymentService::class)->createOrReuse($order->fresh());

                return redirect(route('admin.orders.qr', $order->id))->with('success', __('orders.created_success'));
            } catch (\App\Services\RevenueMonster\Exceptions\RevenueMonsterException $e) {
                return redirect(route('admin.orders.summary', $order->id))
                    ->with('success', __('orders.created_success'))
                    ->with('error', __('orders.qr.failed'));
            }
        }

        return redirect(route('admin.orders.summary', $order->id))->with('success', __('orders.created_success'));
    }

    public function validateAddOrder(Request $request)
    {
        $rules = [
            "customer_id" => [$request->boolean('is_walk_in') ? 'nullable' : 'required'],
            "walk_in_name" => ['nullable', 'string', 'max:100'],
            "walk_in_phone" => ['nullable', 'string', 'max:30'],
            "attn_name" => array_merge(Order::$attribute_rules['attn_name'], []),
            "attn_contact" => array_merge(Order::$attribute_rules['attn_contact'], []),
            // "payment_method" => array_merge(Order::$attribute_rules['payment_method'], []),
            'payment_method' => ['nullable'],
            "billing_address" => $request->boolean('is_walk_in')
                ? ['nullable', 'string', 'max:200']
                : array_merge(Order::$attribute_rules['billing_address'], []),
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

        if (!$customer) {
            return response()->json(['success' => false], 404);
        }

        return response()->json([
            'success' => true,
            'customer' => $customer,
            'order_payment_methods' => User::adminOrderPaymentMethodLabels($customer),
        ]);
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
