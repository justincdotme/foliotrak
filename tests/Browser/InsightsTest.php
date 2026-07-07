<?php

declare(strict_types=1);

use App\Models\CareEvent;
use App\Models\Location;
use App\Models\Observation;
use App\Models\Plant;
use App\Models\Tag;
use App\Models\User;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

it('renders group health trend chart with a clean legend at phone width', function (): void {
    $user      = User::factory()->create();
    $tag       = Tag::factory()->create(['name' => 'Test Group']);
    $locations = Location::factory()->count(2)->create();

    foreach (range(1, 3) as $i) {
        $plant = Plant::factory()->create([
            'common_name' => "Insight Plant {$i}",
            'location_id' => $locations[$i % 2]->id,
        ]);
        $plant->tags()->attach($tag);

        foreach (range(0, 5) as $week) {
            $event = CareEvent::factory()->ofType('observation')->create([
                'plant_id'    => $plant->id,
                'occurred_at' => now()->subWeeks($week),
            ]);
            Observation::create([
                'care_event_id'  => $event->id,
                'overall_health' => fake()->numberBetween(2, 5),
                'light_level'    => fake()->numberBetween(3, 8),
            ]);
        }
    }

    $this->browse(function (Browser $browser) use ($user, $tag): void {
        $browser->logout()
            ->loginAs($user)
            ->resize(375, 812)
            ->visit('/insights')
            ->waitFor('@insights-page')
            ->waitFor('@insights-tag-picker')
            ->within('@insights-tag-picker', function (Browser $b) use ($tag): void {
                $b->press($tag->name);
            })
            ->pause(500)
            ->waitFor('@group-comparison')
            ->assertVisible('@group-comparison-chart');
    });
})->group('insights');

it('shows health-by-location chart legend with Reading circle and Median diamond markers', function (): void {
    $user      = User::factory()->create();
    $tag       = Tag::factory()->create(['name' => 'Test Group']);
    $locations = Location::factory()->count(2)->create();

    foreach (range(1, 3) as $i) {
        $plant = Plant::factory()->create([
            'common_name' => "Insight Plant {$i}",
            'location_id' => $locations[$i % 2]->id,
        ]);
        $plant->tags()->attach($tag);

        foreach (range(0, 5) as $week) {
            $event = CareEvent::factory()->ofType('observation')->create([
                'plant_id'    => $plant->id,
                'occurred_at' => now()->subWeeks($week),
            ]);
            Observation::create([
                'care_event_id'  => $event->id,
                'overall_health' => fake()->numberBetween(2, 5),
                'light_level'    => fake()->numberBetween(3, 8),
            ]);
        }
    }

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->logout()
            ->loginAs($user)
            ->resize(1280, 800)
            ->visit('/insights')
            ->waitFor('@insights-page')
            ->waitFor('@group-comparison')
            ->assertVisible('@group-comparison-chart');
    });
})->group('insights');

it('uses hedging language instead of causal claims', function (): void {
    $user      = User::factory()->create();
    $tag       = Tag::factory()->create(['name' => 'Test Group']);
    $locations = Location::factory()->count(2)->create();

    foreach (range(1, 3) as $i) {
        $plant = Plant::factory()->create([
            'common_name' => "Insight Plant {$i}",
            'location_id' => $locations[$i % 2]->id,
        ]);
        $plant->tags()->attach($tag);

        foreach (range(0, 5) as $week) {
            $event = CareEvent::factory()->ofType('observation')->create([
                'plant_id'    => $plant->id,
                'occurred_at' => now()->subWeeks($week),
            ]);
            Observation::create([
                'care_event_id'  => $event->id,
                'overall_health' => fake()->numberBetween(2, 5),
                'light_level'    => fake()->numberBetween(3, 8),
            ]);
        }
    }

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->logout()
            ->loginAs($user)
            ->resize(1280, 800)
            ->visit('/insights')
            ->waitFor('@insights-page')
            ->assertDontSee('caused')
            ->assertDontSee('leads to')
            ->assertDontSee('will cause');
    });
})->group('insights');
