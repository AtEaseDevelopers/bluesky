<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class ProductOption extends Model
{
    // Specify the fillable attributes for mass assignment
    protected $fillable = ['product_id', 'name', 'mandatory', 'status'];
    
    public static $status = [
        'active' => 'active',
        'inactive' => 'inactive',
        'removed' => 'removed',
    ];

    public function product()
    {
        return $this->belongsTo(Products::class, 'product_id', 'id');
    }
}