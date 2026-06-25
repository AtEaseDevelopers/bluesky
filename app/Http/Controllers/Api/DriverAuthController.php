<?php

namespace App\Http\Controllers\Api;

use App\Driver;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DriverAuthController extends Controller
{
    public function login(Request $request)
    {
        $data = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $driver = Driver::where('username', $data['username'])->first();

        if (!$driver || !Hash::check($data['password'], $driver->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid username or password.',
            ], 401);
        }

        if (!$driver->is_active) {
            return response()->json([
                'success' => false,
                'message' => 'Your driver account is inactive. Please contact your administrator.',
            ], 403);
        }

        $token = $driver->issueApiToken();

        return response()->json([
            'success' => true,
            'token' => $token,
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'phone' => $driver->phone,
                'username' => $driver->username,
            ],
        ]);
    }

    public function logout(Request $request)
    {
        $driver = $request->attributes->get('driver');
        $driver->update(['api_token' => null]);

        return response()->json(['success' => true, 'message' => 'Logged out.']);
    }

    public function me(Request $request)
    {
        $driver = $request->attributes->get('driver');

        return response()->json([
            'success' => true,
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'phone' => $driver->phone,
                'username' => $driver->username,
            ],
        ]);
    }
}
