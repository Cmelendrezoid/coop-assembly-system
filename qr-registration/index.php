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
                $error = "Invalid username or password credentials.";
            }
        } catch (PDOException $e) {
            $error = "Authentication system error: " . $e->getMessage();
        }
    } else {
        $error = "Please fill in both username and password fields.";
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
        <title>Login - GA Registration Portal</title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            :root {
                --bg-gradient: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
                --card-bg: rgba(255, 255, 255, 0.9);
                --text-main: #0f172a;
                --text-muted: #64748b;
                --border-color: #cbd5e1;
                --input-bg: #ffffff;
                --primary-gradient: linear-gradient(135deg, #2563eb 0%, #1d4ed8 100%);
                --primary-shadow: rgba(37, 99, 235, 0.25);
            }

            [data-theme="dark"] {
                --bg-gradient: linear-gradient(135deg, #0b0f19 0%, #111827 100%);
                --card-bg: rgba(30, 41, 59, 0.85);
                --text-main: #f8fafc;
                --text-muted: #94a3b8;
                --border-color: #334155;
                --input-bg: #0f172a;
                --primary-gradient: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
                --primary-shadow: rgba(59, 130, 246, 0.3);
            }

            * { box-sizing: border-box; transition: all 0.25s ease; }
            
            body {
                font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
                background: var(--bg-gradient);
                color: var(--text-main);
                display: flex;
                align-items: center;
                justify-content: center;
                min-height: 100vh;
                margin: 0;
                padding: 20px;
                -webkit-font-smoothing: antialiased;
            }

            .theme-toggle-login {
                position: fixed;
                top: 24px;
                right: 24px;
                background: var(--card-bg);
                border: 1px solid var(--border-color);
                color: var(--text-main);
                padding: 10px 18px;
                border-radius: 30px;
                cursor: pointer;
                font-size: 13px;
                font-weight: 700;
                display: flex;
                align-items: center;
                gap: 8px;
                backdrop-filter: blur(10px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            }

            .btn-back {
                position: fixed;
                top: 24px;
                left: 24px;
                border: 1px solid var(--border-color);
                background: var(--card-bg);
                color: var(--text-main);
                padding: 10px 18px;
                border-radius: 10px;
                font-weight: 700;
                font-size: 13px;
                text-decoration: none;
                backdrop-filter: blur(10px);
                box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            }

            .login-card {
                background: var(--card-bg);
                backdrop-filter: blur(16px);
                padding: 44px 36px;
                border-radius: 24px;
                box-shadow: 0 20px 40px -15px rgba(0,0,0,0.12);
                border: 1px solid var(--border-color);
                width: 100%;
                max-width: 420px;
            }

            .brand-header-login {
                text-align: center;
                margin-bottom: 32px;
            }

            .login-logo {
                height: 60px;
                width: auto;
                margin-bottom: 16px;
                object-fit: contain;
            }

            .login-card h2 {
                margin: 0 0 8px 0;
                font-size: 24px;
                font-weight: 800;
                letter-spacing: -0.02em;
            }

            .login-card p {
                color: var(--text-muted);
                font-size: 13px;
                margin: 0;
            }

            .form-group { margin-bottom: 22px; }

            label {
                display: block;
                font-size: 12px;
                font-weight: 700;
                margin-bottom: 8px;
                text-transform: uppercase;
                letter-spacing: 0.05em;
                color: var(--text-muted);
            }

            input[type="text"], input[type="password"] {
                width: 100%;
                padding: 14px 18px;
                border: 1px solid var(--border-color);
                border-radius: 12px;
                font-size: 14px;
                background: var(--input-bg);
                color: var(--text-main);
                font-family: inherit;
            }

            input[type="text"]:focus, input[type="password"]:focus {
                outline: none;
                border-color: #3b82f6;
                box-shadow: 0 0 0 4px var(--primary-shadow);
            }

            button {
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
            }

            button:hover {
                transform: translateY(-2px);
                box-shadow: 0 12px 20px -4px var(--primary-shadow);
            }

            .alert {
                background: #fef2f2;
                color: #991b1b;
                border: 1px solid #fee2e2;
                padding: 14px 18px;
                border-radius: 12px;
                font-size: 13px;
                margin-bottom: 22px;
            }
        </style>
    </head>
    <body>
        <button class="theme-toggle-login" id="theme-toggle">🌙 Dark Mode</button>
        <a href="../index.php" class="btn-back">← Back</a>
        
        <div class="login-card">
            <div class="brand-header-login">
                <img src="/evoting/assets/images/logo.png" class="login-logo" alt="Company Logo" onerror="this.style.display='none'">
                <h2>System Sign In</h2>
                <p>Authorized terminal operator credentials required</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form method="POST" action="index.php">
                <input type="hidden" name="action" value="login">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" required autofocus placeholder="Enter username">
                </div>
                <div class="form-group">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                <button type="submit">Sign In to Terminal</button>
            </form>
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

$current_user_id = $_SESSION['qr_user_id']; 
$current_username = $_SESSION['qr_username'];

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
            'Claimed Freebie Item', 
            'Claim Date & Time', 
            'Processed By'
        ]);
        
        foreach ($claims as $row) {
            fputcsv($output, [
                $row['member_id'],
                $row['full_name'],
                $row['category'],
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

// Terminal B: Real-time attendance endpoint
if (isset($_GET['action']) && $_GET['action'] === 'get_attendance_data') {
    header('Content-Type: text/html; charset=utf-8');
    try {
        $filter_date = $_GET['filter_date'] ?? '';
        
        if (!empty($filter_date)) {
            $recent_arrivals = $pdo->query("
                SELECT m.id, m.full_name, m.migs_category, m.allowance_claimed_at,
                GROUP_CONCAT(c.item_name SEPARATOR ', ') AS items_claimed
                FROM members m
                LEFT JOIN member_freebie_claims c ON m.id = c.member_id
                WHERE m.allowance_claimed = 1 AND DATE(m.allowance_claimed_at) = '" . $pdo->quote($filter_date) . "'
                GROUP BY m.id
                ORDER BY m.allowance_claimed_at DESC 
                LIMIT 20
            ")->fetchAll();
        } else {
            $recent_arrivals = $pdo->query("
                SELECT m.id, m.full_name, m.migs_category, m.allowance_claimed_at,
                GROUP_CONCAT(c.item_name SEPARATOR ', ') AS items_claimed
                FROM members m
                LEFT JOIN member_freebie_claims c ON m.id = c.member_id
                WHERE m.allowance_claimed = 1
                GROUP BY m.id
                ORDER BY m.allowance_claimed_at DESC 
                LIMIT 20
            ")->fetchAll();
        }
        
        if ($recent_arrivals):
            foreach ($recent_arrivals as $arrival):
                echo '<tr>';
                echo '<td><strong>#' . htmlspecialchars($arrival['id']) . '</strong></td>';
                echo '<td style="font-weight: 700; color: var(--text-main);">' . htmlspecialchars($arrival['full_name']) . '</td>';
                echo '<td><span class="badge badge-success">' . htmlspecialchars(!empty($arrival['migs_category']) ? $arrival['migs_category'] : 'REGULAR') . '</span></td>';
                echo '<td><span class="badge badge-success">✓ Claimed & Attended</span><br><small style="color: var(--text-muted);">' . htmlspecialchars($arrival['items_claimed'] ?: 'No items selected') . '</small></td>';
                echo '<td style="font-size: 12px; color: var(--text-muted);">' . date('Y-m-d H:i:s', strtotime($arrival['allowance_claimed_at'])) . '</td>';
                echo '</tr>';
            endforeach;
        else:
            echo '<tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No attendance & freebie claims recorded yet.</td></tr>';
        endif;
    } catch (PDOException $e) {
        echo "<tr><td colspan='5' style='color: #991b1b;'>Error: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
    }
    exit;
}

// Terminal B: Statistics calculation
if ($route === 'gate') {
    try {
        $filter_date = $_GET['filter_date'] ?? '';
        
        if (!empty($filter_date)) {
            $total_arrivals = $pdo->query("SELECT COUNT(*) as total FROM members WHERE allowance_claimed = 1 AND DATE(allowance_claimed_at) = '" . $pdo->quote($filter_date) . "'")->fetch();
            $by_category = $pdo->query("
                SELECT COALESCE(migs_category, 'REGULAR') as category, COUNT(*) as count 
                FROM members 
                WHERE allowance_claimed = 1 AND DATE(allowance_claimed_at) = '" . $pdo->quote($filter_date) . "'
                GROUP BY migs_category 
                ORDER BY count DESC
            ")->fetchAll();
            $filter_label = ' (on ' . date('M d, Y', strtotime($filter_date)) . ')';
        } else {
            $total_arrivals = $pdo->query("SELECT COUNT(*) as total FROM members WHERE allowance_claimed = 1")->fetch();
            $today_arrivals = $pdo->query("SELECT COUNT(*) as total FROM members WHERE allowance_claimed = 1 AND DATE(allowance_claimed_at) = DATE(NOW())")->fetch();
            $by_category = $pdo->query("
                SELECT COALESCE(migs_category, 'REGULAR') as category, COUNT(*) as count 
                FROM members 
                WHERE allowance_claimed = 1 
                GROUP BY migs_category 
                ORDER BY count DESC
            ")->fetchAll();
            $filter_label = '';
        }
    } catch (PDOException $e) {
        $total_arrivals = ['total' => 0];
        $today_arrivals = ['total' => 0];
        $by_category = [];
        $filter_label = '';
    }
}

// Terminal A Action: Pre-Registration
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register_member') {
    $member_id = $_POST['member_id'] ?? '';
    if (!empty($member_id)) {
        try {
            $check = $pdo->prepare("SELECT registered FROM members WHERE id = ?");
            $check->execute([$member_id]);
            $is_already_reg = $check->fetchColumn();

            $stmt = $pdo->prepare("UPDATE members SET registered = 1, registered_at = NOW(), registered_by = ? WHERE id = ?");
            $stmt->execute([$current_user_id, $member_id]);
            
            header("Location: index.php?route=registration&print_id=" . urlencode($member_id) . "&duplicate=" . ($is_already_reg ? '1' : '0') . "&search=" . urlencode($_GET['search'] ?? ''));
            exit;
        } catch (PDOException $e) {
            $error = "Registration failed: " . $e->getMessage();
        }
    }
}

// TERMINAL B: Scan Pre-Registration QR Code & Claim Freebies (ALLOWS REPRINT, TAGS DUPLICATE)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'gate_scan') {
    $scan_input = trim($_POST['scan_input'] ?? '');
    $selected_items = $_POST['claimed_items'] ?? [];
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
                    // Check 1: Member must be pre-registered
                    if ($member['registered'] == 0) {
                        $error = "Access Denied: Member ID #{$scanned_id} (" . htmlspecialchars($member['full_name']) . ") has NOT pre-registered at Terminal A yet!";
                    } 
                    else {
                        $is_already_claimed = ($member['allowance_claimed'] == 1);

                        $pdo->beginTransaction();

                        // Mark attendance and allowance claim flag if first time
                        if (!$is_already_claimed) {
                            $update = $pdo->prepare("UPDATE members SET allowance_claimed = 1, allowance_claimed_at = NOW(), allowance_processed_by = ? WHERE id = ?");
                            $update->execute([$current_user_id, $scanned_id]);
                        }

                        // Record freebies claimed while preventing duplicate item database entries
                        if (!empty($selected_items) && is_array($selected_items) && !$is_already_claimed) {
                            $check_item_stmt = $pdo->prepare("SELECT COUNT(*) FROM member_freebie_claims WHERE member_id = ? AND item_name = ?");
                            $claim_stmt = $pdo->prepare("
                                INSERT INTO member_freebie_claims (member_id, item_name, claimed_at, processed_by) 
                                VALUES (?, ?, NOW(), ?)
                            ");

                            foreach ($selected_items as $item_name) {
                                $trimmed_item = trim($item_name);
                                
                                // Prevent item duplication in DB
                                $check_item_stmt->execute([$scanned_id, $trimmed_item]);
                                $already_exists = $check_item_stmt->fetchColumn();

                                if ($already_exists == 0) {
                                    $claim_stmt->execute([$scanned_id, $trimmed_item, $current_user_id]);
                                }
                            }
                        }

                        $pdo->commit();

                        // Pass duplicate tag if they had already claimed previously
                        header("Location: index.php?route=gate&print_id=" . urlencode($scanned_id) . "&duplicate=" . ($is_already_claimed ? '1' : '0'));
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

        /* Header Bar */
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
        .btn-theme-toggle:hover {
            transform: scale(1.03);
        }

        /* Navigation Tabs */
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

        /* Operator Info Bar */
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
        .logout-link { 
            color: #ef4444; 
            text-decoration: none; 
            font-weight: 700; 
        }
        .logout-link:hover { color: #dc2626; }

        /* Titles */
        .section-title-wrap { margin-bottom: 20px; }
        h2 { 
            font-weight: 800; 
            font-size: 20px; 
            color: var(--text-main); 
            margin: 0 0 4px 0; 
            letter-spacing: -0.01em;
        }
        .subtitle { 
            color: var(--text-muted); 
            font-size: 13px; 
            margin: 0; 
            font-weight: 500;
        }

        /* Feedback Banner Cards */
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
        .alert-danger { 
            background: var(--danger-bg); 
            color: var(--danger-text); 
            border: 1px solid var(--danger-border); 
        }
        .alert-success { 
            background: var(--success-bg); 
            color: var(--success-text); 
            border: 1px solid var(--success-border); 
        }
        .alert-warning {
            background: #fffbe3;
            color: #b45309;
            border: 1px solid #fde68a;
        }

        /* Search Section */
        .search-box-wrap {
            display: flex;
            gap: 12px;
            margin-bottom: 28px;
        }
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
        input[type="text"]:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 4px var(--primary-shadow);
        }
        
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
        button:hover, .btn-action:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px -4px var(--primary-shadow);
        }

        /* Data Tables */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
            border: 1px solid var(--border-color);
            border-radius: 14px;
        }
        table { 
            width: 100%; 
            border-collapse: collapse; 
            text-align: left;
        }
        th, td { padding: 16px 20px; }
        th { 
            background: var(--table-header); 
            font-weight: 700; 
            color: var(--text-muted); 
            font-size: 11px; 
            border-bottom: 1px solid var(--border-color); 
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }
        td { 
            border-bottom: 1px solid var(--border-color); 
            background: var(--card-bg); 
            font-size: 14px; 
        }
        tr:last-child td { border-bottom: none; }

        .badge { 
            padding: 6px 12px; 
            border-radius: 30px; 
            font-size: 11px; 
            font-weight: 700; 
            display: inline-flex;
            align-items: center;
            gap: 6px;
            letter-spacing: 0.02em;
        }
        .badge-success { background: #dcfce7; color: #15803d; }
        .badge-secondary { background: var(--input-bg); color: var(--text-muted); }

        /* Gate Scanner HUD */
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
            width: 100%;
            max-width: 500px;
            text-align: center; 
            font-weight: 800; 
            font-size: 20px; 
            color: #ffffff; 
            border: 2px solid #3b82f6;
            background: rgba(15, 23, 42, 0.8);
            letter-spacing: 0.08em;
            padding: 18px;
            border-radius: 14px;
            box-shadow: 0 0 20px rgba(59, 130, 246, 0.2);
            margin-bottom: 16px;
        }
        .scanner-card input[type="text"]:focus {
            outline: none;
            box-shadow: 0 0 30px rgba(59, 130, 246, 0.4);
        }
        
        /* Checkboxes inside Scanner Panel */
        .claim-selector-box {
            background: rgba(15, 23, 42, 0.6);
            border: 1px solid var(--hud-border);
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 24px;
            text-align: left;
        }
        .claim-selector-title {
            color: #94a3b8;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 14px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .claim-options-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 12px;
        }
        .claim-option-item {
            display: flex;
            align-items: center;
            gap: 10px;
            background: rgba(30, 41, 59, 0.8);
            padding: 12px 16px;
            border-radius: 10px;
            border: 1px solid #334155;
            cursor: pointer;
            user-select: none;
        }
        .claim-option-item input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #3b82f6;
            cursor: pointer;
        }
        .claim-option-item span {
            color: #f8fafc;
            font-size: 13px;
            font-weight: 600;
        }

        .scanner-status {
            margin-top: 16px;
            font-size: 13px;
            color: var(--hud-text);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 600;
            letter-spacing: 0.03em;
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

        /* Thermal Receipt View */
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
        
        .credential-box { background: #f1f5f9 !important; border: 1px solid #cbd5e1; padding: 10px; margin: 8px auto; border-radius: 4px; text-align: left; font-family: monospace; font-size: 12px; color: #000 !important; width: 100%; box-sizing: border-box; }
        
        .freebie-container { width: 100%; margin: 8px auto; padding: 0; text-align: left; box-sizing: border-box; }
        .freebie-row { display: flex; align-items: center; margin-bottom: 6px; width: 100%; }
        
        .freebie-chk-box { width: 16px; height: 16px; min-width: 16px; min-height: 16px; border: 2px solid #000; margin-right: 10px; display: inline-block; box-sizing: border-box; position: relative; }
        .freebie-chk-box.checked { background: #000 !important; }
        .freebie-chk-box.checked::after { content: "✓"; color: #fff; font-size: 12px; position: absolute; top: -2px; left: 2px; font-weight: bold; }
        
        .freebie-label { font-size: 12px; font-weight: bold; color: #000 !important; font-family: sans-serif; line-height: 1.2; display: inline-block; }

        /* Print Layout Rules */
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

    <?php if ($print_target_id && $route === 'gate'): ?>
        <?php if ($is_duplicate): ?>
            <div class="alert alert-warning">
                <span style="font-size: 18px;">⚠️</span>
                <div><strong>REPRINT NOTICE:</strong> Member #<?= htmlspecialchars($print_target_id) ?> has ALREADY claimed previously. A receipt copy tagged <strong>DUPLICATE / ALREADY CLAIMED</strong> has been printed with freebies section omitted.</div>
            </div>
        <?php else: ?>
            <div class="alert alert-success">
                <span style="font-size: 18px;">🎉</span>
                <div><strong>SUCCESS:</strong> Attendance & Freebies Logged! Claim stub and e-voting credentials sent to thermal printer.</div>
            </div>
        <?php endif; ?>
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
                                    <th style="width: 180px; text-align: right;">Action</th>
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
                                                    <button type="submit" style="padding: 10px 16px; font-size: 12px;">Confirm & Print Pass</button>
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
            <p class="subtitle">Select items being claimed, then scan member pass to log attendance and issue freebies.</p>
        </div>

        <div class="scanner-card">
            <form method="POST" action="index.php?route=gate" id="gate-scan-form">
                <input type="hidden" name="action" value="gate_scan">
                
                <!-- PRE-SCAN ITEM SELECTION PANEL -->
                <div class="claim-selector-box">
                    <div class="claim-selector-title">
                        <span>📋 Select Items To Be Claimed Upon Scanning:</span>
                        <span id="btn-toggle-all" style="font-size: 11px; text-transform: none; color: #3b82f6; cursor: pointer;" onclick="toggleAllFreebies(this)">Toggle All</span>
                    </div>
                    <div class="claim-options-grid">
                        <label class="claim-option-item">
                            <input type="checkbox" name="claimed_items[]" value="GA T-Shirt" class="item-chk">
                            <span>👕 GA T-Shirt</span>
                        </label>
                        <label class="claim-option-item">
                            <input type="checkbox" name="claimed_items[]" value="Cash Allowance" class="item-chk">
                            <span>💵 Cash Allowance</span>
                        </label>
                        <label class="claim-option-item">
                            <input type="checkbox" name="claimed_items[]" value="Snacks / Meals" class="item-chk">
                            <span>🍱 Snacks / Meals</span>
                        </label>
                        <label class="claim-option-item">
                            <input type="checkbox" name="claimed_items[]" value="PMPC Umbrella" class="item-chk">
                            <span>☂️ PMPC Umbrella</span>
                        </label>
                        <label class="claim-option-item">
                            <input type="checkbox" name="claimed_items[]" value="Water Bottle for Gold Members" class="item-chk">
                            <span>🍾 Gold Water Bottle</span>
                        </label>
                    </div>
                </div>

                <input type="text" name="scan_input" id="gate-scan-input" placeholder="SCAN MEMBER QR PASS HERE" autocomplete="off" autofocus>
                <button type="submit" style="display: block; margin: 0 auto; max-width: 500px; width: 100%;">Process Manual Entry / Submit Scan</button>
            </form>

            <div class="scanner-status">
                <span class="pulse-dot"></span>
                Integrated Scanner Ready — Reprints allowed with duplicate warnings
            </div>
        </div>

        <!-- Attendance Stats Section with Summary Export -->
        <div style="background: var(--card-bg); padding: 24px; border-radius: 14px; border: 1px solid var(--border-color); margin-top: 28px; margin-bottom: 28px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <h3 style="margin: 0; color: var(--text-main); font-size: 16px; font-weight: 800;">📊 Attendance & Claims Statistics<?= $filter_label ?></h3>
                <a href="index.php?route=gate&action=export_attendance_summary" style="background: linear-gradient(135deg, #059669 0%, #047857 100%); color: white; padding: 10px 18px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(5, 150, 105, 0.2);">
                    📊 Export Attendance Summary
                </a>
            </div>
            
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px; margin-bottom: 24px;">
                <div style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);">
                    <div style="font-size: 28px; font-weight: 800; margin-bottom: 6px;"><?= $total_arrivals['total'] ?? 0 ?></div>
                    <div style="font-size: 12px; font-weight: 600; opacity: 0.9;">Total Attendees Arrived</div>
                </div>
                
                <?php if (empty($_GET['filter_date'])): ?>
                <div style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);">
                    <div style="font-size: 28px; font-weight: 800; margin-bottom: 6px;"><?= $today_arrivals['total'] ?? 0 ?></div>
                    <div style="font-size: 12px; font-weight: 600; opacity: 0.9;">Today's Arrivals</div>
                </div>
                <?php endif; ?>

                <?php if (!empty($by_category)): ?>
                    <?php foreach ($by_category as $cat): ?>
                    <div style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); color: white; padding: 20px; border-radius: 12px; text-align: center; box-shadow: 0 4px 12px rgba(245, 158, 11, 0.2);">
                        <div style="font-size: 28px; font-weight: 800; margin-bottom: 6px;"><?= $cat['count'] ?></div>
                        <div style="font-size: 12px; font-weight: 600; opacity: 0.9;"><?= htmlspecialchars($cat['category']) ?></div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div style="background: var(--input-bg); padding: 16px; border-radius: 12px; border: 1px solid var(--border-color); margin-bottom: 8px;">
                <label for="date-filter" style="font-size: 12px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.05em; display: block; margin-bottom: 8px;">Filter Statistics By Date</label>
                <div style="display: flex; gap: 10px;">
                    <input type="date" id="date-filter" value="<?= $_GET['filter_date'] ?? '' ?>" style="flex: 1; padding: 10px 14px; border: 1px solid var(--border-color); border-radius: 8px; font-size: 13px; background: var(--card-bg); color: var(--text-main); font-family: inherit;">
                    <button type="button" onclick="filterByDate()" style="padding: 10px 18px; background: var(--primary-gradient); color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 12px;">Filter</button>
                    <button type="button" onclick="clearDateFilter()" style="padding: 10px 18px; background: var(--nav-bg); color: var(--text-main); border: 1px solid var(--border-color); border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 12px;">All Time</button>
                </div>
            </div>
        </div>

        <!-- Attendance & Claim Table with Detailed Excel Export -->
        <div style="background: var(--card-bg); padding: 24px; border-radius: 14px; border: 1px solid var(--border-color); margin-bottom: 28px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3 style="margin: 0; color: var(--text-main); font-size: 16px; font-weight: 800;">Live Attendance & Claim Log</h3>
                <a href="index.php?route=gate&action=export_freebies" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%); color: white; padding: 10px 18px; border-radius: 10px; text-decoration: none; font-weight: 700; font-size: 12px; display: inline-flex; align-items: center; gap: 8px; box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);">
                    📥 Export Freebies Claimants Excel
                </a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Member ID</th>
                            <th>Member Name</th>
                            <th>Category</th>
                            <th>Freebies & Claimed Items</th>
                            <th>Arrival Time</th>
                        </tr>
                    </thead>
                    <tbody id="attendance-table-body">
                        <?php 
                        try {
                            $filter_date = $_GET['filter_date'] ?? '';
                            
                            if (!empty($filter_date)) {
                                $recent_arrivals = $pdo->query("
                                    SELECT m.id, m.full_name, m.migs_category, m.allowance_claimed_at,
                                    GROUP_CONCAT(c.item_name SEPARATOR ', ') AS items_claimed
                                    FROM members m
                                    LEFT JOIN member_freebie_claims c ON m.id = c.member_id
                                    WHERE m.allowance_claimed = 1 AND DATE(m.allowance_claimed_at) = '" . $pdo->quote($filter_date) . "'
                                    GROUP BY m.id
                                    ORDER BY m.allowance_claimed_at DESC 
                                    LIMIT 20
                                ")->fetchAll();
                            } else {
                                $recent_arrivals = $pdo->query("
                                    SELECT m.id, m.full_name, m.migs_category, m.allowance_claimed_at,
                                    GROUP_CONCAT(c.item_name SEPARATOR ', ') AS items_claimed
                                    FROM members m
                                    LEFT JOIN member_freebie_claims c ON m.id = c.member_id
                                    WHERE m.allowance_claimed = 1
                                    GROUP BY m.id
                                    ORDER BY m.allowance_claimed_at DESC 
                                    LIMIT 20
                                ")->fetchAll();
                            }
                            
                            if ($recent_arrivals):
                                foreach ($recent_arrivals as $arrival):
                        ?>
                            <tr>
                                <td><strong>#<?= $arrival['id'] ?></strong></td>
                                <td style="font-weight: 700; color: var(--text-main);"><?= htmlspecialchars($arrival['full_name']) ?></td>
                                <td><span class="badge badge-success"><?= htmlspecialchars(!empty($arrival['migs_category']) ? $arrival['migs_category'] : 'REGULAR') ?></span></td>
                                <td><span class="badge badge-success">✓ Claimed & Attended</span><br><small style="color: var(--text-muted);"><?= htmlspecialchars($arrival['items_claimed'] ?: 'No items selected') ?></small></td>
                                <td style="font-size: 12px; color: var(--text-muted);"><?= date('Y-m-d H:i:s', strtotime($arrival['allowance_claimed_at'])) ?></td>
                            </tr>
                        <?php 
                                endforeach;
                            else:
                        ?>
                            <tr><td colspan="5" style="text-align: center; color: var(--text-muted);">No arrivals or freebie claims recorded yet.</td></tr>
                        <?php 
                            endif;
                        } catch (PDOException $e) {
                            echo "<tr><td colspan='5' style='color: #991b1b;'>Error fetching attendees: " . htmlspecialchars($e->getMessage()) . "</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <script>
            const scanInput = document.getElementById('gate-scan-input');
            const scanForm = document.getElementById('gate-scan-form');
            const chks = document.querySelectorAll('.item-chk');

            function saveFreebieStates() {
                const states = {};
                chks.forEach(chk => {
                    states[chk.value] = chk.checked;
                });
                localStorage.setItem('freebie_item_states', JSON.stringify(states));
                updateToggleAllButtonText();
            }

            function loadFreebieStates() {
                const saved = localStorage.getItem('freebie_item_states');
                if (saved !== null) {
                    try {
                        const states = JSON.parse(saved);
                        chks.forEach(chk => {
                            if (states.hasOwnProperty(chk.value)) {
                                chk.checked = states[chk.value];
                            }
                        });
                    } catch(e) {
                        console.error('Failed to parse saved freebie states', e);
                    }
                } else {
                    chks.forEach(chk => chk.checked = true);
                }
                updateToggleAllButtonText();
            }

            function updateToggleAllButtonText() {
                const btn = document.getElementById('btn-toggle-all');
                if (!btn) return;
                const isAllChecked = Array.from(chks).every(c => c.checked);
                btn.textContent = isAllChecked ? 'Uncheck All' : 'Check All';
            }

            function toggleAllFreebies(el) {
                const isAllChecked = Array.from(chks).every(c => c.checked);
                chks.forEach(c => c.checked = !isAllChecked);
                saveFreebieStates();
                if (scanInput) scanInput.focus();
            }

            loadFreebieStates();

            chks.forEach(chk => {
                chk.addEventListener('change', function() {
                    saveFreebieStates();
                    if (scanInput) scanInput.focus();
                });
                chk.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        if (scanInput) scanInput.focus();
                    }
                });
            });

            if (scanForm) {
                scanForm.addEventListener('submit', function(e) {
                    if (!scanInput || scanInput.value.trim() === '') {
                        e.preventDefault();
                        if (scanInput) scanInput.focus();
                    }
                });
            }

            if (scanInput) {
                scanInput.focus();
                
                document.addEventListener('click', function(e) {
                    if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'BUTTON' && e.target.tagName !== 'A' && !e.target.classList.contains('item-chk')) {
                        scanInput.focus();
                    }
                });
            }
        </script>
    <?php endif; ?>
</div>

<!-- ========================================================================= -->
<!-- 3. RUNTIME THERMAL PRINT RUN STAGE                                        -->
<!-- ========================================================================= -->
<?php 
if (!empty($print_target_id)): 
    $stmt = $pdo->prepare("SELECT * FROM members WHERE id = ?");
    $stmt->execute([$print_target_id]);
    $print_member = $stmt->fetch();
    
    if ($print_member):
        $raw_category = $print_member['migs_category'] ?? $print_member['category'] ?? $print_member['migs_status'] ?? '';
        $migs_category = strtoupper(trim((string)$raw_category));

        $claims_stmt = $pdo->prepare("SELECT item_name FROM member_freebie_claims WHERE member_id = ?");
        $claims_stmt->execute([$print_target_id]);
        $claimed_items_db = $claims_stmt->fetchAll(PDO::FETCH_COLUMN);

        $gate_qr_url = "gate_id=" . $print_member['id']; 
        $gate_qr_file = 'temp_qr_gate_' . $print_member['id'] . '.png';
        QRcode::png($gate_qr_url, $gate_qr_file, QR_ECLEVEL_M, 4, 2);
?>
    <div id="thermal-receipt-view">
        
        <?php if ($is_duplicate): ?>
            <div class="duplicate-notice">⚠️ DUPLICATE COPY - ALREADY CLAIMED</div>
        <?php endif; ?>

        <?php if ($route === 'registration'): ?>
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
                    <td><strong>CATEGORY:</strong></td>
                    <td><?= htmlspecialchars(!empty($migs_category) ? $migs_category : 'REGULAR') ?></td>
                </tr>
            </table>
            
            <div class="receipt-divider"></div>
            <div style="font-size: 9px; font-weight: bold; margin-bottom: 2px; text-align: center; color: #000;">PRESENT THIS AT TERMINAL B</div>
            
            <div class="qr-wrapper">
                <img src="<?= $gate_qr_file ?>?v=<?= time() ?>" alt="Entrance QR Pass">
            </div>
            <div style="font-size: 8px; color: #000; text-align: center;">Scan at Terminal B to verify entrance and claim freebies.</div>

        <?php else: ?>
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
                    <td><strong>CATEGORY:</strong></td>
                    <td><?= htmlspecialchars(!empty($migs_category) ? $migs_category : 'REGULAR') ?></td>
                </tr>
            </table>
            
            <div class="receipt-divider"></div>
            <div style="font-size: 10px; font-weight: bold; margin-bottom: 3px; text-align: left; width: 100%; color: #000;">🔐 E-VOTING CREDENTIALS:</div>
            
            <div class="credential-box">
                <strong>Username:</strong> <?= htmlspecialchars($print_member['username'] ?? 'None Assigned') ?><br>
                <strong>Password:</strong> <?= htmlspecialchars($print_member['password'] ?? 'None Assigned') ?>
            </div>

            <!-- EXCLUDE FREEBIES ON DUPLICATE / RE-SCAN -->
            <?php if (!$is_duplicate): ?>
                <div class="receipt-divider"></div>
                <div style="font-size: 11px; font-weight: bold; margin-bottom: 4px; text-align: center; width: 100%; color: #000;">🎁 ISSUED FREEBIES & ALLOWANCE</div>

                <div class="freebie-container">
                    <?php 
                    $standard_items = ['GA T-Shirt', 'Cash Allowance', 'Snacks / Meals', 'PMPC Umbrella'];
                    if (strpos($migs_category, 'GOLD') !== false) {
                        $standard_items[] = 'Water Bottle for Gold Members';
                    }

                    foreach ($standard_items as $item):
                        $is_claimed = in_array($item, $claimed_items_db);
                    ?>
                        <div class="freebie-row">
                            <div class="freebie-chk-box <?= $is_claimed ? 'checked' : '' ?>"></div>
                            <div class="freebie-label"><?= htmlspecialchars($item) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            
            <div class="receipt-divider" style="margin-top: 6px;"></div>
            <div style="font-size: 7px; line-height: 1.3; font-weight: bold; text-align: center; color: #000;">PANABO CO-OP GENERAL ASSEMBLY 2027</div>
        <?php endif; ?>

    </div>
<?php 
    endif;
endif; 
?>

<!-- Auto-Print Trigger -->
<?php if (!empty($print_target_id)): ?>
<script>
    window.addEventListener('load', function() {
        setTimeout(function() {
            window.print();
        }, 400);
    });
</script>
<?php endif; ?>

<!-- Theme & Date Filter Script -->
<script>
    function filterByDate() {
        const dateInput = document.getElementById('date-filter').value;
        if (!dateInput) {
            alert('Please select a date');
            return;
        }
        window.location.href = 'index.php?route=gate&filter_date=' + encodeURIComponent(dateInput);
    }
    
    function clearDateFilter() {
        window.location.href = 'index.php?route=gate';
    }
    
    <?php if ($route === 'gate'): ?>
    setInterval(function() {
        fetch('index.php?route=gate&action=get_attendance_data')
            .then(response => response.text())
            .then(data => {
                document.getElementById('attendance-table-body').innerHTML = data;
            })
            .catch(error => console.log('Real-time update check...'));
    }, 5000);
    <?php endif; ?>

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
</script>

</body>
</html>