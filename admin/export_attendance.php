<?php
// Include session check and database connection
require_once 'session_start.php';
include '../config/db.php';

$type = isset($_GET['type']) && strtolower(trim($_GET['type'])) === 'pdf' ? 'pdf' : 'excel';
$filterDate = isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '';

// Build filtering conditions strictly using actual database column names
$whereConditions = ["`printed` = 1"];

if (!empty($filterDate) && strtolower($filterDate) !== 'all') {
    $escapedDate = $conn->real_escape_string($filterDate);
    $whereConditions[] = "DATE(`created_at`) = '$escapedDate'";
}

$whereClause = "WHERE " . implode(" AND ", $whereConditions);

// Query using exact column names from your database screenshot:
// id, full_name, migs_category, created_at, printed
$query = "
    SELECT 
        `id` AS member_id, 
        `full_name` AS member_name, 
        COALESCE(`migs_category`, 'REGULAR') AS category, 
        `created_at` AS arrival_time 
    FROM members 
    {$whereClause} 
    ORDER BY `id` DESC
";

$result = $conn->query($query);

// Date string for export label
$dateSuffix = (!empty($filterDate) && strtolower($filterDate) !== 'all') ? $filterDate : date('Y-m-d');

/*
|--------------------------------------------------------------------------
| EXCEL EXPORT (.xls HTML Table Stream)
|--------------------------------------------------------------------------
*/
if ($type === 'excel') {
    $filename = "Attendance_Report_Printed_" . $dateSuffix . ".xls";

    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    header("Content-Disposition: attachment; filename=\"$filename\"");
    header("Pragma: no-cache");
    header("Expires: 0");

    echo '<!DOCTYPE html>';
    echo '<html><head><meta charset="UTF-8"><style>';
    echo 'table { border-collapse: collapse; width: 100%; font-family: Calibri, Arial, sans-serif; }';
    echo 'th { background-color: #2563eb; color: #ffffff; font-weight: bold; border: 1px solid #d1d5db; padding: 6px; text-align: center; }';
    echo 'td { border: 1px solid #d1d5db; padding: 6px; font-size: 13px; text-align: left; }';
    echo 'td.center { text-align: center; }';
    echo '.status-yes { color: #059669; font-weight: bold; text-align: center; }';
    echo '</style></head><body>';

    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>Member ID</th>';
    echo '<th>Member Name</th>';
    echo '<th>Category</th>';
    echo '<th>Status</th>';
    echo '<th>Default Claimed Items</th>';
    echo '<th>Timestamp</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            echo '<tr>';
            echo '<td>#' . htmlspecialchars($row['member_id'] ?? 'N/A') . '</td>';
            echo '<td>' . htmlspecialchars($row['member_name'] ?? 'N/A') . '</td>';
            echo '<td class="center">' . htmlspecialchars($row['category'] ?? 'REGULAR') . '</td>';
            echo '<td class="status-yes">PRESENT</td>';
            echo '<td>GA T-Shirt, Cash Allowance, Snacks / Meals</td>';
            echo '<td class="center">' . htmlspecialchars($row['arrival_time'] ?? '') . '</td>';
            echo '</tr>';
        }
    } else {
        echo '<tr><td colspan="6" style="text-align:center;">No printed member attendance records found.</td></tr>';
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
    <title>Printed Attendance Report - <?php echo htmlspecialchars($dateSuffix); ?></title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; color: #111827; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .header h2 { margin: 0; font-size: 20px; color: #0f172a; }
        .header p { margin: 5px 0 0 0; color: #64748b; font-size: 13px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px 10px; text-align: left; }
        th { background-color: #0f172a; color: #ffffff; text-transform: uppercase; font-size: 11px; }
        td.center { text-align: center; }
        .status-yes { color: #059669; font-weight: bold; text-align: center; }
        tr:nth-child(even) { background-color: #f8fafc; }
        @media print {
            .no-print { display: none; }
            @page { size: landscape; margin: 10mm; }
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
            <h2>Panabo Multi-Purpose Cooperative (PMPC)</h2>
            <p>Printed Members Attendance Summary Report <?php echo (!empty($filterDate) && strtolower($filterDate) !== 'all') ? " - Date: " . htmlspecialchars($filterDate) : " - All Records"; ?></p>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Member ID</th>
                    <th>Member Name</th>
                    <th>Category</th>
                    <th>Status</th>
                    <th>Default Claimed Items</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td>#<?php echo htmlspecialchars($row['member_id'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($row['member_name'] ?? 'N/A'); ?></td>
                            <td class="center"><?php echo htmlspecialchars($row['category'] ?? 'REGULAR'); ?></td>
                            <td class="status-yes">PRESENT</td>
                            <td>GA T-Shirt, Cash Allowance, Snacks / Meals</td>
                            <td class="center"><?php echo htmlspecialchars($row['arrival_time'] ?? ''); ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6" style="text-align: center; color: #64748b; padding: 20px;">
                            No printed member attendance records found.
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