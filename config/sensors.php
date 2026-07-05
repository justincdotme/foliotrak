<?php

return [
    'base_url' => env('SENSOR_BASE_URL', ''),
    'api_key' => env('SENSOR_API_KEY', ''),
    'granularity' => (int) env('SENSOR_GRANULARITY', 30),
    'verify' => env('SENSOR_TLS_VERIFY', false),
];
