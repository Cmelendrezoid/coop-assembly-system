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
$message_type = "success";

// 1. ADD CANDIDATE LOGIC
if(isset($_POST['add_candidate'])){
    $fullname = trim($_POST['full_name']);
    $position_id = (int)$_POST['position_id'];
    $education = trim($_POST['education'] ?? '');
    $description = buildCandidateDescription(
        $_POST['description_accomplishments'] ?? '',
        $_POST['description_platforms'] ?? ''
    );

    $position_stmt = $conn->prepare("SELECT position_name FROM positions WHERE id=?");
    $position_stmt->bind_param("i", $position_id);
    $position_stmt->execute();
    $position_result = $position_stmt->get_result();
    $position = $position_result->fetch_assoc();
    $position_name = $position['position_name'] ?? 'Unassigned';

    $photo = "";
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
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $photo = time() . "_" . uniqid() . "." . $ext;
            file_put_contents($upload_dir . $photo, $data);
        }
    } elseif(isset($_FILES['photo']) && $_FILES['photo']['error'] == 0){
        $upload_dir = "../assets/images/";
        if(!is_dir($upload_dir)){
            mkdir($upload_dir, 0777, true);
        }
        $photo = time() . "_" . basename($_FILES['photo']['name']);
        move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $photo);
    }

    $stmt = $conn->prepare("
        INSERT INTO candidates (fullname, position_name, photo, description, education, position_id)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("sssssi", $fullname, $position_name, $photo, $description, $education, $position_id);

    if($stmt->execute()){
        $message = "Candidate profile registered successfully.";
        $message_type = "success";
    } else {
        $message = "System error: Failed to register candidate profile.";
        $message_type = "danger";
    }
}

