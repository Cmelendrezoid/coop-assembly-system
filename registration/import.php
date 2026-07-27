<?php
/************************************
 * MIGS CSV → MYSQL IMPORTER (FIXED)
 ************************************/

set_time_limit(0);
ini_set('memory_limit', '512M');

/* ===== DB CONNECTION ===== */
$mysqli = new mysqli("localhost", "root", "", "migs_db");
if ($mysqli->connect_error) {
    die("Connection failed: " . $mysqli->connect_error);
}

$mysqli->set_charset("utf8mb4");

/* ===== RESET TABLE ===== */
$mysqli->query("TRUNCATE TABLE members");

/* ===== CSV FILE ===== */
$file = __DIR__ . "/valid.csv";
if (!file_exists($file)) {
    die("CSV file not found.");
}

$handle = fopen($file, "r");

/* ===== PREPARED STATEMENT ===== */
$stmt = $mysqli->prepare("
    INSERT INTO members 
    (full_name, migs_category, username, password, awardee, printed, printed_at)
    VALUES (?, ?, ?, ?, ?, 0, NULL)
");

$line = 0;
$imported = 0;
$skipped = 0;

/* ===== VALID CATEGORIES ===== */
$validCategories = [
    'GOLD',
    'SILVER',
    'BRONZE',
    'YOUTH SAVER',
    'POWER TEEN',
    'ASSOCIATE',
    'NON-MIGS'
];

while (($row = fgetcsv($handle, 0, ",", '"')) !== false) {

    $line++;

    /* Skip header */
    if ($line === 1) continue;

    if (count($row) < 4) {
        $skipped++;
        continue;
    }

    /* ===== NAME ===== */
    $full_name = trim($row[0]);
    if ($full_name === '') {
        $skipped++;
        continue;
    }

    /* ===== MIGS CATEGORY ===== */
    $rawCategory = strtoupper(trim($row[1]));

    if ($rawCategory === '') {
        $category = 'NON-MIGS';
    } elseif (in_array($rawCategory, $validCategories)) {
        $category = $rawCategory;
    } else {
        $category = $rawCategory;
    }

    /* ===== USERNAME / PASSWORD ===== */
    $raw_creds = trim($row[2]);

    $username = '';
    $password = '';

    if (preg_match('/Username:\s*(.+)/i', $raw_creds, $u)) {
        $username = trim($u[1]);
    }

    if (preg_match('/Password:\s*(.+)/i', $raw_creds, $p)) {
        $password = trim($p[1]);
    }

    /* ===== AWARDEE ===== */
    $awardee = 'N/A';

    $val = strtoupper(trim($row[3]));

    if ($val !== '' && strpos($val, 'AWARDEE') !== false) {
        $awardee = 'AWARDEE';
    }

    /* ===== INSERT ===== */
    $stmt->bind_param(
        "sssss",
        $full_name,
        $category,
        $username,
        $password,
        $awardee
    );

    if ($stmt->execute()) {
        $imported++;
    } else {
        $skipped++;
    }
}

fclose($handle);
$stmt->close();
$mysqli->close();

/* ===== RESULT ===== */
echo "<h2>Import Complete</h2>";
echo "Imported rows: <b>$imported</b><br>";
echo "Skipped rows: <b>$skipped</b><br>";
?>
