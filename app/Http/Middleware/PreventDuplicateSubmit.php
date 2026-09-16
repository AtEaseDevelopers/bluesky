<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Guards state-changing form posts against accidental double-submission
 * (double-click, browser retry, back-then-resubmit).
 *
 * Each form render emits a one-time `_submit_token`. The FIRST request carrying
 * a given token claims it atomically via Cache::add; any later request reusing
 * the same token short-circuits without re-running the action. Forms without a
 * token are passed through unchanged (backward compatible).
 */
class PreventDuplicateSubmit
{
    /** How long a claimed token is remembered — long enough to cover slow re-posts. */
    private const TTL_SECONDS = 600;

    public function handle(Request $request, Closure $next)
    {
        $token = trim((string) $request->input('_submit_token', ''));

        // Cache::add only succeeds for the first caller of a key, atomically, so
        // a duplicate submit (same token) fails the add and is treated as a repeat.
        if ($token !== '' && !Cache::add($this->key($token), true, self::TTL_SECONDS)) {
            return back()->with('warning', __('orders.duplicate_submit_ignored'));
        }

        return $next($request);
    }

    private function key(string $token): string
    {
        return 'submit_token:' . sha1($token);
    }
}
