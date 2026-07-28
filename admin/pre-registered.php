<?php
require_once 'session_start.php';
include '../config/db.php';

// Input params for sorting and filtering
$sort_by = $_GET['sort_by'] ?? 'count'; // 'count' or 'name'
$order = strtolower($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$branch_filter = isset($_GET['branch_name']) ? trim($_GET['branch_name']) : null;

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
$self_path = htmlspecialchars($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Pre-registered Members</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <style>
        :root {
            color-scheme: light dark;
            --bg: #f8fafc;
            --surface: #ffffff;
            --text: #111827;
            --muted: #6b7280;
            --border: #e5e7eb;
            --card: #ffffff;
            --surface-strong: #f1f5f9;
            --btn-bg: #2563eb;
            --btn-color: #ffffff;
            --link: #1d4ed8;
            --table-row-bg: #ffffff;
            --table-row-text: #111827;
        }
        body.dark-mode {
            --bg: #0b1220;
            --surface: #111827;
            --text: #f8fafc;
            --muted: #94a3b8;
            --border: #334155;
            --card: #1e293b;
            --surface-strong: #0f172a;
            --btn-bg: #3b82f6;
            --btn-color: #ffffff;
            --link: #93c5fd;
            --table-row-bg: #1e293b;
            --table-row-text: #f8fafc;
        }
        body {
            font-family: "Inter", "Segoe UI", Arial, Helvetica, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        /* Main Content wrapper */
        .main-wrapper {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1.5rem;
            min-width: 0;
        }

        .page-header {
            padding-bottom: 1rem;
            margin-bottom: 1.5rem;
            border-bottom: 1px solid var(--border);
        }
        .section-title {
            font-size: 1.05rem;
            letter-spacing: 0.02em;
            text-transform: uppercase;
            color: var(--muted);
            margin-bottom: 0.75rem;
        }
        .summary-box {
            background: var(--card) !important;
            border: 1px solid var(--border) !important;
            border-radius: 0.75rem;
            padding: 1.25rem;
            min-height: 120px;
            color: var(--text) !important;
        }
        .summary-box .h4 {
            margin-bottom: 0;
            letter-spacing: 0.01em;
            color: var(--text) !important;
        }
        .filter-panel {
            background: var(--surface-strong) !important;
            border: 1px solid var(--border) !important;
            border-radius: 1rem;
            padding: 1rem 1.25rem;
            margin-bottom: 1.5rem;
        }
        .filter-panel .form-label {
            color: var(--muted) !important;
            font-size: 0.95rem;
        }
        .form-select,
        .form-control {
            color: var(--text) !important;
            background: var(--surface) !important;
            border-color: var(--border) !important;
        }
        .btn-theme {
            border-color: var(--border);
            color: var(--text);
            background: transparent;
            box-shadow: none;
        }
        .btn-secondary,
        .btn-outline-secondary {
            color: var(--text) !important;
            background-color: rgba(255,255,255,0.08) !important;
            border-color: var(--border) !important;
        }
        .btn-primary,
        .btn-outline-primary {
            color: var(--btn-color) !important;
            background-color: var(--btn-bg) !important;
            border-color: var(--btn-bg) !important;
        }
        .card {
            background-color: var(--card) !important;
            border: 1px solid var(--border) !important;
            border-radius: 1rem;
            box-shadow: 0 12px 30px rgba(17,24,39,0.06);
            color: var(--text) !important;
        }
        .card-body {
            background-color: transparent !important;
            color: var(--text) !important;
        }
        .card-title {
            font-weight: 600;
            letter-spacing: 0.01em;
            color: var(--text) !important;
        }
        .table {
            border-collapse: separate;
            border-spacing: 0 0.4rem;
            color: var(--text);
        }
        .table thead th {
            border-bottom: 1px solid var(--border);
            background: transparent;
            color: var(--text);
            font-weight: 600;
        }
        .table tbody tr {
            background-color: var(--table-row-bg) !important;
            border: 1px solid var(--border);
            border-radius: 0.75rem;
        }
        .table tbody tr td {
            border: none;
            vertical-align: middle;
            color: var(--table-row-text) !important;
            background-color: transparent !important;
        }
        .table-hover tbody tr:hover {
            background-color: rgba(59, 130, 246, 0.15) !important;
        }
        .chart-card {
            min-height: 340px;
            padding: 1.5rem;
            background: var(--card) !important;
        }
        .chart-card canvas {
            width: 100% !important;
            height: 280px !important;
        }
        .breakdown-card {
            background: var(--surface-strong) !important;
            padding: 1.35rem;
            border: 1px solid var(--border) !important;
            border-radius: 1rem;
        }
        .breakdown-card .detail-box {
            min-height: 100px;
            background: var(--surface) !important;
            border: 1px solid var(--border) !important;
            border-radius: 0.85rem;
            padding: 1rem;
        }
        .breakdown-card .detail-box .small {
            color: var(--muted) !important;
        }
        .pie-chart-container {
            max-width: 320px;
            margin: 0 auto;
        }
        .pie-chart-container canvas {
            width: 100% !important;
            height: 220px !important;
        }
        .bg-light {
            background: rgba(255,255,255,0.12) !important;
        }
        .text-muted {
            color: var(--muted) !important;
        }
        a {
            color: var(--link) !important;
        }
        a:hover {
            color: var(--btn-bg) !important;
        }
    </style>
</head>
<body>

<!-- Main Wrapper -->
<div class="main-wrapper">
    <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h2 class="mb-1">Pre-registered / Printed Members</h2>
            <div class="section-title mb-0">Branch summaries, member details, and registration status</div>
        </div>
        <div class="d-flex gap-2">
            <button id="theme-toggle" type="button" class="btn btn-theme btn-sm">Toggle Theme</button>
            <a href="dashboard.php" class="btn btn-secondary btn-sm">Back to Dashboard</a>
        </div>
    </div>

    <div class="mb-3">
        <strong>Total Pre-registered (Printed/Registered):</strong> <?= $total_pre ?>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="filter-panel">
                <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-3">
                    <div>
                        <h5 class="card-title mb-1">Branch Breakdown</h5>
                        <?php if ($selected_branch_info !== null): ?>
                            <div class="small text-muted">Showing branch: <?= htmlspecialchars($selected_branch_info) ?></div>
                        <?php endif; ?>
                    </div>
                    <form method="get" class="d-flex gap-2 align-items-center flex-wrap">
                        <label class="form-label mb-0">Branch:</label>
                        <select name="branch_name" class="form-select form-select-sm" style="min-width:180px;">
                            <option value="">All Branches</option>
                            <?php foreach ($branches as $row): ?>
                                <option value="<?= esc($row['branch_display']) ?>" <?= $selected_branch_info === $row['branch_display'] ? 'selected' : '' ?>><?= esc($row['branch_display']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <label class="form-label mb-0">Sort:</label>
                        <select name="sort_by" class="form-select form-select-sm" style="width:140px;">
                            <option value="count" <?= $sort_by === 'count' ? 'selected' : '' ?>>By Count</option>
                            <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>By Name</option>
                        </select>
                        <select name="order" class="form-select form-select-sm" style="width:120px;">
                            <option value="desc" <?= $order === 'DESC' ? 'selected' : '' ?>>Descending</option>
                            <option value="asc" <?= $order === 'ASC' ? 'selected' : '' ?>>Ascending</option>
                        </select>
                        <button class="btn btn-primary btn-sm" type="submit">Apply</button>
                    </form>
                </div>
            </div>

            <div class="row g-3 mb-3">
                <div class="col-md-6">
                    <div class="summary-box text-center">
                        <div class="small text-muted">Overall Pre-registered</div>
                        <div class="h4 mb-0"><?= $total_pre ?></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="summary-box text-center">
                        <div class="small text-muted">Overall Unregistered</div>
                        <div class="h4 mb-0"><?= $total_unreg ?></div>
                    </div>
                </div>
            </div>

            <table class="table table-sm mt-3">
                <thead>
                    <tr>
                        <th>Branch</th>
                        <th class="text-end">Pre-registered</th>
                        <th class="text-end">Unregistered</th>
                        <th class="text-end">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($branches as $row): ?>
                    <tr>
                        <td><?= esc($row['branch_display']) ?></td>
                        <td class="text-end"><?= (int)$row['pre_registered_count'] ?></td>
                        <td class="text-end"><?= (int)$row['unregistered_count'] ?></td>
                        <td class="text-end"><a href="pre-registered.php?branch_name=<?= rawurlencode($row['branch_display']) ?>&sort_by=<?= esc($sort_by) ?>&order=<?= esc(strtolower($order)) ?>#branchDetails" class="btn btn-sm btn-outline-primary">View</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>

            <div class="row mt-4" id="branchDetails">
                <div class="col-12 mb-3">
                    <div class="breakdown-card">
                        <div class="row g-4 align-items-center">
                            <!-- Overall Pie Chart -->
                            <div class="<?= $selected_branch_info !== null ? 'col-lg-6' : 'col-12' ?>">
                                <h6 class="mb-3 text-center">Overall Breakdown</h6>
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <div class="detail-box text-center">
                                            <div class="small">Pre-registered</div>
                                            <div class="h4 mb-0"><?= $total_pre ?></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="detail-box text-center">
                                            <div class="small">Unregistered</div>
                                            <div class="h4 mb-0"><?= $total_unreg ?></div>
                                        </div>
                                    </div>
                                </div>
                                <div class="pie-chart-container">
                                    <canvas id="overallPieChart" height="220"></canvas>
                                </div>
                            </div>

                            <!-- Selected Branch Pie Chart (If applicable) -->
                            <?php if ($selected_branch_info !== null): ?>
                            <div class="col-lg-6 border-start border-secondary">
                                <h6 class="mb-3 text-center"><?= htmlspecialchars($selected_branch_info) ?> Breakdown</h6>
                                <div class="row g-3 mb-3">
                                    <div class="col-6">
                                        <div class="detail-box text-center">
                                            <div class="small">Pre-registered</div>
                                            <div class="h4 mb-0"><?= (int)($selected_branch_stats['pre_registered_count'] ?? 0) ?></div>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="detail-box text-center">
                                            <div class="small">Unregistered</div>
                                            <div class="h4 mb-0"><?= (int)($selected_branch_stats['unregistered_count'] ?? 0) ?></div>
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

                <div class="col-12 mb-3">
                    <div class="card border p-3 chart-card">
                        <h6 class="mb-3">Pre-registered Comparison by Branch</h6>
                        <canvas id="branchPieChart" height="380"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Members List</h5>
            </div>

            <?php if ($members_q && $members_q->num_rows > 0): ?>
            <div class="table-responsive">
                <table class="table table-hover table-sm">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Full Name</th>
                            <th>Branch</th>
                            <th>Category</th>
                            <th>Printed / Registered At</th>
                            <th>Profile</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($m = $members_q->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++ ?></td>
                            <td><?= esc($m['full_name']) ?></td>
                            <td><?= esc($m['branch_name'] ?: 'Unassigned') ?></td>
                            <td><?= esc($m['migs_category']) ?></td>
                            <td><?= esc($m['printed_at'] ?: $m['registered_at']) ?></td>
                            <td><a class="btn btn-sm btn-outline-primary" href="view.php?id=<?= (int)$m['id'] ?>">View</a></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="text-muted">No pre-registered members found for the selected branch.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
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
                borderColor: '#0f766e',
                borderWidth: 1
            },
            {
                label: 'Unregistered',
                data: [
                    <?php foreach ($branches as $idx => $row): ?>
                        <?= $idx ? ',' : '' ?><?= (int)$row['unregistered_count'] ?>
                    <?php endforeach; ?>
                ],
                backgroundColor: '#ef4444',
                borderColor: '#991b1b',
                borderWidth: 1
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
        return getCookie('adminTheme') || 'light';
    };

    const saveTheme = theme => {
        try {
            localStorage.setItem('adminTheme', theme);
        } catch (e) {}
        setCookie('adminTheme', theme, 365);
    };

    const updateChartColors = () => {
        const chartTextColor = getComputedStyle(document.body).getPropertyValue('--text').trim() || '#111827';
        const chartGridColor = getComputedStyle(document.body).getPropertyValue('--border').trim() || '#e5e7eb';

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
        document.body.classList.toggle('dark-mode', theme === 'dark');
        if (themeToggle) {
            themeToggle.textContent = theme === 'dark' ? '☀️ Light Mode' : '🌙 Dark Mode';
        }
        saveTheme(theme);
        updateChartColors();
    };

    const ctx = document.getElementById('branchPieChart');
    if (ctx) {
        const customYAxisTicks = {
            id: 'customYAxisTicks',
            afterBuildTicks(chart) {
                const scale = chart.scales.y;
                if (!scale) return;
                scale.ticks = [
                    { value: 100, major: false, label: '100' },
                    { value: 10000, major: false, label: '10000' },
                    { value: 20000, major: false, label: '20000' }
                ];
            }
        };

        mainChart = new Chart(ctx, {
            type: 'bar',
            data: data,
            plugins: [customYAxisTicks],
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    x: {
                        stacked: false,
                        ticks: { color: '#111827' },
                        grid: { color: '#e5e7eb' }
                    },
                    y: {
                        min: 0,
                        max: 20000,
                        ticks: {
                            color: '#111827',
                            callback: value => [100, 10000, 20000].includes(value) ? value : ''
                        },
                        grid: { color: '#e5e7eb' }
                    }
                },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#111827' }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,0.94)',
                        callbacks: { label: ctx => `${ctx.dataset.label}: ${ctx.raw}` }
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
                    borderColor: '#ffffff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#111827' }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,0.94)',
                        callbacks: { label: ctx => `${ctx.label}: ${ctx.raw}` }
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
                    borderColor: '#ffffff',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: '#111827' }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(15,23,42,0.94)',
                        callbacks: { label: ctx => `${ctx.label}: ${ctx.raw}` }
                    }
                }
            }
        });
    }

    if (themeToggle) {
        setTheme(getSavedTheme());
        themeToggle.addEventListener('click', () => {
            setTheme(document.body.classList.contains('dark-mode') ? 'light' : 'dark');
        });
    } else {
        updateChartColors();
    }
})();
</script>
</body>
</html>