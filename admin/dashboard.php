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
| Schema Selection Expressions (Targeting Exact Column Names)
|--------------------------------------------------------------------------
*/

// Primary lookup uses m.migs_category, fallback to c.category if stored
$categorySelect = "
    COALESCE(
        NULLIF(TRIM(m.migs_category), ''),
        NULLIF(TRIM(c.category), ''),
        'NON-MIGS'
    )
";

// Primary lookup uses m.branch_name, fallback to c.branch
$branchSelect = "
    COALESCE(
        NULLIF(TRIM(m.branch_name), ''),
        NULLIF(TRIM(c.branch), ''),
        'Main Branch'
    )
";

/*
|--------------------------------------------------------------------------
| Attendance & Claims Statistics
|--------------------------------------------------------------------------
*/

$filterDate = isset($_GET['filter_date']) ? trim($_GET['filter_date']) : '';

// Base query conditions for member_freebie_claims filtering
$whereClause = "";
if (!empty($filterDate)) {
    $escapedDate = $conn->real_escape_string($filterDate);
    $whereClause = " WHERE DATE(c.claimed_at) = '$escapedDate'";
}

// 1. Total Unique Members Arrived / Claimed
$totalAttendees = 0;
$totalAttendeesRes = $conn->query("SELECT COUNT(DISTINCT member_id) as total FROM member_freebie_claims c" . $whereClause);
if ($totalAttendeesRes) {
    $totalAttendees = $totalAttendeesRes->fetch_assoc()['total'] ?? 0;
}

// 2. Today's Arrivals / Claims
$todaysArrivals = 0;
$todaysArrivalsRes = $conn->query("SELECT COUNT(DISTINCT member_id) as total FROM member_freebie_claims c WHERE DATE(c.claimed_at) = CURDATE()");
if ($todaysArrivalsRes) {
    $todaysArrivals = $todaysArrivalsRes->fetch_assoc()['total'] ?? 0;
}

// 3. Category Breakdown Queries (Gold, Silver, Bronze, Non-MIGS)
$categoryCounts = [
    'GOLD' => 0,
    'SILVER' => 0,
    'BRONZE' => 0,
    'NON-MIGS' => 0
];

// Joins c.member_id against both m.username and m.id
$catQuery = "
    SELECT 
        UPPER(TRIM(" . $categorySelect . ")) as member_cat, 
        COUNT(DISTINCT c.member_id) as total 
    FROM member_freebie_claims c
    LEFT JOIN members m 
        ON c.member_id = m.username 
        OR c.member_id = m.id
    " . $whereClause . "
    GROUP BY UPPER(TRIM(" . $categorySelect . "))
";

$catRes = $conn->query($catQuery);
if ($catRes) {
    while ($row = $catRes->fetch_assoc()) {
        $cat = strtoupper(trim($row['member_cat'] ?? ''));
        
        if (str_contains($cat, 'GOLD')) {
            $categoryCounts['GOLD'] += (int)$row['total'];
        } elseif (str_contains($cat, 'SILVER')) {
            $categoryCounts['SILVER'] += (int)$row['total'];
        } elseif (str_contains($cat, 'BRONZE')) {
            $categoryCounts['BRONZE'] += (int)$row['total'];
        } else {
            $categoryCounts['NON-MIGS'] += (int)$row['total'];
        }
    }
}

// 4. Quantity Summary Breakdown per Item
$itemTotalsQuery = "
    SELECT 
        SUM(CASE WHEN item_name LIKE '%T-Shirt%' THEN 1 ELSE 0 END) AS total_tshirts,
        SUM(CASE WHEN item_name LIKE '%Cash Allowance%' THEN 1 ELSE 0 END) AS total_cash,
        SUM(CASE WHEN item_name LIKE '%Snacks%' OR item_name LIKE '%Meals%' THEN 1 ELSE 0 END) AS total_snacks,
        SUM(CASE WHEN item_name LIKE '%Umbrella%' THEN 1 ELSE 0 END) AS total_umbrellas,
        SUM(CASE WHEN item_name LIKE '%Water Bottle%' OR item_name LIKE '%Gold%' THEN 1 ELSE 0 END) AS total_bottles
    FROM member_freebie_claims c
    $whereClause
