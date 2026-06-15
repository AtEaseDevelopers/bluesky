<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\User;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    public function getForm(Request $request)
    {
        return view('login');
    }

    public function login(Request $request)
    {
        $data = $this->validateLogin($request);
        if (isset($data['error']) && $data['error']) {
            return redirect()->back()->withInput()->withErrors($data['field_err']);
        }

        $login_data = [
            'email' => $data['email'],
            'password' => $data['password'],
        ];

        if (Auth::guard('web')->attempt($login_data)) {
            return redirect(route('member.products'));
        } else {
            // Authentication failed
            return back()->with('error', 'Email or password is incorret')->withInput();
        }
    }

    public function validateLogin(Request $request)
    {
        $rules = [
            'email' => ['required', 'string'],
            'password' => ['required', 'string'],
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

    public function fastLogin(Request $request, $login_code)
    {
        try {
            // Attempt to decrypt the data
            $login_code = Crypt::decryptString($login_code);
        } catch (DecryptException $e) {
            return redirect()->to('/')->with(['error' => 'Invalid Login. Please contact us for more.']);
        }

        $user = User::where('login_code', $login_code)->first();  
        if ($user) {
            // Authentication successful for admin
            Auth::guard('web')->login($user); 
            return redirect(route('member.products'));
        } else {
            // Authentication failed
            return redirect()->to('/')->with(['error' => 'Account Not Found.']);
        }
    }

    public function logout(Request $request)
    {
        Auth::logout();
        Auth::guard('web')->logout();

        // Redirect to a specific page after logout
        return redirect('/');
    }
}
