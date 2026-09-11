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
        return $this->canModule($module, 'view');
    }

    public function canModule(string $module, string $capability = 'view'): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return app(\App\Services\RolePermissionService::class)
            ->canModule((string) $this->role, $module, $capability);
    }

    /**
     * The route name of the first module this admin may view.
     *
     * Used to pick a safe post-login/redirect target so admins whose role
     * cannot access the dashboard are not sent to a 403 page. Returns null
     * when the admin has no accessible module at all.
     */
    public function defaultLandingRoute(): ?string
    {
        foreach (config('admin_permissions.landing_routes', []) as $module => $routeName) {
            if ($this->canAccessModule($module)) {
                return $routeName;
            }
        }

        return null;
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
