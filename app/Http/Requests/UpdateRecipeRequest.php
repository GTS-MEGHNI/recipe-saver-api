<?php

namespace App\Http\Requests;

/**
 * A recipe update (PUT) is a full replacement, so it validates and maps exactly like a create.
 */
class UpdateRecipeRequest extends StoreRecipeRequest
{
}
