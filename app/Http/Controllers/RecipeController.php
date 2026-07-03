<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRecipeRequest;
use App\Http\Requests\UpdateRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use Illuminate\Http\Response;

class RecipeController extends Controller
{
    /**
     * Display a listing of the recipes, newest first.
     *
     * @return \Illuminate\Http\Resources\Json\AnonymousResourceCollection
     */
    public function index()
    {
        $recipes = Recipe::query()
            ->with('images')
            ->latest()
            ->get();

        return RecipeResource::collection($recipes);
    }

    /**
     * Store a newly created recipe.
     */
    public function store(StoreRecipeRequest $request): RecipeResource
    {
        $recipe = Recipe::create($request->toRecipeAttributes());

        return RecipeResource::make($recipe->load('images'));
    }

    /**
     * Display the specified recipe.
     */
    public function show(Recipe $recipe): RecipeResource
    {
        return RecipeResource::make($recipe->load('images'));
    }

    /**
     * Update the specified recipe (full replacement).
     */
    public function update(UpdateRecipeRequest $request, Recipe $recipe): RecipeResource
    {
        $recipe->update($request->toRecipeAttributes());

        return RecipeResource::make($recipe->load('images'));
    }

    /**
     * Remove the specified recipe along with its images (files and rows).
     */
    public function destroy(Recipe $recipe): Response
    {
        $recipe->delete();

        return response()->noContent();
    }
}
