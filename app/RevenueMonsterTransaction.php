<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

/**
 * A Revenue Monster QR checkout session tied to an order. Rows start as
 * `pending` and become `paid` (or `failed`) when RM's callback arrives.
 */
class RevenueMonsterTransaction extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'order_id',
        'reference',
        'checkout_id',
        'transaction_id',
        'qr_code_url',
        'amount',
        'currency',
        'status',
        'order_payment_id',
        'payload',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payload' => 'array',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }
}
