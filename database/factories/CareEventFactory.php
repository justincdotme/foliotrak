<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CareEvent;
use App\Models\CareEventType;
use App\Models\Plant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CareEvent>
 */
class CareEventFactory extends Factory
{
    protected $model = CareEvent::class;

    public function definition(): array
    {
        return [
            'plant_id' => Plant::factory(),
            'care_event_type_id' => fn (): int => $this->typeId('observation'),
            'occurred_at' => fake()->dateTimeBetween('-3 months'),
            'logged_by_user_id' => null,
            'note' => null,
        ];
    }

    public function ofType(string $key): static
    {
        return $this->state(fn (): array => ['care_event_type_id' => $this->typeId($key)]);
    }

    private function typeId(string $key): int
    {
        return CareEventType::firstOrCreate(['key' => $key], ['label' => ucfirst($key)])->id;
    }
}
