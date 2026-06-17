<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BulkPaymentOrder extends Model
{
    protected $fillable = [
        'bulk_payment_id',
        'order_id',
        'amount',
    ];

    public function bulkPayment()
    {
        return $this->belongsTo(BulkPayment::class);
    }

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}
