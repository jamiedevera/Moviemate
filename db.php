<?php
// db.php

// SUPABASE CONNECTION DETAILS
// Important: Use the "Transaction pooler" or "Session pooler" Host and Port if you are on IPv4!
require_once __DIR__ . '/config.php';

$dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=require;connect_timeout=5";

try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // If it fails, print the exact error message
    die("DB error: " . $e->getMessage());
}
