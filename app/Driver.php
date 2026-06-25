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
        'api_token',
        'is_active',
        'role_slug',
        'locale',
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

    /** @return array<int, string> */
    public static function optionsForSelect(?int $includeInactiveId = null): array
    {
        return \Illuminate\Support\Facades\DB::table('drivers')
            ->select('id', 'name', 'username', 'is_active')
            ->where(function ($query) use ($includeInactiveId) {
                $query->where('is_active', true);
                if ($includeInactiveId) {
                    $query->orWhere('id', $includeInactiveId);
                }
            })
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function ($driver) {
                $label = $driver->name ?: $driver->username;
                if (!$driver->is_active) {
                    $label .= ' (' . __('drivers.status_labels.inactive') . ')';
                }

                return [$driver->id => $label];
            })
            ->all();
    }
}
