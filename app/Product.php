<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Product extends Model
{
    public const SELL_IN_QTY = 'qty';
    public const SELL_IN_WEIGHT = 'weight';
    public const SELL_IN_QTY_BILL_WEIGHT = 'qty_bill_weight';

    /** @return array{show_qty: bool, show_weight: bool} */
    public static function reportFlagsForSellIn(?string $sellIn): array
    {
        return match ($sellIn) {
            self::SELL_IN_QTY => ['show_qty' => true, 'show_weight' => false],
            self::SELL_IN_WEIGHT => ['show_qty' => false, 'show_weight' => true],
            self::SELL_IN_QTY_BILL_WEIGHT => ['show_qty' => true, 'show_weight' => true],
            default => ['show_qty' => false, 'show_weight' => false],
        };
    }

    protected static function booted(): void
    {
        static::saving(function (Product $product) {
            if ($product->sell_in) {
                $flags = self::reportFlagsForSellIn($product->sell_in);
                $product->show_qty = $flags['show_qty'];
                $product->show_weight = $flags['show_weight'];
            }
        });
    }

    // Specify the fillable attributes for mass assignment
    protected $fillable = ['uom_id', 'product_category_id', 'name', 'description', 'sku', 'price', 'weight', 'images', 'status', 'remark', 'nos', 'show_weight', 'show_qty', 'sell_in', 'weight_presets'];

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

    public static function get_today_price($id, ?User $user = null)
    {
        return self::resolvePrice($id, $user->category ?? null);
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

    public function requiresQuantityInput(): bool
    {
        return in_array($this->sell_in, [self::SELL_IN_QTY, self::SELL_IN_QTY_BILL_WEIGHT], true);
    }

    public function requiresWeightInput(): bool
    {
        return in_array($this->sell_in, [self::SELL_IN_WEIGHT, self::SELL_IN_QTY_BILL_WEIGHT], true);
    }

    public static function lineNeedsQuantityInput(string $sellIn): bool
    {
        return in_array($sellIn, [self::SELL_IN_QTY, self::SELL_IN_QTY_BILL_WEIGHT], true);
    }

    public static function lineNeedsWeightInput(string $sellIn): bool
    {
        return in_array($sellIn, [self::SELL_IN_WEIGHT, self::SELL_IN_QTY_BILL_WEIGHT], true);
    }

    /**
     * Resolve how an order line is sold (qty, weight, or both) even when product.sell_in is missing.
     */
    public static function resolveSellInForOrderLine($line, ?self $product = null): string
    {
        if ($product && $product->sell_in) {
            return $product->sell_in;
        }

        if (!empty($line->sell_in)) {
            return $line->sell_in;
        }

        if ($product) {
            if ($product->show_qty && $product->show_weight) {
                return self::SELL_IN_QTY_BILL_WEIGHT;
            }
            if ($product->show_qty) {
                return self::SELL_IN_QTY;
            }
        }

        $hasQty = $line->quantity !== null && $line->quantity !== '';
        $hasWeight = ($line->weight ?? $line->product_weight ?? null) !== null
            && ($line->weight ?? $line->product_weight ?? null) !== '';

        if ($hasQty && $hasWeight) {
            return self::SELL_IN_QTY_BILL_WEIGHT;
        }
        if ($hasQty) {
            return self::SELL_IN_QTY;
        }

        return self::SELL_IN_WEIGHT;
    }

    public static function formatOrderLineQtyLabel($line, ?self $product = null): string
    {
        $sellIn = self::resolveSellInForOrderLine($line, $product);

        return match ($sellIn) {
            self::SELL_IN_QTY => (string) ($line->quantity ?? '-'),
            self::SELL_IN_QTY_BILL_WEIGHT => trim(
                ($line->quantity ?? '-') . ' / ' . ($line->weight ?? $line->product_weight ?? '-') . ' kg'
            ),
            default => ($line->weight ?? $line->product_weight ?? '-') . ' kg',
        };
    }

    public function inventoryTracksQuantity(): bool
    {
        return in_array($this->sell_in, [self::SELL_IN_QTY, self::SELL_IN_QTY_BILL_WEIGHT], true);
    }

    public function inventoryTracksWeight(): bool
    {
        return $this->sell_in === self::SELL_IN_WEIGHT;
    }

    /** @return list<string> */
    public function weightPresetsList(): array
    {
        if (empty($this->weight_presets)) {
            return [];
        }

        $raw = json_decode($this->weight_presets, true);

        return is_array($raw) ? array_values(array_filter($raw)) : [];
    }

    public static function parseWeightPresetsInput(?string $input): ?string
    {
        if ($input === null || trim($input) === '') {
            return null;
        }

        $items = preg_split('/[\s,]+/', str_replace(["\r\n", "\n", "\r"], ',', trim($input)), -1, PREG_SPLIT_NO_EMPTY);
        $items = array_values(array_filter(array_map('trim', $items)));

        return empty($items) ? null : json_encode($items);
    }

    public static function formatWeightPresetsForForm(?string $json): string
    {
        $raw = json_decode($json ?? '', true);

        return is_array($raw) ? implode(', ', $raw) : '';
    }

    /**
     * @return array{quantity: ?float, weight: ?float, product_weight: ?float, bill_amount: float, order_weight: float}
     */
    public function resolveLineInputs(?float $quantity, ?float $weight, bool $billByWeight = false): array
    {
        if ($this->sell_in === self::SELL_IN_QTY_BILL_WEIGHT) {
            $qty = (float) $quantity;
            $wt = ($weight !== null && $weight !== '') ? (float) $weight : null;
            $billAmount = ($billByWeight && $wt !== null && $wt > 0) ? $wt : $qty;

            return [
                'quantity' => $qty,
                'weight' => ($wt !== null && $wt > 0) ? $wt : null,
                'product_weight' => null,
                'bill_amount' => $billAmount,
                'order_weight' => ($wt !== null && $wt > 0) ? $wt : 0,
            ];
        }

        if ($this->sell_in === self::SELL_IN_WEIGHT) {
            $wt = (float) $weight;

            return [
                'quantity' => null,
                'weight' => $wt,
                'product_weight' => $wt,
                'bill_amount' => $wt,
                'order_weight' => $wt,
            ];
        }

        $qty = (float) $quantity;

        return [
            'quantity' => $qty,
            'weight' => null,
            'product_weight' => null,
            'bill_amount' => $qty,
            'order_weight' => ($this->weight ?: 0) * $qty,
        ];
    }

    public function calculateLinePrice(float $unitPrice, ?float $quantity, ?float $weight, bool $billByWeight = false): float
    {
        $line = $this->resolveLineInputs($quantity, $weight, $billByWeight);

        return $unitPrice * $line['bill_amount'];
    }

    public function stockCheckAmount(?float $quantity, ?float $weight): float
    {
        if ($this->requiresQuantityInput()) {
            return (float) $quantity;
        }

        return (float) $weight;
    }

    public function scopeWithStorefrontStock(Builder $query): Builder
    {
        return $query
            ->select(
                'products.*',
                'product_stocks.quantity as stock_quantity',
                'product_stocks.weight as stock_weight',
                'uoms.uom_name'
            )
            ->leftJoin('product_stocks', 'product_stocks.product_id', '=', 'products.id')
            ->leftJoin('uoms', 'uoms.id', '=', 'products.uom_id');
    }

    /** Active products for customer ordering (subject to availability — stock may be zero). */
    public function scopeStorefrontCatalog(Builder $query): Builder
    {
        return $query->where('products.status', self::$status['active']);
    }

    /** In-stock products only (e.g. POS counter sales). */
    public function scopeStorefrontAvailable(Builder $query): Builder
    {
        return $query
            ->where('products.status', self::$status['active'])
            ->where(function (Builder $q) {
                $q->where(function (Builder $q) {
                    $q->whereIn('products.sell_in', [self::SELL_IN_QTY, self::SELL_IN_QTY_BILL_WEIGHT])
                        ->where('product_stocks.quantity', '>', 0);
                })->orWhere(function (Builder $q) {
                    $q->where('products.sell_in', self::SELL_IN_WEIGHT)
                        ->where('product_stocks.weight', '>', 0);
                });
            });
    }

    /**
     * Optional catalog filter by customer category visible products.
     * When a category has no products configured, all active catalog products are shown.
     * Per-customer product_visibilities are admin metadata only — not used to hide portal items.
     */
    public function scopeVisibleToCustomer(Builder $query, User $user): Builder
    {
        return $query->where(function (Builder $q) use ($user) {
            $q->whereNotExists(function ($sub) use ($user) {
                $sub->select(DB::raw(1))
                    ->from('customer_category_products')
                    ->join('customer_categories', 'customer_categories.id', '=', 'customer_category_products.customer_category_id')
                    ->where('customer_categories.category', $user->category);
            })->orWhereExists(function ($sub) use ($user) {
                $sub->select(DB::raw(1))
                    ->from('customer_category_products')
                    ->join('customer_categories', 'customer_categories.id', '=', 'customer_category_products.customer_category_id')
                    ->whereColumn('customer_category_products.product_id', 'products.id')
                    ->where('customer_categories.category', $user->category);
            });
        });
    }

    public function storefrontAvailableAmount(): float
    {
        if ($this->sell_in === self::SELL_IN_WEIGHT) {
            return (float) ($this->stock_weight ?? 0);
        }

        return (float) ($this->stock_quantity ?? 0);
    }

    public static function availableStockAmount(self $product, ProductStock $stock): float
    {
        if ($product->sell_in === self::SELL_IN_WEIGHT) {
            return (float) $stock->weight;
        }

        return (float) $stock->quantity;
    }

    public static function formatStorefrontStockLabel(self $product, float $quantity, float $weight, ?string $uomName = null): string
    {
        if ($product->sell_in === self::SELL_IN_WEIGHT) {
            $w = rtrim(rtrim(number_format($weight, 3, '.', ''), '0'), '.');

            return "Weight: {$w} kg";
        }

        return self::formatStockQuantity($quantity, $uomName);
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
