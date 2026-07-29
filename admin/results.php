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

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Election Results</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
    --bg:#f1f5f9;
    --card:#ffffff;
    --text:#1e293b;
    --text-muted:#64748b;
    --border:#e2e8f0;
    --widget-bg:#f8fafc;
    --chart-label-color:#1e293b;
    --badge-bg: #e2e8f0;
    --badge-color: #1e293b;
}

.dark-theme{
    --bg:#0f172a;
    --card:#162338;
    --text:#f8fafc;
    --text-muted:#94a3b8;
    --border:#334155;
    --widget-bg:#1e293b;
    --chart-label-color:#cbd5e1;
    --badge-bg: #334155;
    --badge-color: #f8fafc;
}

body{
    background:var(--bg);
    color:var(--text);
    transition:.3s;
    font-size: clamp(0.875rem, 0.22vw + 0.82rem, 1rem);
}

.container{
    padding-top:100px;
    padding-bottom:60px;
    max-width: 1200px;
}

.card-custom{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:20px;
    overflow:hidden;
}

.stats-card {
    background-color: var(--widget-bg) !important;
    border: 1px solid var(--border) !important;
    border-radius: 14px !important;
    color: var(--text) !important;
    transition: .3s;
    height: 100%;
}

.table{
    color:var(--text);
}

.table th {
    white-space: nowrap;
}

.avatar-wrapper {
    position: relative;
    display: inline-block;
}

.candidate-avatar {
    width: 42px;
    height: 42px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid var(--border);
    background-color: #e2e8f0;
}

.color-dot {
    width: 14px;
    height: 14px;
    border-radius: 50%;
    display: inline-block;
    border: 2px solid #ffffff;
    position: absolute;
    bottom: 0;
    right: -2px;
    box-shadow: 0 1px 3px rgba(0,0,0,0.3);
}

.custom-badge {
    background-color: var(--badge-bg);
    color: var(--badge-color);
    font-size: 0.9rem;
    border-radius: 8px;
    display: inline-block;
    transition: background-color 0.3s, color 0.3s;
}

.text-custom-muted {
    color: var(--text-muted);
}

.dark-theme .table {
    color:#f8fafc;
    border-color: var(--border);
}

.dark-theme .table-striped>tbody>tr:nth-of-type(odd)>* {
    --bs-table-color-type: #f8fafc;
    --bs-table-bg-type: rgba(255, 255, 255, 0.03);
}

.dark-theme .table-hover>tbody>tr:hover>* {
    --bs-table-color-type: #f8fafc;
    --bs-table-bg-type: rgba(255, 255, 255, 0.06);
}

.theme-btn{
    position:fixed;
    top:20px;
    right:20px;
    z-index:9999;
    border:none;
    border-radius:30px;
    padding:10px 18px;
    font-weight:600;
    background:#1e293b;
    color:white;
    box-shadow:0 4px 15px rgba(0,0,0,.2);
    font-size: 0.9rem;
}

.dark-theme .theme-btn{
    background:#334155;
}

.page-title{
    font-size: clamp(1.4rem, 3vw, 2rem);
    font-weight:700;
    margin:0;
}

.header-section{
    display:flex;
    align-items:center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap:15px;
    margin-bottom:30px;
}

.header-left {
    display: flex;
    align-items: center;
    gap: 15px;
}

.filter-select {
    max-width: 250px;
    border-radius: 10px;
    padding: 8px 12px;
    font-weight: 500;
    background-color: var(--card);
    color: var(--text);
    border: 1px solid var(--border);
}

.back-btn{
    border-radius:12px;
    padding:8px 16px;
    font-size: 0.95rem;
}

/* Responsive Scaling Breakpoints */
@media (max-width: 991.98px) {
    .chart-container-wrapper {
        margin-top: 20px;
    }
}

@media (max-width: 576px) {
    .container {
        padding-top: 110px;
        padding-left: 12px;
        padding-right: 12px;
    }
    .header-section {
        margin-bottom: 20px;
        flex-direction: column;
        align-items: flex-start;
    }
    .header-left {
        width: 100%;
    }
    .filter-select {
        width: 100%;
        max-width: 100%;
    }
    .card-body {
        padding: 15px !important;
    }
    .theme-btn {
        top: 15px;
        right: 15px;
        padding: 8px 14px;
        font-size: 0.8rem;
    }
}
</style>
</head>
<body>

<button class="theme-btn" onclick="toggleTheme()">
    <span id="themeText">🌙 Dark Mode</span>
</button>

<div class="container">

<div class="header-section">
    <div class="header-left">
        <a href="dashboard.php" class="btn btn-secondary back-btn">
            ← Dashboard
        </a>
        <h1 class="page-title">🗳️ Election Live Results</h1>
    </div>
    
    <!-- Branch Filtering Selection Dropdown Component -->
    <div>
        <select class="form-select filter-select" id="branchFilter" onchange="filterBranch(this.value)">
            <option value="overall" <?php echo ($selected_branch === 'overall') ? 'selected' : ''; ?>>🌐 Overall Results</option>
            <?php foreach($branches as $b): ?>
                <option value="<?php echo htmlspecialchars($b); ?>" <?php echo ($selected_branch === $b) ? 'selected' : ''; ?>>
                    📍 Branch: <?php echo htmlspecialchars($b); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
</div>

<?php

// Expanded, distinct, high-contrast color palette for candidates & charts
$chart_colors = [
    '#2563eb', // Royal Blue
    '#16a34a', // Emerald Green
    '#d97706', // Warm Amber
    '#dc2626', // Bright Red
    '#9333ea', // Deep Purple
    '#0891b2', // Dark Cyan
    '#db2777', // Vivid Pink
    '#ea580c', // Bright Orange
    '#4f46e5', // Indigo
    '#059669', // Mint Green
    '#ca8a04', // Deep Yellow
    '#be123c', // Crimson
    '#7c3aed', // Violet
    '#0d9488', // Teal
    '#c026d3', // Magenta
    '#b45309', // Rust Orange
    '#0284c7', // Sky Blue
    '#15803d', // Forest Green
    '#801b9c', // Deep Purple Magenta
    '#e11d48'  // Rose
];

