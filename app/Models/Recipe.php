<?php

namespace App\Models;

use App\Enums\RecipeCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $title
 * @property list<string> $ingredients
 * @property list<string> $steps
 * @property int|null $cook_time_minutes
 * @property RecipeCategory|null $category
 * @property bool $is_favorite
 * @property string|null $cover_image_path
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property Carbon|null $deleted_at
 * @property-read Collection<int, RecipeImage> $images
 */
class Recipe extends Model
{
    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'title',
        'ingredients',
        'steps',
        'cook_time_minutes',
        'category',
        'is_favorite',
    ];

    /**
     * Default attribute values. Ensures a freshly created recipe reports `is_favorite` as `false`
     * (not null) in the create response, before it's reloaded from the DB where the column default
     * applies.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_favorite' => false,
    ];

    /**
     * Delete the cover file and every gallery image (rows + files) only when the recipe is
     * permanently deleted. Soft deletes preserve everything so the recipe can be restored intact.
     * Deleting each image via Eloquent triggers its own file cleanup, so nothing is orphaned.
     */
    protected static function booted(): void
    {
        static::forceDeleting(function (Recipe $recipe): void {
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
            'is_favorite' => 'boolean',
        ];
    }
}
