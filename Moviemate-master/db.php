<?php
// db.php

// SUPABASE CONNECTION DETAILS
// Important: Use the "Transaction pooler" or "Session pooler" Host and Port if you are on IPv4!
$host = 'aws-1-ap-southeast-1.pooler.supabase.com'; // e.g., aws-0-...pooler.supabase.com
$port = '6543'; // Supabase pooler port is usually 6543
$dbname = 'postgres';
$user = 'postgres.gfclvaawajevxuwqepfo'; // Make sure this matches the pooler user
$pass = 'I96Y8IR6xGjkwjdX'; // Replace with your actual database password

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
