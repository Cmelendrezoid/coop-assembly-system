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
| SYNC BRANCHES FROM MEMBERS
|--------------------------------------------------------------------------
*/

$member_branches = $conn->query("
    SELECT DISTINCT branch_name
    FROM members
    WHERE branch_name IS NOT NULL
    AND branch_name <> ''
");

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

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Branch Voting Control</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

:root{
    --bg:#f1f5f9;
    --card:#ffffff;
    --text:#1e293b;
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
    padding-top:80px;
}

.card-custom{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:20px;
    overflow:hidden;
}

.theme-btn{
    position:fixed;
    top:20px;
    right:20px;
    border:none;
    border-radius:30px;
    padding:12px 20px;
    background:#1e293b;
    color:white;
    font-weight:600;
    z-index:9999;
}

.dark-theme .theme-btn{
    background:#334155;
}

.page-title{
    font-size:2rem;
    font-weight:700;
}

.header-section{
    display:flex;
    align-items:center;
    gap:15px;
    margin-bottom:30px;
}

.back-btn{
    border-radius:12px;
}

.dark-theme .table{
    color:white;
}

.badge-open{
    background:#10b981;
    color:white;
    padding:8px 12px;
    border-radius:10px;
}

.badge-closed{
    background:#ef4444;
    color:white;
    padding:8px 12px;
    border-radius:10px;
}

.card-header{
    font-weight:600;
}

</style>

</head>

<body>

<button class="theme-btn" onclick="toggleTheme()">
<span id="themeText">🌙 Dark Mode</span>
</button>

<div class="container">

<div class="header-section">

<a
href="dashboard.php"
class="btn btn-secondary back-btn">

← Dashboard

</a>

<h1 class="page-title">
🗳 Branch Voting Control
</h1>

</div>

<div class="card card-custom shadow-sm">

<div class="card-header bg-primary text-white">
Branch Voting Access
</div>

<div class="card-body">

<div class="table-responsive">

<table class="table table-hover align-middle">

<thead>

<tr>

<th>ID</th>
<th>Branch</th>
<th>Status</th>
<th width="250">Action</th>

</tr>

</thead>

<tbody>

<?php while($row = $schedules->fetch_assoc()){ ?>

<tr>

<td>
<?= $row['id']; ?>
</td>

<td>
<?= htmlspecialchars($row['branch_name']); ?>
</td>

<td>

<?php if($row['status'] == 'OPEN'){ ?>

<span class="badge-open">
OPEN
</span>

<?php } else { ?>

<span class="badge-closed">
CLOSED
</span>

<?php } ?>

</td>

<td>

<a
href="?toggle=<?= $row['id']; ?>"
class="btn <?= ($row['status']=='OPEN') ? 'btn-warning' : 'btn-success'; ?>">

<?= ($row['status']=='OPEN')
    ? '🔒 Close Voting'
    : '🟢 Open Voting'; ?>

</a>

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

        localStorage.setItem(
            'admin-theme',
            'dark'
        );

        document.getElementById(
            'themeText'
        ).innerHTML =
            '☀️ Light Mode';

    }else{

        localStorage.setItem(
            'admin-theme',
            'light'
        );

        document.getElementById(
            'themeText'
        ).innerHTML =
            '🌙 Dark Mode';
    }
}

window.onload=function(){

    if(
        localStorage.getItem(
            'admin-theme'
        ) === 'dark'
    ){

        document.body.classList.add(
            'dark-theme'
        );

        document.getElementById(
            'themeText'
        ).innerHTML =
            '☀️ Light Mode';
    }
}

</script>

</body>
</html>