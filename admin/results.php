<?php
require_once 'session_start.php';
include '../config/db.php';

if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit();
}

// 1. Detect branch column name dynamically ('branch_name' or 'branch')
$member_branch_col = 'branch_name';
$member_fullname_col = '';
$cols = $conn->query("SHOW COLUMNS FROM members");
if ($cols) {
    while ($c = $cols->fetch_assoc()) {
        $field_lower = strtolower($c['Field']);
        if (in_array($field_lower, ['branch_name', 'branch'])) {
            $member_branch_col = $c['Field'];
        }
        if (in_array($field_lower, ['full_name', 'fullname', 'name', 'member_name'])) {
            $member_fullname_col = $c['Field'];
        }
    }
}

// 2. Detect candidate name column dynamically ('candidate_name', 'fullname', 'name', or 'full_name')
$candidate_name_col = 'full_name';
$cand_cols = $conn->query("SHOW COLUMNS FROM candidates");
if ($cand_cols) {
    while ($c = $cand_cols->fetch_assoc()) {
        if (in_array(strtolower($c['Field']), ['candidate_name', 'fullname', 'name', 'full_name'])) {
            $candidate_name_col = $c['Field'];
            break;
        }
    }
}

// 3. Detect voter reference column dynamically ('member_id', 'voter_id', or 'voters_id')
$votes_voter_col = 'voter_id';
$v_cols = $conn->query("SHOW COLUMNS FROM votes");
if ($v_cols) {
    while ($c = $v_cols->fetch_assoc()) {
        if (in_array(strtolower($c['Field']), ['member_id', 'voter_id', 'voters_id'])) {
            $votes_voter_col = $c['Field'];
            break;
        }
    }
}

// Helper SQL condition to safely join members table by name fallback
if (!empty($member_fullname_col)) {
    $member_name_match_sql = "TRIM(m.`{$member_fullname_col}`) = TRIM(v.member_name)";
} else {
    $member_name_match_sql = "CONCAT(TRIM(m.first_name), ' ', TRIM(m.last_name)) = TRIM(v.member_name)";
}

// Fetch distinct branches from members table
$branches_query = $conn->query("SELECT DISTINCT TRIM(`{$member_branch_col}`) AS branch_name FROM members WHERE `{$member_branch_col}` IS NOT NULL AND TRIM(`{$member_branch_col}`) != '' ORDER BY `{$member_branch_col}` ASC");
$branches = [];
if($branches_query) {
    while($b_row = $branches_query->fetch_assoc()) {
        $branches[] = $b_row['branch_name'];
    }
}

