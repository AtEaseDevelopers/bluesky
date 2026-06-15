<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CartProduct extends Model
{
    // Specify the fillable attributes for mass assignment
    protected $fillable = ['cart_id', 'product_id', 'quantity', 'weight', 'unit_price', 'price', 'remark', 'status'];

    public static $attribute_rules = [
    ];

    public static $status = [
        'active' => 'active',
        'removed' => 'removed',
    ];

    public static function getOption($id){
        $prod_opt = CartProductOption::where('cart_product_id', $id)
                        ->where('status', CartProductOption::$status['active'])
                        ->pluck('option_item', 'option')
                        ->toArray();

        return $prod_opt;
    }
}