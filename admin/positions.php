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
| ADD POSITION
|--------------------------------------------------------------------------
*/
if(isset($_POST['add_position'])){

    $position_name = trim($_POST['position_name']);
    $vote_limit = (int)$_POST['vote_limit'];

    $stmt = $conn->prepare(
        "INSERT INTO positions (position_name, vote_limit) VALUES (?, ?)"
    );

    $stmt->bind_param("si", $position_name, $vote_limit);

    if($stmt->execute()){
        $message = "Position added successfully.";
    }
}

/*
|--------------------------------------------------------------------------
| UPDATE POSITION (VIA MODAL POP-UP SUBMISSION)
|--------------------------------------------------------------------------
*/
if(isset($_POST['update_position'])){

    $id = (int)$_POST['id'];
    $position_name = trim($_POST['position_name']);
    $vote_limit = (int)$_POST['vote_limit'];

    $stmt = $conn->prepare(
        "UPDATE positions SET position_name=?, vote_limit=? WHERE id=?"
    );

    $stmt->bind_param("sii", $position_name, $vote_limit, $id);
    
    if($stmt->execute()){
        $message = "Position updated successfully.";
    }
}

/*
|--------------------------------------------------------------------------
| DELETE POSITION
|--------------------------------------------------------------------------
*/
if(isset($_GET['delete'])){

    $id = (int)$_GET['delete'];

    $stmt = $conn->prepare(
        "DELETE FROM positions WHERE id=?"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: positions.php");
    exit();
}

// Fetch all database records ordered by name
$positions = $conn->query(
    "SELECT * FROM positions ORDER BY position_name"
);

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Positions - PMPC Admin</title>

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
.form-control::placeholder,
.form-select::placeholder {
    color: var(--placeholder-color);
    opacity: 1;
}

.custom-input:focus, .form-select:focus {
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

/* Modal Dark Theme Overrides */
.dark-theme .modal-content {
    background-color: var(--card-bg);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.dark-theme .modal-header, 
.dark-theme .modal-footer {
    border-color: var(--border-color);
}

.dark-theme .btn-close {
    filter: invert(1) grayscale(1) brightness(2);
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
                <a href="positions.php" class="sidebar-link active">
                    <i class="bi bi-award-fill"></i> Positions
                </a>
            </li>
            <li class="sidebar-item">
                <a href="voters.php" class="sidebar-link">
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
                    <h2 class="page-title">Manage Positions</h2>
                    <p class="welcome-subtitle mb-0">Create and organize election roles and candidate vote allowances</p>
                </div>
            </div>
            <div class="d-flex align-items-center gap-2">
                <button class="theme-toggle-btn" onclick="toggleTheme()">
                    <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
                    <span id="themeText">Dark Mode</span>
                </button>
            </div>
        </div>

        <?php if($message){ ?>
            <div class="alert alert-success rounded-3 border-0 shadow-sm d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-check-circle-fill fs-5"></i>
                <div><?= htmlspecialchars($message) ?></div>
            </div>
        <?php } ?>

        <!-- ADD POSITION FORM -->
        <div class="admin-card mb-4">
            <h5 class="fw-bold mb-3"><i class="bi bi-plus-circle-fill text-primary me-2"></i>Add New Position</h5>
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Position Name</label>
                        <input
                            type="text"
                            name="position_name"
                            class="form-control custom-input"
                            placeholder="e.g. Board of Director, Auditor, Committee Member"
                            required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label">Vote Limit</label>
                        <input
                            type="number"
                            name="vote_limit"
                            class="form-control custom-input"
                            placeholder="Maximum choices allowed"
                            required
                            min="1"
                            value="1">
                    </div>
                </div>

                <div class="mt-4 pt-3 border-top d-flex justify-content-end" style="border-color: var(--border-color) !important;">
                    <button
                        type="submit"
                        name="add_position"
                        class="btn btn-primary rounded-3 px-4 fw-semibold d-inline-flex align-items-center gap-2">
                        <i class="bi bi-plus-lg"></i> Add Position
                    </button>
                </div>
            </form>
        </div>

        <!-- POSITION OVERVIEW LIST -->
        <div class="admin-card">
            <h5 class="fw-bold mb-1"><i class="bi bi-award-fill text-primary me-2"></i>Position Overview List</h5>
            <p class="text-secondary small mb-3">Configured positions and active voter ballot limits</p>

            <div class="table-responsive">
                <table class="table custom-table align-middle">
                    <thead>
                        <tr>
                            <th width="100">ID</th>
                            <th>Position Name</th>
                            <th>Vote Limit</th>
                            <th width="160" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php while($row = $positions->fetch_assoc()){ ?>
                        <tr>
                            <td class="fw-bold text-secondary">#<?= $row['id']; ?></td>
                            <td class="fw-bold"><?= htmlspecialchars($row['position_name']); ?></td>
                            <td>
                                <span class="badge bg-primary bg-opacity-10 text-primary border border-primary border-opacity-20 rounded-pill px-3 py-1">
                                    <?= $row['vote_limit']; ?> Candidate(s) Max
                                </span>
                            </td>
                            <td>
                                <div class="d-flex justify-content-end gap-2">
                                    <button 
                                        type="button" 
                                        class="btn btn-outline-warning btn-sm rounded-3 px-2 py-1"
                                        onclick="openEditModal(<?= htmlspecialchars(json_encode($row)); ?>)"
                                        title="Edit">
                                        <i class="bi bi-pencil-fill"></i>
                                    </button>
                                    <a
                                        href="?delete=<?= $row['id']; ?>"
                                        class="btn btn-outline-danger btn-sm rounded-3 px-2 py-1"
                                        onclick="return confirm('Delete this position permanently?');"
                                        title="Delete">
                                        <i class="bi bi-trash-fill"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    <?php } ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

</div>

<!-- EDIT POSITION MODAL -->
<div class="modal fade" id="editPositionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Position Information</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <div class="modal-body p-4">
          <input type="hidden" name="id" id="edit_id">
          
          <div class="mb-3">
            <label class="form-label">Position Name</label>
            <input type="text" name="position_name" id="edit_name" class="form-control custom-input" required>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Vote Limit</label>
            <input type="number" name="vote_limit" id="edit_vote_limit" class="form-control custom-input" min="1" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary rounded-3 btn-sm fw-semibold" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="update_position" class="btn btn-primary rounded-3 btn-sm fw-semibold">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const bootstrapEditModal = new bootstrap.Modal(document.getElementById('editPositionModal'));

function openEditModal(positionData) {
    document.getElementById('edit_id').value = positionData.id;
    document.getElementById('edit_name').value = positionData.position_name;
    document.getElementById('edit_vote_limit').value = positionData.vote_limit;
    
    bootstrapEditModal.show();
}

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