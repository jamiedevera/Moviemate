<?php
header('Content-Type: text/plain');
echo "Checking Headers...\n";
echo "PHP version: " . phpversion() . "\n";
echo "SAPI: " . php_sapi_name() . "\n";
echo "Server Variables:\n";
print_r($_SERVER);
echo "\nLoaded Extensions:\n";
print_r(get_loaded_extensions());
