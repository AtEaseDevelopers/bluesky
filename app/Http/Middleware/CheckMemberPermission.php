<?php

namespace App\Http\Middleware;

use App\Services\RolePermissionService;
use Closure;
use Illuminate\Support\Facades\Auth;

class CheckMemberPermission
{
    public function handle($request, Closure $next)
    {
        $service = app(RolePermissionService::class);
        $permission = $service->memberRoutePermission($request->route()?->getName());

        if (!$permission) {
            $permission = $this->resolvePathPermission(trim($request->path(), '/'), $service);
        }

        if ($permission) {
            $roleSlug = $this->customerRoleSlug();
            if (!$service->can($roleSlug, $permission)) {
                abort(403, 'You do not have permission to access this feature.');
            }
        }

        return $next($request);
    }

    private function customerRoleSlug(): string
    {
        $user = Auth::guard('web')->user();

        return $user ? ($user->role_slug ?? 'customer') : 'customer';
    }

    private function resolvePathPermission(string $path, RolePermissionService $service): ?string
    {
        foreach (config('permissions.member_paths', []) as $prefix => $permission) {
            if ($path === $prefix || str_starts_with($path, $prefix . '/')) {
                return $permission;
            }
        }

        return null;
    }
}
