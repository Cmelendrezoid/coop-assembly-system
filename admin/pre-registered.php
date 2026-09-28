<?php
require_once 'session_start.php';
include '../config/db.php';

// Input params for sorting and filtering
$sort_by = $_GET['sort_by'] ?? 'count'; // 'count' or 'name'
$order = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$branch_filter = isset($_GET['branch']) ? trim($_GET['branch']) : (isset($_GET['branch_name']) ? trim($_GET['branch_name']) : null);

// Total pre-registered (printed = 1 OR registered = 1) members
$total_pre = (int)$conn->query("SELECT COUNT(*) AS total FROM members WHERE printed = 1 OR registered = 1")->fetch_assoc()['total'];
$total_unreg = (int)($conn->query("SELECT COUNT(*) FROM members WHERE (printed IS NULL OR printed != 1) AND (registered IS NULL OR registered != 1)")->fetch_row()[0]);

// Branch breakdown based on member branch_name values
$order_clause = $sort_by === 'name' ? "branch_display $order" : "pre_registered_count $order";
$branches_q = $conn->query(
    "SELECT TRIM(branch_name) AS branch_display,
            SUM(CASE WHEN printed = 1 OR registered = 1 THEN 1 ELSE 0 END) AS pre_registered_count,
            SUM(CASE WHEN (printed IS NULL OR printed != 1) AND (registered IS NULL OR registered != 1) THEN 1 ELSE 0 END) AS unregistered_count
     FROM members
     WHERE branch_name IS NOT NULL AND TRIM(branch_name) <> ''
     GROUP BY branch_display
     ORDER BY " . $order_clause
);

$branches = [];
while ($row = $branches_q->fetch_assoc()) {
    $branches[] = $row;
}

// Members list query
$members_sql = "SELECT id, full_name, migs_category, branch_name, printed_at, registered_at
                FROM members
                WHERE printed = 1 OR registered = 1";

$selected_branch_info = null;
if ($branch_filter !== null && $branch_filter !== '') {
    $branch_safe = $conn->real_escape_string($branch_filter);
    $members_sql .= " AND branch_name = '" . $branch_safe . "'";
    $selected_branch_info = $branch_filter;
}

$members_sql .= " ORDER BY COALESCE(printed_at, registered_at) DESC, full_name ASC LIMIT 1000";
$members_q = $conn->query($members_sql);

$selected_branch_stats = null;
if ($selected_branch_info !== null) {
    $safe = $conn->real_escape_string($selected_branch_info);
    $selected_branch_stats = $conn->query(
        "SELECT SUM(CASE WHEN printed = 1 OR registered = 1 THEN 1 ELSE 0 END) AS pre_registered_count,
                SUM(CASE WHEN (printed IS NULL OR printed != 1) AND (registered IS NULL OR registered != 1) THEN 1 ELSE 0 END) AS unregistered_count
         FROM members
         WHERE branch_name = '" . $safe . "'"
    )->fetch_assoc();
}

