<?php
require_once 'session_start.php';
include '../config/db.php';
// The rest of your specific page logic continues below...

if(!isset($_SESSION['admin_id'])){
    header("Location: login.php");
    exit();
}

function parseCandidateDescription($description){
    $description = trim($description);
    $sections = [
        'accomplishments' => [],
        'platforms' => []
    ];

    if($description === ''){
        return $sections;
    }

    $lines = preg_split('/\r\n|\n|\r/', $description);
    $current = 'accomplishments';
    $hasSection = false;

    foreach($lines as $line){
        $trimmed = trim($line);
        if($trimmed === ''){
            continue;
        }

        if(preg_match('/^accomplishments\s*[:]?$/i', $trimmed)){
            $current = 'accomplishments';
            $hasSection = true;
            continue;
        }

        if(preg_match('/^platforms\s*[:]?$/i', $trimmed)){
            $current = 'platforms';
            $hasSection = true;
            continue;
        }

        $item = preg_replace('/^[\-\*•]\s*/u', '', $trimmed);
        $sections[$current][] = $item;
    }

    if(!$hasSection && !empty($sections['accomplishments']) && empty($sections['platforms'])){
        return ['accomplishments' => $sections['accomplishments'], 'platforms' => []];
    }

    return $sections;
}

function buildCandidateDescription($accomplishments, $platforms){
    $parts = [];

    $accomplishments = array_filter(array_map('trim', explode("\n", $accomplishments)), 'strlen');
    $platforms = array_filter(array_map('trim', explode("\n", $platforms)), 'strlen');

    if(!empty($accomplishments)){
        $parts[] = 'Accomplishments:';
        foreach($accomplishments as $item){
            $parts[] = '- ' . preg_replace('/^[\-\*•]\s*/u', '', $item);
        }
    }

    if(!empty($platforms)){
        if(!empty($parts)){
            $parts[] = '';
        }
        $parts[] = 'Platforms:';
        foreach($platforms as $item){
            $parts[] = '- ' . preg_replace('/^[\-\*•]\s*/u', '', $item);
        }
    }

    return implode("\n", $parts);
}

$message = "";

