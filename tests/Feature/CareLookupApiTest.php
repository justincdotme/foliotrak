<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CareLookupApiTest extends TestCase
{
    use RefreshDatabase;

    /** @return void */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CareLookupSeeder::class);
    }

    /** @return void */
    public function test_lookups_require_authentication(): void
    {
        $this->getJson('/api/symptoms')->assertUnauthorized();
    }

    /** @return void */
    public function test_lists_care_event_types_in_order(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/care-event-types')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.key', 'watering');
    }

    /** @return void */
    public function test_lists_fertilizer_forms(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/fertilizer-forms')
            ->assertOk()
            ->assertJsonCount(6, 'data')
            ->assertJsonPath('data.0.key', 'liquid');
    }

    /** @return void */
    public function test_lists_nutrients_in_the_component_shape(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->getJson('/api/nutrients')
            ->assertOk()
            ->assertJsonCount(4, 'data')
            ->assertJsonPath('data.0.nutrient_key', 'kelp')
            ->assertJsonPath('data.0.nutrient_symbol', null);
    }

    /** @return void */
    public function test_lists_seeded_symptoms_with_their_chip_shape(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $response = $this->getJson('/api/symptoms')
            ->assertOk()
            ->assertJsonCount(15, 'data')
            ->assertJsonPath('data.0.key', 'yellow_leaf')
            ->assertJsonPath('data.0.category', 'leaf')
            ->assertJsonPath('data.0.is_custom', false);

        $keys = collect($response->json('data'))->pluck('key');
        $this->assertTrue($keys->contains('brown_tips'));
        $this->assertTrue($keys->contains('spider_mites'));
    }
}
