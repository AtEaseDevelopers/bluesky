<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Cart extends Model
{
    // Specify the fillable attributes for mass assignment
    protected $fillable = ['replicated_id', 'user_id', 'status'];

    public static $attribute_rules = [
    ];

    public static $status = [
        'buy-again' => 'buy-again',
        'pending' => 'pending',
        'completed' => 'completed',
        'removed' => 'removed',
        'aborted' => 'aborted',
    ];
}