<?php

require_once __DIR__ . '/../includes/env.php';

// Values come from the .env file. The defaults below match a local XAMPP setup.
$host     = env('DB_HOST', 'localhost');
$dbname   = env('DB_NAME', 'barangay_incident_db');
$username = env('DB_USER', 'root');
$password = env('DB_PASS', '');

try {

    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $username,
        $password
    );

    $pdo->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $pdo->setAttribute(
        PDO::ATTR_DEFAULT_FETCH_MODE,
        PDO::FETCH_ASSOC
    );

} catch (PDOException $e) {

    // Log the real reason, but do not show host/user details to visitors.
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('The system is temporarily unavailable. Please try again later.');

}
