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
| SYNC BRANCHES FROM MEMBERS
|--------------------------------------------------------------------------
*/

$branch_col = null;
$cols = $conn->query("SHOW COLUMNS FROM members");
if ($cols) {
    while ($c = $cols->fetch_assoc()) {
        if (in_array(strtolower($c['Field']), ['branch_name', 'branch'])) {
            $branch_col = $c['Field'];
            break;
        }
    }
}

if ($branch_col) {
    $member_branches = $conn->query("
        SELECT DISTINCT `{$branch_col}` AS branch_name
        FROM members
        WHERE `{$branch_col}` IS NOT NULL
        AND `{$branch_col}` <> ''
    ");

    if ($member_branches) {
        while($branch = $member_branches->fetch_assoc()){

            $branch_name = $branch['branch_name'];

            $check = $conn->prepare("
                SELECT id
                FROM election_schedules
                WHERE branch_name=?
            ");

            $check->bind_param(
                "s",
                $branch_name
            );

            $check->execute();

            $result = $check->get_result();

            if($result->num_rows == 0){

                $insert = $conn->prepare("
                    INSERT INTO election_schedules
                    (
                        branch_name,
                        status
                    )
                    VALUES
                    (
                        ?,
                        'CLOSED'
                    )
                ");

                $insert->bind_param(
                    "s",
                    $branch_name
                );

                $insert->execute();
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| TOGGLE OPEN / CLOSED
|--------------------------------------------------------------------------
*/

if(isset($_GET['toggle'])){

    $id = (int)$_GET['toggle'];

    $stmt = $conn->prepare("
        SELECT status
        FROM election_schedules
        WHERE id=?
    ");

    $stmt->bind_param(
        "i",
        $id
    );

    $stmt->execute();

    $result = $stmt->get_result();

    if($result->num_rows > 0){

        $row = $result->fetch_assoc();

        $new_status =
            ($row['status'] == 'OPEN')
            ? 'CLOSED'
            : 'OPEN';

        $update = $conn->prepare("
            UPDATE election_schedules
            SET status=?
            WHERE id=?
        ");

        $update->bind_param(
            "si",
            $new_status,
            $id
        );

        $update->execute();

        $message =
            "Branch voting status updated.";
    }

    header("Location: elections.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| LOAD BRANCHES
|--------------------------------------------------------------------------
*/

$schedules = $conn->query("
    SELECT *
    FROM election_schedules
    ORDER BY branch_name ASC
");

function esc($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Branch Voting Control - PMPC Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            color-scheme: dark;
            --bg: #090d16;
            --surface: #111827;
            --text: #f3f4f6;
            --muted: #9ca3af;
            --border: #1f2937;
            --card: #111827;
            --surface-strong: #1f2937;
            --btn-bg: #3b82f6;
            --btn-color: #ffffff;
            --link: #60a5fa;
            --table-row-bg: #111827;
            --table-row-text: #f3f4f6;
            --sidebar-bg: #090d16;
            --sidebar-border: #1f2937;
            --sidebar-hover: #161e2e;
            --sidebar-active: #1d4ed8;
            --sidebar-active-text: #ffffff;
            --bs-table-color: #f3f4f6;
            --bs-table-bg: #111827;
        }
        body.light-mode {
            color-scheme: light;
            --bg: #f8fafc;
            --surface: #ffffff;
            --text: #0f172a;
            --muted: #64748b;
            --border: #e2e8f0;
            --card: #ffffff;
            --surface-strong: #f1f5f9;
            --btn-bg: #2563eb;
            --btn-color: #ffffff;
            --link: #2563eb;
            --table-row-bg: #ffffff;
            --table-row-text: #0f172a;
            --sidebar-bg: #ffffff;
            --sidebar-border: #e2e8f0;
            --sidebar-hover: #f8fafc;
            --sidebar-active: #eff6ff;
            --sidebar-active-text: #2563eb;
            --bs-table-color: #0f172a;
            --bs-table-bg: #ffffff;
        }
        body {
            font-family: "Inter", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }

        /* App Layout with Sidebar */
        .app-layout {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 270px;
            background: var(--sidebar-bg);
            border-right: 1px solid var(--sidebar-border);
            display: flex;
            flex-direction: column;
            position: fixed;
            top: 0;
            bottom: 0;
            left: 0;
            z-index: 100;
            transition: background 0.25s ease, border-color 0.25s ease;
        }
        .sidebar-brand {
            padding: 1.75rem 1.25rem 1.25rem 1.25rem;
            display: flex;
            align-items: center;
            gap: 0.85rem;
            border-bottom: 1px solid var(--sidebar-border);
        }
        .sidebar-logo {
            width: 42px;
            height: 42px;
            background: var(--surface-strong);
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--link);
            font-size: 1.35rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
        }
        .sidebar-brand-text .brand-title {
            font-size: 1.05rem;
            font-weight: 700;
            color: var(--text);
            line-height: 1.2;
        }
        .sidebar-brand-text .brand-subtitle {
            font-size: 0.75rem;
            color: var(--muted);
            margin-top: 0.1rem;
        }
        .sidebar-menu-category {
            padding: 1.5rem 1.25rem 0.5rem 1.25rem;
            font-size: 0.7rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: var(--muted);
        }
        .sidebar-menu {
            padding: 0 0.85rem;
            list-style: none;
            margin: 0;
            flex-grow: 1;
            overflow-y: auto;
        }
        .sidebar-menu li {
            margin-bottom: 0.35rem;
        }
        .sidebar-menu a {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            padding: 0.8rem 1rem;
            color: var(--muted);
            text-decoration: none;
            font-weight: 500;
            font-size: 0.9rem;
            border-radius: 0.75rem;
            transition: all 0.2s ease;
        }
        .sidebar-menu a:hover {
            background: var(--sidebar-hover);
            color: var(--text);
            transform: translateX(2px);
        }
        .sidebar-menu a.active {
            background: var(--sidebar-active);
            color: var(--sidebar-active-text);
            font-weight: 600;
            box-shadow: 0 4px 12px rgba(37, 99, 235, 0.2);
        }
        .sidebar-menu a.logout-link {
            color: #ef4444;
            margin-top: auto;
            margin-bottom: 1.5rem;
        }
        .sidebar-menu a.logout-link:hover {
            background: rgba(239, 68, 68, 0.1);
            color: #ef4444;
        }
        .sidebar-menu a i {
            font-size: 1.15rem;
        }

        /* Main Content wrapper */
        .main-content {
            flex-grow: 1;
            margin-left: 270px;
            padding: 2.5rem;
            min-width: 0;
        }

        .page-header {
            padding-bottom: 1.5rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border);
        }
        .page-title {
            font-weight: 700;
            letter-spacing: -0.025em;
            color: var(--text);
            font-size: 1.85rem;
        }
        .section-subtitle {
            font-size: 0.95rem;
            color: var(--muted);
            margin-top: 0.35rem;
        }

        /* Buttons */
        .btn {
            border-radius: 0.75rem;
            font-weight: 500;
            padding: 0.6rem 1.25rem;
            font-size: 0.925rem;
            transition: all 0.2s ease;
        }
        .btn-theme {
            border-color: var(--border);
            color: var(--text);
            background: var(--surface);
        }
        .btn-theme:hover {
            background: var(--surface-strong);
            color: var(--text);
        }
        .btn-secondary {
            color: var(--text) !important;
            background-color: var(--surface-strong) !important;
            border-color: var(--border) !important;
        }
        .btn-secondary:hover {
            background-color: var(--border) !important;
        }
        .btn-primary {
            color: var(--btn-color) !important;
            background-color: var(--btn-bg) !important;
            border-color: var(--btn-bg) !important;
            box-shadow: 0 2px 4px rgba(59, 130, 246, 0.2);
        }
        .btn-primary:hover {
            opacity: 0.92;
            transform: translateY(-1px);
        }

        /* Cards */
        .card {
            background-color: var(--card) !important;
            border: 1px solid var(--border) !important;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.03);
            color: var(--text) !important;
            margin-bottom: 1.75rem;
        }
        .card-body {
            padding: 1.75rem;
            background-color: transparent !important;
        }
        .card-title {
            font-weight: 700;
            color: var(--text) !important;
            font-size: 1.2rem;
            letter-spacing: -0.01em;
        }

        /* Tables & Visibility Fixes */
        .table {
            --bs-table-bg: var(--table-row-bg) !important;
            --bs-table-color: var(--table-row-text) !important;
            --bs-table-border-color: var(--border) !important;
            color: var(--table-row-text) !important;
            background-color: var(--table-row-bg) !important;
            margin-bottom: 0;
            vertical-align: middle;
        }
        .table th, .table td {
            color: var(--table-row-text) !important;
            background-color: var(--table-row-bg) !important;
            border-color: var(--border) !important;
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
            padding: 1rem 1.15rem;
            font-size: 0.925rem;
        }
        .table thead th {
            border-bottom: 2px solid var(--border) !important;
            background-color: var(--table-row-bg) !important;
            color: var(--muted) !important;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.06em;
        }
        .table tbody tr {
            background-color: var(--table-row-bg) !important;
            transition: background-color 0.15s ease;
        }
        .table-hover tbody tr:hover td {
            background-color: var(--surface-strong) !important;
            color: var(--text) !important;
        }

        /* Status Badges */
        .badge-open {
            background: rgba(16, 185, 129, 0.15);
            color: #10b981;
            padding: 0.35rem 0.75rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.8rem;
            border: 1px solid rgba(16, 185, 129, 0.3);
            display: inline-block;
        }
        .badge-closed {
            background: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            padding: 0.35rem 0.75rem;
            border-radius: 0.5rem;
            font-weight: 600;
            font-size: 0.8rem;
            border: 1px solid rgba(239, 68, 68, 0.3);
            display: inline-block;
        }

        @media (max-width: 992px) {
            .sidebar {
                width: 76px;
            }
            .sidebar .sidebar-brand-text,
            .sidebar .sidebar-menu-category,
            .sidebar .sidebar-menu span {
                display: none;
            }
            .sidebar-brand {
                justify-content: center;
                padding: 1.25rem 0.5rem;
            }
            .sidebar-menu {
                padding: 0 0.5rem;
            }
            .sidebar-menu a {
                justify-content: center;
                padding: 0.85rem;
            }
            .sidebar-menu a i {
                font-size: 1.35rem;
            }
            .main-content {
                margin-left: 76px;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>

<div class="app-layout">
    <!-- Sidebar Menu -->
    <nav class="sidebar">
        <div class="sidebar-brand">
            <div class="sidebar-logo">
                <i class="bi bi-shield-shaded"></i>
            </div>
            <div class="sidebar-brand-text">
                <div class="brand-title">PMPC Admin</div>
                <div class="brand-subtitle">Election System</div>
            </div>
        </div>
        <div class="sidebar-menu-category">Main Menu</div>
        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li>
                <a href="candidates.php">
                    <i class="bi bi-person-badge"></i>
                    <span>Candidates</span>
                </a>
            </li>
            <li>
                <a href="positions.php">
                    <i class="bi bi-trophy"></i>
                    <span>Positions</span>
                </a>
            </li>
            <li>
                <a href="voters.php">
                    <i class="bi bi-people-fill"></i>
                    <span>Voters</span>
                </a>
            </li>
            <li>
                <a href="pre-registered.php">
                    <i class="bi bi-card-checklist"></i>
                    <span>Pre-registered</span>
                </a>
            </li>
            <li>
                <a href="elections.php" class="active">
                    <i class="bi bi-building-gear"></i>
                    <span>Branch Control</span>
                </a>
            </li>
            <li>
                <a href="results.php">
                    <i class="bi bi-bar-chart-fill"></i>
                    <span>Results</span>
                </a>
            </li>
            <li>
                <a href="logout.php" class="logout-link">
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </nav>

    <!-- Main Content Area -->
    <main class="main-content">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="page-title mb-1">🗳 Branch Voting Control</h2>
                <div class="section-subtitle">Manage voting access status across all cooperative branches in real-time</div>
            </div>
            <div class="d-flex gap-2">
                <button id="theme-toggle" type="button" class="btn btn-theme btn-sm d-flex align-items-center gap-2">
                    <i class="bi bi-moon-stars"></i> Light Mode
                </button>
                <a href="dashboard.php" class="btn btn-secondary btn-sm d-flex align-items-center gap-2">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        <?php if (!empty($message)): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i><?= esc($message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>

        <!-- Branch Control Card -->
        <div class="card">
            <div class="card-body">
                <h5 class="card-title mb-3">Branch Voting Access Directory</h5>

                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th style="width: 80px;">ID</th>
                                <th>Branch Name</th>
                                <th>Status</th>
                                <th class="text-end" width="220">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php while($row = $schedules->fetch_assoc()){ ?>
                            <tr>
                                <td class="text-muted fw-semibold">#<?= (int)$row['id']; ?></td>
                                <td class="fw-medium"><?= esc($row['branch_name']); ?></td>
                                <td>
                                    <?php if($row['status'] == 'OPEN'){ ?>
                                        <span class="badge-open">OPEN</span>
                                    <?php } else { ?>
                                        <span class="badge-closed">CLOSED</span>
                                    <?php } ?>
                                </td>
                                <td class="text-end">
                                    <a href="?toggle=<?= (int)$row['id']; ?>" class="btn btn-sm <?= ($row['status']=='OPEN') ? 'btn-outline-warning' : 'btn-outline-success'; ?> py-1 px-3">
                                        <?= ($row['status']=='OPEN') ? '<i class="bi bi-lock-fill me-1"></i> Close Voting' : '<i class="bi bi-unlock-fill me-1"></i> Open Voting'; ?>
                                    </a>
                                </td>
                            </tr>
                        <?php } ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    const themeToggle = document.getElementById('theme-toggle');
    const getCookie = name => document.cookie.split('; ').find(row => row.startsWith(name + '='))?.split('=')[1];
    const setCookie = (name, value, days = 365) => {
        const expires = new Date(Date.now() + days * 864e5).toUTCString();
        document.cookie = `${name}=${value}; expires=${expires}; path=/`;
    };

    const getSavedTheme = () => {
        try {
            const localTheme = localStorage.getItem('adminTheme');
            if (localTheme) return localTheme;
        } catch (e) {}
        return getCookie('adminTheme') || 'dark';
    };

    const saveTheme = theme => {
        try {
            localStorage.setItem('adminTheme', theme);
        } catch (e) {}
        setCookie('adminTheme', theme, 365);
    };

    const setTheme = theme => {
        document.body.classList.toggle('light-mode', theme === 'light');
        if (themeToggle) {
            themeToggle.innerHTML = theme === 'light' ? '<i class="bi bi-moon-stars"></i> Dark Mode' : '<i class="bi bi-sun-fill text-warning"></i> Light Mode';
        }
        saveTheme(theme);
    };

    if (themeToggle) {
        setTheme(getSavedTheme());
        themeToggle.addEventListener('click', () => {
            setTheme(document.body.classList.contains('light-mode') ? 'dark' : 'light');
        });
    }
})();
</script>
</body>
</html>