<?php
// Include the centralized session configurations and auth check first
require_once 'session_start.php';
include '../config/db.php';

/*
|--------------------------------------------------------------------------
| Dashboard Statistics
|--------------------------------------------------------------------------
*/

$totalVoters = $conn->query("
    SELECT COUNT(*) as total
    FROM members
")->fetch_assoc()['total'];

$totalCandidates = $conn->query("
    SELECT COUNT(*) as total
    FROM candidates
")->fetch_assoc()['total'];

$totalPositions = $conn->query("
    SELECT COUNT(*) as total
    FROM positions
")->fetch_assoc()['total'];

$totalVotes = $conn->query("
    SELECT COUNT(*) as total
    FROM votes
")->fetch_assoc()['total'];

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
    --sidebar:#0f172a;
    --sidebar-hover:#1e293b;
    --card:#ffffff;
    --text:#1e293b;
    --text-muted:#64748b;
    --border:#e2e8f0;
    --bg:#f8fafc;
}

.dark-theme{
    --sidebar:#020617;
    --sidebar-hover:#162338;
    --card:#162338;
    --text:#f8fafc;
    --text-muted:#94a3b8;
    --border:#334155;
    --bg:#0f172a;
}

body{
    background:var(--bg);
    color:var(--text);
    font-family:'Segoe UI',sans-serif;
    transition:.3s;
}

/* Responsive Top Header for Mobile Navigation */
.mobile-header {
    display: none;
    background: var(--sidebar);
    color: white;
    padding: 15px 20px;
    align-items: center;
    justify-content: space-between;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    box-shadow: 0 2px 10px rgba(0,0,0,0.2);
}

.mobile-brand {
    font-weight: 700;
    font-size: 1.15rem;
    margin: 0;
}

.menu-toggle-btn {
    background: transparent;
    border: 1px solid rgba(255,255,255,0.2);
    color: white;
    padding: 6px 12px;
    border-radius: 8px;
    font-size: 0.9rem;
}

/* Sidebar Wrapper Layout */
.sidebar{
    position:fixed;
    left:0;
    top:0;
    width:260px;
    height:100vh;
    background:var(--sidebar);
    padding:25px;
    overflow-y:auto;
    z-index: 1010;
    transition: transform 0.3s ease;
}

.logo{
    width:80px;
    height:80px;
    object-fit:contain;
}

.brand{
    color:white;
    font-size:1.3rem;
    font-weight:700;
    margin-top:15px;
}

.subtitle{
    color:#94a3b8;
    font-size:.9rem;
    margin-bottom:30px;
}

.nav-link-custom{
    display:block;
    color:#cbd5e1;
    text-decoration:none;
    padding:14px 16px;
    border-radius:12px;
    margin-bottom:10px;
    transition:.2s;
}

.nav-link-custom:hover{
    background:var(--sidebar-hover);
    color:white;
}

.logout{
    color:#f87171 !important;
}

/* Main Content Workspace Layout */
.main{
    margin-left:260px;
    padding:40px;
    transition: margin-left 0.3s ease, padding 0.3s ease;
}

.page-title{
    color:var(--text);
    font-weight:700;
    font-size: clamp(1.5rem, 4vw, 2.2rem);
}

.welcome{
    color:var(--text-muted);
}

.stat-card{
    border:none;
    border-radius:18px;
    background:var(--card);
    color:var(--text);
    border:1px solid var(--border);
    transition:.2s;
    height: 100%;
}

.stat-card:hover{
    transform:translateY(-4px);
}

.stat-icon{
    font-size:2rem;
}

.stat-number{
    font-size:2rem;
    font-weight:700;
}

.stat-label{
    color:var(--text-muted);
}

.theme-btn{
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
    font-size: 0.9rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.dark-theme .theme-btn{
    background:#334155;
}

.admin-box{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:18px;
    padding:25px;
    color:var(--text);
}

/* Overlay Backdrop backdrop when responsive menu is displayed */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.5);
    z-index: 1005;
}

/* Responsive Structural Breakpoints */
@media (max-width: 991.98px) {
    .mobile-header {
        display: flex;
    }
    .sidebar {
        transform: translateX(-100%);
        top: 0;
        height: 100vh;
    }
    .sidebar.show {
        transform: translateX(0);
    }
    .sidebar-overlay.show {
        display: block;
    }
    .main {
        margin-left: 0;
        padding: 90px 20px 40px 20px;
    }
    .theme-btn {
        position: static;
        margin-bottom: 20px;
        display: inline-block;
    }
    .title-area {
        display: flex;
        flex-direction: column-reverse;
        align-items: flex-start;
        gap: 10px;
        margin-bottom: 15px;
    }
}
</style>
</head>
<body>

<div class="mobile-header">
    <h2 class="mobile-brand">PMPC Admin</h2>
    <button class="menu-toggle-btn" onclick="toggleMenu()">☰ Menu</button>
</div>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleMenu()"></div>

<div class="sidebar" id="sidebarNav">
    <div class="text-center">
        <img
            src="../assets/images/logo.png"
            class="logo"
            alt="PMPC Logo"
            onerror="this.style.display='none';"
        >
        <div class="brand">PMPC Admin</div>
        <div class="subtitle">Election Management</div>
    </div>

    <a href="dashboard.php" class="nav-link-custom">🏠 Dashboard</a>
    <a href="candidates.php" class="nav-link-custom">🧑 Candidates</a>
    <a href="positions.php" class="nav-link-custom">🏆 Positions</a>
    <a href="voters.php" class="nav-link-custom">👥 Voters</a>
    <a href="elections.php" class="nav-link-custom">🗳 Branches</a>
    <a href="results.php" class="nav-link-custom">📊 Results</a>
    <a href="logout.php" class="nav-link-custom logout">🚪 Logout</a>
</div>

<div class="main">
    <div class="title-area d-lg-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="page-title">Admin Dashboard</h2>
            <p class="welcome mb-0">
                Welcome back, <strong><?php echo htmlspecialchars($_SESSION['admin_username']); ?></strong>
            </p>
        </div>
        <button class="theme-btn" onclick="toggleTheme()">
            <span id="themeText">🌙 Dark Mode</span>
        </button>
    </div>

    <div class="row g-4 mt-2">
        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card shadow-sm">
                <div class="card-body p-4">
                    <div class="stat-icon">👥</div>
                    <div class="stat-number"><?php echo number_format($totalVoters); ?></div>
                    <div class="stat-label">Registered Voters</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card shadow-sm">
                <div class="card-body p-4">
                    <div class="stat-icon">🧑</div>
                    <div class="stat-number"><?php echo number_format($totalCandidates); ?></div>
                    <div class="stat-label">Candidates</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card shadow-sm">
                <div class="card-body p-4">
                    <div class="stat-icon">🏆</div>
                    <div class="stat-number"><?php echo number_format($totalPositions); ?></div>
                    <div class="stat-label">Positions</div>
                </div>
            </div>
        </div>

        <div class="col-sm-6 col-xl-3">
            <div class="card stat-card shadow-sm">
                <div class="card-body p-4">
                    <div class="stat-icon">🗳</div>
                    <div class="stat-number"><?php echo number_format($totalVotes); ?></div>
                    <div class="stat-label">Votes Cast</div>
                </div>
            </div>
        </div>
    </div>

    <div class="admin-box mt-4 shadow-sm">
        <h4>Election Management System</h4>
        <hr>
        <p>Use the navigation menu to manage candidates, positions, voters, elections, and view election results.</p>
        <p class="mb-0">This dashboard provides a centralized overview of the current election statistics.</p>
    </div>
</div>

<script>
function toggleMenu() {
    const sidebar = document.getElementById('sidebarNav');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
}

function toggleTheme(){
    document.body.classList.toggle('dark-theme');

    if(document.body.classList.contains('dark-theme')){
        localStorage.setItem('admin-theme','dark');
        document.getElementById('themeText').innerHTML='☀️ Light Mode';
    }else{
        localStorage.setItem('admin-theme','light');
        document.getElementById('themeText').innerHTML='🌙 Dark Mode';
    }
}

window.onload=function(){
    if(localStorage.getItem('admin-theme')==='dark'){
        document.body.classList.add('dark-theme');
        document.getElementById('themeText').innerHTML='☀️ Light Mode';
    }
}
</script>
</body>
</html>