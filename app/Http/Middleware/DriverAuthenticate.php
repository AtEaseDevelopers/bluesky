<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Auth;

class DriverAuthenticate
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
        if (Auth::guard('web_driver')->check()) {
            // Block deactivated driver accounts.
            if (!Auth::guard('web_driver')->user()->is_active) {
                Auth::guard('web_driver')->logout();
                return redirect(route('driver.login'))->with('error', 'Your account has been deactivated.');
            }

            return $next($request);
        }

        return redirect(route('driver.login'));
    }
}
