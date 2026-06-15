<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        // $this->middleware('auth');
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function showForm()
    {
        if (Auth::check()) {
            return redirect(route('admin.dashboard'));
        }

        return view('admin.login');
    }

    /**
     * Login function.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function login(Request $request)
    {
        $data = $this->validateLogin($request);
        if (isset($data['error']) && $data['error']){
            return back()->withInput()->withErrors($data['field_err']);
        }

        $login_data = [
            'username' => $data['username'],
            'password' => $data['password'],
        ];
        if (Auth::attempt($login_data)) {
            // Authentication successful for admin
            return redirect(route('admin.dashboard'));
        } else {
            // Authentication failed
            return back()->with('error', 'Account Not Found.')->withInput();
        }
    }

    /**
     * Validate Login validation.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function validateLogin(Request $request)
    {
        $rules = [
            'username' => ['required', 'string'],
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

    /**
     * Login function.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function logout(Request $request)
    {
        Auth::logout();

        // Redirect to a specific page after logout
        return redirect('/login');
    }
}
