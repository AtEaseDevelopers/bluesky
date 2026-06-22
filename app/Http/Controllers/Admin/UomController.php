<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Uom;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UomController extends Controller
{
    public function index()
    {
        return view('admin.uom.index');
    }

    public function create()
    {
        return view('admin.uom.create');
    }

    public function store(Request $request)
    {
        $this->validate(
            $request, [
                'uom_name' => 'required',
            ]
        );

        Uom::create(
            [
                'uom_name' => $request['uom_name']
            ]
        );

        return redirect(route('admin.uom.index'))->with('success', __('uom.added_success'));
    }

    public function edit($id)
    {
        $data['uom'] = Uom::findorFail(decrypt($id));
        return view('admin.uom.edit', $data);
    }

    public function update(Request $request, $id)
    {
        $this->validate(
            $request, [
                'uom_name' => 'required',
            ]
        );

        Uom::where('id', decrypt($id))->update(
            [
                'uom_name' => $request['uom_name']
            ]
        );

        return redirect(route('admin.uom.index'))->with('success', __('uom.updated_success'));
    }

    public function destroy($id)
    {
        return redirect(route('admin.uom.index'))->with('success', __('uom.deleted_success'));
    }

    public function fetch_uom(Request $request)
    {
        $columns = array('id', 'options', 'uom_name', 'total_products', 'created_at');
        $totalRecords = DB::table('uoms')->count();
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
            $records = DB::table('uoms')
                ->select(
                    'id', 'uom_name', 'created_at',
                    DB::raw('(SELECT COUNT(`id`) FROM products WHERE products.uom_id = uoms.id) as total_products')
                )
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $records = DB::table('uoms')
                ->select(
                    'id', 'uom_name', 'created_at',
                    DB::raw('(SELECT COUNT(`id`) FROM products WHERE products.uom_id = uoms.id) as total_products')
                )
                ->where('uom_name', 'LIKE', "%{$search}%")
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
                $nestedData['uom_name'] = $record->uom_name;
                $nestedData['total_products'] = $record->total_products;
                $nestedData['created_at'] = date('d-m-Y', strtotime($record->created_at));
                $nestedData['options'] = '<a href="' . route('admin.uom.edit', encrypt($record->id)) . '" class="btn btn-sm btn-primary me-1"><i class="fa fa-edit"></i></a><button type="button" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="modal" data-bs-target="#delete" data-action="' . route('admin.uom.destroy', encrypt($record->id)) . '"><i class="fa fa-trash"></i></button>';

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
