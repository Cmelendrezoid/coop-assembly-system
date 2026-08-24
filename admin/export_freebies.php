<?php
require_once 'session_start.php';
include '../config/db.php';

// Auto-check and add processed_by column if missing in database
$checkCol = $conn->query("SHOW COLUMNS FROM `attendance_logs` LIKE 'processed_by'");
if ($checkCol && $checkCol->num_rows == 0) {
    $conn->query("ALTER TABLE `attendance_logs` ADD COLUMN `processed_by` VARCHAR(100) DEFAULT 'System Admin' AFTER `claimed_items` ");
}

$type = isset($_GET['type']) && $_GET['type'] === 'pdf' ? 'pdf' : 'excel';
$filterDate = isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '';

$whereClause = "";
if (!empty($filterDate)) {
    $escapedDate = $conn->real_escape_string($filterDate);
    $whereClause = " WHERE DATE(created_at) = '$escapedDate'";
}

// Fetch attendance logs including processed_by staff column
$query = "SELECT member_id, member_name, category, claimed_items, processed_by, created_at FROM attendance_logs $whereClause ORDER BY created_at DESC";
$result = $conn->query($query);

if ($type === 'excel') {
    $filename = "Freebies_Distribution_Log_" . date('Y-m-d_H-i') . ".xls";
    header("Content-Type: application/vnd.ms-excel");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");
    ?>
    <table border="1">
        <thead>
            <tr style="background-color: #10b981; color: #ffffff;">
                <th>Member ID</th>
                <th>Member Name</th>
                <th>Category</th>
                <th>Freebies Claimed</th>
                <th>Processed By</th>
                <th>Distribution Timestamp</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($result && $result->num_rows > 0): ?>
                <?php while ($row = $result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($row['member_id'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['member_name'] ?? ''); ?></td>
                        <td><?php echo htmlspecialchars($row['category'] ?? 'REGULAR'); ?></td>
                        <td><?php echo htmlspecialchars($row['claimed_items'] ?? 'GA T-Shirt, Cash Allowance, Snacks / Meals'); ?></td>
                        <td><?php echo htmlspecialchars($row['processed_by'] ?? $_SESSION['admin_username'] ?? 'System Admin'); ?></td>
                        <td><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></td>
                    </tr>
                <?php endwhile; ?>
            <?php endif; ?>
        </tbody>
    </table>
    <?php
    exit;
} else {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <title>Freebies Distribution Log PDF</title>
        <style>
            body { font-family: Arial, sans-serif; font-size: 12px; margin: 20px; }
            h2 { text-align: center; color: #0f172a; margin-bottom: 5px; }
            p { text-align: center; color: #64748b; margin-top: 0; }
            table { width: 100%; border-collapse: collapse; margin-top: 15px; }
            th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; }
            th { background-color: #0f172a; color: #ffffff; }
            tr:nth-child(even) { background-color: #f8fafc; }
            @media print {
                @page { size: landscape; margin: 10mm; }
            }
        </style>
    </head>
    <body onload="window.print()">
        <h2>Panabo Multi-Purpose Cooperative (PMPC)</h2>
        <p>Freebies & Item Distribution Log <?php echo !empty($filterDate) ? " - Date: " . htmlspecialchars($filterDate) : ""; ?></p>
        <table>
            <thead>
                <tr>
                    <th>Member ID</th>
                    <th>Member Name</th>
                    <th>Category</th>
                    <th>Claimed Freebies</th>
                    <th>Processed By</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($row['member_id'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['member_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($row['category'] ?? 'REGULAR'); ?></td>
                            <td><?php echo htmlspecialchars($row['claimed_items'] ?? 'GA T-Shirt, Cash Allowance, Snacks / Meals'); ?></td>
                            <td><?php echo htmlspecialchars($row['processed_by'] ?? $_SESSION['admin_username'] ?? 'System Admin'); ?></td>
                            <td><?php echo htmlspecialchars($row['created_at'] ?? ''); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="6" style="text-align:center;">No records found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}
?>