<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class BulkPayment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_REJECTED = 'rejected';

    protected $fillable = [
        'user_id',
        'total_amount',
        'payment_method',
        'payment_proof',
        'status',
        'notes',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function allocations()
    {
        return $this->hasMany(BulkPaymentOrder::class);
    }

    public function payments()
    {
        return $this->hasMany(OrderPayment::class);
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }
}
