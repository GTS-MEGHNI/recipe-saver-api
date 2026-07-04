<?php

namespace Tests\Feature;

use App\Enums\RecipeCategory;
use App\Models\Recipe;
use App\Models\RecipeImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RecipeApiTest extends TestCase
{
    use RefreshDatabase;

    private const API_KEY = 'test-api-key';

    protected function setUp(): void
    {
        parent::setUp();

        config(['recipes.api_key' => self::API_KEY]);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<string, string>
     */
    private function keyHeaders(array $headers = []): array
    {
        return array_merge(['X-API-Key' => self::API_KEY], $headers);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function makeRecipe(array $attributes = []): Recipe
    {
        return Recipe::create(array_merge([
            'title' => 'Tarte aux pommes',
            'ingredients' => ['pommes', 'sucre', 'pâte'],
            'steps' => ['Éplucher les pommes', 'Cuire 40 minutes'],
            'cook_time_minutes' => 40,
            'category' => RecipeCategory::Pastry,
        ], $attributes));
    }

    public function test_requests_without_a_valid_api_key_are_rejected(): void
    {
        $this->makeRecipe();

        $this->getJson('/api/recipes')->assertUnauthorized();
        $this->getJson('/api/recipes', ['X-API-Key' => 'wrong'])->assertUnauthorized();
    }

    public function test_it_lists_recipes_newest_first(): void
    {
        $older = $this->makeRecipe(['title' => 'Ancienne']);
        $older->forceFill(['created_at' => now()->subDay()])->save();
        $newer = $this->makeRecipe(['title' => 'Récente']);

        $response = $this->getJson('/api/recipes', $this->keyHeaders());

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_it_shows_a_recipe_in_the_mobile_wire_shape(): void
    {
        $recipe = $this->makeRecipe();

        $response = $this->getJson("/api/recipes/{$recipe->id}", $this->keyHeaders());

        $response->assertOk()
            ->assertJsonPath('data.id', $recipe->id)
            ->assertJsonPath('data.title', 'Tarte aux pommes')
            ->assertJsonPath('data.ingredients', ['pommes', 'sucre', 'pâte'])
            ->assertJsonPath('data.steps', ['Éplucher les pommes', 'Cuire 40 minutes'])
            ->assertJsonPath('data.cookTimeMinutes', 40)
            ->assertJsonPath('data.category', 'PASTRY')
            ->assertJsonPath('data.coverImageUrl', null)
            ->assertJsonPath('data.images', []);

        $this->assertIsInt($response->json('data.createdAt'));
    }

    public function test_it_creates_a_recipe(): void
    {
        $payload = [
            'title' => 'Limonade',
            'ingredients' => ['citron', 'eau', 'sucre'],
            'steps' => ['Presser les citrons', 'Mélanger'],
            'cookTimeMinutes' => 10,
            'category' => 'DRINKS',
        ];

        $response = $this->postJson('/api/recipes', $payload, $this->keyHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.title', 'Limonade')
            ->assertJsonPath('data.category', 'DRINKS')
            ->assertJsonPath('data.cookTimeMinutes', 10);

        $this->assertDatabaseHas('recipes', [
            'title' => 'Limonade',
            'category' => 'DRINKS',
            'cook_time_minutes' => 10,
        ]);
    }

    public function test_it_creates_a_recipe_without_optional_fields(): void
    {
        $response = $this->postJson('/api/recipes', [
            'title' => 'Eau plate',
            'ingredients' => ['eau'],
            'steps' => ['Servir'],
        ], $this->keyHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.cookTimeMinutes', null)
            ->assertJsonPath('data.category', null);
    }

    public function test_it_validates_recipe_creation(): void
    {
        $response = $this->postJson('/api/recipes', [
            'title' => '',
            'ingredients' => 'not-an-array',
            'steps' => [],
            'category' => 'NOPE',
        ], $this->keyHeaders());

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['title', 'ingredients', 'steps', 'category']);
    }

    public function test_it_updates_a_recipe(): void
    {
        $recipe = $this->makeRecipe();

        $response = $this->putJson("/api/recipes/{$recipe->id}", [
            'title' => 'Tarte aux poires',
            'ingredients' => ['poires', 'sucre'],
            'steps' => ['Éplucher', 'Cuire'],
            'cookTimeMinutes' => null,
            'category' => 'CAKE',
        ], $this->keyHeaders());

        $response->assertOk()
            ->assertJsonPath('data.title', 'Tarte aux poires')
            ->assertJsonPath('data.cookTimeMinutes', null)
            ->assertJsonPath('data.category', 'CAKE');

        $this->assertDatabaseHas('recipes', [
            'id' => $recipe->id,
            'title' => 'Tarte aux poires',
            'cook_time_minutes' => null,
        ]);
    }

    public function test_recipes_are_not_favorite_by_default(): void
    {
        $recipe = $this->makeRecipe();

        $this->getJson("/api/recipes/{$recipe->id}", $this->keyHeaders())
            ->assertOk()
            ->assertJsonPath('data.isFavorite', false);
    }

    public function test_it_toggles_the_favorite_flag(): void
    {
        $recipe = $this->makeRecipe();

        $this->putJson("/api/recipes/{$recipe->id}", [
            'title' => $recipe->title,
            'ingredients' => $recipe->ingredients,
            'steps' => $recipe->steps,
            'isFavorite' => true,
        ], $this->keyHeaders())
            ->assertOk()
            ->assertJsonPath('data.isFavorite', true);

        $this->assertDatabaseHas('recipes', ['id' => $recipe->id, 'is_favorite' => true]);
    }

    public function test_an_edit_without_the_flag_preserves_the_favorite_state(): void
    {
        $recipe = $this->makeRecipe(['is_favorite' => true]);

        // A plain edit omits isFavorite entirely; the recipe must stay favorite.
        $this->putJson("/api/recipes/{$recipe->id}", [
            'title' => 'Tarte renommée',
            'ingredients' => $recipe->ingredients,
            'steps' => $recipe->steps,
        ], $this->keyHeaders())
            ->assertOk()
            ->assertJsonPath('data.title', 'Tarte renommée')
            ->assertJsonPath('data.isFavorite', true);
    }

    public function test_it_soft_deletes_a_recipe_and_preserves_its_images(): void
    {
        Storage::fake('public');
        $recipe = $this->makeRecipe();
        $recipe->forceFill(['cover_image_path' => 'recipes/1/cover/c.jpg'])->save();
        Storage::disk('public')->put('recipes/1/cover/c.jpg', 'x');
        Storage::disk('public')->put('recipes/1/gallery/g.jpg', 'y');
        $recipe->images()->create(['path' => 'recipes/1/gallery/g.jpg', 'position' => 0]);

        $this->deleteJson("/api/recipes/{$recipe->id}", [], $this->keyHeaders())
            ->assertNoContent();

        // Row is soft deleted, images and files are kept so the recipe can be restored intact.
        $this->assertSoftDeleted('recipes', ['id' => $recipe->id]);
        $this->assertDatabaseHas('recipe_images', ['recipe_id' => $recipe->id]);
        Storage::disk('public')->assertExists('recipes/1/cover/c.jpg');
        Storage::disk('public')->assertExists('recipes/1/gallery/g.jpg');
    }

    public function test_soft_deleted_recipes_are_excluded_from_the_api(): void
    {
        $recipe = $this->makeRecipe();
        $recipe->delete();

        $this->getJson('/api/recipes', $this->keyHeaders())
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson("/api/recipes/{$recipe->id}", $this->keyHeaders())
            ->assertNotFound();
    }

    public function test_force_deleting_a_recipe_removes_its_image_files(): void
    {
        Storage::fake('public');
        $recipe = $this->makeRecipe();
        $recipe->forceFill(['cover_image_path' => 'recipes/1/cover/c.jpg'])->save();
        Storage::disk('public')->put('recipes/1/cover/c.jpg', 'x');
        Storage::disk('public')->put('recipes/1/gallery/g.jpg', 'y');
        $recipe->images()->create(['path' => 'recipes/1/gallery/g.jpg', 'position' => 0]);

        $recipe->forceDelete();

        $this->assertDatabaseMissing('recipes', ['id' => $recipe->id]);
        $this->assertDatabaseEmpty('recipe_images');
        Storage::disk('public')->assertMissing('recipes/1/cover/c.jpg');
        Storage::disk('public')->assertMissing('recipes/1/gallery/g.jpg');
    }

    public function test_it_uploads_and_downsizes_a_gallery_image(): void
    {
        Storage::fake('public');
        $recipe = $this->makeRecipe();

        $response = $this->postJson("/api/recipes/{$recipe->id}/images", [
            'image' => UploadedFile::fake()->image('photo.jpg', 3000, 2000),
        ], $this->keyHeaders());

        $response->assertCreated()
            ->assertJsonPath('data.position', 1)
            ->assertJsonStructure(['data' => ['id', 'url', 'position']]);

        $image = RecipeImage::firstOrFail();
        Storage::disk('public')->assertExists($image->path);

        $dimensions = getimagesizefromstring(Storage::disk('public')->get($image->path));
        $this->assertNotFalse($dimensions);
        $this->assertLessThanOrEqual(1080, max($dimensions[0], $dimensions[1]));
    }

    public function test_it_uploads_a_cover_image_and_returns_its_url(): void
    {
        Storage::fake('public');
        $recipe = $this->makeRecipe();

        $response = $this->postJson("/api/recipes/{$recipe->id}/cover", [
            'image' => UploadedFile::fake()->image('cover.jpg', 1600, 1200),
        ], $this->keyHeaders());

        $response->assertOk();
        $this->assertNotNull($response->json('data.coverImageUrl'));

        $recipe->refresh();
        $this->assertNotNull($recipe->cover_image_path);
        Storage::disk('public')->assertExists($recipe->cover_image_path);
    }

    public function test_it_rejects_a_non_image_upload(): void
    {
        Storage::fake('public');
        $recipe = $this->makeRecipe();

        $this->postJson("/api/recipes/{$recipe->id}/images", [
            'image' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ], $this->keyHeaders())->assertUnprocessable()->assertJsonValidationErrors('image');
    }

    public function test_it_scopes_gallery_image_deletion_to_the_recipe(): void
    {
        Storage::fake('public');
        $recipe = $this->makeRecipe();
        $other = $this->makeRecipe();
        Storage::disk('public')->put('recipes/x/g.jpg', 'y');
        $image = $recipe->images()->create(['path' => 'recipes/x/g.jpg', 'position' => 0]);

        // Wrong parent → 404 (scoped binding).
        $this->deleteJson("/api/recipes/{$other->id}/images/{$image->id}", [], $this->keyHeaders())
            ->assertNotFound();

        $this->deleteJson("/api/recipes/{$recipe->id}/images/{$image->id}", [], $this->keyHeaders())
            ->assertNoContent();

        $this->assertDatabaseMissing('recipe_images', ['id' => $image->id]);
        Storage::disk('public')->assertMissing('recipes/x/g.jpg');
    }
}
