<?php
// Include session check and database connection
require_once 'session_start.php';
include '../config/db.php';

$type = isset($_GET['type']) ? strtolower(trim($_GET['type'])) : 'excel';
$filterDate = isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '';

// Detect available columns in members table dynamically
$member_id_col = 'id';
$member_category_col = '';
$member_branch_col = '';

$cols = $conn->query("SHOW COLUMNS FROM members");
if ($cols) {
    while ($c = $cols->fetch_assoc()) {
        $field = $c['Field'];
        $field_lower = strtolower($field);

        if (in_array($field_lower, ['member_id', 'id'])) {
            // Prefer member_id if present, otherwise default to id
            if ($field_lower === 'member_id') {
                $member_id_col = $field;
            }
        }
        if (in_array($field_lower, ['category', 'member_type', 'type', 'membership_type'])) {
            $member_category_col = $field;
        }
        if (in_array($field_lower, ['branch', 'branch_name'])) {
            $member_branch_col = $field;
        }
    }
}

// Build SQL dynamic column selects and group expressions
$category_sql = !empty($member_category_col) 
    ? "COALESCE(m.`{$member_category_col}`, 'REGULAR') AS category" 
    : "'REGULAR' AS category";

$branch_sql = !empty($member_branch_col) 
    ? "COALESCE(m.`{$member_branch_col}`, 'Main Branch') AS branch" 
    : "'Main Branch' AS branch";

$group_by_category = !empty($member_category_col) ? ", m.`{$member_category_col}`" : "";
$group_by_branch = !empty($member_branch_col) ? ", m.`{$member_branch_col}`" : "";

// Build filtering condition
$whereClause = "";
if (!empty($filterDate)) {
    $escapedDate = $conn->real_escape_string($filterDate);
    $whereClause = " WHERE DATE(c.claimed_at) = '$escapedDate'";
}

// Query claims with Branch included from members table
$query = "
    SELECT 
        c.member_id, 
        c.member_name, 
        {$category_sql}, 
        {$branch_sql},
        MAX(CASE WHEN c.item_name LIKE '%T-Shirt%' THEN 'YES' ELSE 'NO' END) AS tshirt_claimed,
        MAX(CASE WHEN c.item_name LIKE '%Cash Allowance%' THEN 'YES' ELSE 'NO' END) AS cash_claimed,
        MAX(CASE WHEN c.item_name LIKE '%Snacks%' OR c.item_name LIKE '%Meals%' THEN 'YES' ELSE 'NO' END) AS snacks_claimed,
        MAX(CASE WHEN c.item_name LIKE '%Umbrella%' THEN 'YES' ELSE 'NO' END) AS umbrella_claimed,
        MAX(CASE WHEN c.item_name LIKE '%Water Bottle%' OR c.item_name LIKE '%Gold%' THEN 'YES' ELSE 'NO' END) AS bottle_claimed,
        c.processed_by,
        DATE(MAX(c.claimed_at)) AS distribution_date 
    FROM member_freebie_claims c
    LEFT JOIN members m ON c.member_id = m.`{$member_id_col}`
    $whereClause 
    GROUP BY c.member_id, c.member_name {$group_by_category} {$group_by_branch}, c.processed_by
    ORDER BY distribution_date DESC, c.member_id DESC
";

$result = $conn->query($query);

// Date string for filename
$dateSuffix = !empty($filterDate) ? $filterDate : date('Y-m-d');

