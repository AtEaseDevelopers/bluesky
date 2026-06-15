<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    // Specify the fillable attributes for mass assignment
    protected $fillable = [
        'user_id', 
        'cart_id', 
        'total_price', 
        'attn_name', 
        'attn_contact', 
        'area', 
        'billing_address', 
        'billing_city',
        'billing_postcode', 
        'billing_state', 
        'shipping_address', 
        'shipping_city', 
        'shipping_postcode', 
        'shipping_state', 
        'payment_method', 
        'transfer_slip', 
        'status',
        'driver_id',
        'order_weight',
        'do_no',
        'do_date'
    ];

    public static $path = 'orders';
    
    public static $attribute_rules = [
        'attn_name' => ['nullable', 'string', 'max:30'],
        'attn_contact' => ['nullable', 'string', 'max:30'],
        'billing_address' => ['required', 'string', 'max:100'],
        'billing_postcode' => ['required', 'string', 'max:5'],
        'billing_state' => ['required', 'string', 'max:30'],
        'shipping_address' => ['nullable', 'string', 'max:100'],
        'shipping_postcode' => ['nullable', 'string', 'max:5'],
        'shipping_state' => ['nullable', 'string', 'max:30'],
        'payment_method' => ['required'],
        'transfer_slip' => ['nullable', 'required_if:payment_method,bank-transfer', 'mimes:jpg,jpeg,png', 'max:4096'],
    ];

    public static $status = [
        // 'pending' => 'pending', // removed pending status as per requested on 16/11/2023
        'cancelled' => 'cancelled',
        'processing' => 'processing',
        'delivering' => 'delivering',
        'completed' => 'completed',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id', 'id');
    }
}