<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Photo;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PhotoApiTest extends TestCase
{
    use RefreshDatabase;

    private function actAsHousehold(): void
    {
        Sanctum::actingAs(User::factory()->create());
    }

    public function test_uploading_a_photo_requires_authentication(): void
    {
        $plant = Plant::factory()->create();
        $this->postJson("/api/plants/{$plant->id}/photos")->assertUnauthorized();
    }

    public function test_uploads_a_photo_to_the_photos_disk_with_a_hashed_name(): void
    {
        Storage::fake('photos');
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $response = $this->postJson("/api/plants/{$plant->id}/photos", [
            'photo' => UploadedFile::fake()->image('living-room.jpg'),
            'taken_on' => '2026-06-01',
            'caption' => 'New leaf unfurling',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.plant_id', $plant->id)
            ->assertJsonPath('data.care_event_id', null)
            ->assertJsonPath('data.original_filename', 'living-room.jpg')
            ->assertJsonPath('data.taken_on', '2026-06-01')
            ->assertJsonPath('data.caption', 'New leaf unfurling')
            ->assertJsonStructure(['data' => ['id', 'plant_id', 'path', 'taken_on', 'created_at', 'updated_at']]);

        $path = $response->json('data.path');
        $this->assertNotSame('living-room.jpg', $path, 'stored filename should be hashed, not the original');
        Storage::disk('photos')->assertExists($path);
        $this->assertDatabaseHas('photos', ['id' => $response->json('data.id'), 'plant_id' => $plant->id]);
    }

    public function test_rejects_a_non_image_upload(): void
    {
        Storage::fake('photos');
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $this->postJson("/api/plants/{$plant->id}/photos", [
            'photo' => UploadedFile::fake()->create('care-notes.pdf', 64, 'application/pdf'),
        ])->assertUnprocessable()->assertJsonValidationErrorFor('photo');
    }

    public function test_rejects_an_upload_over_the_size_limit(): void
    {
        Storage::fake('photos');
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $this->postJson("/api/plants/{$plant->id}/photos", [
            'photo' => UploadedFile::fake()->image('huge.jpg')->size(13 * 1024),
        ])->assertUnprocessable()->assertJsonValidationErrorFor('photo');
    }

    public function test_defaults_taken_on_to_today_when_omitted(): void
    {
        Storage::fake('photos');
        $this->actAsHousehold();
        $plant = Plant::factory()->create();

        $this->postJson("/api/plants/{$plant->id}/photos", [
            'photo' => UploadedFile::fake()->image('undated.jpg'),
        ])
            ->assertCreated()
            ->assertJsonPath('data.taken_on', now()->format('Y-m-d'));
    }

    public function test_uploading_with_set_as_cover_updates_the_plant(): void
    {
        Storage::fake('photos');
        $this->actAsHousehold();
        $plant = Plant::factory()->create(['cover_photo_id' => null]);

        $response = $this->postJson("/api/plants/{$plant->id}/photos", [
            'photo' => UploadedFile::fake()->image('hero.jpg'),
            'set_as_cover' => true,
        ])->assertCreated();

        $this->assertSame($response->json('data.id'), $plant->fresh()->cover_photo_id);
    }

    public function test_lists_a_plants_photos(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        Photo::factory()->count(2)->for($plant)->create();
        Photo::factory()->create(); // belongs to another plant

        $this->getJson("/api/plants/{$plant->id}/photos")
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_lists_photos_newest_first_by_taken_on(): void
    {
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        Photo::factory()->for($plant)->create(['taken_on' => '2026-01-10', 'caption' => 'oldest']);
        Photo::factory()->for($plant)->create(['taken_on' => '2026-06-01', 'caption' => 'newest']);
        Photo::factory()->for($plant)->create(['taken_on' => '2026-03-15', 'caption' => 'middle']);

        $captions = collect(
            $this->getJson("/api/plants/{$plant->id}/photos")->assertOk()->json('data')
        )->pluck('caption')->all();

        $this->assertSame(['newest', 'middle', 'oldest'], $captions);
    }

    public function test_deletes_a_photo_and_removes_the_file(): void
    {
        Storage::fake('photos');
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $path = UploadedFile::fake()->image('x.jpg')->store('', 'photos');
        $photo = Photo::factory()->for($plant)->create(['path' => $path]);

        $this->deleteJson("/api/photos/{$photo->id}")->assertNoContent();

        $this->assertDatabaseMissing('photos', ['id' => $photo->id]);
        Storage::disk('photos')->assertMissing($path);
    }

    public function test_deleting_the_cover_photo_clears_the_plants_cover(): void
    {
        Storage::fake('photos');
        $this->actAsHousehold();
        $plant = Plant::factory()->create();
        $photo = Photo::factory()->for($plant)->create();
        $plant->update(['cover_photo_id' => $photo->id]);

        $this->deleteJson("/api/photos/{$photo->id}")->assertNoContent();

        $this->assertNull($plant->fresh()->cover_photo_id);
    }
}