// 2. EDIT CANDIDATE LOGIC
if(isset($_POST['edit_candidate'])){
    $candidate_id = (int)$_POST['candidate_id'];
    $fullname = trim($_POST['full_name']);
    $position_id = (int)$_POST['position_id'];
    $education = trim($_POST['education'] ?? '');
    $description = buildCandidateDescription(
        $_POST['description_accomplishments'] ?? '',
        $_POST['description_platforms'] ?? ''
    );
    
    $position_stmt = $conn->prepare("SELECT position_name FROM positions WHERE id=?");
    $position_stmt->bind_param("i", $position_id);
    $position_stmt->execute();
    $position_result = $position_stmt->get_result();
    $position = $position_result->fetch_assoc();
    $position_name = $position['position_name'] ?? 'Unassigned';

    $photo = "";
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
            if(!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            $photo = time() . "_" . uniqid() . "." . $ext;
            file_put_contents($upload_dir . $photo, $data);
        }
    } elseif(isset($_FILES['photo']) && $_FILES['photo']['error'] == 0){
        $upload_dir = "../assets/images/";
        if(!is_dir($upload_dir)){
            mkdir($upload_dir, 0777, true);
        }
        $photo = time() . "_" . basename($_FILES['photo']['name']);
        move_uploaded_file($_FILES['photo']['tmp_name'], $upload_dir . $photo);
    }

    if(!empty($photo)){
        $stmt = $conn->prepare("
            UPDATE candidates 
            SET fullname=?, position_name=?, photo=?, description=?, education=?, position_id=? 
            WHERE id=?
        ");
        $stmt->bind_param("sssssii", $fullname, $position_name, $photo, $description, $education, $position_id, $candidate_id);
    } else {
        $stmt = $conn->prepare("
            UPDATE candidates 
            SET fullname=?, position_name=?, description=?, education=?, position_id=? 
            WHERE id=?
        ");
        $stmt->bind_param("ssssii", $fullname, $position_name, $description, $education, $position_id, $candidate_id);
    }

    if($stmt->execute()){
        $message = "Candidate profile updated successfully.";
        $message_type = "success";
    } else {
        $message = "System error: Failed to update candidate profile.";
        $message_type = "danger";
    }
}

// 3. DELETE CANDIDATE LOGIC
if(isset($_GET['delete'])){
    $id = (int)$_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM candidates WHERE id=?");
    $stmt->bind_param("i", $id);
    $stmt->execute();

    header("Location: candidates.php");
    exit();
}

$positions = $conn->query("
    SELECT *
    FROM positions
    ORDER BY position_name ASC
");

$candidates_query = $conn->query("
    SELECT
        c.id,
        c.fullname,
        c.photo,
        c.description,
        c.education,
        c.position_id,
        COALESCE(p.position_name, NULLIF(c.position_name, ''), 'Unassigned Position') AS position_name
    FROM candidates c
    LEFT JOIN positions p ON c.position_id = p.id
    ORDER BY position_name ASC, c.fullname ASC
");

$grouped_candidates = [];
$total_candidates = 0;
if($candidates_query){
    while($row = $candidates_query->fetch_assoc()) {
        $pos_name = !empty($row['position_name']) ? $row['position_name'] : 'Unassigned Position';
        $grouped_candidates[$pos_name][] = $row;
        $total_candidates++;
    }
}
$total_positions_assigned = count($grouped_candidates);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Candidate Registry | PMPC Admin</title>

<!-- Google Fonts: Inter -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

<!-- Bootstrap 5, Bootstrap Icons & CropperJS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link href="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.css" rel="stylesheet">

<style>
:root {
    --primary: #3b82f6;
    --primary-hover: #2563eb;
    --primary-glow: rgba(59, 130, 246, 0.35);
    --primary-gradient: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%);
    --bg-main: #090d16;
    --sidebar-bg: #090c15;
    --sidebar-hover: rgba(255, 255, 255, 0.05);
    --sidebar-text: #8e9bb0;
    --sidebar-text-active: #ffffff;
    --sidebar-active-bg: #3b82f6;
    
    --card-bg: rgba(15, 23, 42, 0.7);
    --card-border: rgba(255, 255, 255, 0.08);
    --card-backdrop: blur(16px);
    --card-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.4);
    
    --text-primary: #f8fafc;
    --text-secondary: #94a3b8;
    --text-muted: #64748b;
    --border-color: #1e293b;
    --input-bg: rgba(15, 23, 42, 0.8);
    --placeholder-color: #475569;
}

.light-theme {
    --bg-main: #f8fafc;
    --sidebar-bg: #0b0f19;
    --sidebar-hover: #162032;
    --sidebar-text: #94a3b8;
    --sidebar-text-active: #ffffff;
    --sidebar-active-bg: var(--primary-gradient);
    
    --card-bg: rgba(255, 255, 255, 0.85);
    --card-border: rgba(226, 232, 240, 0.8);
    --card-backdrop: blur(16px);
    --card-shadow: 0 20px 25px -5px rgba(15, 23, 42, 0.03);
    
    --text-primary: #0f172a;
    --text-secondary: #475569;
    --text-muted: #94a3b8;
    --border-color: #e2e8f0;
    --input-bg: rgba(255, 255, 255, 0.9);
    --placeholder-color: #94a3b8;
}

body {
    background-color: var(--bg-main);
    color: var(--text-primary);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
    transition: background-color 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    letter-spacing: -0.011em;
}

/* Glassmorphism Card System */
.glass-card {
    background: var(--card-bg);
    backdrop-filter: var(--card-backdrop);
    -webkit-backdrop-filter: var(--card-backdrop);
    border: 1px solid var(--card-border);
    border-radius: 16px;
    box-shadow: var(--card-shadow);
    transition: transform 0.25s ease, box-shadow 0.25s ease;
}


.main {
    margin-left: 250px;
    padding: 2.5rem 3rem;
    transition: margin-left 0.3s ease, padding 0.3s ease;
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
    font-size: 0.9rem;
}

.theme-toggle-btn {
    border: 1px solid var(--border-color);
    border-radius: 50px;
    padding: 0.5rem 1.1rem;
    background: var(--card-bg);
    color: var(--text-primary);
    font-weight: 600;
    font-size: 0.825rem;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: var(--card-shadow);
    backdrop-filter: blur(10px);
    transition: all 0.2s ease;
}

.theme-toggle-btn:hover {
    background: var(--border-color);
}

/* Bento Box Metrics System */
.bento-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
    gap: 1.25rem;
    margin-bottom: 2rem;
}

