<?php
require_once 'session_start.php';
include '../config/db.php';

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
    $education = trim($_POST['education'] ?? '');
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
    $position_name = $position['position_name'] ?? 'Unassigned';

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
    $position_name = $position['position_name'] ?? 'Unassigned';

    $photo = "";
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
        $stmt->bind_param("sssssii", $full_name, $position_name, $photo, $description, $education, $position_id, $candidate_id);
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
<title>Manage Candidates - PMPC Admin</title>

<!-- Google Fonts: Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

<!-- Bootstrap 5, Bootstrap Icons & CropperJS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" rel="stylesheet">

<style>
:root {
    --sidebar-bg: #0f172a;
    --sidebar-hover: #1e293b;
    --sidebar-text: #94a3b8;
    --sidebar-text-active: #ffffff;
    --sidebar-active-bg: #2563eb;
    --card-bg: #ffffff;
    --text-primary: #0f172a;
    --text-secondary: #64748b;
    --border-color: #e2e8f0;
    --bg-main: #f8fafc;
    --input-bg: #ffffff;
    --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
    --placeholder-color: #64748b;
}

.dark-theme {
    --sidebar-bg: #030712;
    --sidebar-hover: #111827;
    --sidebar-text: #9ca3af;
    --sidebar-text-active: #ffffff;
    --sidebar-active-bg: #2563eb;
    --card-bg: #111827;
    --text-primary: #f9fafb;
    --text-secondary: #9ca3af;
    --border-color: #1f2937;
    --bg-main: #030712;
    --input-bg: #1f2937;
    --card-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
    --placeholder-color: #cbd5e1;
}

body {
    background-color: var(--bg-main);
    color: var(--text-primary);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    transition: background-color 0.2s ease, color 0.2s ease;
}

/* Mobile Header */
.mobile-header {
    display: none;
    background: var(--sidebar-bg);
    color: white;
    padding: 1rem 1.25rem;
    align-items: center;
    justify-content: space-between;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    border-bottom: 1px solid rgba(255, 255, 255, 0.05);
}

/* Sidebar Layout */
.sidebar {
    position: fixed;
    left: 0;
    top: 0;
    width: 260px;
    height: 100vh;
    background: var(--sidebar-bg);
    padding: 1.75rem 1.25rem;
    overflow-y: auto;
    z-index: 1010;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
}

.brand-wrapper {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding-bottom: 1.5rem;
    margin-bottom: 1.5rem;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
}

.logo {
    width: 42px;
    height: 42px;
    border-radius: 10px;
    object-fit: contain;
}

.brand-title {
    color: #ffffff;
    font-size: 1.1rem;
    font-weight: 700;
    line-height: 1.2;
    margin: 0;
}

.brand-subtitle {
    color: var(--sidebar-text);
    font-size: 0.75rem;
    font-weight: 500;
    margin: 0;
}

.nav-section-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: #475569;
    font-weight: 700;
    margin: 1rem 0 0.5rem 0.75rem;
}

.nav-link-custom {
    display: flex;
    align-items: center;
    gap: 0.75rem;
    color: var(--sidebar-text);
    text-decoration: none;
    padding: 0.75rem 1rem;
    border-radius: 10px;
    margin-bottom: 0.25rem;
    font-weight: 500;
    font-size: 0.9rem;
    transition: all 0.15s ease-in-out;
}

.nav-link-custom i {
    font-size: 1.1rem;
}

.nav-link-custom:hover {
    background: var(--sidebar-hover);
    color: var(--sidebar-text-active);
}

.nav-link-custom.active {
    background: var(--sidebar-active-bg);
    color: #ffffff;
}

.nav-link-custom.logout {
    color: #ef4444;
    margin-top: auto;
}

.nav-link-custom.logout:hover {
    background: rgba(239, 68, 68, 0.1);
    color: #f87171;
}

