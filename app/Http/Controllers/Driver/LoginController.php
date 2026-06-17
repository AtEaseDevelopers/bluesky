<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Show the driver login form.
     */
    public function showForm()
    {
        if (Auth::guard('web_driver')->check()) {
            return redirect(route('driver.orders.index'));
        }

        return view('driver.login');
    }

    /**
     * Handle a driver login attempt.
     */
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $credentials = [
            'username' => $data['username'],
            'password' => $data['password'],
            'is_active' => true,
        ];

        if (Auth::guard('web_driver')->attempt($credentials)) {
            $request->session()->regenerate();
            return redirect(route('driver.orders.index'));
        }

        return back()->with('error', 'Invalid username or password.')->withInput($request->only('username'));
    }

    /**
     * Log the driver out.
     */
    public function logout(Request $request)
    {
        Auth::guard('web_driver')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect(route('driver.login'));
    }
}
