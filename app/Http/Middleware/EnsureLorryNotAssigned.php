<?php

namespace App\Http\Middleware;

use App\Driver;
use Closure;
use Illuminate\Support\Facades\Auth;

/**
 * Guards vehicle selection so a driver cannot claim a lorry that is already
 * assigned to another active driver. A lorry is only "free" when no other
 * active driver currently holds it as their lorry number.
 */
class EnsureLorryNotAssigned
{
    public function handle($request, Closure $next)
    {
        $lorry = $request->input('lorry_number');
        $driver = Auth::guard('web_driver')->user();

        if ($lorry && $driver) {
            $assignedToAnother = Driver::where('lorry_number', $lorry)
                ->where('id', '!=', $driver->id)
                ->where('is_active', true)
                ->exists();

            if ($assignedToAnother) {
                return back()
                    ->withInput()
                    ->withErrors([
                        'lorry_number' => 'That vehicle is already assigned to another driver. Please choose a free one.',
                    ]);
            }
        }

        return $next($request);
    }
}