/* Main Workspace */
.main {
    margin-left: 260px;
    padding: 2.5rem;
    transition: margin-left 0.3s ease, padding 0.3s ease;
}

.page-title {
    color: var(--text-primary);
    font-weight: 700;
    font-size: 1.75rem;
    letter-spacing: -0.02em;
    margin: 0;
}

.welcome-subtitle {
    color: var(--text-secondary);
    font-size: 0.925rem;
}

.theme-toggle-btn {
    border: 1px solid var(--border-color);
    border-radius: 50px;
    padding: 0.5rem 1rem;
    background: var(--card-bg);
    color: var(--text-primary);
    font-weight: 600;
    font-size: 0.85rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: var(--card-shadow);
    transition: all 0.2s ease;
}

.theme-toggle-btn:hover {
    background: var(--border-color);
}

/* Base Admin Card */
.admin-card {
    background: var(--card-bg);
    border: 1px solid var(--border-color);
    border-radius: 16px;
    padding: 1.5rem;
    box-shadow: var(--card-shadow);
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}

/* Form Styling */
.form-label {
    color: var(--text-primary);
    font-weight: 600;
    font-size: 0.85rem;
    margin-bottom: 0.4rem;
}

.custom-input, .form-select {
    background-color: var(--input-bg);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 0.6rem 0.85rem;
    font-size: 0.9rem;
}

.custom-input::placeholder,
.form-control::placeholder,
.form-select::placeholder {
    color: var(--placeholder-color);
    opacity: 1;
}

.custom-input:focus, .form-select:focus {
    background-color: var(--input-bg);
    color: var(--text-primary);
    border-color: #2563eb;
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
}

/* Table Styling */
.custom-table {
    color: var(--text-primary);
    margin-bottom: 0;
}

.custom-table th {
    background: transparent;
    color: var(--text-secondary);
    border-bottom: 1px solid var(--border-color);
    font-size: 0.75rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    padding: 1rem;
}

.custom-table td {
    background: transparent;
    color: var(--text-primary);
    border-bottom: 1px solid var(--border-color);
    padding: 1rem;
    vertical-align: middle;
}

.candidate-photo {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border-radius: 10px;
    border: 1px solid var(--border-color);
}

.candidate-photo-placeholder {
    width: 48px;
    height: 48px;
    border-radius: 10px;
    background: rgba(37, 99, 235, 0.1);
    color: #2563eb;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
    font-weight: 700;
}

.position-group-header {
    background-color: rgba(37, 99, 235, 0.08);
    color: #2563eb;
    font-weight: 700;
    padding: 0.85rem 1.25rem;
    border-radius: 12px;
    margin-top: 1.5rem;
    margin-bottom: 1rem;
    font-size: 0.95rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.dark-theme .position-group-header {
    background-color: rgba(37, 99, 235, 0.15);
    color: #60a5fa;
}

/* Modal Dark Theme Styling */
.dark-theme .modal-content {
    background-color: var(--card-bg);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
}

.dark-theme .modal-header, 
.dark-theme .modal-footer {
    border-color: var(--border-color);
}

.dark-theme .btn-close {
    filter: invert(1) grayscale(1) brightness(2);
}

/* Crop Modal */
#cropModal .modal-body {
    background-color: var(--bg-main);
    min-height: 60vh;
    max-height: 75vh;
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

#cropperImage {
    max-width: 100%;
    max-height: 65vh;
    object-fit: contain;
}

.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(15, 23, 42, 0.6);
    backdrop-filter: blur(4px);
    z-index: 1005;
}

@media (max-width: 991.98px) {
    .mobile-header {
        display: flex;
    }
    .sidebar {
        transform: translateX(-100%);
    }
    .sidebar.show {
        transform: translateX(0);
    }
    .sidebar-overlay.show {
        display: block;
    }
    .main {
        margin-left: 0;
        padding: 6rem 1.25rem 2.5rem 1.25rem;
    }
}
</style>
</head>
<body>

