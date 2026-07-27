<?php
require_once 'session_start.php';
include '../config/db.php';
// The rest of your specific page logic continues below...

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

// Whitelist allowed sorting columns to maintain security
$allowedSortColumns = ['id', 'full_name', 'branch_name', 'migs_category'];
$sort = isset($_GET['sort']) && in_array($_GET['sort'], $allowedSortColumns) ? $_GET['sort'] : 'full_name';

// Validate order parameters
$order = isset($_GET['order']) && strtoupper($_GET['order']) === 'DESC' ? 'DESC' : 'ASC';

// Toggle order for the clickable link state
$nextOrder = ($order === 'ASC') ? 'desc' : 'asc';
$branchArrow = ($sort === 'branch_name') ? ($order === 'ASC' ? ' 🔼' : ' 🔽') : '';

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

<title>Manage Voters</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

:root{
    --bg:#f1f5f9;
    --card:#ffffff;
    --text:#0f172a;
    --border:#e2e8f0;
}

.dark-theme{
    --bg:#0f172a;
    --card:#162338;
    --text:#f8fafc;
    --border:#334155;
}

body{
    background:var(--bg);
    color:var(--text);
    transition:.3s;
}

.container{
    padding-top:90px;
    padding-bottom:50px;
}

.card-custom{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:20px;
    overflow:hidden;
}

.page-title{
    font-size:2rem;
    font-weight:700;
    margin:0;
}

.theme-btn{
    position:fixed;
    top:20px;
    right:20px;
    z-index:9999;
    border:none;
    border-radius:50px;
    padding:12px 20px;
    background:#1e293b;
    color:#fff;
    font-weight:600;
    box-shadow:0 5px 15px rgba(0,0,0,.2);
}

.dark-theme .theme-btn{
    background:#334155;
}

.stats-card{
    border:none;
    border-radius:18px;
    color:#fff;
}

.stat-number{
    font-size:2rem;
    font-weight:700;
}

.table{
    color:var(--text);
}

.dark-theme .table{
    color:#fff;
}

.form-control{
    border-radius:12px;
}

.badge{
    font-size:.85rem;
    padding:8px 12px;
}

.header-row{
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:25px;
    flex-wrap:wrap;
    gap:15px;
}

/* Sort link formatting styling */
.sort-link {
    color: inherit;
    text-decoration: none;
}
.sort-link:hover {
    text-decoration: underline;
}

</style>

</head>

<body>

<button class="theme-btn" onclick="toggleTheme()">
    <span id="themeText">🌙 Dark Mode</span>
</button>

<div class="container">

<div class="header-row">

    <div>

        <h1 class="page-title">
            👥 Manage Voters
        </h1>

        <small class="text-secondary">
            Viewing registered cooperative members who have completed voting
        </small>

    </div>

    <a href="dashboard.php" class="btn btn-secondary">
        ← Dashboard
    </a>

</div>

<?php if(!empty($message)){ ?>
<div class="alert alert-danger mb-4">
    <?= htmlspecialchars($message); ?>
</div>
<?php } ?>

<!-- STATISTICS -->

<div class="row mb-4">

<div class="col-md-3 mb-3">

<div class="card stats-card bg-primary shadow">

<div class="card-body text-center">

<div class="stat-number">
<?= $totalMembers ?>
</div>

<div>
Total Members
</div>

</div>

</div>

</div>

<div class="col-md-3 mb-3">

<div class="card stats-card bg-success shadow">

<div class="card-body text-center">

<div class="stat-number">
<?= $totalAwardees ?>
</div>

<div>
Awardees
</div>

</div>

</div>

</div>

<div class="col-md-3 mb-3">

<div class="card stats-card bg-info shadow">

<div class="card-body text-center">

<div class="stat-number">
<?= $totalPrinted ?>
</div>

<div>
Printed IDs
</div>

</div>

</div>

</div>

<div class="col-md-3 mb-3">

<div class="card stats-card bg-warning shadow">

<div class="card-body text-center">

<div class="stat-number">
<?= $totalAllowance ?>
</div>

<div>
Allowance Claimed
</div>

</div>

</div>

</div>

</div>

<!-- SEARCH -->

