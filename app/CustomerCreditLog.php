<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class CustomerCreditLog extends Model
{
    protected $fillable = [
        'user_id',
        'type',
        'amount',
        'balance_before',
        'balance_after',
        'order_id',
        'order_payment_id',
        'notes',
        'recorded_by',
        'recorded_by_driver',
    ];

    public static $types = [
        'overpayment' => 'Overpayment',
        'applied_to_order' => 'Applied to Order',
        'manual_adjustment' => 'Manual Adjustment',
        'cod_collection' => 'COD Collection',
    ];

    public function customer()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function orderPayment()
    {
        return $this->belongsTo(OrderPayment::class);
    }
}
