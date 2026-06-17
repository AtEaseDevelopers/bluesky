<?php

namespace App\Http\Middleware;

use App\Admin;
use Closure;
use Illuminate\Support\Facades\Auth;

class AdminRoleCheck
{
    public function handle($request, Closure $next)
    {
        /** @var Admin|null $admin */
        $admin = Auth::guard('web_admin')->user();

        if (!$admin) {
            return $next($request);
        }

        if ($admin->isSuperadmin()) {
            return $next($request);
        }

        $segment = $this->resolveSegment($request->path());

        if (in_array($segment, config('admin_permissions.superadmin_only_segments', []), true)) {
            abort(403, 'Only superadmin can access this area.');
        }

        $module = $this->resolveModule($request->path());

        if ($module && $admin->canAccessModule($module)) {
            return $next($request);
        }

        if ($segment === '') {
            return $next($request);
        }

        abort(403, 'You do not have permission to access this module.');
    }

    private function resolveSegment(string $path): string
    {
        $prefix = 'admin/';
        if (!str_starts_with($path, $prefix)) {
            return '';
        }

        $relative = substr($path, strlen($prefix));

        return explode('/', $relative)[0] ?? '';
    }

    private function resolveModule(string $path): ?string
    {
        $segment = $this->resolveSegment($path);

        if ($segment === '') {
            return null;
        }

        return config("admin_permissions.route_modules.{$segment}");
    }
}
