<?php
ob_start();
session_start();

// Include database configuration
if (file_exists('../config/db.php')) {
    require_once '../config/db.php';
} elseif (file_exists('../config/conn.php')) {
    require_once '../config/conn.php';
}

// Redirect existing active sessions to their respective dashboards
if (isset($_SESSION['admin_id'])) {
    header("Location: ../admin/dashboard.php");
    exit();
}
if (isset($_SESSION['member_id']) || isset($_SESSION['voter_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username/member ID and password.";
    } else {
        // Detect database connection object ($conn or $pdo)
        $db = null;
        $is_pdo = false;

        if (isset($conn) && $conn) {
            $db = $conn;
            if ($conn instanceof PDO) $is_pdo = true;
        } elseif (isset($pdo) && $pdo) {
            $db = $pdo;
            $is_pdo = true;
        }

        if (!$db) {
            $error = "Database connection failed. Please check ../config/db.php";
        } else {
            $isAdminAccount = false;

            // 1. Check Admin Accounts (users table)
            try {
                if ($is_pdo) {
                    $stmt = $db->prepare("SELECT * FROM users WHERE username = :u LIMIT 1");
                    $stmt->execute(['u' => $username]);
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                } else {
                    $stmt = $db->prepare("SELECT * FROM users WHERE username = ? LIMIT 1");
                    if ($stmt) {
                        $stmt->bind_param("s", $username);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $user = $res ? $res->fetch_assoc() : null;
                    } else {
                        $user = null;
                    }
                }

                if ($user && isset($user['role']) && strtolower($user['role']) === 'admin') {
                    $passMatch = password_verify($password, $user['password']) || $password === $user['password'] || md5($password) === $user['password'];
                    if ($passMatch) {
                        $isAdminAccount = true;
                        session_regenerate_id(true);

                        $_SESSION['admin_id']       = $user['user_id'] ?? $user['id'];
                        $_SESSION['admin_username'] = $user['username'];
                        $_SESSION['role']           = 'admin';

                        header("Location: ../admin/dashboard.php");
                        exit();
                    } else {
                        $isAdminAccount = true;
                        $error = "Invalid admin password. Please try again.";
                    }
                }
            } catch (Exception $e) {
                // Table 'users' may not exist or admin check skipped
            }

            // 2. Check Voter Accounts (members table)
            if (!$isAdminAccount) {
                $table_name = 'members';
                $voter = null;
                $sql_err = "";

                if ($is_pdo) {
                    try {
                        $stmt = $db->prepare("SELECT * FROM {$table_name} WHERE username = :u OR member_id = :u OR id = :u LIMIT 1");
                        $stmt->execute(['u' => $username]);
                        $voter = $stmt->fetch(PDO::FETCH_ASSOC);
                    } catch (Exception $e) {
                        $sql_err = $e->getMessage();
                    }
                } else {
                    // Query matching columns present in the members table
                    $query = "SELECT * FROM {$table_name} WHERE username = ? OR member_id = ? OR id = ?";
                    $stmt = $db->prepare($query);

                    if ($stmt) {
                        $stmt->bind_param("sss", $username, $username, $username);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        if ($res && $res->num_rows > 0) {
                            $voter = $res->fetch_assoc();
                        }
                    } else {
                        $sql_err = $db->error;
                    }
                }

                if (!empty($sql_err)) {
                    $error = "Database Query Error: " . $sql_err;
                } elseif ($voter) {
                    $voterPass = $voter['password'] ?? '';
                    $passMatch = password_verify($password, $voterPass) || $password === $voterPass || md5($password) === $voterPass;

                    if ($passMatch) {
                        // Check Pre-registration / Registration Status
                        $is_preregistered = true;
                        if (array_key_exists('registered', $voter)) {
                            $is_preregistered = !empty($voter['registered']) && $voter['registered'] != 0 && strtolower((string)$voter['registered']) !== 'no';
                        } elseif (array_key_exists('is_preregistered', $voter)) {
                            $is_preregistered = !empty($voter['is_preregistered']) && $voter['is_preregistered'] != 0 && strtolower((string)$voter['is_preregistered']) !== 'no';
                        } elseif (array_key_exists('pre_registered', $voter)) {
                            $is_preregistered = !empty($voter['pre_registered']) && $voter['pre_registered'] != 0 && strtolower((string)$voter['pre_registered']) !== 'no';
                        } elseif (array_key_exists('is_registered', $voter)) {
                            $is_preregistered = !empty($voter['is_registered']) && $voter['is_registered'] != 0 && strtolower((string)$voter['is_registered']) !== 'no';
                        } elseif (array_key_exists('status', $voter)) {
                            $status_val = strtolower(trim((string)$voter['status']));
                            if (in_array($status_val, ['unregistered', 'not_registered', 'pending', 'inactive', '0'])) {
                                $is_preregistered = false;
                            }
                        }

                        if (!$is_preregistered) {
                            $error = "You have not pre-registered yet. Pre-registration is required to log in and vote.";
                        } else {
                            // Primary Key Safe Fallback: Check 'id' first, then 'member_id', then fallback
                            if (!empty($voter['id'])) {
                                $voter_id_val = $voter['id'];
                            } elseif (!empty($voter['member_id'])) {
                                $voter_id_val = $voter['member_id'];
                            } else {
                                $voter_id_val = 0;
                            }

                            $voter_name_val = trim(($voter['first_name'] ?? '') . ' ' . ($voter['last_name'] ?? ''));
                            if (empty($voter_name_val)) {
                                $voter_name_val = $voter['username'] ?? '';
                            }
                            $branch_val = $voter['branch'] ?? '';

                            // Branch Schedule Check
                            $voting_allowed = true;
                            if (!empty($branch_val)) {
                                try {
                                    if ($is_pdo) {
                                        $sch = $db->prepare("SELECT status FROM election_schedules WHERE branch_name = :b LIMIT 1");
                                        $sch->execute(['b' => $branch_val]);
                                        $row = $sch->fetch(PDO::FETCH_ASSOC);
                                        if ($row && strtoupper($row['status']) !== 'OPEN') {
                                            $voting_allowed = false;
                                            $error = "Voting is currently closed for your branch (" . htmlspecialchars($branch_val) . ").";
                                        }
                                    } else {
                                        $sch = $db->prepare("SELECT status FROM election_schedules WHERE branch_name = ? LIMIT 1");
                                        if ($sch) {
                                            $sch->bind_param("s", $branch_val);
                                            $sch->execute();
                                            $res = $sch->get_result();
                                            if ($res && $res->num_rows > 0) {
                                                $row = $res->fetch_assoc();
                                                if (strtoupper($row['status']) !== 'OPEN') {
                                                    $voting_allowed = false;
                                                    $error = "Voting is currently closed for your branch (" . htmlspecialchars($branch_val) . ").";
                                                }
                                            }
                                        }
                                    }
                                } catch (Exception $e) {
                                    // Skip schedule check if table doesn't exist
                                }
                            }

                            if ($voting_allowed) {
                                session_regenerate_id(true);
                                unset($_SESSION['admin_id'], $_SESSION['admin_username']);

                                // Session mapping matching members table fields
                                $_SESSION['member_id']   = $voter_id_val;
                                $_SESSION['voter_id']    = $voter_id_val;
                                $_SESSION['voters_id']   = $voter_id_val;
                                $_SESSION['full_name']   = $voter_name_val;
                                $_SESSION['voter_name']  = $voter_name_val;
                                $_SESSION['branch_name'] = $branch_val;
                                $_SESSION['role']        = 'voter';

                                header("Location: dashboard.php");
                                exit();
                            }
                        }
                    } else {
                        $error = "Invalid password. Please try again.";
                    }
                } else {
                    $error = "Username or Member ID not found. Please verify your credentials.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Welcome - E-Voting Portal</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-gradient: radial-gradient(circle at 50% 0%, #f8fafc 0%, #e2e8f0 100%);
            --card-bg: #ffffff;
            --card-border: rgba(226, 232, 240, 0.9);
            --text-main: #0f172a;
            --text-muted: #64748b;
            --label-color: #334155;
            --input-bg: #f8fafc;
            --input-border: #cbd5e1;
            --input-text: #0f172a;
            --toggle-btn-bg: #ffffff;
            --toggle-btn-color: #334155;
            --toggle-btn-border: #cbd5e1;
            --primary-color: #059669;
            --primary-hover: #047857;
            --primary-shadow: rgba(5, 150, 105, 0.25);
            --badge-bg: #ecfdf5;
            --badge-color: #047857;
        }

        .dark-theme {
            --bg-gradient: radial-gradient(circle at 50% 0%, #0f172a 0%, #020617 100%);
            --card-bg: #1e293b;
            --card-border: rgba(51, 65, 85, 0.9);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --label-color: #cbd5e1;
            --input-bg: #0f172a;
            --input-border: #475569;
            --input-text: #f8fafc;
            --toggle-btn-bg: #1e293b;
            --toggle-btn-color: #f8fafc;
            --toggle-btn-border: #475569;
            --primary-color: #10b981;
            --primary-hover: #059669;
            --primary-shadow: rgba(16, 185, 129, 0.3);
            --badge-bg: rgba(16, 185, 129, 0.15);
            --badge-color: #34d399;
        }

        * {
            transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }

        body {
            background: var(--bg-gradient);
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .theme-toggle-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--toggle-btn-bg);
            color: var(--toggle-btn-color);
            border: 1px solid var(--toggle-btn-border);
            padding: 9px 16px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 100;
        }

        .theme-toggle-btn:hover {
            border-color: var(--primary-color);
            transform: translateY(-1px);
        }

        .portal-wrapper {
            width: 100%;
            max-width: 460px;
            margin: auto;
        }

        .back-btn {
            margin-bottom: 18px;
        }

        .btn-outline-back {
            background: var(--toggle-btn-bg);
            border: 1px solid var(--toggle-btn-border);
            color: var(--toggle-btn-color);
            padding: 8px 16px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }

        .btn-outline-back:hover {
            border-color: var(--primary-color);
            color: var(--primary-color);
            background: var(--toggle-btn-bg);
        }

        .portal {
            border-radius: 24px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.12);
            overflow: hidden;
        }

        .portal-logo {
            width: 88px;
            height: 88px;
            object-fit: contain;
            display: block;
            margin: 0 auto 16px auto;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.05));
        }

        .welcome-badge {
            display: inline-block;
            background: var(--badge-bg);
            color: var(--badge-color);
            font-size: 11px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 10px;
        }

        h3 {
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.02em;
            margin-bottom: 6px;
            font-size: 24px;
            text-align: center;
        }

        .portal-subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 26px;
            line-height: 1.5;
        }

        label {
            color: var(--label-color);
            font-weight: 700;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            margin-bottom: 8px;
            display: block;
        }

        .form-control {
            background: var(--input-bg) !important;
            color: var(--input-text) !important;
            border: 1px solid var(--input-border) !important;
            border-radius: 12px;
            padding: 12px 15px;
            font-size: 14px;
            font-weight: 500;
        }

        .form-control:focus {
            border-color: var(--primary-color) !important;
            box-shadow: 0 0 0 4px var(--primary-shadow) !important;
            background: var(--card-bg) !important;
        }

        .btn-primary {
            background: var(--primary-color) !important;
            border: none;
            border-radius: 12px;
            padding: 13px;
            font-weight: 700;
            font-size: 14px;
            letter-spacing: 0.01em;
            box-shadow: 0 8px 16px -4px var(--primary-shadow);
            margin-top: 6px;
        }

        .btn-primary:hover {
            background: var(--primary-hover) !important;
            transform: translateY(-1px);
            box-shadow: 0 10px 20px -4px var(--primary-shadow);
        }

        .alert-danger {
            border-radius: 12px;
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fee2e2;
            font-size: 13px;
            font-weight: 600;
            padding: 12px 16px;
        }

        .dark-theme .alert-danger {
            background-color: rgba(127, 29, 29, 0.35);
            color: #fca5a5;
            border-color: rgba(153, 27, 27, 0.6);
        }

        .portal-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 500;
        }
    </style>
