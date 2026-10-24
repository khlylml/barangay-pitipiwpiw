<?php
// OpenWeatherMap settings. The key comes from the .env file (OPENWEATHER_API_KEY).
require_once __DIR__ . '/../includes/env.php';

return [
    'api_key'  => env('OPENWEATHER_API_KEY', ''),
    'base_url' => env('OPENWEATHER_BASE_URL', 'https://api.openweathermap.org/data/2.5/weather'),
];
