<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKey
{
    /**
     * Reject any request whose `X-API-Key` header does not match the configured static key.
     *
     * This is the app's only authentication: a single shared key (no user accounts), compared in
     * constant time. Requests without the header, or with the wrong key, get a 401.
     *
     * To blunt brute-force attempts, repeated failures from one IP are throttled: after
     * `recipes.auth_throttle.max_failures` bad keys within `window_seconds`, the IP is locked out
     * with a 429 for `lockout_seconds`. Each locked-out IP is logged once per lockout window so an
     * attacker cannot flood the log (and fill the disk).
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip() ?? 'unknown';

        if (RateLimiter::tooManyAttempts($this->lockoutKey($ip), 1)) {
            $this->rejectLockedOut($ip);
        }

        $expected = config('recipes.api_key');
        $provided = $request->header('X-API-Key');

        if (is_string($expected) && $expected !== '' && is_string($provided) && hash_equals($expected, $provided)) {
            return $next($request);
        }

        $this->recordFailure($ip);

        abort(Response::HTTP_UNAUTHORIZED, 'Invalid or missing API key.');
    }

    /**
     * Count a failed attempt and, once the per-window threshold is crossed, start a lockout.
     */
    private function recordFailure(string $ip): void
    {
        $maxFailures = (int) config('recipes.auth_throttle.max_failures');
        $window = (int) config('recipes.auth_throttle.window_seconds');

        RateLimiter::hit($this->failureKey($ip), $window);
        $this->logFailureOnce($ip);

        if (RateLimiter::tooManyAttempts($this->failureKey($ip), $maxFailures)) {
            RateLimiter::clear($this->failureKey($ip));
            RateLimiter::hit($this->lockoutKey($ip), (int) config('recipes.auth_throttle.lockout_seconds'));

            // Distinct, high-signal line emitted once per lockout — this is what Fail2ban bans on.
            Log::warning('API key lockout triggered', [
                'ip' => $ip,
                'at' => now()->toIso8601String(),
            ]);

            $this->rejectLockedOut($ip);
        }
    }

    /**
     * Abort with 429 and a `Retry-After` header for the remaining lockout duration.
     */
    private function rejectLockedOut(string $ip): never
    {
        $retryAfter = RateLimiter::availableIn($this->lockoutKey($ip));

        abort(Response::HTTP_TOO_MANY_REQUESTS, 'Too many invalid API key attempts. Try again later.', [
            'Retry-After' => (string) $retryAfter,
        ]);
    }

    /**
     * Log an offending IP at most once per lockout window to avoid flooding the log.
     */
    private function logFailureOnce(string $ip): void
    {
        $marker = 'api-auth-logged:'.$ip;

        if (Cache::add($marker, true, (int) config('recipes.auth_throttle.lockout_seconds'))) {
            Log::warning('Rejected API request: invalid or missing key', [
                'ip' => $ip,
                'at' => now()->toIso8601String(),
            ]);
        }
    }

    private function failureKey(string $ip): string
    {
        return 'api-auth-fail:'.$ip;
    }

    private function lockoutKey(string $ip): string
    {
        return 'api-auth-lock:'.$ip;
    }
}
