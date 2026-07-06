<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\OrderFieldSetting;
use Illuminate\Http\Request;

class DeliveryOrderSettingsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function edit()
    {
        return view('admin.settings.delivery-order', [
            'showPrices' => OrderFieldSetting::deliveryOrderShowsPrices(),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'do_show_prices' => 'nullable|boolean',
        ]);

        OrderFieldSetting::setValue(
            'do_show_prices',
            $request->boolean('do_show_prices') ? '1' : '0'
        );

        return redirect(route('admin.settings.delivery-order'))
            ->with('success', __('settings.do_settings_updated'));
    }
}
