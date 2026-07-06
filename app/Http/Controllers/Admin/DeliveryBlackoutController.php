<?php

namespace App\Http\Controllers\Admin;

use App\DeliveryBlackout;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DeliveryBlackoutController extends Controller
{
    public function create()
    {
        return view('admin.delivery-blackouts.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'label' => 'nullable|string|max:255',
            'is_enabled' => 'nullable|boolean',
        ]);

        DeliveryBlackout::create([
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'label' => $data['label'] ?? null,
            'is_enabled' => $request->boolean('is_enabled', true),
        ]);

        return redirect(route('admin.delivery-slots.index'))
            ->with('success', __('delivery_slots.blackout_created'));
    }

    public function update(Request $request, $id)
    {
        $blackout = DeliveryBlackout::findOrFail(decrypt($id));

        $data = $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'label' => 'nullable|string|max:255',
            'is_enabled' => 'nullable|boolean',
        ]);

        $blackout->update([
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'label' => $data['label'] ?? null,
            'is_enabled' => $request->boolean('is_enabled', true),
        ]);

        return redirect(route('admin.delivery-slots.index'))
            ->with('success', __('delivery_slots.blackout_updated'));
    }

    public function destroy($id)
    {
        DeliveryBlackout::where('id', decrypt($id))->delete();

        return redirect(route('admin.delivery-slots.index'))
            ->with('success', __('delivery_slots.blackout_deleted'));
    }

    public function fetch(Request $request)
    {
        $totalRecords = DB::table('delivery_blackouts')->count();
        $totalFiltered = $totalRecords;

        if ($request->input('length') == -1) {
            $limit = $totalRecords;
        } else {
            $limit = $request->input('length');
        }

        $start = $request->input('start');
        $query = DB::table('delivery_blackouts');

        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('label', 'LIKE', "%{$search}%")
                    ->orWhere('start_date', 'LIKE', "%{$search}%")
                    ->orWhere('end_date', 'LIKE', "%{$search}%");
            });
            $totalFiltered = $query->count();
        }

        $records = $query->offset($start)->limit($limit)->orderByDesc('start_date')->get();
        $data = [];

        foreach ($records as $record) {
            $startLabel = date('d-m-Y', strtotime($record->start_date));
            $endLabel = date('d-m-Y', strtotime($record->end_date));
            $data[] = [
                'id' => $record->id,
                'options' => '<a href="' . route('admin.delivery-blackouts.edit', encrypt($record->id)) . '" class="btn btn-sm btn-primary me-1"><i class="fa fa-edit"></i></a>'
                    . '<button type="button" class="btn btn-sm btn-danger btn-delete-blackout" data-bs-toggle="modal" data-bs-target="#delete-blackout" data-action="' . route('admin.delivery-blackouts.destroy', encrypt($record->id)) . '"><i class="fa fa-trash"></i></button>',
                'date_range' => $startLabel === $endLabel ? $startLabel : ($startLabel . ' – ' . $endLabel),
                'label' => $record->label ?: '—',
                'is_enabled' => $record->is_enabled
                    ? '<span class="badge bg-success">' . __('delivery_slots.enabled') . '</span>'
                    : '<span class="badge bg-secondary">' . __('delivery_slots.disabled') . '</span>',
                'created_at' => $record->created_at ? date('d-m-Y', strtotime($record->created_at)) : '-',
            ];
        }

        echo json_encode([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalRecords),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
        ]);
    }

    public function edit($id)
    {
        return view('admin.delivery-blackouts.edit', [
            'blackout' => DeliveryBlackout::findOrFail(decrypt($id)),
        ]);
    }
}
