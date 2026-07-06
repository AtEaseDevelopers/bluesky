<?php

namespace App\Http\Middleware;

use App\Admin;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AdminRoleCheck
{
    public function handle(Request $request, Closure $next)
    {
        /** @var Admin|null $admin */
        $admin = Auth::guard('web_admin')->user();

        if (! $admin) {
            return $next($request);
        }

        if ($admin->isSuperadmin()) {
            return $next($request);
        }

        $segment = $this->resolveSegment($request->path());

        if (in_array($segment, config('admin_permissions.superadmin_only_segments', []), true)) {
            abort(403, 'Only superadmin can access this area.');
        }

        $access = $this->resolveAccess($request);

        if ($access === null) {
            return $next($request);
        }

        if ($admin->canModule($access['module'], $this->effectiveCapability($access['module'], $access['capability']))) {
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
        if (! str_starts_with($path, $prefix)) {
            return '';
        }

        $relative = substr($path, strlen($prefix));

        return explode('/', $relative)[0] ?? '';
    }

    private function resolveAccess(Request $request): ?array
    {
        $path = $request->path();
        $prefix = 'admin/';
        if (! str_starts_with($path, $prefix)) {
            return null;
        }

        $relative = substr($path, strlen($prefix));
        $method = strtoupper($request->method());

        foreach (config('admin_permissions.capability_overrides', []) as $pattern => $access) {
            if ($this->pathMatches($relative, $pattern)) {
                if (in_array($method, ['PUT', 'PATCH', 'DELETE'], true) && ($access['capability'] ?? '') === 'view') {
                    return ['module' => $access['module'], 'capability' => 'edit'];
                }

                return $access;
            }
        }

        $segment = explode('/', $relative)[0] ?? '';
        if ($segment === '') {
            return null;
        }

        $module = config("admin_permissions.route_modules.{$segment}");
        if (! $module) {
            return null;
        }

        if ($method === 'GET') {
            if (preg_match('#/(create|add|invite)$#', $relative)) {
                return ['module' => $module, 'capability' => 'create'];
            }

            if (preg_match('#/(edit|remove)(/|$)#', $relative)) {
                return ['module' => $module, 'capability' => 'edit'];
            }

            return ['module' => $module, 'capability' => 'view'];
        }

        if ($method === 'POST' && $this->isViewPostRoute($relative)) {
            return ['module' => $module, 'capability' => 'view'];
        }

        if (in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            if ($this->isCreateRoute($relative, $method)) {
                return ['module' => $module, 'capability' => 'create'];
            }

            return ['module' => $module, 'capability' => 'edit'];
        }

        return ['module' => $module, 'capability' => 'view'];
    }

    private function pathMatches(string $relative, string $pattern): bool
    {
        if ($pattern === $relative) {
            return true;
        }

        if (str_ends_with($pattern, '*')) {
            $prefix = rtrim($pattern, '*');

            return str_starts_with($relative, $prefix);
        }

        return false;
    }

    private function isViewPostRoute(string $relative): bool
    {
        foreach (config('admin_permissions.view_post_routes', []) as $pattern) {
            if ($this->pathMatches($relative, $pattern)) {
                return true;
            }
        }

        return str_starts_with($relative, 'fetch-')
            || str_contains($relative, '/get-')
            || str_contains($relative, 'order-products-list');
    }

    private function isCreateRoute(string $relative, string $method): bool
    {
        if ($method !== 'POST') {
            return false;
        }

        foreach (config('admin_permissions.create_post_routes', []) as $pattern) {
            if ($this->pathMatches($relative, $pattern)) {
                return true;
            }
        }

        return preg_match('#/(add|invite)(/|$)#', $relative) === 1;
    }

    private function effectiveCapability(string $module, string $capability): string
    {
        $definition = config("permissions.portals.admin.permissions.{$module}");
        $capabilities = $definition['capabilities'] ?? [];

        if ($capabilities === [] || isset($capabilities[$capability])) {
            return $capability;
        }

        if ($capability === 'create' && isset($capabilities['edit'])) {
            return 'edit';
        }

        return $capability;
    }
}
