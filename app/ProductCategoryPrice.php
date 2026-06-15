<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductCategoryPrice extends Model
{
    protected $table = 'product_category_prices';
    protected $guarded = [];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