";
$itemTotalsRes = $conn->query($itemTotalsQuery);
$itemTotals = $itemTotalsRes ? $itemTotalsRes->fetch_assoc() : [
    'total_tshirts' => 0, 'total_cash' => 0, 'total_snacks' => 0, 'total_umbrellas' => 0, 'total_bottles' => 0
];

// 5. Live Attendance & Claims Log Table
$logQuery = "
    SELECT 
        c.member_id, 
        COALESCE(NULLIF(TRIM(m.full_name), ''), c.member_name) as member_name, 
        " . $categorySelect . " as category, 
        " . $branchSelect . " as branch,
        SUM(CASE WHEN c.item_name LIKE '%T-Shirt%' THEN 1 ELSE 0 END) AS qty_tshirt,
        SUM(CASE WHEN c.item_name LIKE '%Cash Allowance%' THEN 1 ELSE 0 END) AS qty_cash,
        SUM(CASE WHEN c.item_name LIKE '%Snacks%' OR c.item_name LIKE '%Meals%' THEN 1 ELSE 0 END) AS qty_snacks,
        SUM(CASE WHEN c.item_name LIKE '%Umbrella%' THEN 1 ELSE 0 END) AS qty_umbrella,
        SUM(CASE WHEN c.item_name LIKE '%Water Bottle%' OR c.item_name LIKE '%Gold%' THEN 1 ELSE 0 END) AS qty_gold_bottle,
        DATE(MAX(c.claimed_at)) AS arrival_date 
    FROM member_freebie_claims c
    LEFT JOIN members m 
        ON c.member_id = m.username 
        OR c.member_id = m.id
    $whereClause 
    GROUP BY c.member_id, member_name, " . $categorySelect . ", " . $branchSelect . "
    ORDER BY arrival_date DESC, c.member_id DESC 
    LIMIT 100
