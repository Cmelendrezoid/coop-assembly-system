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
$db_name = 'coopevoting';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);

    // Auto-create missing claims table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `member_freebie_claims` (
            `id` INT(11) NOT NULL AUTO_INCREMENT,
            `member_id` INT(11) NOT NULL,
            `member_name` VARCHAR(255) DEFAULT NULL,
            `branch` VARCHAR(100) DEFAULT NULL,
            `item_name` VARCHAR(255) NOT NULL,
            `claimed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `processed_by` INT(11) DEFAULT NULL,
            `is_printed` TINYINT(1) NOT NULL DEFAULT 0,
            PRIMARY KEY (`id`),
            KEY `member_id` (`member_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    $columns = $pdo->query("SHOW COLUMNS FROM `member_freebie_claims`")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('member_name', $columns)) {
        $pdo->exec("ALTER TABLE `member_freebie_claims` ADD COLUMN `member_name` VARCHAR(255) AFTER `member_id`");
    }
    if (!in_array('branch', $columns)) {
        $pdo->exec("ALTER TABLE `member_freebie_claims` ADD COLUMN `branch` VARCHAR(100) AFTER `member_name`");
    }
    if (!in_array('is_printed', $columns)) {
        $pdo->exec("ALTER TABLE `member_freebie_claims` ADD COLUMN `is_printed` TINYINT(1) NOT NULL DEFAULT 0 AFTER `processed_by`");
    }

    // Ensure Unique Composite Index on (member_id, item_name) to prevent duplicate database records
    try {
        $pdo->exec("ALTER TABLE `member_freebie_claims` ADD UNIQUE KEY `unique_member_item` (`member_id`, `item_name`)");
    } catch (PDOException $e) {
        // Index already exists or duplicate entries exist; ignore safely
    }

} catch (PDOException $e) {
    die("Database connection or schema setup failed: " . $e->getMessage());
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
                $_SESSION['qr_user_id'] = $user['user_id'] ?? $user['id'] ?? 1;
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

            .hero-badge {
                align-self: flex-start;
                background: rgba(255, 255, 255, 0.15);
                backdrop-filter: blur(8px);
                padding: 6px 14px;
                border-radius: 20px;
                font-size: 12px;
                font-weight: 700;
                border: 1px solid rgba(255, 255, 255, 0.2);
            }

            .hero-content h1 {
                font-size: 28px;
                font-weight: 800;
                line-height: 1.25;
                margin: 20px 0 12px 0;
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

            .brand-header-login { margin-bottom: 28px; }
            .login-logo { height: 52px; width: auto; margin-bottom: 16px; object-fit: contain; }
            .login-card h2 { margin: 0 0 6px 0; font-size: 24px; font-weight: 800; color: var(--text-main); }
            .login-card p { color: var(--text-muted); font-size: 13px; margin: 0; font-weight: 500; }

            .form-group { margin-bottom: 20px; }
            label { display: block; font-size: 12px; font-weight: 700; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.05em; color: var(--text-muted); }
            .input-wrap { position: relative; display: flex; align-items: center; }
            .input-icon { position: absolute; left: 16px; width: 18px; height: 18px; stroke: var(--text-muted); fill: none; stroke-width: 2; stroke-linecap: round; stroke-linejoin: round; pointer-events: none; }

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
            <div class="login-hero">
                <div class="hero-badge">Panabo Cooperative</div>
                <div class="hero-content">
                    <h1>General Assembly Registration Portal</h1>
                    <p>Welcome! Please authenticate your terminal operator session to manage member check-ins and freebie distribution.</p>
                </div>
                <div class="hero-footer">&copy; <?= date('Y') ?> Panabo Multi-Purpose Cooperative</div>
            </div>

            <div class="login-card">
                <div class="brand-header-login">
                    <img src="/evoting/assets/images/logo.png" class="login-logo" alt="Company Logo" onerror="this.style.display='none'">
                    <h2>Welcome Back</h2>
                    <p>Sign in with your authorized terminal credentials</p>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert"><span>⚠️</span><div><?= htmlspecialchars($error) ?></div></div>
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

$current_user_id = $_SESSION['qr_user_id'] ?? 1;
$current_username = $_SESSION['qr_username'] ?? 'System';
$route = $_GET['route'] ?? 'registration';

if (!file_exists('qrlib.php')) {
    die("Error: 'qrlib.php' was not found in this folder.");
}
require_once 'qrlib.php';

// =========================================================================
// 2. BACKEND ROUTING & EXPORT ACTIONS
// =========================================================================
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'export_freebies') {
    try {
        $claims = $pdo->query("
            SELECT 
                c.member_id, 
                COALESCE(c.member_name, m.full_name) AS full_name, 
                COALESCE(m.migs_category, 'REGULAR') AS category,
                COALESCE(c.branch, m.branch_name, 'N/A') AS branch_name,
                c.item_name AS claimed_item,
                c.claimed_at AS claim_time,
                u.username AS processed_by
            FROM member_freebie_claims c
            LEFT JOIN members m ON c.member_id = m.id
            LEFT JOIN users u ON c.processed_by = u.user_id
            ORDER BY c.claimed_at DESC, c.id ASC
        ")->fetchAll();

        $filename = 'Freebies_Claimants_Report_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, ['Member ID', 'Member Name', 'Category', 'Branch', 'Claimed Freebie Item', 'Claim Date & Time', 'Processed By']);
        foreach ($claims as $row) {
            fputcsv($output, [$row['member_id'], $row['full_name'], $row['category'], $row['branch_name'], $row['claimed_item'], $row['claim_time'], $row['processed_by'] ?? 'System']);
        }
        fclose($output);
        exit;
    } catch (PDOException $e) {
        $error = "Export failed: " . $e->getMessage();
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && $_GET['action'] === 'export_attendance_summary') {
    try {
        $total = $pdo->query("SELECT COUNT(*) FROM members WHERE registered = 1")->fetchColumn();
        $today = $pdo->query("SELECT COUNT(*) FROM members WHERE registered = 1 AND DATE(registered_at) = DATE(NOW())")->fetchColumn();
        $categories = $pdo->query("SELECT COALESCE(migs_category, 'REGULAR') AS category, COUNT(*) AS total FROM members WHERE registered = 1 GROUP BY migs_category")->fetchAll();

        $filename = 'Attendance_Headcount_Summary_' . date('Y-m-d_H-i-s') . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        fputcsv($output, ['ATTENDANCE HEADCOUNT SUMMARY REPORT']);
        fputcsv($output, ['Generated On:', date('Y-m-d H:i:s')]);
        fputcsv($output, []);
        fputcsv($output, ['Metric Description', 'Total Count']);
        fputcsv($output, ['Total Attendees Registered (All Time)', $total]);
        fputcsv($output, ['Total Attendees Registered (Today)', $today]);
        fputcsv($output, []);
        fputcsv($output, ['CATEGORY BREAKDOWN']);
        fputcsv($output, ['Member Category', 'Registered Count']);
        foreach ($categories as $cat) {
            fputcsv($output, [$cat['category'], $cat['total']]);
        }
        fclose($output);
        exit;
    } catch (PDOException $e) {
        $error = "Summary export failed: " . $e->getMessage();
    }
}

// --- MEMBER PRE-REGISTRATION ACTION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_member') {
    $member_id = trim($_POST['member_id'] ?? '');
    $raw_claimed_items = $_POST['claimed_items'] ?? [];
    
    if (!empty($member_id)) {
        try {
            $check = $pdo->prepare("SELECT id, full_name, migs_category, branch_name, registered, printed FROM members WHERE id = ?");
            $check->execute([$member_id]);
            $member_info = $check->fetch();

            if ($member_info) {
                $real_id = $member_info['id'];
                $is_already_reg = $member_info['registered'] ?? 0;
                $is_already_printed = $member_info['printed'] ?? 0;
                $member_category = strtoupper(trim((string)($member_info['migs_category'] ?? '')));

                // REPRINT PASS CHECK: If already registered and no new items submitted, preserve existing DB claims
                if ($is_already_reg && empty($raw_claimed_items)) {
                    $search_param = urlencode($_GET['search'] ?? $_POST['search_query_ref'] ?? '');
                    header("Location: index.php?route=registration&print_id=" . urlencode($real_id) . "&duplicate=" . ($is_already_printed ? '1' : '0') . "&search=" . $search_param);
                    exit;
                }

                $pdo->beginTransaction();

                // Strict validation: Remove Cash Allowance if non-MIGS member
                $claimed_items = [];
                if (is_array($raw_claimed_items)) {
                    foreach ($raw_claimed_items as $c_item) {
                        $c_item_trim = trim($c_item);
                        if ($member_category === 'NON-MIGS' && strtolower($c_item_trim) === 'cash allowance') {
                            continue;
                        }
                        if ($c_item_trim !== '') {
                            $claimed_items[] = $c_item_trim;
                        }
                    }
                }
                $claimed_items = array_values(array_unique($claimed_items));

                // 1. Mark member as registered in 'members' table (Does NOT set printed/printed_at here)
                $stmt = $pdo->prepare("UPDATE members SET registered = 1, registered_at = NOW() WHERE id = ?");
                $stmt->execute([$real_id]);

                // 2. Prepare claimed items string/list
                $items_string = !empty($claimed_items) && is_array($claimed_items) 
                    ? implode(', ', array_map('trim', $claimed_items)) 
                    : 'General Assembly Pre-Registration Pass';

                // 3. Update or Insert into 'event_logs' table
                $event_check = $pdo->prepare("SELECT id FROM event_logs WHERE member_id = ?");
                $event_check->execute([$real_id]);
                if ($event_check->fetch()) {
                    $update_event = $pdo->prepare("
                        UPDATE event_logs 
                        SET is_preregistered = 1, 
                            prereg_items_claimed = ?, 
                            prereg_processed_by = ?, 
                            prereg_at = NOW() 
                        WHERE member_id = ?
                    ");
                    $update_event->execute([$items_string, $current_username, $real_id]);
                } else {
                    $insert_event = $pdo->prepare("
                        INSERT INTO event_logs (member_id, is_preregistered, prereg_items_claimed, prereg_processed_by, prereg_at) 
                        VALUES (?, 1, ?, ?, NOW())
                    ");
                    $insert_event->execute([$real_id, $items_string, $current_username]);
                }

                // 4. Update 'member_freebie_claims' table cleanly (Atomic wipe & insert unique items)
                $del_old = $pdo->prepare("DELETE FROM member_freebie_claims WHERE member_id = ?");
                $del_old->execute([$real_id]);

                if (!empty($claimed_items) && is_array($claimed_items)) {
                    $claim_insert = $pdo->prepare("
                        INSERT INTO member_freebie_claims (member_id, member_name, branch, item_name, claimed_at, processed_by, is_printed) 
                        VALUES (?, ?, ?, ?, NOW(), ?, 1)
                        ON DUPLICATE KEY UPDATE 
                            member_name = VALUES(member_name),
                            branch = VALUES(branch),
                            claimed_at = NOW(),
                            processed_by = VALUES(processed_by),
                            is_printed = 1
                    ");
                    foreach ($claimed_items as $item_name) {
                        $item_name = trim($item_name);
                        if ($item_name !== '') {
                            $claim_insert->execute([$real_id, $member_info['full_name'], $member_info['branch_name'] ?? 'N/A', $item_name, $current_user_id]);
                        }
                    }
                } else {
                    $claim_insert = $pdo->prepare("
                        INSERT INTO member_freebie_claims (member_id, member_name, branch, item_name, claimed_at, processed_by, is_printed) 
                        VALUES (?, ?, ?, ?, NOW(), ?, 1)
                        ON DUPLICATE KEY UPDATE 
                            member_name = VALUES(member_name),
                            branch = VALUES(branch),
                            claimed_at = NOW(),
                            processed_by = VALUES(processed_by),
                            is_printed = 1
                    ");
                    $claim_insert->execute([$real_id, $member_info['full_name'], $member_info['branch_name'] ?? 'N/A', 'General Assembly Pre-Registration Pass', $current_user_id]);
                }

                $pdo->commit();

                $search_param = urlencode($_GET['search'] ?? $_POST['search_query_ref'] ?? '');
                header("Location: index.php?route=registration&print_id=" . urlencode($real_id) . "&duplicate=" . ($is_already_printed ? '1' : '0') . "&search=" . $search_param);
                exit;
            } else {
                $error = "Error: Selected member record not found in database.";
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = "Pre-registration failed: " . $e->getMessage();
        }
    } else {
        $error = "Invalid Member ID provided.";
    }
}

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
                    // Check if member already has 'printed = 1' directly in members table
                    $has_been_printed = ((int)($member['printed'] ?? 0) === 1);

                    // duplicate=1 will ONLY trigger if the member.printed column is 1
                    $is_terminal_b_duplicate = $has_been_printed ? 1 : 0;

                    $pdo->beginTransaction();
                    if ($member['registered'] == 0) {
                        $update = $pdo->prepare("UPDATE members SET registered = 1, registered_at = NOW() WHERE id = ?");
                        $update->execute([$member['id']]);
                    }
                    $pdo->commit();

                    header("Location: index.php?route=gate&print_id=" . urlencode($member['id']) . "&gate_print=1" . ($is_terminal_b_duplicate ? "&duplicate=1" : ""));
                    exit;
                } else {
                    $error = "Invalid QR Code: No member found matching ID #{$scanned_id}.";
                }
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) { $pdo->rollBack(); }
                $error = "Database execution error: " . $e->getMessage();
            }
        } else {
            $error = "Please scan a valid Member QR code or enter Member ID.";
        }
    }
}

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
                $member_category = strtoupper(trim((string)($member['migs_category'] ?? '')));

                // Strict validation: Filter items and remove duplicates
                $filtered_items = [];
                if (is_array($selected_items)) {
                    foreach ($selected_items as $s_item) {
                        $s_item_trim = trim($s_item);
                        if ($member_category === 'NON-MIGS' && strtolower($s_item_trim) === 'cash allowance') {
                            continue;
                        }
                        if ($s_item_trim !== '') {
                            $filtered_items[] = $s_item_trim;
                        }
                    }
                }
                $filtered_items = array_values(array_unique($filtered_items));

                $pdo->beginTransaction();

                // Delete ALL existing claims for this exact member_id to guarantee unchecked items disappear
                $del_stmt = $pdo->prepare("DELETE FROM member_freebie_claims WHERE member_id = ?");
                $del_stmt->execute([$member_id]);

                // Insert only currently selected (checked) freebies and mark them as printed
                if (!empty($filtered_items)) {
                    $claim_stmt = $pdo->prepare("
                        INSERT INTO member_freebie_claims (member_id, member_name, branch, item_name, claimed_at, processed_by, is_printed) 
                        VALUES (?, ?, ?, ?, NOW(), ?, 1)
                        ON DUPLICATE KEY UPDATE 
                            member_name = VALUES(member_name),
                            branch = VALUES(branch),
                            claimed_at = NOW(),
                            processed_by = VALUES(processed_by),
                            is_printed = 1
                    ");
                    foreach ($filtered_items as $item_name) {
                        $claim_stmt->execute([$member_id, $member['full_name'], $member['branch_name'] ?? 'N/A', $item_name, $current_user_id]);
                    }

                    // Mark member as registered AND printed in members table specifically when printed on Terminal B
                    $update_att = $pdo->prepare("UPDATE members SET registered = 1, registered_at = COALESCE(registered_at, NOW()), printed = 1, printed_at = NOW() WHERE id = ?");
                    $update_att->execute([$member_id]);
                } else {
                    // If all freebies were unchecked during admin override, reset registered and printed status in members table
                    if ($admin_override === '1') {
                        $reset_stmt = $pdo->prepare("UPDATE members SET registered = 0, registered_at = NULL, printed = 0, printed_at = NULL WHERE id = ?");
                        $reset_stmt->execute([$member_id]);
                    }
                }

                $pdo->commit();
                $redirect = "index.php?route=gate&print_id=" . urlencode($member_id) . "&gate_print=1" . ($admin_override === '1' ? '&override=1' : '');
                if ($print_now === '1') { $redirect .= "&print_now=1"; }
                header("Location: " . $redirect);
                exit;
            } else {
                $error = "Member not found for print claim update.";
            }
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $error = "Print claim update error: " . $e->getMessage();
        }
    }
}

