<?php
// Prevent PHP execution timeout and memory exhaustion for large CSV imports
set_time_limit(0);
ini_set('memory_limit', '512M');

$host = 'localhost';
$user = 'root';
$pass = '';
$dbname = 'coopevoting';

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$message = "";
$messageType = "";

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_FILES["csv_file"])) {
    $fileName = $_FILES["csv_file"]["name"];
    $fileTmp  = $_FILES["csv_file"]["tmp_name"];
    $fileExt  = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($fileExt !== 'csv') {
        $message = "Error: Please upload a valid .csv file.";
        $messageType = "error";
    } else if (!empty($fileTmp) && is_uploaded_file($fileTmp)) {
        $handle = fopen($fileTmp, "r");
        
        if ($handle !== FALSE) {
            // Skip header row: id, full_name, branch_name, branch_id, migs_category, username, password, awardee
            fgetcsv($handle, 1000, ",");
            
            $inserted = 0;
            $updated = 0;
            $failed = 0;

            // Prepared statement for members table
            $stmtMember = $conn->prepare("
                INSERT INTO `members` (`member_id`, `first_name`, `last_name`, `username`, `password`, `category`, `branch`, `is_awardee`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    `first_name` = VALUES(`first_name`),
                    `last_name` = VALUES(`last_name`),
                    `username` = VALUES(`username`),
                    `password` = VALUES(`password`),
                    `category` = VALUES(`category`),
                    `branch` = VALUES(`branch`),
                    `is_awardee` = VALUES(`is_awardee`)
            ");

            // Prepared statement for event_logs table
            $stmtEventLog = $conn->prepare("
                INSERT IGNORE INTO `event_logs` (`member_id`) VALUES (?)
            ");

            // Disable autocommit for batch transaction to keep processing fast
            $conn->autocommit(FALSE);

            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $raw_id      = trim($data[0] ?? '');
                $full_name   = trim($data[1] ?? '');
                $branch      = trim($data[2] ?? '');
                // $branch_id = trim($data[3] ?? ''); // Skipped
                $category    = trim($data[4] ?? '');
                $username    = trim($data[5] ?? '');
                $raw_pass    = trim($data[6] ?? '');
                $awardee_str = strtoupper(trim($data[7] ?? ''));

                if (empty($full_name)) {
                    $failed++;
                    continue;
                }

                // Split "LastName, FirstName Middle" into separate components
                $name_parts = explode(',', $full_name, 2);
                $last_name  = trim($name_parts[0] ?? '');
                $first_name = trim($name_parts[1] ?? '');

                // Sequential member ID formatting (PMPC-00001, PMPC-00002, etc.)
                if (is_numeric($raw_id)) {
                    $member_id = sprintf("PMPC-%05d", (int)$raw_id);
                } else {
                    $member_id = sprintf("PMPC-%05d", $inserted + $updated + 1);
                }

                // Auto-generate username from full name if blank
                if (empty($username)) {
                    $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $last_name . $first_name));
                }

                // Determine Awardee Flag
                $is_awardee = ($awardee_str === 'AWARDEE') ? 1 : 0;

                // Plain Text Password Assignment
                $password = !empty($raw_pass) ? $raw_pass : $username;

                $stmtMember->bind_param("sssssssi", $member_id, $first_name, $last_name, $username, $password, $category, $branch, $is_awardee);

                if ($stmtMember->execute()) {
                    if ($stmtMember->affected_rows == 1) {
                        $inserted++;
                    } else {
                        $updated++;
                    }

                    // Create event log shell entry
                    $stmtEventLog->bind_param("s", $member_id);
                    $stmtEventLog->execute();
                } else {
                    $failed++;
                }
            }

            // Commit all records at once
            $conn->commit();
            $conn->autocommit(TRUE);

            fclose($handle);
            $stmtMember->close();
            $stmtEventLog->close();

            $message = "Import completed successfully! Inserted: $inserted | Updated: $updated | Skipped/Failed: $failed";
            $messageType = "success";
        } else {
            $message = "Unable to open the uploaded file.";
            $messageType = "error";
        }
    } else {
        $message = "Please select a valid CSV file.";
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Import Members CSV</title>
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f6f9; margin: 0; padding: 40px; }
        .card { max-width: 600px; margin: auto; background: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h2 { margin-top: 0; color: #333; }
        .alert { padding: 12px; margin-bottom: 20px; border-radius: 4px; font-weight: bold; }
        .alert.success { background-color: #d1e7dd; color: #0f5132; }
        .alert.error { background-color: #f8d7da; color: #842029; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; color: #555; }
        input[type="file"] { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { background-color: #10b981; color: white; border: none; padding: 10px 20px; font-size: 16px; border-radius: 4px; cursor: pointer; }
        button:hover { background-color: #059669; }
        .note { margin-top: 20px; font-size: 0.88em; color: #666; line-height: 1.5; background: #f8fafc; padding: 12px; border-left: 4px solid #3b82f6; }
    </style>
</head>
<body>

<div class="card">
    <h2>Import Members CSV</h2>

    <?php if (!empty($message)): ?>
        <div class="alert <?php echo $messageType; ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
    <?php endif; ?>

    <form action="" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="csv_file">Select Member CSV File (.csv only):</label>
            <input type="file" name="csv_file" id="csv_file" accept=".csv" required>
        </div>
        <button type="submit">Upload & Import Members</button>
    </form>

    <div class="note">
        <strong>Expected Columns:</strong><br>
        <code>id, full_name, branch_name, branch_id, migs_category, username, password, awardee</code>
    </div>
</div>

</body>
</html>