// Identify current active filter selection context
$raw_branch = isset($_GET['branch']) ? trim($_GET['branch']) : 'overall';
$selected_branches = ($raw_branch === 'overall' || empty($raw_branch)) ? ['overall'] : array_map('trim', explode(',', urldecode($raw_branch)));
$is_overall = in_array('overall', $selected_branches);

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

        .main-content {
            margin-left: 250px;
            padding: 2.5rem;
            min-width: 0;
            min-height: 100vh;
            box-sizing: border-box;
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

        .filter-select {
            border-radius: 0.75rem;
            padding: 0.6rem 1rem;
            font-weight: 500;
            border: 1px solid var(--border);
        }
        .filter-select:focus {
            border-color: var(--btn-bg);
            box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.15);
        }

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
            .main-content {
                margin-left: 76px;
                padding: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }

            .page-header {
                align-items: flex-start !important;
            }

            .page-title {
                font-size: 1.45rem;
            }

            .card-body {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>

<!-- Shared PMPC Admin Sidebar -->
<?php include 'sidebar.php'; ?>

<main class="main-content">
        <div class="page-header d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <h2 class="page-title mb-1">🗳️ Election Live Results</h2>
                <div class="section-subtitle">Real-time candidate tally and cooperative voting turnout overview</div>
            </div>
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Multi-Branch Checkbox Dropdown -->
                <div class="dropdown">
                    <button class="btn filter-select dropdown-toggle d-flex justify-content-between align-items-center" type="button" data-bs-toggle="dropdown" data-bs-auto-close="outside" style="width: 250px; background: var(--surface); color: var(--text); text-align: left;">
                        <span class="text-truncate">
                            <?php 
                            if ($is_overall) {
                                echo '🌐 Overall Results';
                            } else {
                                echo '📍 ' . count($selected_branches) . ' Branch(es) Selected';
                            }
                            ?>
                        </span>
                    </button>
                    <ul class="dropdown-menu p-3 shadow" style="width: 250px; max-height: 400px; overflow-y: auto; background-color: var(--card); border-color: var(--border);">
                        <li>
                            <div class="form-check">
                                <input class="form-check-input branch-cb" type="checkbox" value="overall" id="cb_overall" <?php echo $is_overall ? 'checked' : ''; ?> onchange="handleOverallChange()">
                                <label class="form-check-label fw-bold" for="cb_overall" style="color: var(--text); cursor: pointer;">🌐 Overall Results</label>
                            </div>
                        </li>
                        <li><hr class="dropdown-divider" style="border-color: var(--border);"></li>
                        <?php foreach($branches as $index => $b): ?>
                        <li>
                            <div class="form-check mb-2">
                                <input class="form-check-input branch-cb specific-branch" type="checkbox" value="<?php echo esc($b); ?>" id="cb_<?php echo $index; ?>" <?php echo in_array($b, $selected_branches) && !$is_overall ? 'checked' : ''; ?> onchange="handleBranchChange()">
                                <label class="form-check-label text-truncate" for="cb_<?php echo $index; ?>" style="color: var(--text); display: block; cursor: pointer;">📍 <?php echo esc($b); ?></label>
                            </div>
                        </li>
                        <?php endforeach; ?>
                        <li class="mt-3">
                            <button class="btn btn-primary btn-sm w-100" onclick="applyBranchFilter()">Apply Filter</button>
                        </li>
                    </ul>
                </div>

                <button id="theme-toggle" type="button" class="btn btn-theme btn-sm d-flex align-items-center gap-2">
                    <i class="bi bi-moon-stars"></i> Light Mode
                </button>
                <a href="dashboard.php" class="btn btn-secondary btn-sm d-flex align-items-center gap-2">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>

        <?php
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
                if(!$is_overall) {
                    $escaped_branches_array = [];
                    foreach($selected_branches as $sb) {
                        $escaped_branches_array[] = "'" . $conn->real_escape_string($sb) . "'";
                    }
                    $in_clause = implode(',', $escaped_branches_array);

                    // Falls back to joining via member_name or direct v.branch if voter_id = 0
                    $candidates_query_str = "
                        SELECT
                            c.id,
                            c.`{$candidate_name_col}` AS full_name,
                            c.photo,
                            COUNT(CASE 
                                WHEN TRIM(m.`{$member_branch_col}`) IN ($in_clause) OR TRIM(v.branch) IN ($in_clause) THEN v.id 
                            END) AS total_votes
                        FROM candidates c
                        LEFT JOIN votes v ON c.id = v.candidate_id
                        LEFT JOIN members m ON (v.`{$votes_voter_col}` = m.id OR (v.`{$votes_voter_col}` = 0 AND {$member_name_match_sql}))
                        WHERE c.position_id = $position_id
                        GROUP BY c.id
                        ORDER BY total_votes DESC, c.`{$candidate_name_col}` ASC";
                } else {
                    $candidates_query_str = "
                        SELECT
                            c.id,
                            c.`{$candidate_name_col}` AS full_name,
                            c.photo,
                            COUNT(v.id) AS total_votes
                        FROM candidates c
                        LEFT JOIN votes v ON c.id = v.candidate_id
                        WHERE c.position_id = $position_id
                        GROUP BY c.id
                        ORDER BY total_votes DESC, c.`{$candidate_name_col}` ASC";
                }

                $candidates = $conn->query($candidates_query_str);
                $table_rows = [];

                if($candidates && $candidates->num_rows > 0){
                    $labels_array = [];
                    $votes_array = [];

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
                        <?php if(!empty($table_rows)){ ?>
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
                if(!$is_overall) {
                    $total_voters = $conn->query("SELECT COUNT(*) AS total FROM members WHERE TRIM(`{$member_branch_col}`) IN ($in_clause)")->fetch_assoc()['total'];
                    
                    $total_votes = $conn->query("
                        SELECT COUNT(v.id) AS total 
                        FROM votes v 
                        LEFT JOIN members m ON (v.`{$votes_voter_col}` = m.id OR (v.`{$votes_voter_col}` = 0 AND {$member_name_match_sql}))
                        WHERE TRIM(m.`{$member_branch_col}`) IN ($in_clause) OR TRIM(v.branch) IN ($in_clause)
                    ")->fetch_assoc()['total'];

                    if($conn->query("SHOW COLUMNS FROM members LIKE 'has_voted'")->num_rows > 0){
                        $voted_members = $conn->query("SELECT COUNT(*) AS total FROM members WHERE has_voted = 1 AND TRIM(`{$member_branch_col}`) IN ($in_clause)")->fetch_assoc()['total'];
                    } else {
                        $voted_members = $conn->query("
                            SELECT COUNT(DISTINCT CASE WHEN v.`{$votes_voter_col}` > 0 THEN v.`{$votes_voter_col}` ELSE v.member_name END) AS total 
                            FROM votes v
                            LEFT JOIN members m ON (v.`{$votes_voter_col}` = m.id OR (v.`{$votes_voter_col}` = 0 AND {$member_name_match_sql}))
                            WHERE TRIM(m.`{$member_branch_col}`) IN ($in_clause) OR TRIM(v.branch) IN ($in_clause)
                        ")->fetch_assoc()['total'];
                    }
                } else {
                    $total_voters = $conn->query("SELECT COUNT(*) AS total FROM members")->fetch_assoc()['total'];
                    $total_votes = $conn->query("SELECT COUNT(*) AS total FROM votes")->fetch_assoc()['total'];

                    if($conn->query("SHOW COLUMNS FROM members LIKE 'has_voted'")->num_rows > 0){
                        $voted_members = $conn->query("SELECT COUNT(*) AS total FROM members WHERE has_voted = 1")->fetch_assoc()['total'];
                    } else {
                        $voted_members = $conn->query("SELECT COUNT(DISTINCT CASE WHEN `{$votes_voter_col}` > 0 THEN `{$votes_voter_col}` ELSE member_name END) AS total FROM votes")->fetch_assoc()['total'];
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

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartInstances = {};
const modalChartInstances = {};
const chartColors = <?php echo json_encode($chart_colors); ?>;
const rawChartData = <?php echo json_encode($chart_js_data); ?>;

function handleOverallChange() {
    const overallCb = document.getElementById('cb_overall');
    const branchCbs = document.querySelectorAll('.specific-branch');
    if (overallCb.checked) {
        branchCbs.forEach(cb => cb.checked = false);
    } else if (!Array.from(branchCbs).some(cb => cb.checked)) {
        overallCb.checked = true;
    }
}

function handleBranchChange() {
    const overallCb = document.getElementById('cb_overall');
    const branchCbs = document.querySelectorAll('.specific-branch');
    if (Array.from(branchCbs).some(cb => cb.checked)) {
        overallCb.checked = false;
    } else {
        overallCb.checked = true;
    }
}

function applyBranchFilter() {
    const overallCb = document.getElementById('cb_overall');
    if (overallCb.checked) {
        window.location.href = 'results.php';
        return;
    }
    
    const selected = Array.from(document.querySelectorAll('.specific-branch:checked')).map(cb => cb.value);
    if (selected.length === 0) {
        window.location.href = 'results.php';
    } else {
        window.location.href = 'results.php?branch=' + encodeURIComponent(selected.join(','));
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