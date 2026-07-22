<?php

declare(strict_types=1);

use App\Models\CareEvent;
use App\Models\FertilizerForm;
use App\Models\Photo;
use App\Models\Plant;
use App\Models\Symptom;
use App\Models\User;
use App\Models\WateringDetail;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

/**
 * @return string
 */
function photoFixture(): string
{
    $path = sys_get_temp_dir() . '/dusk-care-photo.jpg';

    if (! file_exists($path)) {
        $img = imagecreatetruecolor(800, 600);
        imagefilledrectangle($img, 0, 0, 800, 600, (int) imagecolorallocate($img, 100, 180, 60));
        imagejpeg($img, $path, 85);
        imagedestroy($img);
    }

    return $path;
}

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

it('logs a watering through the log modal and reflects it on the timeline', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Watering Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-watering')
            ->click('@log-watering')
            ->waitFor('@log-modal')
            ->type('@watering-amount', '250')
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitFor('@timeline-item')
            ->assertSeeIn('@timeline-item', 'Watering')
            ->click('@timeline-item')
            ->waitForText('250 ml');
    });

    $plant->refresh();
    $event = $plant->wateringEvents()->first();
    expect($event)->not->toBeNull();
    expect($event->watering->amount_ml)->toBe(250);
});

it('logs an observation with a seeded symptom and shows it in the timeline detail', function (): void {
    $user    = User::factory()->create();
    $plant   = Plant::factory()->create(['common_name' => 'Dusk Observation Plant']);
    $symptom = Symptom::where('key', 'wilting')->firstOrFail();

    $this->browse(function (Browser $browser) use ($user, $plant, $symptom): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->click("@symptom-{$symptom->key}")
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitFor('@timeline-item')
            ->assertSeeIn('@timeline-item', 'Observation')
            ->click('@timeline-item')
            ->waitForText($symptom->label);
    });

    $plant->refresh();
    $event = $plant->observationEvents()->first();
    expect($event)->not->toBeNull();
    expect($event->observation->symptoms->pluck('id')->all())->toContain($symptom->id);
});

it('edits an existing watering event and persists the change', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Edit Plant']);
    $event = CareEvent::factory()->ofType('watering')->create([
        'plant_id'    => $plant->id,
        'occurred_at' => now()->subDay(),
    ]);
    WateringDetail::create(['care_event_id' => $event->id, 'amount_ml' => 100]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@timeline-item')
            ->click('@timeline-item')
            ->waitFor('@timeline-edit')
            ->click('@timeline-edit')
            ->waitFor('@log-modal')
            ->assertInputValue('@watering-amount', '100')
            ->type('@watering-amount', '400')
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitForText('400 ml');
    });

    $event->refresh();
    expect($event->watering->amount_ml)->toBe(400);
});

it('deletes an event from the timeline', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Delete Plant']);
    $event = CareEvent::factory()->ofType('watering')->create([
        'plant_id'    => $plant->id,
        'occurred_at' => now()->subDay(),
    ]);
    WateringDetail::create(['care_event_id' => $event->id, 'amount_ml' => 150]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@timeline-item')
            ->click('@timeline-item')
            ->waitFor('@timeline-delete')
            ->click('@timeline-delete')
            ->waitFor('@confirm-delete')
            ->click('@confirm-delete')
            ->waitUntilMissing('@timeline-item')
            ->assertSee('No care events logged yet');
    });

    expect(CareEvent::find($event->id))->toBeNull();
    expect(WateringDetail::find($event->id))->toBeNull();
});

it('logs fertilizing with liquid NPK and stores NPK values', function (): void {
    $user   = User::factory()->create();
    $plant  = Plant::factory()->create(['common_name' => 'Dusk NPK Plant']);
    $liquid = FertilizerForm::where('key', 'liquid')->firstOrFail();

    $this->browse(function (Browser $browser) use ($user, $plant, $liquid): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-fertilizing')
            ->click('@log-fertilizing')
            ->waitFor('@log-modal')
            ->waitFor('[dusk="fertilizing-form-select"] option')
            ->select('@fertilizing-form-select', (string) $liquid->id)
            ->type('input[name="npk_n"]', '10')
            ->type('input[name="npk_p"]', '5')
            ->type('input[name="npk_k"]', '5')
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitFor('@timeline-item')
            ->assertSeeIn('@timeline-item', 'Fertilizing');
    });

    $plant->refresh();
    $event = $plant->fertilizingEvents()->first();
    expect($event)->not->toBeNull();
    $detail = $event->fertilizing;
    expect($detail->npk_n)->toBe('10.00');
    expect($detail->npk_p)->toBe('5.00');
    expect($detail->npk_k)->toBe('5.00');
});

