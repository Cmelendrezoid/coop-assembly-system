<?php
require_once 'session_start.php';
include '../config/db.php';

if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit();
}

// 1. Fetch distinct branches from the members table using the correct 'branch_name' column
$branches_query = $conn->query("SELECT DISTINCT branch_name FROM members WHERE branch_name IS NOT NULL AND branch_name != '' ORDER BY branch_name ASC");
$branches = [];
if($branches_query) {
    while($b_row = $branches_query->fetch_assoc()) {
        $branches[] = $b_row['branch_name'];
    }
}

// 2. Identify current active filter selection context
$selected_branch = isset($_GET['branch']) ? trim($_GET['branch']) : 'overall';

function esc($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Election Live Results - PMPC Admin</title>
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
            --modal-bg: #111827;
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
            --modal-bg: #ffffff;
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
        .card-header {
            background-color: var(--surface-strong) !important;
            border-bottom: 1px solid var(--border) !important;
            padding: 1.15rem 1.75rem;
            font-weight: 700;
            color: var(--text) !important;
            border-top-left-radius: 1rem !important;
            border-top-right-radius: 1rem !important;
        }
        .card-title {
            font-weight: 700;
            color: var(--text) !important;
            font-size: 1.2rem;
            letter-spacing: -0.01em;
        }

        /* Filter Select */
        .filter-select {
            border-radius: 0.75rem;
            padding: 0.6rem 1rem;
            font-weight: 500;
            background-color: var(--surface);
            color: var(--text);
            border: 1px solid var(--border);
            max-width: 280px;
        }
        .filter-select:focus {
            border-color: var(--btn-bg);
            box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15);
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

        /* Candidate Avatar & Custom Badge (Theme-adaptive Placeholder Fallback) */
        .avatar-wrapper {
            position: relative;
            display: inline-block;
        }
        .candidate-avatar {
            width: 44px;
            height: 44px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--border);
            background-color: var(--surface-strong);
            color: var(--muted);
        }
        .color-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            border: 2px solid var(--card);
            position: absolute;
            bottom: 0;
            right: -2px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.2);
        }
        .custom-badge {
            background-color: var(--surface-strong);
            color: var(--text);
            font-size: 0.85rem;
            font-weight: 600;
            border-radius: 0.5rem;
            padding: 0.35rem 0.75rem;
            border: 1px solid var(--border);
            display: inline-block;
        }

        .stats-card {
            background-color: var(--surface) !important;
            border: 1px solid var(--border) !important;
            border-radius: 1rem !important;
            transition: transform 0.2s ease;
        }
        .stats-card:hover {
            transform: translateY(-2px);
        }

        /* Modal Customization for Theme compatibility */
        .modal-content {
            background-color: var(--modal-bg) !important;
            color: var(--text) !important;
            border: 1px solid var(--border) !important;
            border-radius: 1rem !important;
        }
        .modal-header, .modal-footer {
            border-color: var(--border) !important;
        }
        .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
        }
        body.light-mode .btn-close {
            filter: none;
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
                <a href="elections.php">
                    <i class="bi bi-building-gear"></i>
                    <span>Branch Control</span>
                </a>
            </li>
            <li>
                <a href="results.php" class="active">
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
                <h2 class="page-title mb-1">🗳️ Election Live Results</h2>
                <div class="section-subtitle">Real-time candidate tally and cooperative voting turnout overview</div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Branch Filtering Selection Dropdown Component -->
                <select class="form-select filter-select" id="branchFilter" onchange="filterBranch(this.value)">
                    <option value="overall" <?php echo ($selected_branch === 'overall') ? 'selected' : ''; ?>>🌐 Overall Results</option>
                    <?php foreach($branches as $b): ?>
                        <option value="<?php echo esc($b); ?>" <?php echo ($selected_branch === $b) ? 'selected' : ''; ?>>
                            📍 Branch: <?php echo esc($b); ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <button id="theme-toggle" type="button" class="btn btn-theme btn-sm d-flex align-items-center gap-2">
                    <i class="bi bi-moon-stars"></i> Light Mode
                </button>
                <a href="dashboard.php" class="btn btn-secondary btn-sm d-flex align-items-center gap-2">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        <?php
        // Expanded, distinct, high-contrast color palette for candidates & charts
        $chart_colors = [
            '#3b82f6', '#10b981', '#f59e0b', '#ef4444', '#8b5cf6', 
            '#06b6d4', '#ec4899', '#f97316', '#6366f1', '#14b8a6', 
            '#eab308', '#f43f5e', '#a855f7', '#0ea5e9', '#d946ef', 
            '#d97706', '#0284c7', '#15803d', '#9333ea', '#e11d48'
        ];

        $positions = $conn->query("SELECT * FROM positions ORDER BY id");
        $chart_js_data = [];

        while($position = $positions->fetch_assoc()){
            $position_id = $position['id'];
            $position_name = $position['position_name'];
        ?>

        <div class="card card-custom shadow-sm mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0" style="font-size: 1.15rem;"><i class="bi bi-award-fill me-2 text-primary"></i><?php echo esc($position_name); ?></h4>
                <button type="button" class="btn btn-sm btn-theme py-1 px-3 d-flex align-items-center gap-1" data-bs-toggle="modal" data-bs-target="#maximizeModal<?php echo $position_id; ?>" title="Maximize View">
                    <i class="bi bi-arrows-fullscreen"></i> Maximize View
                </button>
            </div>
            <div class="card-body">
                <?php
                if($selected_branch !== 'overall') {
                    $escaped_branch = $conn->real_escape_string($selected_branch);
                    $candidates_query_str = "
                        SELECT
                            c.id,
                            c.full_name,
                            c.photo,
                            COUNT(CASE WHEN m.branch_name = '$escaped_branch' THEN v.id END) AS total_votes
                        FROM candidates c
                        LEFT JOIN votes v ON c.id = v.candidate_id
                        LEFT JOIN members m ON v.member_id = m.id
                        WHERE c.position_id = $position_id
                        GROUP BY c.id
                        ORDER BY total_votes DESC, c.full_name ASC";
                } else {
                    $candidates_query_str = "
                        SELECT
                            c.id,
                            c.full_name,
                            c.photo,
                            COUNT(v.id) AS total_votes
                        FROM candidates c
                        LEFT JOIN votes v ON c.id = v.candidate_id
                        WHERE c.position_id = $position_id
                        GROUP BY c.id
                        ORDER BY total_votes DESC, c.full_name ASC";
                }

                $candidates = $conn->query($candidates_query_str);

                if($candidates && $candidates->num_rows > 0){
                    $labels_array = [];
                    $votes_array = [];
                    $table_rows = [];

                    while($candidate = $candidates->fetch_assoc()){
                        $labels_array[] = $candidate['full_name'];
                        $votes_array[] = (int)$candidate['total_votes'];
                        $table_rows[] = $candidate;
                    }
                    
                    $chart_js_data[$position_id] = [
                        'labels' => $labels_array,
                        'votes' => $votes_array
                    ];
                ?>
                <div class="row align-items-center">
                    <div class="col-lg-7 mb-3 mb-lg-0">
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th style="width: 70px;">Photo</th>
                                        <th>Candidate Name</th>
                                        <th class="text-end" style="width: 160px;">Votes Received</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach($table_rows as $index => $candidate) { 
                                    $candidate_img_html = (!empty($candidate['photo']) && file_exists('../assets/images/' . $candidate['photo'])) 
                                        ? '<img src="../assets/images/' . esc($candidate['photo']) . '" alt="' . esc($candidate['full_name']) . '" class="candidate-avatar">'
                                        : '<div class="candidate-avatar d-flex align-items-center justify-content-center"><i class="bi bi-person-fill fs-5"></i></div>';
                                    $assigned_color = $chart_colors[$index % count($chart_colors)];
                                ?>
                                    <tr>
                                        <td>
                                            <div class="avatar-wrapper">
                                                <?php echo $candidate_img_html; ?>
                                                <span class="color-dot" style="background-color: <?php echo $assigned_color; ?>;" title="Chart Color"></span>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <span style="background-color: <?php echo $assigned_color; ?>; width: 10px; height: 10px; border-radius: 50%; display: inline-block;"></span>
                                                <span class="fw-semibold text-wrap"><?php echo esc($candidate['full_name']); ?></span>
                                            </div>
                                        </td>
                                        <td class="text-end">
                                            <span class="custom-badge">
                                                <strong><?php echo (int)$candidate['total_votes']; ?></strong> votes
                                            </span>
                                        </td>
                                    </tr>
                                <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="col-lg-5">
                        <div style="position: relative; height: 260px; width: 100%;">
                            <canvas id="chart_pos_<?php echo $position_id; ?>"></canvas>
                        </div>
                    </div>
                </div>
                <?php } else { ?>
                    <div class="text-muted py-3 text-center fst-italic">No candidates found registered for this position.</div>
                <?php } ?>
            </div>
        </div>

        <!-- Maximize View Modal for Each Position -->
        <div class="modal fade" id="maximizeModal<?php echo $position_id; ?>" tabindex="-1" aria-labelledby="maximizeModalLabel<?php echo $position_id; ?>" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-centered modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold" id="maximizeModalLabel<?php echo $position_id; ?>">
                            <i class="bi bi-award-fill me-2 text-primary"></i><?php echo esc($position_name); ?> — Detailed View
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body p-4">
                        <?php if($candidates && count(array_filter($table_rows)) > 0){ ?>
                        <div class="row align-items-center">
                            <div class="col-lg-7 mb-4 mb-lg-0">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle mb-0">
                                        <thead>
                                            <tr>
                                                <th style="width: 70px;">Photo</th>
                                                <th>Candidate Name</th>
                                                <th class="text-end" style="width: 160px;">Votes Received</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                        <?php foreach($table_rows as $index => $candidate) { 
                                            $candidate_img_html = (!empty($candidate['photo']) && file_exists('../assets/images/' . $candidate['photo'])) 
                                                ? '<img src="../assets/images/' . esc($candidate['photo']) . '" alt="' . esc($candidate['full_name']) . '" class="candidate-avatar">'
                                                : '<div class="candidate-avatar d-flex align-items-center justify-content-center"><i class="bi bi-person-fill fs-5"></i></div>';
                                            $assigned_color = $chart_colors[$index % count($chart_colors)];
                                        ?>
                                            <tr>
                                                <td>
                                                    <div class="avatar-wrapper">
                                                        <?php echo $candidate_img_html; ?>
                                                        <span class="color-dot" style="background-color: <?php echo $assigned_color; ?>;"></span>
                                                    </div>
                                                </td>
                                                <td>
                                                    <div class="d-flex align-items-center gap-2">
                                                        <span style="background-color: <?php echo $assigned_color; ?>; width: 10px; height: 10px; border-radius: 50%; display: inline-block;"></span>
                                                        <span class="fw-semibold"><?php echo esc($candidate['full_name']); ?></span>
                                                    </div>
                                                </td>
                                                <td class="text-end">
                                                    <span class="custom-badge">
                                                        <strong><?php echo (int)$candidate['total_votes']; ?></strong> votes
                                                    </span>
                                                </td>
                                            </tr>
                                        <?php } ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            <div class="col-lg-5">
                                <div style="position: relative; height: 350px; width: 100%;">
                                    <canvas id="chart_modal_pos_<?php echo $position_id; ?>"></canvas>
                                </div>
                            </div>
                        </div>
                        <?php } else { ?>
                            <div class="text-muted py-4 text-center fst-italic">No candidates available for this position.</div>
                        <?php } ?>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
                    </div>
                </div>
            </div>
        </div>
        <?php } ?>

        <!-- Turnout Statistics Card -->
        <div class="card shadow-sm mt-4">
            <div class="card-header">
                <h4 class="mb-0" style="font-size: 1.15rem;"><i class="bi bi-graph-up-arrow me-2 text-success"></i>Election Turnout Statistics</h4>
            </div>
            <div class="card-body">
                <?php
                if($selected_branch !== 'overall') {
                    $escaped_branch = $conn->real_escape_string($selected_branch);
                    $total_voters = $conn->query("SELECT COUNT(*) AS total FROM members WHERE branch_name = '$escaped_branch'")->fetch_assoc()['total'];
                    
                    $total_votes = $conn->query("
                        SELECT COUNT(v.id) AS total 
                        FROM votes v 
                        INNER JOIN members m ON v.member_id = m.id 
                        WHERE m.branch_name = '$escaped_branch'
                    ")->fetch_assoc()['total'];

                    if($conn->query("SHOW COLUMNS FROM members LIKE 'has_voted'")->num_rows > 0){
                        $voted_members = $conn->query("SELECT COUNT(*) AS total FROM members WHERE has_voted = 1 AND branch_name = '$escaped_branch'")->fetch_assoc()['total'];
                    } else {
                        $voted_members = $conn->query("
                            SELECT COUNT(DISTINCT v.member_id) AS total 
                            FROM votes v
                            INNER JOIN members m ON v.member_id = m.id
                            WHERE m.branch_name = '$escaped_branch'
                        ")->fetch_assoc()['total'];
                    }
                } else {
                    $total_voters = $conn->query("SELECT COUNT(*) AS total FROM members")->fetch_assoc()['total'];
                    $total_votes = $conn->query("SELECT COUNT(*) AS total FROM votes")->fetch_assoc()['total'];

                    if($conn->query("SHOW COLUMNS FROM members LIKE 'has_voted'")->num_rows > 0){
                        $voted_members = $conn->query("SELECT COUNT(*) AS total FROM members WHERE has_voted = 1")->fetch_assoc()['total'];
                    } else {
                        $voted_members = $conn->query("SELECT COUNT(DISTINCT member_id) AS total FROM votes")->fetch_assoc()['total'];
                    }
                }

                $turnout_rate = ($total_voters > 0) ? round(($voted_members / $total_voters) * 100, 1) : 0;
                ?>
                <div class="row g-4">
                    <div class="col-md-4">
                        <div class="card stats-card text-center h-100 mb-0">
                            <div class="card-body py-4">
                                <h2 class="text-primary fw-bold mb-1"><?php echo number_format($total_voters); ?></h2>
                                <p class="mb-0 fw-semibold small uppercase" style="color: #cbd5e1;">Total Members Registered</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card text-center h-100 mb-0">
                            <div class="card-body py-4">
                                <h2 class="text-success fw-bold mb-1"><?php echo number_format($voted_members); ?> <span class="fs-5 fw-normal" style="color: #cbd5e1;">(<?php echo $turnout_rate; ?>%)</span></h2>
                                <p class="mb-0 fw-semibold small uppercase" style="color: #cbd5e1;">Voters Checked In</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card stats-card text-center h-100 mb-0">
                            <div class="card-body py-4">
                                <h2 class="text-info fw-bold mb-1"><?php echo number_format($total_votes); ?></h2>
                                <p class="mb-0 fw-semibold small uppercase" style="color: #cbd5e1;">Aggregated Total Ballots Cast</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartInstances = {};
const modalChartInstances = {};
const chartColors = <?php echo json_encode($chart_colors); ?>;
const rawChartData = <?php echo json_encode($chart_js_data); ?>;

function filterBranch(val) {
    if (val === 'overall') {
        window.location.href = 'results.php';
    } else {
        window.location.href = 'results.php?branch=' + encodeURIComponent(val);
    }
}

function getChartTextColor() {
    return document.body.classList.contains('light-mode') ? '#0f172a' : '#f3f4f6';
}

function getChartBorderColor() {
    return document.body.classList.contains('light-mode') ? '#ffffff' : '#111827';
}

function getResponsiveLegendPosition() {
    return window.innerWidth < 576 ? 'bottom' : 'right';
}

function buildCharts() {
    Object.keys(rawChartData).forEach(positionId => {
        // Main Card Chart
        const canvasElement = document.getElementById(`chart_pos_${positionId}`);
        if(canvasElement) {
            const ctx = canvasElement.getContext('2d');
            const dataSet = rawChartData[positionId];
            const cumulativeVotes = dataSet.votes.reduce((sum, val) => sum + val, 0);
            
            chartInstances[positionId] = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: dataSet.labels,
                    datasets: [{
                        data: dataSet.votes,
                        backgroundColor: chartColors,
                        borderColor: getChartBorderColor(),
                        borderWidth: 2,
                        hoverOffset: 4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: getResponsiveLegendPosition(),
                            labels: {
                                color: getChartTextColor(),
                                font: { family: 'Inter', size: 11, weight: '500' },
                                padding: 10,
                                boxWidth: 12
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    let label = context.label || '';
                                    let value = context.raw || 0;
                                    let percentage = cumulativeVotes > 0 ? ((value / cumulativeVotes) * 100).toFixed(1) : 0;
                                    return ` ${label}: ${value} vote(s) (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }

        // Modal Maximize Chart initialization on show
        const modalElement = document.getElementById(`maximizeModal${positionId}`);
        if(modalElement) {
            modalElement.addEventListener('shown.bs.modal', function () {
                const modalCanvas = document.getElementById(`chart_modal_pos_${positionId}`);
                if(modalCanvas && !modalChartInstances[positionId]) {
                    const mCtx = modalCanvas.getContext('2d');
                    const dataSet = rawChartData[positionId];
                    const cumulativeVotes = dataSet.votes.reduce((sum, val) => sum + val, 0);

                    modalChartInstances[positionId] = new Chart(mCtx, {
                        type: 'doughnut',
                        data: {
                            labels: dataSet.labels,
                            datasets: [{
                                data: dataSet.votes,
                                backgroundColor: chartColors,
                                borderColor: getChartBorderColor(),
                                borderWidth: 2,
                                hoverOffset: 6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        color: getChartTextColor(),
                                        font: { family: 'Inter', size: 12, weight: '500' },
                                        padding: 14,
                                        boxWidth: 14
                                    }
                                },
                                tooltip: {
                                    callbacks: {
                                        label: function(context) {
                                            let label = context.label || '';
                                            let value = context.raw || 0;
                                            let percentage = cumulativeVotes > 0 ? ((value / cumulativeVotes) * 100).toFixed(1) : 0;
                                            return ` ${label}: ${value} vote(s) (${percentage}%)`;
                                        }
                                    }
                                }
                            }
                        }
                    });
                } else if(modalChartInstances[positionId]) {
                    modalChartInstances[positionId].options.plugins.legend.labels.color = getChartTextColor();
                    modalChartInstances[positionId].data.datasets[0].borderColor = getChartBorderColor();
                    modalChartInstances[positionId].update();
                }
            });
        }
    });
}

function updateChartThemes() {
    const textColor = getChartTextColor();
    const borderColor = getChartBorderColor();
    const legendPosition = getResponsiveLegendPosition();
    
    Object.keys(chartInstances).forEach(id => {
        const chart = chartInstances[id];
        chart.options.plugins.legend.labels.color = textColor;
        chart.options.plugins.legend.position = legendPosition;
        chart.data.datasets[0].borderColor = borderColor;
        chart.update();
    });

    Object.keys(modalChartInstances).forEach(id => {
        const mChart = modalChartInstances[id];
        mChart.options.plugins.legend.labels.color = textColor;
        mChart.data.datasets[0].borderColor = borderColor;
        mChart.update();
    });
}

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
        updateChartThemes();
    };

    if (themeToggle) {
        setTheme(getSavedTheme());
        themeToggle.addEventListener('click', () => {
            setTheme(document.body.classList.contains('light-mode') ? 'dark' : 'light');
        });
    }

    window.addEventListener('resize', () => {
        updateChartThemes();
    });

    window.addEventListener('DOMContentLoaded', () => {
        buildCharts();
    });
})();
</script>
</body>
</html>