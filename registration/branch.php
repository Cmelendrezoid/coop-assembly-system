<?php
require "auth.php";
require "db.php";

// Security: Only Admins should see the full breakdown
if ($_SESSION['role'] !== 'admin') {
    die("Access Denied.");
}

/**
 * The Query:
 * 1. COALESCE(b.branch_name, 'UNASSIGNED/NULL') ensures that if a member 
 * has no branch_id, they are grouped under "UNASSIGNED/NULL".
 * 2. We use a LEFT JOIN from members to branches to catch every single member record.
 */
$sql = "SELECT 
            COALESCE(b.branch_name, '⚠️ UNASSIGNED / NULL') AS branch_display, 
            COUNT(m.id) AS total_members,
            SUM(CASE WHEN m.allowance_claimed = 1 THEN 1 ELSE 0 END) AS total_claimed,
            SUM(CASE WHEN m.allowance_claimed = 0 OR m.allowance_claimed IS NULL THEN 1 ELSE 0 END) AS total_unclaimed
        FROM members m
        LEFT JOIN branches b ON m.branch_id = b.branch_id
        GROUP BY b.branch_name
        ORDER BY total_members DESC";

$result = $conn->query($sql);

// Calculate Grand Totals for the bottom row
$grand_total = 0;
$grand_claimed = 0;
$grand_unclaimed = 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Branch Summary – PMPC 2026</title>
    <style>
        body { font-family: 'Segoe UI', Arial, sans-serif; background: #f0f2f5; margin: 0; padding: 40px; }
        .container { max-width: 900px; margin: auto; background: white; padding: 30px; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.1); }
        h2 { color: #1e3c72; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 15px; text-align: left; border-bottom: 1px solid #eee; }
        th { background: #1e3c72; color: white; text-transform: uppercase; font-size: 13px; }
        tr:hover { background: #f9f9f9; }
        .badge { padding: 5px 10px; border-radius: 20px; font-weight: bold; font-size: 14px; }
        .total { background: #e8f0fe; color: #1e3c72; }
        .claimed { background: #d4edda; color: #155724; }
        .unclaimed { background: #fff3cd; color: #856404; }
        .footer-row { background: #333 !important; color: white; font-weight: bold; }
        .back-btn { display: inline-block; margin-bottom: 20px; text-decoration: none; color: #3a7bd5; font-weight: bold; }
    </style>
</head>
<body>

<div class="container">
    <a href="index.php" class="back-btn">← Back to Search</a>
    <h2>📊 Member Distribution & Claim Status</h2>
    <p>This report includes all members, including those with missing or null branch assignments.</p>

    <table>
        <thead>
            <tr>
                <th>Branch Name</th>
                <th>Total Members</th>
                <th>Claimed</th>
                <th>Not Claimed</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): 
                $grand_total += $row['total_members'];
                $grand_claimed += $row['total_claimed'];
                $grand_unclaimed += $row['total_unclaimed'];
            ?>
            <tr>
                <td><strong><?= htmlspecialchars($row['branch_display']) ?></strong></td>
                <td><span class="badge total"><?= number_format($row['total_members']) ?></span></td>
                <td><span class="badge claimed"><?= number_format($row['total_claimed']) ?></span></td>
                <td><span class="badge unclaimed"><?= number_format($row['total_unclaimed']) ?></span></td>
            </tr>
            <?php endwhile; ?>
        </tbody>
        <tfoot>
            <tr class="footer-row">
                <td>GRAND TOTAL</td>
                <td><?= number_format($grand_total) ?></td>
                <td><?= number_format($grand_claimed) ?></td>
                <td><?= number_format($grand_unclaimed) ?></td>
            </tr>
        </tfoot>
    </table>
</div>

</body>
</html>