// 1. ADD CANDIDATE LOGIC
if(isset($_POST['add_candidate'])){

    $full_name = trim($_POST['full_name']);
    $position_id = (int)$_POST['position_id'];
    $description = buildCandidateDescription(
        $_POST['description_accomplishments'] ?? '',
        $_POST['description_platforms'] ?? ''
    );

    $position_stmt = $conn->prepare("
        SELECT position_name
        FROM positions
        WHERE id=?
    ");
    $position_stmt->bind_param("i",$position_id);
    $position_stmt->execute();
    $position_result = $position_stmt->get_result();
    $position = $position_result->fetch_assoc();
    $position_name = $position['position_name'];

    $photo = "";
    // Prefer cropped photo (base64) if provided, else fallback to uploaded file
    if(!empty($_POST['cropped_photo'])){
        $cropped = $_POST['cropped_photo'];
        // Expect data URI like: data:image/png;base64,XXXX
        if(preg_match('/^data:(image\/[a-zA-Z]+);base64,(.+)$/', $cropped, $matches)){
            $mime = $matches[1];
            $data = base64_decode($matches[2]);
            $ext = '';
            switch($mime){
                case 'image/png': $ext = 'png'; break;
                case 'image/gif': $ext = 'gif'; break;
                default: $ext = 'jpg'; break;
            }
            $upload_dir = "../assets/images/";
            if(!is_dir($upload_dir)) mkdir($upload_dir,0777,true);
            $photo = time() . "_" . uniqid() . "." . $ext;
            file_put_contents($upload_dir . $photo, $data);
        }
    } elseif(isset($_FILES['photo']) && $_FILES['photo']['error'] == 0){
        $upload_dir = "../assets/images/";
        if(!is_dir($upload_dir)){
            mkdir($upload_dir,0777,true);
        }
        $photo = time() . "_" . basename($_FILES['photo']['name']);
        move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $photo);
    }

    $stmt = $conn->prepare("
        INSERT INTO candidates (full_name, position_name, photo, description, education, position_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssssi", $full_name, $position_name, $photo, $description, $education, $position_id);

    if($stmt->execute()){
        $message = "Candidate added successfully.";
    }
}

// 2. EDIT CANDIDATE LOGIC
if(isset($_POST['edit_candidate'])){
    $candidate_id = (int)$_POST['candidate_id'];
    $full_name = trim($_POST['full_name']);
    $position_id = (int)$_POST['position_id'];
    $education = trim($_POST['education'] ?? '');
    $description = buildCandidateDescription(
        $_POST['description_accomplishments'] ?? '',
        $_POST['description_platforms'] ?? ''
    );
    
    // Fetch associated position name
    $position_stmt = $conn->prepare("SELECT position_name FROM positions WHERE id=?");
    $position_stmt->bind_param("i", $position_id);
    $position_stmt->execute();
    $position_result = $position_stmt->get_result();
    $position = $position_result->fetch_assoc();
    $position_name = $position['position_name'];

    // If a cropped photo base64 is provided, use that first
    if(!empty($_POST['cropped_photo'])){
        $cropped = $_POST['cropped_photo'];
        if(preg_match('/^data:(image\/[a-zA-Z]+);base64,(.+)$/', $cropped, $matches)){
            $mime = $matches[1];
            $data = base64_decode($matches[2]);
            $ext = '';
            switch($mime){
                case 'image/png': $ext = 'png'; break;
                case 'image/gif': $ext = 'gif'; break;
                default: $ext = 'jpg'; break;
            }
            $upload_dir = "../assets/images/";
            if(!is_dir($upload_dir)) mkdir($upload_dir,0777,true);
            $photo = time() . "_" . uniqid() . "." . $ext;
            file_put_contents($upload_dir . $photo, $data);
        }
    } elseif(isset($_FILES['photo']) && $_FILES['photo']['error'] == 0){
        $upload_dir = "../assets/images/";
        if(!is_dir($upload_dir)){
            mkdir($upload_dir,0777,true);
        }
        $photo = time() . "_" . basename($_FILES['photo']['name']);
        move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $photo);
    }

    // Prepare update statement based on whether we have a new photo
    if(!empty($photo)){
        $stmt = $conn->prepare("
            UPDATE candidates 
            SET full_name=?, position_name=?, photo=?, description=?, education=?, position_id=? 
            WHERE id=?
        ");
        $stmt->bind_param("ssssiii", $full_name, $position_name, $photo, $description, $education, $position_id, $candidate_id);
    } else {
        // Keep the old photo if a new file isn't uploaded
        $stmt = $conn->prepare("
            UPDATE candidates 
            SET full_name=?, position_name=?, description=?, education=?, position_id=? 
            WHERE id=?
        ");
        $stmt->bind_param("ssssii", $full_name, $position_name, $description, $education, $position_id, $candidate_id);
    }

    if($stmt->execute()){
        $message = "Candidate updated successfully.";
    }
}

// 3. DELETE CANDIDATE LOGIC
if(isset($_GET['delete'])){

    $id = (int)$_GET['delete'];

    $stmt = $conn->prepare("
        DELETE FROM candidates
        WHERE id=?
    ");
    $stmt->bind_param("i",$id);
    $stmt->execute();

    header("Location: candidates.php");
    exit();
}

$positions = $conn->query("
    SELECT *
    FROM positions
    ORDER BY position_name
");

$candidates_query = $conn->query("
    SELECT
        c.*,
        p.position_name
    FROM candidates c
    LEFT JOIN positions p
    ON c.position_id = p.id
    ORDER BY p.position_name ASC, c.full_name ASC
");

// Restructure candidates into position groups array
$grouped_candidates = [];
while($row = $candidates_query->fetch_assoc()) {
    $pos_name = !empty($row['position_name']) ? $row['position_name'] : 'Unassigned Position';
    $grouped_candidates[$pos_name][] = $row;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Manage Candidates</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" rel="stylesheet">
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
    --table-muted:#64748b;
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
    --table-muted:#94a3b8;
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
    max-width: 1300px;
}

.card-custom{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:20px;
    overflow:hidden;
}

/* Explicit Table Theme Rules targeting text visibility */
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

/* Ensures custom text tags and fallback text styles stay highly clear */
.table td strong, 
.table td span,
.table td em,
.table-muted-text {
    color: var(--table-text) !important;
}

.table .custom-muted-desc {
    color: var(--table-muted) !important;
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

.form-control::file-selector-button {
    background-color: var(--bg);
    color: var(--text);
    border-color: var(--border);
    transition: .3s;
}

.form-control::placeholder {
    color: var(--placeholder-color) !important;
    opacity: 1;
}

/* Modal Dark Theme Overrides */
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

.candidate-photo{
    width:60px;
    height:60px;
    object-fit:cover;
    border-radius:12px;
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

.position-group-header {
    background-color: rgba(59, 130, 246, 0.1);
    color: #3b82f6;
    font-weight: 600;
    padding: 12px 20px;
    border-radius: 12px;
    margin-top: 25px;
    margin-bottom: 15px;
    font-size: clamp(1rem, 2vw, 1.15rem);
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    flex-wrap: wrap;
}

.dark-theme .position-group-header {
    background-color: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
}

/* Custom CSS Breakpoints for Mobile View */
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

/* Crop Modal Styling - Make it larger */
#cropModal .modal-dialog {
    max-width: 95vw;
    margin: auto;
}

#cropModal .modal-body {
    padding: 20px;
    background-color: var(--card);
    min-height: 70vh;
    max-height: 80vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: auto;
}

#cropperImage {
    max-width: 90%;
    max-height: 90%;
    width: auto;
    height: auto;
    object-fit: contain;
}

#cropModal .modal-content {
    background-color: var(--card);
    max-height: 90vh;
}

#cropModal .modal-header,
#cropModal .modal-footer {
    background-color: var(--card);
    flex-shrink: 0;
}

@media (max-width: 768px) {
    #cropModal .modal-dialog {
        max-width: 98vw;
    }
    #cropModal .modal-body {
        min-height: 60vh;
        max-height: 75vh;
        padding: 15px;
    }
    #cropperImage {
        max-width: 95%;
        max-height: 95%;
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
        👥 Manage Candidates
    </h1>
</div>

<?php if($message){ ?>
<div class="alert alert-success" style="border-radius:12px;">
    <?= $message ?>
</div>
<?php } ?>

<div class="card card-custom shadow-sm mb-5">
<div class="card-header bg-primary text-white py-3">
    Add New Candidate
</div>
<div class="card-body p-4">
<form method="POST" enctype="multipart/form-data">
<div class="row">

<div class="col-md-6 mb-3">
<label class="form-label">Full Name</label>
<input
type="text"
name="full_name"
class="form-control"
placeholder="e.g. Jane Doe"
required>
</div>

<div class="col-md-6 mb-3">
<label class="form-label">Position</label>
<select
name="position_id"
class="form-select"
required>
<option value="">Select Position</option>
<?php
$positions->data_seek(0);
while($position = $positions->fetch_assoc()){
?>
<option value="<?= $position['id']; ?>">
    <?= htmlspecialchars($position['position_name']); ?>
</option>
<?php } ?>
</select>
</div>

</div>

<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Accomplishments</label>
        <textarea
            name="description_accomplishments"
            class="form-control"
            placeholder="Enter each accomplishment on a new line"
            rows="5"></textarea>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Platforms</label>
        <textarea
            name="description_platforms"
            class="form-control"
            placeholder="Enter each platform point on a new line"
            rows="5"></textarea>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Highest Educational Attainment</label>
        <input
            type="text"
            name="education"
            class="form-control"
            placeholder="Highest educational attainment"
        >
    </div>
</div>

<div class="mb-3">
            <label class="form-label">Candidate Photo</label>
            <input
            type="file"
            name="photo"
            id="photo_add"
            class="form-control"
            accept="image/*"
            onchange="openCropper(this, 'cropped_photo_add')">
            <input type="hidden" name="cropped_photo" id="cropped_photo_add">
</div>

<button
type="submit"
name="add_candidate"
class="btn btn-primary px-4 py-2"
style="border-radius:10px;">
Add Candidate
</button>
</form>
</div>
</div>

<div class="card card-custom shadow-sm">
<div class="card-header bg-dark text-white py-3">
    Candidate List (Separated by Position)
</div>
<div class="card-body p-4">

<?php if(empty($grouped_candidates)){ ?>
    <div class="alert alert-warning mb-0" style="border-radius:12px;">
        No candidates registered yet.
    </div>
<?php } else { ?>

    <?php foreach($grouped_candidates as $position_title => $candidates_list){ ?>
        
        <div class="position-group-header">
            <span>📁 <?= htmlspecialchars($position_title); ?></span> 
            <span class="badge bg-secondary" style="font-size:0.8rem; border-radius:6px;">
                <?= count($candidates_list); ?> Candidate(s)
            </span>
        </div>

        <div class="table-responsive mb-4">
            <table class="table table-hover table-striped align-middle mb-0">
                <thead>
                    <tr>
                        <th width="8%">ID</th>
                        <th width="12%">Photo</th>
                        <th width="25%">Name</th>
                        <th width="18%">Education</th>
                        <th>Description</th>
                        <th width="160">Action</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach($candidates_list as $row){ ?>
                    <tr>
                        <td><strong class="table-muted-text"><?= $row['id']; ?></strong></td>
                        <td>
                            <?php if(!empty($row['photo']) && file_exists("../assets/images/" . $row['photo'])){ ?>
                                <img src="../assets/images/<?= $row['photo']; ?>" class="candidate-photo">
                            <?php } else { ?>
                                <span class="custom-muted-desc" style="font-size:0.85rem;">No Photo</span>
                            <?php } ?>
                        </td>
                        <td><strong class="table-muted-text"><?= htmlspecialchars($row['full_name']); ?></strong></td>
                        <td><?= htmlspecialchars($row['education'] ?: '—'); ?></td>
                        <td>
                            <?php if(!empty($row['description'])){ ?>
                                <ul class="mb-0 custom-muted-desc" style="padding-left:1rem;">
                                    <?php foreach(explode("\n", trim($row['description'])) as $item){
                                        $item = trim($item);
                                        if($item !== ''){
                                    ?>
                                        <li><?= htmlspecialchars($item); ?></li>
                                    <?php }} ?>
                                </ul>
                            <?php } else { ?>
                                <em class="custom-muted-desc" style="font-size:0.85rem;">No details provided.</em>
                            <?php } ?>
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
                                    class="btn btn-danger btn-sm px-2"
                                    style="border-radius:8px;"
                                    onclick="return confirm('Are you sure you want to delete this candidate?')">
                                    Delete
                                </a>
                            </div>
                        </td>
                    </tr>
                <?php } ?>
                </tbody>
            </table>
        </div>
    <?php } ?>
<?php } ?>
</div>
</div>
</div>

<div class="modal fade" id="editCandidateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="modalTitle">✏️ Edit Candidate Information</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-body">
          <input type="hidden" name="candidate_id" id="edit_id">
          
          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Full Name</label>
              <input type="text" name="full_name" id="edit_name" class="form-control" required>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Position</label>
              <select name="position_id" id="edit_position_id" class="form-select" required>
                <?php
                $positions->data_seek(0);
                while($position = $positions->fetch_assoc()){
                ?>
                <option value="<?= $position['id']; ?>">
                  <?= htmlspecialchars($position['position_name']); ?>
                </option>
                <?php } ?>
              </select>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6 mb-3">
              <label class="form-label">Accomplishments</label>
              <textarea name="description_accomplishments" id="edit_description_accomplishments" class="form-control" rows="5" placeholder="Enter each accomplishment on a new line"></textarea>
            </div>
            <div class="col-md-6 mb-3">
              <label class="form-label">Platforms</label>
              <textarea name="description_platforms" id="edit_description_platforms" class="form-control" rows="5" placeholder="Enter each platform point on a new line"></textarea>
            </div>
          </div>

          <div class="row">
              <div class="col-md-12 mb-3">
                  <label class="form-label">Education</label>
                  <input type="text" name="education" id="edit_education" class="form-control" placeholder="Highest educational attainment">
              </div>
          </div>

          <div class="mb-3">
            <label class="form-label">Update Candidate Photo (Leave blank to retain current)</label>
                        <input type="file" name="photo" id="photo_edit" class="form-control" accept="image/*" onchange="openCropper(this, 'cropped_photo_edit')">
                        <input type="hidden" name="cropped_photo" id="cropped_photo_edit">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" style="border-radius:10px;">Cancel</button>
          <button type="submit" name="edit_candidate" class="btn btn-success" style="border-radius:10px;">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>

<div class="modal fade" id="cropModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Crop Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body text-center">
                <img id="cropperImage" src="">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="cropSaveBtn" class="btn btn-primary">Use Cropped Image</button>
            </div>
        </div>
    </div>
</div>

<script>
let cropper = null;
let currentFileUrl = null;
let currentHiddenFieldId = null;
const cropModal = new bootstrap.Modal(document.getElementById('cropModal'));

function openCropper(inputElem, hiddenFieldId){
        if(!inputElem.files || !inputElem.files.length) return;
        const file = inputElem.files[0];
        currentHiddenFieldId = hiddenFieldId;
        if(currentFileUrl) URL.revokeObjectURL(currentFileUrl);
        currentFileUrl = URL.createObjectURL(file);
        const img = document.getElementById('cropperImage');
        img.src = currentFileUrl;
        img.onload = function(){
                if(cropper) cropper.destroy();
                cropper = new Cropper(img, { aspectRatio: 1, viewMode: 1, autoCropArea: 1 });
                cropModal.show();
        }
}

document.getElementById('cropSaveBtn').addEventListener('click', function(){
        if(!cropper) return;
        
        // REMOVED fixed width/height constraints to retain the original high resolution
        const canvas = cropper.getCroppedCanvas({
            imageSmoothingEnabled: true,
            imageSmoothingQuality: 'high'
        });
        
        const dataUrl = canvas.toDataURL('image/jpeg', 0.95);
        const hidden = document.getElementById(currentHiddenFieldId);
        if(hidden) hidden.value = dataUrl;
        cropModal.hide();
        // clean up
        if(currentFileUrl) { URL.revokeObjectURL(currentFileUrl); currentFileUrl = null; }
        if(cropper){ cropper.destroy(); cropper = null; }
});

// Ensure modal cleanup on close
document.getElementById('cropModal').addEventListener('hidden.bs.modal', function(){
        if(cropper){ cropper.destroy(); cropper = null; }
        const img = document.getElementById('cropperImage');
        img.src = '';
});
</script>
<script>
const bootstrapEditModal = new bootstrap.Modal(document.getElementById('editCandidateModal'));

function openEditModal(candidateData) {
    document.getElementById('edit_id').value = candidateData.id;
    document.getElementById('edit_name').value = candidateData.full_name;
    document.getElementById('edit_position_id').value = candidateData.position_id;

    const raw = candidateData.description || '';
    const lines = raw.split(/\r\n|\n|\r/);
    const accomplishments = [];
    const platforms = [];
    let section = 'accomplishments';

    lines.forEach(function(line){
        const trimmed = line.trim();
        if(trimmed === '') return;
        if(/^accomplishments\s*[:]?$/i.test(trimmed)){
            section = 'accomplishments';
            return;
        }
        if(/^platforms\s*[:]?$/i.test(trimmed)){
            section = 'platforms';
            return;
        }
        const item = trimmed.replace(/^[\-\*•]\s*/u, '');
        if(section === 'platforms'){
            platforms.push(item);
        } else {
            accomplishments.push(item);
        }
    });

    document.getElementById('edit_description_accomplishments').value = accomplishments.join('\n');
    document.getElementById('edit_description_platforms').value = platforms.join('\n');
    document.getElementById('edit_education').value = candidateData.education || '';
    
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