<div class="card card-custom shadow-sm mb-4">

<div class="card-body">

<form method="GET">

<!-- Preserve sorting parameters across searches -->
<input type="hidden" name="sort" value="<?= htmlspecialchars($sort); ?>">
<input type="hidden" name="order" value="<?= htmlspecialchars($order); ?>">

<div class="row">

<div class="col-md-10">

<input
type="text"
name="search"
class="form-control"
placeholder="Search completed voters..."
value="<?= htmlspecialchars($search); ?>"
>

</div>

<div class="col-md-2">

<button
type="submit"
class="btn btn-primary w-100">

Search

</button>

</div>

</div>

</form>

</div>

</div>

<!-- VOTERS TABLE -->

<div class="card card-custom shadow-sm">

<div class="card-header bg-dark text-white d-flex justify-content-between align-items-center">
    <span>Members Done Voting</span>
    <?php if($sort === 'branch_name'){ ?>
        <small class="badge bg-secondary">Sorted by Branch (<?= $order ?>)</small>
    <?php } ?>
</div>

<div class="card-body">

<div class="table-responsive">

<table class="table table-hover align-middle">

<thead>

<tr>

<th>ID</th>
<th>Full Name</th>
<th>
    <!-- Interactive Header Link for Sorting by Branch -->
    <a href="?sort=branch_name&order=<?= $nextOrder; ?>&search=<?= urlencode($search); ?>" class="sort-link fw-bold text-primary">
        Branch<?= $branchArrow; ?> 🔄
    </a>
</th>
<th>MIGS Category</th>
<th>Awardee</th>
<th>Printed</th>
<th>Allowance</th>
<th width="140">Action</th>

</tr>

</thead>

<tbody>

<?php if($voters->num_rows > 0){ ?>
    <?php while($row = $voters->fetch_assoc()){ ?>

    <tr>

    <td>
    <?= $row['id']; ?>
    </td>

    <td>
    <?= htmlspecialchars($row['full_name']); ?>
    </td>

    <td>
    <?= htmlspecialchars($row['branch_name']); ?>
    </td>

    <td>
    <?= htmlspecialchars($row['migs_category']); ?>
    </td>

    <td>

    <?php

    $awardee = trim($row['awardee'] ?? '');

    if(
        !empty($awardee) &&
        strtoupper($awardee) !== 'N/A'
    ){
    ?>

    <span class="badge bg-success">
        Awardee
    </span>

    <?php } else { ?>

    <span class="badge bg-secondary">
        Regular
    </span>

    <?php } ?>

    </td>

    <td>

    <?php if($row['printed']){ ?>

    <span class="badge bg-primary">
        Printed
    </span>

    <?php } else { ?>

    <span class="badge bg-danger">
        Not Printed
    </span>

    <?php } ?>

    </td>

    <td>

    <?php if($row['allowance_claimed']){ ?>

    <span class="badge bg-success">
        Claimed
    </span>

    <?php } else { ?>

    <span class="badge bg-warning text-dark">
        Pending
    </span>

    <?php } ?>

    </td>

    <td>

    <a
    href="?reset_vote=<?= $row['id']; ?>"
    class="btn btn-warning btn-sm fw-semibold"
    onclick="return confirm('Are you sure you want to completely wipe out this user\'s ballot record and grant them a second chance to vote?')">
        🔄 Reset Vote
    </a>

    </td>

    </tr>

    <?php } ?>
<?php } else { ?>
    <tr>
        <td colspan="8" class="text-center py-4 text-muted">
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

<script>

function toggleTheme(){

    document.body.classList.toggle('dark-theme');

    if(document.body.classList.contains('dark-theme')){

        localStorage.setItem('admin-theme','dark');

        document.getElementById('themeText').innerHTML =
        '☀️ Light Mode';

    }else{

        localStorage.setItem('admin-theme','light');

        document.getElementById('themeText').innerHTML =
        '🌙 Dark Mode';
    }
}

window.onload = function(){

    if(localStorage.getItem('admin-theme') === 'dark'){

        document.body.classList.add('dark-theme');

        document.getElementById('themeText').innerHTML =
        '☀️ Light Mode';
    }
}

</script>

</body>
</html>