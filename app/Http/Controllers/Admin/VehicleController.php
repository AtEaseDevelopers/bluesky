<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Vehicle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VehicleController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function index()
    {
        return view('admin.vehicles.index');
    }

    public function create()
    {
        return view('admin.vehicles.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'vehicle_number' => ['required', 'string', 'max:50', Rule::unique('vehicles', 'vehicle_number')],
            'description' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        Vehicle::create([
            'vehicle_number' => $data['vehicle_number'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect(route('admin.vehicles.index'))->with('success', __('drivers.vehicle_added_success'));
    }

    public function edit($id)
    {
        return view('admin.vehicles.edit', [
            'vehicle' => Vehicle::findOrFail(decrypt($id)),
        ]);
    }

    public function update(Request $request, $id)
    {
        $vehicleId = decrypt($id);

        $data = $request->validate([
            'vehicle_number' => ['required', 'string', 'max:50', Rule::unique('vehicles', 'vehicle_number')->ignore($vehicleId)],
            'description' => 'nullable|string|max:255',
            'is_active' => 'nullable|boolean',
        ]);

        Vehicle::where('id', $vehicleId)->update([
            'vehicle_number' => $data['vehicle_number'],
            'description' => $data['description'] ?? null,
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect(route('admin.vehicles.index'))->with('success', __('drivers.vehicle_updated_success'));
    }

    public function destroy($id)
    {
        Vehicle::where('id', decrypt($id))->delete();

        return redirect(route('admin.vehicles.index'))->with('success', __('drivers.vehicle_deleted_success'));
    }

    public function fetch(Request $request)
    {
        $columns = ['id', 'options', 'vehicle_number', 'description', 'is_active', 'created_at'];
        $totalitems = DB::table('vehicles')->count();
        $totalFiltered = $totalitems;

        $limit = $request->input('length') == -1 ? $totalitems : $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')] ?? 'vehicle_number';
        $dir = $request->input('order.0.dir') ?? 'asc';

        if ($order === 'options') {
            $order = 'vehicle_number';
        }

        $query = DB::table('vehicles')
            ->select('id', 'vehicle_number', 'description', 'is_active', 'created_at');

        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('vehicle_number', 'LIKE', "%{$search}%")
                    ->orWhere('description', 'LIKE', "%{$search}%");
            });
            $totalFiltered = (clone $query)->count();
        }

        $records = $query->offset($start)->limit($limit)->orderBy($order, $dir)->get();
        $data = [];

        foreach ($records as $record) {
            $data[] = [
                'id' => $record->id,
                'vehicle_number' => $record->vehicle_number,
                'description' => $record->description ?: '-',
                'is_active' => $record->is_active
                    ? '<span class="badge bg-success">' . e(__('drivers.status_labels.active')) . '</span>'
                    : '<span class="badge bg-secondary">' . e(__('drivers.status_labels.inactive')) . '</span>',
                'created_at' => date('m-d-Y', strtotime($record->created_at)),
                'options' => '
                    <a href="' . route('admin.vehicles.edit', encrypt($record->id)) . '" class="btn btn-sm btn-primary" title="' . e(__('ui.edit')) . '">
                        <i class="fa fa-edit"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-danger btn-delete" title="' . e(__('ui.delete')) . '" data-action="' . route('admin.vehicles.destroy', encrypt($record->id)) . '" data-bs-toggle="modal" data-bs-target="#delete">
                        <i class="fa fa-trash"></i>
                    </button>
                ',
            ];
        }

        echo json_encode([
            'draw' => intval($request->input('draw')),
            'recordsTotal' => intval($totalitems),
            'recordsFiltered' => intval($totalFiltered),
            'data' => $data,
        ]);
    }
}
