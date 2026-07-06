<?php

declare(strict_types=1);

use App\Models\Equipment;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * @param Plant $plant
 *
 * @return Collection
 */
function equipmentEvents(Plant $plant): Collection
{
    return $plant->careEvents()->whereHas('careEventType', fn ($t) => $t->where('key', 'equipment'))
        ->with('equipmentChange')->get();
}

it('records an added event when equipment is attached via the plant update', function (): void {
    $plant      = Plant::factory()->create();
    $humidifier = Equipment::create(['key' => 'humidifier', 'label' => 'Humidifier', 'sort_order' => 1]);

    actingAs(User::factory()->create())
        ->patchJson("/api/plants/{$plant->id}", ['equipment_ids' => [$humidifier->id]])
        ->assertOk();

    $events = equipmentEvents($plant);
    expect($events)->toHaveCount(1);
    expect($events[0]->equipmentChange->action)->toBe('added');
    expect($events[0]->equipmentChange->equipment_label)->toBe('Humidifier');
    expect($plant->fresh()->equipment)->toHaveCount(1);
});

it('records a removed event when equipment is detached', function (): void {
    $plant = Plant::factory()->create();
    $fan   = Equipment::create(['key' => 'fan', 'label' => 'Fan', 'sort_order' => 1]);
    $plant->equipment()->attach($fan->id);
    $user = User::factory()->create();

    actingAs($user)->patchJson("/api/plants/{$plant->id}", ['equipment_ids' => []])->assertOk();

    $events = equipmentEvents($plant);
    expect($events)->toHaveCount(1);
    expect($events[0]->equipmentChange->action)->toBe('removed');
    expect($events[0]->equipmentChange->equipment_label)->toBe('Fan');
});

it('writes nothing when the equipment set is unchanged', function (): void {
    $plant = Plant::factory()->create();
    $fan   = Equipment::create(['key' => 'fan', 'label' => 'Fan', 'sort_order' => 1]);
    $plant->equipment()->attach($fan->id);

    actingAs(User::factory()->create())
        ->patchJson("/api/plants/{$plant->id}", ['equipment_ids' => [$fan->id]])
        ->assertOk();

    expect(equipmentEvents($plant))->toHaveCount(0);
});

it('keeps the label in history after the equipment type is deleted', function (): void {
    $plant = Plant::factory()->create();
    $mat   = Equipment::create(['key' => 'mat', 'label' => 'Heat Mat', 'sort_order' => 1]);
    $user  = User::factory()->create();
    actingAs($user)->patchJson("/api/plants/{$plant->id}", ['equipment_ids' => [$mat->id]]);

    $mat->delete();

    $event = equipmentEvents($plant)->first();
    expect($event->equipmentChange->equipment_id)->toBeNull();
    expect($event->equipmentChange->equipment_label)->toBe('Heat Mat');
});

it('surfaces equipment events in the plant timeline read endpoint', function (): void {
    $plant = Plant::factory()->create();
    $fan   = Equipment::create(['key' => 'fan', 'label' => 'Fan', 'sort_order' => 1]);
    $user  = User::factory()->create();
    actingAs($user)->patchJson("/api/plants/{$plant->id}", ['equipment_ids' => [$fan->id]]);

    actingAs($user)->getJson("/api/plants/{$plant->id}/timeline")
        ->assertOk()
        ->assertJsonPath('data.events.0.type', 'equipment')
        ->assertJsonPath('data.events.0.equipment_change.action', 'added')
        ->assertJsonPath('data.events.0.equipment_change.equipment_label', 'Fan');
});
