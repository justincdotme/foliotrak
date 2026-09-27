<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\Care\SensorSeries;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register(): void
    {
        // One read of the sensor tables serves every plant in a request.
        $this->app->singleton(SensorSeries::class);
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot(): void
    {
        //
    }
}
