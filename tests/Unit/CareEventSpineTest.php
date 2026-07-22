<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CareEventType;
use App\Models\Plant;
use App\Models\User;
use App\Support\CareEventSpine;
use Database\Seeders\CareLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CareEventSpineTest extends TestCase
{
    use RefreshDatabase;

    /** @return void */
    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CareLookupSeeder::class);
    }

    /** @return void */
    public function test_build_creates_spine_row_with_explicit_occurred_at(): void
    {
        $plant      = Plant::factory()->create();
        $user       = User::factory()->create();
        $occurredAt = '2026-06-20T08:30:00Z';
        $note       = 'Test watering';

        $spine = CareEventSpine::build(
            plant: $plant,
            typeKey: 'watering',
            occurredAt: $occurredAt,
            userId: $user->id,
            note: $note,
        );

        $this->assertDatabaseHas('care_events', [
            'id'                 => $spine->id,
            'plant_id'           => $plant->id,
            'care_event_type_id' => CareEventType::idFor('watering'),
            'logged_by_user_id'  => $user->id,
            'note'               => $note,
        ]);
        $this->assertTrue($spine->fresh()->occurred_at->equalTo(Carbon::parse($occurredAt)));
    }

    /** @return void */
    public function test_build_defaults_occurred_at_to_now_when_null(): void
    {
        $plant      = Plant::factory()->create();
        $user       = User::factory()->create();
        $beforeCall = now()->floorSeconds();

        $spine = CareEventSpine::build(
            plant: $plant,
            typeKey: 'observation',
            occurredAt: null,
            userId: $user->id,
            note: null,
        );

        $afterCall = now()->ceilSeconds();

        $refreshed = $spine->fresh();
        $this->assertThat(
            $refreshed->occurred_at,
            $this->logicalAnd(
                $this->greaterThanOrEqual($beforeCall),
                $this->lessThanOrEqual($afterCall),
            ),
        );
        $this->assertDatabaseHas('care_events', [
            'id'                => $spine->id,
            'plant_id'          => $plant->id,
            'logged_by_user_id' => $user->id,
            'note'              => null,
        ]);
    }
}
