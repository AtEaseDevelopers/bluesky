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
