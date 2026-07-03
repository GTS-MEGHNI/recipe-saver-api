<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKey
{
    /**
     * Reject any request whose `X-API-Key` header does not match the configured static key.
     *
     * This is the app's only authentication: a single shared key (no user accounts), compared in
     * constant time. Requests without the header, or with the wrong key, get a 401.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('recipes.api_key');
        $provided = $request->header('X-API-Key');

        if (! is_string($expected) || $expected === '' || ! is_string($provided) || ! hash_equals($expected, $provided)) {
            abort(Response::HTTP_UNAUTHORIZED, 'Invalid or missing API key.');
        }

        return $next($request);
    }
}
