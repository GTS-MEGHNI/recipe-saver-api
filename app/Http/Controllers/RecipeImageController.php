<?php

namespace App\Http\Controllers;

use App\Http\Resources\RecipeImageResource;
use App\Models\Recipe;
use App\Models\RecipeImage;
use App\Services\ImageOptimizer;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class RecipeImageController extends Controller
{
    /**
     * Add a photo to a recipe's gallery, appended after the current last image. The upload is
     * downscaled and compressed before it is stored.
     */
    public function store(Request $request, Recipe $recipe, ImageOptimizer $optimizer): RecipeImageResource
    {
        $request->validate([
            'image' => ['required', 'image', 'max:20480'],
        ]);

        $path = $optimizer->store($request->file('image'), "recipes/{$recipe->id}/gallery");

        $image = $recipe->images()->create([
            'path' => $path,
            'position' => (int) $recipe->images()->max('position') + 1,
        ]);

        return RecipeImageResource::make($image);
    }

    /**
     * Remove a single gallery photo (its row and file). Scoped binding guarantees the image belongs
     * to the given recipe.
     */
    public function destroy(Recipe $recipe, RecipeImage $image): Response
    {
        $image->delete();

        return response()->noContent();
    }
}
