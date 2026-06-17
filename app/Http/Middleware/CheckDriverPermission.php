<?php

namespace App\Http\Middleware;

use App\Services\RolePermissionService;
use Closure;
use Illuminate\Support\Facades\Auth;

class CheckDriverPermission
{
    public function handle($request, Closure $next)
    {
        $service = app(RolePermissionService::class);
        $permission = $service->driverRoutePermission($request->route()?->getName());

        if ($permission) {
            $driver = Auth::guard('web_driver')->user();
            $roleSlug = $driver->role_slug ?? 'driver';

            if (!$service->can($roleSlug, $permission)) {
                abort(403, 'You do not have permission to access this feature.');
            }
        }

        return $next($request);
    }
}
