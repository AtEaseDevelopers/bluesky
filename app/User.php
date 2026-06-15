<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name', 
        'email', 
        'password', 
        'category', 
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
        'login_code', 
        'remark', 
        'status', 
        'price_permission',
        'invoice_visibility',
        'invoice_price_permission',
        'default_driver_id',
        'sql_customer_code',
        'fax_no'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public static $attribute_rules = [
        'name' => ['required', 'unique:users', 'string', 'max:100'],
        'email' => ['nullable', 'email', 'max:100'],
        'password' => ['required', 'string'],
        'category' => ['nullable', 'string', 'max:30'],
        'attn_name' => ['nullable', 'string', 'max:30'],
        'attn_contact' => ['nullable', 'string', 'max:30'],
        'billing_address' => ['required', 'string', 'max:100'],
        'billing_postcode' => ['required', 'string', 'max:5'],
        'billing_state' => ['required', 'string', 'max:30'],
        'shipping_address' => ['nullable', 'string', 'max:100'],
        'shipping_postcode' => ['nullable', 'string', 'max:5'],
        'shipping_state' => ['nullable', 'string', 'max:30'],
        'payment_method' => ['required', 'array'],
        'remark' => ['nullable', 'string', 'max:500'],
        'fax_no' => ['nullable', 'string', 'max:20'],
    ];

    public static $payment_method = [
        'cod' => 'cod',
        'term' => 'term',
        'bank-transfer' => 'bank-transfer',
        'e-wallet' => 'e-wallet',
    ];

    public static $user_status = [
        'active' => 'active',
        'locked' => 'locked',
        'terminated' => 'terminated',
    ];
}
