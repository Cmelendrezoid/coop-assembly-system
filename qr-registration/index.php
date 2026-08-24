<?php
// =========================================================================
// 1. CONFIGURATION, SESSIONS & DATABASE CONNECTION
// =========================================================================
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'migs_db';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

$message = '';
$error = '';
$print_target_id = $_GET['print_id'] ?? null;
$gate_print = $_GET['gate_print'] ?? null;
$is_duplicate = isset($_GET['duplicate']) && $_GET['duplicate'] === '1';

// --- AUTHENTICATION ACTION HANDLERS ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['qr_user_id']);
    unset($_SESSION['qr_username']);
    header("Location: index.php");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
            $stmt->execute([$username]);
            $user = $stmt->fetch();

            if ($user && ($password === $user['password'] || password_verify($password, $user['password']) || md5($password) === $user['password'])) {
                $_SESSION['qr_user_id'] = $user['user_id'];
                $_SESSION['qr_username'] = $user['username'];
                header("Location: index.php");
                exit;
            } else {
                $error = "Invalid username or password. Please double-check your credentials and try again.";
            }
        } catch (PDOException $e) {
            $error = "Authentication system error: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in both username and password fields to sign in.";
    }
}

// --- ENFORCE LOGIN BARRIER ---
if (!isset($_SESSION['qr_user_id'])) {
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Sign In - Panabo Cooperative Assembly Portal</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            :root {
                --bg-gradient: radial-gradient(circle at 50% 0%, #f1f5f9 0%, #e2e8f0 100%);
                --card-bg: #ffffff;
                --text-main: #0f172a;
                --text-muted: #64748b;
                --border-color: #e2e8f0;
                --input-bg: #f8fafc;
                --input-border: #cbd5e1;
                --primary-gradient: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                --primary-hover: linear-gradient(135deg, #1d4ed8 0%, #1e40af 100%);
                --primary-shadow: rgba(37, 99, 235, 0.25);
                --badge-bg: #eff6ff;
                --badge-text: #1e40af;
            }

            [data-theme="dark"] {
                --bg-gradient: radial-gradient(circle at 50% 0%, #0f172a 0%, #0b0f19 100%);
                --card-bg: #1e293b;
                --text-main: #f8fafc;
                --text-muted: #94a3b8;
                --border-color: #334155;
                --input-bg: #0f172a;
                --input-border: #475569;
                --primary-gradient: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                --primary-hover: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                --primary-shadow: rgba(59, 130, 246, 0.35);
                --badge-bg: rgba(59, 130, 246, 0.15);
                --badge-text: #93c5fd;
            }

            * { box-sizing: border-box; transition: background 0.2s, border-color 0.2s, color 0.2s, box-shadow 0.2s, transform 0.2s; }
            
            body {
                font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
                background: var(--bg-gradient);
                color: var(--text-main);
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                padding: 24px;
                -webkit-font-smoothing: antialiased;
            }

            .top-nav-actions {
                position: fixed;
                top: 24px;
                left: 24px;
                right: 24px;
                display: flex;
                justify-content: space-between;
                align-items: center;
                z-index: 10;
            }

            .btn-back, .theme-toggle-login {
                background: var(--card-bg);
                border: 1px solid var(--border-color);
                color: var(--text-main);
                padding: 10px 18px;
                border-radius: 30px;
                font-weight: 700;
                font-size: 13px;
                text-decoration: none;
                backdrop-filter: blur(12px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.05);
                display: flex;
                align-items: center;
                gap: 8px;
                cursor: pointer;
            }

            .btn-back:hover, .theme-toggle-login:hover {
                transform: translateY(-2px);
                border-color: #3b82f6;
            }

            .login-wrapper {
                width: 100%;
                max-width: 880px;
                background: var(--card-bg);
                border-radius: 28px;
                box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15);
                border: 1px solid var(--border-color);
                display: flex;
                overflow: hidden;
            }

            .login-hero {
                flex: 1;
                background: linear-gradient(135deg, #1e3a8a 0%, #1e40af 50%, #2563eb 100%);
                color: #ffffff;
                padding: 48px;
                display: flex;
                flex-direction: column;
                justify-content: space-between;
                position: relative;
            }

            .login-hero::before {
                content: '';
                position: absolute;
                inset: 0;
                background: radial-gradient(circle at top right, rgba(255, 255, 255, 0.15), transparent);
                pointer-events: none;
            }

            .hero-badge {
                align-self: flex-start;
                background: rgba(255, 255, 255, 0.15);
                backdrop-filter: blur(8px);
                padding: 6px 14px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 700;
                letter-spacing: 0.03em;
                border: 1px solid rgba(255, 255, 255, 0.2);
            }

            .hero-content h1 {
                font-size: 28px;
                font-weight: 800;
                line-height: 1.25;
                margin: 20px 0 12px 0;
                letter-spacing: -0.02em;
            }

            .hero-content p {
                font-size: 14px;
                color: rgba(255, 255, 255, 0.85);
                line-height: 1.6;
                margin: 0;
            }

            .hero-footer {
                font-size: 12px;
                color: rgba(255, 255, 255, 0.65);
                font-weight: 600;
            }

            .login-card {
                flex: 1;
                padding: 48px 40px;
                display: flex;
                flex-direction: column;
                justify-content: center;
            }

            .brand-header-login {
                margin-bottom: 28px;
            }

            .login-logo {
                height: 52px;
                width: auto;
                margin-bottom: 16px;
                object-fit: contain;
            }

            .login-card h2 {
                margin: 0 0 6px 0;
                font-size: 24px;
                font-weight: 800;
                letter-spacing: -0.02em;
                color: var(--text-main);
            }

            .login-card p {
                color: var(--text-muted);
                font-size: 13px;
                margin: 0;
                font-weight: 500;
            }

            .form-group { margin-bottom: 20px; }

            label {
                display: block;
                font-size: 12px;
                font-weight: 700;
                margin-bottom: 8px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--text-muted);
            }

            .input-wrap {
                position: relative;
                display: flex;
                align-items: center;
            }

            .input-icon {
                position: absolute;
                left: 16px;
                width: 18px;
                height: 18px;
                stroke: var(--text-muted);
                fill: none;
                stroke-width: 2;
                stroke-linecap: round;
                stroke-linejoin: round;
                pointer-events: none;
            }

            input[type="text"], input[type="password"] {
                width: 100%;
                padding: 14px 16px 14px 46px;
                border: 1px solid var(--input-border);
                border-radius: 12px;
                font-size: 14px;
                background: var(--input-bg);
                color: var(--text-main);
                font-family: inherit;
                font-weight: 500;
            }

            input[type="text"]:focus, input[type="password"]:focus {
                outline: none;
                border-color: #3b82f6;
                box-shadow: 0 0 0 4px var(--primary-shadow);
                background: var(--card-bg);
            }

            .btn-login {
                width: 100%;
                background: var(--primary-gradient);
                color: white;
                border: none;
                padding: 14px;
                font-size: 14px;
                border-radius: 12px;
                font-weight: 700;
                cursor: pointer;
                font-family: inherit;
                box-shadow: 0 8px 16px -4px var(--primary-shadow);
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                margin-top: 8px;
            }

            .btn-login:hover {
                background: var(--primary-hover);
                transform: translateY(-2px);
                box-shadow: 0 12px 20px -4px var(--primary-shadow);
            }

            .alert {
                background: #fef2f2;
                color: #991b1b;
                border: 1px solid #fee2e2;
                padding: 12px 16px;
                border-radius: 12px;
                font-size: 13px;
                margin-bottom: 20px;
                display: flex;
                align-items: center;
                gap: 10px;
                font-weight: 600;
            }

            @media (max-width: 768px) {
                .login-wrapper { flex-direction: column; }
                .login-hero { padding: 32px; text-align: center; }
                .hero-badge { align-self: center; }
                .login-card { padding: 32px 24px; }
            }
        </style>
    </head>
    <body>
        <div class="top-nav-actions">
            <a href="../index.php" class="btn-back">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5M12 19l-7-7 7-7"/></svg>
                Back to Portal
            </a>
            <button class="theme-toggle-login" id="theme-toggle">🌙 Dark Mode</button>
        </div>
        
        <div class="login-wrapper">
            <!-- Left Branding Pane -->
            <div class="login-hero">
                <div class="hero-badge">Panabo Cooperative</div>
                <div class="hero-content">
                    <h1>General Assembly Registration Portal</h1>
                    <p>Welcome! Please authenticate your terminal operator session to manage member check-ins and freebie distribution.</p>
                </div>
                <div class="hero-footer">
                    &copy; <?= date('Y') ?> Panabo Multi-Purpose Cooperative
                </div>
            </div>

            <!-- Right Form Pane -->
            <div class="login-card">
                <div class="brand-header-login">
                    <img src="/evoting/assets/images/logo.png" class="login-logo" alt="Company Logo" onerror="this.style.display='none'">
                    <h2>Welcome Back</h2>
                    <p>Sign in with your authorized terminal credentials</p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert">
                        <span>⚠️</span>
                        <div><?= htmlspecialchars($error) ?></div>
                    </div>
                <?php endif; ?>

                <form method="POST" action="index.php">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group">
                        <label>Username</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                            <input type="text" name="username" required autofocus placeholder="Enter your username">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Password</label>
                        <div class="input-wrap">
                            <svg class="input-icon" viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
                            <input type="password" name="password" required placeholder="••••••••">
                        </div>
                    </div>

                    <button type="submit" class="btn-login">
                        Sign In to Terminal
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </button>
                </form>
            </div>
        </div>

        <script>
            const toggleBtn = document.getElementById('theme-toggle');
            const currentTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
            
            if (currentTheme === 'dark') {
                document.documentElement.setAttribute('data-theme', 'dark');
                toggleBtn.textContent = '☀️ Light Mode';
            }

            toggleBtn.addEventListener('click', () => {
                let theme = document.documentElement.getAttribute('data-theme');
                if (theme === 'dark') {
                    document.documentElement.removeAttribute('data-theme');
                    localStorage.setItem('theme', 'light');
                    toggleBtn.textContent = '🌙 Dark Mode';
                } else {
                    document.documentElement.setAttribute('data-theme', 'dark');
                    localStorage.setItem('theme', 'dark');
                    toggleBtn.textContent = '☀️ Light Mode';
                }
            });
        </script>
    </body>
    </html>
    <?php
    exit;
}

$current_user_id = $_SESSION['qr_user_id'] ?? 0;
$current_username = $_SESSION['qr_username'] ?? 'System';

// Define route early
$route = $_GET['route'] ?? 'registration';

if (!file_exists('qrlib.php')) {
    die("Error: 'qrlib.php' was not found in this folder.");
}
require_once 'qrlib.php';

// =========================================================================
// 2. BACKEND ROUTING & EXPORT ACTIONS
// =========================================================================

// EXPORT 1: Freebies Claimants Report
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'export_freebies') {
    try {
        $claims = $pdo->query("
            SELECT 
                m.id AS member_id, 
                m.full_name, 
                COALESCE(m.migs_category, 'REGULAR') AS category,
                COALESCE(m.branch_name, 'N/A') AS branch_name,
                COALESCE(c.item_name, 'No Freebies Claimed') AS claimed_item,
                COALESCE(c.claimed_at, m.allowance_claimed_at) AS claim_time,
                u.username AS processed_by
            FROM members m
            INNER JOIN member_freebie_claims c ON m.id = c.member_id
            LEFT JOIN users u ON c.processed_by = u.user_id
            WHERE m.allowance_claimed = 1
            ORDER BY c.claimed_at DESC, m.id ASC
        ")->fetchAll();

        $filename = 'Freebies_Claimants_Report_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
        
        fputcsv($output, [
            'Member ID', 
            'Member Name', 
            'Category', 
            'Branch',
            'Claimed Freebie Item', 
            'Claim Date & Time', 
            'Processed By'
        ]);
        
        foreach ($claims as $row) {
            fputcsv($output, [
                $row['member_id'],
                $row['full_name'],
                $row['category'],
                $row['branch_name'],
                $row['claimed_item'],
                $row['claim_time'],
                $row['processed_by'] ?? 'System'
            ]);
        }
        
        fclose($output);
        exit;
    } catch (PDOException $e) {
        $error = "Export failed: " . $e->getMessage();
    }
}

// EXPORT 2: Headcount Attendance Summary Report
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'export_attendance_summary') {
    try {
        $total = $pdo->query("SELECT COUNT(*) FROM members WHERE allowance_claimed = 1")->fetchColumn();
        $today = $pdo->query("SELECT COUNT(*) FROM members WHERE allowance_claimed = 1 AND DATE(allowance_claimed_at) = DATE(NOW())")->fetchColumn();
        $categories = $pdo->query("
            SELECT COALESCE(migs_category, 'REGULAR') AS category, COUNT(*) AS total 
            FROM members 
            WHERE allowance_claimed = 1 
            GROUP BY migs_category
        ")->fetchAll();

        $filename = 'Attendance_Headcount_Summary_' . date('Y-m-d_H-i-s') . '.csv';
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        fputcsv($output, ['ATTENDANCE HEADCOUNT SUMMARY REPORT']);
        fputcsv($output, ['Generated On:', date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        fputcsv($output, ['Metric Description', 'Total Count']);
        fputcsv($output, ['Total Attendees Arrived (All Time)', $total]);
        fputcsv($output, ['Total Attendees Arrived (Today)', $today]);
        fputcsv($output, []);
        fputcsv($output, ['CATEGORY BREAKDOWN']);
        fputcsv($output, ['Member Category', 'Arrived Count']);
        
        foreach ($categories as $cat) {
            fputcsv($output, [$cat['category'], $cat['total']]);
        }
        
        fclose($output);
        exit;
    } catch (PDOException $e) {
        $error = "Summary export failed: " . $e->getMessage();
    }
}

// Terminal A Action: Pre-Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_member') {
    $member_id = $_POST['member_id'] ?? '';
    $claimed_items = $_POST['claimed_items'] ?? [];
    if (!empty($member_id)) {
        try {
            $check = $pdo->prepare("SELECT registered FROM members WHERE id = ?");
            $check->execute([$member_id]);
            $is_already_reg = $check->fetchColumn();

            $stmt = $pdo->prepare("UPDATE members SET registered = 1, registered_at = NOW(), registered_by = ? WHERE id = ?");
            $stmt->execute([$current_user_id, $member_id]);

            // Clear any previous pre-registration freebies for this member
            $del_old = $pdo->prepare("DELETE FROM member_freebie_claims WHERE member_id = ?");
            $del_old->execute([$member_id]);

            if (!empty($claimed_items) && is_array($claimed_items)) {
                $claim_insert = $pdo->prepare("INSERT INTO member_freebie_claims (member_id, item_name, claimed_at, processed_by) VALUES (?, ?, NOW(), ?)");
                foreach ($claimed_items as $item_name) {
                    $item_name = trim($item_name);
                    if ($item_name !== '') {
                        $claim_insert->execute([$member_id, $item_name, $current_user_id]);
                    }
                }
            }
            
            header("Location: index.php?route=registration&print_id=" . urlencode($member_id) . "&duplicate=" . ($is_already_reg ? '1' : '0') . "&search=" . urlencode($_GET['search'] ?? ''));
            exit;
        } catch (PDOException $e) {
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}

// TERMINAL B: Scan Pre-Registration QR Code & Claim Freebies
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'gate_scan') {
    $scan_input = trim($_POST['scan_input'] ?? '');
    $scanned_id = '';

    if (empty($scan_input)) {
        $error = "Please scan a valid Member QR code or enter Member ID.";
    } else {
        if (preg_match('/gate_id=([^&]+)/i', $scan_input, $matches)) {
            $scanned_id = trim($matches[1]);
        } else {
            $scanned_id = $scan_input;
        }

        if (!empty($scanned_id)) {
            try {
                $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
                $stmt->execute([$scanned_id]);
                $member = $stmt->fetch();

                if ($member) {
                    if ($member['registered'] == 0) {
                        $error = "Access Denied: Member ID #{$scanned_id} (" . htmlspecialchars($member['full_name']) . ") has NOT pre-registered at Terminal A yet!";
                    } 
                    else {
                        $is_already_claimed = ($member['allowance_claimed'] == 1);

                        $pdo->beginTransaction();

                        // Mark attendance on first check-in
                        if (!$is_already_claimed) {
                            $update = $pdo->prepare("UPDATE members SET allowance_claimed = 1, allowance_claimed_at = NOW(), allowance_processed_by = ? WHERE id = ?");
                            $update->execute([$current_user_id, $scanned_id]);
                        }

                        $pdo->commit();

                        header("Location: index.php?route=gate&print_id=" . urlencode($scanned_id) . "&gate_print=1" . ($is_already_claimed ? "&duplicate=1" : ""));
                        exit;
                    }
                } else {
                    $error = "Invalid QR Code: No member found matching ID #{$scanned_id}.";
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                $error = "Database execution error: " . $e->getMessage();
            }
        } else {
            $error = "Please scan a valid Member QR code or enter Member ID.";
        }
    }
}

// TERMINAL B: Interactive Claims & Printing Form Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'gate_print_claims') {
    $member_id = trim($_POST['member_id'] ?? '');
    $selected_items = $_POST['selected_items'] ?? [];
    $print_now = trim($_POST['print_now'] ?? '0');
    $admin_override = trim($_POST['admin_override'] ?? '0');

    if (!empty($member_id)) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
            $stmt->execute([$member_id]);
            $member = $stmt->fetch();

            if ($member) {

                // ADMIN OVERRIDE / SECOND CHANCE
                if ($admin_override === '1') {
                    $pdo->beginTransaction();

                    // Clear freebie claims database records
                    $del_stmt = $pdo->prepare("DELETE FROM member_freebie_claims WHERE member_id = ?");
                    $del_stmt->execute([$member_id]);

                    // Reset allowance_claimed flag and timestamp
                    $reset_stmt = $pdo->prepare("UPDATE members SET allowance_claimed = 0, allowance_claimed_at = NULL, allowance_processed_by = NULL WHERE id = ?");
                    $reset_stmt->execute([$member_id]);

                    $pdo->commit();

                    $redirect = "index.php?route=gate&print_id=" . urlencode($member_id) . "&gate_print=1&override=1";
                    header("Location: " . $redirect);
                    exit;
                }

                // NORMAL SAVE / CLAIM PROCESS
                $pdo->beginTransaction();

                // Ensure attendance is marked upon saving claims
                $update_att = $pdo->prepare("UPDATE members SET allowance_claimed = 1, allowance_claimed_at = COALESCE(allowance_claimed_at, NOW()), allowance_processed_by = ? WHERE id = ?");
                $update_att->execute([$current_user_id, $member_id]);

                // Synchronize the current Terminal B selection.
                $del_stmt = $pdo->prepare("DELETE FROM member_freebie_claims WHERE member_id = ?");
                $del_stmt->execute([$member_id]);

                if (!empty($selected_items) && is_array($selected_items)) {
                    $claim_stmt = $pdo->prepare("INSERT INTO member_freebie_claims (member_id, item_name, claimed_at, processed_by) VALUES (?, ?, NOW(), ?)");

                    foreach ($selected_items as $item_name) {
                        $trimmed_item = trim($item_name);
                        if ($trimmed_item === '') {
                            continue;
                        }
                        $claim_stmt->execute([$member_id, $trimmed_item, $current_user_id]);
                    }
                }

                $pdo->commit();

                $redirect = "index.php?route=gate&print_id=" . urlencode($member_id) . "&gate_print=1";
                if ($print_now === '1') {
                    $redirect .= "&print_now=1";
                }
                header("Location: " . $redirect);
                exit;
            } else {
                $error = "Member not found for print claim update.";
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = "Print claim update error: " . $e->getMessage();
        }
    }
}

// Search Handler (Terminal A)
$search_results = [];
$search_query = trim($_GET['search'] ?? '');
if ($route === 'registration' && !empty($search_query) && !filter_var($search_query, FILTER_VALIDATE_URL)) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.*, u.username AS clerk_name 
            FROM members m 
            LEFT JOIN users u ON m.registered_by = u.user_id 
            WHERE m.id = ? OR m.full_name LIKE ? 
            LIMIT 10
        ");
        $stmt->execute([$search_query, "%$search_query%"]);
        $search_results = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Database Search Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cooperative General Assembly Portal</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            --card-bg: #ffffff;
            --nav-bg: #f1f5f9;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-color: #e2e8f0;
            --input-bg: #f8fafc;
            
            --primary-gradient: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
            --primary-shadow: rgba(37, 99, 235, 0.2);
            --table-header: #f8fafc;
            
            --success-bg: #f0fdf4;
            --success-border: #bbf7d0;
            --success-text: #15803d;
            
            --danger-bg: #fef2f2;
            --danger-border: #fecaca;
            --danger-text: #b91c1c;

            --hud-bg: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            --hud-border: #334155;
            --hud-text: #f8fafc;
        }

        [data-theme="dark"] {
            --bg-gradient: linear-gradient(135deg, #090d16 0%, #111827 100%);
            --card-bg: #1e293b;
            --nav-bg: #0f172a;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --input-bg: #0f172a;
            
            --primary-gradient: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            --primary-shadow: rgba(59, 130, 246, 0.3);
            --table-header: #0f172a;
            
            --success-bg: rgba(6, 78, 59, 0.6);
            --success-border: #047857;
            --success-text: #6ee7b7;
            
            --danger-bg: rgba(127, 29, 29, 0.6);
            --danger-border: #991b1b;
            --danger-text: #fca5a5;

            --hud-bg: linear-gradient(180deg, #030712 0%, #0f172a 100%);
            --hud-border: #1e293b;
            --hud-text: #f8fafc;
        }

        * { box-sizing: border-box; transition: background 0.2s, border-color 0.2s, color 0.2s, box-shadow 0.2s; }

        body { 
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; 
            background: var(--bg-gradient); 
            min-height: 100vh;
            margin: 0; 
            padding: 36px 16px; 
            color: var(--text-main); 
            -webkit-font-smoothing: antialiased;
        }

        .container { 
            max-width: 1020px; 
            margin: 0 auto; 
            background: var(--card-bg); 
            padding: 36px; 
            border-radius: 24px; 
            box-shadow: 0 20px 45px -10px rgba(0,0,0,0.08);
            border: 1px solid var(--border-color);
        }

        .brand-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
            padding-bottom: 24px;
            border-bottom: 1px solid var(--border-color);
        }
        .brand-left {
            display: flex;
            align-items: center;
            gap: 16px;
        }
        .ui-logo {
            height: 48px;
            width: auto;
            object-fit: contain;
        }
        .brand-text h1 {
            margin: 0;
            font-size: 22px;
            color: var(--text-main);
            font-weight: 800;
            letter-spacing: -0.02em;
        }
        .brand-text p {
            margin: 3px 0 0 0;
            font-size: 13px;
            color: var(--text-muted);
            font-weight: 500;
        }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .btn-theme-toggle {
            background: var(--nav-bg);
            border: 1px solid var(--border-color);
            color: var(--text-main);
            padding: 9px 16px;
            border-radius: 30px;
            cursor: pointer;
            font-size: 13px;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-tabs { 
            display: flex; 
            background: var(--nav-bg);
            padding: 6px;
            border-radius: 16px;
            margin-bottom: 24px; 
            gap: 6px;
            border: 1px solid var(--border-color);
        }
        .nav-tabs a { 
            flex: 1;
            text-align: center;
            padding: 14px 16px; 
            text-decoration: none; 
            color: var(--text-muted); 
            font-weight: 700; 
            font-size: 13px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }
        .nav-tabs a.active { 
            color: #ffffff; 
            background: var(--primary-gradient);
            box-shadow: 0 8px 16px -4px var(--primary-shadow);
        }

        .debug-bar {
            font-size: 13px;
            color: var(--text-muted);
            background: var(--nav-bg);
            padding: 12px 20px;
            border-radius: 12px;
            margin-bottom: 28px;
            border: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-weight: 500;
        }
        .logout-link { color: #ef4444; text-decoration: none; font-weight: 700; }

        .section-title-wrap { margin-bottom: 20px; }
        h2 { font-weight: 800; font-size: 20px; color: var(--text-main); margin: 0 0 4px 0; letter-spacing: -0.01em; }
        .subtitle { color: var(--text-muted); font-size: 13px; margin: 0; font-weight: 500; }

        .alert { 
            padding: 16px 20px; 
            border-radius: 14px; 
            margin-bottom: 28px; 
            font-weight: 600; 
            font-size: 14px; 
            display: flex;
            align-items: center;
            gap: 12px;
            line-height: 1.4;
            box-shadow: 0 4px 12px rgba(0,0,0,0.02);
        }
        .alert-danger { background: var(--danger-bg); color: var(--danger-text); border: 1px solid var(--danger-border); }
        .alert-success { background: var(--success-bg); color: var(--success-text); border: 1px solid var(--success-border); }
        .alert-warning { background: #fffbe3; color: #b45309; border: 1px solid #fde68a; }

        .search-box-wrap { display: flex; gap: 12px; margin-bottom: 28px; }
        input[type="text"] { 
            flex: 1; 
            padding: 14px 18px; 
            border: 1px solid var(--border-color); 
            border-radius: 12px; 
            font-size: 14px; 
            background-color: var(--input-bg);
            color: var(--text-main);
            font-family: inherit;
        }
        input[type="text"]:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 4px var(--primary-shadow); }
        
        button, .btn-action { 
            background: var(--primary-gradient); 
            color: white; 
            border: none; 
            padding: 14px 28px; 
            font-size: 14px; 
            border-radius: 12px; 
            cursor: pointer; 
            font-weight: 700; 
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            font-family: inherit;
            box-shadow: 0 8px 16px -4px var(--primary-shadow);
        }

        .table-responsive { width: 100%; overflow-x: auto; border: 1px solid var(--border-color); border-radius: 14px; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 16px 20px; }
        th { background: var(--table-header); font-weight: 700; color: var(--text-muted); font-size: 11px; border-bottom: 1px solid var(--border-color); text-transform: uppercase; letter-spacing: 0.08em; }
        td { border-bottom: 1px solid var(--border-color); background: var(--card-bg); font-size: 14px; }
        tr:last-child td { border-bottom: none; }

        .badge { padding: 6px 12px; border-radius: 30px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; letter-spacing: 0.02em; }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-secondary { background: var(--input-bg); color: var(--text-muted); }

        .scanner-card { 
            border: 1px solid var(--hud-border); 
            background: var(--hud-bg); 
            padding: 36px 28px; 
            border-radius: 20px; 
            text-align: center; 
            margin-bottom: 24px; 
            box-shadow: 0 20px 40px -15px rgba(0,0,0,0.3);
        }
        .scanner-card input[type="text"] { 
            width: 100%; max-width: 500px; text-align: center; font-weight: 800; font-size: 20px; color: #ffffff; border: 2px solid #3b82f6; background: rgba(15, 23, 42, 0.8); letter-spacing: 0.08em; padding: 18px; border-radius: 14px; box-shadow: 0 0 20px rgba(59, 130, 246, 0.2); margin-bottom: 16px;
        }

        .claim-status-panel {
            margin-top: 24px;
            padding: 20px;
            border-radius: 18px;
            background: rgba(255,255,255,0.05);
            border: 1px solid rgba(255,255,255,0.1);
        }
        .claim-status-title {
            color: #f8fafc;
            font-size: 15px;
            font-weight: 800;
            margin-bottom: 16px;
            letter-spacing: 0.02em;
            text-align: center;
        }

        .print-item-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            padding: 16px 20px;
            border-radius: 14px;
            margin-bottom: 10px;
            cursor: pointer;
            transition: all 0.2s ease;
            border: 1px solid rgba(0,0,0,0.1);
            text-align: left;
            user-select: none;
        }
        .print-item-row.item-claimed { background: #d1fae5; color: #065f46; border-color: #a7f3d0; }
        .print-item-row.item-unclaimed { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
        .print-item-row input[type="checkbox"] { display: none; }
        
        .print-item-state { 
            padding: 6px 14px; 
            border-radius: 999px; 
            font-size: 12px; 
            font-weight: 800; 
            min-width: 100px; 
            text-align: center; 
            letter-spacing: 0.03em;
        }
        .print-item-row.item-claimed .print-item-state { background: #10b981; color: white; }
        .print-item-row.item-unclaimed .print-item-state { background: #ef4444; color: white; }

        .scanner-status {
            margin-top: 16px;
            font-size: 13px;
            color: var(--hud-text);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 600;
        }
        .pulse-dot {
            width: 10px;
            height: 10px;
            background-color: #22c55e;
            border-radius: 50%;
            display: inline-block;
            box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7);
            animation: pulse 1.6s infinite;
        }
        @keyframes pulse {
            0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); }
            70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); }
            100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); }
        }

        #thermal-receipt-view { 
            display: none; 
            background: white !important; 
            color: #000 !important;
            width: 80mm; 
            padding: 15px; 
            margin: 24px auto; 
            border: 1px dashed #aaa; 
            text-align: center; 
            box-sizing: border-box; 
            font-family: monospace;
        }
        
        .receipt-header { font-weight: bold; font-size: 15px; margin-bottom: 2px; text-transform: uppercase; color: #000 !important; text-align: center; }
        .receipt-divider { border-top: 1px dashed #000; margin: 10px 0; width: 100%; }
        .duplicate-notice { border: 2px solid #000; font-weight: bold; font-size: 12px; padding: 6px; margin: 8px auto; text-transform: uppercase; letter-spacing: 1px; color: #000 !important; text-align: center; width: 100%; box-sizing: border-box; }
        
        .receipt-details-table { width: 100%; margin: 0 auto; text-align: left; font-size: 12px; line-height: 1.4; color: #000 !important; }
        .receipt-details-table td { padding: 2px 0; border: none; background: transparent !important; color: #000 !important; }

        .qr-wrapper { margin: 10px auto; text-align: center; }
        .qr-wrapper img { width: 130px; height: 130px; display: block; margin: 0 auto; }
        
        .freebie-container { width: 100%; margin: 8px auto; padding: 0; text-align: left; box-sizing: border-box; }
        .freebie-row { display: flex; align-items: center; margin-bottom: 6px; width: 100%; }
        
        .freebie-chk-box { width: 16px; height: 16px; min-width: 16px; min-height: 16px; border: 2px solid #000; margin-right: 10px; display: inline-block; box-sizing: border-box; position: relative; }
        .freebie-chk-box.checked { background: #000 !important; }
        .freebie-chk-box.checked::after { content: "✓"; color: #fff; font-size: 12px; position: absolute; top: -2px; left: 2px; font-weight: bold; }
        
        .freebie-label { font-size: 12px; font-weight: bold; color: #000 !important; font-family: sans-serif; line-height: 1.2; display: inline-block; }

        @media print {
            @page { 
                margin: 0; 
                size: 80mm auto; 
            }
            body * { visibility: hidden !important; background: transparent !important; }
            #thermal-receipt-view, #thermal-receipt-view * { visibility: visible !important; color: #000 !important; }
            #thermal-receipt-view { 
                display: flex !important;
                flex-direction: column !important;
                align-items: center !important;
                position: absolute !important;
                left: 0 !important;
                right: 0 !important;
                top: 0 !important;
                width: 80mm !important; 
                margin: 0 auto !important;
                padding: 6mm !important; 
                box-sizing: border-box !important;
                background: #ffffff !important;
            }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="container no-print">
    <!-- Header -->
    <div class="brand-header">
        <div class="brand-left">
            <img src="/evoting/assets/images/logo.png" class="ui-logo" alt="Company Logo" onerror="this.style.display='none'">
            <div class="brand-text">
                <h1>Panabo Cooperative</h1>
                <p>General Assembly Registration & Gate Control System</p>
            </div>
        </div>
        <div class="header-actions">
            <button class="btn-theme-toggle" id="theme-toggle">🌙 Dark Mode</button>
        </div>
    </div>

    <!-- Navigation Tabs -->
    <div class="nav-tabs">
        <a href="index.php?route=registration" class="<?= $route === 'registration' ? 'active' : '' ?>">
            🏢 Terminal A: Central Registration
        </a>
        <a href="index.php?route=gate" class="<?= $route === 'gate' ? 'active' : '' ?>">
            🎁 Terminal B: Attendance & Freebies Gate
        </a>
    </div>

    <!-- Operator Status Bar -->
    <div class="debug-bar">
        <span>Active Operator: <strong><?= htmlspecialchars($current_username) ?></strong></span>
        <a href="index.php?action=logout" class="logout-link">End Session →</a>
    </div>

    <!-- Feedback Alerts -->
    <?php if (!empty($error)): ?>
        <div class="alert alert-danger">
            <span style="font-size: 18px;">⚠️</span>
            <div><?= $error ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success">
            <span style="font-size: 18px;">🎉</span>
            <div><?= $message ?></div>
        </div>
    <?php endif; ?>

    <!-- ========================================== -->
    <!-- INTERFACE: TERMINAL A (REGISTRATION)       -->
    <!-- ========================================== -->
    <?php if ($route === 'registration'): ?>
        <div class="section-title-wrap">
            <h2>Member Registration Window (Terminal A)</h2>
            <p class="subtitle">Search and process member pre-registration to generate gate passes.</p>
        </div>
        
        <form method="GET" action="index.php" class="search-box-wrap">
            <input type="hidden" name="route" value="registration">
            <input type="text" name="search" placeholder="Enter Member ID or Full Name..." value="<?= htmlspecialchars(!filter_var($search_query, FILTER_VALIDATE_URL) ? $search_query : '') ?>" autofocus>
            <button type="submit">Search Member</button>
        </form>

        <?php if (!empty($search_query) && !filter_var($search_query, FILTER_VALIDATE_URL)): ?>
            <div style="margin-top: 28px;">
                <h3 style="font-size: 14px; font-weight: 800; margin-bottom: 14px; color: var(--text-main);">
                    Search Results (<?= count($search_results) ?> found)
                </h3>
                <?php if (count($search_results) > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 100px;">ID</th>
                                    <th>Full Name</th>
                                    <th style="width: 160px;">Status</th>
                                    <th style="width: 280px; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($search_results as $row): ?>
                                    <tr>
                                        <td><strong>#<?= $row['id'] ?></strong></td>
                                        <td style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($row['full_name']) ?></td>
                                        <td>
                                            <?php if ($row['registered']): ?>
                                                <span class="badge badge-success">✓ Pre-Registered</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <form method="POST" action="index.php?route=registration&search=<?= urlencode($search_query) ?>" style="margin:0;">
                                                <input type="hidden" name="action" value="register_member">
                                                <input type="hidden" name="member_id" value="<?= $row['id'] ?>">
                                                <?php if (!$row['registered']): ?>
                                                    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:10px;">
                                                        <div style="text-align:left; max-width: 260px; font-size: 12px; color: var(--text-muted);">
                                                            <strong style="display:block; margin-bottom: 6px;">Select Terminal A Pre-Registration Freebies:</strong>
                                                            <label style="display:inline-flex; align-items:center; gap:6px; margin-bottom: 4px;">
                                                                <input type="checkbox" name="claimed_items[]" value="GA T-Shirt"> GA T-Shirt
                                                            </label>
                                                            <label style="display:inline-flex; align-items:center; gap:6px; margin-bottom: 4px;">
                                                                <input type="checkbox" name="claimed_items[]" value="Cash Allowance"> Cash Allowance
                                                            </label>
                                                            <label style="display:inline-flex; align-items:center; gap:6px; margin-bottom: 4px;">
                                                                <input type="checkbox" name="claimed_items[]" value="Snacks / Meals"> Snacks / Meals
                                                            </label>
                                                            <label style="display:inline-flex; align-items:center; gap:6px; margin-bottom: 4px;">
                                                                <input type="checkbox" name="claimed_items[]" value="PMPC Umbrella"> PMPC Umbrella
                                                            </label>
                                                            <?php if (!empty($row['migs_category']) && stripos($row['migs_category'], 'GOLD') !== false): ?>
                                                            <label style="display:inline-flex; align-items:center; gap:6px;">
                                                                <input type="checkbox" name="claimed_items[]" value="Water Bottle for Gold Members"> Water Bottle for Gold Members
                                                            </label>
                                                            <?php endif; ?>
                                                        </div>
                                                        <button type="submit" style="padding: 10px 16px; font-size: 12px;">Confirm & Print Pass</button>
                                                    </div>
                                                <?php else: ?>
                                                    <button type="submit" style="padding: 10px 16px; font-size: 12px; background: linear-gradient(135deg, #d97706 0%, #b45309 100%);">Reprint Pass</button>
                                                <?php endif; ?>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <p style="color: var(--text-muted); font-size: 13px; padding: 12px 0;">No member accounts match your search query.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <!-- ========================================================= -->
    <!-- INTERFACE: TERMINAL B (ATTENDANCE & FREEBIES GATE)       -->
    <!-- ========================================================= -->
    <?php if ($route === 'gate'): ?>
        <div class="section-title-wrap">
            <h2>Attendance & Freebies Terminal (Terminal B)</h2>
            <p class="subtitle">Scan member pass to review pre-registered claims and issue on-site items.</p>
        </div>

        <div class="scanner-card">
            <form method="POST" action="index.php?route=gate" id="gate-scan-form">
                <input type="hidden" name="action" value="gate_scan">
                <input type="text" name="scan_input" id="gate-scan-input" placeholder="SCAN MEMBER QR PASS HERE" autocomplete="off" autofocus>
                <button type="submit" style="display: block; margin: 0 auto; max-width: 500px; width: 100%;">Scan Member Pass</button>
            </form>

            <div class="scanner-status">
                <span class="pulse-dot"></span>
                Integrated Scanner Ready — Scan a member pass to proceed
            </div>

            <?php if (!empty($print_target_id)): ?>
                <?php
                    $gate_member = null;
                    $gate_claimed_items = [];
                    $gate_freebie_items = ['GA T-Shirt', 'Cash Allowance', 'Snacks / Meals', 'PMPC Umbrella'];
                    
                    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
                    $stmt->execute([$print_target_id]);
                    $gate_member = $stmt->fetch();

                    if ($gate_member) {
                        if (!empty($gate_member['migs_category']) && stripos($gate_member['migs_category'], 'GOLD') !== false) {
                            $gate_freebie_items[] = 'Water Bottle for Gold Members';
                        }

                        $claim_stmt = $pdo->prepare("SELECT item_name FROM member_freebie_claims WHERE member_id = ?");
                        $claim_stmt->execute([$print_target_id]);
                        $gate_claimed_items = array_filter(array_map('trim', $claim_stmt->fetchAll(PDO::FETCH_COLUMN)), 'strlen');
                        $gate_claimed_items_normalized = array_map(function($name) {
                            return preg_replace('/\s+/', ' ', mb_strtolower(trim($name)));
                        }, $gate_claimed_items);
                    }
                ?>

                <?php if ($gate_member): ?>
                    <div class="claim-status-panel">
                        <div class="claim-status-title">
                            Member Claim Status: <strong><?= htmlspecialchars($gate_member['full_name']) ?></strong> 
                            (#<?= htmlspecialchars($gate_member['id']) ?>) - <?= htmlspecialchars(!empty($gate_member['migs_category']) ? strtoupper($gate_member['migs_category']) : 'REGULAR') ?>
                            <div style="font-size: 13px; color: #94a3b8; font-weight: 600; margin-top: 4px;">Branch: <?= htmlspecialchars(!empty($gate_member['branch_name']) ? $gate_member['branch_name'] : 'N/A') ?></div>
                        </div>

                        <!-- Combined Interactive Item List Form -->
                        <form method="POST" action="index.php?route=gate&print_id=<?= urlencode($print_target_id) ?>&gate_print=1" id="gate-print-form" style="margin-top: 16px; text-align: left;">
                            <input type="hidden" name="action" value="gate_print_claims">
                            <input type="hidden" name="member_id" value="<?= htmlspecialchars($print_target_id) ?>">
                            <input type="hidden" name="print_now" value="1">
                            <input type="hidden" name="admin_override" id="admin_override_input" value="0">

                            <div style="font-size: 12px; color: #94a3b8; text-align: left; margin-bottom: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em;">
                                Click items below to toggle status & tag for issue:
                            </div>

                            <?php foreach ($gate_freebie_items as $item):
                                $normalized_item = preg_replace('/\s+/', ' ', mb_strtolower(trim($item)));
                                $item_claimed = in_array($normalized_item, $gate_claimed_items_normalized, true);
                            ?>
                                <div class="print-item-row <?= $item_claimed ? 'item-claimed' : 'item-unclaimed' ?>">
                                    <input type="checkbox" name="selected_items[]" value="<?= htmlspecialchars($item) ?>" <?= $item_claimed ? 'checked' : '' ?> />
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <span class="freebie-chk-box <?= $item_claimed ? 'checked' : '' ?>"></span>
                                        <span class="freebie-label" style="color: inherit !important; font-size: 14px;"><?= htmlspecialchars($item) ?></span>
                                    </div>
                                    <span class="print-item-state"><?= $item_claimed ? '✓ Claimed' : '✗ Unclaimed' ?></span>
                                </div>
                            <?php endforeach; ?>

                            <!-- COMBINED ACTION BUTTONS: STANDARD SAVE & SECOND CHANCE OVERRIDE -->
                            <div style="display: flex; justify-content: center; gap: 12px; margin-top: 24px; flex-wrap: wrap;">
                                <button type="submit" id="gate-save-print-btn" style="padding: 14px 32px; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; border-radius: 12px; font-weight: 800; font-size: 14px; border: none; cursor: pointer; box-shadow: 0 8px 16px -4px rgba(4, 120, 87, 0.4); display: inline-flex; align-items: center; gap: 8px;">
                                    💾 Save DB & Print Slip 🖨️
                                </button>
                                
                                <button type="button" id="btnOverride" onclick="requestAdminOverride()" style="padding: 14px 24px; background: linear-gradient(135deg, #d97706 0%, #b45309 100%); color: white; border-radius: 12px; font-weight: 800; font-size: 14px; border: none; cursor: pointer; box-shadow: 0 8px 16px -4px rgba(217, 119, 6, 0.4); display: inline-flex; align-items: center; gap: 8px;">
                                    🔑 Override & Grant Second Chance
                                </button>
                            </div>
                        </form>
                    </div>
                <?php else: ?>
                    <div style="margin-top: 18px; font-size: 13px; color: #b91c1c; font-weight: 700;">Member record not found.</div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- ========================================================================= -->
<!-- 3. RUNTIME THERMAL PRINT RUN STAGE                                        -->
<!-- ========================================================================= -->
<?php 
if (!empty($print_target_id) && ($route !== 'gate' || ($_GET['gate_print'] ?? '') === '1')): 
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$print_target_id]);
    $print_member = $stmt->fetch();
    
    if ($print_member):
        $raw_category = $print_member['migs_category'] ?? $print_member['category'] ?? $print_member['migs_status'] ?? '';
        $migs_category = strtoupper(trim((string)$raw_category));
        $branch_name = !empty($print_member['branch_name']) ? trim($print_member['branch_name']) : 'N/A';

        $claims_stmt = $pdo->prepare("SELECT item_name FROM member_freebie_claims WHERE member_id = ?");
        $claims_stmt->execute([$print_target_id]);
        $claimed_items_db = array_filter(array_map('trim', array_unique($claims_stmt->fetchAll(PDO::FETCH_COLUMN))));

        $gate_qr_url = "gate_id=" . $print_member['id']; 
        $gate_qr_file = 'temp_qr_gate_' . $print_member['id'] . '.png';
        QRcode::png($gate_qr_url, $gate_qr_file, QR_ECLEVEL_M, 4, 2);
?>
    <div id="thermal-receipt-view">
        <?php if ($is_duplicate): ?>
            <div class="duplicate-notice">⚠️ DUPLICATE COPY - ALREADY CLAIMED</div>
        <?php endif; ?>

        <?php if ($route === 'registration'): ?>
            <!-- Terminal A Registration Pass -->
            <div class="receipt-header">GENERAL ASSEMBLY</div>
            <div style="font-size: 11px; font-weight: bold; letter-spacing: 1px; text-align: center; color: #000;">PRE-REGISTRATION QR PASS</div>
            <div class="receipt-divider"></div>
            
            <table class="receipt-details-table">
                <tr>
                    <td style="width: 45%;"><strong>MEMBER ID:</strong></td>
                    <td><?= htmlspecialchars($print_member['id']) ?></td>
                </tr>
                <tr>
                    <td><strong>NAME:</strong></td>
                    <td><?= htmlspecialchars($print_member['full_name']) ?></td>
                </tr>
                <tr>
                    <td><strong>BRANCH:</strong></td>
                    <td><?= htmlspecialchars($branch_name) ?></td>
                </tr>
                <tr>
                    <td><strong>CATEGORY:</strong></td>
                    <td><?= htmlspecialchars(!empty($migs_category) ? $migs_category : 'REGULAR') ?></td>
                </tr>
            </table>
            
            <div class="receipt-divider"></div>
            <?php if (!empty($claimed_items_db)): ?>
                <div style="font-size: 11px; font-weight: 700; margin-bottom: 8px; text-align: left; width: 100%; color: #000;">Pre-Registration Freebies</div>
                <div class="freebie-container">
                    <?php foreach ($claimed_items_db as $item): ?>
                        <div class="freebie-row">
                            <div class="freebie-chk-box checked"></div>
                            <div class="freebie-label"><?= htmlspecialchars($item) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="receipt-divider"></div>
            <?php else: ?>
                <div style="font-size: 10px; color: #000; margin-bottom: 10px; text-align: center;">No pre-registration freebies were selected.</div>
            <?php endif; ?>
            
            <div style="font-size: 9px; font-weight: bold; margin-bottom: 2px; text-align: center; color: #000;">PRESENT THIS AT TERMINAL B</div>
            <div class="qr-wrapper">
                <img src="<?= $gate_qr_file ?>?v=<?= time() ?>" alt="Entrance QR Pass">
            </div>
            <div style="font-size: 8px; color: #000; text-align: center;">Scan at Terminal B to verify entrance and claim freebies.</div>

        <?php else: ?>
            <!-- Terminal B Gate Attendance & Freebie Printout -->
            <div class="receipt-header">GENERAL ASSEMBLY</div>
            <div style="font-size: 11px; font-weight: bold; letter-spacing: 1px; text-align: center; color: #000;">
                <?= $is_duplicate ? 'ATTENDANCE RECEIPT (REPRINT)' : 'ATTENDANCE & FREEBIES CLAIMED' ?>
            </div>
            <div class="receipt-divider"></div>
            
            <table class="receipt-details-table">
                <tr>
                    <td style="width: 45%;"><strong>MEMBER ID:</strong></td>
                    <td><?= htmlspecialchars($print_member['id']) ?></td>
                </tr>
                <tr>
                    <td><strong>NAME:</strong></td>
                    <td><?= htmlspecialchars($print_member['full_name']) ?></td>
                </tr>
                <tr>
                    <td><strong>BRANCH:</strong></td>
                    <td><?= htmlspecialchars($branch_name) ?></td>
                </tr>
                <tr>
                    <td><strong>CATEGORY:</strong></td>
                    <td><?= htmlspecialchars(!empty($migs_category) ? $migs_category : 'REGULAR') ?></td>
                </tr>
            </table>

            <div class="receipt-divider"></div>
            <div style="font-size: 11px; font-weight: bold; margin-bottom: 4px; text-align: center; width: 100%; color: #000;">🎁 ISSUED FREEBIES & ALLOWANCE</div>

            <?php if (!empty($claimed_items_db)): ?>
                <div class="freebie-container">
                    <?php foreach ($claimed_items_db as $item): ?>
                        <div class="freebie-row">
                            <div class="freebie-chk-box checked"></div>
                            <div class="freebie-label"><?= htmlspecialchars($item) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="font-size: 10px; color: #000; margin-bottom: 10px; text-align: center;">No freebies were recorded for this member.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<!-- Auto-Print Trigger Script -->
<?php if (!empty($print_target_id) && ($route !== 'gate' || ($_GET['gate_print'] ?? '') === '1')): ?>
<script>
    window.addEventListener('load', function() {
        <?php if ($route !== 'gate' || ($_GET['print_now'] ?? '') === '1'): ?>
        setTimeout(function() {
            window.print();
        }, 400);
        <?php endif; ?>
    });
</script>
<?php endif; ?>

<!-- UI Interactivity & Smooth Scroll Scripts -->
<script>
    // Security Passkey for Staff Override
    const ADMIN_AUTHORIZATION_KEY = "ADMIN123";

    function requestAdminOverride() {
        const inputKey = prompt("STAFF ERROR OVERRIDE:\nEnter Admin Authorization Key to allow a second-chance claim modification:");
        if (inputKey === null) return;

        if (inputKey === ADMIN_AUTHORIZATION_KEY) {
            document.getElementById('admin_override_input').value = '1';
            alert('Admin Authorization Granted! Submitting claim adjustment...');
            document.getElementById('gate-print-form').submit();
        } else {
            alert('ERROR: Invalid Authorization Key. Override denied.');
        }
    }

    // Restore scroll position on page reload if saved
    document.addEventListener("DOMContentLoaded", function() {
        const savedScroll = sessionStorage.getItem("portal_scroll_pos");
        if (savedScroll !== null) {
            window.scrollTo(0, parseInt(savedScroll));
            sessionStorage.removeItem("portal_scroll_pos");
        }
    });

    // Save scroll position before submitting forms or unloading
    window.addEventListener("beforeunload", function() {
        sessionStorage.setItem("portal_scroll_pos", window.scrollY);
    });

    const toggleBtn = document.getElementById('theme-toggle');
    const currentTheme = localStorage.getItem('theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
    
    if (currentTheme === 'dark') {
        document.documentElement.setAttribute('data-theme', 'dark');
        if(toggleBtn) toggleBtn.textContent = '☀️ Light Mode';
    }

    if(toggleBtn) {
        toggleBtn.addEventListener('click', () => {
            let theme = document.documentElement.getAttribute('data-theme');
            if (theme === 'dark') {
                document.documentElement.removeAttribute('data-theme');
                localStorage.setItem('theme', 'light');
                toggleBtn.textContent = '🌙 Dark Mode';
            } else {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
                toggleBtn.textContent = '☀️ Light Mode';
            }
        });
    }

    const scanInput = document.getElementById('gate-scan-input');
    const scanForm = document.getElementById('gate-scan-form');

    if (scanForm) {
        scanForm.addEventListener('submit', function(e) {
            if (!scanInput || scanInput.value.trim() === '') {
                e.preventDefault();
                if (scanInput) scanInput.focus({ preventScroll: true });
            }
        });
    }

    // Auto-focus scanner input safely without causing page scroll jumps
    if (scanInput) {
        scanInput.focus({ preventScroll: true });
        document.addEventListener('click', function(e) {
            const isClickInsideInteractive = e.target.closest('input, button, a, label, .print-item-row, .claim-status-panel');
            if (!isClickInsideInteractive) {
                scanInput.focus({ preventScroll: true });
            }
        });
    }

    // Dynamic Live Toggle behavior for unified item list
    const printItemRows = document.querySelectorAll('.print-item-row');
    printItemRows.forEach((row) => {
        const checkbox = row.querySelector('input[type="checkbox"]');
        const stateLabel = row.querySelector('.print-item-state');
        const checkBoxBox = row.querySelector('.freebie-chk-box');

        row.addEventListener('click', function(e) {
            e.preventDefault(); // Prevent scroll jump
            if (!checkbox) return;

            checkbox.checked = !checkbox.checked;

            if (checkbox.checked) {
                row.classList.remove('item-unclaimed');
                row.classList.add('item-claimed');
                if (stateLabel) stateLabel.textContent = '✓ Claimed';
                if (checkBoxBox) checkBoxBox.classList.add('checked');
            } else {
                row.classList.remove('item-claimed');
                row.classList.add('item-unclaimed');
                if (stateLabel) stateLabel.textContent = '✗ Unclaimed';
                if (checkBoxBox) checkBoxBox.classList.remove('checked');
            }
        });
    });
</script>

</body>
</html>
