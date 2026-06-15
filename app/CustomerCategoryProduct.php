<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerCategoryProduct extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function product() {
        return $this->belongsTo(\App\Product::class);
    }
}
