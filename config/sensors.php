<?php

declare(strict_types=1);

return [
    'base_url' => env('SENSOR_BASE_URL', ''),
    'api_key'  => env('SENSOR_API_KEY', ''),
    // A zero or empty value would crash the scheduler's modulo gate.
    'granularity' => ((int) env('SENSOR_GRANULARITY', 30)) ?: 30,
    'verify'      => env('SENSOR_TLS_VERIFY', true),
];
