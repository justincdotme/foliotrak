<?php

declare(strict_types=1);

namespace App\Support\Care;

/**
 * Plain-language account of why a due date says what it says. Descriptive
 * throughout, never causal, and always carrying the sample size behind the
 * claim (ADR-0009).
 */
final class DueRationale
{
    /**
     * @param CareInterval            $interval
     * @param MoistureProjection|null $correction
     *
     * @return string
     */
    public static function for(CareInterval $interval, ?MoistureProjection $correction): string
    {
        return trim(self::interval($interval) . ' ' . self::reading($correction));
    }

    /**
     * @param CareInterval $interval
     *
     * @return string
     */
    private static function interval(CareInterval $interval): string
    {
        $cadence = sprintf('Your logged rhythm is about %s.', self::days($interval->cadenceDays));

        return match ($interval->basis) {
            'override'    => sprintf('You set this schedule to %s.', self::days($interval->days)),
            'conditioned' => $cadence . sprintf(
                ' In air like today, %s of this plant\'s soil readings have averaged closer to %s, so this proposes %s.',
                self::readings($interval->sampleSize),
                self::days((int) $interval->learnedDays),
                self::days($interval->days),
            ),
            'humidity_banded' => $cadence . sprintf(
                ' At humidity like today, %s have averaged closer to %s, so this proposes %s.',
                self::readings($interval->sampleSize),
                self::days((int) $interval->learnedDays),
                self::days($interval->days),
            ),
            'temperature_banded' => $cadence . sprintf(
                ' At temperatures like today, %s have averaged closer to %s, so this proposes %s.',
                self::readings($interval->sampleSize),
                self::days((int) $interval->learnedDays),
                self::days($interval->days),
            ),
            'plant_soil' => $cadence . sprintf(
                ' %s of this plant\'s soil suggest closer to %s, so this proposes %s.',
                ucfirst(self::readings($interval->sampleSize)),
                self::days((int) $interval->learnedDays),
                self::days($interval->days),
            ),
            default => $cadence . ' No soil reading has landed on a day that would test it yet, so this follows the rhythm alone.',
        };
    }

    /**
     * @param MoistureProjection|null $correction
     *
     * @return string
     */
    private static function reading(?MoistureProjection $correction): string
    {
        if ($correction === null) {
            return '';
        }

        return sprintf(
            'Soil read %s of 10 on %s%s, which carries the next watering to %s.',
            self::trim($correction->anchor->value),
            $correction->anchor->at->format('M j'),
            $correction->anchor->source === 'sensor' ? ' from a moisture sensor' : '',
            $correction->dueDate->format('M j'),
        );
    }

    /**
     * @param integer $days
     *
     * @return string
     */
    private static function days(int $days): string
    {
        return $days . ' day' . ($days === 1 ? '' : 's');
    }

    /**
     * @param integer $count
     *
     * @return string
     */
    private static function readings(int $count): string
    {
        return $count . ' reading' . ($count === 1 ? '' : 's');
    }

    /**
     * @param float $value
     *
     * @return string
     */
    private static function trim(float $value): string
    {
        return rtrim(rtrim(number_format($value, 1), '0'), '.');
    }
}
