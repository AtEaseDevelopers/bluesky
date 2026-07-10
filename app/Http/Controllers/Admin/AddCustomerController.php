<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\User;
use App\CustomerOption;
use App\CustomerOptionItem;
use App\Helper;
use App\ProductVisibility;
use App\System;
use App\Product;
use App\CustomerCategory;
use App\CustomerCategoryProduct;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AddCustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function showForm()
    {
        // $category_list = User::select('category')
        //     ->groupBy('category')
        //     ->pluck('category')
        //     ->toArray();
        $category_list = DB::table('customer_categories')->select('id', 'category')->get()->toArray();


        $areas = DB::table('areas')->select('id', 'area_name')->get()->toArray();
        $products = DB::table('products')->select('id', 'name', 'sku')->get()->toArray();

        return view(
            'admin.customers.create',
            [
                'products' => $products,
                'areas' => $areas,
                'category_list' => $category_list,
                'payment_method_options' => User::$payment_method,
                'shipping_state_options' => System::$country_state['MY'],
            ]
        );
    }

    public function addCustomer(Request $request)
    {
        $data = $this->validateAddCustomer($request);

        if (isset($data['error']) && $data['error']) {
            return back()->withInput()->withErrors($data['field_err']);
        }

        // generate login code for specific user, unique for every user
        $login_code = User::generateLoginCode();
        $default_password = 'ecommerce123';
        $customer = User::create(
            [
                "name" => $data['name'],
                "email" => $data['email'] ?? null,
                "category" => $data['category'],
                "customer_type" => $request->input('customer_type', 'cod'),
                "payment_term_days" => $request->input('customer_type', 'cod') === 'credit'
                    ? (int) $request->input('payment_term_days', 30)
                    : null,
                "attn_name" => $data['attn_name'],
                "attn_contact" => $data['attn_contact'],
                "payment_method" => isset($data['payment_method']) ? json_encode($data['payment_method']) : null,
                "login_code" => $login_code,
                "area" => $request['area_id'],
                "billing_address" => $data['billing_address'],
                "billing_city" => $request['billing_city'] ?? null,
                "shipping_city" => $request['shipping_city'] ?? null,
                "billing_postcode" => $data['billing_postcode'] ?? '',
                "billing_state" => $data['billing_state'] ?? '',
                "shipping_address" => $data['shipping_address'] ?? '',
                "shipping_postcode" => $data['shipping_postcode'] ?? '',
                "shipping_state" => $data['shipping_state'] ?? '',
                "fax_no" => $data['fax_no'] ?? '',
                "password" => Hash::make($default_password),
                "status" => User::$user_status['active'],
                "remark" => $data['remark'],
                "price_permission" => $request['price_permission'] ?? 0,
                "invoice_visibility" => $request['invoice_visibility'] ?? 0,
                "invoice_price_permission" => $request['invoice_price_permission'] ?? 0,
                'sql_customer_code' => $request['sql_customer_code'] ?? null,
                'ssm' => $request['ssm'] ?? null,
                'tin_no' => $request['tin_no'] ?? null,
                'registration_completed_at' => now(),
            ]
        );

        if ($request['product_id']) {
            foreach ($request['product_id'] as $pid) {
                ProductVisibility::create([
                    'user_id' => $customer->id,
                    'product_id' => $pid,
                ]);
            }
        }

        return redirect(route('admin.customers'))->with(
            'success',
            "$customer->name has been added. Default login password is $default_password."
        );

    }

    public function validateAddCustomer(Request $request)
    {
        $rules = [
            "name" => array_merge(User::$attribute_rules['name'], []),
            "email" => array_merge(User::$attribute_rules['email'], ['unique:users,email']),
            "category" => array_merge(User::$attribute_rules['category'], []),
            "attn_name" => array_merge(User::$attribute_rules['attn_name'], []),
            "attn_contact" => array_merge(User::$attribute_rules['attn_contact'], []),
            // "payment_method" => array_merge(User::$attribute_rules['payment_method'], []),
            "payment_method" => ['nullable'],
            "billing_address" => array_merge(User::$attribute_rules['billing_address'], []),
            // "billing_postcode" => array_merge(User::$attribute_rules['billing_postcode'], []),
            'billing_postcode' => ['nullable'],
            // "billing_state" => array_merge(User::$attribute_rules['billing_state'], []),
            'billing_state' => ['nullable'],
            "shipping_address" => array_merge(User::$attribute_rules['shipping_address'], []),
            // "shipping_postcode" => array_merge(User::$attribute_rules['shipping_postcode'], []),
            'shipping_postcode' => ['nullable'],
            // "shipping_state" => array_merge(User::$attribute_rules['shipping_state'], []),
            'shipping_state' => ['nullable'],
            "remark" => array_merge(User::$attribute_rules['remark'], []),
            "fax_no" => array_merge(User::$attribute_rules['fax_no'], []),
            'ssm' => array_merge(User::$attribute_rules['ssm'], []),
            'tin_no' => array_merge(User::$attribute_rules['tin_no'], []),
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

    public function getProductsForCategory(Request $request)
    {
        $category = trim($request->input('category'));
        $categoryRecord = CustomerCategory::where('category', $category)->first();

        if (!$categoryRecord) {
            // Try case insensitive
            $categoryRecord = CustomerCategory::whereRaw('LOWER(category) = ?', [strtolower($category)])->first();
        }

        if (!$categoryRecord) {
            return response()->json(['success' => false, 'message' => 'Category not found', 'searched_category' => $category]);
        }

        $ccps = CustomerCategoryProduct::where('customer_category_id', $categoryRecord->id)->get();
        $products = $ccps->map(function ($ccp) {
            $product = $ccp->product;
            if ($product) {
                return [
                    'id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'price' => $product->price ?? 0,
                ];
            }
            return null;
        })->filter()->values();

        return response()->json([
            'success' => true,
            'products' => $products,
            'category_id' => $categoryRecord->id,
            'ccp_count' => $ccps->count(),
            'products_count' => $products->count()
        ]);
    }
}