/*
|--------------------------------------------------------------------------
| EXCEL EXPORT (.xls HTML Table Stream)
|--------------------------------------------------------------------------
*/
if ($type === 'excel') {
    $filename = "Freebies_Distribution_Log_" . $dateSuffix . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<!DOCTYPE html>';
    echo '<html><head><meta charset="UTF-8"><style>';
    echo 'table { border-collapse: collapse; width: 100%; font-family: Calibri, Arial, sans-serif; }';
    echo 'th { background-color: #ffffff; color: #000000; font-weight: bold; border: 1px solid #d1d5db; padding: 6px; text-align: center; }';
    echo 'td { border: 1px solid #d1d5db; padding: 6px; font-size: 13px; text-align: center; }';
    echo 'td.left { text-align: left; }';
    echo '.yes { color: #10b981; font-weight: bold; }';
    echo '.no { color: #9ca3af; }';
    echo '</style></head><body>';

    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Member ID</th>';
    echo '<th>Member Name</th>';
    echo '<th>Category</th>';
    echo '<th>Branch</th>';
    echo '<th>GA T-Shirt</th>';
    echo '<th>Cash Allowance</th>';
    echo '<th>Snacks / Meals</th>';
    echo '<th>PMPC Umbrella</th>';
    echo '<th>Water Bottle (Gold)</th>';
    echo '<th>Processed By</th>';
    echo '<th>Distribution Date</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td class="left">#' . htmlspecialchars($row['member_id']) . '</td>';
            echo '<td class="left">' . htmlspecialchars($row['member_name']) . '</td>';
            echo '<td>' . htmlspecialchars($row['category']) . '</td>';
            echo '<td>' . htmlspecialchars($row['branch']) . '</td>';
            echo '<td class="' . ($row['tshirt_claimed'] === 'YES' ? 'yes' : 'no') . '">' . $row['tshirt_claimed'] . '</td>';
            echo '<td class="' . ($row['cash_claimed'] === 'YES' ? 'yes' : 'no') . '">' . $row['cash_claimed'] . '</td>';
            echo '<td class="' . ($row['snacks_claimed'] === 'YES' ? 'yes' : 'no') . '">' . $row['snacks_claimed'] . '</td>';
            echo '<td class="' . ($row['umbrella_claimed'] === 'YES' ? 'yes' : 'no') . '">' . $row['umbrella_claimed'] . '</td>';
            echo '<td class="' . ($row['bottle_claimed'] === 'YES' ? 'yes' : 'no') . '">' . $row['bottle_claimed'] . '</td>';
            echo '<td>' . htmlspecialchars($row['processed_by'] ?? 'System') . '</td>';
            echo '<td>' . htmlspecialchars($row['distribution_date']) . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="11" style="text-align:center;">No claim logs recorded for this date.</td></tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '</body></html>';
    exit;
}

/*
|--------------------------------------------------------------------------
| PDF EXPORT (Printable HTML Document)
|--------------------------------------------------------------------------
*/
if ($type === 'pdf') {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
    <meta charset="UTF-8">
    <title>Freebies Distribution Log - <?php echo htmlspecialchars($dateSuffix); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #111827; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 20px; color: #0f172a; }
        .header p { margin: 5px 0 0 0; color: #64748b; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 11px; }
        th, td { border: 1px solid #cbd5e1; padding: 6px 8px; text-align: center; }
        th { background-color: #0f172a; color: #ffffff; text-transform: uppercase; font-size: 10px; }
        td.left { text-align: left; }
        tr:nth-child(even) { background-color: #f8fafc; }
        .yes { font-weight: bold; color: #059669; }
        .no { color: #9ca3af; }
        @media print {
            .no-print { display: none; }
        }
    </style>
    </head>
    <body onload="window.print()">
        <div class="no-print" style="margin-bottom: 15px;">
            <button onclick="window.print()" style="padding: 8px 16px; background: #2563eb; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: bold;">
                Print PDF
            </button>
        </div>
        <div class="header">
            <h2>Panabo Multi-Purpose Cooperative</h2>
            <p>Freebies & Item Distribution Log - <?php echo htmlspecialchars($dateSuffix); ?></p>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Member ID</th>
                    <th>Member Name</th>
                    <th>Category</th>
                    <th>Branch</th>
                    <th>GA T-Shirt</th>
                    <th>Cash Allowance</th>
                    <th>Snacks / Meals</th>
                    <th>PMPC Umbrella</th>
                    <th>Water Bottle (Gold)</th>
                    <th>Processed By</th>
                    <th>Distribution Date</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td class="left">#<?php echo htmlspecialchars($row['member_id']); ?></td>
                            <td class="left"><?php echo htmlspecialchars($row['member_name']); ?></td>
                            <td><?php echo htmlspecialchars($row['category']); ?></td>
                            <td><?php echo htmlspecialchars($row['branch']); ?></td>
                            <td class="<?php echo $row['tshirt_claimed'] === 'YES' ? 'yes' : 'no'; ?>"><?php echo $row['tshirt_claimed']; ?></td>
                            <td class="<?php echo $row['cash_claimed'] === 'YES' ? 'yes' : 'no'; ?>"><?php echo $row['cash_claimed']; ?></td>
                            <td class="<?php echo $row['snacks_claimed'] === 'YES' ? 'yes' : 'no'; ?>"><?php echo $row['snacks_claimed']; ?></td>
                            <td class="<?php echo $row['umbrella_claimed'] === 'YES' ? 'yes' : 'no'; ?>"><?php echo $row['umbrella_claimed']; ?></td>
                            <td class="<?php echo $row['bottle_claimed'] === 'YES' ? 'yes' : 'no'; ?>"><?php echo $row['bottle_claimed']; ?></td>
                            <td><?php echo htmlspecialchars($row['processed_by'] ?? 'System'); ?></td>
                            <td><?php echo htmlspecialchars($row['distribution_date']); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="11" style="text-align: center; color: #64748b; padding: 20px;">
                            No claim records found for this selection.
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </body>
    </html>
    <?php
    exit;
}
?>