<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Plant;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TagApiTest extends TestCase
{
    use RefreshDatabase;

    private function actAsHousehold(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_listing_tags_requires_authentication(): void
    {
        $this->getJson('/api/tags')->assertUnauthorized();
    }

    public function test_lists_tags(): void
    {
        $this->actAsHousehold();
        Tag::factory()->create(['name' => 'Pothos', 'color' => '#4ade80']);
        Tag::factory()->create(['name' => 'Kitchen']);

        $this->getJson('/api/tags')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonStructure(['data' => [['id', 'name', 'color']]]);
    }

    public function test_creates_a_tag(): void
    {
        $this->actAsHousehold();

        $this->postJson('/api/tags', ['name' => 'Tropical', 'color' => '#22c55e'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Tropical')
            ->assertJsonPath('data.color', '#22c55e');

        $this->assertDatabaseHas('plant_tags', ['name' => 'Tropical', 'color' => '#22c55e']);
    }

    public function test_rejects_a_duplicate_tag_name(): void
    {
        $this->actAsHousehold();
        Tag::factory()->create(['name' => 'Pothos']);

        $this->postJson('/api/tags', ['name' => 'Pothos'])
            ->assertUnprocessable()
            ->assertJsonValidationErrorFor('name');
    }

    public function test_updates_a_tag(): void
    {
        $this->actAsHousehold();
        $tag = Tag::factory()->create(['name' => 'Kitchen', 'color' => null]);

        $this->patchJson("/api/tags/{$tag->id}", ['color' => '#f59e0b'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Kitchen')
            ->assertJsonPath('data.color', '#f59e0b');
    }

    public function test_updating_a_tag_allows_keeping_its_own_name(): void
    {
        $this->actAsHousehold();
        $tag = Tag::factory()->create(['name' => 'Kitchen']);

        $this->patchJson("/api/tags/{$tag->id}", ['name' => 'Kitchen', 'color' => '#000000'])
            ->assertOk();
    }

    public function test_auto_assigns_color_when_none_provided(): void
    {
        $this->actAsHousehold();

        $this->postJson('/api/tags', ['name' => 'First'])
            ->assertCreated()
            ->assertJsonPath('data.color', 'var(--series-1)');

        $this->postJson('/api/tags', ['name' => 'Second'])
            ->assertCreated()
            ->assertJsonPath('data.color', 'var(--series-2)');
    }

    public function test_auto_color_cycles_through_palette(): void
    {
        $this->actAsHousehold();

        for ($i = 1; $i <= 8; $i++) {
            Tag::factory()->create(['name' => "Tag{$i}"]);
        }

        $this->postJson('/api/tags', ['name' => 'Ninth'])
            ->assertCreated()
            ->assertJsonPath('data.color', 'var(--series-1)');
    }

    public function test_explicit_color_is_preserved(): void
    {
        $this->actAsHousehold();

        $this->postJson('/api/tags', ['name' => 'Custom', 'color' => '#ff0000'])
            ->assertCreated()
            ->assertJsonPath('data.color', '#ff0000');
    }

    public function test_deletes_a_tag(): void
    {
        $this->actAsHousehold();
        $tag = Tag::factory()->create();

        $this->deleteJson("/api/tags/{$tag->id}")->assertNoContent();
        $this->assertDatabaseMissing('plant_tags', ['id' => $tag->id]);
    }

    public function test_deleting_a_tag_removes_its_plant_associations(): void
    {
        $this->actAsHousehold();
        $tag = Tag::factory()->create();
        $plant = Plant::factory()->create();
        $plant->tags()->attach($tag);

        $this->deleteJson("/api/tags/{$tag->id}")->assertNoContent();

        $this->assertDatabaseMissing('plant_tag', [
            'plant_id' => $plant->id,
            'tag_id' => $tag->id,
        ]);
    }
}
