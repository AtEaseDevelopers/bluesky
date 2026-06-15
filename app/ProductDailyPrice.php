<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class ProductDailyPrice extends Model
{
    // Specify the fillable attributes for mass assignment
    protected $fillable = ['date', 'product_id', 'price', 'user_category', 'status'];

    public static $attribute_rules = [
        "date" => [
            'required', 
            'date_format:Y-m-d',
        ],
        "price" => ['required', 'numeric', 'min:0'],
    ];

    public static $status = [
        'active' => 'active',
        'removed' => 'removed',
    ];

    // Define the belongsTo relationship to Category
    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}