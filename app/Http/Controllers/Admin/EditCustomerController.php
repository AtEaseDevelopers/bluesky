<?php

namespace App\Http\Controllers\Admin;

use App\Helper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\ProductVisibility;
use App\Services\CreditService;
use App\System;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class EditCustomerController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function showForm(Request $request, $id)
    {
        $customer = User::find(decrypt($id));
        // $category_list = User::select('category')
        //     ->groupBy('category')
        //     ->pluck('category')
        //     ->toArray();
        $category_list = DB::table('customer_categories')->select('id', 'category')->get()->toArray();


        $customer->payment_method = json_decode($customer->payment_method ?? "[]", true);
        $products = DB::table('products')->select('id', 'name', 'sku')->get()->toArray();
        $drivers = DB::table('drivers')->select('id', 'lorry_number')->get()->toArray();
        $areas = DB::table('areas')->select('id', 'area_name')->get()->toArray();

        $product_visibilities = DB::table('product_visibilities')
            ->join('products', 'products.id', '=', 'product_visibilities.product_id')
            ->select('product_id', 'products.name as product_name', 'product_visibilities.id')
            ->where('product_visibilities.user_id', $customer->id)
            ->where('products.status', '=', 'active')
            ->get()
            ->toArray();


        return view(
            'admin.customers.edit',
            [
                'customer' => $customer,
                'drivers' => $drivers,
                'areas' => $areas,
                'products' => $products,
                'product_visibilities' => $product_visibilities,
                'category_list' => $category_list,
                'payment_method_options' => User::$payment_method,
                'shipping_state_options' => System::$country_state['MY'],
                'credit_logs' => $customer->isCreditCustomer()
                    ? app(CreditService::class)->logsForCustomer($customer->id)
                    : collect(),
                'assigned_driver_ids' => $customer->assignedDriverIds(),
            ]
        );
    }

    public function editCustomer(Request $request, $id)
    {
        $customer = User::find(decrypt($id));

        $data = $this->validateEditCustomer($request, $customer);
        if (isset($data['error']) && $data['error']) {
            return redirect()->back()->withInput()->withErrors($data['field_err']);
        }

        $customer->fill(
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
                "area" => $request['area_id'],
                "billing_address" => $data['billing_address'],
                "billing_city" => $request['billing_city'] ?? null,
                "shipping_city" => $request['shipping_city'] ?? null,
                "billing_postcode" => $data['billing_postcode'] ?? '',
                "billing_state" => $data['billing_state'] ?? '',
                "shipping_address" => $data['shipping_address'] ?? "",
                "shipping_postcode" => $data['shipping_postcode'] ?? "",
                "shipping_state" => $data['shipping_state'] ?? "",
                "status" => User::$user_status['active'],
                "remark" => $data['remark'],
                "price_permission" => $request['price_permission'] ?? 0,
                "invoice_visibility" => $request['invoice_visibility'] ?? 0,
                "invoice_price_permission" => $request['invoice_price_permission'] ?? 0,
                "default_driver_id" => null,
                'sql_customer_code' => $request['sql_customer_code'] ?? null,
                "fax_no" => $data['fax_no'] ?? null,

            ]
        )->save();

        $customer->syncDrivers($request->input('driver_ids', []));

        if (($request->input('customer_type', 'cod') === 'cod')) {
            app(CreditService::class)->clearBalanceForCodCustomer(
                $customer->fresh(),
                auth('web_admin')->id()
            );
        }

        if ($request['product_id']) {
            foreach ($request['product_id'] as $pid) {
                ProductVisibility::updateOrCreate([
                    'user_id' => $customer->id,
                    'product_id' => $pid,
                ]);
            }
        }

        return redirect(route('admin.customers.edit', encrypt($customer->id)))->with('success', "$customer->name has been updated successfully.");
    }

    public function updatePassword(Request $request)
    {
        $customer = User::findorfail(decrypt($request['id']));
        $data = $this->validateUpdatePassword($request, $customer);
        if (isset($data['error']) && $data['error']) {
            return back()->withInput()->withErrors($data['field_err']);
        }

        // generate login code for specific user, unique for every user
        do {
            $login_code = Helper::generateRandomString(100);
            $exist = User::where('login_code', $login_code)->exists();
        } while ($exist);

        $customer->fill(
            [
                "password" => Hash::make($data['new_password']),
                "login_code" => $login_code,
            ]
        )->save();

        return redirect(route('admin.customers.edit', encrypt($customer->id)))->with('success', "$customer->name login password & fast-login link has been updated successfully.");

    }

    public function validateUpdatePassword(Request $request, User $customer)
    {
        $rules = [
            "new_password" => ['required', 'string', 'max:100'],
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

    public function validateEditCustomer(Request $request, User $customer)
    {
        $rules = [
            "name" => ['required', 'unique:users,name,' . $customer->id, 'string', 'max:100'],
            "email" => array_merge(User::$attribute_rules['email'], ['unique:users,email,' . $customer->id]),
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
            'shipping_address' => array_merge(User::$attribute_rules['shipping_address'], []),
            // "shipping_postcode" => array_merge(User::$attribute_rules['shipping_postcode'], []),
            'shipping_postcode' => ['nullable'],
            // "shipping_state" => array_merge(User::$attribute_rules['shipping_state'], []),
            'shipping_state' => ['nullable'],
            "remark" => array_merge(User::$attribute_rules['remark'], []),
            "fax_no" => array_merge(User::$attribute_rules['fax_no'], []),
            'driver_ids' => ['nullable', 'array'],
            'driver_ids.*' => ['exists:drivers,id'],
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

    public function generateNewLoginLink(Request $request, User $customer)
    {
        // generate login code for specific user, unique for every user
        do {
            $login_code = Helper::generateRandomString(100);
            $exist = User::where('login_code', $login_code)->exists();
        } while ($exist);

        $customer->fill(
            [
                "login_code" => $login_code,
            ]
        )->save();

        return redirect(route('admin.customers'))->with('success', "New login link has been generated for $customer->name.");
    }

    public function generateRegistrationLink(Request $request, User $customer)
    {
        if ($customer->hasCompletedRegistration()) {
            return redirect()->back()->with('error', 'This customer has already completed portal registration.');
        }

        $customer->generateRegistrationToken();

        $referer = $request->headers->get('referer', '');
        if (str_contains($referer, 'invite/success')) {
            return redirect()
                ->route('admin.customers.invite.success', encrypt($customer->id))
                ->with('success', 'A new registration link has been generated.');
        }

        return redirect(route('admin.customers.edit', encrypt($customer->id)))->with(
            'success',
            'A new registration link has been generated.'
        );
    }
}
