<?php
// Tiny .env reader (no libraries needed).
//
// It looks for the .env file in this order:
//   1) your home folder, outside public_html (the safest place)
//   2) one folder above the project
//   3) inside the project folder
//
// Lines look like:  KEY=value   (lines starting with # are comments)

function loadEnv(): array
{
    static $values = null;

    if ($values !== null) {
        return $values;
    }

    $values = [];

    $candidates = [
        dirname(__DIR__, 3) . '/.env',
        dirname(__DIR__, 2) . '/.env',
        dirname(__DIR__) . '/.env',
    ];

    foreach ($candidates as $file) {
        if (!is_file($file) || !is_readable($file)) {
            continue;
        }

        foreach (file($file, FILE_IGNORE_NEW_LINES) as $line) {
            $line = trim($line);

            if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Remove matching quotes: KEY="my value"
            if (
                strlen($value) >= 2 &&
                ($value[0] === '"' || $value[0] === "'") &&
                substr($value, -1) === $value[0]
            ) {
                $value = substr($value, 1, -1);
            }

            $values[$key] = $value;
        }

        break; // use the first .env that exists
    }

    return $values;
}

function env(string $key, ?string $default = null): ?string
{
    $values = loadEnv();

    return array_key_exists($key, $values) ? $values[$key] : $default;
}
