<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Contracts\SensorReadingSource;
use App\DTOs\SensorReading as SensorReadingDTO;
use App\Models\Sensor;
use App\Models\SensorReading;
use DateTimeInterface;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Throwable;

class IngestSensorReadings extends Command
{
    /** @var string */
    protected $signature = 'sensors:ingest';

    /** @var string */
    protected $description = 'Fetch sensor readings from the gateway and store locally';

    /**
     * @param SensorReadingSource $source
     *
     * @return integer
     */
    public function handle(SensorReadingSource $source): int
    {
        if (blank(config('sensors.base_url')) || blank(config('sensors.api_key'))) {
            $this->info('Sensor gateway not configured, skipping sync.');

            return self::SUCCESS;
        }

        $sensors = Sensor::all();

        if ($sensors->isEmpty()) {
            $this->info('No sensors registered, nothing to ingest.');

            return self::SUCCESS;
        }

        $totalNew        = 0;
        $totalFetched    = 0;
        $totalUnreadable = 0;

        foreach ($sensors as $sensor) {
            $since      = $this->watermark($sensor);
            $fetched    = 0;
            $inserted   = 0;
            $unreadable = 0;

            try {
                foreach ($source->readingsSince($sensor->mac, $since) as $dto) {
                    $fetched++;

                    try {
                        $inserted += $this->persist($sensor, $dto);
                    } catch (Throwable) {
                        $unreadable++;
                    }
                }
            } catch (Throwable $e) {
                // One failing sensor must not starve the rest of the fleet.
                Log::warning('Sensor ingest aborted for one sensor', [
                    'sensor_id' => $sensor->id,
                    'mac'       => $sensor->mac,
                    'error'     => $e->getMessage(),
                ]);
                $this->warn("Ingest failed for {$sensor->mac}: {$e->getMessage()}");
            }

            if ($unreadable > 0) {
                // Rows the type's transformer rejects are skipped so the watermark
                // can advance past them; a wrong sensor type is the usual cause.
                Log::warning('Skipped unreadable sensor readings', [
                    'sensor_id' => $sensor->id,
                    'mac'       => $sensor->mac,
                    'type'      => $sensor->type->value,
                    'skipped'   => $unreadable,
                ]);
                $this->warn("Skipped {$unreadable} unreadable readings for {$sensor->mac}.");
            }

            $totalFetched += $fetched;
            $totalNew += $inserted;
            $totalUnreadable += $unreadable;
        }

        $duplicates = $totalFetched - $totalNew - $totalUnreadable;
        $this->info("Synced {$totalNew} new readings across {$sensors->count()} sensors ({$duplicates} duplicates skipped).");

        return self::SUCCESS;
    }

    /**
     * @param Sensor $sensor
     *
     * @return DateTimeInterface
     */
    private function watermark(Sensor $sensor): DateTimeInterface
    {
        $latest = SensorReading::query()
            ->where('sensor_id', $sensor->id)
            ->max('recorded_at');

        if ($latest) {
            return Carbon::parse($latest);
        }

        return Carbon::now('UTC')->subWeek();
    }

    /**
     * @param Sensor           $sensor
     * @param SensorReadingDTO $dto
     *
     * @return integer
     */
    private function persist(Sensor $sensor, SensorReadingDTO $dto): int
    {
        $transformer = $sensor->type->transformer();

        $ts      = $dto->recordedAt->getTimestamp();
        $snapped = (int) (round($ts / 900) * 900);

        return SensorReading::insertOrIgnore([
            'sensor_id'   => $sensor->id,
            'data'        => json_encode($transformer->normalize($dto->data)),
            'recorded_at' => Carbon::createFromTimestamp($snapped)->format('Y-m-d H:i:s'),
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }
}
