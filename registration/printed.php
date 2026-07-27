<?php 
require "auth.php"; 
require "db.php";

$search = trim($_GET['q'] ?? '');

// Branch Mapping for UI Display
$branch_names = [
    19 => 'Panabo', 20 => 'Tibungco', 21 => 'Bajada', 22 => 'Matina', 
    23 => 'Tagum', 24 => 'Sto. Tomas', 25 => 'Toril', 26 => 'Surigao', 
    27 => 'CDO', 28 => 'Valencia', 29 => 'Gensan', 30 => 'Koronadal', 
    31 => 'Butuan', 32 => 'Digos', 33 => 'Kidapawan', 34 => 'Calinan', 35 => 'SCWE'
];
$current_branch = $branch_names[$_SESSION['branch_id']] ?? 'Unknown';

// Base query
$sql = "
    SELECT full_name, migs_category, printed_at
    FROM members
    WHERE printed = 1
";

// Security: If not admin, restrict to user's branch
if ($_SESSION['role'] !== 'admin') {
    $sql .= " AND branch_id = " . intval($_SESSION['branch_id']);
}

// Add search condition
if ($search !== '') {
    $sql .= " AND full_name LIKE ?";
}

$sql .= " ORDER BY printed_at DESC";

$stmt = $conn->prepare($sql);

if ($search !== '') {
    $like = "%$search%";
    $stmt->bind_param("s", $like);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMPC | Printed Members</title>
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
            --input-bg: #ffffff;
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
            --input-bg: #1e293b;
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
            z-index: 1000;
        }

        .sidebar.collapsed { width: 80px; }
        .sidebar h2 { font-size: 1.9rem; margin-bottom: 40px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; }
        .sidebar.collapsed h2, .sidebar.collapsed .user-profile { display: none; }

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

        .sidebar span { margin-left: 12px; }
        .sidebar.collapsed span { display: none; }
        .logout { margin-top: auto; color: #ff6b6b !important; border: 1px solid rgba(255,107,107,0.2) !important; }

        /* ===== MAIN CONTENT ===== */
        .main {
            flex: 1;
            margin-left: 280px;
            padding: 40px;
            transition: margin-left 0.3s ease;
        }
        .main.collapsed { margin-left: 80px; }

        .header-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 40px;
        }

        .page-title h1 { font-size: 28px; color: var(--dark-blue); }
        .page-title p { color: var(--text-muted); margin-top: 5px; }

        /* ===== SEARCH BOX ===== */
        .search-container {
            margin-bottom: 30px;
            position: relative;
            max-width: 500px;
        }

        .search-container input {
            width: 100%;
            padding: 15px 25px;
            border-radius: 15px;
            border: 1px solid var(--border-color);
            background: var(--input-bg);
            color: var(--text-main);
            font-size: 16px;
            outline: none;
            box-shadow: 0 4px 12px rgba(0,0,0,0.03);
            transition: 0.3s;
        }

        .search-container input:focus {
            border-color: var(--royal-blue);
            box-shadow: 0 4px 20px rgba(0,74,173,0.1);
        }

        /* ===== CARDS ===== */
        .card-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 20px;
        }

        .member-card {
            background: var(--card-bg);
            padding: 25px;
            border-radius: 20px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.02);
            border: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            border-left: 6px solid #2ecc71;
            transition: transform 0.2s;
        }

        .member-card:hover { transform: translateY(-5px); }

        .member-card .m-name { 
            font-size: 18px; 
            font-weight: 700; 
            color: var(--dark-blue); 
            margin-bottom: 10px;
            text-transform: uppercase;
        }

        .member-card .m-meta {
            display: flex;
            flex-direction: column;
            gap: 8px;
            font-size: 14px;
            color: var(--text-muted);
        }

        .m-meta span b { color: var(--text-main); }

        .badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(22, 163, 74, 0.1);
            color: #2ecc71;
            width: fit-content;
        }

        .empty-state {
            text-align: center;
            padding: 60px;
            background: var(--card-bg);
            border-radius: 20px;
            color: var(--text-muted);
            border: 1px solid var(--border-color);
        }

        .toggle-btn {
            position: fixed; top: 20px; left: 262px;
            width: 35px; height: 35px; border-radius: 50%;
            border: none; cursor: pointer; background: var(--royal-blue);
            color: white; z-index: 1001; transition: 0.3s;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .sidebar.collapsed + .toggle-btn { left: 62px; }

        @media (max-width: 768px) {
            .card-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="<?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'dark-mode' : '' ?>">

<aside class="sidebar" id="sidebar">
    <h2>PANABO COOP <br> 2026</h2>

    <div class="user-profile">
        <span class="name"><?= htmlspecialchars($_SESSION['username']) ?></span>
        <span class="branch"><?= htmlspecialchars($current_branch) ?> Branch</span>
    </div>

    <nav>
        <a href="index.php">🏠 <span>Search Member</span></a>
        <a href="tracker.php">📈 <span>Live Attendance</span></a>
        <hr style="border: 0.5px solid rgba(255,255,255,0.1); margin: 15px 0;">
        <a href="printed.php" class="active">🟢 <span>Printed Members</span></a>
        <a href="allowance_report.php">💵 <span>Allowance Report</span></a>
        
        <button class="theme-btn" onclick="toggleTheme()">
            <span id="theme-icon"><?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? '☀️' : '🌙' ?></span>
            <span id="theme-text"><?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'Light Mode' : 'Dark Mode' ?></span>
        </button>

        <a href="logout.php" class="logout">🚪 <span>Logout System</span></a>
    </nav>
</aside>

<main class="main" id="main">
    <div class="header-flex">
        <div class="page-title">
            <h1>Printed Members</h1>
            <p>List of all verified and printed records</p>
        </div>
    </div>

    <div class="search-container">
        <form method="get">
            <input
                type="text"
                name="q"
                placeholder="Search by full name..."
                value="<?= htmlspecialchars($search) ?>"
                autocomplete="off"
            >
        </form>
    </div>

    <?php if ($result->num_rows === 0): ?>
        <div class="empty-state">
            <p>No printed members found for this branch matching your search.</p>
        </div>
    <?php else: ?>
        <div class="card-grid">
            <?php while ($row = $result->fetch_assoc()): ?>
                <div class="member-card">
                    <div class="m-name"><?= htmlspecialchars($row['full_name']) ?></div>
                    <div class="m-meta">
                        <span><b>Category:</b> <?= htmlspecialchars($row['migs_category']) ?></span>
                        <span><b>Printed On:</b> <?= date("F j, Y • g:i a", strtotime($row['printed_at'])) ?></span>
                        <div class="badge">Verified Printed</div>
                    </div>
                </div>
            <?php endwhile; ?>
        </div>
    <?php endif; ?>
</main>

<script>
function toggleSidebar() {
    document.getElementById("sidebar").classList.toggle("collapsed");
    document.getElementById("main").classList.toggle("collapsed");
}

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