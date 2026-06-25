<?php

namespace App\Http\Controllers\Admin;

use App\Driver;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class DriverController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function index()
    {
        return view('admin.drivers.index');
    }

    public function create()
    {
        return view('admin.drivers.create');
    }

    public function store(Request $request)
    {
        $data = $this->validate($request, [
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:30',
            'username' => ['required', 'string', 'max:50', Rule::unique('drivers', 'username')],
            'password' => 'required|string|min:6',
            'is_active' => 'nullable|boolean',
        ]);

        Driver::create([
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect(route('admin.drivers.index'))->with('success', __('drivers.added_success'));
    }

    public function edit($id)
    {
        return view('admin.drivers.edit', [
            'driver' => Driver::findOrFail(decrypt($id)),
        ]);
    }

    public function update(Request $request, $id)
    {
        $driverId = decrypt($id);

        $data = $this->validate($request, [
            'name' => 'required|string|max:100',
            'phone' => 'nullable|string|max:30',
            'username' => ['required', 'string', 'max:50', Rule::unique('drivers', 'username')->ignore($driverId)],
            'password' => 'nullable|string|min:6',
            'is_active' => 'nullable|boolean',
        ]);

        $update = [
            'name' => $data['name'],
            'phone' => $data['phone'] ?? null,
            'username' => $data['username'],
            'is_active' => $request->boolean('is_active', true),
        ];

        if (!empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }

        Driver::where('id', $driverId)->update($update);

        return redirect(route('admin.drivers.index'))->with('success', __('drivers.updated_success'));
    }

    public function destroy($id)
    {
        Driver::where('id', decrypt($id))->delete();

        return redirect(route('admin.drivers.index'))->with('success', __('drivers.deleted_success'));
    }

    public function fetch(Request $request)
    {
        $columns = ['id', 'options', 'name', 'username', 'phone', 'is_active', 'created_at'];
        $totalitems = DB::table('drivers')->count();
        $totalFiltered = $totalitems;

        $limit = $request->input('length') == -1 ? $totalitems : $request->input('length');
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')] ?? 'name';
        $dir = $request->input('order.0.dir') ?? 'asc';

        if ($order === 'options') {
            $order = 'name';
        }

        $query = DB::table('drivers')
            ->select('id', 'name', 'username', 'phone', 'is_active', 'created_at');

        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('name', 'LIKE', "%{$search}%")
                    ->orWhere('username', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            });
            $totalFiltered = (clone $query)->count();
        }

        $records = $query->offset($start)->limit($limit)->orderBy($order, $dir)->get();
        $data = [];

        foreach ($records as $record) {
            $data[] = [
                'id' => $record->id,
                'name' => $record->name ?: '-',
                'username' => $record->username ?: '-',
                'phone' => $record->phone ?: '-',
                'is_active' => $record->is_active
                    ? '<span class="badge bg-success">' . e(__('drivers.status_labels.active')) . '</span>'
                    : '<span class="badge bg-secondary">' . e(__('drivers.status_labels.inactive')) . '</span>',
                'created_at' => date('m-d-Y', strtotime($record->created_at)),
                'options' => '
                    <a href="' . route('admin.drivers.edit', encrypt($record->id)) . '" class="btn btn-sm btn-primary" title="' . e(__('ui.edit')) . '">
                        <i class="fa fa-edit"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-danger btn-delete" title="' . e(__('ui.delete')) . '" data-action="' . route('admin.drivers.destroy', encrypt($record->id)) . '" data-bs-toggle="modal" data-bs-target="#delete">
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
