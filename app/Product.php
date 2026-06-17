<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

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

    public static function get_today_price($id, User $user)
    {
        return self::resolvePrice($id, $user->category);
    }

    public static function resolvePrice($id, ?string $userCategory = null): float
    {
        $product = Product::find($id);
        if (!$product) {
            return 0;
        }

        $date = Carbon::now()->format('Y-m-d');

        if ($userCategory) {
            $dailyPrice = ProductDailyPrice::where('date', $date)
                ->where('product_id', $product->id)
                ->where('status', ProductDailyPrice::$status['active'])
                ->where('user_category', $userCategory)
                ->first();

            if ($dailyPrice) {
                return (float) $dailyPrice->price;
            }
        }

        $dailyPrice = ProductDailyPrice::where('date', $date)
            ->where('product_id', $product->id)
            ->where('status', ProductDailyPrice::$status['active'])
            ->whereNull('user_category')
            ->first();

        if ($dailyPrice) {
            return (float) $dailyPrice->price;
        }

        if ($userCategory) {
            $categoryPrice = ProductCategoryPrice::where('product_id', $product->id)
                ->where('category_name', $userCategory)
                ->first();

            if ($categoryPrice) {
                return (float) $categoryPrice->price;
            }
        }

        return (float) $product->price;
    }

    public static function formatUnitPrice(float $price, ?string $uomName = null): string
    {
        return 'RM ' . number_format($price, 2) . ' / ' . ($uomName ?: 'KG');
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

    public function stock()
    {
        return $this->hasOne(ProductStock::class);
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }

    public static function formatStockQuantity(float $quantity, ?string $uomName = null): string
    {
        $qty = rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');

        return "Qty: {$qty}";
    }

    public static function storeUploadedImage(int $productId, UploadedFile $file): string
    {
        do {
            $extension = $file->getClientOriginalExtension();
            $filename = time() . rand() . '.' . $extension;
            $path = self::$path . '/' . $productId;
        } while (Storage::disk('local')->exists($path . '/' . $filename));

        Storage::disk('local')->put($path . '/' . $filename, file_get_contents($file));

        return $filename;
    }

    public static function resolveImageUrl($product): string
    {
        if (!is_object($product) || empty($product->id)) {
            return asset('assets/images/product-default.jpg');
        }

        $images = is_string($product->images ?? null)
            ? json_decode($product->images, true)
            : ($product->images ?? null);

        if (is_array($images) && !empty($images[0])) {
            return url('/') . '/' . self::$path . '/' . $product->id . '/' . $images[0];
        }

        return asset('assets/images/product-default.jpg');
    }
}
