<?php

declare(strict_types=1);

namespace App\Support\Correlation;

use App\Models\Plant;
use Illuminate\Support\Collection;

/**
 * One thing the correlation engine can relate to plant health. A numeric factor extracts the
 * paired (input, health) samples across a set of plants; the engine pools them into a Spearman
 * correlation. Adding temperature, light, or fertilizer later is implementing this interface,
 * not changing the engine (ADR-0020).
 */
interface Factor
{
    /**
     * The x-axis label for the correlation pair (for example `watering_interval_days`).
     */
    public function key(): string;

    /**
     * The y-axis label, the outcome the factor is related to (for example `overall_health`).
     */
    public function outcomeKey(): string;

    /**
     * The Eloquent relations the factor reads, as eager-load paths relative to a plant (for
     * example `wateringEvents`, `observationEvents.observation`). The caller loads these so the
     * factor never lazy-loads per plant, keeping a new factor a one-class addition.
     *
     * @return list<string>
     */
    public function relations(): array;

    /**
     * Pooled paired samples across the plants, one entry per aligned (input, health) reading.
     *
     * @param  Collection<int, Plant>  $plants
     * @return list<array{x: float, y: float}>
     */
    public function pairs(Collection $plants): array;
}
