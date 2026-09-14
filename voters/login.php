<?php
ob_start();
session_start();

// Include database configuration
if (file_exists('../config/db.php')) {
    require_once '../config/db.php';
} elseif (file_exists('../config/conn.php')) {
    require_once '../config/conn.php';
}

// Redirect active sessions
if (isset($_SESSION['admin_id'])) {
    header("Location: ../admin/dashboard.php");
    exit();
}
if (isset($_SESSION['member_id']) || isset($_SESSION['voter_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

/**
 * Universal Password Checker
 * Handles leading zeros, plain strings, and standard hashes
 */
function check_member_password($input_pass, $stored_pass) {
    if ($stored_pass === null || $stored_pass === '') {
        return false;
    }

    $raw_input   = strval($input_pass);
    $raw_stored  = strval($stored_pass);
    $trim_input  = trim($raw_input);
    $trim_stored = trim($raw_stored);

    // Direct match (retains leading zeros like '059644')
    if ($raw_input === $raw_stored || $trim_input === $trim_stored) {
        return true;
    }

    // Case-insensitive match
    if (strcasecmp($raw_input, $raw_stored) === 0 || strcasecmp($trim_input, $trim_stored) === 0) {
        return true;
    }

    // Standard PHP password_verify (for hashed passwords)
    if (
        password_verify($raw_input, $raw_stored) || 
        password_verify($trim_input, $raw_stored) || 
        password_verify($raw_input, $trim_stored) || 
        password_verify($trim_input, $trim_stored)
    ) {
        return true;
    }

    // Legacy Hash Check (MD5, SHA1, SHA256)
    $lower_stored = strtolower($trim_stored);
    if (
        strtolower(md5($raw_input)) === $lower_stored || 
        strtolower(md5($trim_input)) === $lower_stored ||
        strtolower(sha1($raw_input)) === $lower_stored || 
        strtolower(sha1($trim_input)) === $lower_stored ||
        strtolower(hash('sha256', $raw_input)) === $lower_stored || 
        strtolower(hash('sha256', $trim_input)) === $lower_stored
    ) {
        return true;
    }

    return false;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $username = isset($_POST['username']) ? trim(strval($_POST['username'])) : '';
    $password = isset($_POST['password']) ? strval($_POST['password']) : '';

    if (empty($username) || empty($password)) {
        $error = "Please enter both username/member ID and password.";
    } else {
        // Resolve Active Database Connection
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
            $error = "Database connection error. Please verify database configuration.";
        } else {
            $matched_voters = [];

            // Query matching accounts by ID, Username, or Full Name
            try {
                if ($is_pdo) {
                    $stmt = $db->prepare("
                        SELECT * FROM members 
                        WHERE LOWER(TRIM(username)) = LOWER(:u) 
                           OR LOWER(TRIM(CAST(id AS CHAR))) = LOWER(:u)
                           OR LOWER(TRIM(full_name)) LIKE LOWER(:like_u)
                    ");
                    $stmt->execute([
                        'u'      => $username,
                        'like_u' => '%' . $username . '%'
                    ]);
                    $matched_voters = $stmt->fetchAll(PDO::FETCH_ASSOC);
                } else {
                    $stmt = $db->prepare("
                        SELECT * FROM members 
                        WHERE LOWER(TRIM(username)) = LOWER(?) 
                           OR LOWER(TRIM(CAST(id AS CHAR))) = LOWER(?)
                           OR LOWER(TRIM(full_name)) LIKE ?
                    ");
                    if ($stmt) {
                        $like_search = '%' . $username . '%';
                        $stmt->bind_param("sss", $username, $username, $like_search);
                        $stmt->execute();
                        $res = $stmt->get_result();
                        $matched_voters = $res ? $res->fetch_all(MYSQLI_ASSOC) : [];
                    }
                }
            } catch (Exception $e) {
                $error = "Database Query Error: " . $e->getMessage();
            }

            $voter = null;

            // Iterate over matches to account for duplicate usernames with different passwords/IDs
            if (!empty($matched_voters)) {
                foreach ($matched_voters as $candidate) {
                    $stored_pass = $candidate['password'] ?? '';
                    if (check_member_password($password, $stored_pass)) {
                        $voter = $candidate;
                        break;
                    }
                }
            }

            // Authenticate Credentials
            if ($voter) {
                // Pre-registration status check
                $is_registered = !isset($voter['registered']) || (string)$voter['registered'] === '1' || (int)$voter['registered'] === 1 || $voter['registered'] === null;

                if (!$is_registered) {
                    $error = "You have not pre-registered yet. Pre-registration is required to log in.";
                } else {
                    $branch_name = $voter['branch_name'] ?? '';
                    $can_vote = true;

                    // Branch schedule verification
                    if (!empty($branch_name)) {
                        try {
                            if ($is_pdo) {
                                $sch = $db->prepare("SELECT status FROM election_schedules WHERE LOWER(TRIM(branch_name)) = LOWER(:b) LIMIT 1");
                                $sch->execute(['b' => $branch_name]);
                                $row = $sch->fetch(PDO::FETCH_ASSOC);
                                if ($row && strtoupper(trim($row['status'])) !== 'OPEN') {
                                    $can_vote = false;
                                    $error = "Voting is currently closed for your branch (" . htmlspecialchars($branch_name) . ").";
                                }
                            } else {
                                $sch = $db->prepare("SELECT status FROM election_schedules WHERE LOWER(TRIM(branch_name)) = LOWER(?) LIMIT 1");
                                if ($sch) {
                                    $sch->bind_param("s", $branch_name);
                                    $sch->execute();
                                    $res = $sch->get_result();
                                    if ($res && $res->num_rows > 0) {
                                        $row = $res->fetch_assoc();
                                        if (strtoupper(trim($row['status'])) !== 'OPEN') {
                                            $can_vote = false;
                                            $error = "Voting is currently closed for your branch (" . htmlspecialchars($branch_name) . ").";
                                        }
                                    }
                                }
                            }
                        } catch (Exception $e) {
                            // Ignore if optional
                        }
                    }

                    if ($can_vote) {
                        session_regenerate_id(true);

                        unset($_SESSION['admin_id'], $_SESSION['admin_username']);

                        $_SESSION['member_id']   = $voter['id'];
                        $_SESSION['voter_id']    = $voter['id'];
                        $_SESSION['voters_id']   = $voter['id'];
                        $_SESSION['full_name']   = !empty($voter['full_name']) ? $voter['full_name'] : $voter['username'];
                        $_SESSION['voter_name']  = $_SESSION['full_name'];
                        $_SESSION['branch_name'] = $branch_name;
                        $_SESSION['role']        = 'voter';

                        header("Location: dashboard.php");
                        exit();
                    }
                }
            } else {
                // Fallback check in 'users' (Admin) table if not found in members
                $admin_user = null;
                try {
                    if ($is_pdo) {
                        $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(TRIM(username)) = LOWER(:u) LIMIT 1");
                        $stmt->execute(['u' => $username]);
                        $admin_user = $stmt->fetch(PDO::FETCH_ASSOC);
                    } else {
                        $stmt = $db->prepare("SELECT * FROM users WHERE LOWER(TRIM(username)) = LOWER(?) LIMIT 1");
                        if ($stmt) {
                            $stmt->bind_param("s", $username);
                            $stmt->execute();
                            $res = $stmt->get_result();
                            $admin_user = $res ? $res->fetch_assoc() : null;
                        }
                    }
                } catch (Exception $e) {}

                if ($admin_user && check_member_password($password, $admin_user['password'] ?? '')) {
                    session_regenerate_id(true);
                    $_SESSION['admin_id']       = $admin_user['user_id'] ?? $admin_user['id'];
                    $_SESSION['admin_username'] = $admin_user['username'];
                    $_SESSION['role']           = 'admin';

                    header("Location: ../admin/dashboard.php");
                    exit();
                } else {
                    $error = "Invalid password or user account. Please check your credentials.";
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