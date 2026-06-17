<?php

namespace App\Http\Middleware;

use App\Providers\RouteServiceProvider;
use Closure;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

class AdminRoleCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (Auth::guard('web_admin')->check()) {
            // check if admin not superadmin, restrict from some page
            if(Auth::guard('web_admin')->user()->role != 'superadmin'){
                $allowed_path = ['dashboard', 'order', 'orders', 'inventory'];
                $currentPath = $request->path();

                // Check if the current path is in the list of restricted paths
                if (!in_array($currentPath, $allowed_path) && !$this->startsWithAny($currentPath, $allowed_path)) {
                    return abort(403);
                }
            }
        }

        return $next($request);
    }

    // Helper function to check if the string starts with any of the given prefixes
    private function startsWithAny($string, array $prefixes)
    {
        foreach ($prefixes as $prefix) {
            if (str_starts_with($string, $prefix . '/')) {
                return true;
            }
        }

        return false;
    }
}
