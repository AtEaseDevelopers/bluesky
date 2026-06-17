<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Driver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

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
        $data = $request->validate([
            'lorry_number' => 'required|string|max:50',
            'name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30|unique:drivers,phone',
            'pin' => 'nullable|string|min:4|max:20',
        ]);

        Driver::create([
            'lorry_number' => $data['lorry_number'],
            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'pin_hash' => !empty($data['pin']) ? Hash::make($data['pin']) : null,
            'is_active' => true,
        ]);

        return redirect(route('admin.lorry.index'))->with('success', 'Driver added successfully.');
    }

    public function edit($id)
    {
        $data['driver'] = Driver::where('id', decrypt($id))->firstOrFail();
        return view('admin.drivers.edit', $data);
    }

    public function update(Request $request, $id)
    {
        $driver = Driver::where('id', decrypt($id))->firstOrFail();

        $data = $request->validate([
            'lorry_number' => 'required|string|max:50',
            'name' => 'nullable|string|max:100',
            'phone' => 'nullable|string|max:30|unique:drivers,phone,' . $driver->id,
            'pin' => 'nullable|string|min:4|max:20',
        ]);

        $update = [
            'lorry_number' => $data['lorry_number'],
            'name' => $data['name'] ?? null,
            'phone' => $data['phone'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ];

        if (!empty($data['pin'])) {
            $update['pin_hash'] = Hash::make($data['pin']);
            $update['api_token'] = null;
        }

        $driver->update($update);

        return redirect(route('admin.lorry.index'))->with('success', 'Driver updated successfully.');
    }

    public function destroy($id)
    {
        Driver::where('id', decrypt($id))->delete();

        return redirect(route('admin.lorry.index'))->with('success', 'Driver deleted successfully.');
    }

    public function get_lorry(Request $request)
    {
        $columns = array('id', 'options', 'lorry_number', 'name', 'phone', 'created_at');

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

        $query = DB::table('drivers')->select('id', 'lorry_number', 'name', 'phone', 'is_active', 'created_at');

        if (!empty($request->input('search.value'))) {
            $search = $request->input('search.value');
            $query->where(function ($q) use ($search) {
                $q->where('lorry_number', 'LIKE', "%{$search}%")
                    ->orWhere('name', 'LIKE', "%{$search}%")
                    ->orWhere('phone', 'LIKE', "%{$search}%");
            });
            $totalFiltered = $query->count();
        }

        $records = $query->offset($start)->limit($limit)->orderBy($order, $dir)->get();

        $data = array();
        if (!empty($records)) {
            foreach ($records as $record) {
                $nestedData['id'] = $record->id;
                $nestedData['lorry_number'] = $record->lorry_number;
                $nestedData['name'] = $record->name ?: '-';
                $nestedData['phone'] = $record->phone ?: '-';
                $nestedData['created_at'] = date('m-d-Y', strtotime($record->created_at));
                $nestedData['options'] = '
                    <a href="' . route('admin.lorry.edit', encrypt($record->id)) . '" class="btn btn-sm btn-primary">
                        <i class="fa fa-edit"></i>
                    </a>
                    <button type="button" class="btn btn-sm btn-danger btn-delete" data-action="' . route('admin.lorry.destroy', encrypt($record->id)) . '" data-bs-toggle="modal" data-bs-target="#delete">
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
