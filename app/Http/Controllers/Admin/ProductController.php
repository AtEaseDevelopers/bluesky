<?php

namespace App\Http\Controllers\Admin;

use App\Exports\AdminProductExport;
use App\Helper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Http\Requests\ImportProductRequest;
use App\Imports\ProductsImport;
use App\Product;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function index(Request $request)
    {
        $products = DB::table('products')
            ->leftJoin('uoms', 'uoms.id', '=', 'products.uom_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->select(
                'uom_name',
                'category_name',
                'products.id',
                'name',
                'images',
                'sku',
                'price',
                'description',
                'status',
                'products.updated_at',
                'products.created_at',
            )
            ->where('status', '!=', Product::$status['removed']);

        if (!empty($request['product_category_id'])) {
            $products->where('product_category_id', "{$request['product_category_id']}");
        }

        if (!empty($request['uom_id'])) {
            $products->where('uom_id', "{$request['uom_id']}");
        }

        if (!empty($request['sku'])) {
            $products->where('sku', 'LIKE', "%{$request['sku']}%");
        }

        if (!empty($request['name'])) {
            $products->where('name', 'LIKE', "%{$request['name']}%");
        }

        if (!empty($request['status'])) {
            $products->where('status', $request['status']);
        }

        if (!empty($request['min_price']) || !empty($request['max_price'])) {
            if (!empty($request['min_price'])) {
                $products->where('price', '>=', $request['min_price']);
            }

            if (!empty($request['max_price'])) {
                $products->where('price', '<=', $request['max_price']);
            }
        }

        if (!empty($request['price_range'])) {
            $price_range = $request['price_range'];

            $filter_price_range = explode(',', $price_range);
            $from_price = $filter_price_range[0];
            $to_price = $filter_price_range[1];

            $products->where('price', '>=', $from_price);
            $products->where('price', '<=', $to_price);
        }

        if (!empty($request['fdate'])) {
            $products->where('products.created_at', '>=', $request['fdate']);
        }

        if (!empty($request['tdate'])) {
            $products->where('products.created_at', '<=', $request['tdate'] . " 23:59:59");
        }

        $products = $products->paginate(15);
        $minPrice = Product::min('price');
        $maxPrice = Product::max('price');

        // foreach ($products as $key => $value) {
        //     $image = json_decode($value->images, true);
        //     if (isset($image[0])) {
        //         $products[$key]->image_url = url('/') . '/' . Product::$path."/".$value->id."/".$image[0];
        //     } else {
        //         $products[$key]->image_url = asset('assets/images/product-default.jpg');
        //     }
        // }

        $product_path = Product::$path;

        $uoms = DB::table('uoms')
            ->select('id', 'uom_name')
            ->get()
            ->toArray();

        $product_categories = DB::table('product_categories')
            ->select('id', 'category_name')
            ->get()
            ->toArray();

        return view('admin.products.index', [
                'product_path' => $product_path,
                'products' => $products,
                'uoms' => $uoms,
                'product_categories' => $product_categories,
                'input' => $request->all() + ['min_price' => $minPrice, 'max_price' => $maxPrice, 'from_price' => $from_price ?? $minPrice, 'to_price' => $to_price ?? $maxPrice],
                'query_params' => Helper::query_params($request->input()),
            ]
        );
    }

    public function export(Request $request)
    {
        $products = Product::select('id', 'name', 'description', 'price', 'status', 'created_at');

        if ($filter_sku = $request->input('sku')) {
            $products->where('sku', 'LIKE', "%$filter_sku%");
        }

        if ($filter_name = $request->input('name')) {
            $products->where('name', 'LIKE', "%$filter_name%");
        }

        if ($filter_status = $request->input('status')) {
            $products->where('status', $filter_status);
        }

        if ($filter_price_range = $request->input('price_range')) {
            $filter_price_range = explode(',', $filter_price_range);
            $from_price = $filter_price_range[0];
            $to_price = $filter_price_range[1];
            $products->where('price', '>=', $from_price);
            $products->where('price', '<=', $to_price);
        }

        $header = ['No', 'Name', 'Description', 'Price', 'Status', 'Created At']; // Adjust the header based on your data model
        return Excel::download(new AdminProductExport($products->get(), $header), Carbon::now()->format('YmdHis').'-Product-List.xlsx');
    }

    public function import()
    {
        return view('admin.products.import');
    }

    public function import_store(ImportProductRequest $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv|max:10240'
        ]);

        try {
            $import = new ProductsImport();

            Excel::import($import, $request->file('file'));

            return redirect()->back()
                ->with('success', "Successfully imported products!");

        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Import failed: ' . $e->getMessage())
                ->withInput();
        }
    }
}