</head>
<body>

<button class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()">
    <span id="themeToggleIcon">🌙</span>
    <span id="themeToggleText">Dark Mode</span>
</button>

<div class="container">
    <div class="portal-wrapper">
        <div class="back-btn">
            <a href="../index.php" class="btn-outline-back">
                ← Return to Main Portal
            </a>
        </div>

        <div class="portal shadow-lg">
            <div class="p-4 p-sm-5 text-center">
                <img src="../assets/images/logo.png" alt="PMPC Logo" class="portal-logo" onerror="this.style.display='none';">

                <div>
                    <span class="welcome-badge">Secure E-Voting</span>
                </div>
                <h3>Welcome Back</h3>
                <p class="portal-subtitle">Please sign in with your official account credentials to participate in your branch's active voting session.</p>

                <?php if(!empty($error)): ?>
                    <div class="alert alert-danger mb-4 text-start">
                        ⚠️ <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" class="text-start">
                    <div class="mb-3">
                        <label>Username / Member ID</label>
                        <input type="text" name="username" class="form-control" placeholder="Enter your registered username or member ID" required autocomplete="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                    </div>

                    <div class="mb-4">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter your secure password" required autocomplete="current-password">
                    </div>

                    <button type="submit" name="login" class="btn btn-primary w-100">
                        Sign In
                    </button>
                </form>

                <div class="portal-footer">
                    Panabo Multi-Purpose Cooperative E-Voting System
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function toggleTheme(){
    const body = document.body;
    const icon = document.getElementById('themeToggleIcon');
    const text = document.getElementById('themeToggleText');

    body.classList.toggle('dark-theme');

    if(body.classList.contains('dark-theme')){
        icon.innerText = '☀️';
        text.innerText = 'Light Mode';
        localStorage.setItem('pmpc-theme', 'dark');
    } else {
        icon.innerText = '🌙';
        text.innerText = 'Dark Mode';
        localStorage.setItem('pmpc-theme', 'light');
    }
}

window.onload = function(){
    if(localStorage.getItem('pmpc-theme') === 'dark'){
        document.body.classList.add('dark-theme');
        document.getElementById('themeToggleIcon').innerText = '☀️';
        document.getElementById('themeToggleText').innerText = 'Light Mode';
    }
}
</script>

</body>
</html>
<?php
ob_end_flush();
?>