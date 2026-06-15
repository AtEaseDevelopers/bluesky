<?php

namespace App\Http\Controllers\Admin;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Product;
use App\ProductDailyPrice;
use App\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class AddProductDailyPriceController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth_admin');
    }

    public function showForm(Request $request, $date="", $duplicate_to_date="")
    {
        $product_daily_price = ProductDailyPrice::where('date', $date)->get();

        $formatted_product_daily_price = [];
        foreach ($product_daily_price as $ind => $setting) {
            $formatted_product_daily_price[$setting->product_id][$setting->user_category] = $setting->price;
        }

        $products = Product::where('status', Product::$status['active']);
        
        if ($search_q = $request->input('search_q')) {
            $products->where('name', "LIKE", "%".$search_q."%");
            $products->orWhere('sku', "LIKE", "%".$search_q."%");
        }

        if (isset($request->sort_by)) {
            $sort = explode('-', $request->sort_by);
            $products->orderBy($sort[0], $sort[1]);
        }
                            
        $products = $products->get();
        $category_list = User::select('category')
            ->groupBy('category')   
            ->pluck('category')
            ->toArray();

        return view(
            'admin.products.add-product-daily-price-table', [
                'duplicating' => $duplicate_to_date? true : false,
                'duplicate_from_date' => $date? : "",
                'setup_date' => $duplicate_to_date? : $date,
                'products' => $products,
                'category_list' => $category_list,
                'product_daily_price' => $formatted_product_daily_price ?? null,
            ]
        );
    }

    public function addProductDailyPriceBatch(Request $request, $date="")
    {
        $data = $this->validateAddProductDailyPriceBatch($request);
        if (isset($data['error']) && $data['error']) {
            return redirect()->back()->withInput()->withErrors($data['field_err']);
        }

        foreach ($data['price'] as $product_id => $price_info) {
            foreach ($price_info as $user_category => $price) {
                $exist = ProductDailyPrice::where(
                    [
                    'date' => $date,
                        'product_id' => $product_id,
                        'user_category' => $user_category? : null,
                        'status' => ProductDailyPrice::$status['active'],
                    ]
                )->first();
        
                // if setting already existed
                if (!empty($exist)) {
                    $exist->update(
                        [
                        'price' => $price
                        ]
                    );
                } else {
                    $product = ProductDailyPrice::create(
                        [
                            'date' => $date,
                            'product_id' => $product_id,
                            'user_category' => $user_category? : null,
                            'price' => $price,
                            'status' => ProductDailyPrice::$status['active'],
                        ]
                    );
                }
            }
        }

        return back()->with('success', "Setting saved successfully.");
    }

    public function validateAddProductDailyPriceBatch(Request $request)
    {
        $rules = [
            "price" => ['required', 'array'],
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

    public function addProductDailyPrice(Request $request)
    {
        $data = $this->validateAddProductDailyPrice($request);
        if (isset($data['error']) && $data['error']) {
            return redirect()->back()->withInput()->withErrors($data['field_err']);
        }


        if (ProductDailyPrice::where(
            [
                'date' => $data['date'],
                'product_id' => $data['product_id'],
                'user_category' => $data['user_category'],
                'status' => ProductDailyPrice::$status['active'],
            ]
        )->exists()
        ) {
            return back()->with('error', "The setting has been found duplicated.");
        }
        
        $product = ProductDailyPrice::create(
            [
                'date' => $data['date'],
                'product_id' => $data['product_id'],
                'user_category' => $data['user_category'],
                'price' => $data['price'],
                'status' => ProductDailyPrice::$status['active'],
            ]
        );

        return redirect(url('/admin/product-daily-prices'))->with('success', "Daily price setup successfully.");
    }

    public function validateAddProductDailyPrice(Request $request)
    {
        $rules = [
            "date" => array_merge(ProductDailyPrice::$attribute_rules['date'], []),
            "product_id" => [
                function ($attribute, $value, $fail) {
                    $product = Product::find($value);
                    if (!$product) {
                        $fail('validation.in');
                    }
                }
            ],
            "user_category" => ['nullable',
                function ($attribute, $value, $fail) {
                    $category_list = User::select('category')
                        ->groupBy('category')   
                        ->pluck('category')
                        ->toArray();
                    if(!in_array($value, $category_list)) {
                        $fail('validation.in');
                    }
                }
            ],
            "price" => array_merge(ProductDailyPrice::$attribute_rules['price'], []),
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
}
