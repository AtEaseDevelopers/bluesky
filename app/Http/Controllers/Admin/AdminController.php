<?php

namespace App\Http\Controllers\Admin;

use App\Admin;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        $admins = DB::table('admins')->get();
        return view('admin.admins.index', ['admins' => $admins]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        return view('admin.admins.create');
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        $this->validate(
            $request,
            [
                'admin_name' => 'required',
                'admin_username' => 'required',
                'admin_email' => 'required',
                'admin_password' => 'required',

            ]
        );

        Admin::create(
            [
                'name' => $request['admin_name'],
                'username' => $request['admin_username'],
                'email' => $request['admin_email'],
                'password' => Hash::make($request['admin_password']),
                'role' => 'superadmin',
            ]
        );

        return redirect(route('admin.admins.index'))->with('success', "Admin has been added successfully.");

    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $data['admin'] = Admin::findorFail(decrypt($id));
        return view('admin.admins.edit', $data);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {
        $this->validate(
            $request,
            [
                'admin_name' => 'required',
                'admin_username' => 'required',
                'admin_email' => 'required',
            ]
        );

        $data = [
            'name' => $request['admin_name'],
            'username' => $request['admin_username'],
            'email' => $request['admin_email'],
        ];

        if (!empty($request['admin_password'])) {
            $data['password'] = Hash::make($request['admin_password']);
        }

        Admin::where('id', decrypt($id))->update($data);


        return redirect(route('admin.admins.index'))->with('success', "Admin has been updated successfully.");

    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $admin = Admin::findOrFail(decrypt($id));
        $admin->delete();
        return redirect(route('admin.admins.index'))->with('success', "Admin has been deleted successfully.");
    }

    public function fetch_admins(Request $request)
    {
        $columns = array('id', 'options', 'admin_name', 'admin_username', 'admin_email', 'created_at');
        $totalRecords = DB::table('admins')->count();
        $totalFiltered = $totalRecords;
        if ($request->input('length') == -1) {
            $limit = $totalRecords;
        } else {
            $limit = $request->input('length');
        }
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        if (empty($request->input('search.value'))) {
            $records = DB::table('admins')
                ->select(
                    'id',
                    'name',
                    'username',
                    'email',
                    'created_at',
                )
                ->where('role', 'superadmin')
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $records = DB::table('admins')
                ->select(
                    'id',
                    'name',
                    'username',
                    'email',
                    'created_at',
                )
                ->where('role', 'superadmin')
                ->where('name', 'LIKE', "%{$search}%")
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
                $nestedData['admin_name'] = $record->name;
                $nestedData['admin_username'] = $record->username;
                $nestedData['admin_email'] = $record->email;
               
                $nestedData['created_at'] = date('d-m-Y', strtotime($record->created_at));
                $nestedData['options'] = '<a href="' . route('admin.admins.edit', encrypt($record->id)) . '" class="btn btn-sm btn-primary me-1"><i class="fa fa-edit"></i></a><button type="button" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="modal" data-bs-target="#delete" data-action="' . route('admin.admins.destroy', encrypt($record->id)) . '"><i class="fa fa-trash"></i></button>';

                $data[] = $nestedData;
            }
        }

        $json_data = array(
            "draw" => intval($request->input('draw')),
            "recordsTotal" => intval($totalRecords),
            "recordsFiltered" => intval($totalFiltered),
            "data" => $data,
        );

        echo json_encode($json_data);
    }

    public function update_status(Request $request)
    {
        $request->validate([
            'admin_id' => 'required|exists:admins,id',
            'status' => 'required|in:active,inactive'
        ]);

        $admin = Admin::find($request->admin_id);
        $admin->status = $request->status;
        $admin->save();

        return response()->json(['success' => true]);
    }

}
