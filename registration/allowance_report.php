<?php
require "db.php";
require "auth.php";

$branch_names = [
    19 => 'Panabo', 20 => 'Tibungco', 21 => 'Bajada', 22 => 'Matina', 
    23 => 'Tagum', 24 => 'Sto. Tomas', 25 => 'Toril', 26 => 'Surigao', 
    27 => 'CDO', 28 => 'Valencia', 29 => 'Gensan', 30 => 'Koronadal', 
    31 => 'Butuan', 32 => 'Digos', 33 => 'Kidapawan', 34 => 'Calinan', 
    35 => 'SCWE', 36 => 'Davao City Venue'
];
$current_branch = $branch_names[$_SESSION['branch_id']] ?? 'Unknown';

$sql = "SELECT m.full_name, m.branch_name, m.allowance_processed_by, m.allowance_claimed_at, u.username 
        FROM members m
        LEFT JOIN users u ON m.allowance_processed_by = u.user_id
        WHERE m.allowance_claimed = 1 
        ORDER BY m.allowance_claimed_at DESC";

$result = $conn->query($sql);
$total_claimed = $result->num_rows;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMPC | Allowance Dashboard</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <style>
        /* CSS VARIABLES */
        :root {
            --royal-blue: #004aad;
            --dark-blue: #002d6b;
            --accent-green: #00ff88;
            --bg-light: #f4f7fe;
            --card-bg: #ffffff;
            --text-main: #333333;
            --text-muted: #64748b;
            --sidebar-bg: #004aad;
            --border-color: #edf2f7;
            --table-hover: #fbfcfe;
            --table-header: #f8fafc;
        }

        /* DARK MODE OVERRIDES */
        body.dark-mode {
            --bg-light: #020617;
            --card-bg: #0f172a;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --sidebar-bg: #000000;
            --border-color: #334155;
            --dark-blue: #3b82f6;
            --table-hover: #1e293b;
            --table-header: #111827;
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }

        body { 
            background: var(--bg-light); 
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
            transition: background 0.3s, color 0.3s;
        }

        /* ===== SIDEBAR ===== */
        .sidebar {
            width: 280px;
            background: var(--sidebar-bg);
            color: white;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
            position: fixed;
            height: 100vh;
        }

        .sidebar h2 {
            font-size: 1.9rem;
            letter-spacing: 1px;
            margin-bottom: 40px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            padding-bottom: 20px;
        }

        .user-profile {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 30px;
        }

        .user-profile .name { display: block; font-weight: 700; font-size: 14px; color: var(--accent-green); }
        .user-profile .branch { font-size: 12px; opacity: 0.8; }

        .sidebar a, .theme-btn {
            padding: 14px 18px;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            transition: 0.2s;
            font-weight: 500;
            background: transparent;
            border: none;
            width: 100%;
            cursor: pointer;
            font-size: 16px;
        }

        .sidebar a:hover, .sidebar a.active, .theme-btn:hover {
            background: rgba(255,255,255,0.15);
            transform: translateX(5px);
        }

        .logout { margin-top: auto; color: #ff6b6b !important; border: 1px solid rgba(255,107,107,0.2) !important; }

        /* ===== MAIN AREA ===== */
        .content {
            flex: 1;
            margin-left: 280px;
            padding: 40px;
            overflow-y: auto;
            transition: margin-left 0.3s ease;
        }

        .header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .page-title h1 { font-size: 28px; color: var(--dark-blue); }
        .page-title p { color: var(--text-muted); margin-top: 5px; }

        /* Summary Cards */
        .stat-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            width: fit-content;
            min-width: 250px;
            border-left: 5px solid var(--royal-blue);
            border-top: 1px solid var(--border-color);
            border-right: 1px solid var(--border-color);
            border-bottom: 1px solid var(--border-color);
        }

        .stat-card .label { font-size: 11px; text-transform: uppercase; color: var(--text-muted); letter-spacing: 1px; }
        .stat-card .value { font-size: 32px; font-weight: 800; color: var(--royal-blue); display: block; }

        /* Table Card */
        .table-container {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 20px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.03);
            border: 1px solid var(--border-color);
        }

        .actions { margin-bottom: 25px; display: flex; gap: 15px; }

        .btn {
            padding: 12px 24px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            cursor: pointer;
            transition: 0.3s;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-excel { background: #1D6F42; color: white; }
        .btn-print { background: var(--royal-blue); color: white; }
        .btn:hover { opacity: 0.9; transform: translateY(-2px); }

        table { width: 100%; border-collapse: collapse; }
        th { 
            background: var(--table-header); 
            padding: 16px; 
            text-align: left; 
            font-size: 13px; 
            color: var(--text-muted); 
            text-transform: uppercase; 
            letter-spacing: 0.5px;
        }

        td { padding: 18px 16px; border-bottom: 1px solid var(--border-color); font-size: 14px; color: var(--text-main); }
        tr:last-child td { border: none; }
        tr:hover td { background: var(--table-hover); }

        .badge-user {
            background: rgba(59, 130, 246, 0.1);
            color: var(--dark-blue);
            padding: 6px 12px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 12px;
            display: inline-block;
        }

        .timestamp { color: var(--text-muted); font-size: 13px; }

        @media print { 
            .sidebar, .actions, .no-print { display: none !important; } 
            .content { margin-left: 0 !important; padding: 0; } 
            .table-container { border: none; box-shadow: none; }
        }
    </style>
</head>
<body class="<?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'dark-mode' : '' ?>">

    <aside class="sidebar" id="sidebar">
        <h2>PANABO COOP <br> 2026</h2>

        <div class="user-profile">
            <span class="name">&nbsp; &nbsp; <?= htmlspecialchars($_SESSION['username']) ?></span>
            <span class="branch">&nbsp; &nbsp; <?= htmlspecialchars($current_branch) ?> Branch</span>
        </div>

        <nav>
            <a href="index.php">🏠 <span> &nbsp; Search Member</span></a>
            <a href="tracker.php">📈 <span> &nbsp; Live Attendance</span></a>
            <hr style="border: 0.5px solid rgba(255,255,255,0.1); margin: 15px 0;">
            <a href="printed.php">🟢 <span> &nbsp; Printed Members</span></a>
            <a href="allowance_report.php" class="active">💵 <span> &nbsp; Allowance Report</span></a>
            
            <button class="theme-btn" onclick="toggleTheme()">
                <span id="theme-icon"><?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? '☀️' : '🌙' ?></span>
                <span id="theme-text"><?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'Light Mode' : 'Dark Mode' ?></span>
            </button>

            <a href="logout.php" class="logout">🚪 <span> &nbsp; Logout System</span></a>
        </nav>
    </aside>

    <main class="content">
        <div class="header-flex">
            <div class="page-title">
                <h1>Allowance Claims</h1>
                <p>Monitor and export distribution records</p>
            </div>
            <div class="stat-card">
                <span class="label">Total Distributed</span>
                <span class="value"><?= number_format($total_claimed) ?></span>
            </div>
        </div>

        <div class="table-container">
            <div class="actions no-print">
                <button class="btn btn-excel" onclick="location.href='export_allowance.php'">Export Excel</button>
                <button class="btn btn-print" onclick="window.print()">Generate PDF</button>
            </div>

            <table>
                <thead>
                    <tr>
                        <th>Member Name</th>
                        <th>Branch</th>
                        <th>Time Processed</th>
                        <th>Claim Clerk</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($total_claimed > 0): ?>
                        <?php while($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td style="font-weight: 600;"><?= htmlspecialchars($row['full_name']) ?></td>
                            <td><?= htmlspecialchars($row['branch_name']) ?></td>
                            <td class="timestamp"><?= date('M d, Y • h:i A', strtotime($row['allowance_claimed_at'])) ?></td>
                            <td>
                                <span class="badge-user">
                                    👤 <?= htmlspecialchars($row['username'] ?? 'Unknown') ?>
                                    <small style="opacity: 0.7;">(ID: <?= htmlspecialchars($row['allowance_processed_by']) ?>)</small>
                                </span>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 40px; color: var(--text-muted);">No records found.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </main>

<script>
    function toggleTheme() {
        const body = document.body;
        const icon = document.getElementById('theme-icon');
        const text = document.getElementById('theme-text');
        
        body.classList.toggle('dark-mode');
        const isDark = body.classList.contains('dark-mode');
        
        icon.innerText = isDark ? '☀️' : '🌙';
        text.innerText = isDark ? 'Light Mode' : 'Dark Mode';
        
        document.cookie = "theme=" + (isDark ? "dark" : "light") + ";max-age=" + (30*24*60*60) + ";path=/";
    }
</script>

</body>
</html>