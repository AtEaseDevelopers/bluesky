<?php

namespace App\Http\Middleware;

use App\Driver;
use Closure;
use Illuminate\Http\Request;

class AuthenticateDriver
{
    public function handle(Request $request, Closure $next)
    {
        $driver = Driver::findByToken($request->bearerToken());

        if (!$driver) {
            return response()->json(['success' => false, 'message' => 'Unauthorized.'], 401);
        }

        $request->attributes->set('driver', $driver);

        return $next($request);
    }
}
