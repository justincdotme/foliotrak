<?php

declare(strict_types=1);

use App\Models\Equipment;
use App\Models\Plant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('creates equipment, deriving a slug key and next sort order', function () {
    Equipment::query()->delete();
    Equipment::create(['key' => 'seed', 'label' => 'Seed', 'sort_order' => 3]);

    actingAs(User::factory()->create())
        ->postJson('/api/equipment', ['label' => 'Grow Light'])
        ->assertCreated()
        ->assertJsonPath('data.label', 'Grow Light')
        ->assertJsonPath('data.key', 'grow_light')
        ->assertJsonPath('data.sort_order', 4);
});

it('rejects a duplicate label', function () {
    Equipment::create(['key' => 'humidifier', 'label' => 'Humidifier', 'sort_order' => 1]);

    actingAs(User::factory()->create())
        ->postJson('/api/equipment', ['label' => 'Humidifier'])
        ->assertStatus(422);
});

it('renames equipment', function () {
    $equipment = Equipment::create(['key' => 'fan', 'label' => 'Fan', 'sort_order' => 1]);

    actingAs(User::factory()->create())
        ->patchJson("/api/equipment/{$equipment->id}", ['label' => 'Oscillating Fan'])
        ->assertOk()
        ->assertJsonPath('data.label', 'Oscillating Fan');
});

it('deletes equipment and cascade-detaches it from plants', function () {
    $equipment = Equipment::create(['key' => 'mat', 'label' => 'Heat Mat', 'sort_order' => 1]);
    $plant = Plant::factory()->create();
    $plant->equipment()->attach($equipment->id);

    actingAs(User::factory()->create())
        ->deleteJson("/api/equipment/{$equipment->id}")
        ->assertNoContent();

    expect(Equipment::find($equipment->id))->toBeNull();
    expect($plant->fresh()->equipment)->toHaveCount(0);
});