<div class="mobile-header">
    <div class="d-flex align-items-center gap-2">
        <img src="../assets/images/logo.png" class="logo" style="width: 32px; height: 32px;" alt="Logo" onerror="this.style.display='none';">
        <h2 class="brand-title">PMPC Admin</h2>
    </div>
    <button class="btn btn-outline-light btn-sm rounded-3 px-3" onclick="toggleMenu()">
        <i class="bi bi-list fs-5"></i>
    </button>
</div>

<div class="sidebar-overlay" id="sidebarOverlay" onclick="toggleMenu()"></div>

<aside class="sidebar" id="sidebarNav">
    <div class="brand-wrapper">
        <img src="../assets/images/logo.png" class="logo" alt="PMPC Logo" onerror="this.style.display='none';">
        <div>
            <h1 class="brand-title">PMPC Admin</h1>
            <p class="brand-subtitle">Election System</p>
        </div>
    </div>

    <div class="nav-section-label">Main Menu</div>
    <a href="dashboard.php" class="nav-link-custom"><i class="bi bi-grid-1x2-fill"></i> Dashboard</a>
    <a href="candidates.php" class="nav-link-custom active"><i class="bi bi-person-badge"></i> Candidates</a>
    <a href="positions.php" class="nav-link-custom"><i class="bi bi-award"></i> Positions</a>
    <a href="voters.php" class="nav-link-custom"><i class="bi bi-people"></i> Voters</a>
    <a href="pre-registered.php" class="nav-link-custom"><i class="bi bi-clipboard-check"></i> Pre-registered</a>
    <a href="elections.php" class="nav-link-custom"><i class="bi bi-building"></i> Branches</a>
    <a href="results.php" class="nav-link-custom"><i class="bi bi-bar-chart-line"></i> Results</a>

    <a href="logout.php" class="nav-link-custom logout"><i class="bi bi-box-arrow-right"></i> Logout</a>
</aside>

