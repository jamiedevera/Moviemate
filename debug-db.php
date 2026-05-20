<?php
// debug-db.php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

echo "<h2>Database Configuration Debugger</h2>";

echo "<h3>Configured Constants:</h3>";
echo "DB_HOST: " . DB_HOST . "<br>";
echo "DB_PORT: " . DB_PORT . "<br>";
echo "DB_NAME: " . DB_NAME . "<br>";
echo "DB_USER: " . DB_USER . "<br>";

$pass = DB_PASS;
$obscuredPass = '';
if (!empty($pass)) {
    $obscuredPass = (strlen($pass) > 4) 
        ? substr($pass, 0, 2) . str_repeat('*', strlen($pass) - 4) . substr($pass, -2) . " (Length: " . strlen($pass) . ")"
        : str_repeat('*', strlen($pass)) . " (Length: " . strlen($pass) . ")";
} else {
    $obscuredPass = "[EMPTY]";
}
echo "DB_PASS: " . $obscuredPass . "<br>";

echo "<h3>Testing Connection:</h3>";
$dsn = "pgsql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";sslmode=require;connect_timeout=5";
try {
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    echo "<b style='color:green'>SUCCESS! Connected to DB.</b><br>";
} catch (Exception $e) {
    echo "<b style='color:red'>CONNECTION FAILED:</b> " . $e->getMessage() . "<br>";
}

echo "<h3>Available environment variables matching DB/TMDB:</h3>";
$allVars = array_merge($_SERVER, $_ENV);
ksort($allVars);
foreach ($allVars as $key => $val) {
    if (stripos($key, 'DB') !== false || stripos($key, 'TMDB') !== false) {
        $obscuredVal = $val;
        if (stripos($key, 'PASS') !== false || stripos($key, 'KEY') !== false || stripos($key, 'SECRET') !== false) {
            $obscuredVal = !empty($val) 
                ? ((strlen($val) > 4) ? (substr($val, 0, 2) . '...' . substr($val, -2) . ' (Len: ' . strlen($val) . ')') : '... (Len: ' . strlen($val) . ')') 
                : '[EMPTY]';
        }
        echo "<b>$key</b>: $obscuredVal<br>";
    }
}
