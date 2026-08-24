<?php
// Include the centralized session configurations and auth check first
require_once 'session_start.php';
include '../config/db.php';

/*
|--------------------------------------------------------------------------
| Election & Voter Statistics
|--------------------------------------------------------------------------
*/

$totalVoters = 0;
$totalCandidates = 0;
$totalPositions = 0;
$totalVotes = 0;

$votersRes = $conn->query("SELECT COUNT(*) as total FROM members");
if ($votersRes) { $totalVoters = $votersRes->fetch_assoc()['total'] ?? 0; }

$candidatesRes = $conn->query("SELECT COUNT(*) as total FROM candidates");
if ($candidatesRes) { $totalCandidates = $candidatesRes->fetch_assoc()['total'] ?? 0; }

$positionsRes = $conn->query("SELECT COUNT(*) as total FROM positions");
if ($positionsRes) { $totalPositions = $positionsRes->fetch_assoc()['total'] ?? 0; }

$votesRes = $conn->query("SELECT COUNT(*) as total FROM votes");
if ($votesRes) { $totalVotes = $votesRes->fetch_assoc()['total'] ?? 0; }

/*
|--------------------------------------------------------------------------
| Auto-Create Attendance Table if Missing
|--------------------------------------------------------------------------
*/

$createTableQuery = "
CREATE TABLE IF NOT EXISTS `attendance_logs` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `member_id` VARCHAR(50) NOT NULL,
  `member_name` VARCHAR(255) NOT NULL,
  `category` VARCHAR(50) DEFAULT 'REGULAR',
  `claimed_items` TEXT NULL,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
";
$conn->query($createTableQuery);

/*
|--------------------------------------------------------------------------
| Attendance & Claims Statistics
|--------------------------------------------------------------------------
*/

$filterDate = isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '';

// Base query conditions for attendance logs
$whereClause = "";
if (!empty($filterDate)) {
    $escapedDate = $conn->real_escape_string($filterDate);
    $whereClause = " WHERE DATE(created_at) = '$escapedDate'";
}

// 1. Total Attendees Arrived
$totalAttendees = 0;
$totalAttendeesRes = $conn->query("SELECT COUNT(*) as total FROM attendance_logs" . $whereClause);
if ($totalAttendeesRes) {
    $totalAttendees = $totalAttendeesRes->fetch_assoc()['total'] ?? 0;
}

// 2. Today's Arrivals
$todaysArrivals = 0;
$todaysArrivalsRes = $conn->query("SELECT COUNT(*) as total FROM attendance_logs WHERE DATE(created_at) = CURDATE()");
if ($todaysArrivalsRes) {
    $todaysArrivals = $todaysArrivalsRes->fetch_assoc()['total'] ?? 0;
}

// 3. Category Breakdown (e.g., GOLD members)
$goldCount = 0;
$goldCategoryQuery = "SELECT COUNT(*) as total FROM attendance_logs WHERE UPPER(category) = 'GOLD'" . ($whereClause ? " AND DATE(created_at) = '$escapedDate'" : "");
$goldRes = $conn->query($goldCategoryQuery);
if ($goldRes) {
    $goldCount = $goldRes->fetch_assoc()['total'] ?? 0;
}

// 4. Live Attendance & Claims Log Table
$attendanceLogs = false;
$logQuery = "
    SELECT 
        member_id, 
        member_name, 
        category, 
        claimed_items, 
        created_at 
    FROM attendance_logs 
    $whereClause 
    ORDER BY created_at DESC 
    LIMIT 100
";
$attendanceLogs = $conn->query($logQuery);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Admin Dashboard - PMPC</title>
<!-- Google Fonts: Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<!-- Bootstrap 5 & Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
:root {
    --sidebar-bg: #0f172a;
    --sidebar-hover: #1e293b;
    --sidebar-text: #94a3b8;
    --sidebar-text-active: #ffffff;
    --sidebar-active-bg: #2563eb;
    --card-bg: #ffffff;
    --text-primary: #0f172a;
    --text-secondary: #64748b;
    --border-color: #e2e8f0;
    --bg-main: #f8fafc;
    --input-bg: #ffffff;
    --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
}

