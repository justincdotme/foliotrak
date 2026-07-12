<?php

declare(strict_types=1);

namespace App\Services\Sensors;

use App\Models\Sensor;
use App\Models\SensorCalibrationPoint;

final class MoistureCalibration
{
    /**
     * Interpolates a raw ADC count onto the 1-10 soil moisture scale using a
     * sensor's calibration anchors. Capacitive probes read higher when dry,
     * so positions descend as raw values ascend.
     *
     * @param float                                  $rawValue
     * @param list<array{position: int, value: int}> $points
     *
     * @return integer|null
     */
    public static function scale(float $rawValue, array $points): ?int
    {
        if (count($points) < 2) {
            return null;
        }

        $sorted = collect($points)->sortBy('value')->values()->all();
        $first  = $sorted[0];
        $last   = $sorted[count($sorted) - 1];

        if ($rawValue <= $first['value']) {
            return $first['position'];
        }

        if ($rawValue >= $last['value']) {
            return $last['position'];
        }

        for ($i = 1; $i < count($sorted); $i++) {
            $lower = $sorted[$i - 1];
            $upper = $sorted[$i];

            if ($rawValue > $upper['value']) {
                continue;
            }

            $span     = $upper['value'] - $lower['value'];
            $fraction = $span > 0 ? ($rawValue - $lower['value']) / $span : 0.0;
            $position = $lower['position'] + $fraction * ($upper['position'] - $lower['position']);

            return max(1, min(10, (int) round($position)));
        }

        return $last['position'];
    }

    /**
     * The firmware broadcasts a 12-bit ADC count, so the hardware envelope is
     * fixed: 4095 is the driest possible state (position 1), 0 the wettest
     * (position 10), 2048 the midpoint. Hardware-derived defaults never drift
     * with reading history; saved anchors refine from here.
     *
     * @return list<array{position: int, value: int}>
     */
    public static function suggestedPoints(): array
    {
        return [
            ['position' => 1, 'value' => 4095],
            ['position' => 5, 'value' => 2048],
            ['position' => 10, 'value' => 0],
        ];
    }

    /**
     * Saved anchors win once two exist; otherwise fall back to the hardware
     * range so auto-fill works before the user has calibrated anything.
     *
     * @param Sensor $sensor
     *
     * @return list<array{position: int, value: int}>
     */
    public static function effectivePoints(Sensor $sensor): array
    {
        $saved = $sensor->calibrationPoints()
            ->orderBy('position')
            ->get()
            ->map(fn (SensorCalibrationPoint $point): array => [
                'position' => $point->position,
                'value'    => $point->raw_value,
            ])
            ->all();

        if (count($saved) >= 2) {
            return $saved;
        }

        return self::suggestedPoints();
    }
}
