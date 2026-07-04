<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Static API Key
    |--------------------------------------------------------------------------
    |
    | The single shared key that authenticates every request to the recipes
    | API via the `X-API-Key` header. There are no user accounts: one key maps
    | to one shared dataset, which is what lets any device running the mobile
    | app pull down the same recipes. Treat this key as low-trust.
    |
    */

    'api_key' => env('RECIPE_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Force HTTPS
    |--------------------------------------------------------------------------
    |
    | When true, all generated URLs (routes, assets, storage links) use the
    | `https` scheme regardless of the incoming request. Enable this in
    | production, where Caddy terminates TLS, so links never leak as plain HTTP.
    |
    */

    'force_https' => (bool) env('FORCE_HTTPS', false),

    /*
    |--------------------------------------------------------------------------
    | Auth Failure Throttling
    |--------------------------------------------------------------------------
    |
    | Brute-force protection for the static API key. Once an IP sends
    | `max_failures` bad/missing keys within `window_seconds`, it is locked out
    | for `lockout_seconds` (429). The lockout is deliberately finite: shared
    | carrier NAT means many real users can sit behind one IP, so permanent
    | banning belongs at the firewall (Fail2ban), not here.
    |
    */

    'auth_throttle' => [
        'max_failures' => (int) env('RECIPE_AUTH_MAX_FAILURES', 5),
        'window_seconds' => (int) env('RECIPE_AUTH_WINDOW', 60),
        'lockout_seconds' => (int) env('RECIPE_AUTH_LOCKOUT', 3600),
    ],

    /*
    |--------------------------------------------------------------------------
    | Image Optimization
    |--------------------------------------------------------------------------
    |
    | Uploaded photos are downscaled and re-encoded before they are stored, so
    | full-resolution camera images never hit disk. `max_edge` caps the longest
    | side (in pixels); `jpeg_quality` (0-100) controls the output compression.
    |
    */

    'images' => [
        'max_edge' => (int) env('RECIPE_IMAGE_MAX_EDGE', 1080),
        'jpeg_quality' => (int) env('RECIPE_IMAGE_JPEG_QUALITY', 85),
    ],

];
