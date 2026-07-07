<?php

declare(strict_types=1);

use App\Models\CareEvent;
use App\Models\Location;
use App\Models\Observation;
use App\Models\Plant;
use App\Models\Tag;
use App\Models\User;
use App\Models\WateringDetail;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

it('renders group comparison chart after selecting a tag in the picker', function (): void {
    $user      = User::factory()->create();
    $tag       = Tag::factory()->create(['name' => 'Correlation Group']);
    $locations = Location::factory()->count(2)->create();

    foreach (range(1, 4) as $i) {
        $plant = Plant::factory()->create([
            'common_name' => "Corr Plant {$i}",
            'location_id' => $locations[$i % 2]->id,
        ]);
        $plant->tags()->attach($tag);

        foreach (range(0, 6) as $week) {
            $event = CareEvent::factory()->ofType('observation')->create([
                'plant_id'    => $plant->id,
                'occurred_at' => now()->subWeeks($week),
            ]);
            Observation::create([
                'care_event_id'  => $event->id,
                'overall_health' => fake()->numberBetween(2, 5),
                'light_level'    => fake()->numberBetween(3, 9),
                'weight_grams'   => fake()->numberBetween(200, 1500),
            ]);
        }

        foreach (range(0, 4) as $w) {
            $wev = CareEvent::factory()->ofType('watering')->create([
                'plant_id'    => $plant->id,
                'occurred_at' => now()->subWeeks($w),
            ]);
            WateringDetail::create(['care_event_id' => $wev->id, 'amount_ml' => 200]);
        }
    }

    $this->browse(function (Browser $browser) use ($user, $tag): void {
        $browser->loginAs($user)
            ->visit('/insights')
            ->waitFor('@insights-page')
            ->waitFor('@insights-tag-picker')
            ->within('@insights-tag-picker', function (Browser $b) use ($tag): void {
                $b->press($tag->name);
            })
            ->pause(500)
            ->waitFor('@group-comparison-chart')
            ->assertVisible('@group-comparison-chart');
    });
})->group('insights-correlation');

it('renders correlation scatter chart with SVG content', function (): void {
    $user      = User::factory()->create();
    $tag       = Tag::factory()->create(['name' => 'Correlation Group']);
    $locations = Location::factory()->count(2)->create();

    foreach (range(1, 4) as $i) {
        $plant = Plant::factory()->create([
            'common_name' => "Corr Plant {$i}",
            'location_id' => $locations[$i % 2]->id,
        ]);
        $plant->tags()->attach($tag);

        foreach (range(0, 6) as $week) {
            $event = CareEvent::factory()->ofType('observation')->create([
                'plant_id'    => $plant->id,
                'occurred_at' => now()->subWeeks($week),
            ]);
            Observation::create([
                'care_event_id'  => $event->id,
                'overall_health' => fake()->numberBetween(2, 5),
                'light_level'    => fake()->numberBetween(3, 9),
                'weight_grams'   => fake()->numberBetween(200, 1500),
            ]);
        }

        foreach (range(0, 4) as $w) {
            $wev = CareEvent::factory()->ofType('watering')->create([
                'plant_id'    => $plant->id,
                'occurred_at' => now()->subWeeks($w),
            ]);
            WateringDetail::create(['care_event_id' => $wev->id, 'amount_ml' => 200]);
        }
    }

    $this->browse(function (Browser $browser) use ($user, $tag): void {
        $browser->loginAs($user)
            ->visit('/insights')
            ->waitFor('@insights-page')
            ->waitFor('@insights-tag-picker')
            ->within('@insights-tag-picker', function (Browser $b) use ($tag): void {
                $b->press($tag->name);
            })
            ->pause(500)
            ->waitFor('@correlation-scatter', 10)
            ->assertVisible('@correlation-scatter');

        $symbols = $browser->elements('[dusk="correlation-scatter"] .recharts-scatter-symbol');

        if (count($symbols) === 0) {
            $symbols = $browser->elements('[dusk="correlation-scatter"] svg path');
        }
        expect(count($symbols))->toBeGreaterThan(0);
    });
})->group('insights-correlation');

