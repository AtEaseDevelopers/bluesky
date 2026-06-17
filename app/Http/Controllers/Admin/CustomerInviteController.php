<?php

namespace App\Http\Controllers\Admin;

use App\Helper;
use App\Http\Controllers\Controller;
use App\ProductVisibility;
use App\CustomerCategoryProduct;
use App\System;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerInviteController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function create()
    {
        return view('admin.customers.invite', [
            'category_list' => DB::table('customer_categories')->select('id', 'category')->get(),
            'areas' => DB::table('areas')->select('id', 'area_name')->get(),
            'drivers' => DB::table('drivers')->select('id', 'lorry_number', 'name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_type' => 'required|in:cod,credit',
            'category' => 'required|string|max:30',
            'area_id' => 'nullable|exists:areas,id',
            'default_driver_id' => 'nullable|exists:drivers,id',
            'remark' => 'nullable|string|max:500',
        ]);

        do {
            $loginCode = Helper::generateRandomString(100);
        } while (User::where('login_code', $loginCode)->exists());

        do {
            $placeholderName = 'Invite-' . strtoupper(Str::random(8));
        } while (User::where('name', $placeholderName)->exists());

        $paymentMethods = $data['customer_type'] === 'credit'
            ? json_encode(['term'])
            : json_encode(['cod']);

        $customer = User::create([
            'name' => $placeholderName,
            'email' => null,
            'password' => Hash::make(Str::random(64)),
            'login_code' => $loginCode,
            'category' => $data['category'],
            'customer_type' => $data['customer_type'],
            'credit_balance' => 0,
            'payment_method' => $paymentMethods,
            'area' => $data['area_id'] ?? null,
            'default_driver_id' => $data['default_driver_id'] ?? null,
            'billing_address' => 'Pending registration',
            'shipping_address' => '',
            'status' => User::$user_status['locked'],
            'remark' => $data['remark'] ?? null,
            'price_permission' => true,
            'invoice_visibility' => true,
            'invoice_price_permission' => true,
        ]);

        $categoryRecord = DB::table('customer_categories')->where('category', $data['category'])->first();
        if ($categoryRecord) {
            $productIds = CustomerCategoryProduct::where('customer_category_id', $categoryRecord->id)
                ->pluck('product_id');
            foreach ($productIds as $productId) {
                ProductVisibility::create([
                    'user_id' => $customer->id,
                    'product_id' => $productId,
                ]);
            }
        }

        $customer->generateRegistrationToken();

        return redirect()
            ->route('admin.customers.invite.success', encrypt($customer->id))
            ->with('success', 'Registration link created. Send it to your new customer.');
    }

    public function success($customerId)
    {
        $customer = User::findOrFail(decrypt($customerId));

        if ($customer->hasCompletedRegistration()) {
            return redirect()->route('admin.customers.edit', $customerId);
        }

        return view('admin.customers.invite-success', [
            'customer' => $customer,
            'registrationUrl' => $customer->registrationUrl(),
        ]);
    }
}