function esc($s) { return htmlspecialchars($s ?? ''); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pre-registered Members Dashboard - PMPC Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            color-scheme: dark;
            --bg: #0b0f19;
            --surface: #111827;
            --text: #f9fafb;
            --muted: #9ca3af;
            --border: #1f2937;
            --card: #111827;
            --surface-strong: #1f2937;
            --btn-bg: #2563eb;
            --btn-color: #ffffff;
            --link: #60a5fa;
            --table-row-bg: #111827;
            --table-row-text: #f9fafb;
            --sidebar-bg: #0b0f19;
            --sidebar-border: #1f2937;
            --sidebar-hover: #1f2937;
            --sidebar-active: #2563eb;
            --sidebar-active-text: #ffffff;
            --bs-table-color: #f9fafb;
            --bs-table-bg: #111827;
        }
        body.light-mode {
            color-scheme: light;
            --bg: #f8fafc;
            --surface: #ffffff;
            --text: #0f172a;
            --muted: #475569;
            --border: #cbd5e1;
            --card: #ffffff;
            --surface-strong: #f1f5f9;
            --btn-bg: #2563eb;
            --btn-color: #ffffff;
            --link: #2563eb;
            --table-row-bg: #ffffff;
            --table-row-text: #0f172a;
            --sidebar-bg: #ffffff;
            --sidebar-border: #e2e8f0;
            --sidebar-hover: #f1f5f9;
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

        /* Shared Admin Layout */
        .app-layout {
            display: flex;
            min-height: 100vh;
        }

        /* Main Content wrapper */
        .main-content {
            flex-grow: 1;
            margin-left: 250px;
            padding: 2rem;
            width: calc(100% - 250px);
            max-width: calc(100% - 250px);
            box-sizing: border-box;
            min-width: 0;
        }

        .page-header {
            padding-bottom: 1.25rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid var(--border);
        }
        .page-title {
            font-weight: 700;
            letter-spacing: -0.025em;
            color: var(--text);
            font-size: 1.75rem;
        }
        .section-subtitle {
            font-size: 0.95rem;
            color: var(--muted);
            margin-top: 0.25rem;
        }
        
        /* Modern KPI Summary Cards */
        .summary-card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        .summary-icon {
            width: 48px;
            height: 48px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
        }
        .icon-success { background: rgba(16, 185, 129, 0.1); color: #10b981; }
        .icon-danger { background: rgba(239, 68, 68, 0.1); color: #ef4444; }
        .summary-content .small {
            color: var(--muted);
            font-weight: 500;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }
        .summary-content .h3 {
            font-weight: 700;
            color: var(--text);
            margin-bottom: 0;
            font-size: 1.8rem;
        }

        /* Filter Panel */
        .filter-panel {
            background: var(--surface-strong);
            border: 1px solid var(--border);
            border-radius: 1rem;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        .filter-panel .form-label {
            color: var(--muted);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.03em;
        }
        .form-select,
        .form-control {
            color: var(--text) !important;
            background: var(--surface) !important;
            border-color: var(--border) !important;
            border-radius: 0.50rem;
            padding: 0.5rem 0.75rem;
            font-size: 0.9rem;
        }
        .form-select:focus,
        .form-control:focus {
            border-color: var(--btn-bg) !important;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
        }

        /* Buttons */
        .btn {
            border-radius: 0.5rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
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
        }
        .btn-primary:hover {
            opacity: 0.9;
        }

        /* Cards */
        .card {
            background-color: var(--card) !important;
            border: 1px solid var(--border) !important;
            border-radius: 1rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.02);
            color: var(--text) !important;
            margin-bottom: 1.5rem;
        }
        .card-body {
            padding: 1.5rem;
            background-color: transparent !important;
        }
        .card-title {
            font-weight: 600;
            color: var(--text) !important;
            font-size: 1.15rem;
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
            padding: 0.85rem 1rem;
            font-size: 0.9rem;
        }
        .table thead th {
            border-bottom: 2px solid var(--border) !important;
            background-color: var(--table-row-bg) !important;
            color: var(--muted) !important;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 0.05em;
        }
        .table tbody tr {
            background-color: var(--table-row-bg) !important;
        }
        .table-hover tbody tr:hover td {
            background-color: var(--surface-strong) !important;
            color: var(--text) !important;
        }

        /* Breakdown Elements */
        .breakdown-card {
            background: var(--surface-strong) !important;
            padding: 1.5rem;
            border: 1px solid var(--border) !important;
            border-radius: 1rem;
        }
        .detail-box {
            background: var(--surface) !important;
            border: 1px solid var(--border) !important;
            border-radius: 0.75rem;
            padding: 1rem;
            text-align: center;
        }
        .detail-box .small {
            color: var(--muted) !important;
            font-size: 0.8rem;
            font-weight: 500;
        }
        .detail-box .h4 {
            font-weight: 700;
            color: var(--text);
            margin-top: 0.25rem;
        }
        .pie-chart-container {
            max-width: 280px;
            margin: 0 auto;
        }
        
        .text-muted { color: var(--muted) !important; }
        a { color: var(--link); text-decoration: none; }
        a:hover { text-decoration: underline; }

        @media (max-width: 991.98px) {
            .main-content {
                margin-left: 0;
                width: 100%;
                max-width: 100%;
                padding: 4.5rem 1rem 1.5rem;
            }
        }
    </style>
</head>
<body>

<div class="app-layout">
    <?php include 'sidebar.php'; ?>

<main class="main-content">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="page-title mb-1">Pre-registered & Printed Members</h2>
                <div class="section-subtitle">Branch summaries, real-time metrics, and comprehensive registration lists</div>
            </div>
            <div class="d-flex gap-2">
                <button id="theme-toggle" type="button" class="btn btn-theme btn-sm d-flex align-items-center gap-2">
                    <i class="bi bi-moon-stars"></i> Light Mode
                </button>
                <a href="dashboard.php" class="btn btn-secondary btn-sm d-flex align-items-center gap-2">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>

        <!-- Quick Global Metrics -->
        <div class="row g-4 mb-4">
            <div class="col-md-6">
                <div class="summary-card">
                    <div class="summary-icon icon-success">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div class="summary-content">
                        <div class="small">Total Pre-registered (Printed/Registered)</div>
                        <div class="h3"><?= number_format($total_pre) ?></div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="summary-card">
                    <div class="summary-icon icon-danger">
                        <i class="bi bi-hourglass-split"></i>
                    </div>
                    <div class="summary-content">
                        <div class="small">Total Unregistered Pool</div>
                        <div class="h3"><?= number_format($total_unreg) ?></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Branch Breakdown & Controls Card -->
        <div class="card mb-4">
            <div class="card-body">
                <div class="filter-panel">
                    <div class="d-flex justify-content-between align-items-center mb-0 flex-wrap gap-3">
                        <div>
                            <h5 class="card-title mb-1">Branch Breakdown & Filters</h5>
                            <?php if ($selected_branch_info !== null): ?>
                                <div class="small text-muted">Active filter: <span class="fw-semibold text-primary"><?= htmlspecialchars($selected_branch_info) ?></span></div>
                            <?php endif; ?>
                        </div>
                        <form method="get" class="d-flex gap-2 align-items-center flex-wrap">
                            <div>
                                <label class="form-label mb-1">Branch</label>
                                <select name="branch" class="form-select form-select-sm" style="min-width:180px;">
                                    <option value="">All Branches</option>
                                    <?php foreach ($branches as $row): ?>
                                        <option value="<?= esc($row['branch_display']) ?>" <?= $selected_branch_info === $row['branch_display'] ? 'selected' : '' ?>><?= esc($row['branch_display']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label class="form-label mb-1">Sort By</label>
                                <select name="sort_by" class="form-select form-select-sm" style="width:130px;">
                                    <option value="count" <?= $sort_by === 'count' ? 'selected' : '' ?>>Count</option>
                                    <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>Name</option>
                                </select>
                            </div>
                            <div>
                                <label class="form-label mb-1">Order</label>
                                <select name="order" class="form-select form-select-sm" style="width:130px;">
                                    <option value="desc" <?= $order === 'DESC' ? 'selected' : '' ?>>Descending</option>
                                    <option value="asc" <?= $order === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                                </select>
                            </div>
                            <div class="d-flex align-items-end" style="padding-top: 1.5rem;">
                                <button class="btn btn-primary btn-sm px-3" type="submit">
                                    <i class="bi bi-filter me-1"></i> Apply
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="table-responsive mt-3">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>Branch Name</th>
                                <th class="text-end">Pre-registered</th>
                                <th class="text-end">Unregistered</th>
                                <th class="text-end">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($branches as $row): ?>
                            <tr>
                                <td class="fw-medium"><?= esc($row['branch_display']) ?></td>
                                <td class="text-end fw-semibold text-success"><?= number_format((int)$row['pre_registered_count']) ?></td>
                                <td class="text-end text-muted"><?= number_format((int)$row['unregistered_count']) ?></td>
                                <td class="text-end">
                                    <a href="pre-registered.php?branch=<?= rawurlencode($row['branch_display']) ?>&sort_by=<?= esc($sort_by) ?>&order=<?= esc(strtolower($order)) ?>#branchDetails" class="btn btn-sm btn-outline-primary py-1 px-2">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="row mt-4 g-4" id="branchDetails">
                    <div class="col-12">
                        <div class="breakdown-card">
                            <div class="row g-4 align-items-center">
                                <!-- Overall Pie Chart -->
                                <div class="<?= $selected_branch_info !== null ? 'col-lg-6' : 'col-12' ?>">
                                    <h6 class="mb-3 text-center fw-bold">Overall System Breakdown</h6>
                                    <div class="row g-3 mb-3">
                                        <div class="col-6">
                                            <div class="detail-box">
                                                <div class="small">Pre-registered</div>
                                                <div class="h4 mb-0 text-success"><?= number_format($total_pre) ?></div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="detail-box">
                                                <div class="small">Unregistered</div>
                                                <div class="h4 mb-0 text-danger"><?= number_format($total_unreg) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="pie-chart-container">
                                        <canvas id="overallPieChart" height="220"></canvas>
                                    </div>
                                </div>

                                <!-- Selected Branch Pie Chart (If applicable) -->
                                <?php if ($selected_branch_info !== null): ?>
                                <div class="col-lg-6">
                                    <h6 class="mb-3 text-center fw-bold"><?= htmlspecialchars($selected_branch_info) ?> Breakdown</h6>
                                    <div class="row g-3 mb-3">
                                        <div class="col-6">
                                            <div class="detail-box">
                                                <div class="small">Pre-registered</div>
                                                <div class="h4 mb-0 text-success"><?= number_format((int)($selected_branch_stats['pre_registered_count'] ?? 0)) ?></div>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="detail-box">
                                                <div class="small">Unregistered</div>
                                                <div class="h4 mb-0 text-danger"><?= number_format((int)($selected_branch_stats['unregistered_count'] ?? 0)) ?></div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="pie-chart-container">
                                        <canvas id="selectedBranchPieChart" height="220"></canvas>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card border p-3 mb-0">
                            <h6 class="mb-3 fw-bold">Pre-registered vs Unregistered Comparison by Branch</h6>
                            <div style="position: relative; height:380px; width:100%">
                                <canvas id="branchBarChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Members List Card -->
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h5 class="card-title mb-0">Members Directory</h5>
                    <span class="badge bg-secondary text-light px-3 py-2">Showing up to 1,000 records</span>
                </div>

                <?php if ($members_q && $members_q->num_rows > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle">
                        <thead>
                            <tr>
                                <th style="width: 50px;">#</th>
                                <th>Full Name</th>
                                <th>Branch</th>
                                <th>Category</th>
                                <th>Printed / Registered At</th>
                                <th class="text-end">Profile</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; while ($m = $members_q->fetch_assoc()): ?>
                            <tr>
                                <td class="text-muted fw-semibold"><?= $i++ ?></td>
                                <td class="fw-medium"><?= esc($m['full_name']) ?></td>
                                <td><span class="badge bg-secondary bg-opacity-10 text-primary border"><?= esc($m['branch_name'] ?: 'Unassigned') ?></span></td>
                                <td class="text-muted"><?= esc($m['migs_category']) ?></td>
                                <td class="text-muted"><?= esc($m['printed_at'] ?: $m['registered_at']) ?></td>
                                <td class="text-end">
                                    <a class="btn btn-sm btn-outline-primary py-1 px-2" href="view.php?id=<?= (int)$m['id'] ?>">
                                        <i class="bi bi-person-badge"></i> View
                                    </a>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                    <div class="text-center py-5 text-muted">
                        <i class="bi bi-inbox display-4 d-block mb-2"></i>
                        <p class="mb-0">No pre-registered members found for the selected criteria.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </main>
</div>

<script>
function toggleMenu() {
    const sidebar = document.getElementById('sidebarNav');
    const overlay = document.getElementById('sidebarOverlay');

    if (sidebar) sidebar.classList.toggle('show');
    if (overlay) overlay.classList.toggle('show');
}

(function() {
    let mainChart = null;
    let overallChart = null;
    let selectedChart = null;

    const data = {
        labels: [
            <?php foreach ($branches as $idx => $row): ?>
                <?= $idx ? ',' : '' ?><?= json_encode($row['branch_display']) ?>
            <?php endforeach; ?>
        ],
        datasets: [
            {
                label: 'Pre-registered',
                data: [
                    <?php foreach ($branches as $idx => $row): ?>
                        <?= $idx ? ',' : '' ?><?= (int)$row['pre_registered_count'] ?>
                    <?php endforeach; ?>
                ],
                backgroundColor: '#10b981',
                borderColor: '#059669',
                borderWidth: 1,
                borderRadius: 4
            },
            {
                label: 'Unregistered',
                data: [
                    <?php foreach ($branches as $idx => $row): ?>
                        <?= $idx ? ',' : '' ?><?= (int)$row['unregistered_count'] ?>
                    <?php endforeach; ?>
                ],
                backgroundColor: '#ef4444',
                borderColor: '#dc2626',
                borderWidth: 1,
                borderRadius: 4
            }
        ]
    };

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

    const updateChartColors = () => {
        const chartTextColor = getComputedStyle(document.body).getPropertyValue('--text').trim() || '#f9fafb';
        const chartGridColor = getComputedStyle(document.body).getPropertyValue('--border').trim() || '#1f2937';

        if (mainChart) {
            mainChart.options.scales.x.ticks.color = chartTextColor;
            mainChart.options.scales.x.grid.color = chartGridColor;
            mainChart.options.scales.y.ticks.color = chartTextColor;
            mainChart.options.scales.y.grid.color = chartGridColor;
            mainChart.options.plugins.legend.labels.color = chartTextColor;
            mainChart.update();
        }

        if (overallChart) {
            overallChart.options.plugins.legend.labels.color = chartTextColor;
            overallChart.update();
        }

        if (selectedChart) {
            selectedChart.options.plugins.legend.labels.color = chartTextColor;
            selectedChart.update();
        }
    };

    const setTheme = theme => {
        document.body.classList.toggle('light-mode', theme === 'light');
        if (themeToggle) {
            themeToggle.innerHTML = theme === 'light' ? '<i class="bi bi-moon-stars"></i> Dark Mode' : '<i class="bi bi-sun-fill text-warning"></i> Light Mode';
        }
        saveTheme(theme);
        updateChartColors();
    };

    const ctx = document.getElementById('branchBarChart');
    if (ctx) {
        mainChart = new Chart(ctx, {
            type: 'bar',
            data: data,
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        ticks: { color: '#f9fafb' },
                        grid: { color: '#1f2937' }
                    },
                    y: {
                        beginAtZero: true,
                        ticks: { color: '#f9fafb' },
                        grid: { color: '#1f2937' }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#f9fafb', usePointStyle: true, boxWidth: 8 }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: { label: ctx => ` ${ctx.dataset.label}: ${ctx.raw}` }
                    }
                }
            }
        });
    }

    // Overall Breakdown Pie Chart
    const overallCtx = document.getElementById('overallPieChart');
    if (overallCtx) {
        overallChart = new Chart(overallCtx, {
            type: 'pie',
            data: {
                labels: ['Pre-registered', 'Unregistered'],
                datasets: [{
                    data: [<?= $total_pre ?>, <?= $total_unreg ?>],
                    backgroundColor: ['#10b981', '#ef4444'],
                    borderColor: 'transparent',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#f9fafb', usePointStyle: true, boxWidth: 8 }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw}` }
                    }
                }
            }
        });
    }

    // Selected Branch Breakdown Pie Chart
    const selectedBranchCtx = document.getElementById('selectedBranchPieChart');
    if (selectedBranchCtx) {
        selectedChart = new Chart(selectedBranchCtx, {
            type: 'pie',
            data: {
                labels: ['Pre-registered', 'Unregistered'],
                datasets: [{
                    data: [<?= (int)($selected_branch_stats['pre_registered_count'] ?? 0) ?>, <?= (int)($selected_branch_stats['unregistered_count'] ?? 0) ?>],
                    backgroundColor: ['#10b981', '#ef4444'],
                    borderColor: 'transparent',
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#f9fafb', usePointStyle: true, boxWidth: 8 }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15, 23, 42, 0.9)',
                        padding: 12,
                        cornerRadius: 8,
                        callbacks: { label: ctx => ` ${ctx.label}: ${ctx.raw}` }
                    }
                }
            }
        });
    }

    if (themeToggle) {
        setTheme(getSavedTheme());
        themeToggle.addEventListener('click', () => {
            setTheme(document.body.classList.contains('light-mode') ? 'dark' : 'light');
        });
    } else {
        updateChartColors();
    }
})();
</script>
</body>
</html>