";
$attendanceLogs = $conn->query($logQuery);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Admin Dashboard - PMPC E-Voting</title>
    
    <!-- Google Fonts & Bootstrap Icons -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Anti-flicker script to apply saved theme instantly -->
    <script>
        (function() {
            if (localStorage.getItem('admin-theme') === 'dark') {
                document.documentElement.classList.add('dark-theme');
            }
        })();
    </script>

    <style>
        :root {
            --bg-main: #f1f5f9;
            --sidebar-bg: #0f172a;
            --sidebar-hover: #1e293b;
            --sidebar-text: #94a3b8;
            --sidebar-text-active: #ffffff;
            --sidebar-active-bg: #2563eb;
            --card-bg: rgba(255, 255, 255, 0.9);
            --card-border: #e2e8f0;
            --text-primary: #0f172a;
            --text-secondary: #64748b;
            --input-bg: #ffffff;
            --table-hover: #f8fafc;
            --card-shadow: 0 10px 25px -5px rgba(15, 23, 42, 0.04), 0 8px 10px -6px rgba(15, 23, 42, 0.04);
            --glass-backdrop: blur(12px);
        }

        .dark-theme {
            --bg-main: #020617;
            --sidebar-bg: #090d16;
            --sidebar-hover: #161e2e;
            --sidebar-text: #64748b;
            --sidebar-text-active: #f8fafc;
            --sidebar-active-bg: #2563eb;
            --card-bg: rgba(15, 23, 42, 0.75);
            --card-border: #1e293b;
            --text-primary: #f8fafc;
            --text-secondary: #94a3b8;
            --input-bg: #0f172a;
            --table-hover: #1e293b;
            --card-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.5);
            --glass-backdrop: blur(16px);
        }

        body {
            background-color: var(--bg-main);
            color: var(--text-primary);
            font-family: 'Plus Jakarta Sans', system-ui, -apple-system, sans-serif;
            transition: background-color 0.3s ease, color 0.3s ease;
            min-height: 100vh;
        }

        .main {
            margin-left: 250px;
            padding: 2.25rem 2.5rem;
            width: calc(100% - 250px);
            max-width: calc(100% - 250px);
            box-sizing: border-box;
            transition: margin-left 0.3s ease;
            overflow-x: hidden;
        }

        .page-title {
            color: var(--text-primary);
            font-weight: 800;
            font-size: 1.85rem;
            letter-spacing: -0.03em;
            margin: 0;
        }

        .welcome-subtitle {
            color: var(--text-secondary);
            font-size: 0.95rem;
            font-weight: 500;
        }

        .theme-toggle-btn {
            border: 1px solid var(--card-border);
            border-radius: 50px;
            padding: 0.55rem 1.15rem;
            background: var(--card-bg);
            color: var(--text-primary);
            font-weight: 600;
            font-size: 0.85rem;
            display: inline-flex;
            align-items: center;
            gap: 0.6rem;
            box-shadow: var(--card-shadow);
            backdrop-filter: var(--glass-backdrop);
            transition: all 0.2s ease;
            cursor: pointer;
        }

        .theme-toggle-btn:hover {
            transform: translateY(-2px);
            border-color: var(--sidebar-active-bg);
        }

        .admin-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: var(--card-shadow);
            backdrop-filter: var(--glass-backdrop);
            transition: all 0.25s ease;
        }

        .stat-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            border-color: rgba(37, 99, 235, 0.4);
        }

        .stat-icon-wrapper {
            width: 50px;
            height: 50px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            margin-bottom: 1.25rem;
        }

        .stat-number {
            font-size: 2.1rem;
            font-weight: 800;
            letter-spacing: -0.04em;
            color: var(--text-primary);
            line-height: 1;
            margin-bottom: 0.4rem;
        }

        .stat-label {
            color: var(--text-secondary);
            font-size: 0.88rem;
            font-weight: 600;
        }

        .bento-banner {
            border-radius: 16px;
            padding: 1.5rem;
            color: #ffffff;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 20px -5px rgba(0, 0, 0, 0.15);
        }

        .banner-blue { background: linear-gradient(135deg, #2563eb, #1d4ed8); }
        .banner-green { background: linear-gradient(135deg, #10b981, #047857); }
        .banner-gold { background: linear-gradient(135deg, #f59e0b, #b45309); }
        .banner-silver { background: linear-gradient(135deg, #64748b, #334155); }
        .banner-bronze { background: linear-gradient(135deg, #d97706, #78350f); }
        .banner-red { background: linear-gradient(135deg, #ef4444, #991b1b); }

        .item-metric-card {
            background: rgba(255, 255, 255, 0.04);
            border: 1px solid var(--card-border);
            border-radius: 14px;
            padding: 1.1rem 0.75rem;
            text-align: center;
            transition: all 0.2s ease;
        }

        .item-metric-card:hover {
            background: rgba(37, 99, 235, 0.05);
            border-color: var(--sidebar-active-bg);
        }

        .item-metric-val {
            font-size: 1.6rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 0.25rem;
        }

        .item-metric-lbl {
            font-size: 0.72rem;
            font-weight: 700;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-pill-custom {
            font-size: 0.73rem;
            padding: 0.35em 0.8em;
            border-radius: 50px;
            font-weight: 700;
            letter-spacing: 0.3px;
        }

        .badge-gold {
            background-color: rgba(245, 158, 11, 0.15);
            color: #d97706;
            border: 1px solid rgba(245, 158, 11, 0.4);
        }
        .dark-theme .badge-gold { color: #fbbf24; }

        .badge-silver {
            background-color: rgba(100, 116, 139, 0.15);
            color: #64748b;
            border: 1px solid rgba(100, 116, 139, 0.4);
        }
        .dark-theme .badge-silver { color: #cbd5e1; }

        .badge-bronze {
            background-color: rgba(217, 119, 6, 0.15);
            color: #b45309;
            border: 1px solid rgba(217, 119, 6, 0.4);
        }

        .badge-nonmigs {
            background-color: rgba(239, 68, 68, 0.15);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.4);
        }

        .badge-claimed {
            background-color: rgba(16, 185, 129, 0.12);
            color: #059669;
            border: 1px solid rgba(16, 185, 129, 0.3);
            font-weight: 800;
            padding: 0.25em 0.65em;
            border-radius: 8px;
        }

        .dark-theme .badge-claimed { color: #34d399; }

        .badge-unclaimed {
            color: var(--text-secondary);
            opacity: 0.4;
            font-weight: 500;
        }

        .table-responsive {
            border-radius: 14px;
            overflow: hidden;
        }

        .custom-table {
            color: var(--text-primary);
            margin-bottom: 0;
            border-collapse: separate;
            border-spacing: 0;
        }

        .custom-table th {
            background: rgba(15, 23, 42, 0.03);
            color: var(--text-secondary);
            border-bottom: 1px solid var(--card-border);
            font-size: 0.72rem;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: 0.06em;
            padding: 1rem;
        }

        .dark-theme .custom-table th {
            background: rgba(255, 255, 255, 0.03);
        }

        .custom-table td {
            border-bottom: 1px solid var(--card-border);
            padding: 1rem;
            vertical-align: middle;
            font-size: 0.9rem;
            transition: background-color 0.15s ease;
        }

        .custom-table tbody tr:hover td {
            background-color: var(--table-hover);
        }

        .custom-table tbody tr:last-child td {
            border-bottom: none;
        }

        .custom-input {
            background-color: var(--input-bg);
            color: var(--text-primary);
            border: 1px solid var(--card-border);
            border-radius: 10px;
            padding: 0.5rem 0.9rem;
            font-weight: 500;
        }

        .custom-input:focus {
            background-color: var(--input-bg);
            color: var(--text-primary);
            border-color: var(--sidebar-active-bg);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.2);
        }

        @media (max-width: 991.98px) {
            .main {
                margin-left: 0;
                width: 100%;
                max-width: 100%;
                padding: 5.5rem 1.25rem 2.5rem 1.25rem;
            }
        }
    </style>
</head>
<body>

<?php include 'sidebar.php'; ?>

<main class="main">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="page-title">Dashboard Overview</h2>
            <p class="welcome-subtitle mb-0">
                Welcome back, <strong><?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin', ENT_QUOTES, 'UTF-8'); ?></strong>
            </p>
        </div>
        <div>
            <button class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()" aria-label="Toggle Theme">
                <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
                <span id="themeText">Dark Mode</span>
            </button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="admin-card stat-card">
                <div>
                    <div class="stat-icon-wrapper bg-primary bg-opacity-10 text-primary">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($totalVoters); ?></div>
                </div>
                <div class="stat-label">Registered Voters</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="admin-card stat-card">
                <div>
                    <div class="stat-icon-wrapper bg-info bg-opacity-10 text-info">
                        <i class="bi bi-person-bounding-box"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($totalCandidates); ?></div>
                </div>
                <div class="stat-label">Total Candidates</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="admin-card stat-card">
                <div>
                    <div class="stat-icon-wrapper bg-warning bg-opacity-10 text-warning">
                        <i class="bi bi-trophy-fill"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($totalPositions); ?></div>
                </div>
                <div class="stat-label">Open Positions</div>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="admin-card stat-card">
                <div>
                    <div class="stat-icon-wrapper bg-success bg-opacity-10 text-success">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($totalVotes); ?></div>
                </div>
                <div class="stat-label">Total Votes Cast</div>
            </div>
        </div>
    </div>

    <div class="admin-card mb-4">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-pie-chart-fill text-primary me-2"></i>Attendance & Claims Statistics</h5>
                <p class="text-secondary small mb-0">Overview of member turnout and total item quantities distributed</p>
            </div>
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
            <div class="col-12 col-md-6">
                <div class="bento-banner banner-blue">
                    <div class="display-6 fw-extrabold mb-1" style="font-weight: 800;"><?php echo number_format($totalAttendees); ?></div>
                    <div class="fw-semibold text-white-50 small text-uppercase tracking-wider">Total Attendees Arrived</div>
                </div>
            </div>
            <div class="col-12 col-md-6">
                <div class="bento-banner banner-green">
                    <div class="display-6 fw-extrabold mb-1" style="font-weight: 800;"><?php echo number_format($todaysArrivals); ?></div>
                    <div class="fw-semibold text-white-50 small text-uppercase tracking-wider">Today's Arrivals</div>
                </div>
            </div>
        </div>

        <h6 class="fw-bold text-secondary text-uppercase small tracking-wider mb-2">Member Category Breakdown</h6>
        <div class="row g-3 mb-4">
            <div class="col-6 col-md-3">
                <div class="bento-banner banner-gold text-center py-3">
                    <div class="fs-2 fw-bold mb-1"><?php echo number_format($categoryCounts['GOLD']); ?></div>
                    <div class="fw-semibold text-white-50 small text-uppercase tracking-wider">Gold Category</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="bento-banner banner-silver text-center py-3">
                    <div class="fs-2 fw-bold mb-1"><?php echo number_format($categoryCounts['SILVER']); ?></div>
                    <div class="fw-semibold text-white-50 small text-uppercase tracking-wider">Silver Category</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="bento-banner banner-bronze text-center py-3">
                    <div class="fs-2 fw-bold mb-1"><?php echo number_format($categoryCounts['BRONZE']); ?></div>
                    <div class="fw-semibold text-white-50 small text-uppercase tracking-wider">Bronze Category</div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="bento-banner banner-red text-center py-3">
                    <div class="fs-2 fw-bold mb-1"><?php echo number_format($categoryCounts['NON-MIGS']); ?></div>
                    <div class="fw-semibold text-white-50 small text-uppercase tracking-wider">Non-MIGS</div>
                </div>
            </div>
        </div>

        <h6 class="fw-bold text-secondary text-uppercase small tracking-wider mb-3">Total Quantities Claimed Per Item</h6>
        <div class="row g-2 mb-4">
            <div class="col-6 col-sm-4 col-md">
                <div class="item-metric-card">
                    <div class="item-metric-val text-primary"><?php echo number_format($itemTotals['total_tshirts']); ?></div>
                    <div class="item-metric-lbl">GA T-Shirts</div>
                </div>
            </div>
            <div class="col-6 col-sm-4 col-md">
                <div class="item-metric-card">
                    <div class="item-metric-val text-success"><?php echo number_format($itemTotals['total_cash']); ?></div>
                    <div class="item-metric-lbl">Cash Allowance</div>
                </div>
            </div>
            <div class="col-6 col-sm-4 col-md">
                <div class="item-metric-card">
                    <div class="item-metric-val text-info"><?php echo number_format($itemTotals['total_snacks']); ?></div>
                    <div class="item-metric-lbl">Snacks / Meals</div>
                </div>
            </div>
            <div class="col-6 col-sm-4 col-md">
                <div class="item-metric-card">
                    <div class="item-metric-val text-warning"><?php echo number_format($itemTotals['total_umbrellas']); ?></div>
                    <div class="item-metric-lbl">PMPC Umbrellas</div>
                </div>
            </div>
            <div class="col-6 col-sm-4 col-md">
                <div class="item-metric-card">
                    <div class="item-metric-val text-danger"><?php echo number_format($itemTotals['total_bottles']); ?></div>
                    <div class="item-metric-lbl">Water Bottles</div>
                </div>
            </div>
        </div>

        <div class="pt-3 border-top" style="border-color: var(--card-border) !important;">
            <label class="form-label text-secondary small fw-bold text-uppercase tracking-wider mb-2">Filter Statistics By Date</label>
            <form method="GET" action="dashboard.php" class="row g-2 align-items-center">
                <div class="col-auto flex-grow-1 flex-md-grow-0">
                    <input type="date" name="filter_date" class="form-control custom-input" value="<?php echo htmlspecialchars($filterDate, ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <div class="col-auto d-flex gap-2">
                    <button type="submit" class="btn btn-primary rounded-3 btn-sm px-3 fw-semibold"><i class="bi bi-funnel-fill me-1"></i> Filter</button>
                    <a href="dashboard.php" class="btn btn-outline-secondary rounded-3 btn-sm px-3 fw-semibold">Reset</a>
                </div>
            </form>
        </div>
    </div>

    <div class="admin-card">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 mb-4">
            <div>
                <h5 class="fw-bold mb-1"><i class="bi bi-clock-history text-primary me-2"></i>Live Attendance & Claim Log</h5>
                <p class="text-secondary small mb-0">Real-time log of check-ins and quantities claimed</p>
            </div>
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
            <table class="table custom-table align-middle text-center">
                <thead>
                    <tr>
                        <th class="text-start">Member ID</th>
                        <th class="text-start">Member Name</th>
                        <th>Category</th>
                        <th>Branch</th>
                        <th>T-Shirt</th>
                        <th>Cash</th>
                        <th>Snacks</th>
                        <th>Umbrella</th>
                        <th>Water Bottle</th>
                        <th>Arrival Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($attendanceLogs && $attendanceLogs->num_rows > 0): ?>
                        <?php while ($log = $attendanceLogs->fetch_assoc()): ?>
                            <?php 
                                $catUpper = strtoupper($log['category'] ?? '');
                                $badgeClass = 'badge-nonmigs';
                                if (str_contains($catUpper, 'GOLD')) {
                                    $badgeClass = 'badge-gold';
                                } elseif (str_contains($catUpper, 'SILVER')) {
                                    $badgeClass = 'badge-silver';
                                } elseif (str_contains($catUpper, 'BRONZE')) {
                                    $badgeClass = 'badge-bronze';
                                }
                            ?>
                            <tr>
                                <td class="fw-bold text-primary text-start">#<?php echo htmlspecialchars($log['member_id'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td class="fw-semibold text-start"><?php echo htmlspecialchars($log['member_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <span class="badge badge-pill-custom <?php echo $badgeClass; ?>">
                                        <?php echo htmlspecialchars($log['category'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge bg-secondary bg-opacity-10 text-secondary border border-secondary border-opacity-20 rounded-pill px-2.5 py-1 small">
                                        <?php echo htmlspecialchars($log['branch'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?php echo $log['qty_tshirt'] > 0 ? 'badge-claimed' : 'badge-unclaimed'; ?>">
                                        <?php echo $log['qty_tshirt'] > 0 ? $log['qty_tshirt'] : '-'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?php echo $log['qty_cash'] > 0 ? 'badge-claimed' : 'badge-unclaimed'; ?>">
                                        <?php echo $log['qty_cash'] > 0 ? $log['qty_cash'] : '-'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?php echo $log['qty_snacks'] > 0 ? 'badge-claimed' : 'badge-unclaimed'; ?>">
                                        <?php echo $log['qty_snacks'] > 0 ? $log['qty_snacks'] : '-'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?php echo $log['qty_umbrella'] > 0 ? 'badge-claimed' : 'badge-unclaimed'; ?>">
                                        <?php echo $log['qty_umbrella'] > 0 ? $log['qty_umbrella'] : '-'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="<?php echo $log['qty_gold_bottle'] > 0 ? 'badge-claimed' : 'badge-unclaimed'; ?>">
                                        <?php echo $log['qty_gold_bottle'] > 0 ? $log['qty_gold_bottle'] : '-'; ?>
                                    </span>
                                </td>
                                <td class="text-nowrap text-secondary small">
                                    <i class="bi bi-calendar-event me-1"></i><?php echo htmlspecialchars($log['arrival_date'], ENT_QUOTES, 'UTF-8'); ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="10" class="text-center text-secondary py-5">
                                <i class="bi bi-inbox fs-2 d-block mb-2 text-opacity-50"></i>
                                No attendance records found for the selected view.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</main>

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

    function updateThemeUI() {
        const isDark = document.documentElement.classList.contains('dark-theme');
        const themeIcon = document.getElementById('themeIcon');
        const themeText = document.getElementById('themeText');

        if (isDark) {
            themeIcon.className = 'bi bi-sun-fill';
            themeText.innerText = 'Light Mode';
        } else {
            themeIcon.className = 'bi bi-moon-stars-fill';
            themeText.innerText = 'Dark Mode';
        }
    }

    function toggleTheme() {
        document.documentElement.classList.toggle('dark-theme');
        const isDark = document.documentElement.classList.contains('dark-theme');
        localStorage.setItem('admin-theme', isDark ? 'dark' : 'light');
        updateThemeUI();
    }

    document.addEventListener('DOMContentLoaded', updateThemeUI);
</script>
</body>
</html>