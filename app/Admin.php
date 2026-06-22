<?php

namespace App;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Admin extends Authenticatable
{
    use Notifiable;

    public const ROLE_SUPERADMIN = 'superadmin';
    public const ROLE_ADMIN = 'admin';

    public const STATUS_ACTIVE = 'active';
    public const STATUS_INACTIVE = 'inactive';

    protected $fillable = [
        'name', 'email', 'username', 'password', 'role', 'status', 'locale',
    ];

    protected $hidden = [
        'password', 'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public static $attribute_rules = [
        'username' => ['required', 'string'],
        'password' => ['required', 'string'],
    ];

    public function isSuperadmin(): bool
    {
        return $this->role === self::ROLE_SUPERADMIN;
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isActive(): bool
    {
        return ($this->status ?? self::STATUS_ACTIVE) === self::STATUS_ACTIVE;
    }

    public function roleLabel(): string
    {
        $role = app(\App\Services\RolePermissionService::class)->findRole((string) $this->role);

        return $role ? $role->name : ucfirst((string) $this->role);
    }

    public function canAccessModule(string $module): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return app(\App\Services\RolePermissionService::class)->can((string) $this->role, $module);
    }

    public function canManageRolePermissions(): bool
    {
        return $this->isSuperadmin();
    }

    public function canManageAdminUsers(): bool
    {
        return $this->isSuperadmin();
    }
}
