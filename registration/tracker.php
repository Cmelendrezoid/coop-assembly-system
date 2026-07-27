<?php
require "db.php";
require "auth.php";

// Get date or check if "all" is requested
$view_all = isset($_GET['view_all']) && $_GET['view_all'] == '1';
$selected_date = $_GET['report_date'] ?? date('Y-m-d');

// Branch Mapping for Sidebar
$branch_names = [
    19 => 'Panabo', 20 => 'Tibungco', 21 => 'Bajada', 22 => 'Matina', 
    23 => 'Tagum', 24 => 'Sto. Tomas', 25 => 'Toril', 26 => 'Surigao', 
    27 => 'CDO', 28 => 'Valencia', 29 => 'Gensan', 30 => 'Koronadal', 
    31 => 'Butuan', 32 => 'Digos', 33 => 'Kidapawan', 34 => 'Calinan', 
    35 => 'SCWE', 36 => 'Davao City Venue'
];
$current_branch = $branch_names[$_SESSION['branch_id']] ?? 'Unknown';

/* ================= DATABASE QUERIES ================= */

// 1. Logic for Daily or Overall Toggle
$query_condition = $view_all ? "1=1" : "DATE(printed_at) = ?";
$stmt = $conn->prepare("
    SELECT 
        COUNT(*) as daily_total,
        SUM(CASE WHEN UPPER(migs_category) IN ('GOLD', 'SILVER', 'BRONZE') THEN 1 ELSE 0 END) as daily_migs,
        SUM(CASE WHEN UPPER(migs_category) NOT IN ('GOLD', 'SILVER', 'BRONZE') OR migs_category IS NULL THEN 1 ELSE 0 END) as daily_non_migs
    FROM members 
    WHERE printed = 1 AND $query_condition
");

if (!$view_all) {
    $stmt->bind_param("s", $selected_date);
}
$stmt->execute();
$daily_data = $stmt->get_result()->fetch_assoc();

// 2. Persistent Overall Total (for the small badge)
$overall_res = $conn->query("SELECT COUNT(*) as total FROM members WHERE printed = 1");
$grand_total = $overall_res->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMPC | Live Tracker</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        /* [Existing Styles Kept for Seamlessness] */
        :root {
            --royal-blue: #004aad;
            --dark-blue: #002d6b;
            --accent-green: #00ff88;
            --bg-light: #f4f7fe;
            --card-bg: #ffffff;
            --text-main: #333333;
            --text-muted: #64748b;
            --sidebar-bg: #004aad;
            --stat-box-bg: #f8fafc;
            --border-color: #edf2f7;
        }

        body.dark-mode {
            --bg-light: #020617;
            --card-bg: #0f172a;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --sidebar-bg: #000000;
            --stat-box-bg: #1e293b;
            --border-color: #334155;
            --dark-blue: #3b82f6;
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
            position: fixed;
            height: 100vh;
        }
        .sidebar h2 { font-size: 1.9rem; margin-bottom: 40px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; }
        .user-profile { background: rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; margin-bottom: 30px; }
        .user-profile .name { display: block; font-weight: 700; font-size: 14px; color: var(--accent-green); }
        .sidebar a, .theme-btn { padding: 14px 18px; color: white; text-decoration: none; border-radius: 10px; margin-bottom: 8px; display: flex; align-items: center; font-weight: 500; background: transparent; border: none; width: 100%; cursor: pointer; font-size: 16px; }
        .sidebar a:hover, .sidebar a.active, .theme-btn:hover { background: rgba(255,255,255,0.15); transform: translateX(5px); transition: 0.2s; }
        .sidebar span { margin-left: 12px; }
        .logout { margin-top: auto; color: #ff6b6b !important; }

        /* ===== MAIN AREA ===== */
        .main { flex: 1; margin-left: 280px; padding: 40px; display: flex; flex-direction: column; align-items: center; }

        .header-title { text-align: center; margin-bottom: 30px; }
        .header-title h1 { color: var(--dark-blue); font-size: 32px; font-weight: 800; }

        /* FILTER BAR */
        .filter-bar {
            background: var(--card-bg);
            padding: 10px 20px;
            border-radius: 50px;
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 40px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.05);
            border: 1px solid var(--border-color);
        }
        .filter-bar label { font-weight: 600; font-size: 14px; color: var(--text-muted); }
        .pretty-date { border: 1px solid var(--border-color); border-radius: 20px; padding: 8px 15px; font-size: 14px; background: var(--card-bg); color: var(--text-main); }
        .btn-update { background: var(--royal-blue); color: white; border: none; padding: 10px 20px; border-radius: 25px; font-weight: 600; cursor: pointer; }
        
        .view-toggle { display: flex; background: var(--bg-light); padding: 5px; border-radius: 25px; border: 1px solid var(--border-color); }
        .view-toggle a { padding: 8px 18px; border-radius: 20px; text-decoration: none; font-size: 13px; font-weight: 600; color: var(--text-muted); transition: 0.3s; }
        .view-toggle a.active { background: var(--royal-blue); color: white; }

        /* TRACKER CARD */
        .tracker-container {
            width: 100%; max-width: 800px; background: var(--card-bg); border-radius: 30px; padding: 50px; 
            box-shadow: 0 20px 50px rgba(0,0,0,0.1); text-align: center; position: relative; border: 1px solid var(--border-color);
        }
        .tracker-container::before { content: ''; position: absolute; top: 0; left: 0; width: 100%; height: 8px; background: linear-gradient(90deg, var(--royal-blue), var(--accent-green)); }

        .date-display { font-size: 14px; text-transform: uppercase; letter-spacing: 2px; color: var(--text-muted); margin-bottom: 10px; display: block; }
        .main-num { font-size: 100px; font-weight: 800; color: var(--dark-blue); line-height: 1; margin: 10px 0; letter-spacing: -2px; }
        .main-label { color: var(--text-muted); font-size: 18px; margin-bottom: 40px; }

        .stats-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;}
        .stat-box { background: var(--stat-box-bg); padding: 25px; border-radius: 20px; border: 1px solid var(--border-color); }
        .stat-box h4 { font-size: 11px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 10px; }
        .stat-box .val { font-size: 32px; font-weight: 800; color: var(--dark-blue); }

        /* OVERALL MINI BOX */
        .overall-box { background: var(--dark-blue); color: white; padding: 15px; border-radius: 15px; display: inline-flex; align-items: center; gap: 10px; margin-top: 20px; font-weight: 600;}
        .overall-box span { background: rgba(255,255,255,0.2); padding: 2px 10px; border-radius: 10px; font-size: 14px; }

        .actions { margin-top: 30px; display: flex; gap: 15px; justify-content: center; }
        .btn-action { padding: 12px 25px; border-radius: 12px; border: none; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 10px; transition: 0.3s; }
        .btn-refresh { background: var(--stat-box-bg); color: var(--text-main); border: 1px solid var(--border-color); }
        .btn-export { background: #1D6F42; color: white; }
    </style>
</head>
<body class="<?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'dark-mode' : '' ?>">

<aside class="sidebar">
    <h2>PANABO COOP <br> 2026</h2>
    <div class="user-profile">
        <span class="name"><?= htmlspecialchars($_SESSION['username']) ?></span>
        <span class="branch"><?= htmlspecialchars($current_branch) ?> Branch</span>
    </div>
    <nav>
        <a href="index.php">🏠 <span>Search Member</span></a>
        <a href="tracker.php" class="active">📈 <span>Live Attendance</span></a>
        <hr style="border: 0.5px solid rgba(255,255,255,0.1); margin: 15px 0;">
        <a href="printed.php">🟢 <span>Printed Members</span></a>
        <a href="allowance_report.php">💵 <span>Allowance Report</span></a>
        
        <button class="theme-btn" onclick="toggleTheme()">
            <span id="theme-icon"><?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? '☀️' : '🌙' ?></span>
            <span id="theme-text"><?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'Light Mode' : 'Dark Mode' ?></span>
        </button>

        <a href="logout.php" class="logout">🚪 <span>Logout System</span></a>
    </nav>
</aside>

<main class="main">
    <div class="header-title">
        <h1>Attendance Monitoring</h1>
        <p>Real-time data visualization of member check-ins</p>
    </div>
    
    <div class="filter-bar">
        <div class="view-toggle">
            <a href="tracker.php" class="<?= !$view_all ? 'active' : '' ?>">Specific Date</a>
            <a href="tracker.php?view_all=1" class="<?= $view_all ? 'active' : '' ?>">Overall (All Dates)</a>
        </div>

        <?php if(!$view_all): ?>
        <form method="GET" style="display:flex; align-items:center; gap:10px;">
            <label>Date</label>
            <input type="date" name="report_date" class="pretty-date" value="<?= htmlspecialchars($selected_date) ?>">
            <button type="submit" class="btn-update">Go</button>
        </form>
        <?php endif; ?>
    </div>

    <div class="tracker-container">
        <span class="date-display"><?= $view_all ? "Consolidated Record (2026)" : date('l, F j, Y', strtotime($selected_date)) ?></span>
        <span class="main-num"><?= number_format($daily_data['daily_total'] ?? 0) ?></span>
        <p class="main-label">Members Checked-in <?= $view_all ? "Overall" : "on this date" ?></p>

        <div class="stats-grid">
            <div class="stat-box">
                <h4>🏆 MIGS</h4>
                <span class="val" style="color: #16a34a;"><?= number_format($daily_data['daily_migs'] ?? 0) ?></span>
            </div>
            <div class="stat-box">
                <h4>👥 Non-MIGS</h4>
                <span class="val"><?= number_format($daily_data['daily_non_migs'] ?? 0) ?></span>
            </div>
        </div>

        <?php if (!$view_all): ?>
            <div class="overall-box">
                Total Attendance across all dates: <span><?= number_format($grand_total) ?></span>
            </div>
        <?php endif; ?>

        <div class="actions">
            <button class="btn-action btn-refresh" onclick="location.reload()">🔄 Refresh</button>
            <button class="btn-action btn-export" onclick="exportExcel()">📊 Export Report</button>
        </div>
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

    function exportExcel() {
        const date = document.querySelector('.pretty-date') ? document.querySelector('.pretty-date').value : '';
        const viewAll = "<?= $view_all ? '1' : '0' ?>";
        window.location.href = 'export_tracker.php?date=' + date + '&view_all=' + viewAll;
    }
</script>

</body>
</html>