<?php

declare(strict_types=1);

use App\Models\CareEvent;
use App\Models\FertilizerForm;
use App\Models\FertilizingDetail;
use App\Models\Location;
use App\Models\Observation;
use App\Models\Photo;
use App\Models\Plant;
use App\Models\RelocationDetail;
use App\Models\RepottingDetail;
use App\Models\User;
use App\Models\WateringDetail;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

it('shows a merged sorted feed of all care event types', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Timeline Plant']);
    $locA  = Location::factory()->create(['name' => 'Kitchen']);
    $locB  = Location::factory()->create(['name' => 'Office']);

    $wEv = CareEvent::factory()->ofType('watering')->create([
        'plant_id' => $plant->id, 'occurred_at' => now()->subDays(5),
    ]);
    WateringDetail::create(['care_event_id' => $wEv->id, 'amount_ml' => 200]);

    $fEv = CareEvent::factory()->ofType('fertilizing')->create([
        'plant_id' => $plant->id, 'occurred_at' => now()->subDays(4),
    ]);
    $form = FertilizerForm::where('key', 'liquid')->firstOrFail();
    FertilizingDetail::create([
        'care_event_id'      => $fEv->id,
        'fertilizer_form_id' => $form->id,
        'npk_n'              => '10.00', 'npk_p' => '5.00', 'npk_k' => '5.00',
    ]);

    $rEv = CareEvent::factory()->ofType('repotting')->create([
        'plant_id' => $plant->id, 'occurred_at' => now()->subDays(3),
    ]);
    RepottingDetail::create([
        'care_event_id'  => $rEv->id, 'soil_recipe' => 'Peat and perlite',
        'pot_size_value' => '6.0', 'pot_size_unit' => 'in', 'fertilizer_added' => false,
    ]);

    $oEv = CareEvent::factory()->ofType('observation')->create([
        'plant_id' => $plant->id, 'occurred_at' => now()->subDays(2),
    ]);
    Observation::create([
        'care_event_id' => $oEv->id, 'overall_health' => 3, 'light_level' => 6,
    ]);

    $relEv = CareEvent::factory()->ofType('relocation')->create([
        'plant_id' => $plant->id, 'occurred_at' => now()->subDay(), 'note' => 'Moved for sunlight',
    ]);
    RelocationDetail::create([
        'care_event_id' => $relEv->id, 'from_location_id' => $locA->id, 'to_location_id' => $locB->id,
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@care-timeline')
            ->assertScript(
                "document.querySelectorAll('[dusk=\"timeline-item\"]').length === 5",
                true,
            );

        // Timeline is reverse-chronological: Moved, Observation, Repotting, Fertilizing, Watering
        $labels = $browser->script(
            "return Array.from(document.querySelectorAll('[dusk=\"timeline-item\"]')).map(el => el.textContent)",
        );

        expect($labels[0])->toHaveCount(5);
        expect($labels[0][0])->toContain('Moved');
        expect($labels[0][1])->toContain('Observation');
        expect($labels[0][2])->toContain('Repotting');
        expect($labels[0][3])->toContain('Fertilizing');
        expect($labels[0][4])->toContain('Watering');
    });
});

it('edits a fertilizing event and persists the NPK change', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Fertilize Edit Plant']);
    $form  = FertilizerForm::where('key', 'liquid')->firstOrFail();
    $event = CareEvent::factory()->ofType('fertilizing')->create([
        'plant_id' => $plant->id, 'occurred_at' => now()->subDay(),
    ]);
    FertilizingDetail::create([
        'care_event_id'      => $event->id,
        'fertilizer_form_id' => $form->id,
        'npk_n'              => '10.00', 'npk_p' => '5.00', 'npk_k' => '5.00',
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@timeline-item')
            ->click('@timeline-item')
            ->waitFor('@timeline-edit')
            ->click('@timeline-edit')
            ->waitFor('@log-modal')
            ->waitFor('input[name="npk_n"]')
            ->assertInputValue('input[name="npk_n"]', '10')
            ->type('npk_n', '20')
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitForText('20 - 5 - 5');
    });

    $event->refresh();
    expect((float) $event->fertilizing->npk_n)->toBe(20.0);
});

it('edits a repotting event and persists the soil recipe change', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Repot Edit Plant']);
    $event = CareEvent::factory()->ofType('repotting')->create([
        'plant_id' => $plant->id, 'occurred_at' => now()->subDay(),
    ]);
    RepottingDetail::create([
        'care_event_id'  => $event->id, 'soil_recipe' => 'Peat and perlite',
        'pot_size_value' => '6.0', 'pot_size_unit' => 'in', 'fertilizer_added' => false,
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@timeline-item')
            ->click('@timeline-item')
            ->waitFor('@timeline-edit')
            ->click('@timeline-edit')
            ->waitFor('@log-modal')
            ->waitFor('textarea[name="soil_recipe"]')
            ->type('soil_recipe', 'Coco coir and pumice')
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitForText('Coco coir and pumice');
    });

    $event->refresh();
    expect($event->repotting->soil_recipe)->toBe('Coco coir and pumice');
});

it('edits an observation event and persists the health rating change', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Observation Edit Plant']);
    $event = CareEvent::factory()->ofType('observation')->create([
        'plant_id' => $plant->id, 'occurred_at' => now()->subDay(),
    ]);
    Observation::create([
        'care_event_id' => $event->id, 'overall_health' => 3, 'light_level' => 6,
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@timeline-item')
            ->click('@timeline-item')
            ->waitFor('@timeline-edit')
            ->click('@timeline-edit')
            ->waitFor('@log-modal');

        // Health picker buttons use aria-pressed; index 4 is rating 5
        $browser->script(
            "document.querySelectorAll('[aria-pressed]')[4].click()",
        );

        $browser->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitForText('Health:');
    });

    $event->refresh();
    expect($event->observation->overall_health)->toBe(5);
});

it('edits a relocation event note through the dedicated edit form', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Relocation Edit Plant']);
    $locA  = Location::factory()->create(['name' => 'Kitchen']);
    $locB  = Location::factory()->create(['name' => 'Office']);
    $event = CareEvent::factory()->ofType('relocation')->create([
        'plant_id' => $plant->id, 'occurred_at' => now()->subDay(), 'note' => 'Moved for sunlight',
    ]);
    RelocationDetail::create([
        'care_event_id' => $event->id, 'from_location_id' => $locA->id, 'to_location_id' => $locB->id,
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@timeline-item')
            ->click('@timeline-item')
            ->waitFor('@timeline-edit')
            ->click('@timeline-edit')
            ->waitFor('@log-modal')
            ->waitFor('textarea[name="note"]')
            ->type('note', 'Better light in office')
            ->press('Save changes')
            ->waitUntilMissing('@log-modal')
            ->waitForText('Better light in office');
    });

    $event->refresh();
    expect($event->note)->toBe('Better light in office');
});

it('renders a photo linked to a care event in the timeline detail view', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Photo Timeline Plant']);
    $event = CareEvent::factory()->ofType('observation')->create([
        'plant_id' => $plant->id, 'occurred_at' => now()->subDay(),
    ]);
    Observation::create([
        'care_event_id' => $event->id, 'overall_health' => 4, 'light_level' => 7,
    ]);
    Photo::factory()->create([
        'plant_id'      => $plant->id,
        'care_event_id' => $event->id,
        'caption'       => 'Healthy leaf photo',
    ]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@timeline-item')
            ->click('@timeline-item')
            ->waitForText('Health:')
            ->assertPresent('img[alt="Healthy leaf photo"]');
    });
});
