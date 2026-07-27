<?php
require "db.php";
require "auth.php";

$q = trim($_GET['q'] ?? '');

// Branch Mapping for UI Display
$branch_names = [
    19 => 'Panabo', 20 => 'Tibungco', 21 => 'Bajada', 22 => 'Matina', 
    23 => 'Tagum', 24 => 'Sto. Tomas', 25 => 'Toril', 26 => 'Surigao', 
    27 => 'CDO', 28 => 'Valencia', 29 => 'Gensan', 30 => 'Koronadal', 
    31 => 'Butuan', 32 => 'Digos', 33 => 'Kidapawan', 34 => 'Calinan', 
    35 => 'SCWE', 36 => 'Davao City Venue'
];

$current_branch = $branch_names[$_SESSION['branch_id']] ?? 'Unknown';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PMPC | Search Results</title>
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
            --success-green: #2ecc71;
        }

        /* DARK MODE OVERRIDES */
        body.dark-mode {
            --bg-light: #020617;
            --card-bg: #0f172a;
            --text-main: #f1f5f9;
            --text-muted: #94a3b8;
            --sidebar-bg: #000000;
            --border-color: #1e293b;
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
            transition: 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 4px 0 20px rgba(0,0,0,0.1);
            position: fixed;
            height: 100vh;
            z-index: 1000;
        }

        .sidebar h2 { font-size: 1.5rem; margin-bottom: 40px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; }

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
        .sidebar a:hover, .theme-btn:hover { background: rgba(255,255,255,0.15); transform: translateX(5px); }
        
        .logout { margin-top: auto; color: #ff6b6b !important; border: 1px solid rgba(255,107,107,0.2) !important; }

        /* ===== MAIN CONTENT ===== */
        .main {
            flex: 1;
            margin-left: 280px;
            padding: 40px;
            transition: margin-left 0.3s ease;
        }

        .header-section { margin-bottom: 30px; }
        .header-section h1 { font-size: 28px; color: var(--dark-blue); font-weight: 800; }
        .header-section p { color: var(--text-muted); font-size: 16px; margin-top: 5px; }

        .back-nav { margin-bottom: 20px; display: inline-block; }
        .back-nav a {
            color: var(--royal-blue);
            text-decoration: none;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        /* ===== RESULTS LIST ===== */
        .results-container { width: 100%; max-width: 800px; margin: 0 auto; }

        .result-card {
            background: var(--card-bg);
            border-radius: 16px;
            margin-bottom: 12px;
            border: 1px solid var(--border-color);
            transition: 0.2s;
            overflow: hidden;
        }

        .result-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.05);
            border-color: var(--royal-blue);
        }

        .result-card a {
            padding: 20px 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            text-decoration: none;
            color: inherit;
        }

        .member-info .m-name {
            display: block;
            font-size: 18px;
            font-weight: 700;
            color: var(--text-main);
            text-transform: uppercase;
        }

        .member-info .m-branch {
            display: block;
            font-size: 13px;
            color: var(--text-muted);
            margin-top: 4px;
            font-weight: 500;
        }

        .status-badge {
            background: rgba(46, 204, 113, 0.1);
            color: var(--success-green);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            border: 1px solid rgba(46, 204, 113, 0.2);
        }

        .no-results {
            text-align: center;
            padding: 50px;
            background: var(--card-bg);
            border-radius: 20px;
            color: var(--text-muted);
            border: 1px dashed var(--border-color);
        }
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
        <a href="index.php">🏠 <span> &nbsp; Search Member</span></a>
        <a href="tracker.php">📈 <span> &nbsp; Live Attendance</span></a>
        <hr style="border: 0.5px solid rgba(255,255,255,0.1); margin: 15px 0;">
        <a href="printed.php">🟢 <span> &nbsp; Printed Members</span></a>
        <a href="allowance_report.php">💵 <span> &nbsp; Allowance Report</span></a>
        
        <button class="theme-btn" onclick="toggleTheme()">
            <span id="theme-icon"><?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? '☀️' : '🌙' ?></span>
            <span id="theme-text"><?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'Light Mode' : 'Dark Mode' ?></span>
        </button>

        <a href="logout.php" class="logout">🚪 <span> &nbsp; Logout System</span></a>
    </nav>
</aside>

<main class="main">
    <div class="header-section">
        <div class="back-nav">
            <a href="index.php">← Back to Search</a>
        </div>
        <h1>Results for "<?= htmlspecialchars($q) ?>"</h1>
        <p>Select a member to view details and process attendance</p>
    </div>

    <div class="results-container">
        <?php
        if ($q !== '') {
            $sql = "SELECT m.id, m.full_name, m.printed, m.branch_name AS original_branch, b.branch_name AS venue_name 
                    FROM members m 
                    LEFT JOIN branches b ON m.branch_id = b.branch_id 
                    WHERE m.full_name LIKE ? 
                    ORDER BY m.full_name 
                    LIMIT 50";

            $stmt = $conn->prepare($sql);
            $like = "%$q%";
            $stmt->bind_param("s", $like);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result->num_rows === 0) {
                echo "<div class='no-results'>
                        <p>No members found matching your search.</p>
                      </div>";
            }

            while ($row = $result->fetch_assoc()) {
                $member_branch = !empty($row['original_branch']) ? $row['original_branch'] : ($row['venue_name'] ?? 'Unassigned');
                ?>
                <div class="result-card">
                    <a href="view.php?id=<?= $row['id'] ?>">
                        <div class="member-info">
                            <span class="m-name"><?= htmlspecialchars($row['full_name']) ?></span>
                            <span class="m-branch">Branch: <?= htmlspecialchars($member_branch) ?></span>
                        </div>
                        <?php if ($row['printed']): ?>
                            <span class="status-badge">ALREADY PRINTED</span>
                        <?php else: ?>
                            <span style="color: var(--royal-blue); font-size: 20px;">→</span>
                        <?php endif; ?>
                    </a>
                </div>
                <?php
            }
        }
        ?>
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