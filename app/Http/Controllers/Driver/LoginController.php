<?php

namespace App\Http\Controllers\Driver;

use App\Driver;
use App\Http\Controllers\Controller;
use App\Services\LocaleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
    public function login(Request $request, LocaleService $localeService)
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $driver = Driver::where('username', $data['username'])->first();

        if (!$driver || !Hash::check($data['password'], $driver->password)) {
            return back()->with('error', 'Invalid username or password.')->withInput($request->only('username'));
        }

        if (!$driver->is_active) {
            return back()->with('error', 'Your driver account is inactive. Please contact your administrator.')->withInput($request->only('username'));
        }

        Auth::guard('web_driver')->login($driver);
        $request->session()->regenerate();
        $localeService->syncSessionFromUser($driver);

        return redirect(route('driver.orders.index'));
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