$positions = $conn->query("SELECT * FROM positions ORDER BY id");

// Collect data arrays for global Javascript chart definitions
$chart_js_data = [];

while($position = $positions->fetch_assoc()){
    $position_id = $position['id'];
    $position_name = $position['position_name'];
?>

<div class="card card-custom shadow-sm mb-5">

<div class="card-header bg-primary text-white py-3">
    <h4 class="mb-0" style="font-size: 1.25rem;"><?php echo htmlspecialchars($position_name); ?></h4>
</div>

<div class="card-body p-4">

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
    
    <div class="col-lg-7 mb-2 mb-lg-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th width="12%">Candidate</th>
                        <th>Name</th>
                        <th width="30%">Votes Received</th>
                    </tr>
                </thead>
                <tbody>
                <?php 
                foreach($table_rows as $index => $candidate) { 
                    $candidate_img = (!empty($candidate['photo']) && file_exists('../assets/images/' . $candidate['photo'])) 
                        ? '../assets/images/' . htmlspecialchars($candidate['photo']) 
                        : 'https://via.placeholder.com/150/cbd5e1/1e293b?text=User';
                    
                    // Match assigned unique chart color index
                    $assigned_color = $chart_colors[$index % count($chart_colors)];
                ?>
                    <tr>
                        <td>
                            <div class="avatar-wrapper">
                                <img src="<?php echo $candidate_img; ?>" alt="<?php echo htmlspecialchars($candidate['full_name']); ?>" class="candidate-avatar">
                                <span class="color-dot" style="background-color: <?php echo $assigned_color; ?>;" title="Chart Color"></span>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge" style="background-color: <?php echo $assigned_color; ?>; width: 12px; height: 12px; padding: 0; border-radius: 50%; display: inline-block;"></span>
                                <strong><?php echo htmlspecialchars($candidate['full_name']); ?></strong>
                            </div>
                        </td>
                        <td>
                            <span class="custom-badge px-3 py-2">
                                <strong><?php echo $candidate['total_votes']; ?></strong> votes
                            </span>
                        </td>
                    </tr>
                <?php 
                } 
                ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div class="col-lg-5 chart-container-wrapper">
        <div class="p-2" style="position: relative; height: 280px; max-height: 280px; margin: 0 auto; width: 100%;">
            <canvas id="chart_pos_<?php echo $position_id; ?>"></canvas>
        </div>
    </div>

</div>

<?php
} else {
?>
    <div class="alert alert-warning mb-0" style="border-radius:12px;">
        No candidates found for this position.
    </div>
<?php
}
?>

</div>

</div>

<?php
}
?>

<div class="card card-custom shadow-sm">

<div class="card-header bg-dark text-white py-3">
    📊 Election Turnout Statistics
</div>

<div class="card-body p-4">

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

    <div class="col-sm-6 col-md-4">
        <div class="card text-center stats-card shadow-sm">
            <div class="card-body py-4">
                <h2 class="text-primary mb-1"><strong><?php echo $total_voters; ?></strong></h2>
                <p class="text-custom-muted mb-0 fw-bold">Total Members Registered</p>
            </div>
        </div>
    </div>

    <div class="col-sm-6 col-md-4">
        <div class="card text-center stats-card shadow-sm">
            <div class="card-body py-4">
                <h2 class="text-success mb-1"><strong><?php echo $voted_members; ?></strong></h2>
                <p class="text-custom-muted mb-0 fw-bold">Voters Checked In (<?php echo $turnout_rate; ?>%)</p>
            </div>
        </div>
    </div>

    <div class="col-sm-12 col-md-4">
        <div class="card text-center stats-card shadow-sm">
            <div class="card-body py-4">
                <h2 class="text-info mb-1"><strong><?php echo $total_votes; ?></strong></h2>
                <p class="text-custom-muted mb-0 fw-bold">Aggregated Total Ballots Cast</p>
            </div>
        </div>
    </div>

</div>

</div>

</div>

</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const chartInstances = {};
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
    return document.body.classList.contains('dark-theme') ? '#cbd5e1' : '#1e293b';
}

function getChartBorderColor() {
    return document.body.classList.contains('dark-theme') ? '#162338' : '#ffffff';
}

function getResponsiveLegendPosition() {
    return window.innerWidth < 576 ? 'bottom' : 'right';
}

function buildCharts() {
    Object.keys(rawChartData).forEach(positionId => {
        const canvasElement = document.getElementById(`chart_pos_${positionId}`);
        if(!canvasElement) return;
        
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
                            font: { family: 'Segoe UI', size: 12, weight: '500' },
                            padding: 12
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
}

function toggleTheme(){
    document.body.classList.toggle('dark-theme');

    if(document.body.classList.contains('dark-theme')){
        localStorage.setItem('admin-theme','dark');
        document.getElementById('themeText').innerHTML='☀️ Light Mode';
    }else{
        localStorage.setItem('admin-theme','light');
        document.getElementById('themeText').innerHTML='🌙 Dark Mode';
    }
    updateChartThemes();
}

window.addEventListener('resize', () => {
    updateChartThemes();
});

window.onload = function(){
    if(localStorage.getItem('admin-theme')==='dark'){
        document.body.classList.add('dark-theme');
        document.getElementById('themeText').innerHTML='☀️ Light Mode';
    }
    buildCharts();
}
</script>
</body>
</html>