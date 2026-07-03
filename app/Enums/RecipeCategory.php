<?php

namespace App\Enums;

/**
 * Fixed set of recipe categories, mirroring the Android app's
 * `com.recipesaver.app.data.local.entities.RecipeCategory` enum. Persisted and exposed over the API
 * as the case name (e.g. "FOOD"). French labels/icons live in the mobile UI layer, so this stays a
 * plain domain type with no display concerns.
 */
enum RecipeCategory: string
{
    case Drinks = 'DRINKS';
    case Food = 'FOOD';
    case Pastry = 'PASTRY';
    case Verrine = 'VERRINE';
    case Cake = 'CAKE';
    case Confiture = 'CONFITURE';
}
