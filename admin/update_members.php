<?php
// =========================================================================
// CSV DATABASE UPDATE UTILITY FOR MEMBERS TABLE
// =========================================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'coopevoting';

$message = '';
$error = '';
$stats = null;

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Database Connection Error: " . $e->getMessage());
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = "File upload failed with error code: " . $file['error'];
    } else {
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext !== 'csv') {
            $error = "Invalid file type. Please upload a valid .csv file.";
        } else {
            $handle = fopen($file['tmp_name'], 'r');
            if ($handle !== false) {
                // Remove UTF-8 BOM if present
                $bom = fread($handle, 3);
                if ($bom !== "\xEF\xBB\xBF") {
                    rewind($handle);
                }

                $header = fgetcsv($handle, 1000, ',');

                if (!$header) {
                    $error = "The uploaded CSV file appears to be empty.";
                } else {
                    // Map headers to lowercase trimmed strings
                    $map = [];
                    foreach ($header as $index => $colName) {
                        $cleanCol = strtolower(trim($colName));
                        $map[$cleanCol] = $index;
                    }

                    // Flexible Column Matching
                    $id_idx       = $map['id'] ?? $map['member_id'] ?? $map['member id'] ?? null;
                    $name_idx     = $map['full_name'] ?? $map['fullname'] ?? $map['name'] ?? $map['member_name'] ?? null;
                    $cat_idx      = $map['migs_category'] ?? $map['category'] ?? $map['migs category'] ?? null;
                    $branch_idx   = $map['branch_name'] ?? $map['branch'] ?? $map['branch name'] ?? null;
                    $user_idx     = $map['username'] ?? null;
                    $pass_idx     = $map['password'] ?? null;

                    if ($id_idx === null || $name_idx === null) {
                        $error = "CSV Missing Required Headers! CSV must contain at least 'id' (or 'member_id') and 'full_name' (or 'name').";
                    } else {
                        $processedCount = 0;
                        $insertedOrUpdated = 0;

                        $sql = "
                            INSERT INTO members (id, full_name, migs_category, branch_name, username, password)
                            VALUES (:id, :full_name, :migs_category, :branch_name, :username, :password)
                            ON DUPLICATE KEY UPDATE
                                full_name = VALUES(full_name),
                                migs_category = COALESCE(VALUES(migs_category), migs_category),
                                branch_name = COALESCE(VALUES(branch_name), branch_name),
                                username = COALESCE(VALUES(username), username),
                                password = COALESCE(VALUES(password), password)
                        ";

                        $stmt = $pdo->prepare($sql);

                        $pdo->beginTransaction();
                        try {
                            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                                if (empty($row) || !array_filter($row)) {
                                    continue; // Skip empty lines
                                }

                                $id_val       = trim($row[$id_idx] ?? '');
                                $name_val     = trim($row[$name_idx] ?? '');
                                $cat_val      = $cat_idx !== null ? trim($row[$cat_idx] ?? '') : null;
                                $branch_val   = $branch_idx !== null ? trim($row[$branch_idx] ?? '') : null;
                                $user_val     = $user_idx !== null ? trim($row[$user_idx] ?? '') : null;
                                $pass_val     = $pass_idx !== null ? trim($row[$pass_idx] ?? '') : null;

                                if (empty($id_val) || empty($name_val)) {
                                    continue; // Skip rows missing ID or Name
                                }

                                $stmt->execute([
                                    ':id'            => $id_val,
                                    ':full_name'     => $name_val,
                                    ':migs_category' => $cat_val !== '' ? $cat_val : 'REGULAR',
                                    ':branch_name'   => $branch_val !== '' ? $branch_val : 'Panabo Main',
                                    ':username'      => $user_val !== '' ? $user_val : null,
                                    ':password'      => $pass_val !== '' ? $pass_val : null,
                                ]);

                                $processedCount++;
                            }

                            $pdo->commit();
                            fclose($handle);

                            $message = "Database synchronization successful! Processed <strong>{$processedCount}</strong> member records.";
                        } catch (Exception $e) {
                            if ($pdo->inTransaction()) {
                                $pdo->rollBack();
                            }
                            fclose($handle);
                            $error = "Error during CSV processing: " . $e->getMessage();
                        }
                    }
                }
            } else {
                $error = "Could not open uploaded file for reading.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Member CSV Database Sync Utility</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-color: #f8fafc;
            --card-bg: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --primary-color: #2563eb;
            --primary-hover: #1d4ed8;
        }

        body {
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            background-color: var(--bg-color);
            color: var(--text-main);
            margin: 0;
            padding: 40px 20px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }

        .upload-card {
            background: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            padding: 36px;
            width: 100%;
            max-width: 620px;
            box-shadow: 0 20px 40px -15px rgba(0,0,0,0.07);
        }

        .card-header h1 {
            font-size: 22px;
            font-weight: 800;
            margin: 0 0 6px 0;
        }

        .card-header p {
            font-size: 13px;
            color: var(--text-muted);
            margin: 0 0 24px 0;
        }

        .alert {
            padding: 14px 18px;
            border-radius: 12px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }
        .alert-danger { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        .file-dropzone {
            border: 2px dashed #cbd5e1;
            border-radius: 14px;
            padding: 32px 20px;
            text-align: center;
            background: #f8fafc;
            cursor: pointer;
            margin-bottom: 24px;
            transition: all 0.2s;
        }

        .file-dropzone:hover {
            border-color: var(--primary-color);
            background: #eff6ff;
        }

        .file-dropzone input[type="file"] {
            display: none;
        }

        .dropzone-label {
            font-size: 14px;
            font-weight: 700;
            color: var(--text-main);
            display: block;
            margin-bottom: 4px;
        }

        .dropzone-sub {
            font-size: 12px;
            color: var(--text-muted);
        }

        .file-name-display {
            margin-top: 10px;
            font-size: 12px;
            font-weight: 800;
            color: var(--primary-color);
        }

        .btn-submit {
            width: 100%;
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 14px;
            border-radius: 12px;
            font-weight: 700;
            font-size: 14px;
            cursor: pointer;
            transition: background 0.2s;
        }

        .btn-submit:hover {
            background: var(--primary-hover);
        }

        .info-box {
            background: #f1f5f9;
            border-radius: 12px;
            padding: 16px;
            margin-top: 24px;
            font-size: 12px;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .info-box strong {
            color: var(--text-main);
        }

        .btn-back {
            display: inline-block;
            margin-top: 16px;
            font-size: 13px;
            color: var(--text-muted);
            text-decoration: none;
            font-weight: 700;
        }
        .btn-back:hover { color: var(--primary-color); }
    </style>
</head>
<body>

<div class="upload-card">
    <div class="card-header">
        <h1>📊 Member Database CSV Sync</h1>
        <p>Upload your updated members CSV file to sync records into the database.</p>
    </div>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success">✅ <div><?= $message ?></div></div>
    <?php endif; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">⚠️ <div><?= $error ?></div></div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data">
        <div class="file-dropzone" onclick="document.getElementById('csv_input').click();">
            <span class="dropzone-label">📁 Click here to select CSV File</span>
            <span class="dropzone-sub">Supports .csv file formats</span>
            <div id="file-name" class="file-name-display"></div>
            <input type="file" name="csv_file" id="csv_input" accept=".csv" required onchange="showFileName(this)">
        </div>

        <button type="submit" class="btn-submit">Sync Database Now</button>
    </form>

    <div class="info-box">
        <strong>Required CSV Columns:</strong>
        <p style="margin: 4px 0 0 0;">
            The script dynamically detects column headers. Ensure your CSV has header names like:
            <br>• <code>id</code> or <code>member_id</code>
            <br>• <code>full_name</code> or <code>name</code>
            <br>• <code>migs_category</code> or <code>category</code>
            <br>• <code>branch_name</code> or <code>branch</code>
            <br>• <code>username</code> & <code>password</code> (optional)
        </p>
    </div>

    <a href="index.php" class="btn-back">← Return to Assembly Portal</a>
</div>

<script>
    function showFileName(input) {
        const display = document.getElementById('file-name');
        if (input.files && input.files[0]) {
            display.textContent = "Selected: " + input.files[0].name;
        } else {
            display.textContent = "";
        }
    }
</script>

</body>
</html>