it('logs fertilizing with organic form and attaches a nutrient', function (): void {
    $user    = User::factory()->create();
    $plant   = Plant::factory()->create(['common_name' => 'Dusk Organic Plant']);
    $organic = FertilizerForm::where('key', 'organic')->firstOrFail();

    $this->browse(function (Browser $browser) use ($user, $plant, $organic): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-fertilizing')
            ->click('@log-fertilizing')
            ->waitFor('@log-modal')
            ->waitFor('[dusk="fertilizing-form-select"] option')
            ->select('@fertilizing-form-select', (string) $organic->id)
            ->waitFor('[dusk="nutrient-chips"]');

        $browser->script("document.querySelector('[dusk=\"nutrient-chips\"] button')?.scrollIntoView({block: 'center'})");
        $browser->pause(300);
        $browser->script("document.querySelector('[dusk=\"nutrient-chips\"] button')?.click()");

        $browser->pause(300);
        $browser->script("document.querySelector('[dusk=\"care-form-submit\"]')?.scrollIntoView({block: 'center'})");
        $browser->pause(300);
        $browser->script("document.querySelector('[dusk=\"care-form-submit\"]')?.click()");
        $browser->waitUntilMissing('@log-modal')
            ->waitFor('@timeline-item')
            ->assertSeeIn('@timeline-item', 'Fertilizing');
    });

    $plant->refresh();
    $event = $plant->fertilizingEvents()->first();
    expect($event)->not->toBeNull();
    $detail = $event->fertilizing;
    expect($detail->fertilizer_form_id)->toBe($organic->id);
    expect($detail->nutrients()->count())->toBe(1);
});

it('logs repotting with fertilizer toggle and chains a linked fertilizing entry', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Repot Fert Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-repotting')
            ->click('@log-repotting')
            ->waitFor('@log-modal')
            ->click('#fertilizer-added')
            ->click('@care-form-submit')
            ->waitFor('@fertilizing-form-select')
            ->waitUsing(5, 100, fn () => $browser->attribute('@fertilizing-form-select', 'value') !== '')
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitFor('@timeline-item');
    });

    $repotEvent = CareEvent::where('plant_id', $plant->id)
        ->whereHas('repotting')
        ->first();
    expect($repotEvent)->not->toBeNull();
    expect($repotEvent->repotting->fertilizer_added)->toBeTrue();

    $fertEvent = $plant->fertilizingEvents()->first();
    expect($fertEvent)->not->toBeNull();
    expect($fertEvent->fertilizing)->not->toBeNull();
});

it('toggles the fertilizer switch via its label text', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Label Toggle Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-repotting')
            ->click('@log-repotting')
            ->waitFor('@log-modal')
            ->waitFor('#fertilizer-added[aria-checked="false"]')
            ->click('label[for="fertilizer-added"]')
            ->waitFor('#fertilizer-added[aria-checked="true"]');
    });
});

it('logs an observation with a photo and links it without cropping', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Photo Obs Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->attach('input[type="file"]', photoFixture())
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitFor('@timeline-item')
            ->assertSeeIn('@timeline-item', 'Observation');
    });

    $plant->refresh();
    $event = $plant->observationEvents()->first();
    expect($event)->not->toBeNull();
    $photo = Photo::where('care_event_id', $event->id)->first();
    expect($photo)->not->toBeNull();
});

it('logs watering with a note and displays it in the expanded timeline', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Note Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-watering')
            ->click('@log-watering')
            ->waitFor('@log-modal')
            ->type('@watering-amount', '300')
            ->type('textarea[name="note"]', 'Soil was very dry')
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitFor('@timeline-item')
            ->assertSeeIn('@timeline-item', 'Watering')
            ->click('@timeline-item')
            ->waitForText('Soil was very dry');
    });

    $plant->refresh();
    $event = $plant->wateringEvents()->first();
    expect($event)->not->toBeNull();
    expect($event->note)->toBe('Soil was very dry');
});

it('logs repotting with soil recipe and pot size values', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Repot Details Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-repotting')
            ->click('@log-repotting')
            ->waitFor('@log-modal')
            ->type('textarea[name="soil_recipe"]', '5 parts bark, 2 parts perlite')
            ->type('@repotting-pot-size', '10')
            ->press('cm')
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitFor('@timeline-item');
    });

    $repotEvent = CareEvent::where('plant_id', $plant->id)
        ->whereHas('repotting')
        ->first();
    expect($repotEvent)->not->toBeNull();
    $detail = $repotEvent->repotting;
    expect($detail->soil_recipe)->toBe('5 parts bark, 2 parts perlite');
    expect($detail->pot_size_value)->toBe('10.0');
    expect($detail->pot_size_unit)->toBe('cm');
});

it('logs observation with growth rate and leaf size', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Growth Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->press('Slow')
            ->type('input[name="leaf_size_mm"]', '120')
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitFor('@timeline-item');
    });

    $plant->refresh();
    $event = $plant->observationEvents()->first();
    expect($event)->not->toBeNull();
    $obs = $event->observation;
    expect($obs->growth_rate->value)->toBe('slow');
    expect($obs->leaf_size_mm)->toBe('120.0');
});

it('renders form chips and select options from seeded lookup data', function (): void {
    $user     = User::factory()->create();
    $plant    = Plant::factory()->create(['common_name' => 'Dusk Lookups Plant']);
    $symptom  = Symptom::where('key', 'wilting')->firstOrFail();
    $fertForm = FertilizerForm::where('key', 'liquid')->firstOrFail();

    $this->browse(function (Browser $browser) use ($user, $plant, $symptom, $fertForm): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->waitFor("@symptom-{$symptom->key}")
            ->assertSeeIn("@symptom-{$symptom->key}", $symptom->label)
            ->click('button[aria-label="Close"]')
            ->waitUntilMissing('@log-modal')
            ->click('@log-fertilizing')
            ->waitFor('@log-modal')
            ->waitFor('[dusk="fertilizing-form-select"] option')
            ->assertSeeIn('@fertilizing-form-select', $fertForm->label);
    });
});
