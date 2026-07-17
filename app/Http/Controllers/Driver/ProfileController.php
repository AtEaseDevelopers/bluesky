<?php

namespace App\Http\Controllers\Driver;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function index()
    {
        return view('driver.profile', [
            'driver' => Auth::guard('web_driver')->user(),
        ]);
    }

    public function updatePassword(Request $request)
    {
        $driver = Auth::guard('web_driver')->user();

        $data = $request->validate([
            'old_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ]);

        if (!Hash::check($data['old_password'], $driver->password)) {
            return back()
                ->withInput($request->except('old_password', 'password', 'password_confirmation'))
                ->with('error', __('driver_portal.profile.old_password_incorrect'));
        }

        $driver->update([
            'password' => Hash::make($data['password']),
        ]);

        return back()->with('success', __('driver_portal.profile.password_changed'));
    }
}
