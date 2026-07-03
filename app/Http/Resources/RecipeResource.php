<?php

namespace App\Http\Resources;

use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * Wire shape mirrors the Android `Recipe` entity (camelCase keys) so the mobile DTO maps one-to-one.
 *
 * @mixin Recipe
 */
class RecipeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'ingredients' => $this->ingredients,
            'steps' => $this->steps,
            'cookTimeMinutes' => $this->cook_time_minutes,
            'category' => $this->category?->value,
            'coverImageUrl' => $this->cover_image_path !== null
                ? Storage::disk('public')->url($this->cover_image_path)
                : null,
            'images' => RecipeImageResource::collection($this->whenLoaded('images')),
            'createdAt' => $this->created_at->getTimestampMs(),
        ];
    }
}
