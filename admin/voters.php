<?php
require_once 'session_start.php';
include '../config/db.php';

if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit();
}

$message = "";

/*
|--------------------------------------------------------------------------
| RESET VOTER'S BALLOT (2ND CHANCE LOGIC)
|--------------------------------------------------------------------------
*/
if(isset($_GET['reset_vote'])){

    $id = (int)$_GET['reset_vote'];

    $conn->begin_transaction();

    try {
        $stmt1 = $conn->prepare("
            DELETE FROM votes 
            WHERE member_id = ?
        ");
        $stmt1->bind_param("i", $id);
        $stmt1->execute();

        $stmt2 = $conn->prepare("
            UPDATE members 
            SET has_voted = 0 
            WHERE id = ?
        ");
        $stmt2->bind_param("i", $id);
        $stmt2->execute();

        $conn->commit();
        
        header("Location: voters.php");
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error resetting voter ballot: " . $e->getMessage();
    }
}

/*
|--------------------------------------------------------------------------
| SORTING LOGIC
|--------------------------------------------------------------------------
*/
$allowedSortColumns = ['id', 'full_name', 'branch_name', 'migs_category'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSortColumns) ? $_GET['sort'] : 'full_name';
$order = isset($_GET['order']) && strtoupper($_GET['order']) === 'DESC' ? 'DESC' : 'ASC';
$nextOrder = ($order === 'ASC') ? 'desc' : 'asc';
$branchArrow = ($sort === 'branch_name') ? ($order === 'ASC' ? ' <i class="bi bi-arrow-up"></i>' : ' <i class="bi bi-arrow-down"></i>') : '';

/*
|--------------------------------------------------------------------------
| SEARCH & SELECT (FILTERED TO DONE VOTING WITH SORTING)
|--------------------------------------------------------------------------
*/
$search = $_GET['search'] ?? '';

if(!empty($search)){
    $searchTerm = "%".$search."%";
    $stmt = $conn->prepare("
        SELECT *
        FROM members
        WHERE has_voted = 1 AND full_name LIKE ?
        ORDER BY {$sort} {$order}
    ");
    $stmt->bind_param("s",$searchTerm);
    $stmt->execute();
    $voters = $stmt->get_result();
}else{
    $voters = $conn->query("
        SELECT *
        FROM members
        WHERE has_voted = 1
        ORDER BY {$sort} {$order}
    ");
}

/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/
$totalMembers = $conn->query("
    SELECT COUNT(*) total
    FROM members
")->fetch_assoc()['total'];

$totalAwardees = $conn->query("
    SELECT COUNT(*) total
    FROM members
    WHERE awardee IS NOT NULL
    AND awardee <> ''
    AND awardee <> 'N/A'
")->fetch_assoc()['total'];

$totalPrinted = $conn->query("
    SELECT COUNT(*) total
    FROM members
    WHERE printed = 1
")->fetch_assoc()['total'];

$totalAllowance = $conn->query("
    SELECT COUNT(*) total
    FROM members
    WHERE allowance_claimed = 1
")->fetch_assoc()['total'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Voters - PMPC Admin</title>

<!-- Google Fonts: Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Bootstrap 5 & Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

<style>
:root {
    --card-bg: #ffffff;
    --text-primary: #0f172a;
    --text-secondary: #64748b;
    --border-color: #e2e8f0;
    --bg-main: #f8fafc;
    --input-bg: #ffffff;
    --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
    --placeholder-color: #64748b;
    --sidebar-bg: #ffffff;
    --sidebar-hover: #f1f5f9;
    --sidebar-active-bg: #eff6ff;
    --sidebar-active-color: #2563eb;
}

.dark-theme {
    --card-bg: #111827;
    --text-primary: #f9fafb;
    --text-secondary: #9ca3af;
    --border-color: #1f2937;
    --bg-main: #030712;
    --input-bg: #1f2937;
    --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
    --placeholder-color: #cbd5e1;
    --sidebar-bg: #111827;
    --sidebar-hover: #1f2937;
    --sidebar-active-bg: rgba(37, 99, 235, 0.15);
    --sidebar-active-color: #3b82f6;
}

body {
    background-color: var(--bg-main);
    color: var(--text-primary);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    transition: background-color 0.2s ease, color 0.2s ease;
    margin: 0;
}

/* Layout Wrapper */
.app-wrapper {
    display: flex;
    min-height: 100vh;
}

/* Sidebar Styling */
.sidebar {
    width: 260px;
    background-color: var(--sidebar-bg);
    border-right: 1px solid var(--border-color);
    position: fixed;
    top: 0;
    bottom: 0;
    left: 0;
    z-index: 1040;
    display: flex;
    flex-direction: column;
    transition: transform 0.3s ease;
}

.sidebar-header {
    padding: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    border-bottom: 1px solid var(--border-color);
}

.sidebar-brand {
    font-weight: 700;
    font-size: 1.1rem;
    color: var(--text-primary);
    text-decoration: none;
    letter-spacing: -0.01em;
}

.sidebar-menu {
    padding: 0.75rem 0.75rem;
    list-style: none;
    margin: 0;
    flex-grow: 1;
    overflow-y: auto;
}

.sidebar-item {
    margin-bottom: 0.25rem;
}

.sidebar-link {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.75rem 1rem;
    color: var(--text-secondary);
    text-decoration: none;
    font-weight: 500;
    font-size: 0.9rem;
    border-radius: 10px;
    transition: all 0.2s ease;
}

.sidebar-link:hover {
    background-color: var(--sidebar-hover);
    color: var(--text-primary);
}

.sidebar-link.active {
    background-color: var(--sidebar-active-bg);
    color: var(--sidebar-active-color);
    font-weight: 600;
}

.sidebar-footer {
    padding: 1rem;
    border-top: 1px solid var(--border-color);
}

/* Main Content Area */
.main-content {
    flex-grow: 1;
    margin-left: 260px;
    padding: 2rem 2.5rem;
    max-width: calc(100% - 260px);
}

/* Modern Card Styling */
.admin-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.75rem;
    box-shadow: var(--card-shadow);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

/* Typography & Headers */
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

/* Theme Toggle Button */
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

/* Form Controls */
.form-label {
    color: var(--text-primary);
    font-weight: 600;
    font-size: 0.85rem;
    margin-bottom: 0.4rem;
}

.custom-input, .form-select {
    background-color: var(--input-bg);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 0.6rem 0.85rem;
    font-size: 0.9rem;
}

.custom-input::placeholder,
.form-control::placeholder {
    color: var(--placeholder-color);
    opacity: 1;
}

.custom-input:focus, .form-select:focus, .form-control:focus {
    background-color: var(--input-bg);
    color: var(--text-primary);
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
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

/* Stat Widgets */
.stat-widget {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.25rem 1.5rem;
    box-shadow: var(--card-shadow);
    display: flex;
    align-items: center;
    gap: 1rem;
}

.stat-icon {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
}

/* Mobile Sidebar Toggle */
.sidebar-toggler {
    display: none;
    background: none;
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    padding: 0.4rem 0.75rem;
    border-radius: 8px;
    font-size: 1.25rem;
}

@media (max-width: 992px) {
    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar.show {
        transform: translateX(0);
    }
    .main-content {
        margin-left: 0;
        max-width: 100%;
        padding: 1.5rem 1rem;
    }
    .sidebar-toggler {
        display: inline-flex;
        align-items: center;
        justify-content: center;
    }
}
</style>
</head>
<body>

<div class="app-wrapper">

    <!-- SIDEBAR NAVIGATION -->
    <nav class="sidebar" id="appSidebar">
        <div class="sidebar-header">
            <div class="d-flex align-items-center justify-content-center bg-primary bg-opacity-10 text-primary rounded-3 p-2" style="width: 40px; height: 40px;">
                <i class="bi bi-shield-lock-fill fs-5"></i>
            </div>
            <div>
                <a href="dashboard.php" class="sidebar-brand d-block lh-sm">PMPC Admin</a>
                <span class="text-secondary" style="font-size: 0.75rem;">Election System</span>
            </div>
        </div>

        <div style="padding: 1.25rem 1.5rem 0.5rem; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: var(--text-secondary); letter-spacing: 0.05em;">MAIN MENU</div>

        <ul class="sidebar-menu">
            <li class="sidebar-item">
                <a href="dashboard.php" class="sidebar-link">
                    <i class="bi bi-grid-fill"></i> Dashboard
                </a>
            </li>
            <li class="sidebar-item">
                <a href="candidates.php" class="sidebar-link">
                    <i class="bi bi-people-fill"></i> Candidates
                </a>
            </li>
            <li class="sidebar-item">
                <a href="positions.php" class="sidebar-link">
                    <i class="bi bi-award-fill"></i> Positions
                </a>
            </li>
            <li class="sidebar-item">
                <a href="voters.php" class="sidebar-link active">
                    <i class="bi bi-person-badge-fill"></i> Voters
                </a>
            </li>
            <li class="sidebar-item">
                <a href="pre-registered.php" class="sidebar-link">
                    <i class="bi bi-clipboard-check"></i> Pre-registered
                </a>
            </li>
            <li class="sidebar-item">
                <a href="elections.php" class="sidebar-link">
                    <i class="bi bi-building"></i> Branches
                </a>
            </li>
            <li class="sidebar-item">
                <a href="results.php" class="sidebar-link">
                    <i class="bi bi-bar-chart-fill"></i> Results
                </a>
            </li>
        </ul>
        <div class="sidebar-footer">
            <a href="logout.php" class="sidebar-link text-danger">
                <i class="bi bi-box-arrow-left"></i> Logout
            </a>
        </div>
    </nav>

    <!-- MAIN CONTENT CONTAINER -->
    <div class="main-content">

        <!-- Top Header & Theme Switcher -->
        <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
            <div class="d-flex align-items-center gap-3">
                <button class="sidebar-toggler" onclick="toggleSidebar()" aria-label="Toggle Sidebar">
                    <i class="bi bi-list"></i>
                </button>
                <div>
                    <h2 class="page-title">Manage Voters</h2>
                    <p class="welcome-subtitle mb-0">Viewing registered cooperative members who have completed voting</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="theme-toggle-btn" onclick="toggleTheme()">
                    <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
                    <span id="themeText">Dark Mode</span>
                </button>
            </div>
        </div>

        <?php if(!empty($message)){ ?>
            <div class="alert alert-danger rounded-3 border-0 shadow-sm d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-exclamation-triangle-fill fs-5"></i>
                <div><?= htmlspecialchars($message); ?></div>
            </div>
        <?php } ?>

        <!-- STATISTICS WIDGETS -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="stat-widget">
                    <div class="stat-icon bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold"><?= $totalMembers ?></div>
                        <div class="text-secondary small fw-medium">Total Members</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-widget">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-award-fill"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold"><?= $totalAwardees ?></div>
                        <div class="text-secondary small fw-medium">Awardees</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-widget">
                    <div class="stat-icon bg-info bg-opacity-10 text-info">
                        <i class="bi bi-printer-fill"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold"><?= $totalPrinted ?></div>
                        <div class="text-secondary small fw-medium">Printed IDs</div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-widget">
                    <div class="stat-icon bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold"><?= $totalAllowance ?></div>
                        <div class="text-secondary small fw-medium">Allowance Claimed</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEARCH BAR -->
        <div class="admin-card mb-4">
            <form method="GET">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sort); ?>">
                <input type="hidden" name="order" value="<?= htmlspecialchars($order); ?>">
                <div class="row g-3">
                    <div class="col-md-10">
                        <input
                            type="text"
                            name="search"
                            class="form-control custom-input"
                            placeholder="Search completed voters by name..."
                            value="<?= htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100 rounded-3 fw-semibold py-2">
                            <i class="bi bi-search me-1"></i> Search
                        </button>
                    </div>
                </div>
            </form>
        </div>

        <!-- VOTERS TABLE -->
        <div class="admin-card">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div>
                    <h5 class="fw-bold mb-1"><i class="bi bi-person-check-fill text-primary me-2"></i>Members Done Voting</h5>
                    <p class="text-secondary small mb-0">List of verified voters who have successfully cast their votes</p>
                </div>
                <?php if($sort === 'branch_name'){ ?>
                    <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-20 rounded-pill px-3 py-1">
                        Sorted by Branch (<?= strtoupper($order) ?>)
                    </span>
                <?php } ?>
            </div>

            <div class="table-responsive">
                <table class="table custom-table align-middle">
                    <thead>
                        <tr>
                            <th width="80">ID</th>
                            <th>Full Name</th>
                            <th>
                                <a href="?sort=branch_name&order=<?= $nextOrder; ?>&search=<?= urlencode($search); ?>" class="text-decoration-none text-secondary fw-bold d-inline-flex align-items-center gap-1">
                                    Branch <?= $branchArrow; ?> <i class="bi bi-arrow-down-up small"></i>
                                </a>
                            </th>
                            <th>MIGS Category</th>
                            <th>Awardee</th>
                            <th>Printed</th>
                            <th>Allowance</th>
                            <th width="140" class="text-end">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if($voters->num_rows > 0){ ?>
                        <?php while($row = $voters->fetch_assoc()){ ?>
                            <tr>
                                <td class="fw-bold text-secondary">#<?= $row['id']; ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($row['full_name']); ?></td>
                                <td><?= htmlspecialchars($row['branch_name']); ?></td>
                                <td><?= htmlspecialchars($row['migs_category']); ?></td>
                                <td>
                                    <?php
                                    $awardee = trim($row['awardee'] ?? '');
                                    if(!empty($awardee) && strtoupper($awardee) !== 'N/A'){
                                    ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20 rounded-pill px-3 py-1">Awardee</span>
                                    <?php } else { ?>
                                        <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-20 rounded-pill px-3 py-1">Regular</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if($row['printed']){ ?>
                                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-20 rounded-pill px-3 py-1">Printed</span>
                                    <?php } else { ?>
                                        <span class="badge bg-danger bg-opacity-10 text-danger border border-danger border-opacity-20 rounded-pill px-3 py-1">Not Printed</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <?php if($row['allowance_claimed']){ ?>
                                        <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20 rounded-pill px-3 py-1">Claimed</span>
                                    <?php } else { ?>
                                        <span class="badge bg-warning bg-opacity-10 text-warning border border-warning border-opacity-20 rounded-pill px-3 py-1">Pending</span>
                                    <?php } ?>
                                </td>
                                <td class="text-end">
                                    <a
                                        href="?reset_vote=<?= $row['id']; ?>"
                                        class="btn btn-outline-warning btn-sm rounded-3 px-2 py-1 fw-semibold"
                                        onclick="return confirm('Are you sure you want to completely wipe out this user\'s ballot record and grant them a second chance to vote?')"
                                        title="Reset Vote">
                                        <i class="bi bi-arrow-counterclockwise"></i> Reset
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="8" class="text-center py-4 text-secondary">
                                No records found for members who have completed voting.
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleSidebar() {
    document.getElementById('appSidebar').classList.toggle('show');
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