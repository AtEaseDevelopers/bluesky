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
            'phone' => 'required|string',
            'pin' => 'required|string|min:4|max:20',
        ]);

        $driver = Driver::where('phone', $data['phone'])->where('is_active', true)->first();

        if (!$driver || !$driver->verifyPin($data['pin'])) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid phone or PIN.',
            ], 401);
        }

        $token = $driver->issueApiToken();

        return response()->json([
            'success' => true,
            'token' => $token,
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'phone' => $driver->phone,
                'lorry_number' => $driver->lorry_number,
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
                'lorry_number' => $driver->lorry_number,
            ],
        ]);
    }
}
