<?php require "auth.php"; ?>
<?php
require "db.php";

$message = "";
$status_class = "";

/* ================= HANDLE CSV IMPORT ================= */
if (isset($_POST['import'])) {
    if ($_FILES['csv_file']['error'] == 0) {
        $filename = $_FILES['csv_file']['tmp_name'];
        // Open file with read permissions
        $handle = fopen($filename, "r");
        
        $count = 0;
        $duplicates = 0;
        
        // Step 1: Read the first row to find the 'voucher_code' column index
        $header = fgetcsv($handle, 1000, ",");
        
        // Clean headers (remove hidden spaces or BOM characters)
        $header = array_map(function($h) { return trim(strtolower($h)); }, $header);
        $targetColumn = array_search('voucher_code', $header);

        if ($targetColumn !== FALSE) {
            // Step 2: Loop through the remaining rows
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                $code = isset($data[$targetColumn]) ? trim($data[$targetColumn]) : '';
                
                if (!empty($code)) {
                    // Using INSERT IGNORE so duplicate codes in the CSV don't stop the script
                    $stmt = $conn->prepare("INSERT IGNORE INTO wifi_vouchers (voucher_code) VALUES (?)");
                    $stmt->bind_param("s", $code);
                    
                    if ($stmt->execute()) {
                        if ($conn->affected_rows > 0) {
                            $count++;
                        } else {
                            $duplicates++;
                        }
                    }
                }
            }
            $message = "Import Finished: $count new vouchers added. $duplicates duplicates were skipped.";
            $status_class = "success";
        } else {
            $message = "Error: Column 'voucher_code' not found. Please check your Excel header.";
            $status_class = "error";
        }
        fclose($handle);
    } else {
        $message = "Upload Error: Please select a valid CSV file.";
        $status_class = "error";
    }
}

/* ================= HANDLE TRUNCATE ================= */
if (isset($_POST['clear_all'])) {
    if ($conn->query("TRUNCATE TABLE wifi_vouchers")) {
        $message = "Database Wiped: All vouchers have been deleted.";
        $status_class = "error";
    }
}

/* ================= FETCH CURRENT TOTAL ================= */
$countRes = $conn->query("SELECT COUNT(*) as total FROM wifi_vouchers");
$totalVouchers = $countRes->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Voucher Management | Panabo Coop</title>
    <style>
        body { margin: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f0f2f5; display: flex; justify-content: center; align-items: center; min-height: 100vh; }
        .container { background: white; padding: 40px; border-radius: 12px; box-shadow: 0 8px 24px rgba(0,0,0,0.15); width: 100%; max-width: 420px; text-align: center; }
        
        h2 { color: #1e3c72; margin: 0 0 10px; }
        .counter-box { background: #e8f5e9; padding: 20px; border-radius: 10px; margin: 20px 0; border: 1px solid #c8e6c9; }
        .counter { font-size: 56px; font-weight: bold; color: #2e7d32; display: block; }
        .counter-label { font-size: 12px; color: #666; text-transform: uppercase; letter-spacing: 1px; }

        .alert { padding: 15px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; line-height: 1.4; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

        .upload-section { background: #f8f9fa; padding: 20px; border-radius: 8px; border: 1px solid #ddd; }
        input[type="file"] { margin: 15px 0; font-size: 13px; width: 100%; }
        
        .btn { cursor: pointer; border: none; border-radius: 5px; font-weight: bold; transition: 0.2s; text-transform: uppercase; }
        .btn-upload { background: #1e3c72; color: white; padding: 14px; width: 100%; font-size: 14px; }
        .btn-upload:hover { background: #3a7bd5; transform: translateY(-1px); }
        
        .footer-links { margin-top: 30px; display: flex; flex-direction: column; gap: 10px; }
        .btn-back { color: #1e3c72; text-decoration: none; font-size: 14px; font-weight: bold; }
        .btn-clear { background: none; color: #b71c1c; font-size: 11px; text-decoration: underline; opacity: 0.6; }
        .btn-clear:hover { opacity: 1; }
    </style>
</head>
<body>

<div class="container">
    <h2>Voucher Pool</h2>
    
    <div class="counter-box">
        <span class="counter"><?= number_format($totalVouchers) ?></span>
        <span class="counter-label">Available Vouchers</span>
    </div>

    <?php if ($message): ?>
        <div class="alert <?= $status_class ?>"><?= $message ?></div>
    <?php endif; ?>

    <div class="upload-section">
        <form method="post" enctype="multipart/form-data">
            <p style="font-size: 12px; color: #444; margin: 0;">Upload your CSV file</p>
            <input type="file" name="csv_file" accept=".csv" required>
            <button type="submit" name="import" class="btn btn-upload">📥 Import to Database</button>
        </form>
    </div>

    <div class="footer-links">
        <a href="view.php?id=1" class="btn-back">← Back to Member View</a>
        
        <form method="post" onsubmit="return confirm('Are you sure? This will delete ALL vouchers from the system.');">
            <button type="submit" name="clear_all" class="btn btn-clear">Clear Database Table</button>
        </form>
    </div>
</div>

</body>
</html>