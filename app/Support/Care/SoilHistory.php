<?php

declare(strict_types=1);

namespace App\Support\Care;

use App\Enums\SensorType;
use App\Models\CareEvent;
use App\Models\Plant;
use App\Models\Sensor;
use App\Support\Stats;
use Illuminate\Support\Carbon;

/**
 * A plant's soil moisture history, merged from manual observations and
 * calibrated moisture sensors onto one 1-to-10 scale. One instance per plant:
 * the readings are assembled once so every read method shares the same pass.
 */
final class SoilHistory
{
    /**
     * Eager-load paths a caller must load before building one of these.
     * Sensor readings are deliberately absent: they are per-sensor rather than
     * per-plant data, so SensorSeries reads them once for the whole request.
     *
     * @var list<string>
     */
    public const RELATIONS = [
        'wateringEvents',
        'observationEvents.observation',
        'sensors',
    ];

    /** Days of observation history considered. */
    private const OBSERVATION_DAYS = 90;

    /**
     * The watering modal writes its companion observation at the identical
     * occurred_at, and that reading is taken before the water goes in.
     */
    private const WATERING_TOLERANCE_MINUTES = 5;

    /**
     * @param Plant             $plant
     * @param list<SoilReading> $readings Chronological, both sources merged.
     */
    private function __construct(
        private readonly Plant $plant,
        private readonly array $readings,
    ) {}

    /**
     * @param Plant             $plant
     * @param Carbon            $now
     * @param SensorSeries|null $series
     *
     * @return self
     */
    public static function for(Plant $plant, Carbon $now, ?SensorSeries $series = null): self
    {
        $series   = $series ?? app(SensorSeries::class);
        $readings = [
            ...self::fromObservations($plant, $now),
            ...self::fromSensors($plant, $series),
        ];

        usort(
            $readings,
            fn (SoilReading $a, SoilReading $b): int => $a->at->getTimestamp() <=> $b->at->getTimestamp(),
        );

        return new self($plant, $readings);
    }

    /**
     * @param Plant  $plant
     * @param Carbon $now
     *
     * @return list<SoilReading>
     */
    private static function fromObservations(Plant $plant, Carbon $now): array
    {
        $since    = $now->copy()->subDays(self::OBSERVATION_DAYS);
        $readings = [];

        foreach ($plant->observationEvents as $event) {
            $observation = $event->observation;

            if ($observation === null || $event->occurred_at->lessThan($since)) {
                continue;
            }

            $value = $observation->soil_moisture_precise !== null
                ? (float) $observation->soil_moisture_precise
                : $observation->soil_moisture_relative?->numericValue();

            if ($value === null) {
                continue;
            }

            $readings[] = new SoilReading(
                value: $value,
                at: $event->occurred_at,
                source: 'observation',
                humidityPct: $observation->ambient_humidity_pct !== null ? (float) $observation->ambient_humidity_pct : null,
                tempC: $observation->ambient_temp_c !== null ? (float) $observation->ambient_temp_c : null,
            );
        }

        return $readings;
    }

    /**
     * @param Plant        $plant
     * @param SensorSeries $series
     *
     * @return list<SoilReading>
     */
    private static function fromSensors(Plant $plant, SensorSeries $series): array
    {
        if (! $plant->relationLoaded('sensors')) {
            return [];
        }

        $probes = $plant->sensors->filter(
            fn (Sensor $sensor): bool => $sensor->type === SensorType::Moisture,
        );

        // Without a probe there can be no sensor reading, so the ambient
        // assembly below would be built and then discarded.
        if ($probes->isEmpty()) {
            return [];
        }

        $ambient  = self::ambientByDay($plant, $series);
        $readings = [];

        foreach ($probes as $probe) {
            foreach ($series->moistureReadings($probe->id) as $reading) {
                $day = $reading->at->format('Y-m-d');

                $readings[] = new SoilReading(
                    value: $reading->value,
                    at: $reading->at,
                    source: 'sensor',
                    humidityPct: $ambient[$day]['humidity'] ?? null,
                    tempC: $ambient[$day]['temp'] ?? null,
                );
            }
        }

        return $readings;
    }

    /**
     * Daily mean hygrometer conditions across every hygrometer on the plant,
     * so a sensor soil reading can be attributed to the air it dried in.
     *
     * @param Plant        $plant
     * @param SensorSeries $series
     *
     * @return array<string, array{humidity: float|null, temp: float|null}>
     */
    private static function ambientByDay(Plant $plant, SensorSeries $series): array
    {
        $humidity = [];
        $temp     = [];

        foreach ($plant->sensors as $sensor) {
            if ($sensor->type !== SensorType::Hygrometer) {
                continue;
            }

            foreach ($series->ambientByDay($sensor->id) as $day => $conditions) {
                $humidity[$day][] = $conditions['humidity'];
                $temp[$day][]     = $conditions['temp'];
            }
        }

        $byDay = [];

        foreach (array_keys($humidity + $temp) as $day) {
            $byDay[$day] = [
                'humidity' => self::meanOf($humidity[$day] ?? []),
                'temp'     => self::meanOf($temp[$day] ?? []),
            ];
        }

        return $byDay;
    }