.dark-theme {
    --sidebar-bg: #030712;
    --sidebar-hover: #111827;
    --sidebar-text: #9ca3af;
    --sidebar-text-active: #ffffff;
    --sidebar-active-bg: #2563eb;
    --card-bg: #111827;
    --text-primary: #f9fafb;
    --text-secondary: #9ca3af;
    --border-color: #1f2937;
    --bg-main: #030712;
    --input-bg: #1f2937;
    --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
}

body {
    background-color: var(--bg-main);
    color: var(--text-primary);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    transition: background-color 0.2s ease, color 0.2s ease;
}

/* Mobile Header */
.mobile-header {
    display: none;
    background: var(--sidebar-bg);
    color: white;
    padding: 1rem 1.25rem;
    align-items: center;
    justify-content: space-between;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

/* Sidebar Layout */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 260px;
    height: 100vh;
    background: var(--sidebar-bg);
    padding: 1.75rem 1.25rem;
    overflow-y: auto;
    z-index: 1010;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
}

.brand-wrapper {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 1.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.logo {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    object-fit: contain;
}

.brand-title {
    color: #ffffff;
    font-size: 1.1rem;
    font-weight: 700;
    line-height: 1.2;
    margin: 0;
}

.brand-subtitle {
    color: var(--sidebar-text);
    font-size: 0.75rem;
    font-weight: 500;
    margin: 0;
}

.nav-section-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #475569;
    font-weight: 700;
    margin: 1rem 0 0.5rem 0.75rem;
}

.nav-link-custom {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--sidebar-text);
    text-decoration: none;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    margin-bottom: 0.25rem;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.15s ease-in-out;
}

.nav-link-custom i {
    font-size: 1.1rem;
}

.nav-link-custom:hover {
    background: var(--sidebar-hover);
    color: var(--sidebar-text-active);
}

.nav-link-custom.active {
    background: var(--sidebar-active-bg);
    color: #ffffff;
}

.nav-link-custom.logout {
    color: #ef4444;
    margin-top: auto;
}

.nav-link-custom.logout:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #f87171;
}

/* Main Workspace */
.main {
    margin-left: 260px;
    padding: 2.5rem;
    transition: margin-left 0.3s ease, padding 0.3s ease;
}

.page-title {
    color: var(--text-primary);
    font-weight: 700;
    font-size: 1.75rem;
    letter-spacing: -0.02em;
    margin: 0;
}

.welcome-subtitle {
    color: var(--text-secondary);
    font-size: 0.925rem;
}

.theme-toggle-btn {
    border: 1px solid var(--border-color);
    border-radius: 50px;
    padding: 0.5rem 1rem;
    background: var(--card-bg);
    color: var(--text-primary);
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: var(--card-shadow);
    transition: all 0.2s ease;
}

.theme-toggle-btn:hover {
    background: var(--border-color);
}

/* Base Card Style */
.admin-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

.stat-icon-wrapper {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    margin-bottom: 1rem;
}

.stat-number {
    font-size: 1.85rem;
    font-weight: 700;
    letter-spacing: -0.03em;
    color: var(--text-primary);
    line-height: 1;
    margin-bottom: 0.35rem;
}

.stat-label {
    color: var(--text-secondary);
    font-size: 0.85rem;
    font-weight: 500;
}

