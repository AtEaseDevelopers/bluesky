<?php

namespace App\Http\Middleware;

use App\User;
use Closure;
use Illuminate\Support\Facades\Auth;

class AuthenticateMember
{
    public function handle($request, Closure $next)
    {
        if (!Auth::guard('web')->check()) {
            return redirect()->route('login')->with('error', 'Please login to continue.');
        }

        $user = Auth::guard('web')->user();

        if (!$user->hasCompletedRegistration()) {
            Auth::guard('web')->logout();

            return redirect()->route('login')->with('error', 'Please complete registration using your invitation link first.');
        }

        if ($user->status !== User::$user_status['active']) {
            Auth::guard('web')->logout();

            return redirect()->route('login')->with('error', 'Your account is not active. Please contact us for assistance.');
        }

        return $next($request);
    }
}
