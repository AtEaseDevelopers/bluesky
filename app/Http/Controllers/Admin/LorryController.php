<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Driver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class LorryController extends Controller
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
            'name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'lorry_number' => 'required',
            'username' => ['required', 'string', 'max:50', Rule::unique('drivers', 'username')],
            'password' => 'required|string|min:6',
            'is_active' => 'nullable|boolean',
        ]);

        Driver::create([
            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'lorry_number' => $data['lorry_number'],
            'username' => $data['username'],
            'password' => Hash::make($data['password']),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return redirect(route('admin.lorry.index'))->with('success', 'Driver added successfully.');
    }

    public function edit($id)
    {
        $data['driver'] = Driver::where('id', decrypt($id))->first();
        return view('admin.drivers.edit', $data);
    }

    public function update(Request $request, $id)
    {
        $driverId = decrypt($id);

        $data = $this->validate($request, [
            'name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30',
            'lorry_number' => 'required',
            'username' => ['required', 'string', 'max:50', Rule::unique('drivers', 'username')->ignore($driverId)],
            'password' => 'nullable|string|min:6',
            'is_active' => 'nullable|boolean',
        ]);

        $update = [
            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'lorry_number' => $data['lorry_number'],
            'username' => $data['username'],
            'is_active' => $request->boolean('is_active', true),
        ];

        // Only change the password when a new one is supplied.
        if (!empty($data['password'])) {
            $update['password'] = Hash::make($data['password']);
        }

        Driver::where('id', $driverId)->update($update);

        return redirect(route('admin.lorry.index'))->with('success', 'Driver updated successfully.');
    }

    public function destroy($id)
    {
        Driver::where('id', decrypt($id))->delete();

        return redirect(route('admin.lorry.index'))->with('success', 'Driver deleted successfully.');
    }

    public function get_lorry(Request $request)
    {
        $columns = array('id', 'options', 'name', 'username', 'lorry_number', 'is_active', 'created_at');

        $totalitems = DB::table('drivers')->count();
        $totalFiltered = $totalitems;
        if ($request->input('length') == -1) {
            $limit =  $totalitems;
        } else {
            $limit = $request->input('length');
        }
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        if (empty($request->input('search.value'))) {
            $records = DB::table('drivers')
                ->select('id', 'name', 'username', 'lorry_number', 'is_active', 'created_at')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $records = DB::table('drivers')
                ->select('id', 'name', 'username', 'lorry_number', 'is_active', 'created_at')
                ->where('lorry_number', 'LIKE', "%{$search}%")
                ->orWhere('name', 'LIKE', "%{$search}%")
                ->orWhere('username', 'LIKE', "%{$search}%")
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();

            $totalFiltered = $records->count();
        }

        $data = array();
        if (!empty($records)) {
            foreach ($records as $record) {
                $nestedData['id'] = $record->id;
                $nestedData['name'] = $record->name ?: '-';
                $nestedData['username'] = $record->username ?: '-';
                $nestedData['lorry_number'] = $record->lorry_number;
                $nestedData['is_active'] = $record->is_active
                    ? '<span class="badge bg-success">Active</span>'
                    : '<span class="badge bg-secondary">Inactive</span>';
                $nestedData['created_at'] = date('m-d-Y', strtotime($record->created_at));
                $nestedData['options'] = '
                    <a href="' . route('admin.lorry.edit', encrypt($record->id)) . '" class="btn btn-sm btn-primary" title="Edit">
                        <i class="fa fa-edit"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-danger btn-delete" title="Delete" data-action="' . route('admin.lorry.destroy', encrypt($record->id)) . '" data-bs-toggle="modal" data-bs-target="#delete">
                        <i class="fa fa-trash"></i>
                    </button>
                ';
                $data[] = $nestedData;
            }
        }

        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalitems),
            "recordsFiltered" => intval($totalFiltered),
            "data" => $data,
        );

        echo json_encode($json_data);
    }
}
