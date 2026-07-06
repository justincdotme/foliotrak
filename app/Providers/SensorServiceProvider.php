<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\SensorReadingSource;
use App\Services\Sensors\GondolaAdapter;
use Illuminate\Support\ServiceProvider;

class SensorServiceProvider extends ServiceProvider
{
    /** @return void */
    public function register(): void
    {
        $this->app->singleton(SensorReadingSource::class, GondolaAdapter::class);
    }
}
