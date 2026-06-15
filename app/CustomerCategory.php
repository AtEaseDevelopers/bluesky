<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CustomerCategory extends Model
{
    use HasFactory;
    
    protected $guarded = [];

    public function products() {
        return $this->hasMany(CustomerCategoryProduct::class);
    }
}