.bento-card {
    padding: 1.35rem 1.5rem;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    position: relative;
    overflow: hidden;
}

.bento-icon {
    width: 40px;
    height: 40px;
    border-radius: 10px;
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    margin-bottom: 1rem;
}

.bento-value {
    font-size: 1.85rem;
    font-weight: 800;
    letter-spacing: -0.03em;
    line-height: 1;
    margin-bottom: 0.35rem;
}

.bento-label {
    font-size: 0.75rem;
    font-weight: 700;
    color: var(--text-secondary);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* Inputs & Form Styling */
.form-label-custom {
    color: var(--text-primary);
    font-weight: 700;
    font-size: 0.775rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.5rem;
}

.custom-input, .form-select {
    background-color: var(--input-bg);
    color: var(--text-primary);
    border: 1px solid var(--border-color);
    border-radius: 10px;
    padding: 0.75rem 1rem;
    font-size: 0.9rem;
    font-weight: 500;
    transition: all 0.2s ease;
}

.custom-input::placeholder,
.form-select::placeholder {
    color: var(--placeholder-color);
}

.custom-input:focus, .form-select:focus {
    background-color: var(--input-bg);
    color: var(--text-primary);
    border-color: var(--primary);
    box-shadow: 0 0 0 4px var(--primary-glow);
    outline: none;
}

.btn-enterprise {
    background: var(--primary-gradient);
    color: #ffffff;
    font-weight: 700;
    font-size: 0.875rem;
    border: none;
    border-radius: 10px;
    padding: 0.75rem 1.5rem;
    box-shadow: 0 8px 18px var(--primary-glow);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
}

.btn-enterprise:hover {
    transform: translateY(-1px);
    box-shadow: 0 12px 22px var(--primary-glow);
    color: #ffffff;
}

.position-header-card {
    background: var(--primary-gradient);
    color: #ffffff;
    padding: 0.85rem 1.35rem;
    border-radius: 12px;
    margin-top: 2rem;
    margin-bottom: 1rem;
    display: flex;
    align-items: center;
    justify-content: space-between;
    box-shadow: 0 6px 16px var(--primary-glow);
}

.position-header-title {
    font-weight: 800;
    font-size: 1rem;
    letter-spacing: -0.01em;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

.custom-table {
    color: var(--text-primary);
    margin-bottom: 0;
}

.custom-table th {
    background: transparent;
    color: var(--text-muted);
    border-bottom: 1px solid var(--border-color);
    font-size: 0.725rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
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
    width: 52px;
    height: 52px;
    object-fit: cover;
    border-radius: 12px;
    border: 1px solid var(--border-color);
    box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
}

.candidate-photo-placeholder {
    width: 52px;
    height: 52px;
    border-radius: 12px;
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.25rem;
    font-weight: 800;
    border: 1px dashed rgba(59, 130, 246, 0.3);
}

.badge-chip {
    display: inline-block;
    padding: 0.25rem 0.65rem;
    border-radius: 6px;
    font-size: 0.7rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-bottom: 0.35rem;
}

.badge-accomplishments {
    background: rgba(59, 130, 246, 0.15);
    color: #60a5fa;
    border: 1px solid rgba(59, 130, 246, 0.3);
}

.badge-platforms {
    background: rgba(16, 185, 129, 0.15);
    color: #34d399;
    border: 1px solid rgba(16, 185, 129, 0.3);
}

.structured-list {
    list-style: none;
    padding-left: 0;
    margin-bottom: 0.5rem;
}

.structured-list li {
    position: relative;
    padding-left: 1.15rem;
    margin-bottom: 0.25rem;
    font-size: 0.85rem;
    color: var(--text-secondary);
}

.structured-list li::before {
    content: "•";
    position: absolute;
    left: 0.25rem;
    color: #3b82f6;
    font-weight: bold;
}


</style>
</head>
<body>

<!-- SHARED SIDEBAR INCLUSION -->
<?php include 'sidebar.php'; ?>

<main class="main">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h2 class="page-title">Candidate Directory</h2>
            <p class="welcome-subtitle mb-0">Manage official candidate profiles, declarations, and credential media</p>
        </div>
        <div class="d-flex align-items-center gap-2">
            <button class="theme-toggle-btn" onclick="toggleTheme()">
                <i class="bi bi-sun-fill" id="themeIcon"></i>
                <span id="themeText">Light Mode</span>
            </button>
        </div>
    </div>

    <!-- BENTO STATS METRICS GRID -->
    <div class="bento-grid">
        <div class="glass-card bento-card">
            <div class="bento-icon"><i class="bi bi-people-fill"></i></div>
            <div>
                <div class="bento-value"><?= $total_candidates; ?></div>
                <div class="bento-label">Registered Candidates</div>
            </div>
        </div>
        <div class="glass-card bento-card">
            <div class="bento-icon"><i class="bi bi-award-fill"></i></div>
            <div>
                <div class="bento-value"><?= $total_positions_assigned; ?></div>
                <div class="bento-label">Active Contest Categories</div>
            </div>
        </div>
    </div>

    <?php if($message){ ?>
        <div class="alert alert-<?= $message_type; ?> rounded-4 border-0 shadow-sm d-flex align-items-center gap-2 mb-4 p-3">
            <i class="bi bi-check-circle-fill fs-5"></i>
            <div class="fw-semibold"><?= htmlspecialchars($message); ?></div>
        </div>
    <?php } ?>

    <!-- ADD CANDIDATE FORM CONTAINER -->
    <div class="glass-card p-4 mb-4">
        <h5 class="fw-bold mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-person-plus-fill text-primary"></i> Register New Candidate Profile
        </h5>
        <form method="POST" enctype="multipart/form-data">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label-custom">Full Candidate Name</label>
                    <input type="text" name="full_name" class="form-control custom-input" placeholder="e.g. Honorable Jane Doe" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label-custom">Target Position Category</label>
                    <select name="position_id" class="form-select custom-input" required>
                        <option value="">Select Target Position</option>
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
                    <label class="form-label-custom">Accomplishments & Milestones</label>
                    <textarea name="description_accomplishments" class="form-control custom-input" placeholder="Enter bullet points (one per line)" rows="4"></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label-custom">Policy Platforms & Advocacy</label>
                    <textarea name="description_platforms" class="form-control custom-input" placeholder="Enter bullet points (one per line)" rows="4"></textarea>
                </div>

                <div class="col-md-4">
                    <label class="form-label-custom">Educational Credentials</label>
                    <input type="text" name="education" class="form-control custom-input mb-3" placeholder="e.g. Master of Business Administration">
                    
                    <label class="form-label-custom">Official Portrait Media</label>
                    <input type="file" name="photo" id="photo_add" class="form-control custom-input" accept="image/*" onchange="openCropper(this, 'cropped_photo_add')">
                    <input type="hidden" name="cropped_photo" id="cropped_photo_add">
                </div>
            </div>

            <div class="mt-4 pt-3 border-top d-flex justify-content-end" style="border-color: var(--border-color) !important;">
                <button type="submit" name="add_candidate" class="btn btn-enterprise d-inline-flex align-items-center gap-2">
                    <i class="bi bi-shield-plus"></i> Save Candidate Profile
                </button>
            </div>
        </form>
    </div>

    <!-- CANDIDATE DIRECTORY TABLE CONTAINER -->
    <div class="glass-card p-4">
        <div class="d-flex align-items-center justify-content-between mb-2">
            <div>
                <h5 class="fw-bold mb-0 d-flex align-items-center gap-2">
                    <i class="bi bi-journal-medical text-primary"></i> Master Directory
                </h5>
                <p class="text-secondary small mb-0">System candidate profiles grouped by contest position</p>
            </div>
        </div>

        <?php if(empty($grouped_candidates)){ ?>
            <div class="text-center text-secondary py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                No candidate records registered in system.
            </div>
        <?php } else { ?>

            <?php foreach($grouped_candidates as $position_title => $candidates_list){ ?>
                <div class="position-header-card">
                    <div class="position-header-title">
                        <i class="bi bi-award"></i> <?= htmlspecialchars($position_title); ?>
                    </div> 
                    <span class="badge bg-white text-primary rounded-pill px-3 py-1 fw-bold fs-7">
                        <?= count($candidates_list); ?> Candidate(s)
                    </span>
                </div>

                <div class="table-responsive">
                    <table class="table custom-table align-middle">
                        <thead>
                            <tr>
                                <th width="5%">ID</th>
                                <th width="10%">Media</th>
                                <th width="20%">Candidate</th>
                                <th width="18%">Educational Credentials</th>
                                <th>Statements & Platforms</th>
                                <th width="100" class="text-end">Controls</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach($candidates_list as $row){ 
                            $parsed = parseCandidateDescription($row['description']);
                        ?>
                            <tr>
                                <td class="fw-bold text-muted">#<?= $row['id']; ?></td>
                                <td>
                                    <?php if(!empty($row['photo']) && file_exists("../assets/images/" . $row['photo'])){ ?>
                                        <img src="../assets/images/<?= $row['photo']; ?>" class="candidate-photo" alt="Photo">
                                    <?php } else { ?>
                                        <div class="candidate-photo-placeholder">
                                            <?= strtoupper(substr($row['fullname'], 0, 1)); ?>
                                        </div>
                                    <?php } ?>
                                </td>
                                <td>
                                    <div class="fw-bold fs-6"><?= htmlspecialchars($row['fullname']); ?></div>
                                </td>
                                <td>
                                    <div class="text-secondary small font-monospace"><?= htmlspecialchars($row['education'] ?: '—'); ?></div>
                                </td>
                                <td>
                                    <?php if(!empty($parsed['accomplishments'])){ ?>
                                        <span class="badge-chip badge-accomplishments"><i class="bi bi-trophy"></i> Accomplishments</span>
                                        <ul class="structured-list">
                                            <?php foreach($parsed['accomplishments'] as $acc){ ?>
                                                <li><?= htmlspecialchars($acc); ?></li>
                                            <?php } ?>
                                        </ul>
                                    <?php } ?>

                                    <?php if(!empty($parsed['platforms'])){ ?>
                                        <span class="badge-chip badge-platforms"><i class="bi bi-lightbulb"></i> Platforms</span>
                                        <ul class="structured-list">
                                            <?php foreach($parsed['platforms'] as $plat){ ?>
                                                <li><?= htmlspecialchars($plat); ?></li>
                                            <?php } ?>
                                        </ul>
                                    <?php } ?>

                                    <?php if(empty($parsed['accomplishments']) && empty($parsed['platforms'])){ ?>
                                        <span class="text-muted small"><em>No candidate statement submitted.</em></span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <div class="d-flex justify-content-end gap-1">
                                        <button 
                                            type="button" 
                                            class="btn btn-outline-primary btn-sm rounded-3 px-2 py-1"
                                            onclick='openEditModal(<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8"); ?>)'
                                            title="Edit Profile">
                                            <i class="bi bi-pencil-square"></i>
                                        </button>
                                        <a
                                            href="?delete=<?= $row['id']; ?>"
                                            class="btn btn-outline-danger btn-sm rounded-3 px-2 py-1"
                                            onclick="return confirm('Are you sure you want to delete this candidate profile?')"
                                            title="Delete Profile">
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
    <div class="modal-content rounded-4 border-0 shadow-lg glass-card" style="background: var(--card-bg);">
      <div class="modal-header border-bottom" style="border-color: var(--border-color) !important;">
        <h5 class="modal-title fw-bold"><i class="bi bi-pencil-square text-primary me-2"></i>Edit Candidate Profile</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form method="POST" enctype="multipart/form-data">
        <div class="modal-body p-4">
          <input type="hidden" name="candidate_id" id="edit_id">
          
          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label-custom">Full Name</label>
              <input type="text" name="full_name" id="edit_name" class="form-control custom-input" required>
            </div>
            <div class="col-md-6">
              <label class="form-label-custom">Contest Position</label>
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
              <label class="form-label-custom">Accomplishments</label>
              <textarea name="description_accomplishments" id="edit_description_accomplishments" class="form-control custom-input" rows="4" placeholder="Enter bullet points (one per line)"></textarea>
            </div>
            <div class="col-md-6">
              <label class="form-label-custom">Platforms</label>
              <textarea name="description_platforms" id="edit_description_platforms" class="form-control custom-input" rows="4" placeholder="Enter bullet points (one per line)"></textarea>
            </div>
          </div>

          <div class="row g-3 mb-3">
              <div class="col-md-12">
                  <label class="form-label-custom">Education</label>
                  <input type="text" name="education" id="edit_education" class="form-control custom-input" placeholder="Highest educational attainment">
              </div>
          </div>

          <div>
            <label class="form-label-custom">Update Portrait <span class="text-muted fw-normal">(Optional)</span></label>
            <input type="file" name="photo" id="photo_edit" class="form-control custom-input" accept="image/*" onchange="openCropper(this, 'cropped_photo_edit')">
            <input type="hidden" name="cropped_photo" id="cropped_photo_edit">
          </div>
        </div>
        <div class="modal-footer border-top" style="border-color: var(--border-color) !important;">
          <button type="button" class="btn btn-outline-secondary rounded-3 btn-sm fw-semibold" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" name="edit_candidate" class="btn btn-enterprise btn-sm">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- CROPPER MODAL -->
<div class="modal fade" id="cropModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-4 border-0 shadow-lg glass-card" style="background: var(--card-bg);">
            <div class="modal-header border-bottom" style="border-color: var(--border-color) !important;">
                <h5 class="modal-title fw-bold"><i class="bi bi-crop text-primary me-2"></i>Crop Candidate Portrait</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <img id="cropperImage" src="" alt="To Crop">
            </div>
            <div class="modal-footer border-top" style="border-color: var(--border-color) !important;">
                <button type="button" class="btn btn-outline-secondary rounded-3 btn-sm fw-semibold" data-bs-dismiss="modal">Cancel</button>
                <button type="button" id="cropSaveBtn" class="btn btn-enterprise btn-sm">Apply Cropped Photo</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/cropperjs@1.5.13/dist/cropper.min.js"></script>

<script>
function toggleMenu() {
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (sidebar) sidebar.classList.toggle('show');
    if (overlay) overlay.classList.toggle('show');
}

function updateThemeUI(isLight) {
    const themeIcon = document.getElementById('themeIcon');
    const themeText = document.getElementById('themeText');
    
    if (isLight) {
        document.body.classList.add('light-theme');
        if (themeIcon) themeIcon.className = 'bi bi-moon-stars-fill';
        if (themeText) themeText.innerText = 'Dark Mode';
    } else {
        document.body.classList.remove('light-theme');
        if (themeIcon) themeIcon.className = 'bi bi-sun-fill';
        if (themeText) themeText.innerText = 'Light Mode';
    }
}

function toggleTheme() {
    const isLight = !document.body.classList.contains('light-theme');
    localStorage.setItem('admin-theme', isLight ? 'light' : 'dark');
    updateThemeUI(isLight);
}

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
        width: 450,
        height: 450,
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

const bootstrapEditModal = new bootstrap.Modal(document.getElementById('editCandidateModal'));

function openEditModal(candidateData) {
    document.getElementById('edit_id').value = candidateData.id;
    document.getElementById('edit_name').value = candidateData.fullname;
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
    if (savedTheme === 'light') {
        updateThemeUI(true);
    }
}
</script>
</body>
</html>
