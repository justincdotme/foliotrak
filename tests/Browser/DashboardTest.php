<?php

declare(strict_types=1);

use App\Models\CareEvent;
use App\Models\Observation;
use App\Models\Plant;
use App\Models\Symptom;
use App\Models\User;
use App\Models\WateringDetail;
use Database\Seeders\CareLookupSeeder;
use Laravel\Dusk\Browser;

beforeEach(function (): void {
    $this->seed(CareLookupSeeder::class);
});

it('renders due-for-care section with a plant past its watering interval', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create([
        'common_name'                     => 'Thirsty Plant',
        'watering_interval_days_override' => 3,
    ]);
    $event = CareEvent::factory()->ofType('watering')->create([
        'plant_id'    => $plant->id,
        'occurred_at' => now()->subDays(5),
    ]);
    WateringDetail::create(['care_event_id' => $event->id, 'amount_ml' => 200]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/')
            ->waitFor('@due-for-care')
            ->assertSeeIn('@due-for-care', 'Thirsty Plant');
    });
});

it('renders recent activity section with recent care events', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Active Plant']);
    $event = CareEvent::factory()->ofType('watering')->create([
        'plant_id'    => $plant->id,
        'occurred_at' => now()->subHour(),
    ]);
    WateringDetail::create(['care_event_id' => $event->id, 'amount_ml' => 150]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/')
            ->waitFor('@recent-activity')
            ->assertSeeIn('@recent-activity', 'Active Plant');
    });
});

it('shows empty state when no plants exist', function (): void {
    $user = User::factory()->create();

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/')
            ->waitFor('@dashboard-greeting')
            ->assertSeeIn('@due-for-care', 'No plants need care right now.')
            ->assertSeeIn('@recent-activity', 'No activity yet');
    });
});

it('groups flagged problems by plant instead of showing separate rows per symptom', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Sick Plant']);

    $symptom1 = Symptom::where('key', 'wilting')->firstOrFail();
    $symptom2 = Symptom::where('key', 'yellow_leaf')->firstOrFail();
    $symptom3 = Symptom::where('key', Symptom::KEY_ROOT_ROT)->firstOrFail();

    $event = CareEvent::factory()->ofType('observation')->create([
        'plant_id'    => $plant->id,
        'occurred_at' => now()->subDay(),
    ]);
    $obs = Observation::create(['care_event_id' => $event->id, 'overall_health' => 2]);
    $obs->symptoms()->attach([$symptom1->id, $symptom2->id, $symptom3->id]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/')
            ->waitFor('@flagged-problems')
            ->assertSeeIn('@flagged-problems', 'Sick Plant');

        $items = $browser->elements('[dusk="flagged-item"]');
        expect(count($items))->toBe(1);
    });
});

it('does not show a plant in due-for-care when the 28-day gate is unmet and no override exists', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Young Plant']);

    $event1 = CareEvent::factory()->ofType('watering')->create([
        'plant_id'    => $plant->id,
        'occurred_at' => now()->subDays(5),
    ]);
    WateringDetail::create(['care_event_id' => $event1->id, 'amount_ml' => 200]);

    $event2 = CareEvent::factory()->ofType('watering')->create([
        'plant_id'    => $plant->id,
        'occurred_at' => now()->subDays(2),
    ]);
    WateringDetail::create(['care_event_id' => $event2->id, 'amount_ml' => 200]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/')
            ->waitFor('@due-for-care')
            ->assertDontSeeIn('@due-for-care', 'Young Plant');
    });
});

it('shows overdue indicator when explicit watering override is exceeded', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create([
        'common_name'                     => 'Overdue Plant',
        'watering_interval_days_override' => 5,
    ]);
    $event = CareEvent::factory()->ofType('watering')->create([
        'plant_id'    => $plant->id,
        'occurred_at' => now()->subDays(6),
    ]);
    WateringDetail::create(['care_event_id' => $event->id, 'amount_ml' => 200]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/')
            ->waitFor('@due-for-care')
            ->assertSeeIn('@due-for-care', 'Overdue Plant')
            ->assertSeeIn('@due-for-care', '1d overdue');
    });
});