it('renders correlation heatmap with SVG content', function (): void {
    $user      = User::factory()->create();
    $tag       = Tag::factory()->create(['name' => 'Correlation Group']);
    $locations = Location::factory()->count(2)->create();

    foreach (range(1, 4) as $i) {
        $plant = Plant::factory()->create([
            'common_name' => "Corr Plant {$i}",
            'location_id' => $locations[$i % 2]->id,
        ]);
        $plant->tags()->attach($tag);

        foreach (range(0, 6) as $week) {
            $event = CareEvent::factory()->ofType('observation')->create([
                'plant_id'    => $plant->id,
                'occurred_at' => now()->subWeeks($week),
            ]);
            Observation::create([
                'care_event_id'  => $event->id,
                'overall_health' => fake()->numberBetween(2, 5),
                'light_level'    => fake()->numberBetween(3, 9),
                'weight_grams'   => fake()->numberBetween(200, 1500),
            ]);
        }

        foreach (range(0, 4) as $w) {
            $wev = CareEvent::factory()->ofType('watering')->create([
                'plant_id'    => $plant->id,
                'occurred_at' => now()->subWeeks($w),
            ]);
            WateringDetail::create(['care_event_id' => $wev->id, 'amount_ml' => 200]);
        }
    }

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/insights')
            ->waitFor('@insights-page')
            ->waitFor('@correlation-heatmap', 10)
            ->assertVisible('@correlation-heatmap');

        $rects = $browser->elements('[dusk="correlation-heatmap"] svg rect');
        expect(count($rects))->toBeGreaterThan(0);
    });
})->group('insights-correlation');

it('shows gate message when selected tag has only one plant', function (): void {
    $user      = User::factory()->create();
    $loneTag   = Tag::factory()->create(['name' => 'Lone Tag']);
    $lonePlant = Plant::factory()->create(['common_name' => 'Solo Plant']);
    $lonePlant->tags()->attach($loneTag);

    // Second plant keeps the initial unfiltered view above the 2-plant minimum
    Plant::factory()->create(['common_name' => 'Extra Plant']);

    foreach (range(0, 5) as $week) {
        $event = CareEvent::factory()->ofType('observation')->create([
            'plant_id'    => $lonePlant->id,
            'occurred_at' => now()->subWeeks($week),
        ]);
        Observation::create([
            'care_event_id'  => $event->id,
            'overall_health' => 4,
        ]);
    }

    $this->browse(function (Browser $browser) use ($user, $loneTag): void {
        $browser->loginAs($user)
            ->visit('/insights')
            ->waitFor('@insights-page')
            ->waitFor('@insights-tag-picker')
            ->within('@insights-tag-picker', function (Browser $b) use ($loneTag): void {
                $b->press($loneTag->name);
            })
            ->pause(500)
            ->waitFor('@insights-gate')
            ->assertVisible('@insights-gate')
            ->assertSee('fewer than 2');
    });
})->group('insights-correlation');

it('shows correlation pending when observation history is insufficient', function (): void {
    $user    = User::factory()->create();
    $gateTag = Tag::factory()->create(['name' => 'Gate Tag']);

    foreach (range(1, 3) as $i) {
        $p = Plant::factory()->create(['common_name' => "Gate Plant {$i}"]);
        $p->tags()->attach($gateTag);

        $event = CareEvent::factory()->ofType('observation')->create([
            'plant_id'    => $p->id,
            'occurred_at' => now()->subDays(3),
        ]);
        Observation::create([
            'care_event_id'  => $event->id,
            'overall_health' => 3,
        ]);
    }

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/insights')
            ->waitFor('@insights-page')
            ->waitFor('@correlation-pending', 10)
            ->assertVisible('@correlation-pending')
            ->assertSee('Correlation analysis is coming');
    });
})->group('insights-correlation');
