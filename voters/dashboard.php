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
        $stmt = $db->prepare("SELECT first_name, last_name, is_awardee, category, has_voted FROM members WHERE member_id = :id OR id = :id LIMIT 1");
        $stmt->execute(['id' => $member_id]);
        $member = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $stmt = $db->prepare("SELECT first_name, last_name, is_awardee, category, has_voted FROM members WHERE member_id = ? OR id = ? LIMIT 1");
        if ($stmt) {
            $stmt->bind_param("ss", $member_id, $member_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $member = $result ? $result->fetch_assoc() : null;
        }
    }

    if (!empty($member)) {
        $constructed_name = trim(($member['first_name'] ?? '') . ' ' . ($member['last_name'] ?? ''));
        if (!empty($constructed_name)) {
            $full_name = $constructed_name;
        }

        // Check voting status from DB directly
        $has_voted = intval($member['has_voted'] ?? 0);

        // Check if member is marked as awardee (1 or true)
        if (!empty($member['is_awardee']) && intval($member['is_awardee']) === 1) {
            $isAwardee = true;
            $awardee_text = !empty($member['category']) ? $member['category'] : 'Recognized Cooperative Awardee';
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Voter Dashboard</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

:root{
    --bg-gradient: radial-gradient(circle at top,#ffffff 0%,#f1f5f9 100%);
    --card-bg:#ffffff;
    --card-border:#e2e8f0;
    --text-color:#1e293b;
    --secondary:#64748b;
}

.dark-theme{
    --bg-gradient: radial-gradient(circle at top,#0f172a 0%,#020617 100%);
    --card-bg:#162338;
    --card-border:#334155;
    --text-color:#f8fafc;
    --secondary:#94a3b8;
}

body{
    background:var(--bg-gradient);
    min-height:100vh;
    font-family:'Segoe UI',sans-serif;
    transition:.3s;
}

.dashboard-card{
    max-width:700px;
    margin:auto;
    margin-top:80px;
    border:none;
    border-radius:20px;
    background:var(--card-bg);
    border:1px solid var(--card-border);
    overflow:hidden;
}

.logo{
    width:100px;
    height:100px;
    object-fit:contain;
    margin-bottom:20px;
}

.welcome-title{
    color:var(--text-color);
    font-weight:700;
    font-size:2rem;
}

.subtitle{
    color:var(--secondary);
    margin-bottom:25px;
}

.profile-box{
    display:inline-block;
    padding:14px 24px;
    border-radius:12px;
    background:rgba(59,130,246,.08);
    border:1px solid rgba(59,130,246,.15);
    color:var(--text-color);
    font-weight:600;
    margin-bottom:25px;
}

.status-card{
    display:flex;
    align-items:center;
    gap:20px;
    text-align:left;
    padding:20px;
    border-radius:15px;
    margin-bottom:30px;
}

.status-icon{
    font-size:40px;
}

.status-title{
    font-size:1.1rem;
    font-weight:700;
    margin-bottom:4px;
}

.status-text{
    margin:0;
}

.awardee{
    background:#ecfdf5;
    border:1px solid #10b981;
    color:#065f46;
}

.dark-theme .awardee{
    background:#064e3b;
    color:#d1fae5;
}

.already-voted-card {
    background: #fef2f2;
    border: 1px solid #fca5a5;
    color: #991b1b;
}

.dark-theme .already-voted-card {
    background: #450a0a;
    color: #fecaca;
}

.btn-vote{
    background:#10b981;
    color:white;
    border:none;
    padding:12px 30px;
    border-radius:12px;
    font-weight:600;
}

.btn-vote:hover{
    background:#059669;
    color:white;
}

.btn-logout{
    background:#ef4444;
    color:white;
    border:none;
    padding:12px 30px;
    border-radius:12px;
    font-weight:600;
}

.btn-logout:hover{
    background:#dc2626;
    color:white;
}

.theme-toggle-btn{
    position:fixed;
    top:20px;
    right:20px;
    border:none;
    border-radius:30px;
    padding:10px 18px;
    background:#1e293b;
    color:white;
    font-weight:600;
    z-index:999;
}

.dark-theme .theme-toggle-btn{
    background:#334155;
}

.footer-text{
    color:var(--secondary);
    font-size:.85rem;
    margin-top:30px;
}

</style>

</head>
<body>

<button class="theme-toggle-btn" onclick="toggleTheme()">
    <span id="themeText">🌙 Dark Mode</span>
</button>

<div class="container">

<div class="card shadow-lg dashboard-card">

<div class="card-body p-5 text-center">

<img
    src="../assets/images/logo.png"
    alt="PMPC Logo"
    class="logo"
    onerror="this.style.display='none';"
>

<h1 class="welcome-title">
    Welcome Back
</h1>

<p class="subtitle">
    PMPC E-Voting Portal
</p>

<div class="profile-box">
    👤 <?php echo htmlspecialchars($full_name); ?>
</div>

<?php if($isAwardee){ ?>

<div class="status-card awardee">

    <div class="status-icon">
        🏆
    </div>

    <div>

        <div class="status-title">
            Awardee Member
        </div>

        <p class="status-text">
            <?php echo htmlspecialchars($awardee_text); ?>
        </p>

    </div>

</div>

<?php } ?>

<?php if ($has_voted === 1): ?>

<div class="status-card already-voted-card">
    <div class="status-icon">
        ✅
    </div>
    <div>
        <div class="status-title">
            Voting Completed
        </div>
        <p class="status-text">
            You have already cast your vote for this election.
        </p>
    </div>
</div>

<div class="d-flex justify-content-center gap-3">
    <a href="logout.php" class="btn btn-logout">
        🚪 Logout
    </a>
</div>

<?php else: ?>

<div class="d-flex justify-content-center gap-3">

    <a href="vote.php" class="btn btn-vote">
        🗳 Start Voting
    </a>

    <a href="logout.php" class="btn btn-logout">
        🚪 Logout
    </a>

</div>

<?php endif; ?>

<div class="footer-text">
    Panabo Multipurpose Cooperative E-Voting System
</div>

</div>

</div>

</div>

<script>

function toggleTheme(){

    document.body.classList.toggle('dark-theme');

    if(document.body.classList.contains('dark-theme')){

        localStorage.setItem('pmpc-theme','dark');
        document.getElementById('themeText').innerHTML='☀️ Light Mode';

    }else{

        localStorage.setItem('pmpc-theme','light');
        document.getElementById('themeText').innerHTML='🌙 Dark Mode';

    }
}

window.onload=function(){

    if(localStorage.getItem('pmpc-theme')==='dark'){

        document.body.classList.add('dark-theme');
        document.getElementById('themeText').innerHTML='☀️ Light Mode';

    }

}

</script>

</body>
</html>