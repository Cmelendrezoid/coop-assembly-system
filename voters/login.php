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
            $error = "Invalid password.";
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
                $error = "Invalid password.";
            }

        } else {
            $error = "Username not found.";
        }
    }
}
?>

<!DOCTYPE html>
<html>
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Voter Login</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

:root {
    --bg-gradient: radial-gradient(circle at top, #ffffff 0%, #f1f3f5 100%);
    --card-bg: #ffffff;
    --card-border: rgba(0, 0, 0, 0.05);
    --text-main-gradient: linear-gradient(135deg, #1e293b 0%, #475569 100%);
    --text-muted: #64748b;
    --label-color: #475569;
    --input-bg: #ffffff;
    --input-border: #cbd5e1;
    --input-text: #1e293b;
    --toggle-btn-bg: #f1f5f9;
    --toggle-btn-color: #334155;
}

.dark-theme {
    --bg-gradient: radial-gradient(circle at top, #1e293b 0%, #0f172a 100%);
    --card-bg: #1e293b;
    --card-border: rgba(255,255,255,0.05);
    --text-main-gradient: linear-gradient(135deg, #ffffff 0%, #cbd5e1 100%);
    --text-muted: #94a3b8;
    --label-color: #cbd5e1;
    --input-bg: #1e293b;
    --input-border: #475569;
    --input-text: #f8fafc;
    --toggle-btn-bg: #334155;
    --toggle-btn-color: #f8fafc;
}

body{
    background: var(--bg-gradient);
    font-family: 'Inter', sans-serif;
    min-height: 100vh;
}

.theme-toggle-btn{
    position:absolute;
    top:20px;
    right:20px;
    background:var(--toggle-btn-bg);
    color:var(--toggle-btn-color);
    border:none;
    padding:10px 16px;
    border-radius:30px;
    cursor:pointer;
    font-weight:600;
}

.portal-wrapper{
    max-width:500px;
    margin:auto;
    margin-top:60px;
}

.portal{
    border-radius:16px;
    background:var(--card-bg);
    border:1px solid var(--card-border);
}

.portal-logo{
    width:120px;
    height:120px;
    object-fit:contain;
    display:block;
    margin:auto;
    margin-bottom:20px;
}

h3{
    font-weight:700;
    background:var(--text-main-gradient);
    -webkit-background-clip:text;
    -webkit-text-fill-color:transparent;
    margin-bottom:30px;
}

label{
    color:var(--label-color);
    font-weight:500;
}

.form-control{
    background:var(--input-bg) !important;
    color:var(--input-text) !important;
    border:1px solid var(--input-border) !important;
    border-radius:10px;
    padding:12px;
}

.form-control:focus{
    border-color:#3b82f6 !important;
    box-shadow:0 0 0 3px rgba(59,130,246,.15) !important;
}

.btn-primary{
    background:#3b82f6 !important;
    border:none;
    border-radius:10px;
    padding:12px;
    font-weight:600;
}

.btn-primary:hover{
    background:#2563eb !important;
}

.back-btn{
    margin-bottom:15px;
}

.alert-danger{
    border-radius:12px;
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

<a href="../index.php" class="btn btn-outline-secondary">
← Back to Portal
</a>

</div>

<div class="card shadow-lg portal">

<div class="card-body p-5">

<img
src="../assets/images/logo.png"
alt="PMPC Logo"
class="portal-logo"
onerror="this.style.display='none';"
>

<h3 class="text-center">
E-Voting System
</h3>

<?php if($error){ ?>

<div class="alert alert-danger">
<?php echo $error; ?>
</div>

<?php } ?>

<form method="POST">

<div class="mb-3">

<label>Username</label>

<input
type="text"
name="username"
class="form-control"
placeholder="Enter your username"
required
autocomplete="username">

</div>

<div class="mb-4">

<label>Password</label>

<input
type="password"
name="password"
class="form-control"
placeholder="Enter your password"
required
autocomplete="current-password">

</div>

<button
type="submit"
name="login"
class="btn btn-primary w-100">

Login

</button>

</form>

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

        localStorage.setItem(
            'pmpc-theme',
            'dark'
        );

    }else{

        icon.innerText = '🌙';
        text.innerText = 'Dark Mode';

        localStorage.setItem(
            'pmpc-theme',
            'light'
        );
    }
}

window.onload = function(){

    if(
        localStorage.getItem('pmpc-theme')
        === 'dark'
    ){

        document.body.classList.add(
            'dark-theme'
        );

        document.getElementById(
            'themeToggleIcon'
        ).innerText = '☀️';

        document.getElementById(
            'themeToggleText'
        ).innerText = 'Light Mode';
    }
}

</script>

</body>
</html>