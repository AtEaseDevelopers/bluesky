<?php

namespace App\Http\Controllers\Admin;

use App\Helper;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Product;
use App\ProductOption;
use App\ProductOptionItem;
use App\ProductCategoryPrice;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EditProductController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function showForm($id)
    {
        $product = Product::find(decrypt($id));
        $product->image_url = Product::resolveImageUrl($product);
        $product->product_option = Product::getOption($product->id);
        $uoms = DB::table('uoms')->select('id', 'uom_name')->get()->toArray();
        $product_categories = DB::table('product_categories')->select('id', 'category_name')->get()->toArray();
        $customer_categories = $this->customerCategories();
        $category_prices = ProductCategoryPrice::where('product_id', $product->id)->get();

        return view(
            'admin.products.edit', [
                'product' => $product,
                'uoms' => $uoms,
                'product_categories' => $product_categories,
                'customer_categories' => $customer_categories,
                'category_prices' => $category_prices,
            ]
        );
    }

    public function editProduct(Request $request, $id)
    {
        $data = $this->validateEditProduct($request);
        $product = Product::find(decrypt($id));

        if (isset($data['error']) && $data['error']) {
            return redirect()->back()->withInput()->withErrors($data['field_err']);
        }

        $product->fill(
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
                'show_weight' => isset($data['show_weight']),
                'show_qty' => isset($data['show_qty']),
                'sell_in' => $data['sell_in'],
            ]
        )->save();

        // process images
        if (isset($data['images']) && $data['images']) {
            $filename = Product::storeUploadedImage($product->id, $data['images']);
            $product->update(['images' => json_encode([$filename])]);
        }

        // Process category pricing
        ProductCategoryPrice::where('product_id', $product->id)->delete();
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
        // remove all previous added product option, add new when user update product
        ProductOption::where('product_id', $product->id)->update(['status' => ProductOption::$status['removed']]);
        ProductOptionItem::where('product_id', $product->id)->update(['status' => ProductOptionItem::$status['removed']]);
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

        return redirect(route('admin.products.edit', encrypt($product->id)))->with('success', "$product->name has been updated successfully.");
    }

    private function customerCategories(): array
    {
        $fromTable = DB::table('customer_categories')->pluck('category')->toArray();
        $fromUsers = DB::table('users')->select('category')->distinct()->whereNotNull('category')->pluck('category')->toArray();

        $categories = array_values(array_unique(array_merge($fromTable, $fromUsers)));
        sort($categories);

        return $categories;
    }

    public function validateEditProduct(Request $request)
    {
        $rules = [
            "images" => [
                'nullable',
                'file',
                'mimes:jpeg,png,jpg',
                'max:4096'
            ],
            "name" => array_merge(Product::$attribute_rules['name'], []),
            "description" => array_merge(Product::$attribute_rules['description'], []),
            "sku" => array_merge(Product::$attribute_rules['sku'], []),
            "price" => array_merge(Product::$attribute_rules['price'], []),
            "status" => array_merge(Product::$attribute_rules['status'], []),
            "product_option" => ['nullable'],
            "product_option_mandatory" => ['nullable'],
            'remark' => ['nullable'],
            'nos' => ['nullable'],
            'show_weight' => ['nullable'],
            'show_qty' => ['nullable'],
            'sell_in' => ['required'],
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

    public function removeProduct(Request $request, Product $product)
    {
        $product->update(
            [
            'status' => Product::$status['removed']
            ]
        );

        return redirect()->to('/admin/products')->with('success', "$product->name has been removed successfully.");
    }
}
