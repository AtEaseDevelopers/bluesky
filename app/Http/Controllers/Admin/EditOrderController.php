<?php

namespace App\Http\Controllers\Admin;

use App\Area;
use App\Exports\AdminOrderExport;
use App\Helper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Order;
use App\OrderProduct;
use App\OrderProductOption;
use App\Product;
use App\Services\CreditService;
use App\Services\OrderService;
use App\System;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
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

        if (!$order->canAdminEditOrder()) {
            return redirect(route('admin.orders.summary', $order->id))
                ->with('error', __('orders.cannot_edit'));
        }
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
                'customers_list' => User::all(),
                'walk_in_payment_method_keys' => User::walkInOrderPaymentMethodKeys(),
                'order' => $order,
                'products' => $order_products->toArray(),
                'areas' => Area::optionsForSelect(),
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

        // Remember who the order belonged to so a customer change can move its
        // credit ledger impact from the old customer to the new one.
        $previousUserId = $order->user_id;

        // The admin picks the type first, then the customer. Crossing is allowed:
        // a registered order can become a walk-in and a walk-in can be assigned to
        // a registered account.
        $isWalkIn = $request->boolean('is_walk_in');

        if ($isWalkIn) {
            $user = null;
        } else {
            $customerId = $request->input('customer_id') ?: $request->input('customer');
            $user = User::find($customerId);
            if (!$user) {
                return redirect()->back()->withInput()->withErrors([
                    'customer_id' => __('orders.customer_required'),
                ]);
            }
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
        $order->update(
            [
            "order_type" => $isWalkIn ? Order::$order_types['walk_in'] : Order::$order_types['registered'],
            "user_id" => $isWalkIn ? null : $user->id,
            "walk_in_name" => $isWalkIn ? $request->input('walk_in_name') : null,
            "walk_in_phone" => $isWalkIn ? $request->input('walk_in_phone') : null,
            "total_price" => $total,
            "attn_name" => $data['attn_name'],
            "attn_contact" => $data['attn_contact'],
            "payment_method" => $data['payment_method'] ?? null,
            "area" => Area::orderStorageValue($request->input('area')),
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

        foreach (($data['product_id'] ?? []) as $key => $product_id) {
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
                "product_name" => Product::orderLineName($product),
                "quantity" => $line['quantity'],
                "weight" => $line['weight'],
                "product_weight" => $line['product_weight'],
                "unit_price" => $unit_price,
                "price" => $price,
                "remark" => $data['remark'][$key] ?? '',
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

        $this->syncCreditForReassignment($order->fresh(), $previousUserId);

        return redirect(route('admin.orders.summary', $order->id))->with('success', __('orders.edited_success'));

    }

    /**
     * When an order is reassigned to a different customer, move its credit
     * ledger impact: restore the previous customer and transfer the credit-term
     * charge to the newly assigned customer, then re-apply available credit /
     * a payment due date to that customer.
     */
    private function syncCreditForReassignment(Order $order, ?int $previousUserId): void
    {
        if ((int) $previousUserId === (int) $order->user_id) {
            return; // Customer unchanged — nothing to move.
        }

        $adminId = Auth::guard('web_admin')->id();
        $creditService = app(CreditService::class);

        $previousCustomer = $previousUserId ? User::find($previousUserId) : null;
        $newCustomer = $order->user_id ? User::find($order->user_id) : null;

        $creditService->reassignOrderCredit($order, $previousCustomer, $newCustomer, $adminId);

        if ($newCustomer) {
            if ($order->fresh()->shouldAutoApplyCredit()) {
                $creditService->applyAvailableCredit($order->fresh());
            }
            app(OrderService::class)->applyDefaultPaymentDueDate($order->fresh());
        }
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

    /**
     * Distinct walk-in customers seen on past walk-in orders (name + phone), so
     * the admin can reuse an existing walk-in record instead of retyping it.
     */
    public function searchWalkIns(Request $request)
    {
        $term = trim((string) $request->input('q', ''));

        $query = Order::query()
            ->where('order_type', Order::$order_types['walk_in'])
            ->whereNotNull('walk_in_name')
            ->where('walk_in_name', '!=', '');

        if ($term !== '') {
            $pattern = Helper::likePattern($term);
            $query->where(function ($q) use ($pattern) {
                $q->where('walk_in_name', 'like', $pattern)
                    ->orWhere('walk_in_phone', 'like', $pattern);
            });
        }

        $results = $query->orderByDesc('id')
            ->get(['walk_in_name', 'walk_in_phone'])
            ->unique(fn ($order) => mb_strtolower(trim($order->walk_in_name)) . '|' . trim((string) $order->walk_in_phone))
            ->take(20)
            ->map(fn ($order) => [
                'name' => $order->walk_in_name,
                'phone' => $order->walk_in_phone ?? '',
            ])
            ->values();

        return response()->json([
            'success' => true,
            'results' => $results,
        ]);
    }

    public function validateEditOrder(Request $request, Order $order)
    {
        if (!$order->canAdminEditOrder()) {
            return [
                'error' => "Order cannot be edited.",
                'field_err' => [],
            ];
        }

        $isWalkIn = $request->boolean('is_walk_in');

        $rules = [
            // The select2 dropdown is disabled at submit (product step), so the
            // JS-synced hidden customer_id is what actually posts.
            "customer_id" => [$isWalkIn ? 'nullable' : 'required'],
            "customer" => ['nullable'],
            "walk_in_name" => [$isWalkIn ? 'required' : 'nullable', 'string', 'max:100'],
            "walk_in_phone" => ['nullable', 'string', 'max:30'],
            "attn_name" => array_merge(Order::$attribute_rules['attn_name'], []),
            "attn_contact" => array_merge(Order::$attribute_rules['attn_contact'], []),
            "payment_method" => ['nullable', 'string', 'max:30'],
            // A walk-in counter sale needs no billing address; a registered order does.
            "billing_address" => $isWalkIn
                ? ['nullable', 'string', 'max:200']
                : array_merge(Order::$attribute_rules['billing_address'], []),
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
