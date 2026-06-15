<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductOptionItem extends Model
{
    // Specify the fillable attributes for mass assignment
    protected $fillable = ['product_id', 'product_option_id', 'name', 'status'];
    
    public static $status = [
        'active' => 'active',
        'inactive' => 'inactive',
        'removed' => 'removed',
    ];

    public function product_option()
    {
        return $this->belongsTo(ProductOption::class, 'product_option_id', 'id');
    }
}