<?php
require "db.php";
require "auth.php";

// Get the date from the URL
$selected_date = $_GET['date'] ?? date('Y-m-d');

// Set headers to force download as Excel .xls
header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=Attendance_Summary_$selected_date.xls");
header("Pragma: no-cache");
header("Expires: 0");

/* ================= FETCH DATA ================= */
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as daily_total,
        SUM(CASE WHEN UPPER(migs_category) IN ('GOLD', 'SILVER', 'BRONZE') THEN 1 ELSE 0 END) as daily_migs,
        SUM(CASE WHEN UPPER(migs_category) NOT IN ('GOLD', 'SILVER', 'BRONZE') OR migs_category IS NULL THEN 1 ELSE 0 END) as daily_non_migs
    FROM members 
    WHERE printed = 1 AND DATE(printed_at) = ?
");
$stmt->bind_param("s", $selected_date);
$stmt->execute();
$daily_data = $stmt->get_result()->fetch_assoc();

// Also fetch breakdown by branch for a better Excel report
$branch_stmt = $conn->prepare("
    SELECT branch_name, COUNT(*) as count 
    FROM members 
    WHERE printed = 1 AND DATE(printed_at) = ? 
    GROUP BY branch_name
");
$branch_stmt->bind_param("s", $selected_date);
$branch_stmt->execute();
$branch_result = $branch_stmt->get_result();
?>

<table border="1">
    <tr>
        <th colspan="2" style="background-color: #1e3c72; color: white;">PANABO COOP GA 2026 ATTENDANCE REPORT</th>
    </tr>
    <tr>
        <td><strong>Date:</strong></td>
        <td><?= date('F j, Y', strtotime($selected_date)) ?></td>
    </tr>
    <tr>
        <td><strong>Total Arrived:</strong></td>
        <td><?= $daily_data['daily_total'] ?></td>
    </tr>
    <tr>
        <td><strong>MIGS Arrived:</strong></td>
        <td><?= $daily_data['daily_migs'] ?></td>
    </tr>
    <tr>
        <td><strong>Non-MIGS Arrived:</strong></td>
        <td><?= $daily_data['daily_non_migs'] ?></td>
    </tr>
    <tr><td></td><td></td></tr>
    <tr>
        <th colspan="2" style="background-color: #eee;">BREAKDOWN BY BRANCH</th>
    </tr>
    <tr>
        <th>Branch Name</th>
        <th>Attendance Count</th>
    </tr>
    <?php while($row = $branch_result->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['branch_name']) ?></td>
        <td><?= $row['count'] ?></td>
    </tr>
    <?php endwhile; ?>
</table>