/* Custom Colored Summary Cards */
.card-gradient-blue {
    background: linear-gradient(135deg, #2563eb, #1d4ed8);
    color: #ffffff;
    border-radius: 14px;
}

.card-gradient-green {
    background: linear-gradient(135deg, #10b981, #047857);
    color: #ffffff;
    border-radius: 14px;
}

.card-gradient-orange {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    color: #ffffff;
    border-radius: 14px;
}

/* Badges */
.badge-gold {
    background-color: rgba(217, 119, 6, 0.15);
    color: #d97706;
    border: 1px solid rgba(217, 119, 6, 0.3);
    font-size: 0.75rem;
    padding: 0.35em 0.75em;
    border-radius: 20px;
    font-weight: 600;
}

.dark-theme .badge-gold {
    color: #fbbf24;
}

.badge-status {
    background-color: rgba(16, 185, 129, 0.15);
    color: #059669;
    border: 1px solid rgba(16, 185, 129, 0.3);
    font-size: 0.75rem;
    padding: 0.35em 0.75em;
    border-radius: 8px;
    display: inline-flex;
    align-items: center;
    gap: 0.3rem;
    font-weight: 600;
}

.dark-theme .badge-status {
    color: #34d399;
}

/* Table Styling */
.custom-table {
    color: var(--text-primary);
    margin-bottom: 0;
}

.custom-table th {
    background: transparent;
    color: var(--text-secondary);
    border-bottom: 1px solid var(--border-color);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 1rem;
}

.custom-table td {
    background: transparent;
    color: var(--text-primary);
    border-bottom: 1px solid var(--border-color);
    padding: 1rem;
    vertical-align: middle;
}

.custom-table tbody tr:last-child td {
    border-bottom: none;
}

.custom-input {
    background-color: var(--input-bg);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 0.5rem 0.85rem;
}

.custom-input:focus {
    background-color: var(--input-bg);
    color: var(--text-primary);
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

/* Mobile Sidebar Overlay */
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1005;
}

@media (max-width: 991.98px) {
    .mobile-header {
        display: flex;
    }
    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar.show {
        transform: translateX(0);
    }
    .sidebar-overlay.show {
        display: block;
    }
    .main {
        margin-left: 0;
        padding: 6rem 1.25rem 2.5rem 1.25rem;
    }
}
</style>
</head>
<body>

<div class="mobile-header">
    <div class="d-flex align-items-center gap-2">
        <img src="../assets/images/logo.png" class="logo" style="width: 32px; height: 32px;" alt="Logo" onerror="this.style.display='none';">
        <h2 class="brand-title">PMPC Admin</h2>
    </div>
    <button class="btn btn-outline-light btn-sm rounded-3 px-3" onclick="toggleMenu()">
        <i class="bi bi-list fs-5"></i>
    </button>
</div>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleMenu()"></div>

<aside class="sidebar" id="sidebarNav">
    <div class="brand-wrapper">
        <img src="../assets/images/logo.png" class="logo" alt="PMPC Logo" onerror="this.style.display='none';">
        <div>
            <h1 class="brand-title">PMPC Admin</h1>
            <p class="brand-subtitle">Election System</p>
        </div>
    </div>

    <div class="nav-section-label">Main Menu</div>
    <a href="dashboard.php" class="nav-link-custom active"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
    <a href="candidates.php" class="nav-link-custom"><i class="bi bi-person-badge"></i> Candidates</a>
    <a href="positions.php" class="nav-link-custom"><i class="bi bi-award"></i> Positions</a>
    <a href="voters.php" class="nav-link-custom"><i class="bi bi-people"></i> Voters</a>
    <a href="pre-registered.php" class="nav-link-custom"><i class="bi bi-clipboard-check"></i> Pre-registered</a>
    <a href="elections.php" class="nav-link-custom"><i class="bi bi-building"></i> Branches</a>
    <a href="results.php" class="nav-link-custom"><i class="bi bi-bar-chart-line"></i> Results</a>

    <a href="logout.php" class="nav-link-custom logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
</aside>

<main class="main">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="page-title">Dashboard Overview</h2>
            <p class="welcome-subtitle mb-0">
                Welcome back, <strong><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?></strong>
            </p>
        </div>
        <div>
            <button class="theme-toggle-btn" onclick="toggleTheme()">
                <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
                <span id="themeText">Dark Mode</span>
            </button>
        </div>
    </div>

    <!-- ELECTION STATS ROW -->
    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="admin-card stat-card">
                <div class="stat-icon-wrapper bg-primary bg-opacity-10 text-primary">
                    <i class="bi bi-people-fill"></i>
                </div>
                <div class="stat-number"><?php echo number_format($totalVoters); ?></div>
                <div class="stat-label">Registered Voters</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="admin-card stat-card">
                <div class="stat-icon-wrapper bg-info bg-opacity-10 text-info">
                    <i class="bi bi-person-bounding-box"></i>
                </div>
                <div class="stat-number"><?php echo number_format($totalCandidates); ?></div>
                <div class="stat-label">Candidates</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="admin-card stat-card">
                <div class="stat-icon-wrapper bg-warning bg-opacity-10 text-warning">
                    <i class="bi bi-trophy-fill"></i>
                </div>
                <div class="stat-number"><?php echo number_format($totalPositions); ?></div>
                <div class="stat-label">Positions</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="admin-card stat-card">
                <div class="stat-icon-wrapper bg-success bg-opacity-10 text-success">
                    <i class="bi bi-check-circle-fill"></i>
                </div>
                <div class="stat-number"><?php echo number_format($totalVotes); ?></div>
                <div class="stat-label">Votes Cast</div>
            </div>
        </div>
    </div>

    <!-- ATTENDANCE & CLAIMS STATISTICS SECTION -->
    <div class="admin-card mb-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-pie-chart-fill text-primary me-2"></i>Attendance & Claims Statistics</h5>
                <p class="text-secondary small mb-0">Overview of member turnout and item distribution</p>
            </div>
            <!-- Export Dropdown Menu -->
            <div class="dropdown">
                <button class="btn btn-outline-success btn-sm rounded-3 fw-semibold dropdown-toggle d-inline-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-download"></i> Export Attendance
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="export_attendance.php?type=excel<?php echo !empty($filterDate) ? '&filter_date=' . urlencode($filterDate) : ''; ?>">
                            <i class="bi bi-file-earmark-excel text-success"></i> Export as Excel (.xls)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="export_attendance.php?type=pdf<?php echo !empty($filterDate) ? '&filter_date=' . urlencode($filterDate) : ''; ?>" target="_blank">
                            <i class="bi bi-file-earmark-pdf text-danger"></i> Export as PDF
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-12 col-md-4">
                <div class="p-4 text-center card-gradient-blue shadow-sm">
                    <div class="display-6 fw-bold mb-1"><?php echo number_format($totalAttendees); ?></div>
                    <div class="fw-medium text-white-50 small text-uppercase tracking-wider">Total Attendees Arrived</div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="p-4 text-center card-gradient-green shadow-sm">
                    <div class="display-6 fw-bold mb-1"><?php echo number_format($todaysArrivals); ?></div>
                    <div class="fw-medium text-white-50 small text-uppercase tracking-wider">Today's Arrivals</div>
                </div>
            </div>
            <div class="col-12 col-md-4">
                <div class="p-4 text-center card-gradient-orange shadow-sm">
                    <div class="display-6 fw-bold mb-1"><?php echo number_format($goldCount); ?></div>
                    <div class="fw-medium text-white-50 small text-uppercase tracking-wider">GOLD Category</div>
                </div>
            </div>
        </div>

        <!-- Filter Statistics by Date -->
        <div class="pt-3 border-top" style="border-color: var(--border-color) !important;">
            <label class="form-label text-secondary small fw-bold text-uppercase tracking-wider">Filter Statistics By Date</label>
            <form method="GET" action="dashboard.php" class="row g-2 align-items-center">
                <div class="col-auto flex-grow-1 flex-md-grow-0">
                    <input type="date" name="filter_date" class="form-control custom-input" value="<?php echo htmlspecialchars($filterDate); ?>">
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary rounded-3 btn-sm px-3 fw-semibold"><i class="bi bi-funnel-fill me-1"></i> Filter</button>
                    <a href="dashboard.php" class="btn btn-outline-secondary rounded-3 btn-sm px-3 fw-semibold">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <!-- LIVE ATTENDANCE & CLAIM LOG SECTION -->
    <div class="admin-card">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-clock-history text-primary me-2"></i>Live Attendance & Claim Log</h5>
                <p class="text-secondary small mb-0">Real-time log of check-ins and freebie distribution</p>
            </div>
            <!-- Export Dropdown Menu -->
            <div class="dropdown">
                <button class="btn btn-primary btn-sm rounded-3 fw-semibold dropdown-toggle d-inline-flex align-items-center gap-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-download"></i> Export Freebies Log
                </button>
                <ul class="dropdown-menu dropdown-menu-end shadow-sm">
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="export_freebies.php?type=excel<?php echo !empty($filterDate) ? '&filter_date=' . urlencode($filterDate) : ''; ?>">
                            <i class="bi bi-file-earmark-excel text-success"></i> Export as Excel (.xls)
                        </a>
                    </li>
                    <li>
                        <a class="dropdown-item d-flex align-items-center gap-2" href="export_freebies.php?type=pdf<?php echo !empty($filterDate) ? '&filter_date=' . urlencode($filterDate) : ''; ?>" target="_blank">
                            <i class="bi bi-file-earmark-pdf text-danger"></i> Export as PDF
                        </a>
                    </li>
                </ul>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table custom-table align-middle">
                <thead>
                    <tr>
                        <th>Member ID</th>
                        <th>Member Name</th>
                        <th>Category</th>
                        <th>Freebies & Claimed Items</th>
                        <th>Arrival Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($attendanceLogs && $attendanceLogs->num_rows > 0): ?>
                        <?php while ($log = $attendanceLogs->fetch_assoc()): ?>
                            <tr>
                                <td class="fw-bold text-primary">#<?php echo htmlspecialchars($log['member_id']); ?></td>
                                <td class="fw-semibold"><?php echo htmlspecialchars($log['member_name']); ?></td>
                                <td>
                                    <span class="badge badge-gold">
                                        <?php echo htmlspecialchars($log['category'] ?? 'REGULAR'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge-status mb-1">
                                        <i class="bi bi-check-circle-fill"></i> Claimed & Attended
                                    </span>
                                    <div class="text-secondary small">
                                        <?php echo htmlspecialchars($log['claimed_items'] ?? 'GA T-Shirt, Cash Allowance, Snacks / Meals'); ?>
                                    </div>
                                </td>
                                <td class="text-nowrap text-secondary small">
                                    <i class="bi bi-clock me-1"></i><?php echo htmlspecialchars($log['created_at']); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-secondary py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-opacity-50"></i>
                                No attendance logs found for this selection.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

<!-- Bootstrap 5 JavaScript Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
function toggleMenu() {
    const sidebar = document.getElementById('sidebarNav');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
}

function updateThemeUI(isDark) {
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');
    
    if (isDark) {
        document.body.classList.add('dark-theme');
        themeIcon.className = 'bi bi-sun-fill';
        themeText.innerText = 'Light Mode';
    } else {
        document.body.classList.remove('dark-theme');
        themeIcon.className = 'bi bi-moon-stars-fill';
        themeText.innerText = 'Dark Mode';
    }
}

function toggleTheme() {
    const isDark = !document.body.classList.contains('dark-theme');
    localStorage.setItem('admin-theme', isDark ? 'dark' : 'light');
    updateThemeUI(isDark);
}

window.onload = function() {
    const savedTheme = localStorage.getItem('admin-theme');
    if (savedTheme === 'dark') {
        updateThemeUI(true);
    }
}
</script>
</body>
</html>