<main class="main">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="page-title">Manage Candidates</h2>
            <p class="welcome-subtitle mb-0">Register, edit, and organize candidate profiles for active elections</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="dashboard.php" class="btn btn-outline-secondary rounded-3 btn-sm fw-semibold d-inline-flex align-items-center gap-2">
                <i class="bi bi-arrow-left"></i> Dashboard
            </a>
            <button class="theme-toggle-btn" onclick="toggleTheme()">
                <i class="bi bi-moon-stars-fill" id="themeIcon"></i>
                <span id="themeText">Dark Mode</span>
            </button>
        </div>
    </div>

    <?php if($message){ ?>
        <div class="alert alert-success rounded-3 border-0 shadow-sm d-flex align-items-center gap-2 mb-4">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div><?= htmlspecialchars($message) ?></div>
        </div>
    <?php } ?>

    <!-- ADD CANDIDATE FORM -->
    <div class="admin-card mb-4">
        <h5 class="fw-bold mb-3"><i class="bi bi-person-plus-fill text-primary me-2"></i>Add New Candidate</h5>
        <form method="POST" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Full Name</label>
                    <input type="text" name="full_name" class="form-control custom-input" placeholder="e.g. Jane Doe" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label">Position</label>
                    <select name="position_id" class="form-select custom-input" required>
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

                <div class="col-md-4">
                    <label class="form-label">Accomplishments</label>
                    <textarea name="description_accomplishments" class="form-control custom-input" placeholder="Enter each accomplishment on a new line" rows="4"></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Platforms</label>
                    <textarea name="description_platforms" class="form-control custom-input" placeholder="Enter each platform point on a new line" rows="4"></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label">Highest Educational Attainment</label>
                    <input type="text" name="education" class="form-control custom-input mb-3" placeholder="e.g. Master of Business Administration">
                    
                    <label class="form-label">Candidate Photo</label>
                    <input type="file" name="photo" id="photo_add" class="form-control custom-input" accept="image/*" onchange="openCropper(this, 'cropped_photo_add')">
                    <input type="hidden" name="cropped_photo" id="cropped_photo_add">
                </div>
            </div>

            <div class="mt-4 pt-3 border-top d-flex justify-content-end" style="border-color: var(--border-color) !important;">
                <button type="submit" name="add_candidate" class="btn btn-primary rounded-3 px-4 fw-semibold d-inline-flex align-items-center gap-2">
                    <i class="bi bi-plus-lg"></i> Add Candidate
                </button>
            </div>
        </form>
    </div>

    <!-- CANDIDATE LIST SECTION -->
    <div class="admin-card">
        <h5 class="fw-bold mb-1"><i class="bi bi-person-lines-fill text-primary me-2"></i>Candidate Directory</h5>
        <p class="text-secondary small mb-3">Organized by running positions</p>

        <?php if(empty($grouped_candidates)){ ?>
            <div class="text-center text-secondary py-5">
                <i class="bi bi-inbox fs-2 d-block mb-2 text-opacity-50"></i>
                No candidates registered yet.
            </div>
        <?php } else { ?>

            <?php foreach($grouped_candidates as $position_title => $candidates_list){ ?>
                <div class="position-group-header">
                    <span><i class="bi bi-folder-fill me-2"></i><?= htmlspecialchars($position_title); ?></span> 
                    <span class="badge bg-primary bg-opacity-20 text-primary border border-primary border-opacity-20 rounded-pill px-3 py-1">
                        <?= count($candidates_list); ?> Candidate(s)
                    </span>
                </div>

                <div class="table-responsive mb-2">
                    <table class="table custom-table align-middle">
                        <thead>
                            <tr>
                                <th width="6%">ID</th>
                                <th width="10%">Photo</th>
                                <th width="22%">Name</th>
                                <th width="20%">Education</th>
                                <th>Description</th>
                                <th width="140" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($candidates_list as $row){ ?>
                            <tr>
                                <td class="fw-bold text-secondary">#<?= $row['id']; ?></td>
                                <td>
                                    <?php if(!empty($row['photo']) && file_exists("../assets/images/" . $row['photo'])){ ?>
                                        <img src="../assets/images/<?= $row['photo']; ?>" class="candidate-photo" alt="Photo">
                                    <?php } else { ?>
                                        <div class="candidate-photo-placeholder">
                                            <?= strtoupper(substr($row['full_name'], 0, 1)); ?>
                                        </div>
                                    <?php } ?>
                                </td>
                                <td class="fw-bold"><?= htmlspecialchars($row['full_name']); ?></td>
                                <td class="text-secondary small"><?= htmlspecialchars($row['education'] ?: '—'); ?></td>
                                <td>
                                    <?php if(!empty($row['description'])){ ?>
                                        <ul class="mb-0 text-secondary small ps-3">
                                            <?php foreach(explode("\n", trim($row['description'])) as $item){
                                                $item = trim($item);
                                                if($item !== ''){
                                            ?>
                                                <li><?= htmlspecialchars($item); ?></li>
                                            <?php }} ?>
                                        </ul>
                                    <?php } else { ?>
                                        <span class="text-secondary small opacity-75"><em>No details provided.</em></span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-end gap-2">
                                        <button 
                                            type="button" 
                                            class="btn btn-outline-warning btn-sm rounded-3 px-2 py-1"
                                            onclick="openEditModal(<?= htmlspecialchars(json_encode($row)); ?>)"
                                            title="Edit">
                                            <i class="bi bi-pencil-fill"></i>
                                        </button>
                                        <a
                                            href="?delete=<?= $row['id']; ?>"
                                            class="btn btn-outline-danger btn-sm rounded-3 px-2 py-1"
                                            onclick="return confirm('Are you sure you want to delete this candidate?')"
                                            title="Delete">
                                            <i class="bi bi-trash-fill"></i>
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
</main>

<!-- EDIT CANDIDATE MODAL -->
<div class="modal fade" id="editCandidateModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-4 border-0 shadow">
      <div class="modal-header">
        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Candidate Information</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-body p-4">
          <input type="hidden" name="candidate_id" id="edit_id">
          
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Full Name</label>
              <input type="text" name="full_name" id="edit_name" class="form-control custom-input" required>
            </div>
            <div class="col-md-6">
              <label class="form-label">Position</label>
              <select name="position_id" id="edit_position_id" class="form-select custom-input" required>
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

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label">Accomplishments</label>
              <textarea name="description_accomplishments" id="edit_description_accomplishments" class="form-control custom-input" rows="4" placeholder="Enter each accomplishment on a new line"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label">Platforms</label>
              <textarea name="description_platforms" id="edit_description_platforms" class="form-control custom-input" rows="4" placeholder="Enter each platform point on a new line"></textarea>
            </div>
          </div>

          <div class="row g-3">
              <div class="col-md-12 mb-2">
                  <label class="form-label">Education</label>
                  <input type="text" name="education" id="edit_education" class="form-control custom-input" placeholder="Highest educational attainment">
              </div>
          </div>

          <div>
            <label class="form-label">Update Photo <span class="text-secondary fw-normal">(Leave blank to retain current)</span></label>
            <input type="file" name="photo" id="photo_edit" class="form-control custom-input" accept="image/*" onchange="openCropper(this, 'cropped_photo_edit')">
            <input type="hidden" name="cropped_photo" id="cropped_photo_edit">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary rounded-3 btn-sm fw-semibold" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_candidate" class="btn btn-primary rounded-3 btn-sm fw-semibold">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- CROPPER MODAL -->
<div class="modal fade" id="cropModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content rounded-4 border-0">
            <div class="modal-header">
                <h5 class="modal-title fw-bold"><i class="bi bi-crop text-primary me-2"></i>Crop Photo</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <img id="cropperImage" src="" alt="To Crop">
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary rounded-3 btn-sm fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="cropSaveBtn" class="btn btn-primary rounded-3 btn-sm fw-semibold">Use Cropped Image</button>
            </div>
        </div>
    </div>
</div>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>

<script>
function toggleMenu() {
    const sidebar = document.getElementById('sidebarNav');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar.classList.toggle('show');
    overlay.classList.toggle('show');
}

function updateThemeUI(isDark) {
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');
    
    if (isDark) {
        document.body.classList.add('dark-theme');
        themeIcon.className = 'bi bi-sun-fill';
        themeText.innerText = 'Light Mode';
    } else {
        document.body.classList.remove('dark-theme');
        themeIcon.className = 'bi bi-moon-stars-fill';
        themeText.innerText = 'Dark Mode';
    }
}

function toggleTheme() {
    const isDark = !document.body.classList.contains('dark-theme');
    localStorage.setItem('admin-theme', isDark ? 'dark' : 'light');
    updateThemeUI(isDark);
}

// Cropper Logic
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
    
    const canvas = cropper.getCroppedCanvas({
        imageSmoothingEnabled: true,
        imageSmoothingQuality: 'high'
    });
    
    const dataUrl = canvas.toDataURL('image/jpeg', 0.95);
    const hidden = document.getElementById(currentHiddenFieldId);
    if(hidden) hidden.value = dataUrl;
    cropModal.hide();
    
    if(currentFileUrl) { URL.revokeObjectURL(currentFileUrl); currentFileUrl = null; }
    if(cropper){ cropper.destroy(); cropper = null; }
});

document.getElementById('cropModal').addEventListener('hidden.bs.modal', function(){
    if(cropper){ cropper.destroy(); cropper = null; }
    const img = document.getElementById('cropperImage');
    img.src = '';
});

// Edit Candidate Modal Logic
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

window.onload = function(){
    const savedTheme = localStorage.getItem('admin-theme');
    if (savedTheme === 'dark') {
        updateThemeUI(true);
    }
}
</script>
</body>
</html>