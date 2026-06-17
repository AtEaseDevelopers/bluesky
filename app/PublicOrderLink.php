<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class PublicOrderLink extends Model
{
    protected $fillable = [
        'token',
        'order_id',
        'created_by',
        'used_at',
    ];

    protected $casts = [
        'used_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    public function creator()
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function isActive(): bool
    {
        return $this->used_at === null && $this->order_id === null;
    }

    public function getUrlAttribute(): string
    {
        return url('order/public/' . $this->token);
    }

    public static function generateToken(): string
    {
        do {
            $token = Helper::generateRandomString(48);
        } while (static::where('token', $token)->exists());

        return $token;
    }
}
