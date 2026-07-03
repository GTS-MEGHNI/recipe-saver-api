<?php

use App\Http\Controllers\RecipeController;
use App\Http\Controllers\RecipeCoverController;
use App\Http\Controllers\RecipeImageController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json(['status' => 'ok']));

/*
| Recipes API — authenticated by a single static key via the `X-API-Key` header (no accounts).
| Shape mirrors the Android app's Recipe entity; see the mobile repo's architecture.md §12.
*/
Route::middleware('api.key')->group(function (): void {
    Route::apiResource('recipes', RecipeController::class);

    Route::post('recipes/{recipe}/cover', [RecipeCoverController::class, 'store']);
    Route::delete('recipes/{recipe}/cover', [RecipeCoverController::class, 'destroy']);

    Route::apiResource('recipes.images', RecipeImageController::class)
        ->scoped()
        ->only(['store', 'destroy']);
});
