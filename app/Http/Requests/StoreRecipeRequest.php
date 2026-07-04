<?php

namespace App\Http\Requests;

use App\Enums\RecipeCategory;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreRecipeRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * Authentication is handled by the `api.key` middleware, so any request that reaches here is
     * already authorized.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'ingredients' => ['required', 'array'],
            'ingredients.*' => ['required', 'string', 'max:1000'],
            'steps' => ['required', 'array'],
            'steps.*' => ['required', 'string', 'max:5000'],
            'cookTimeMinutes' => ['nullable', 'integer', 'min:0', 'max:65535'],
            'category' => ['nullable', Rule::enum(RecipeCategory::class)],
            'isFavorite' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * Map the camelCase wire payload onto the model's snake_case attributes.
     *
     * `is_favorite` is only included when the client actually sent `isFavorite`, so an ordinary
     * create/edit (which omits it) never clobbers a recipe's existing favorite state — only an
     * explicit favorite toggle carries the field.
     *
     * @return array{title: string, ingredients: list<string>, steps: list<string>, cook_time_minutes: int|null, category: string|null, is_favorite?: bool}
     */
    public function toRecipeAttributes(): array
    {
        /** @var array{title: string, ingredients: list<string>, steps: list<string>, cookTimeMinutes?: int|null, category?: string|null, isFavorite?: bool} $validated */
        $validated = $this->validated();

        $attributes = [
            'title' => $validated['title'],
            'ingredients' => $validated['ingredients'],
            'steps' => $validated['steps'],
            'cook_time_minutes' => $validated['cookTimeMinutes'] ?? null,
            'category' => $validated['category'] ?? null,
        ];

        if (array_key_exists('isFavorite', $validated)) {
            $attributes['is_favorite'] = $validated['isFavorite'];
        }

        return $attributes;
    }
}
