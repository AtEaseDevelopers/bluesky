<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductStock extends Model
{
    protected $fillable = [
        'product_id',
        'quantity',
        'weight',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
