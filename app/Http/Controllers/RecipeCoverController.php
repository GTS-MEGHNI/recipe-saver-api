<?php

namespace App\Http\Controllers;

use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use App\Services\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class RecipeCoverController extends Controller
{
    /**
     * Set or replace a recipe's cover photo, deleting the previous cover file if there was one. The
     * upload is downscaled and compressed before it is stored.
     */
    public function store(Request $request, Recipe $recipe, ImageOptimizer $optimizer): RecipeResource
    {
        $request->validate([
            'image' => ['required', 'image', 'max:20480'],
        ]);

        if ($recipe->cover_image_path !== null) {
            Storage::disk('public')->delete($recipe->cover_image_path);
        }

        $recipe->forceFill([
            'cover_image_path' => $optimizer->store($request->file('image'), "recipes/{$recipe->id}/cover"),
        ])->save();

        return RecipeResource::make($recipe->load('images'));
    }

    /**
     * Remove a recipe's cover photo (file and reference).
     */
    public function destroy(Recipe $recipe): RecipeResource
    {
        if ($recipe->cover_image_path !== null) {
            Storage::disk('public')->delete($recipe->cover_image_path);
            $recipe->forceFill(['cover_image_path' => null])->save();
        }

        return RecipeResource::make($recipe->load('images'));
    }
}
