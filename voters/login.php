<?php

session_start();
include '../config/db.php';

// Check existing active sessions
if (isset($_SESSION['admin_id'])) {
    header("Location: ../admin/dashboard.php");
    exit();
}

if (isset($_SESSION['member_id'])) {
    header("Location: dashboard.php");
    exit();
}

$error = "";

if (isset($_POST['login'])) {

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    // 1. Check ONLY for Admin Accounts in the users table
    $adminStmt = $conn->prepare("SELECT * FROM users WHERE username = ? AND LOWER(role) = 'admin' LIMIT 1");
    $adminStmt->bind_param("s", $username);
    $adminStmt->execute();
    $adminResult = $adminStmt->get_result();

    $isAdminAccount = false;

    if ($adminResult && $adminResult->num_rows > 0) {
        $user = $adminResult->fetch_assoc();

        $adminPasswordMatches = password_verify($password, $user['password'])
            || $password === $user['password']
            || md5($password) === $user['password'];

        if ($adminPasswordMatches) {
            $isAdminAccount = true;

            session_regenerate_id(true);
            unset($_SESSION['member_id'], $_SESSION['full_name'], $_SESSION['branch_name']);

            $_SESSION['admin_id']       = $user['user_id'];
            $_SESSION['admin_username'] = $user['username'];
            $_SESSION['role']           = 'admin';

            // Direct Admin to Admin Dashboard
            header("Location: ../admin/dashboard.php");
            exit();
        } else {
            // Password failed for Admin username
            $isAdminAccount = true;
            $error = "Invalid password. Please try again.";
        }
    }

    // 2. If not an Admin account, check Member Accounts (Voters)
    if (!$isAdminAccount) {

        $stmt = $conn->prepare("SELECT * FROM members WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows > 0) {

            $member = null;
            while ($row = $result->fetch_assoc()) {
                if (password_verify($password, $row['password']) || $password === $row['password'] || md5($password) === $row['password']) {
                    $member = $row;
                    break;
                }
            }

            if ($member) {
                // Check if member has already cast a vote
                $alreadyVoted = false;
                $columnCheck = $conn->query("SHOW COLUMNS FROM members LIKE 'has_voted'");
                
                if ($columnCheck && $columnCheck->num_rows > 0) {
                    $alreadyVoted = !empty($member['has_voted']);
                } else {
                    $voteCheck = $conn->prepare("SELECT COUNT(*) AS total FROM votes WHERE member_id = ?");
                    $voteCheck->bind_param("i", $member['id']);
                    $voteCheck->execute();
                    $voteResult = $voteCheck->get_result();
                    $voteRow = $voteResult->fetch_assoc();
                    $alreadyVoted = ($voteRow['total'] > 0);
                }

                if ($alreadyVoted) {
                    $error = "This account has already voted.";
                } else {
                    $branch_name = trim($member['branch_name']);

                    // Verify election status for member's branch
                    $schedule_stmt = $conn->prepare("SELECT status FROM election_schedules WHERE branch_name = ? LIMIT 1");
                    $schedule_stmt->bind_param("s", $branch_name);
                    $schedule_stmt->execute();
                    $schedule_result = $schedule_stmt->get_result();

                    if ($schedule_result->num_rows == 0) {
                        $error = "No election configuration found for your branch.";
                    } else {
                        $schedule = $schedule_result->fetch_assoc();

                        if ($schedule['status'] != 'OPEN') {
                            $error = "Voting is currently closed for your branch.";
                        } else {
                            session_regenerate_id(true);
                            unset($_SESSION['admin_id'], $_SESSION['admin_username'], $_SESSION['role']);
                            
                            $_SESSION['member_id']   = $member['id'];
                            $_SESSION['full_name']   = $member['full_name'];
                            $_SESSION['branch_name'] = $member['branch_name'];

                            // Direct Member to E-Voting Dashboard/Page
                            header("Location: dashboard.php");
                            exit();
                        }
                    }
                }

            } else {
                $error = "Invalid password. Please try again.";
            }

        } else {
            $error = "Username not found. Please verify your credentials.";
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

                <?php if($error){ ?>
                    <div class="alert alert-danger mb-4 text-start">
                        ⚠️ <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php } ?>

                <form method="POST" class="text-start">
                    <div class="mb-3">
                        <label>Username</label>
                        <input type="text" name="username" class="form-control" placeholder="Enter your registered username" required autocomplete="username">
                    </div>

                    <div class="mb-4">
                        <label>Password</label>
                        <input type="password" name="password" class="form-control" placeholder="Enter your secure password" required autocomplete="current-password">
                    </div>

                    <button type="submit" name="login" class="btn btn-primary w-100">
                        Sign In to Cast Your Vote
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