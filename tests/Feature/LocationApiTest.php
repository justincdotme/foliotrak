<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Location;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class LocationApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return void */
    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create());
    }

    /** @return void */
    public function test_list_returns_all_locations_sorted_by_name(): void
    {
        Location::factory()->create(['name' => 'Kitchen']);
        Location::factory()->create(['name' => 'Bedroom']);
        Location::factory()->create(['name' => 'Office']);

        $this->getJson('/api/locations')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.name', 'Bedroom')
            ->assertJsonPath('data.1.name', 'Kitchen')
            ->assertJsonPath('data.2.name', 'Office');
    }

    /** @return void */
    public function test_create_stores_and_returns_the_location(): void
    {
        $this->postJson('/api/locations', ['name' => 'South window'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'South window')
            ->assertJsonStructure(['data' => ['id', 'name']]);

        $this->assertDatabaseHas('locations', ['name' => 'South window']);
    }

    /** @return void */
    public function test_create_trims_whitespace(): void
    {
        $this->postJson('/api/locations', ['name' => '  Office  '])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Office');

        $this->assertDatabaseHas('locations', ['name' => 'Office']);
    }

    /** @return void */
    public function test_create_rejects_duplicate_name_case_insensitively(): void
    {
        Location::factory()->create(['name' => 'Office']);

        $this->postJson('/api/locations', ['name' => 'office'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    /** @return void */
    public function test_create_rejects_empty_name(): void
    {
        $this->postJson('/api/locations', ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    /** @return void */
    public function test_list_requires_authentication(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/locations')->assertUnauthorized();
    }
}
