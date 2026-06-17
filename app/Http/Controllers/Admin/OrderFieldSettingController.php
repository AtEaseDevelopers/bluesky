<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\OrderFieldSetting;
use Illuminate\Http\Request;

class OrderFieldSettingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function edit()
    {
        return view('admin.settings.order-fields', [
            'weightPresets' => implode(', ', OrderFieldSetting::weightPresets()),
            'situationOptions' => implode(', ', OrderFieldSetting::situationOptions()),
            'situationLabel' => OrderFieldSetting::situationLabel(),
        ]);
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'weight_presets' => 'required|string|max:500',
            'situation_options' => 'required|string|max:500',
            'situation_label' => 'required|string|max:50',
        ]);

        $weightPresets = $this->parseList($data['weight_presets']);
        $situationOptions = $this->parseList($data['situation_options']);

        if (empty($weightPresets)) {
            return back()->withInput()->with('error', 'Enter at least one weight preset.');
        }

        if (empty($situationOptions)) {
            return back()->withInput()->with('error', 'Enter at least one situation option.');
        }

        OrderFieldSetting::setValue('weight_presets', json_encode($weightPresets));
        OrderFieldSetting::setValue('situation_options', json_encode($situationOptions));
        OrderFieldSetting::setValue('situation_label', trim($data['situation_label']));

        return back()->with('success', 'Order field settings updated.');
    }

    private function parseList(string $value): array
    {
        return array_values(array_filter(array_map('trim', preg_split('/[\r\n,]+/', $value))));
    }
}
