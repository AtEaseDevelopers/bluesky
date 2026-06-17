<?php

namespace App;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Driver extends Authenticatable
{
    use Notifiable;

    protected $fillable = [
        'name',
        'phone',
        'username',
        'password',
        'lorry_number',
        'api_token',
        'is_active',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'api_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public static $attribute_rules = [
        'username' => ['required', 'string'],
        'password' => ['required', 'string'],
    ];

    /**
     * Delivery orders assigned to this driver.
     */
    public function orders()
    {
        return $this->hasMany(Order::class, 'driver_id', 'id');
    }

    public function issueApiToken(): string
    {
        $plainToken = bin2hex(random_bytes(32));
        $this->update(['api_token' => hash('sha256', $plainToken)]);

        return $plainToken;
    }

    public static function findByToken(?string $plainToken): ?self
    {
        if (!$plainToken) {
            return null;
        }

        return static::where('api_token', hash('sha256', $plainToken))
            ->where('is_active', true)
            ->first();
    }
}
