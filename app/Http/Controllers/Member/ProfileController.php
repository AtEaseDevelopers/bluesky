<?php

namespace App\Http\Controllers\Member;

use App\Cart;
use App\CartProduct;
use App\Helper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Product;
use App\System;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class ProfileController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('web');
    }

    /**
     * Show the application edit product.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(Request $request)
    {
        $user = Auth::guard('web')->user();
        $user->payment_method = json_decode($user->payment_method??"[]", true);
        
        return view(
            'member.profile', [
                'customer' => $user,
            ]
        );
    }

    /**
     * edit function.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function updatePassword(Request $request)
    {
        $user = Auth::guard('web')->user();
        $data = $this->validateUpdatePassword($request, $user);
        
        if (isset($data['error']) && $data['error']) {
            return redirect()->back()->withInput()->withErrors($data['field_err']);
        }

        // generate login code for specific user, unique for every user
        do {
            $login_code = Helper::generateRandomString(100);
            $exist = User::where('login_code', $login_code)->exists();
        } while($exist);

        $user->fill(
            [
                "password" => Hash::make($data['password']),
                "login_code" => $login_code,
            ]
        )->save();

        return redirect(route('member.profile'))->with('success', "Login password & fast-login link has been updated.");
    }

    /**
     * Validate add validation.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function validateUpdatePassword(Request $request, User $user)
    {
        $rules = [
            "password" => ['required', 'min:6', 'string', 'max:100', 'confirmed'],
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
}
