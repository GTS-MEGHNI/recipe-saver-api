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
