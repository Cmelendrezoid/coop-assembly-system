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
    $trim_input   = trim($raw_input);
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
    <title>Welcome - PMPC E-Voting Portal</title>
    
    <!-- Google Fonts: Plus Jakarta Sans -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Bootstrap 5 & Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <style>
        :root {
            /* Royal Blue & Glass Palette */
            --royal-blue: #4169E1;
            --royal-hover: #3152c7;
            --gold-light: #FDE047;
            --gold-dark: #CA8A04;
            
            /* Dynamic Mesh Gradient & Glass */
            --bg-mesh: radial-gradient(at 0% 0%, rgba(65, 105, 225, 0.08) 0px, transparent 50%),
                       radial-gradient(at 100% 0%, rgba(34, 211, 238, 0.08) 0px, transparent 50%),
                       radial-gradient(at 100% 100%, rgba(253, 224, 71, 0.06) 0px, transparent 50%),
                       #f8fafc;
            --glass-card: rgba(255, 255, 255, 0.82);
            --glass-border: rgba(255, 255, 255, 0.9);
            --card-shadow: 0 20px 40px -15px rgba(65, 105, 225, 0.12), 0 0 15px rgba(255, 255, 255, 0.6) inset;
            
            --text-main: #0f172a;
            --text-muted: #64748b;
            --label-color: #334155;
            --input-bg: rgba(248, 250, 252, 0.8);
            --input-border: #cbd5e1;
            --input-text: #0f172a;
            
            --toggle-btn-bg: rgba(255, 255, 255, 0.85);
            --toggle-btn-color: #334155;
            --toggle-btn-border: rgba(255, 255, 255, 0.9);
            
            --primary-gradient: linear-gradient(135deg, #2563eb, #4169E1);
            --primary-hover: linear-gradient(135deg, #1d4ed8, #3152c7);
            --primary-shadow: rgba(65, 105, 225, 0.3);
            
            --badge-bg: rgba(65, 105, 225, 0.1);
            --badge-color: #2563eb;
            --badge-border: rgba(65, 105, 225, 0.2);
            
            --alert-bg: rgba(254, 242, 242, 0.9);
            --alert-border: rgba(254, 202, 202, 0.8);
            --alert-text: #991b1b;
        }

        .dark-theme {
            --bg-mesh: radial-gradient(at 0% 0%, rgba(65, 105, 225, 0.2) 0px, transparent 50%),
                       radial-gradient(at 100% 0%, rgba(202, 138, 4, 0.12) 0px, transparent 50%),
                       radial-gradient(at 100% 100%, rgba(34, 211, 238, 0.1) 0px, transparent 50%),
                       #020617;
            --glass-card: rgba(30, 41, 59, 0.72);
            --glass-border: rgba(255, 255, 255, 0.12);
            --card-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.6), 0 0 15px rgba(65, 105, 225, 0.1) inset;
            
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --label-color: #cbd5e1;
            --input-bg: rgba(15, 23, 42, 0.7);
            --input-border: #475569;
            --input-text: #f8fafc;
            
            --toggle-btn-bg: rgba(30, 41, 59, 0.75);
            --toggle-btn-color: #f8fafc;
            --toggle-btn-border: rgba(255, 255, 255, 0.15);
            
            --primary-gradient: linear-gradient(135deg, #4169E1, #2563eb);
            --primary-hover: linear-gradient(135deg, #3152c7, #1d4ed8);
            --primary-shadow: rgba(65, 105, 225, 0.4);
            
            --badge-bg: rgba(253, 224, 71, 0.12);
            --badge-color: #FDE047;
            --badge-border: rgba(253, 224, 71, 0.25);
            
            --alert-bg: rgba(127, 29, 29, 0.35);
            --alert-border: rgba(153, 27, 27, 0.6);
            --alert-text: #fca5a5;
        }

        * {
            box-sizing: border-box;
            transition: background-color 0.25s ease, border-color 0.25s ease, color 0.25s ease, box-shadow 0.25s ease, transform 0.2s ease;
        }

        body {
            background-color: #0f172a;
            background-image: var(--bg-mesh);
            background-size: 150% 150%;
            animation: gradientMove 15s ease infinite alternate;
            font-family: 'Plus Jakarta Sans', sans-serif;
            min-height: 100vh;
            color: var(--text-main);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
            position: relative;
            overflow-x: hidden;
        }

        @keyframes gradientMove {
            0% { background-position: 0% 0%; }
            50% { background-position: 100% 100%; }
            100% { background-position: 0% 100%; }
        }

        /* Ambient Orbs */
        .orb {
            position: fixed;
            border-radius: 50%;
            filter: blur(90px);
            z-index: -1;
            opacity: 0.35;
            animation: floatOrb 12s ease-in-out infinite alternate;
        }
        .orb-1 { width: 350px; height: 350px; background: var(--royal-blue); top: -5%; left: -5%; }
        .orb-2 { width: 300px; height: 300px; background: var(--gold-dark); bottom: -5%; right: -5%; animation-delay: -6s; }

        @keyframes floatOrb {
            0% { transform: translateY(0) scale(1); }
            100% { transform: translateY(-30px) scale(1.05); }
        }

        /* Floating Theme Toggle Button */
        .theme-toggle-btn {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--toggle-btn-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            color: var(--toggle-btn-color);
            border: 1px solid var(--toggle-btn-border);
            padding: 9px 16px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.05);
            display: flex;
            align-items: center;
            gap: 8px;
            z-index: 100;
        }

        .theme-toggle-btn:hover {
            border-color: var(--royal-blue);
            transform: translateY(-2px);
            color: var(--royal-blue);
        }

        .dark-theme .theme-toggle-btn:hover {
            border-color: var(--gold-light);
            color: var(--gold-light);
        }

        .portal-wrapper {
            width: 100%;
            max-width: 450px;
            margin: auto;
            position: relative;
            z-index: 1;
        }

        .back-btn {
            margin-bottom: 16px;
        }

        .btn-outline-back {
            background: var(--toggle-btn-bg);
            backdrop-filter: blur(20px);
            border: 1px solid var(--toggle-btn-border);
            color: var(--toggle-btn-color);
            padding: 8px 18px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 13px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
        }

        .btn-outline-back:hover {
            border-color: var(--royal-blue);
            color: var(--royal-blue);
            transform: translateY(-1px);
        }

        .dark-theme .btn-outline-back:hover {
            border-color: var(--gold-light);
            color: var(--gold-light);
        }

        .portal {
            border-radius: 26px;
            background: var(--glass-card);
            backdrop-filter: blur(25px) saturate(160%);
            -webkit-backdrop-filter: blur(25px) saturate(160%);
            border: 1px solid var(--glass-border);
            box-shadow: var(--card-shadow);
            overflow: hidden;
        }

        .portal-logo {
            width: 84px;
            height: 84px;
            object-fit: contain;
            display: block;
            margin: 0 auto 16px auto;
            filter: drop-shadow(0 6px 12px rgba(0,0,0,0.08));
        }

        .welcome-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--badge-bg);
            color: var(--badge-color);
            border: 1px solid var(--badge-border);
            font-size: 11px;
            font-weight: 800;
            padding: 5px 14px;
            border-radius: 20px;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            margin-bottom: 12px;
        }

        h3 {
            font-weight: 800;
            color: var(--text-main);
            letter-spacing: -0.025em;
            margin-bottom: 6px;
            font-size: 25px;
            text-align: center;
        }

        .portal-subtitle {
            text-align: center;
            color: var(--text-muted);
            font-size: 13px;
            font-weight: 500;
            margin-bottom: 24px;
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

        /* Form Input Group Enhancements */
        .input-group-custom {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-group-custom .input-icon {
            position: absolute;
            left: 14px;
            color: var(--text-muted);
            font-size: 1.1rem;
            pointer-events: none;
            z-index: 5;
        }

        .input-group-custom .form-control {
            padding-left: 42px !important;
        }

        .form-control {
            background: var(--input-bg) !important;
            color: var(--input-text) !important;
            border: 1px solid var(--input-border) !important;
            border-radius: 14px !important;
            padding: 12px 15px;
            font-size: 14px;
            font-weight: 600;
        }

        .form-control:focus {
            border-color: var(--royal-blue) !important;
            box-shadow: 0 0 0 4px var(--primary-shadow) !important;
            background: var(--glass-card) !important;
        }

        .dark-theme .form-control:focus {
            border-color: var(--gold-light) !important;
        }

        .password-toggle-btn {
            position: absolute;
            right: 12px;
            background: none;
            border: none;
            color: var(--text-muted);
            font-size: 1.1rem;
            cursor: pointer;
            padding: 4px;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .password-toggle-btn:hover {
            color: var(--text-main);
        }

        .btn-primary-action {
            background: var(--primary-gradient);
            border: none;
            border-radius: 14px;
            padding: 13px;
            font-weight: 800;
            font-size: 14px;
            letter-spacing: 0.02em;
            color: #ffffff;
            box-shadow: 0 10px 20px -4px var(--primary-shadow);
            margin-top: 6px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .btn-primary-action:hover {
            background: var(--primary-hover);
            transform: translateY(-2px);
            box-shadow: 0 14px 25px -4px var(--primary-shadow);
            color: #ffffff;
        }

        .alert-danger-custom {
            border-radius: 14px;
            background-color: var(--alert-bg);
            color: var(--alert-text);
            border: 1px solid var(--alert-border);
            backdrop-filter: blur(10px);
            font-size: 13px;
            font-weight: 600;
            padding: 12px 16px;
            display: flex;
            align-items: flex-start;
            gap: 10px;
        }

        .portal-footer {
            margin-top: 24px;
            text-align: center;
            font-size: 12px;
            color: var(--text-muted);
            font-weight: 600;
            border-top: 1px solid var(--glass-border);
            padding-top: 18px;
        }
    </style>
</head>
<body>

<!-- Ambient background lighting orbs -->
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<button class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()">
    <i class="bi bi-moon-stars-fill" id="themeToggleIcon"></i>
    <span id="themeToggleText">Dark Mode</span>
</button>

<div class="container">
    <div class="portal-wrapper">
        <div class="back-btn">
            <a href="../index.php" class="btn-outline-back">
                <i class="bi bi-arrow-left"></i> Return to Main Portal
            </a>
        </div>

        <div class="portal">
            <div class="p-4 p-sm-5 text-center">
                <img src="../assets/images/logo.png" alt="PMPC Logo" class="portal-logo" onerror="this.style.display='none';">

                <div>
                    <span class="welcome-badge">
                        <i class="bi bi-shield-lock-fill"></i> Secure E-Voting
                    </span>
                </div>
                <h3>Welcome Back</h3>
                <p class="portal-subtitle">Sign in with your official account credentials to participate in your branch's active voting session.</p>

                <?php if(!empty($error)): ?>
                    <div class="alert-danger-custom mb-4 text-start">
                        <i class="bi bi-exclamation-triangle-fill fs-6 flex-shrink-0 mt-1"></i>
                        <div><?php echo htmlspecialchars($error); ?></div>
                    </div>
                <?php endif; ?>

                <form action="login.php" method="POST" class="text-start">
                    <div class="mb-3">
                        <label>Username / Member ID</label>
                        <div class="input-group-custom">
                            <i class="bi bi-person-fill input-icon"></i>
                            <input type="text" name="username" class="form-control" placeholder="Enter username or member ID" required autocomplete="username" value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                    </div>

                    <div class="mb-4">
                        <label>Password</label>
                        <div class="input-group-custom">
                            <i class="bi bi-key-fill input-icon"></i>
                            <input type="password" id="passwordInput" name="password" class="form-control" style="padding-right: 42px !important;" placeholder="Enter your password" required autocomplete="current-password">
                            <button type="button" class="password-toggle-btn" onclick="togglePasswordVisibility()" aria-label="Toggle password visibility">
                                <i class="bi bi-eye-slash-fill" id="passwordToggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" name="login" class="btn-primary-action">
                        <span>Sign In</span> <i class="bi bi-box-arrow-in-right fs-5"></i>
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
function togglePasswordVisibility() {
    const input = document.getElementById('passwordInput');
    const icon = document.getElementById('passwordToggleIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.remove('bi-eye-slash-fill');
        icon.classList.add('bi-eye-fill');
    } else {
        input.type = 'password';
        icon.classList.remove('bi-eye-fill');
        icon.classList.add('bi-eye-slash-fill');
    }
}

function toggleTheme(){
    const body = document.body;
    const icon = document.getElementById('themeToggleIcon');
    const text = document.getElementById('themeToggleText');

    body.classList.toggle('dark-theme');

    if(body.classList.contains('dark-theme')){
        icon.className = 'bi bi-sun-fill';
        text.innerText = 'Light Mode';
        localStorage.setItem('pmpc-theme', 'dark');
    } else {
        icon.className = 'bi bi-moon-stars-fill';
        text.innerText = 'Dark Mode';
        localStorage.setItem('pmpc-theme', 'light');
    }
}

window.onload = function(){
    if(localStorage.getItem('pmpc-theme') === 'dark'){
        document.body.classList.add('dark-theme');
        document.getElementById('themeToggleIcon').className = 'bi bi-sun-fill';
        document.getElementById('themeToggleText').innerText = 'Light Mode';
    }
}
</script>

</body>
</html>
<?php
ob_end_flush();
?>