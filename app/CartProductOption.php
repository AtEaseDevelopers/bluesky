<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CartProductOption extends Model
{
    // Specify the fillable attributes for mass assignment
    protected $fillable = ['cart_product_id', 'option', 'option_item', 'status'];

    public static $attribute_rules = [
    ];

    public static $status = [
        'active' => 'active',
        'removed' => 'removed',
    ];
}