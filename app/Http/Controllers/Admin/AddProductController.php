<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;

use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use App\ProductOptionItem;
use App\ProductOption;
use App\Product;
use App\ProductCategoryPrice;
use Illuminate\Support\Facades\DB;

class AddProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function showForm()
    {
        $data['uoms'] = DB::table('uoms')->select('id', 'uom_name')->get()->toArray();
        $data['product_categories'] = DB::table('product_categories')->select('id', 'category_name')->get()->toArray();
        $data['customer_categories'] = $this->customerCategories();

        return view('admin.products.create', $data);
    }

    public function addProduct(Request $request)
    {
        $data = $this->validateAddProduct($request);
        if (isset($data['error']) && $data['error']) {
            return redirect()->back()->withInput()->withErrors($data['field_err']);
        }

        $product = Product::create(
            [
                'uom_id' => $data['uom_id'],
                'product_category_id' => $data['product_category_id'],
                'name' => $data['name'],
                'description' => $data['description'],
                'sku' => $data['sku'],
                'price' => $data['price'],
                'weight' => $request['weight'],
                'status' => $data['status'],
                'remark' => $data['remark'],
                'nos' => $data['nos'],
                'sell_in' => $data['sell_in'],
                'weight_presets' => in_array($data['sell_in'], [Product::SELL_IN_WEIGHT, Product::SELL_IN_QTY_BILL_WEIGHT], true)
                    ? Product::parseWeightPresetsInput($request->input('weight_presets'))
                    : null,
            ]
        );

        // process images
        if (isset($data['images']) && $data['images']) {
            $filename = Product::storeUploadedImage($product->id, $data['images']);
            $product->update(['images' => json_encode([$filename])]);
        }

        // Process category pricing
        if ($request->has('category_prices') && is_array($request->input('category_prices'))) {
            foreach ($request->input('category_prices') as $category => $price) {
                if ($price !== null && $price !== '') {
                    ProductCategoryPrice::create([
                        'product_id' => $product->id,
                        'category_name' => $category,
                        'price' => $price
                    ]);
                }
            }
        }

        // process product option
        if (isset($data['product_option']) && $data['product_option']) {
            // add into product option and product option items
            foreach ($data['product_option'] as $product_option => $option_items) {
                $product_option = ProductOption::create(
                    [
                        'product_id' => $product->id,
                        'name' => $product_option,
                        'mandatory' => $data['product_option_mandatory'][$product_option]? 1 : 0,
                        'status' => ProductOption::$status['active'],
                    ]
                );
                $option_items_arr = explode(',', $option_items);
                foreach ($option_items_arr as $item_value) {
                    $product_option_item = ProductOptionItem::create(
                        [
                            'product_id' => $product->id,
                            'product_option_id' => $product_option->id,
                            'name' => trim($item_value),
                            'status' => ProductOptionItem::$status['active'],
                        ]
                    );
                }
            }
        }

        return redirect(route('admin.products'))->with('success', "$product->name has been added successfully.");
    }

    public function validateAddProduct(Request $request)
    {
        $rules = [
            "images" => array_merge(Product::$attribute_rules['images'], []),
            "name" => array_merge(Product::$attribute_rules['name'], []),
            "description" => array_merge(Product::$attribute_rules['description'], []),
            "sku" => array_merge(Product::$attribute_rules['sku'], []),
            "price" => array_merge(Product::$attribute_rules['price'], []),
            "status" => array_merge(Product::$attribute_rules['status'], []),
            "product_option" => ['nullable'],
            "product_option_mandatory" => ['nullable'],
            'remark' => ['nullable'],
            'nos' => ['nullable'],
            'sell_in' => ['required', 'in:qty,weight,qty_bill_weight'],
            'weight_presets' => ['nullable', 'string', 'max:500'],
            'uom_id' => ['required'],
            'product_category_id' => ['required'],
        ];

        try {
            $data = $request->validate($rules);
        } catch (ValidationException $err) {
            return [
                'error' => $err->getMessage(),
                'field_err' => $err->validator->errors()->getMessages(),
            ];
        }

        return $data;
    }

    private function customerCategories(): array
    {
        $fromTable = DB::table('customer_categories')->pluck('category')->toArray();
        $fromUsers = DB::table('users')->select('category')->distinct()->whereNotNull('category')->pluck('category')->toArray();

        $categories = array_values(array_unique(array_merge($fromTable, $fromUsers)));
        sort($categories);

        return $categories;
    }
}
