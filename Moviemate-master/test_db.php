<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h3>PHP Configuration Check</h3>";
echo "PDO Drivers: " . implode(", ", PDO::getAvailableDrivers()) . "<br>";
echo "OpenSSL Loaded: " . (extension_loaded('openssl') ? 'Yes' : 'No') . "<br>";
echo "cURL Loaded: " . (extension_loaded('curl') ? 'Yes' : 'No') . "<br>";

echo "<h3>Database Connection Test</h3>";
require_once 'db.php';

try {
    $stmt = $pdo->query("SELECT version()");
    $row = $stmt->fetch();
    echo "<b style='color:green'>SUCCESS!</b> Connected to: " . $row['version'];
} catch (Exception $e) {
    echo "<b style='color:red'>FAILED:</b> " . $e->getMessage();
}

echo "<h3>HTTPS Connection Test (TMDB API)</h3>";
// TMDB and other APIs require HTTPS.
$testUrl = "https://www.google.com";
$test = @file_get_contents($testUrl);
if ($test) {
    echo "<b style='color:green'>SUCCESS!</b> Can access HTTPS sites.";
} else {
    echo "<b style='color:red'>FAILED:</b> Cannot access HTTPS sites. OpenSSL might still be disabled.<br>";
    echo "<i>Tip: Try restarting the server from the PHP directory:</i><br>";
    echo "<code>cd C:\Users\jamie.devera\php</code><br>";
    echo "<code>.\php.exe -S localhost:8000 -t \"\\\\egs2\OJT Files\jamie.devera\Downloads\Moviemate-master\Moviemate-master\"</code>";
}
