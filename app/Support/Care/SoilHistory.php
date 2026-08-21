<?php

declare(strict_types=1);

namespace App\Support\Care;

use App\Enums\SensorType;
use App\Models\CareEvent;
use App\Models\Plant;
use App\Services\Sensors\MoistureCalibration;
use App\Services\Sensors\Transformers\HygrometerTransformer;
use App\Services\Sensors\Transformers\MoistureTransformer;
use App\Support\Stats;
use Illuminate\Support\Carbon;

/**
 * A plant's soil moisture history, merged from manual observations and
 * calibrated moisture sensors onto one 1-to-10 scale.
 */
final class SoilHistory
{
    /**
     * Eager-load paths a caller must load before calling anything here.
     *
     * @var list<string>
     */
    public const RELATIONS = [
        'wateringEvents',
        'observationEvents.observation',
        'sensors.readings',
        'sensors.calibrationPoints',
    ];

    /** Days of observation history considered. */
    private const OBSERVATION_DAYS = 90;

    /**
     * Sensors store a reading every 15 to 30 minutes, so a shorter window
     * still yields far more samples than observations do over 90 days.
     */
    private const SENSOR_DAYS = 30;

    /**
     * The watering modal writes its companion observation at the identical
     * occurred_at, and that reading is taken before the water goes in.
     */
    private const WATERING_TOLERANCE_MINUTES = 5;

    /**
     * The newest reading that postdates the last watering, or null when the
     * plant has no usable moisture evidence and the projection must be skipped.
     *
     * @param Plant  $plant
     * @param Carbon $now
     *
     * @return SoilReading|null
     */
    public static function anchor(Plant $plant, Carbon $now): ?SoilReading
    {
        $cutoff = self::lastWateringCutoff($plant);

        $candidates = array_values(array_filter(
            self::raw($plant, $now),
            fn (SoilReading $reading): bool => $cutoff === null || $reading->at->greaterThan($cutoff),
        ));

        return $candidates === [] ? null : $candidates[count($candidates) - 1];
    }

    /**
     * Chronological readings collapsed to one median value per calendar day
     * per source. Raw sensor readings are minutes apart, which would fall
     * under the estimator's minimum run length and yield no runs at all.
     *
     * @param Plant  $plant
     * @param Carbon $now
     *
     * @return list<SoilReading>
     */
    public static function daily(Plant $plant, Carbon $now): array
    {
        $byDay = [];

        foreach (self::raw($plant, $now) as $reading) {
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

        usort($daily, fn (SoilReading $a, SoilReading $b): int => $a->at <=> $b->at);

        return $daily;
    }

    /**
     * Ambient conditions to estimate against right now: the newest reading
     * that carries each of them.
     *
     * @param Plant  $plant
     * @param Carbon $now
     *
     * @return array{humidity: float|null, temp: float|null}
     */
    public static function currentConditions(Plant $plant, Carbon $now): array
    {
        $conditions = ['humidity' => null, 'temp' => null];

        foreach (self::raw($plant, $now) as $reading) {
            $conditions['humidity'] = $reading->humidityPct ?? $conditions['humidity'];
            $conditions['temp']     = $reading->tempC ?? $conditions['temp'];
        }

        return $conditions;
    }

    /**
     * Timestamps of the plant's logged waterings, oldest first.
     *
     * @param Plant $plant
     *
     * @return list<Carbon>
     */
    public static function wateringTimes(Plant $plant): array
    {
        return $plant->wateringEvents
            ->map(fn (CareEvent $event): Carbon => $event->occurred_at)
            ->values()
            ->all();
    }

    /**
     * Every reading from both sources, chronological and unaggregated.
     *
     * @param Plant  $plant
     * @param Carbon $now
     *
     * @return list<SoilReading>
     */
    private static function raw(Plant $plant, Carbon $now): array
    {
        $readings = [...self::fromObservations($plant, $now), ...self::fromSensors($plant, $now)];

        usort($readings, fn (SoilReading $a, SoilReading $b): int => $a->at <=> $b->at);

        return $readings;
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
     * @param Plant  $plant
     * @param Carbon $now
     *
     * @return list<SoilReading>
     */
    private static function fromSensors(Plant $plant, Carbon $now): array
    {
        if (! $plant->relationLoaded('sensors')) {
            return [];
        }

        $since   = $now->copy()->subDays(self::SENSOR_DAYS);
        $ambient = self::ambientByDay($plant, $since);

        $readings = [];

        foreach ($plant->sensors as $sensor) {
            if ($sensor->type !== SensorType::Moisture) {
                continue;
            }

            $points      = MoistureCalibration::pointsFrom($sensor->calibrationPoints);
            $transformer = new MoistureTransformer;

            foreach ($sensor->readings as $reading) {
                if ($reading->recorded_at->lessThan($since)) {
                    continue;
                }

                $position = MoistureCalibration::scale(
                    (float) $transformer->hydrate($reading->data)->moisture,
                    $points,
                );

                if ($position === null) {
                    continue;
                }

                $day = $reading->recorded_at->format('Y-m-d');

                $readings[] = new SoilReading(
                    value: (float) $position,
                    at: $reading->recorded_at,
                    source: 'sensor',
                    humidityPct: $ambient[$day]['humidity'] ?? null,
                    tempC: $ambient[$day]['temp'] ?? null,
                );
            }
        }

        return $readings;
    }

    /**
     * Daily mean hygrometer conditions, so a sensor soil reading can be
     * attributed to the air it dried in.
     *
     * @param Plant  $plant
     * @param Carbon $since
     *
     * @return array<string, array{humidity: float|null, temp: float|null}>
     */
    private static function ambientByDay(Plant $plant, Carbon $since): array
    {
        $humidity = [];
        $temp     = [];

        foreach ($plant->sensors as $sensor) {
            if ($sensor->type !== SensorType::Hygrometer) {
                continue;
            }

            $transformer = new HygrometerTransformer;

            foreach ($sensor->readings as $reading) {
                if ($reading->recorded_at->lessThan($since)) {
                    continue;
                }

                $day              = $reading->recorded_at->format('Y-m-d');
                $value            = $transformer->hydrate($reading->data);
                $humidity[$day][] = (float) $value->humidity;
                $temp[$day][]     = (float) $value->temperature;
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
     * The most recent watering plus the pre-watering tolerance.
     *
     * @param Plant $plant
     *
     * @return Carbon|null
     */
    private static function lastWateringCutoff(Plant $plant): ?Carbon
    {
        $times = self::wateringTimes($plant);

        if ($times === []) {
            return null;
        }

        return $times[count($times) - 1]->copy()->addMinutes(self::WATERING_TOLERANCE_MINUTES);
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
}
