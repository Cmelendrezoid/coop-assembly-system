<?php

session_start();

// Include database configuration
if (file_exists('../config/db.php')) {
    require_once '../config/db.php';
} elseif (file_exists('../config/conn.php')) {
    require_once '../config/conn.php';
}

if (!isset($_SESSION['member_id']) && !isset($_SESSION['voter_id'])) {
    header("Location: login.php");
    exit();
}

// Detect database connection object ($conn or $pdo)
$db = isset($conn) ? $conn : (isset($pdo) ? $pdo : null);
$member_id = $_SESSION['member_id'] ?? $_SESSION['voter_id'];

$full_name = $_SESSION['full_name'] ?? 'Member';
$isAwardee = false;
$awardee_text = "";
$has_voted = 0;

if ($db) {
    if ($db instanceof PDO) {
        $stmt = $db->prepare("SELECT full_name, awardee, has_voted FROM members WHERE id = :id LIMIT 1");
        $stmt->execute(['id' => $member_id]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare("SELECT full_name, awardee, has_voted FROM members WHERE id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("s", $member_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $member = $result ? $result->fetch_assoc() : null;
        }
    }

    if (!empty($member)) {
        if (!empty($member['full_name'])) {
            $full_name = $member['full_name'];
        }

        // Check voting status from DB directly
        $has_voted = intval($member['has_voted'] ?? 0);

        // Check awardee status
        $awardee_val = trim($member['awardee'] ?? '');
        if (!empty($awardee_val) && strtoupper($awardee_val) !== 'N/A' && $awardee_val !== '0') {
            $isAwardee = true;
            $awardee_text = $awardee_val;
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Voter Dashboard - PMPC E-Voting</title>

    <!-- Bootstrap 5.3 & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Anti-flicker script to apply saved theme instantly -->
    <script>
        (function() {
            if (localStorage.getItem('pmpc-theme') === 'dark') {
                document.documentElement.classList.add('dark-theme');
            }
        })();
    </script>

    <style>
        :root {
            --bg-gradient: radial-gradient(circle at 50% 0%, #1e40af 0%, #0f172a 100%);
            --card-bg: rgba(255, 255, 255, 0.92);
            --card-border: rgba(226, 232, 240, 0.8);
            --text-color: #0f172a;
            --text-muted: #64748b;
            --box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.08);
            --profile-bg: rgba(37, 99, 235, 0.06);
            --profile-border: rgba(37, 99, 235, 0.15);
        }

        .dark-theme {
            --bg-gradient: radial-gradient(circle at 50% 0%, #0f172a 0%, #020617 100%);
            --card-bg: rgba(15, 23, 42, 0.85);
            --card-border: rgba(51, 65, 85, 0.6);
            --text-color: #f8fafc;
            --text-muted: #94a3b8;
            --box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.5);
            --profile-bg: rgba(59, 130, 246, 0.12);
            --profile-border: rgba(59, 130, 246, 0.25);
        }

        body {
            background: var(--bg-gradient);
            background-attachment: fixed;
            min-height: 100vh;
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            color: var(--text-color);
            transition: background-color 0.3s ease, color 0.3s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
            margin: 0;
        }

        .dashboard-container {
            width: 100%;
            max-width: 680px;
            padding: 0 15px;
        }

        .dashboard-card {
            border: 1px solid var(--card-border);
            border-radius: 24px;
            background: var(--card-bg);
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            transition: all 0.3s ease;
        }

        .logo-container {
            position: relative;
            display: inline-block;
            margin-bottom: 20px;
        }

        .logo {
            width: 90px;
            height: 90px;
            object-fit: contain;
            filter: drop-shadow(0 4px 6px rgba(0, 0, 0, 0.1));
            transition: transform 0.3s ease;
        }

        .logo:hover {
            transform: scale(1.04);
        }

        .welcome-title {
            color: var(--text-color);
            font-weight: 800;
            font-size: 2.1rem;
            letter-spacing: -0.5px;
            margin-bottom: 4px;
        }

        .subtitle {
            color: var(--text-muted);
            font-size: 1rem;
            font-weight: 500;
            margin-bottom: 24px;
        }

        .profile-box {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 22px;
            border-radius: 50px;
            background: var(--profile-bg);
            border: 1px solid var(--profile-border);
            color: var(--text-color);
            font-weight: 600;
            font-size: 0.98rem;
            margin-bottom: 28px;
        }

        .status-card {
            display: flex;
            align-items: center;
            gap: 18px;
            text-align: left;
            padding: 20px 24px;
            border-radius: 16px;
            margin-bottom: 28px;
            transition: transform 0.2s ease;
        }

        .status-card:hover {
            transform: translateY(-2px);
        }

        .status-icon {
            font-size: 2rem;
            line-height: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 52px;
            height: 52px;
            border-radius: 12px;
            flex-shrink: 0;
        }

        .status-title {
            font-size: 1.05rem;
            font-weight: 700;
            margin-bottom: 2px;
        }

        .status-text {
            margin: 0;
            font-size: 0.92rem;
            opacity: 0.9;
        }

        /* Awardee Card */
        .awardee {
            background: rgba(16, 185, 129, 0.08);
            border: 1px solid rgba(16, 185, 129, 0.3);
            color: #065f46;
        }

        .awardee .status-icon {
            background: rgba(16, 185, 129, 0.15);
            color: #059669;
        }

        .dark-theme .awardee {
            background: rgba(6, 78, 59, 0.35);
            border: 1px solid rgba(52, 211, 153, 0.3);
            color: #a7f3d0;
        }

        .dark-theme .awardee .status-icon {
            background: rgba(52, 211, 153, 0.15);
            color: #34d399;
        }

        /* Already Voted Card */
        .already-voted-card {
            background: rgba(239, 68, 68, 0.08);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #991b1b;
        }

        .already-voted-card .status-icon {
            background: rgba(239, 68, 68, 0.15);
            color: #dc2626;
        }

        .dark-theme .already-voted-card {
            background: rgba(127, 29, 29, 0.35);
            border: 1px solid rgba(248, 113, 113, 0.3);
            color: #fecaca;
        }

        .dark-theme .already-voted-card .status-icon {
            background: rgba(248, 113, 113, 0.15);
            color: #f87171;
        }

        /* Action Buttons */
        .btn-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 28px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 0.98rem;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-vote {
            background: #10b981;
            color: #ffffff;
            border: none;
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.25);
        }

        .btn-vote:hover {
            background: #059669;
            color: #ffffff;
            transform: translateY(-2px);
            box-shadow: 0 6px 16px rgba(16, 185, 129, 0.35);
        }

        .btn-logout {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.2);
        }

        .btn-logout:hover {
            background: #ef4444;
            color: #ffffff;
            border-color: #ef4444;
            transform: translateY(-2px);
        }

        .dark-theme .btn-logout {
            background: rgba(239, 68, 68, 0.15);
            color: #f87171;
            border-color: rgba(239, 68, 68, 0.3);
        }

        .dark-theme .btn-logout:hover {
            background: #ef4444;
            color: #ffffff;
        }

        /* Fixed Theme Toggle */
        .theme-toggle-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            border: 1px solid var(--card-border);
            border-radius: 50px;
            padding: 10px 18px;
            background: var(--card-bg);
            color: var(--text-color);
            backdrop-filter: blur(10px);
            font-weight: 600;
            font-size: 0.88rem;
            z-index: 999;
            display: flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .theme-toggle-btn:hover {
            transform: translateY(-2px);
        }

        .footer-text {
            color: var(--text-muted);
            font-size: 0.85rem;
            margin-top: 32px;
            font-weight: 500;
        }
    </style>
</head>
<body>

<button class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()" aria-label="Toggle theme">
    <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
    <span id="themeText">Dark Mode</span>
</button>

<div class="dashboard-container">
    <div class="card dashboard-card">
        <div class="card-body p-4 p-md-5 text-center">

            <div class="logo-container">
                <img
                    src="../assets/images/logo.png"
                    alt="PMPC Logo"
                    class="logo"
                    onerror="this.style.display='none';"
                >
            </div>

            <h1 class="welcome-title">Welcome Back</h1>
            <p class="subtitle">PMPC E-Voting Portal</p>

            <div class="profile-box">
                <i class="bi bi-person-circle"></i>
                <span><?php echo htmlspecialchars($full_name, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>

            <?php if ($isAwardee): ?>
                <div class="status-card awardee">
                    <div class="status-icon">
                        <i class="bi bi-trophy-fill"></i>
                    </div>
                    <div>
                        <div class="status-title">Awardee Member</div>
                        <p class="status-text"><?php echo htmlspecialchars($awardee_text, ENT_QUOTES, 'UTF-8'); ?></p>
                    </div>
                </div>
            <?php endif; ?>

            <?php if ($has_voted === 1): ?>
                <div class="status-card already-voted-card">
                    <div class="status-icon">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div>
                        <div class="status-title">Voting Completed</div>
                        <p class="status-text">You have successfully cast your vote for this election.</p>
                    </div>
                </div>

                <div class="d-flex justify-content-center">
                    <a href="logout.php" class="btn-action btn-logout">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            <?php else: ?>
                <div class="d-flex flex-column flex-sm-row justify-content-center gap-3">
                    <a href="vote.php" class="btn-action btn-vote">
                        <i class="bi bi-box-seam-fill"></i> Start Voting
                    </a>
                    <a href="logout.php" class="btn-action btn-logout">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            <?php endif; ?>

            <div class="footer-text">
                Panabo Multi-Purpose Cooperative E-Voting System
            </div>

        </div>
    </div>
</div>

<script>
    function updateThemeUI() {
        const isDark = document.documentElement.classList.contains('dark-theme');
        const themeText = document.getElementById('themeText');
        const themeIcon = document.getElementById('themeIcon');

        if (isDark) {
            themeText.textContent = 'Light Mode';
            themeIcon.className = 'bi bi-sun-fill';
        } else {
            themeText.textContent = 'Dark Mode';
            themeIcon.className = 'bi bi-moon-stars-fill';
        }
    }

    function toggleTheme() {
        document.documentElement.classList.toggle('dark-theme');
        const isDark = document.documentElement.classList.contains('dark-theme');
        localStorage.setItem('pmpc-theme', isDark ? 'dark' : 'light');
        updateThemeUI();
    }

    // Sync button state once page mounts
    document.addEventListener('DOMContentLoaded', updateThemeUI);
</script>

</body>
</html>