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
}
