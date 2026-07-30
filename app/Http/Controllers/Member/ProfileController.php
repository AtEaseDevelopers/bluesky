<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Order;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('web');
    }

    public function index(Request $request)
    {
        $user = Auth::guard('web')->user();
        $paymentMethods = json_decode($user->payment_method ?? '[]', true);

        return view('member.profile', [
            'customer' => $user,
            'paymentMethods' => is_array($paymentMethods) ? $paymentMethods : [],
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = Auth::guard('web')->user();
        $data = $this->validateUpdateProfile($request, $user);

        if (isset($data['error']) && $data['error']) {
            return redirect()->back()->withInput()->withErrors($data['field_err']);
        }

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'attn_name' => $data['attn_name'] ?? null,
            'attn_contact' => $data['attn_contact'] ?? null,
            'contact_method' => $data['contact_method'] ?? null,
            'wechat_id' => ($data['contact_method'] ?? null) === Order::$contact_methods['wechat']
                ? ($data['wechat_id'] ?? null)
                : null,
            'billing_address' => $data['billing_address'],
            'shipping_address' => $data['shipping_address'] ?? null,
        ])->save();

        return redirect()->route('member.profile')->with('success', __('user.profile.updated'));
    }

    public function updatePassword(Request $request)
    {
        $user = Auth::guard('web')->user();
        $data = $this->validateUpdatePassword($request, $user);

        if (isset($data['error']) && $data['error']) {
            return redirect()->back()->withInput()->withErrors($data['field_err']);
        }

        $user->fill([
            'password' => Hash::make($data['password']),
            'login_code' => User::generateLoginCode(),
        ])->save();

        return redirect()->route('member.profile')->with('success', __('user.profile.password_updated'));
    }

    public function validateUpdateProfile(Request $request, User $user): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:100', Rule::unique('users', 'name')->ignore($user->id)],
            'email' => ['nullable', 'email', 'max:100', Rule::unique('users', 'email')->ignore($user->id)],
            'attn_name' => ['nullable', 'string', 'max:100'],
            'attn_contact' => ['nullable', 'string', 'max:100'],
            'contact_method' => ['nullable', Rule::in(array_values(Order::$contact_methods))],
            'wechat_id' => ['nullable', 'string', 'max:100', 'required_if:contact_method,wechat'],
            'billing_address' => ['required', 'string', 'max:500'],
            'shipping_address' => ['nullable', 'string', 'max:500'],
        ];

        try {
            return $request->validate($rules);
        } catch (ValidationException $err) {
            return [
                'error' => $err->getMessage(),
                'field_err' => $err->validator->errors()->getMessages(),
            ];
        }
    }

    public function validateUpdatePassword(Request $request, User $user): array
    {
        $rules = [
            'password' => ['required', 'min:6', 'string', 'max:100', 'confirmed'],
        ];

        try {
            return $request->validate($rules);
        } catch (ValidationException $err) {
            return [
                'error' => $err->getMessage(),
                'field_err' => $err->validator->errors()->getMessages(),
            ];
        }
    }
}
