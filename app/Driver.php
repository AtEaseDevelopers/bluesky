<?php

namespace App;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Driver extends Authenticatable
{
    use Notifiable;
    use SoftDeletes;

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
        // Raw query builder: the SoftDeletes global scope does not apply here,
        // so deleted drivers are excluded explicitly — except the one explicitly
        // referenced (e.g. the driver already assigned to the order being edited).
        return \Illuminate\Support\Facades\DB::table('drivers')
            ->select('id', 'name', 'username', 'is_active', 'deleted_at')
            ->where(function ($query) use ($includeInactiveId) {
                $query->where(function ($q) {
                    $q->whereNull('deleted_at')->where('is_active', true);
                });
                if ($includeInactiveId) {
                    $query->orWhere('id', $includeInactiveId);
                }
            })
            ->orderBy('name')
            ->get()
            ->mapWithKeys(function ($driver) {
                return [$driver->id => self::formatSelectLabel(
                    $driver->name,
                    $driver->username,
                    (bool) $driver->is_active,
                    !is_null($driver->deleted_at)
                )];
            })
            ->all();
    }

    /**
     * Active drivers for dropdowns, plus any drivers referenced on existing orders.
     *
     * @param  array<int|string|null>  $referencedDriverIds
     * @return array<int, string>
     */
    public static function optionsForOrders(array $referencedDriverIds = []): array
    {
        $options = self::optionsForSelect();
        $missingIds = collect($referencedDriverIds)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->diff(array_map('intval', array_keys($options)));

        if ($missingIds->isEmpty()) {
            return $options;
        }

        // Include trashed drivers so orders assigned to a since-deleted driver
        // still render a name rather than a blank label.
        static::withTrashed()
            ->whereIn('id', $missingIds)
            ->orderBy('name')
            ->get()
            ->each(function (self $driver) use (&$options) {
                $options[$driver->id] = $driver->displayLabel();
            });

        return $options;
    }

    public function displayLabel(): string
    {
        return self::formatSelectLabel($this->name, $this->username, (bool) $this->is_active, $this->trashed());
    }

    public static function displayLabelForId(?int $driverId): ?string
    {
        if (!$driverId) {
            return null;
        }

        // Include trashed so a deleted driver referenced by a past order still resolves.
        $driver = static::withTrashed()->find($driverId);

        return $driver ? $driver->displayLabel() : null;
    }

    protected static function formatSelectLabel(?string $name, ?string $username, bool $isActive, bool $isDeleted = false): string
    {
        $label = $name ?: $username ?: '-';
        if ($isDeleted) {
            $label .= ' (' . __('drivers.status_labels.deleted') . ')';
        } elseif (!$isActive) {
            $label .= ' (' . __('drivers.status_labels.inactive') . ')';
        }

        return $label;
    }
}
