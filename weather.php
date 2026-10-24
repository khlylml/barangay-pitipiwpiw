<?php
// Small server-side helper so the OpenWeatherMap API key stays private.
// Usage: weather.php?lat=14.5995&lng=120.9842  (logged-in users only)
session_start();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=300');

function weatherFail(int $status, string $message): void
{
    http_response_code($status);
    echo json_encode(['error' => $message]);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    weatherFail(401, 'Please log in.');
}

$lat = filter_input(INPUT_GET, 'lat', FILTER_VALIDATE_FLOAT);
$lng = filter_input(INPUT_GET, 'lng', FILTER_VALIDATE_FLOAT);

if (
    $lat === false || $lat === null || $lat < -90 || $lat > 90 ||
    $lng === false || $lng === null || $lng < -180 || $lng > 180
) {
    weatherFail(400, 'Invalid coordinates.');
}

$config = require __DIR__ . '/config/weather.php';

if (empty($config['api_key'])) {
    weatherFail(503, 'Weather is not set up yet.');
}

$url = $config['base_url'] . '?' . http_build_query([
    'lat' => $lat,
    'lon' => $lng,
    'units' => 'metric',
    'appid' => $config['api_key'],
]);

$body = false;

if (function_exists('curl_init')) {
    $curl = curl_init($url);
    curl_setopt_array($curl, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
    ]);
    $body = curl_exec($curl);
    curl_close($curl);
} else {
    $body = @file_get_contents($url, false, stream_context_create([
        'http' => ['timeout' => 8],
    ]));
}

$data = is_string($body) ? json_decode($body, true) : null;

if (!is_array($data) || !isset($data['main']['temp'])) {
    weatherFail(502, 'Weather is unavailable right now.');
}

echo json_encode([
    'description' => ucfirst($data['weather'][0]['description'] ?? 'Unknown'),
    'temp' => round($data['main']['temp']),
    'humidity' => (int) ($data['main']['humidity'] ?? 0),
    // OpenWeatherMap gives metres per second; convert to km/h.
    'wind_kmh' => round(($data['wind']['speed'] ?? 0) * 3.6),
    'rain_mm' => (float) ($data['rain']['1h'] ?? 0),
]);
