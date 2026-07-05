<?php

use App\Console\Commands\IngestSensorReadings;
use App\Console\Commands\SendCareReminders;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(SendCareReminders::class)->dailyAt('08:00');

Schedule::command(IngestSensorReadings::class)
    ->everyMinute()
    ->when(fn () => now()->minute % config('sensors.granularity', 30) === 0);
