<?php
require "db.php";
require "auth.php";

header("Content-Type: application/vnd.ms-excel");
header("Content-Disposition: attachment; filename=GA_Allowance_Report_".date('Y-m-d').".xls");
header("Pragma: no-cache");
header("Expires: 0");

/** * SQL JOIN FIXED:
 * Matches your screenshot: 'members.allowance_processed_by' -> 'users.user_id'
 */
$sql = "SELECT m.full_name, m.branch_name, m.allowance_claimed_at, m.allowance_processed_by, u.username 
        FROM members m
        LEFT JOIN users u ON m.allowance_processed_by = u.user_id
        WHERE m.allowance_claimed = 1 
        ORDER BY m.allowance_claimed_at DESC";

$result = $conn->query($sql);
?>

<table border="1">
    <tr>
        <th colspan="5" style="background-color: #1e3c72; color: white; font-size: 14pt;">PANABO COOP - ALLOWANCE CLAIM REPORT</th>
    </tr>
    <tr style="background-color: #f2f2f2; font-weight: bold;">
        <th>Full Name</th>
        <th>Branch Name</th>
        <th>Date/Time Claimed</th>
        <th>Processed By (Username)</th>
        <th>Processor ID</th>
    </tr>
    <?php while($row = $result->fetch_assoc()): ?>
    <tr>
        <td><?= htmlspecialchars($row['full_name']) ?></td>
        <td><?= htmlspecialchars($row['branch_name']) ?></td>
        <td><?= $row['allowance_claimed_at'] ?></td>
        <td><?= htmlspecialchars($row['username'] ?? 'N/A') ?></td>
        <td><?= htmlspecialchars($row['allowance_processed_by']) ?></td>
    </tr>
    <?php endwhile; ?>
</table>