    /**
     * @param list<float|null> $values
     *
     * @return float|null
     */
    private static function meanOf(array $values): ?float
    {
        $present = array_values(array_filter($values, fn (?float $value): bool => $value !== null));

        return $present === [] ? null : array_sum($present) / count($present);
    }

    /**
     * The newest watering strictly before a reading, allowing for the
     * companion reading stamped at the same second as the watering.
     *
     * @param list<Carbon> $waterings Oldest first.
     * @param Carbon       $at
     *
     * @return Carbon|null
     */
    private static function wateringBefore(array $waterings, Carbon $at): ?Carbon
    {
        $cutoff = $at->copy()->subMinutes(self::WATERING_TOLERANCE_MINUTES);
        $found  = null;

        foreach ($waterings as $time) {
            if ($time->lessThanOrEqualTo($cutoff)) {
                $found = $time;
            }
        }

        return $found;
    }

    /**
     * The newest reading that postdates the last watering, or null when the
     * plant has no usable moisture evidence since it was last watered.
     *
     * @return SoilReading|null
     */
    public function anchor(): ?SoilReading
    {
        $cutoff = $this->lastWateringCutoff();

        $candidates = array_values(array_filter(
            $this->readings,
            fn (SoilReading $reading): bool => $cutoff === null || $reading->at->greaterThan($cutoff),
        ));

        return $candidates === [] ? null : $candidates[count($candidates) - 1];
    }

    /**
     * Chronological readings collapsed to one median value per calendar day
     * per source. Raw sensor readings are minutes apart, which would fall
     * under the estimator's minimum run length and yield no runs at all.
     *
     * @return list<SoilReading>
     */
    public function daily(): array
    {
        $byDay = [];

        foreach ($this->readings as $reading) {
            $byDay[$reading->source . '|' . $reading->at->format('Y-m-d')][] = $reading;
        }

        $daily = [];

        foreach ($byDay as $group) {
            $daily[] = new SoilReading(
                value: Stats::median(array_map(fn (SoilReading $r): float => $r->value, $group)) ?? 0.0,
                at: $group[count($group) - 1]->at->copy()->startOfDay(),
                source: $group[0]->source,
                humidityPct: self::meanOf(array_map(fn (SoilReading $r): ?float => $r->humidityPct, $group)),
                tempC: self::meanOf(array_map(fn (SoilReading $r): ?float => $r->tempC, $group)),
            );
        }

        usort($daily, fn (SoilReading $a, SoilReading $b): int => $a->at->getTimestamp() <=> $b->at->getTimestamp());

        return $daily;
    }

    /**
     * Ambient conditions to estimate against right now: the newest reading
     * that carries each of them.
     *
     * @return array{humidity: float|null, temp: float|null}
     */
    public function currentConditions(): array
    {
        $conditions = ['humidity' => null, 'temp' => null];

        foreach ($this->readings as $reading) {
            $conditions['humidity'] = $reading->humidityPct ?? $conditions['humidity'];
            $conditions['temp']     = $reading->tempC ?? $conditions['temp'];
        }

        return $conditions;
    }

    /**
     * Every soil reading paired with the watering it followed, so the interval
     * estimator can ask whether that stretch turned out to be right. The
     * reading the watering modal stamps at the moment of watering is the
     * verdict on the stretch that just ended, so it counts against the
     * watering before it rather than being discarded.
     *
     * @return list<SoilEvidence>
     */
    public function evidence(): array
    {
        $waterings = $this->wateringTimes();
        $evidence  = [];

        foreach ($this->readings as $reading) {
            $previous = self::wateringBefore($waterings, $reading->at);

            if ($previous === null) {
                continue;
            }

            $evidence[] = new SoilEvidence(
                daysSinceWatering: ($reading->at->getTimestamp() - $previous->getTimestamp()) / 86400,
                value: $reading->value,
                at: $reading->at,
                source: $reading->source,
                humidityPct: $reading->humidityPct,
                tempC: $reading->tempC,
            );
        }

        return $evidence;
    }

    /**
     * Timestamps of the plant's logged waterings, oldest first.
     *
     * @return list<Carbon>
     */
    public function wateringTimes(): array
    {
        return $this->plant->wateringEvents
            ->map(fn (CareEvent $event): Carbon => $event->occurred_at)
            ->values()
            ->all();
    }

    /**
     * The most recent watering plus the pre-watering tolerance.
     *
     * @return Carbon|null
     */
    private function lastWateringCutoff(): ?Carbon
    {
        $times = $this->wateringTimes();

        if ($times === []) {
            return null;
        }

        return $times[count($times) - 1]->copy()->addMinutes(self::WATERING_TOLERANCE_MINUTES);
    }
}
