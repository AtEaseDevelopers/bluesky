<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\PosService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PosController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function setSession(Request $request, PosService $pos)
    {
        $request->validate([
            'mode' => ['required', 'in:guest,customer'],
            'customer_id' => ['nullable', 'exists:users,id'],
        ]);

        $adminId = Auth::guard('web_admin')->id();

        if ($request->input('mode') === 'guest') {
            $pos->startGuest($request, $adminId);

            return response()->json(['success' => true]);
        }

        $request->validate([
            'customer_id' => ['required', 'exists:users,id'],
        ]);

        $customer = User::findOrFail($request->input('customer_id'));

        if (!$customer->hasCompletedRegistration()) {
            return response()->json([
                'success' => false,
                'message' => __('customers.pos.registration_required'),
            ], 422);
        }

        if ($customer->status !== User::$user_status['active']) {
            return response()->json([
                'success' => false,
                'message' => __('customers.pos.customer_inactive'),
            ], 422);
        }

        $pos->startCustomer($request, $adminId, $customer);

        return response()->json(['success' => true]);
    }

    public function resetSession(Request $request, PosService $pos)
    {
        $pos->clear($request);

        return response()->json(['success' => true]);
    }

    public function exit(Request $request, PosService $pos)
    {
        $pos->clear($request);

        return redirect()
            ->route('admin.customers')
            ->with('success', __('customers.pos.ended'));
    }
}
