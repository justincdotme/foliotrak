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

        $totalNew     = 0;
        $totalFetched = 0;

        foreach ($sensors as $sensor) {
            $since    = $this->watermark($sensor);
            $fetched  = 0;
            $inserted = 0;

            foreach ($source->readingsSince($sensor->mac, $since) as $dto) {
                $fetched++;
                $inserted += $this->persist($sensor, $dto);
            }

            $totalFetched += $fetched;
            $totalNew += $inserted;
        }

        $skipped = $totalFetched - $totalNew;
        $this->info("Synced {$totalNew} new readings across {$sensors->count()} sensors ({$skipped} duplicates skipped).");

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

        return Carbon::now('UTC')->subDay();
    }

    /**
     * @param Sensor           $sensor
     * @param SensorReadingDTO $dto
     *
     * @return integer
     */
    private function persist(Sensor $sensor, SensorReadingDTO $dto): int
    {
        $meta = null;

        if ($dto->battery !== null || $dto->rssi !== null) {
            $meta = json_encode(array_filter([
                'battery' => $dto->battery,
                'rssi'    => $dto->rssi,
            ], fn ($v) => $v !== null));
        }

        return SensorReading::insertOrIgnore([
            'sensor_id'   => $sensor->id,
            'temperature' => $dto->temperature,
            'humidity'    => $dto->humidity,
            'recorded_at' => $dto->recordedAt->format('Y-m-d H:i:s'),
            'meta'        => $meta,
            'created_at'  => now(),
            'updated_at'  => now(),
        ]);
    }
}