$search_results = [];
$search_query = trim($_GET['search'] ?? '');
if ($route === 'registration' && !empty($search_query) && !filter_var($search_query, FILTER_VALIDATE_URL)) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.* 
            FROM members m 
            WHERE m.id = ? 
               OR m.full_name LIKE ? 
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
        body { font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif; background: var(--bg-gradient); min-height: 100vh; margin: 0; padding: 36px 16px; color: var(--text-main); -webkit-font-smoothing: antialiased; }
        .container { max-width: 1020px; margin: 0 auto; background: var(--card-bg); padding: 36px; border-radius: 24px; box-shadow: 0 20px 45px -10px rgba(0,0,0,0.08); border: 1px solid var(--border-color); }
        .brand-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 28px; padding-bottom: 24px; border-bottom: 1px solid var(--border-color); }
        .brand-left { display: flex; align-items: center; gap: 16px; }
        .ui-logo { height: 48px; width: auto; object-fit: contain; }
        .brand-text h1 { margin: 0; font-size: 22px; color: var(--text-main); font-weight: 800; letter-spacing: -0.02em; }
        .brand-text p { margin: 3px 0 0 0; font-size: 13px; color: var(--text-muted); font-weight: 500; }
        .header-actions { display: flex; align-items: center; gap: 12px; }
        .btn-theme-toggle { background: var(--nav-bg); border: 1px solid var(--border-color); color: var(--text-main); padding: 9px 16px; border-radius: 30px; cursor: pointer; font-size: 13px; font-weight: 700; display: flex; align-items: center; gap: 8px; }
        .nav-tabs { display: flex; background: var(--nav-bg); padding: 6px; border-radius: 16px; margin-bottom: 24px; gap: 6px; border: 1px solid var(--border-color); }
        .nav-tabs a { flex: 1; text-align: center; padding: 14px 16px; text-decoration: none; color: var(--text-muted); font-weight: 700; font-size: 13px; border-radius: 12px; display: flex; align-items: center; justify-content: center; gap: 8px; }
        .nav-tabs a.active { color: #ffffff; background: var(--primary-gradient); box-shadow: 0 8px 16px -4px var(--primary-shadow); }
        .debug-bar { font-size: 13px; color: var(--text-muted); background: var(--nav-bg); padding: 12px 20px; border-radius: 12px; margin-bottom: 28px; border: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; font-weight: 500; }
        .logout-link { color: #ef4444; text-decoration: none; font-weight: 700; }
        .section-title-wrap { margin-bottom: 20px; }
        h2 { font-weight: 800; font-size: 20px; color: var(--text-main); margin: 0 0 4px 0; }
        .subtitle { color: var(--text-muted); font-size: 13px; margin: 0; font-weight: 500; }
        .alert { padding: 16px 20px; border-radius: 14px; margin-bottom: 28px; font-weight: 600; font-size: 14px; display: flex; align-items: center; gap: 12px; }
        .alert-danger { background: var(--danger-bg); color: var(--danger-text); border: 1px solid var(--danger-border); }
        .alert-success { background: var(--success-bg); color: var(--success-text); border: 1px solid var(--success-border); }
        .search-box-wrap { display: flex; gap: 12px; margin-bottom: 28px; }
        input[type="text"] { flex: 1; padding: 14px 18px; border: 1px solid var(--border-color); border-radius: 12px; font-size: 14px; background-color: var(--input-bg); color: var(--text-main); font-family: inherit; }
        input[type="text"]:focus { outline: none; border-color: #3b82f6; box-shadow: 0 0 0 4px var(--primary-shadow); }
        button, .btn-action { background: var(--primary-gradient); color: white; border: none; padding: 14px 28px; font-size: 14px; border-radius: 12px; cursor: pointer; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; font-family: inherit; box-shadow: 0 8px 16px -4px var(--primary-shadow); }
        .table-responsive { width: 100%; overflow-x: auto; border: 1px solid var(--border-color); border-radius: 14px; }
        table { width: 100%; border-collapse: collapse; text-align: left; }
        th, td { padding: 16px 20px; }
        th { background: var(--table-header); font-weight: 700; color: var(--text-muted); font-size: 11px; border-bottom: 1px solid var(--border-color); text-transform: uppercase; letter-spacing: 0.08em; }
        td { border-bottom: 1px solid var(--border-color); background: var(--card-bg); font-size: 14px; }
        .badge { padding: 6px 12px; border-radius: 30px; font-size: 11px; font-weight: 700; display: inline-flex; align-items: center; gap: 6px; }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-secondary { background: var(--input-bg); color: var(--text-muted); }
        .badge-warning { background: #fef3c7; color: #b45309; }
        .scanner-card { border: 1px solid var(--hud-border); background: var(--hud-bg); padding: 36px 28px; border-radius: 20px; text-align: center; margin-bottom: 24px; box-shadow: 0 20px 40px -15px rgba(0,0,0,0.3); }
        .scanner-card input[type="text"] { width: 100%; max-width: 500px; text-align: center; font-weight: 800; font-size: 20px; color: #ffffff; border: 2px solid #3b82f6; background: rgba(15, 23, 42, 0.8); letter-spacing: 0.08em; padding: 18px; border-radius: 14px; margin-bottom: 16px; }
        .claim-status-panel { margin-top: 24px; padding: 20px; border-radius: 18px; background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); }
        .claim-status-title { color: #f8fafc; font-size: 15px; font-weight: 800; margin-bottom: 16px; text-align: center; }
        .print-item-row { display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 16px 20px; border-radius: 14px; margin-bottom: 10px; cursor: pointer; border: 1px solid rgba(0,0,0,0.1); text-align: left; user-select: none; }
        .print-item-row.item-claimed { background: #d1fae5; color: #065f46; border-color: #a7f3d0; }
        .print-item-row.item-unclaimed { background: #fee2e2; color: #991b1b; border-color: #fca5a5; }
        .print-item-row input[type="checkbox"] { display: none; }
        .print-item-state { padding: 6px 14px; border-radius: 999px; font-size: 12px; font-weight: 800; min-width: 100px; text-align: center; }
        .print-item-row.item-claimed .print-item-state { background: #10b981; color: white; }
        .print-item-row.item-unclaimed .print-item-state { background: #ef4444; color: white; }
        .scanner-status { margin-top: 16px; font-size: 13px; color: var(--hud-text); display: flex; align-items: center; justify-content: center; gap: 10px; font-weight: 600; }
        .pulse-dot { width: 10px; height: 10px; background-color: #22c55e; border-radius: 50%; display: inline-block; animation: pulse 1.6s infinite; }
        @keyframes pulse { 0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.7); } 70% { transform: scale(1); box-shadow: 0 0 0 10px rgba(34, 197, 94, 0); } 100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(34, 197, 94, 0); } }
        #thermal-receipt-view { display: none; background: white !important; color: #000 !important; width: 80mm; padding: 15px; margin: 24px auto; border: 1px dashed #aaa; text-align: center; font-family: monospace; }
        .receipt-header { font-weight: bold; font-size: 15px; margin-bottom: 2px; text-transform: uppercase; color: #000 !important; text-align: center; }
        .receipt-divider { border-top: 1px dashed #000; margin: 10px 0; width: 100%; }
        .duplicate-notice { border: 2px solid #000; font-weight: bold; font-size: 12px; padding: 6px; margin: 8px auto; text-transform: uppercase; color: #000 !important; text-align: center; width: 100%; }
        .receipt-details-table { width: 100%; margin: 0 auto; text-align: left; font-size: 12px; line-height: 1.4; color: #000 !important; }
        .receipt-details-table td { padding: 3px 0; border: none; background: transparent !important; color: #000 !important; }
        .receipt-cred-section { margin: 8px 0; padding: 8px 0; border-top: 1px dashed #000; border-bottom: 1px dashed #000; }
        .receipt-cred-row { display: flex; justify-between; align-items: center; margin: 4px 0; font-size: 13px; }
        .receipt-cred-label { font-weight: bold; text-transform: uppercase; color: #000 !important; }
        .receipt-cred-value { font-weight: 800; font-size: 14px; font-family: monospace; letter-spacing: 0.5px; color: #000 !important; }
        .qr-wrapper { margin: 10px auto; text-align: center; }
        .qr-wrapper img { width: 130px; height: 130px; display: block; margin: 0 auto; }
        .freebie-container { width: 100%; margin: 8px auto; text-align: left; }
        .freebie-row { display: flex; align-items: center; margin-bottom: 6px; width: 100%; }
        .freebie-chk-box { width: 16px; height: 16px; min-width: 16px; border: 2px solid #000; margin-right: 10px; display: inline-block; position: relative; }
        .freebie-chk-box.checked { background: #000 !important; }
        .freebie-chk-box.checked::after { content: "✓"; color: #fff; font-size: 12px; position: absolute; top: -2px; left: 2px; font-weight: bold; }
        .freebie-label { font-size: 12px; font-weight: bold; color: #000 !important; font-family: sans-serif; }
        @media print {
            @page { margin: 0; size: 80mm auto; }
            body * { visibility: hidden !important; background: transparent !important; }
            #thermal-receipt-view, #thermal-receipt-view * { visibility: visible !important; color: #000 !important; }
            #thermal-receipt-view { display: flex !important; flex-direction: column !important; align-items: center !important; position: absolute !important; left: 0 !important; right: 0 !important; top: 0 !important; width: 80mm !important; margin: 0 auto !important; padding: 6mm !important; background: #ffffff !important; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>

<div class="container no-print">
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

    <div class="nav-tabs">
        <a href="index.php?route=registration" class="<?= $route === 'registration' ? 'active' : '' ?>">🏢 Terminal A: Central Registration</a>
        <a href="index.php?route=gate" class="<?= $route === 'gate' ? 'active' : '' ?>">🎁 Terminal B: Attendance & Freebies Gate</a>
    </div>

    <div class="debug-bar">
        <span>Active Operator: <strong><?= htmlspecialchars($current_username) ?></strong></span>
        <a href="index.php?action=logout" class="logout-link">End Session →</a>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><span style="font-size: 18px;">⚠️</span><div><?= $error ?></div></div>
    <?php endif; ?>

    <?php if (!empty($message)): ?>
        <div class="alert alert-success"><span style="font-size: 18px;">🎉</span><div><?= $message ?></div></div>
    <?php endif; ?>

    <?php if ($route === 'registration'): ?>
        <div class="section-title-wrap">
            <h2>Member Registration Window (Terminal A)</h2>
            <p class="subtitle">Search and process member pre-registration to generate gate passes.</p>
        </div>
        
        <form method="GET" action="index.php" class="search-box-wrap">
            <input type="hidden" name="route" value="registration">
            <input type="text" name="search" placeholder="Enter Member ID or Name..." value="<?= htmlspecialchars(!filter_var($search_query, FILTER_VALIDATE_URL) ? $search_query : '') ?>" autofocus>
            <button type="submit">Search Member</button>
        </form>

        <?php if (!empty($search_query) && !filter_var($search_query, FILTER_VALIDATE_URL)): ?>
            <div style="margin-top: 28px;">
                <h3 style="font-size: 14px; font-weight: 800; margin-bottom: 14px; color: var(--text-main);">Search Results (<?= count($search_results) ?> found)</h3>
                <?php if (count($search_results) > 0): ?>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th style="width: 120px;">ID</th>
                                    <th>Full Name</th>
                                    <th style="width: 130px;">Category</th>
                                    <th style="width: 140px;">Status</th>
                                    <th style="width: 280px; text-align: right;">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($search_results as $row): 
                                    $cat_display = !empty($row['migs_category']) ? strtoupper(trim($row['migs_category'])) : 'REGULAR';
                                    $is_non_migs = ($cat_display === 'NON-MIGS');
                                ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars('#' . $row['id']) ?></strong></td>
                                        <td style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($row['full_name']) ?></td>
                                        <td>
                                            <span class="badge <?= $is_non_migs ? 'badge-warning' : 'badge-secondary' ?>">
                                                <?= htmlspecialchars($cat_display) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($row['registered']): ?>
                                                <span class="badge badge-success">✓ Pre-Registered</span>
                                            <?php else: ?>
                                                <span class="badge badge-secondary">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td style="text-align: right;">
                                            <form method="POST" action="index.php?route=registration" style="margin:0;">
                                                <input type="hidden" name="action" value="register_member">
                                                <input type="hidden" name="member_id" value="<?= $row['id'] ?>">
                                                <input type="hidden" name="search_query_ref" value="<?= htmlspecialchars($search_query) ?>">
                                                
                                                <?php if (!$row['registered']): ?>
                                                    <div style="display:flex; flex-direction:column; align-items:flex-end; gap:10px;">
                                                        <div style="text-align:left; max-width: 260px; font-size: 12px; color: var(--text-muted);">
                                                            <strong style="display:block; margin-bottom: 6px;">Select Terminal A Pre-Registration Freebies:</strong>
                                                            <label style="display:inline-flex; align-items:center; gap:6px; margin-bottom: 4px;"><input type="checkbox" name="claimed_items[]" value="GA T-Shirt"> GA T-Shirt</label><br>
                                                            
                                                            <?php if ($is_non_migs): ?>
                                                                <label style="display:inline-flex; align-items:center; gap:6px; margin-bottom: 4px; opacity: 0.5; cursor: not-allowed;" title="Non-MIGS members are not eligible for cash allowance">
                                                                    <input type="checkbox" disabled> <span style="text-decoration: line-through;">Cash Allowance</span> <span style="color:#ef4444; font-size:10px; font-weight:bold;">(Non-MIGS Ineligible)</span>
                                                                </label><br>
                                                            <?php else: ?>
                                                                <label style="display:inline-flex; align-items:center; gap:6px; margin-bottom: 4px;"><input type="checkbox" name="claimed_items[]" value="Cash Allowance"> Cash Allowance</label><br>
                                                            <?php endif; ?>

                                                            <label style="display:inline-flex; align-items:center; gap:6px; margin-bottom: 4px;"><input type="checkbox" name="claimed_items[]" value="Snacks / Meals"> Snacks / Meals</label><br>
                                                            <label style="display:inline-flex; align-items:center; gap:6px; margin-bottom: 4px;"><input type="checkbox" name="claimed_items[]" value="PMPC Umbrella"> PMPC Umbrella</label>
                                                            <?php if (stripos($cat_display, 'GOLD') !== false): ?>
                                                            <br><label style="display:inline-flex; align-items:center; gap:6px;"><input type="checkbox" name="claimed_items[]" value="Water Bottle for Gold Members"> Water Bottle for Gold Members</label>
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

            <div class="scanner-status"><span class="pulse-dot"></span>Integrated Scanner Ready — Scan a member pass to proceed</div>

            <?php if (!empty($print_target_id)): ?>
                <?php
                    $gate_member = null;
                    $gate_claimed_items = [];
                    
                    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
                    $stmt->execute([$print_target_id]);
                    $gate_member = $stmt->fetch();

                    if ($gate_member) {
                        $gate_member_cat = strtoupper(trim((string)($gate_member['migs_category'] ?? '')));
                        $is_gate_non_migs = ($gate_member_cat === 'NON-MIGS');

                        // Exclude Cash Allowance if member is NON-MIGS
                        if ($is_gate_non_migs) {
                            $gate_freebie_items = ['GA T-Shirt', 'Snacks / Meals', 'PMPC Umbrella'];
                        } else {
                            $gate_freebie_items = ['GA T-Shirt', 'Cash Allowance', 'Snacks / Meals', 'PMPC Umbrella'];
                        }

                        if (stripos($gate_member_cat, 'GOLD') !== false) {
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
                                <div class="print-item-row <?= $item_claimed ? 'item-claimed' : 'item-unclaimed' ?>" data-claimed="<?= $item_claimed ? '1' : '0' ?>">
                                    <input type="checkbox" name="selected_items[]" value="<?= htmlspecialchars($item) ?>" <?= $item_claimed ? 'checked' : '' ?> />
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <span class="freebie-chk-box <?= $item_claimed ? 'checked' : '' ?>"></span>
                                        <span class="freebie-label" style="color: inherit !important; font-size: 14px;"><?= htmlspecialchars($item) ?></span>
                                    </div>
                                    <span class="print-item-state"><?= $item_claimed ? '✓ Claimed' : '✗ Unclaimed' ?></span>
                                </div>
                            <?php endforeach; ?>

                            <div style="display: flex; justify-content: center; gap: 12px; margin-top: 24px; flex-wrap: wrap;">
                                <button type="submit" id="gate-save-print-btn" style="padding: 14px 32px; background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; border-radius: 12px; font-weight: 800; font-size: 14px; border: none; cursor: pointer; box-shadow: 0 8px 16px -4px rgba(4, 120, 87, 0.4); display: inline-flex; align-items: center; gap: 8px;">
                                    💾 Save DB & Print Slip 🖨️
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

<?php 
if (!empty($print_target_id) && ($route !== 'gate' || ($_GET['gate_print'] ?? '') === '1')): 
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$print_target_id]);
    $print_member = $stmt->fetch();
    
    if ($print_member):
        $raw_category = $print_member['migs_category'] ?? '';
        $migs_category = strtoupper(trim((string)$raw_category));
        $is_non_migs_member = ($migs_category === 'NON-MIGS');
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
            <div class="duplicate-notice">⚠️ DUPLICATE COPY - SLIP ALREADY PRINTED</div>
        <?php endif; ?>

        <?php if ($route === 'registration'): ?>
            <div class="receipt-header">GENERAL ASSEMBLY</div>
            <div style="font-size: 11px; font-weight: bold; letter-spacing: 1px; text-align: center; color: #000;">PRE-REGISTRATION QR PASS</div>
            <div class="receipt-divider"></div>
            
            <table class="receipt-details-table">
                <tr><td style="width: 45%;"><strong>MEMBER ID:</strong></td><td>#<?= htmlspecialchars($print_member['id']) ?></td></tr>
                <tr><td><strong>NAME:</strong></td><td><?= htmlspecialchars($print_member['full_name']) ?></td></tr>
                <tr><td><strong>BRANCH:</strong></td><td><?= htmlspecialchars($branch_name) ?></td></tr>
                <tr><td><strong>CATEGORY:</strong></td><td><?= htmlspecialchars(!empty($migs_category) ? $migs_category : 'REGULAR') ?></td></tr>
            </table>
            
            <div class="receipt-divider"></div>
            <?php if (!empty($claimed_items_db)): ?>
                <div style="font-size: 11px; font-weight: 700; margin-bottom: 8px; text-align: left; width: 100%; color: #000;">Pre-Registration Freebies</div>
                <div class="freebie-container">
                    <?php foreach ($claimed_items_db as $item): ?>
                        <div class="freebie-row"><div class="freebie-chk-box checked"></div><div class="freebie-label"><?= htmlspecialchars($item) ?></div></div>
                    <?php endforeach; ?>
                </div>
                <div class="receipt-divider"></div>
            <?php else: ?>
                <div style="font-size: 10px; color: #000; margin-bottom: 10px; text-align: center;">No pre-registration freebies were selected.</div>
            <?php endif; ?>
            
            <div style="font-size: 9px; font-weight: bold; margin-bottom: 2px; text-align: center; color: #000;">PRESENT THIS AT TERMINAL B</div>
            <div class="qr-wrapper"><img src="<?= $gate_qr_file ?>?v=<?= time() ?>" alt="Entrance QR Pass"></div>
            <div style="font-size: 8px; color: #000; text-align: center;">Scan at Terminal B to verify entrance and claim freebies.</div>
        <?php else: ?>
            <div class="receipt-header">GENERAL ASSEMBLY</div>
            <div style="font-size: 11px; font-weight: bold; letter-spacing: 1px; text-align: center; color: #000;">
                <?= $is_duplicate ? 'ATTENDANCE RECEIPT (REPRINT)' : 'ATTENDANCE & FREEBIES CLAIMED' ?>
            </div>
            <div class="receipt-divider"></div>
            
            <table class="receipt-details-table">
                <tr><td style="width: 45%;"><strong>MEMBER ID:</strong></td><td>#<?= htmlspecialchars($print_member['id']) ?></td></tr>
                <tr><td><strong>NAME:</strong></td><td><?= htmlspecialchars($print_member['full_name']) ?></td></tr>
                <tr><td><strong>BRANCH:</strong></td><td><?= htmlspecialchars($branch_name) ?></td></tr>
                <tr><td><strong>CATEGORY:</strong></td><td><?= htmlspecialchars(!empty($migs_category) ? $migs_category : 'REGULAR') ?></td></tr>
            </table>

            <div class="receipt-cred-section">
                <div style="font-size: 10px; font-weight: bold; text-align: center; margin-bottom: 6px; letter-spacing: 1px; color: #000;">--- VOTING CREDENTIALS ---</div>
                <div class="receipt-cred-row">
                    <span class="receipt-cred-label">USERNAME:</span>
                    <span class="receipt-cred-value"><?= $is_non_migs_member ? 'NON-MIGS INELIGIBLE' : htmlspecialchars($print_member['username'] ?? 'N/A') ?></span>
                </div>
                <div class="receipt-cred-row">
                    <span class="receipt-cred-label">PASSWORD:</span>
                    <span class="receipt-cred-value"><?= $is_non_migs_member ? 'NON-MIGS INELIGIBLE' : htmlspecialchars($print_member['password'] ?? 'N/A') ?></span>
                </div>
            </div>

            <div style="font-size: 11px; font-weight: bold; margin-bottom: 4px; text-align: center; width: 100%; color: #000;">🎁 ISSUED FREEBIES & ALLOWANCE</div>

            <?php if (!empty($claimed_items_db)): ?>
                <div class="freebie-container">
                    <?php foreach ($claimed_items_db as $item): ?>
                        <div class="freebie-row"><div class="freebie-chk-box checked"></div><div class="freebie-label"><?= htmlspecialchars($item) ?></div></div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div style="font-size: 10px; color: #000; margin-bottom: 10px; text-align: center;">No freebies were recorded for this member.</div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>
<?php endif; ?>

<?php if (!empty($print_target_id) && ($route !== 'gate' || ($_GET['gate_print'] ?? '') === '1')): ?>
<script>
    window.addEventListener('load', function() {
        <?php if ($route !== 'gate' || ($_GET['print_now'] ?? '') === '1'): ?>
        setTimeout(function() { window.print(); }, 400);
        <?php endif; ?>
    });
</script>
<?php endif; ?>

<script>
    const ADMIN_AUTHORIZATION_KEY = "ADMIN123";
    let isAdminUnlocked = false;

    function requestAdminOverride() {
        const inputKey = prompt("ADMIN OVERRIDE REQUIRED:\nThis print slip was already generated/printed.\nEnter Admin Authorization Key to save modifications:");
        if (inputKey === null) return false;
        if (inputKey === ADMIN_AUTHORIZATION_KEY) {
            isAdminUnlocked = true;
            document.getElementById('admin_override_input').value = '1';
            alert('Admin Authorization Granted! Claim updates allowed.');
            return true;
        } else {
            alert('ERROR: Invalid Authorization Key. Action cancelled.');
            return false;
        }
    }

    document.addEventListener("DOMContentLoaded", function() {
        const savedScroll = sessionStorage.getItem("portal_scroll_pos");
        if (savedScroll !== null) {
            window.scrollTo(0, parseInt(savedScroll));
            sessionStorage.removeItem("portal_scroll_pos");
        }

        const gatePrintForm = document.getElementById('gate-print-form');
        if (gatePrintForm) {
            gatePrintForm.addEventListener('submit', function(e) {
                const isPrintedAlready = <?= json_encode($is_duplicate) ?>;
                if (isPrintedAlready && !isAdminUnlocked) {
                    const authorized = requestAdminOverride();
                    if (!authorized) {
                        e.preventDefault();
                    }
                }
            });
        }
    });

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

    if (scanInput) {
        scanInput.focus({ preventScroll: true });
        document.addEventListener('click', function(e) {
            const isClickInsideInteractive = e.target.closest('input, button, a, label, .print-item-row, .claim-status-panel');
            if (!isClickInsideInteractive) {
                scanInput.focus({ preventScroll: true });
            }
        });
    }

    const printItemRows = document.querySelectorAll('.print-item-row');
    printItemRows.forEach((row) => {
        const checkbox = row.querySelector('input[type="checkbox"]');
        const stateLabel = row.querySelector('.print-item-state');
        const checkBoxBox = row.querySelector('.freebie-chk-box');

        row.addEventListener('click', function(e) {
            e.preventDefault();
            if (!checkbox) return;

            const isPrintedAlready = <?= json_encode($is_duplicate) ?>;

            // Require admin key ONLY if the print slip has already been printed
            if (isPrintedAlready && !isAdminUnlocked) {
                const wantOverride = confirm("LOCKED ITEM: This slip has already been printed.\n\nWould you like to enter the Admin Key to make modifications?");
                if (wantOverride) {
                    requestAdminOverride();
                }
                return;
            }

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