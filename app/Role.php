<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    public const PORTAL_ADMIN = 'admin';
    public const PORTAL_CUSTOMER = 'customer';
    public const PORTAL_DRIVER = 'driver';

    protected $fillable = [
        'name',
        'slug',
        'portal',
        'description',
        'is_system',
        'is_superadmin',
    ];

    protected $casts = [
        'is_system' => 'boolean',
        'is_superadmin' => 'boolean',
    ];

    public function getRouteKeyName()
    {
        return 'slug';
    }

    public function portalLabel(): string
    {
        return config("permissions.portals.{$this->portal}.label", ucfirst($this->portal));
    }
}
