<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderProduct extends Model
{
    protected $fillable = [
        'order_id', 
        'product_id', 
        'product_name', 
        'quantity', 
        'weight', 
        'unit_price', 
        'price', 
        'remark',
        'nos',
        'status',
        'product_weight',
    ];

    public static $attribute_rules = [
    ];

    public static $status = [
        'active' => 'active',
        'removed' => 'removed',
    ];

    public static function getOption($id)
    {
        $prod_opt = OrderProductOption::where('order_product_id', $id)
            ->where('status', OrderProductOption::$status['active'])
            ->pluck('option_item', 'option')
            ->toArray();

        return $prod_opt;
    }

    /**
     * @param  iterable<int, object>  $products
     * @param  array<int, iterable<int, object>>  $optionsByOrderProduct
     */
    public static function formatAdminListHtml(iterable $products, array $optionsByOrderProduct = []): string
    {
        $lines = [];

        foreach ($products as $product) {
            $options = $optionsByOrderProduct[$product->id] ?? [];
            $lines[] = self::formatAdminListLine($product, $options);
        }

        return implode('<hr class="my-1 border-light">', array_filter($lines));
    }

    /**
     * @param  iterable<int, object>|array<string, string>  $options
     */
    public static function formatAdminListLine(object $product, iterable $options = []): string
    {
        $parts = ['<strong>' . e($product->product_name) . '</strong>'];

        if (!empty($product->sku)) {
            $parts[] = '<small>SKU: ' . e($product->sku) . '</small>';
        }

        foreach ($options as $option) {
            if (is_array($option)) {
                $optionName = $option['option'] ?? '';
                $optionValue = $option['option_item'] ?? '';
            } else {
                $optionName = $option->option ?? '';
                $optionValue = $option->option_item ?? '';
            }

            if ($optionName !== '' && $optionValue !== '') {
                $parts[] = '<small>' . e($optionName) . ': ' . e($optionValue) . '</small>';
            }
        }

        $sellIn = $product->sell_in ?? Product::SELL_IN_WEIGHT;
        $qtyLabel = self::formatAdminListQty($product, $sellIn);
        if ($qtyLabel !== null) {
            $parts[] = '<small>' . e($qtyLabel) . '</small>';
        }

        if ($sellIn !== Product::SELL_IN_QTY) {
            $weight = self::displayWeight($product);
            if ($weight !== null) {
                $parts[] = '<small>' . __('orders.weight') . ': ' . e($weight) . '</small>';
            }
        }

        if (!empty($product->nos)) {
            $parts[] = '<small>NOS: ' . e($product->nos) . '</small>';
        }

        if (!empty($product->remark)) {
            $parts[] = '<small>' . __('orders.remark_label') . ' ' . e($product->remark) . '</small>';
        }

        return implode('<br>', $parts);
    }

    public static function formatAdminListQty(object $product, ?string $sellIn = null): ?string
    {
        $sellIn = $sellIn ?? Product::SELL_IN_WEIGHT;

        if ($sellIn === Product::SELL_IN_QTY) {
            if ($product->quantity === null || $product->quantity === '') {
                return null;
            }

            return __('orders.qty') . ': ' . rtrim(rtrim(number_format((float) $product->quantity, 3, '.', ''), '0'), '.');
        }

        if ($sellIn === Product::SELL_IN_QTY_BILL_WEIGHT) {
            $qty = ($product->quantity !== null && $product->quantity !== '')
                ? rtrim(rtrim(number_format((float) $product->quantity, 3, '.', ''), '0'), '.')
                : '-';
            $weight = self::displayWeight($product) ?? '-';

            return __('orders.qty') . ': ' . $qty . ' / ' . __('orders.weight') . ': ' . $weight;
        }

        return null;
    }

    public static function displayWeight(object $product): ?string
    {
        $sellIn = self::resolveSellInForOrderLine($product);

        if ($sellIn === self::SELL_IN_QTY_BILL_WEIGHT && $product->quantity !== null && ! empty($product->weight)) {
            return rtrim((string) ((float) $product->quantity * (float) $product->weight)) . ' KG';
        }

        if (! empty($product->weight)) {
            return rtrim((string) $product->weight) . ' KG';
        }

        if ($product->quantity !== null && $product->product_weight !== null) {
            return rtrim((string) ($product->quantity * $product->product_weight)) . ' KG';
        }

        return null;
    }

    public static function reportWeightValue(object $product): ?float
    {
        $display = self::displayWeight($product);

        if ($display === null) {
            return null;
        }

        return (float) preg_replace('/[^0-9.]/', '', $display);
    }
}
