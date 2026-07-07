<?php

declare(strict_types=1);

use App\Models\CareEvent;
use App\Models\Observation;
use App\Models\Plant;
use App\Models\Symptom;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

/**
 * @return string
 */
function observationFixture(): string
{
    $path = sys_get_temp_dir() . '/dusk-obs-photo.jpg';

    if (! file_exists($path)) {
        $img = imagecreatetruecolor(800, 600);
        imagefilledrectangle($img, 0, 0, 800, 600, (int) imagecolorallocate($img, 100, 200, 50));
        imagejpeg($img, $path, 85);
        imagedestroy($img);
    }

    return $path;
}

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

it('visually updates the health picker when a rating is clicked', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Health Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->click('@health-rating-4')
            ->pause(200);

        expect($browser->attribute('@health-rating-4', 'aria-pressed'))->toBe('true');
        expect($browser->attribute('@health-rating-3', 'aria-pressed'))->toBe('false');
    });
});

it('updates the displayed light value when the slider changes', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Light Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->click('@light-slider')
            ->keys('@light-slider', '{right}', '{right}', '{right}')
            ->waitForText('8 / 10')
            ->assertSeeIn('@light-value', '8 / 10');
    });
});

it('toggles soil moisture between relative and precise modes', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Soil Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal');

        // Relative mode is the default; Dry/Moist/Wet buttons are visible.
        $browser->assertSeeIn('@soil-moisture-field', 'Dry')
            ->assertSeeIn('@soil-moisture-field', 'Moist')
            ->assertSeeIn('@soil-moisture-field', 'Wet');

        // Switch to precise mode.
        $browser->press('Meter (1-10)')
            ->pause(300)
            ->assertDontSeeIn('@soil-moisture-field', 'Dry')
            ->assertPresent('input[aria-label="Soil moisture level 1 to 10"]');

        // Switch back to relative mode.
        $browser->press('Quick check')
            ->pause(300)
            ->assertSeeIn('@soil-moisture-field', 'Dry')
            ->assertMissing('input[aria-label="Soil moisture level 1 to 10"]');
    });
});

it('selects and deselects a symptom chip on consecutive clicks', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Symptom Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->click('@symptom-wilting')
            ->pause(200);

        // Active chip gets an inline background style.
        $active = $browser->attribute('@symptom-wilting', 'style');
        expect($active)->toContain('background');

        // Click again to deselect.
        $browser->click('@symptom-wilting')
            ->pause(200);

        $inactive = $browser->attribute('@symptom-wilting', 'style');
        expect($inactive)->not->toContain('background');
    });
});

it('creates a custom symptom that persists in the database', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Custom Symptom Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->type('input[aria-label="Custom symptom"]', 'Sunburn')
            ->keys('input[aria-label="Custom symptom"]', '{enter}')
            ->waitForText('Sunburn')
            ->click('@care-form-submit')
            ->waitUntilMissing('@log-modal')
            ->waitFor('@timeline-item')
            ->assertSeeIn('@timeline-item', 'Observation');
    });

    $symptom = Symptom::where('key', 'sunburn')->first();
    expect($symptom)->not->toBeNull();
    expect($symptom->is_custom)->toBeTrue();

    $event = $plant->observationEvents()->first();
    expect($event->observation->symptoms->pluck('key')->all())->toContain('sunburn');
});

it('shows a preview after attaching a photo', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Dusk Photo Plant']);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@log-observation')
            ->click('@log-observation')
            ->waitFor('@log-modal')
            ->attach('@photo-attach-input', observationFixture())
            ->assertSeeIn('@photo-preview', 'dusk-obs-photo.jpg');
    });
});

it('pre-populates widgets when editing an existing observation', function (): void {
    $user    = User::factory()->create();
    $plant   = Plant::factory()->create(['common_name' => 'Dusk Edit Observation Plant']);
    $symptom = Symptom::where('key', 'wilting')->firstOrFail();

    $event = CareEvent::factory()->ofType('observation')->create([
        'plant_id'    => $plant->id,
        'occurred_at' => now()->subDay(),
    ]);
    Observation::create([
        'care_event_id'  => $event->id,
        'overall_health' => 4,
        'light_level'    => 7,
        'weight_grams'   => 500,
    ]);
    $event->observation->symptoms()->attach($symptom);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit("/plants/{$plant->id}")
            ->waitFor('@timeline-item')
            ->click('@timeline-item')
            ->waitFor('@timeline-edit')
            ->click('@timeline-edit')
            ->waitFor('@log-modal');

        // Health picker shows rating 4 selected.
        expect($browser->attribute('@health-rating-4', 'aria-pressed'))->toBe('true');
        expect($browser->attribute('@health-rating-3', 'aria-pressed'))->toBe('false');

        // Light level reads 7.
        $browser->assertSeeIn('@light-value', '7 / 10');

        // Weight: 500 g = 1 lb, 1 oz, 18.1 g.
        $browser->assertInputValue('@weight-lb', '1')
            ->assertInputValue('@weight-oz', '1')
            ->assertInputValue('@weight-g', '18.1');

        // Wilting symptom chip is selected.
        $style = $browser->attribute('@symptom-wilting', 'style');
        expect($style)->toContain('background');
    });
});
