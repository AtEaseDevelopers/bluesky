<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\CreditService;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class CustomerCreditController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function adjust(Request $request, $id)
    {
        $customer = User::findOrFail(decrypt($id));

        if (!$customer->isCreditCustomer()) {
            return back()->with('error', 'Credit adjustments apply to credit customers only. COD customers pay on delivery.');
        }

        $data = $request->validate([
            'amount' => 'required|numeric|not_in:0',
            'notes' => 'required|string|max:500',
        ]);

        try {
            app(CreditService::class)->manualAdjust(
                $customer,
                (float) $data['amount'],
                $data['notes'],
                Auth::guard('web_admin')->id()
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Customer credit balance updated.');
    }
}
