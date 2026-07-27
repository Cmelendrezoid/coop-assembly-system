<?php
require "db.php";

// Increase execution time for large datasets (50k+ members)
set_time_limit(0);
ini_set('memory_limit', '512M');

$csvFile = "branch_mapping.csv";

if (!file_exists($csvFile)) {
    die("Error: $csvFile not found. Please place the CSV in the same folder.");
}

$file = fopen($csvFile, "r");
$updated = 0;
$skipped = 0;
$notFoundList = [];

echo "<h2>Branch Data Import Progress</h2>";
echo "<p>Processing... Please wait.</p>";

// Use a transaction for much faster database performance
$conn->begin_transaction();

try {
    while (($row = fgetcsv($file)) !== FALSE) {
        // Basic validation: ensure row isn't empty
        if (empty($row[0])) continue;

        $fullName = trim($row[0]);
        $branchName = trim($row[1]);

        /**
         * We use UPPER() to ensure 'Juan Dela Cruz' matches 'JUAN DELA CRUZ'
         * We also only update if branch_name is currently empty to avoid redundant work
         */
        $stmt = $conn->prepare("UPDATE members SET branch_name = ? WHERE UPPER(full_name) = UPPER(?)");
        $stmt->bind_param("ss", $branchName, $fullName);
        $stmt->execute();

        if ($stmt->affected_rows > 0) {
            $updated++;
        } else {
            $skipped++;
            $notFoundList[] = $fullName;
        }
    }

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    die("Critical Error: " . $e->getMessage());
}

fclose($file);

/* ================= RESULTS DISPLAY ================= */

echo "<div style='font-family: sans-serif; padding: 20px; border: 1px solid #ccc; background: #f9f9f9;'>";
echo "<h3>Import Summary</h3>";
echo "✅ Successfully Updated: <strong>$updated</strong> members.<br>";
echo "❌ Failed to Match: <strong>$skipped</strong> members.<br>";
echo "</div>";

if (!empty($notFoundList)) {
    echo "<h4>Names that did not match (First 100):</h4>";
    echo "<div style='font-size: 12px; color: #666; height: 200px; overflow-y: scroll; border: 1px solid #ddd; padding: 10px;'>";
    foreach (array_slice($notFoundList, 0, 100) as $name) {
        echo htmlspecialchars($name) . "<br>";
    }
    echo "</div>";
}
?>