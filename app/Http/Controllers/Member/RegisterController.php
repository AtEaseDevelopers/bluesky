<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;

class RegisterController extends Controller
{
    public function showForm(Request $request, $token)
    {
        $customer = $this->resolveCustomer($token);
        if (!$customer) {
            return redirect()->route('login')->with('error', 'This registration link is invalid or has expired.');
        }

        if ($customer->hasCompletedRegistration()) {
            return redirect()->route('login')->with('success', 'This invitation has already been used. Please log in.');
        }

        return view('member.register', [
            'customer' => $customer,
            'token' => $token,
        ]);
    }

    public function register(Request $request, $token)
    {
        $customer = $this->resolveCustomer($token);
        if (!$customer) {
            return redirect()->route('login')->with('error', 'This registration link is invalid or has expired.');
        }

        if ($customer->hasCompletedRegistration()) {
            return redirect()->route('login')->with('success', 'This invitation has already been used. Please log in.');
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', 'unique:users,name,' . $customer->id],
            'email' => ['required', 'email', 'max:100', 'unique:users,email,' . $customer->id],
            'attn_name' => ['nullable', 'string', 'max:30'],
            'attn_contact' => ['required', 'string', 'max:30'],
            'billing_address' => ['required', 'string', 'max:100'],
            'shipping_address' => ['nullable', 'string', 'max:100'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $loginCode = User::generateLoginCode();

        $customer->update([
            'name' => $data['name'],
            'email' => $data['email'],
            'attn_name' => $data['attn_name'] ?? null,
            'attn_contact' => $data['attn_contact'],
            'billing_address' => $data['billing_address'],
            'shipping_address' => $data['shipping_address'] ?? '',
            'password' => Hash::make($data['password']),
            'login_code' => $loginCode,
            'status' => User::$user_status['active'],
            'registration_token' => null,
            'registration_token_expires_at' => null,
            'registration_completed_at' => now(),
        ]);

        Auth::guard('web')->login($customer);

        return redirect()->route('member.products')->with('success', 'Welcome! Your customer account is ready.');
    }

    private function resolveCustomer(string $encryptedToken): ?User
    {
        try {
            $registrationToken = Crypt::decryptString($encryptedToken);
        } catch (DecryptException $e) {
            return null;
        }

        $customer = User::where('registration_token', $registrationToken)->first();
        if (!$customer || !$customer->registrationTokenValid()) {
            return null;
        }

        return $customer;
    }
}
