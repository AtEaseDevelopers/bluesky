<?php

namespace App\Http\Controllers\Admin;

use App\CustomerCategory;
use App\CustomerCategoryProduct;
use App\Http\Controllers\Controller;
use App\Product;
use App\ProductType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CustomerCategoryController extends Controller
{
    public function index()
    {
        return view('admin.customers.categories.index');
    }

    public function create()
    {
        $products_by_type = Product::where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('admin.customers.categories.create', [
            'products_by_type' => $products_by_type
        ]);
    }

    public function store(Request $req)
    {
        $this->validate($req,[
            'category_name' => 'required',

        ]);

        try {
            DB::beginTransaction();

            $customer_category = CustomerCategory::create([
                'category' => $req->category_name
            ]);
            foreach ($req->visible_products as $product_id) {
                CustomerCategoryProduct::create([
                    'customer_category_id' => $customer_category->id,
                    'product_id' => $product_id
                ]);
            }

            DB::commit();

            return redirect(route('admin.customer-categories.index'))->with('success', "Customer category has been added successfully.");
        } catch (\Throwable $th) {
            DB::rollBack();
            report($th);

            return back()->with('error', "Something went wrong");
        }
    }

    public function edit($id)
    {
        $products_by_type = Product::where('status', 'active')
            ->orderBy('name')
            ->get();

        $category = CustomerCategory::findorFail(decrypt($id));
        $category_product_ids = $category->products->pluck('product_id')->toArray();

        return view('admin.customers.categories.edit', [
            'category' => $category,
            'category_product_ids' => $category_product_ids,
            'products_by_type' => $products_by_type
        ]);
    }

    public function update(Request $req, $id)
    {
        $this->validate($req, [
            'category_name' => 'required',
            'visible_products' => 'required|array',
            'visible_products.*' => 'required',
        ]);

        try {
            DB::beginTransaction();

            CustomerCategory::where('id', decrypt($id))->update([
                'category' => $req->category_name
            ]);

            CustomerCategoryProduct::where('customer_category_id', decrypt($id))->delete();
            foreach ($req->visible_products as $product_id) {
                CustomerCategoryProduct::create([
                    'customer_category_id' => decrypt($id),
                    'product_id' => $product_id
                ]);
            }

            DB::commit();

            return redirect(route('admin.customer-categories.index'))->with('success', "Customer category has been added successfully.");
        } catch (\Throwable $th) {
            DB::rollBack();
            report($th);

            return back()->with('error', "Something went wrong");
        }
    }

    public function destroy($id)
    {
        $id = decrypt($id);
        CustomerCategory::where('id', $id)->delete();
        CustomerCategoryProduct::where('customer_category_id', $id)->delete();
        return redirect(route('admin.customer-categories.index'))->with('success', "Customer category has been deleted successfully.");
    }

    public function fetch_categories(Request $request)
    {
        $columns = array('id', 'options', 'category', 'created_at');
        $totalRecords = DB::table('customer_categories')->count();
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
            $records = DB::table('customer_categories')
                ->select(
                    'id',
                    'category',
                    'created_at',
                )
                ->offset($start)
                ->limit($limit)
                ->orderBy($order, $dir)
                ->get();
        } else {
            $search = $request->input('search.value');
            $records = DB::table('customer_categories')
                ->select(
                    'id',
                    'category',
                    'created_at',
                )
                ->where('category', 'LIKE', "%{$search}%")
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
                $nestedData['category'] = $record->category;
                $nestedData['created_at'] = date('d-m-Y', strtotime($record->created_at));
                $nestedData['options'] = '<a href="' . route('admin.customer-categories.edit', encrypt($record->id)) . '" class="btn btn-sm btn-primary me-1"><i class="fa fa-edit"></i></a><button type="button" class="btn btn-sm btn-danger btn-delete" data-bs-toggle="modal" data-bs-target="#delete" data-action="' . route('admin.customer-categories.destroy', encrypt($record->id)) . '"><i class="fa fa-trash"></i></button>';

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
}
