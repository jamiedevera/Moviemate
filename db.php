<?php
// db.php

$host   = getenv('DB_HOST');
$port   = getenv('DB_PORT');
$dbname = getenv('DB_NAME');
$user   = getenv('DB_USER');
$pass   = getenv('DB_PASS');


$dsn = "pgsql:host=$host;port=$port;dbname=$dbname;sslmode=require";

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    // If it fails, print the exact error message
    die("DB error: " . $e->getMessage());
}
