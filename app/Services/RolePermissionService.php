<?php

namespace App\Services;

use App\Role;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RolePermissionService
{
    public function allRoles(): Collection
    {
        return Role::orderByDesc('is_superadmin')
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();
    }

    public function rolesByPortal(): Collection
    {
        return $this->allRoles()->groupBy('portal');
    }

    public function assignableAdminRoles(): Collection
    {
        return Role::where('portal', Role::PORTAL_ADMIN)
            ->orderBy('name')
            ->get();
    }

    public function rolesForPortal(string $portal): Collection
    {
        return Role::where('portal', $portal)->orderBy('name')->get();
    }

    public function findRole(string $slug): ?Role
    {
        if (! Schema::hasTable('roles')) {
            return null;
        }

        return Cache::remember("role.record.{$slug}", 300, function () use ($slug) {
            return Role::where('slug', $slug)->first();
        });
    }

    public function portalDefinitions(string $portal): array
    {
        return config("permissions.portals.{$portal}.permissions", []);
    }

    public function definitionsForRole(Role $role): array
    {
        return $this->portalDefinitions($role->portal);
    }

    public function allowedMap(string $roleSlug): array
    {
        $role = $this->findRole($roleSlug);
        if (!$role) {
            return [];
        }

        if ($role->is_superadmin) {
            return array_fill_keys(array_keys($this->definitionsForRole($role)), true);
        }

        return Cache::remember("role_permissions.map.{$roleSlug}", 300, function () use ($role, $roleSlug) {
            $definitions = $this->definitionsForRole($role);
            $stored = Schema::hasTable('role_permissions')
                ? DB::table('role_permissions')
                    ->where('role', $roleSlug)
                    ->pluck('allowed', 'permission')
                : collect();

            $map = [];
            foreach ($definitions as $key => $definition) {
                if ($stored->has($key)) {
                    $map[$key] = (bool) $stored[$key];
                } else {
                    $map[$key] = $definition['default'] ?? true;
                }
            }

            return $map;
        });
    }

    public function can(string $roleSlug, string $permission): bool
    {
        $role = $this->findRole($roleSlug);
        if (!$role) {
            return false;
        }

        if ($role->is_superadmin) {
            return true;
        }

        $map = $this->allowedMap($roleSlug);

        return $map[$permission] ?? false;
    }

    public function sync(Role $role, array $enabledPermissions): void
    {
        if ($role->is_superadmin) {
            return;
        }

        $definitions = $this->definitionsForRole($role);
        $enabled = array_flip($enabledPermissions);
        $now = now();

        foreach ($definitions as $permission => $definition) {
            DB::table('role_permissions')->updateOrInsert(
                ['role' => $role->slug, 'permission' => $permission],
                [
                    'allowed' => isset($enabled[$permission]),
                    'updated_at' => $now,
                    'created_at' => $now,
                ]
            );
        }

        $this->forgetRoleCache($role->slug);
    }

    public function createRole(array $data, array $enabledPermissions): Role
    {
        $slug = $this->makeUniqueSlug($data['name']);

        $role = Role::create([
            'name' => $data['name'],
            'slug' => $slug,
            'portal' => $data['portal'],
            'description' => $data['description'] ?? null,
            'is_system' => false,
            'is_superadmin' => false,
        ]);

        $this->sync($role, $enabledPermissions);

        return $role;
    }

    public function updateRole(Role $role, array $data, array $enabledPermissions): Role
    {
        if (!$role->is_system) {
            $role->name = $data['name'];
            $role->description = $data['description'] ?? null;
        } else {
            $role->description = $data['description'] ?? $role->description;
        }

        $role->save();
        $this->sync($role, $enabledPermissions);

        return $role;
    }

    public function deleteRole(Role $role): void
    {
        if ($role->is_system) {
            throw new \InvalidArgumentException('System roles cannot be deleted.');
        }

        if (DB::table('admins')->where('role', $role->slug)->exists()) {
            throw new \InvalidArgumentException('This role is assigned to admin users and cannot be deleted.');
        }

        if (DB::table('users')->where('role_slug', $role->slug)->exists()) {
            throw new \InvalidArgumentException('This role is assigned to customers and cannot be deleted.');
        }

        if (DB::table('drivers')->where('role_slug', $role->slug)->exists()) {
            throw new \InvalidArgumentException('This role is assigned to drivers and cannot be deleted.');
        }

        DB::table('role_permissions')->where('role', $role->slug)->delete();
        $this->forgetRoleCache($role->slug);
        $role->delete();
    }

    public function makeUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name);
        if ($base === '') {
            $base = 'role';
        }

        $slug = $base;
        $counter = 2;

        while ($this->slugExists($slug, $ignoreId)) {
            $slug = $base . '-' . $counter;
            $counter++;
        }

        return $slug;
    }

    public function memberRoutePermission(?string $routeName): ?string
    {
        if (!$routeName) {
            return null;
        }

        return config("permissions.member_routes.{$routeName}");
    }

    public function driverRoutePermission(?string $routeName): ?string
    {
        if (!$routeName) {
            return null;
        }

        return config("permissions.driver_routes.{$routeName}");
    }

    public function memberPathPermission(string $path): ?string
    {
        foreach (config('permissions.member_paths', []) as $pattern => $permission) {
            if ($path === $pattern || str_starts_with($path, $pattern . '/')) {
                return $permission;
            }
        }

        return null;
    }

    public function memberFilePermission(string $filename): ?string
    {
        foreach (config('permissions.member_file_permissions', []) as $needle => $permission) {
            if (str_contains($filename, $needle)) {
                return $permission;
            }
        }

        return null;
    }

    private function slugExists(string $slug, ?int $ignoreId = null): bool
    {
        $query = Role::where('slug', $slug);
        if ($ignoreId) {
            $query->where('id', '!=', $ignoreId);
        }

        return $query->exists();
    }

    private function forgetRoleCache(string $slug): void
    {
        Cache::forget("role.record.{$slug}");
        Cache::forget("role_permissions.map.{$slug}");
    }
}
