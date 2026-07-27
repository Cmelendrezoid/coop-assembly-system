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
<title>Manage Positions</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root{
    --bg:#f1f5f9;
    --card:#ffffff;
    --text:#1e293b;
    --placeholder-color: rgba(30, 41, 59, 0.5);
    --border:#e2e8f0;
    --input-bg:#ffffff;
    --input-color:#1e293b;
    --table-bg:#ffffff;
    --table-text:#1e293b;
    --table-header-bg:#f8fafc;
}

.dark-theme{
    --bg:#0f172a;
    --card:#162338;
    --text:#f8fafc;
    --placeholder-color: rgba(248, 250, 252, 0.4);
    --border:#334155;
    --input-bg:#1e293b;
    --input-color:#f8fafc;
    --table-bg:#1e293b;
    --table-text:#f8fafc;
    --table-header-bg:#0f172a;
}

body{
    background:var(--bg);
    color:var(--text);
    transition:.3s;
    font-size: clamp(0.875rem, 0.22vw + 0.82rem, 1rem);
}

.container{
    padding-top: 100px;
    padding-bottom: 60px;
    max-width: 1200px;
}

.card-custom{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:20px;
    overflow:hidden;
}

/* Explicit Structural Theme Rules targeting clean text visibility */
.table {
    background-color: var(--table-bg) !important;
    color: var(--table-text) !important;
    border-color: var(--border) !important;
}

.table th {
    background-color: var(--table-header-bg) !important;
    color: var(--table-text) !important;
    white-space: nowrap;
}

.table td {
    background-color: var(--table-bg) !important;
    color: var(--table-text) !important;
}

.table td strong,
.table-muted-text {
    color: var(--table-text) !important;
}

.dark-theme .table-striped>tbody>tr:nth-of-type(odd)>td {
    background-color: rgba(255, 255, 255, 0.02) !important;
}

.form-label {
    color: var(--text);
    font-weight: 500;
    transition: color .3s;
}

.form-control,
.form-select{
    border-radius:12px;
    background-color: var(--input-bg);
    border-color: var(--border);
    color: var(--input-color);
    transition: background-color .3s, border-color .3s, color .3s;
}

.form-control:focus,
.form-select:focus {
    background-color: var(--input-bg);
    color: var(--input-color);
    border-color: #3b82f6;
    box-shadow: 0 0 0 0.25rem rgba(59, 130, 246, 0.25);
}

.form-control::placeholder {
    color: var(--placeholder-color) !important;
    opacity: 1;
}

/* Reusable Modal Dark Theme Overrides */
.dark-theme .modal-content {
    background-color: var(--card);
    color: var(--text);
    border: 1px solid var(--border);
}
.dark-theme .modal-header {
    border-bottom: 1px solid var(--border);
}
.dark-theme .modal-footer {
    border-top: 1px solid var(--border);
}
.dark-theme .btn-close {
    filter: invert(1) grayscale(1) brightness(2);
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
    gap:15px;
    margin-bottom:30px;
}

.back-btn{
    border-radius:12px;
    padding:8px 16px;
    font-size: 0.95rem;
}

.card-header{
    font-weight:600;
}

/* Custom Responsive CSS Breakpoints for Tablets and Smartphones */
@media (max-width: 576px) {
    .container {
        padding-top: 85px;
        padding-left: 12px;
        padding-right: 12px;
    }
    .header-section {
        margin-bottom: 20px;
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
    .d-flex.gap-2 {
        flex-direction: column;
        width: 100%;
    }
    .d-flex.gap-2 button, 
    .d-flex.gap-2 a {
        width: 100%;
        text-align: center;
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
    <a href="dashboard.php" class="btn btn-secondary back-btn">
        ← Dashboard
    </a>
    <h1 class="page-title">
        📋 Manage Positions
    </h1>
</div>

<?php if($message){ ?>
<div class="alert alert-success shadow-sm" style="border-radius:12px;">
    <?= $message ?>
</div>
<?php } ?>

<div class="card card-custom shadow-sm mb-5">
<div class="card-header bg-primary text-white py-3">
    ➕ Add New Position
</div>
<div class="card-body p-4">
<form method="POST">
<div class="row">

    <div class="col-md-8 mb-3">
        <label class="form-label">Position Name</label>
        <input
            type="text"
            name="position_name"
            class="form-control"
            placeholder="e.g. Board of Director, Auditor, Committee Member"
            required>
    </div>

    <div class="col-md-4 mb-3">
        <label class="form-label">Vote Limit</label>
        <input
            type="number"
            name="vote_limit"
            class="form-control"
            placeholder="Maximum choices allowed"
            required
            min="1"
            value="1">
    </div>

</div>

<div class="d-flex gap-2 mt-2">
    <button
        type="submit"
        name="add_position"
        class="btn btn-primary px-4 py-2"
        style="border-radius:10px;">
        Add Position
    </button>
</div>
</form>
</div>
</div>

<div class="card card-custom shadow-sm">
<div class="card-header bg-dark text-white py-3">
    Position Overview List
</div>
<div class="card-body p-4">
<div class="table-responsive">
<table class="table table-hover table-striped align-middle mb-0">
<thead>
    <tr>
        <th width="100">ID</th>
        <th>Position</th>
        <th>Vote Limit</th>
        <th width="160">Actions</th>
    </tr>
</thead>
<tbody>
<?php while($row = $positions->fetch_assoc()){ ?>
<tr>
    <td><strong class="table-muted-text">#<?= $row['id']; ?></strong></td>
    <td><strong class="table-muted-text"><?= htmlspecialchars($row['position_name']); ?></strong></td>
    <td>
        <span class="badge bg-info text-dark px-3 py-2" style="border-radius:8px; font-size:0.9rem;">
            <?= $row['vote_limit']; ?> Candidate(s) Max
        </span>
    </td>
    <td>
    <div class="d-flex gap-2">
        <button 
            type="button" 
            class="btn btn-warning btn-sm px-3"
            style="border-radius:8px;"
            onclick="openEditModal(<?= htmlspecialchars(json_encode($row)); ?>)">
            Edit
        </button>
        <a
            href="?delete=<?= $row['id']; ?>"
            class="btn btn-danger btn-sm px-3"
            style="border-radius:8px;"
            onclick="return confirm('Delete this position permanently?');">
            Delete
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

<div class="modal fade" id="editPositionModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-md modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">✏️ Edit Position Information</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST">
        <div class="modal-body">
          <input type="hidden" name="id" id="edit_id">
          
          <div class="mb-3">
            <label class="form-label">Position Name</label>
            <input type="text" name="position_name" id="edit_name" class="form-control" required>
          </div>
          
          <div class="mb-3">
            <label class="form-label">Vote Limit</label>
            <input type="number" name="vote_limit" id="edit_vote_limit" class="form-control" min="1" required>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:10px;">Cancel</button>
          <button type="submit" name="update_position" class="btn btn-success" style="border-radius:10px;">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Instantiate structural reference targeting the edit modal component
const bootstrapEditModal = new bootstrap.Modal(document.getElementById('editPositionModal'));

function openEditModal(positionData) {
    document.getElementById('edit_id').value = positionData.id;
    document.getElementById('edit_name').value = positionData.position_name;
    document.getElementById('edit_vote_limit').value = positionData.vote_limit;
    
    bootstrapEditModal.show();
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
}

window.onload=function(){
    if(localStorage.getItem('admin-theme')==='dark'){
        document.body.classList.add('dark-theme');
        document.getElementById('themeText').innerHTML='☀️ Light Mode';
    }
}
</script>
</body>
</html>