it('shows due-soon indicator when watering is due within one day', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create([
        'common_name'                     => 'Almost Due Plant',
        'watering_interval_days_override' => 7,
    ]);
    $event = CareEvent::factory()->ofType('watering')->create([
        'plant_id'    => $plant->id,
        'occurred_at' => now()->subDays(6),
    ]);
    WateringDetail::create(['care_event_id' => $event->id, 'amount_ml' => 200]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/')
            ->waitFor('@due-for-care')
            ->assertSeeIn('@due-for-care', 'Almost Due Plant')
            ->assertSeeIn('@due-for-care', 'in 1d')
            ->assertDontSeeIn('@due-for-care', 'overdue');
    });
});

it('sorts due-for-care plants by most overdue first', function (): void {
    $user = User::factory()->create();

    $plantA = Plant::factory()->create([
        'common_name'                     => 'Plant Alpha',
        'watering_interval_days_override' => 3,
    ]);
    $eventA = CareEvent::factory()->ofType('watering')->create([
        'plant_id'    => $plantA->id,
        'occurred_at' => now()->subDays(10),
    ]);
    WateringDetail::create(['care_event_id' => $eventA->id, 'amount_ml' => 200]);

    $plantB = Plant::factory()->create([
        'common_name'                     => 'Plant Bravo',
        'watering_interval_days_override' => 3,
    ]);
    $eventB = CareEvent::factory()->ofType('watering')->create([
        'plant_id'    => $plantB->id,
        'occurred_at' => now()->subDays(5),
    ]);
    WateringDetail::create(['care_event_id' => $eventB->id, 'amount_ml' => 200]);

    $plantC = Plant::factory()->create([
        'common_name'                     => 'Plant Charlie',
        'watering_interval_days_override' => 3,
    ]);
    $eventC = CareEvent::factory()->ofType('watering')->create([
        'plant_id'    => $plantC->id,
        'occurred_at' => now()->subDays(4),
    ]);
    WateringDetail::create(['care_event_id' => $eventC->id, 'amount_ml' => 200]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/')
            ->waitFor('@due-for-care');

        $items = $browser->script(
            "return Array.from(document.querySelectorAll('[dusk=\"due-care-item\"]')).map(el => el.textContent)",
        );

        expect($items[0][0])->toContain('Plant Alpha');
        expect($items[0][1])->toContain('Plant Bravo');
        expect($items[0][2])->toContain('Plant Charlie');
    });
});

it('uses non-causal language in flagged problems', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create(['common_name' => 'Flagged Plant']);

    $symptom = Symptom::where('key', Symptom::KEY_ROOT_ROT)->firstOrFail();

    $event = CareEvent::factory()->ofType('observation')->create([
        'plant_id'    => $plant->id,
        'occurred_at' => now()->subDay(),
    ]);
    $obs = Observation::create(['care_event_id' => $event->id, 'overall_health' => 2]);
    $obs->symptoms()->attach([$symptom->id]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/')
            ->waitFor('@flagged-problems')
            ->assertSeeIn('@flagged-problems', 'Flagged Plant')
            ->assertSeeIn('@flagged-problems', 'reported')
            ->assertDontSee('caused')
            ->assertDontSee('leads to');
    });
});

it('navigates to plant detail when clicking a due-for-care item', function (): void {
    $user  = User::factory()->create();
    $plant = Plant::factory()->create([
        'common_name'                     => 'Clickable Plant',
        'watering_interval_days_override' => 3,
    ]);
    $event = CareEvent::factory()->ofType('watering')->create([
        'plant_id'    => $plant->id,
        'occurred_at' => now()->subDays(5),
    ]);
    WateringDetail::create(['care_event_id' => $event->id, 'amount_ml' => 200]);

    $this->browse(function (Browser $browser) use ($user, $plant): void {
        $browser->loginAs($user)
            ->visit('/')
            ->waitFor('@due-for-care')
            ->click('[dusk="due-care-item"]')
            ->waitForLocation('/plants/' . $plant->id)
            ->assertPathIs('/plants/' . $plant->id);
    });
});

it('does not show a plant in due-for-care when override exists but no events or schedule start date', function (): void {
    $user = User::factory()->create();
    Plant::factory()->create([
        'common_name'                     => 'New Override Plant',
        'watering_interval_days_override' => 7,
    ]);

    $this->browse(function (Browser $browser) use ($user): void {
        $browser->loginAs($user)
            ->visit('/')
            ->waitFor('@due-for-care')
            ->assertDontSeeIn('@due-for-care', 'New Override Plant');
    });
});
