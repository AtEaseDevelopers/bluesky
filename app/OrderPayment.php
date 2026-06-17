<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class OrderPayment extends Model
{
    protected $fillable = [
        'order_id',
        'payment_method',
        'amount',
        'payment_proof',
        'recorded_by',
        'notes',
    ];

    public static $payment_methods = [
        'cash' => 'Cash',
        'qr' => 'QR',
        'bank-transfer' => 'Bank Transfer',
        'payment-gateway' => 'Payment Gateway',
        'credit-term' => 'Credit Term',
        'cod' => 'COD',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function recorder()
    {
        return $this->belongsTo(Admin::class, 'recorded_by');
    }
}
