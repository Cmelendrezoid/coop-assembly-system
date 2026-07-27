<?php 
require "auth.php"; 
require "db.php";

// Branch Mapping for UI Display
$branch_names = [
    19 => 'Panabo', 20 => 'Tibungco', 21 => 'Bajada', 22 => 'Matina', 
    23 => 'Tagum', 24 => 'Sto. Tomas', 25 => 'Toril', 26 => 'Surigao', 
    27 => 'CDO', 28 => 'Valencia', 29 => 'Gensan', 30 => 'Koronadal', 
    31 => 'Butuan', 32 => 'Digos', 33 => 'Kidapawan', 34 => 'Calinan', 35 => 'SCWE'
];

$current_branch = $branch_names[$_SESSION['branch_id']] ?? 'General Staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PANABO COOP | Member Search</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            /* Light Mode Variables */
            --royal-blue: #004aad;
            --dark-blue: #002d6b;
            --accent-green: #00ff88;
            --bg-color: #f4f7fe;
            --sidebar-bg: #004aad;
            --card-bg: #ffffff;
            --text-main: #1e293b;
            --text-muted: #64748b;
            --input-bg: #f8fafc;
            --border-color: #e2e8f0;
        }

        /* Dark Mode Variables - Deep Navy Palette */
        body.dark-mode {
            --bg-color: #000818;       /* Dark Navy */
            --sidebar-bg: #000002;    /* Near Black Blue */
            --card-bg: #1e293b;       /* Lighter Navy Card */
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --input-bg: #0f172a;
            --border-color: #334155;
            --dark-blue: #3b82f6;     /* Brighter blue for contrast in dark mode */
        }

        * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }

        body { 
            background: var(--bg-color); 
            color: var(--text-main);
            display: flex;
            min-height: 100vh;
            overflow-x: hidden;
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
        .sidebar h2 { font-size: 1.9rem; margin-bottom: 40px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; white-space: nowrap; }
        .sidebar.collapsed h2, .sidebar.collapsed .user-profile { display: none; }

        .user-profile {
            background: rgba(255,255,255,0.1);
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 30px;
        }
        .user-profile .name { display: block; font-weight: 700; font-size: 14px; color: var(--accent-green); }
        .user-profile .branch { font-size: 12px; opacity: 0.8; }

        .sidebar a, .theme-toggle {
            padding: 14px 18px;
            color: white;
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 8px;
            display: flex;
            align-items: center;
            transition: 0.2s;
            font-weight: 500;
            border: none;
            background: transparent;
            width: 100%;
            cursor: pointer;
            font-size: 16px;
        }
        .sidebar a:hover, .sidebar a.active, .theme-toggle:hover { 
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
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
        }
        .main.collapsed { margin-left: 80px; }

        .hero-section { margin-bottom: 40px; }
        .hero-section h1 { 
            font-size: 3.5rem; 
            font-weight: 800; 
            color: var(--royal-blue); /* Keeps brand identity */
            line-height: 1.1;
            margin-bottom: 15px;
        }
        body.dark-mode .hero-section h1 { color: #60a5fa; } /* Brighter blue for dark mode */

        .hero-section h2 {
            font-size: 1.9rem;
            color: var(--text-muted);
            font-weight: 400;
            letter-spacing: 1px;
        }

        /* ===== SEARCH BOX ===== */
        .search-card {
            background: var(--card-bg);
            padding: 50px;
            border-radius: 30px;
            box-shadow: 0 20px 50px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 600px;
            border: 1px solid var(--border-color);
            position: relative;
            transition: background 0.3s, border 0.3s;
        }

        .search-group { display: flex; flex-direction: column; gap: 15px; }

        input[type="text"] {
            width: 100%;
            padding: 18px 25px;
            border-radius: 15px;
            border: 2px solid var(--border-color);
            font-size: 18px;
            outline: none;
            transition: 0.3s;
            background: var(--input-bg);
            color: var(--text-main);
        }

        input[type="text"]:focus {
            border-color: var(--royal-blue);
            box-shadow: 0 0 0 4px rgba(0, 74, 173, 0.1);
        }

        .search-btn {
            padding: 18px;
            border: none;
            border-radius: 15px;
            background: linear-gradient(135deg, var(--royal-blue), var(--dark-blue));
            color: white;
            font-size: 18px;
            font-weight: 700;
            cursor: pointer;
            transition: 0.3s;
            box-shadow: 0 10px 20px rgba(0, 74, 173, 0.2);
        }

        .search-btn:hover { transform: translateY(-3px); filter: brightness(1.1); }

        .toggle-btn {
            position: fixed;
            top: 20px;
            left: 262px;
            width: 35px;
            height: 35px;
            border-radius: 50%;
            border: none;
            cursor: pointer;
            background: var(--royal-blue);
            color: white;
            z-index: 1001;
            transition: 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
        }

        .sidebar.collapsed + .toggle-btn { left: 62px; }

        .hint { margin-top: 20px; font-size: 13px; color: var(--text-muted); }

        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .sidebar.active { transform: translateX(0); }
            .main { margin-left: 0; }
            .hero-section h1 { font-size: 2.5rem; }
            .search-card { padding: 30px; }
        }
    </style>
</head>
<body class="<?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'dark-mode' : '' ?>">

<aside class="sidebar" id="sidebar">
    <h2>PANABO COOP <br>2026</h2>

    <div class="user-profile">
        <span class="name"><?= htmlspecialchars($_SESSION['username']) ?></span>
        <span class="branch"><?= htmlspecialchars($current_branch) ?> Branch</span>
    </div>

    <nav>
        <a href="index.php" class="active">🏠 <span>Search Member</span></a>
        <a href="tracker.php">📈 <span>Live Attendance</span></a>
        <hr style="border: 0.5px solid rgba(255,255,255,0.1); margin: 15px 0;">
        <a href="printed.php">🟢 <span>Printed Members</span></a>
        <a href="allowance_report.php">💵 <span>Allowance Report</span></a>
        
        <button class="theme-toggle" onclick="toggleTheme()">
            <span id="theme-icon"><?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? '☀️' : '🌙' ?></span>
            <span id="theme-text"><?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'Light Mode' : 'Dark Mode' ?></span>
        </button>

        <a href="logout.php" class="logout">🚪 <span>Logout System</span></a>
    </nav>
</aside>

<main class="main" id="main">
    <div class="hero-section">
        <h1>PANABO COOP</h1>
        <h2>General Assembly 2026 Registry</h2>
    </div>

    <div class="search-card">
        <form action="search.php" method="GET" class="search-group">
            <input
                type="text"
                name="q"
                placeholder="Search Name (Last, First)..."
                required
                autofocus
                autocomplete="off"
            >
            <button type="submit" class="search-btn">Find Member Record</button>
        </form>
        <p class="hint">Type the member's name to begin verification</p>
    </div>
</main>

<script>
// Sidebar Toggle
function toggleSidebar() {
    document.getElementById("sidebar").classList.toggle("collapsed");
    document.getElementById("main").classList.toggle("collapsed");
}

// Persistent Dark Mode Logic
function toggleTheme() {
    const body = document.body;
    const icon = document.getElementById('theme-icon');
    const text = document.getElementById('theme-text');
    
    body.classList.toggle('dark-mode');
    const isDark = body.classList.contains('dark-mode');
    
    // Update Text/Icon instantly
    icon.innerText = isDark ? '☀️' : '🌙';
    text.innerText = isDark ? 'Light Mode' : 'Dark Mode';
    
    // Save preference for 30 days (path=/ ensures it works on all pages)
    document.cookie = "theme=" + (isDark ? "dark" : "light") + ";max-age=" + (30*24*60*60) + ";path=/";
}
</script>

</body>
</html>