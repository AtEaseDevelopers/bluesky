<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    // Specify the fillable attributes for mass assignment
    protected $fillable = ['uom_id', 'product_category_id', 'name', 'description', 'sku', 'price', 'weight', 'images', 'status', 'remark', 'nos', 'show_weight', 'show_qty', 'sell_in'];

    public static $attribute_rules = [
        "images" => [
            'nullable',
            'file',
            'mimes:jpeg,png,jpg',
            'max:4096'
        ],
        "name" => ['required', 'max:50'],
        "description" => ['nullable', 'max:200'],
        "sku" => ['nullable', 'max:50'],
        "price" => ['required', 'numeric', 'min:0'],
        "status" => ['required', 'max:50'],
    ];

    public static $path = 'products';

    public static $status = [
        'active' => 'active',
        'inactive' => 'inactive',
        'removed' => 'removed',
    ];

    public static function get_today_price($id, User $user){
        $product = Product::find($id);
        $date = Carbon::now()->format('Y-m-d');

        // Fetch the specific category price
        $product_daily_price = ProductDailyPrice::where('date', $date)
            ->where('product_id', $product->id)
            ->where('status', ProductDailyPrice::$status['active'])
            ->where('user_category', $user->category)
            ->first();

        if ($product_daily_price) {
            return $product_daily_price->price;
        }

        // If no specific category price found, fetch the general price
        $product_daily_price = ProductDailyPrice::where('date', $date)
            ->where('product_id', $product->id)
            ->where('status', ProductDailyPrice::$status['active'])
            ->whereNull('user_category') // For all categories (null)
            ->first();

        if ($product_daily_price) {
            return $product_daily_price->price;
        }

        // Fallback to the product's default price
        return $product->price;
    }

    /**
     * Price shown to public / General Customer (no account, no category).
     * Uses today's "all categories" daily price, else the product default price.
     */
    public static function getPublicTodayPrice($id){
        $product = Product::find($id);
        $date = Carbon::now()->format('Y-m-d');

        $product_daily_price = ProductDailyPrice::where('date', $date)
            ->where('product_id', $product->id)
            ->where('status', ProductDailyPrice::$status['active'])
            ->whereNull('user_category') // For all categories (null)
            ->first();

        if ($product_daily_price) {
            return $product_daily_price->price;
        }

        return $product->price;
    }

    public static function getOption($id, $return_array=false){
        $product_option = [];
        $product_option_mandatory = [];

        $prod_opt = ProductOption::where('product_id', $id)
                        ->where('status', ProductOption::$status['active'])
                        ->get();

        foreach ($prod_opt as $index => $value) {
            $prod_opt_itm = ProductOptionItem::where('product_id', $id)
                                                ->where('product_option_id', $value->id)
                                                ->where('status', ProductOptionItem::$status['active'])
                                                ->pluck('name')->toArray();

            if($return_array){
                $product_option[$value->name] = $prod_opt_itm;
            }else{
                $product_option[$value->name] = implode(', ', $prod_opt_itm);
            }
            $product_option_mandatory[$value->name] = $value->mandatory;
        }

        return [
            'product_option' => $product_option,
            'product_option_mandatory' => $product_option_mandatory,
        ];
    }

    public function categoryPrices()
    {
        return $this->hasMany(ProductCategoryPrice::class);
    }
}
