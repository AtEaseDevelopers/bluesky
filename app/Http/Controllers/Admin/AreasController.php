<?php

namespace App\Http\Controllers\Admin;

use App\Area;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AreasController extends Controller
{
    public function index()
    {
        return view('admin.areas.index');
    }

    public function create()
    {
        return view('admin.areas.create');
    }

    public function store(Request $request)
    {
        $this->validate(
            $request, [
                'area_name' => 'required',
            ]
        );

        Area::create(
            [
                'area_name' => $request['area_name']
            ]
        );

        return redirect(route('admin.areas.index'))->with('success', __('areas.added_success'));
    }

    public function edit($id)
    {
        $data['area'] = Area::findorFail(decrypt($id));
        return view('admin.areas.edit', $data);
    }

    public function update(Request $request, $id)
    {
        $this->validate(
            $request, [
                'area_name' => 'required',
            ]
        );

        Area::where('id', decrypt($id))->update(
            [
                'area_name' => $request['area_name']
            ]
        );

        return redirect(route('admin.areas.index'))->with('success', __('areas.updated_success'));
    }

    public function destroy($id)
    {
        return redirect(route('admin.areas.index'))->with('success', __('areas.deleted_success'));
    }

    public function fetch_areas(Request $request)
    {
        $columns = array('id', 'options', 'area_name', 'total_customers', 'created_at');
        $totalRecords = DB::table('areas')->count();
        $totalFiltered = $totalRecords;
        if ($request->input('length') == -1) {
            $limit =  $totalRecords;
        } else {
            $limit = $request->input('length');
        }
        $start = $request->input('start');
        $order = $columns[$request->input('order.0.column')];
        $dir = $request->input('order.0.dir');

        if (empty($request->input('search.value'))) {
            $records = DB::table('areas')
                ->select(
                    'id', 'area_name', 'created_at',
                    DB::raw('(SELECT COUNT(`id`) FROM users WHERE areas.id = users.area) as total_customers')
                )
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $records = DB::table('areas')
                ->select(
                    'id', 'area_name', 'created_at',
                    DB::raw('(SELECT COUNT(`id`) FROM users WHERE areas.id = users.area) as total_customers')
                )
                ->where('area_name', 'LIKE', "%{$search}%")
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
                $nestedData['area_name'] = $record->area_name;
                $nestedData['total_customers'] = $record->total_customers;
                $nestedData['created_at'] = $record->created_at ? date('d-m-Y', strtotime($record->created_at)) : '-';
                $nestedData['options'] = '<a href="' . route('admin.areas.edit', encrypt($record->id)) . '" class="btn btn-sm btn-primary me-1"><i class="fa fa-edit"></i></a><button type="button" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="modal" data-bs-target="#delete" data-action="' . route('admin.areas.destroy', encrypt($record->id)) . '"><i class="fa fa-trash"></i></button>';

                $data[] = $nestedData;
            }
        }

        $json_data = array(
            "draw"              => intval($request->input('draw')),
            "recordsTotal"      => intval($totalRecords),
            "recordsFiltered"   => intval($totalFiltered),
            "data"              => $data,
        );

        echo json_encode($json_data);
    }
}
