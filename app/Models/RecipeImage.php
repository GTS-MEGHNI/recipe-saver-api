<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * One photo in a recipe's gallery (many images per recipe). Only the stored file path is kept here;
 * the binary lives on the configured filesystem disk and is exposed to clients as a full URL.
 *
 * @property int $id
 * @property int $recipe_id
 * @property string $path
 * @property int $position
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Recipe $recipe
 */
class RecipeImage extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'path',
        'position',
    ];

    /**
     * Delete the underlying image file whenever the row is deleted, so no orphaned binaries linger.
     */
    protected static function booted(): void
    {
        static::deleting(function (RecipeImage $image): void {
            Storage::disk('public')->delete($image->path);
        });
    }

    /**
     * @return BelongsTo<Recipe, $this>
     */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
        ];
    }
}
