<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
set_time_limit(0);

$csvFile = __DIR__ . "/data.csv";

if (!file_exists($csvFile)) {
    die("❌ data.csv not found");
}

$handle = fopen($csvFile, "r");
if (!$handle) {
    die("❌ Cannot open CSV");
}

echo "<h2>CSV DEBUG OUTPUT (first 5 rows)</h2>";
echo "<pre>";

$lineNum = 0;

while (($line = fgets($handle)) !== false) {

    $lineNum++;

    // Convert encoding (try UTF-16 → UTF-8)
    $converted = mb_convert_encoding($line, 'UTF-8', 'UTF-16LE');

    echo "=============================\n";
    echo "RAW LINE $lineNum:\n";
    var_dump($line);

    echo "\nAFTER ENCODING CONVERSION:\n";
    var_dump($converted);

    echo "\nEXPLODE BY COMMA:\n";
    $parts = explode(',', $converted);
    var_dump($parts);

    echo "\nEXPLODE BY PIPE:\n";
    $partsPipe = explode('|', $converted);
    var_dump($partsPipe);

    if ($lineNum >= 5) {
        break;
    }
}

echo "</pre>";
fclose($handle);
