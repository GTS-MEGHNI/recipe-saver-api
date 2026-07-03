<?php

namespace App\Models;

use App\Enums\RecipeCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $title
 * @property list<string> $ingredients
 * @property list<string> $steps
 * @property int|null $cook_time_minutes
 * @property RecipeCategory|null $category
 * @property string|null $cover_image_path
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read \Illuminate\Database\Eloquent\Collection<int, RecipeImage> $images
 */
class Recipe extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'ingredients',
        'steps',
        'cook_time_minutes',
        'category',
    ];

    /**
     * Delete the cover file and every gallery image (rows + files) when the recipe is deleted.
     * Deleting each image via Eloquent triggers its own file cleanup, so nothing is orphaned.
     */
    protected static function booted(): void
    {
        static::deleting(function (Recipe $recipe): void {
            if ($recipe->cover_image_path !== null) {
                Storage::disk('public')->delete($recipe->cover_image_path);
            }

            $recipe->images()->get()->each->delete();
        });
    }

    /**
     * The images that make up this recipe's gallery, ordered by gallery position.
     *
     * @return HasMany<RecipeImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(RecipeImage::class)->orderBy('position');
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ingredients' => 'array',
            'steps' => 'array',
            'cook_time_minutes' => 'integer',
            'category' => RecipeCategory::class,
        ];
    }
}
