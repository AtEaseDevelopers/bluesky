<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductVisibility extends Model
{
    use HasFactory;
    
    protected $fillable = [
        'user_id', 
        'product_id',
    ];   
}
