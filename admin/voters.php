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
| BASE CONDITION FOR COMPLETED VOTERS (STRICT HAS_VOTED CHECK)
|--------------------------------------------------------------------------
*/
$voterMatchCondition = "members.has_voted = 1";

/*
|--------------------------------------------------------------------------
| EXPORT TO EXCEL / CSV LOGIC
|--------------------------------------------------------------------------
*/
if(isset($_GET['export']) && $_GET['export'] === 'excel'){
    $filename = "completed_voters_" . date('Y-m-d_H-i-s') . ".csv";

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=' . $filename);

    $output = fopen('php://output', 'w');

    // Add UTF-8 BOM for Excel to render accents/special characters correctly
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // CSV Column Headers
    fputcsv($output, ['ID', 'Full Name', 'Branch', 'Category', 'Status']);

    $exportQuery = $conn->query("
        SELECT id, full_name, branch_name, migs_category
        FROM members
        WHERE {$voterMatchCondition}
        ORDER BY full_name ASC
    ");

    if($exportQuery && $exportQuery->num_rows > 0){
        while($row = $exportQuery->fetch_assoc()){
            fputcsv($output, [
                $row['id'],
                $row['full_name'],
                $row['branch_name'] ?? '',
                $row['migs_category'] ?? '',
                'Done Voting'
            ]);
        }
    }

    fclose($output);
    exit();
}

/*
|--------------------------------------------------------------------------
| SORTING LOGIC
|--------------------------------------------------------------------------
*/
$sortKey = $_GET['sort'] ?? 'name';
$allowedSortKeys = ['id', 'name', 'branch_name', 'migs_category'];
if (!in_array($sortKey, $allowedSortKeys)) {
    $sortKey = 'name';
}

$sortDbMap = [
    'id'            => 'id',
    'name'          => 'full_name',
    'branch_name'   => 'branch_name',
    'migs_category' => 'migs_category'
];
$sortColumn = $sortDbMap[$sortKey];

$order = isset($_GET['order']) && strtoupper($_GET['order']) === 'DESC' ? 'DESC' : 'ASC';
$nextOrder = ($order === 'ASC') ? 'desc' : 'asc';
$branchArrow = ($sortKey === 'branch_name') ? ($order === 'ASC' ? ' <i class="bi bi-arrow-up"></i>' : ' <i class="bi bi-arrow-down"></i>') : '';

/*
|--------------------------------------------------------------------------
| SEARCH & SELECT (FILTERED TO DONE VOTING WITH SORTING)
|--------------------------------------------------------------------------
*/
$search = $_GET['search'] ?? '';

if(!empty($search)){
    $searchTerm = "%".$search."%";
    $stmt = $conn->prepare("
        SELECT id, full_name, branch_name, migs_category, has_voted
        FROM members
        WHERE {$voterMatchCondition}
          AND full_name LIKE ?
        ORDER BY {$sortColumn} {$order}
    ");
    $stmt->bind_param("s", $searchTerm);
    $stmt->execute();
    $voters = $stmt->get_result();
}else{
    $voters = $conn->query("
        SELECT id, full_name, branch_name, migs_category, has_voted
        FROM members
        WHERE {$voterMatchCondition}
        ORDER BY {$sortColumn} {$order}
    ");
}

/*
|--------------------------------------------------------------------------
| STATISTICS
|--------------------------------------------------------------------------
*/
$totalMembersQuery = $conn->query("
    SELECT COUNT(*) total
    FROM members
");
$totalMembers = $totalMembersQuery ? $totalMembersQuery->fetch_assoc()['total'] : 0;

$totalVotedQuery = $conn->query("
    SELECT COUNT(DISTINCT id) total FROM members WHERE {$voterMatchCondition}
");
$totalVoted = $totalVotedQuery ? $totalVotedQuery->fetch_assoc()['total'] : 0;

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

/* Main Content Area */
.main-content {
    flex-grow: 1;
    margin-left: 250px;
    padding: 2rem 2.5rem;
    width: calc(100% - 250px);
    max-width: calc(100% - 250px);
    box-sizing: border-box;
    min-width: 0;
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

    <!-- SHARED SIDEBAR -->
    <?php include 'sidebar.php'; ?>

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
            <div class="col-md-6">
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
            <div class="col-md-6">
                <div class="stat-widget">
                    <div class="stat-icon bg-success bg-opacity-10 text-success">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div>
                        <div class="fs-4 fw-bold"><?= $totalVoted ?></div>
                        <div class="text-secondary small fw-medium">Total Completed Voters</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- SEARCH BAR -->
        <div class="admin-card mb-4">
            <form method="GET">
                <input type="hidden" name="sort" value="<?= htmlspecialchars($sortKey); ?>">
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
            <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-3">
                <div>
                    <h5 class="fw-bold mb-1"><i class="bi bi-person-check-fill text-primary me-2"></i>Members Done Voting</h5>
                    <p class="text-secondary small mb-0">List of verified voters who have successfully cast their votes</p>
                </div>
                <div class="d-flex align-items-center gap-2">
                    <?php if($sortKey === 'branch_name'){ ?>
                        <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-20 rounded-pill px-3 py-2">
                            Sorted by Branch (<?= strtoupper($order) ?>)
                        </span>
                    <?php } ?>
                    <a href="?export=excel" class="btn btn-success fw-semibold rounded-3 px-3 py-2 d-inline-flex align-items-center gap-2">
                        <i class="bi bi-file-earmark-excel-fill fs-6"></i> Export to Excel
                    </a>
                </div>
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
                            <th>Category</th>
                            <th class="text-end">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if($voters && $voters->num_rows > 0){ ?>
                        <?php while($row = $voters->fetch_assoc()){ ?>
                            <tr>
                                <td class="fw-bold text-secondary">#<?= $row['id']; ?></td>
                                <td class="fw-bold"><?= htmlspecialchars($row['full_name'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($row['branch_name'] ?? ''); ?></td>
                                <td><?= htmlspecialchars($row['migs_category'] ?? ''); ?></td>
                                <td class="text-end">
                                    <span class="badge bg-success bg-opacity-10 text-success border border-success border-opacity-20 rounded-pill px-3 py-1 fw-semibold">
                                        <i class="bi bi-check-circle-fill me-1"></i> Done Voting
                                    </span>
                                </td>
                            </tr>
                        <?php } ?>
                    <?php } else { ?>
                        <tr>
                            <td colspan="5" class="text-center py-4 text-secondary">
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
function toggleMenu() {
    const sidebar = document.getElementById('sidebarNav');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebar) {
        sidebar.classList.toggle('show');
    }

    if (overlay) {
        overlay.classList.toggle('show');
    }
}

function toggleSidebar() {
    toggleMenu();
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