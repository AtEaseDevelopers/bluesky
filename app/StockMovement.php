<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class StockMovement extends Model
{
    protected $fillable = [
        'product_id',
        'movement_type',
        'quantity_before',
        'quantity_change',
        'quantity_after',
        'weight',
        'weight_before',
        'weight_change',
        'weight_after',
        'uom_id',
        'order_id',
        'admin_id',
        'reason',
        'remarks',
        'movement_date',
    ];

    public static $movement_types = [
        'stock_in' => 'Stock In',
        'stock_out' => 'Stock Out',
        'sales_deduction' => 'Sales Deduction',
        'order_amendment' => 'Order Amendment',
        'manual_adjustment' => 'Manual Adjustment',
    ];

    public static $stock_out_reasons = [
        'dead_stock' => 'Dead Stock',
        'spoilage' => 'Spoilage',
        'staff_use' => 'Staff Use',
        'stock_correction' => 'Stock Correction',
        'other' => 'Other',
    ];

    public static function movementTypeLabels(): array
    {
        $labels = [];
        foreach (array_keys(self::$movement_types) as $key) {
            $labels[$key] = __('inventory.movement_types.' . $key);
        }

        return $labels;
    }

    public static function stockOutReasonLabels(): array
    {
        $labels = [];
        foreach (array_keys(self::$stock_out_reasons) as $key) {
            $labels[$key] = __('inventory.stock_out_reasons.' . $key);
        }

        return $labels;
    }

    public static function movementTypeLabel(string $type): string
    {
        $key = 'inventory.movement_types.' . $type;
        $label = __($key);

        return $label !== $key ? $label : (self::$movement_types[$type] ?? $type);
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function admin()
    {
        return $this->belongsTo(Admin::class);
    }

    public function uom()
    {
        return $this->belongsTo(Uom::class);
    }
}
