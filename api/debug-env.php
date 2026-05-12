<?php
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "REQUEST_URI: " . $_SERVER['REQUEST_URI'] . "\n";
echo "dirname: " . dirname($_SERVER['SCRIPT_NAME']) . "\n";
echo "base (with logic): " . rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\') . "\n";
$base = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\');
$base = preg_replace('#[\\\\/]api$#', '', $base);
if ($base === '/' || $base === '\\') { $base = ''; }
echo "final base: " . $base . "\n";
