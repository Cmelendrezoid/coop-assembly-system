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

function formatCandidateName($last_name, $first_name, $middle_name, $suffix, $titles){
    $last_name = trim($last_name);
    $first_name = trim($first_name);
    $middle_name = trim($middle_name);
    $middle_initial = '';

    if($middle_name !== ''){
        $middle_initial = preg_match('/^\p{L}\.?$/u', $middle_name)
            ? rtrim($middle_name, '.') . '.'
            : mb_substr($middle_name, 0, 1) . '.';
        $middle_initial = mb_strtoupper($middle_initial);
    }

    $first_and_middle = trim($first_name . ($middle_initial !== '' ? ' ' . $middle_initial : ''));
    $first_middle_suffix = trim($first_and_middle . (trim($suffix) !== '' ? ' ' . trim($suffix) : ''));
    $parts = [];
    foreach([$last_name, $first_middle_suffix, $titles] as $part){
        $part = trim($part);
        if($part !== ''){
            $parts[] = $part;
        }
    }
    return implode(', ', $parts);
}

$candidate_name_columns = [
    'candidate_last_name' => 'VARCHAR(255) NULL',
    'candidate_first_name' => 'VARCHAR(255) NULL',
    'candidate_middle_name' => 'VARCHAR(255) NULL',
    'candidate_suffix' => 'VARCHAR(100) NULL',
    'candidate_titles' => 'VARCHAR(255) NULL'
];
$candidate_columns_result = $conn->query("SHOW COLUMNS FROM candidates");
if(!$candidate_columns_result){
    die("Unable to inspect candidate name fields: " . $conn->error);
}
$existing_candidate_columns = [];
while($candidate_column = $candidate_columns_result->fetch_assoc()){
    $existing_candidate_columns[] = $candidate_column['Field'];
}
foreach($candidate_name_columns as $column_name => $column_definition){
    if(!in_array($column_name, $existing_candidate_columns, true)){
        if(!$conn->query("ALTER TABLE candidates ADD COLUMN `{$column_name}` {$column_definition}")){
            die("Unable to set up candidate name fields: " . $conn->error);
        }
    }
}

$candidate_order_added = !in_array('candidate_order', $existing_candidate_columns, true);
if($candidate_order_added){
    if(!$conn->query("ALTER TABLE candidates ADD COLUMN candidate_order INT NOT NULL DEFAULT 0")){
        die("Unable to set up candidate ordering: " . $conn->error);
    }

    $candidate_order_query = $conn->query("
        SELECT id, position_id
        FROM candidates
        ORDER BY position_id ASC, fullname ASC, id ASC
    ");
    if(!$candidate_order_query){
        die("Unable to initialize candidate ordering: " . $conn->error);
    }

    $candidate_order_update = $conn->prepare("UPDATE candidates SET candidate_order=? WHERE id=?");
    if(!$candidate_order_update){
        die("Unable to initialize candidate ordering: " . $conn->error);
    }
    $position_order = [];
    while($candidate_order_row = $candidate_order_query->fetch_assoc()){
        $candidate_position_id = (int)$candidate_order_row['position_id'];
        $position_order[$candidate_position_id] = ($position_order[$candidate_position_id] ?? 0) + 1;
        $candidate_order_value = $position_order[$candidate_position_id];
        $candidate_order_id = (int)$candidate_order_row['id'];
        $candidate_order_update->bind_param("ii", $candidate_order_value, $candidate_order_id);
        if(!$candidate_order_update->execute()){
            die("Unable to initialize candidate ordering: " . $candidate_order_update->error);
        }
    }
}

$message = "";
$message_type = "success";

if(isset($_POST['save_candidate_order'])){
    $raw_position_id = $_POST['order_position_id'] ?? null;
    $position_id = is_scalar($raw_position_id)
        ? filter_var($raw_position_id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;
    $submitted_orders = $_POST['candidate_order'] ?? null;

    if($position_id === false || $position_id === null || !is_array($submitted_orders)){
        $message = "Unable to save candidate order. Please reload the page and try again.";
        $message_type = "danger";
    } else {
        $existing_ids = [];
        $candidate_ids_stmt = $conn->prepare("SELECT id FROM candidates WHERE position_id=?");
        if(!$candidate_ids_stmt){
            $message = "System error: Unable to load candidates for ordering.";
            $message_type = "danger";
        } else {
            $candidate_ids_stmt->bind_param("i", $position_id);
            $candidate_ids_stmt->execute();
            $candidate_ids_result = $candidate_ids_stmt->get_result();
            while($candidate_id_row = $candidate_ids_result->fetch_assoc()){
                $existing_ids[] = (int)$candidate_id_row['id'];
            }
            sort($existing_ids);

            $submitted_ids = [];
            $orders_to_save = [];
            $used_order_values = [];
            $valid_orders = true;
            foreach($submitted_orders as $candidate_id => $order_value){
                $candidate_id = filter_var($candidate_id, FILTER_VALIDATE_INT);
                $order_value = is_scalar($order_value)
                    ? filter_var($order_value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
                    : false;
                if($candidate_id === false || $candidate_id === null || $order_value === false || $order_value === null || in_array($order_value, $used_order_values, true)){
                    $valid_orders = false;
                    break;
                }
                $submitted_ids[] = $candidate_id;
                $used_order_values[] = $order_value;
                $orders_to_save[] = ['id' => $candidate_id, 'order' => $order_value];
            }
            sort($submitted_ids);

            if(!$valid_orders || $submitted_ids !== $existing_ids){
                $message = "Candidate order must include each candidate once with a unique positive number.";
                $message_type = "danger";
            } else {
                usort($orders_to_save, function($first, $second){
                    return $first['order'] <=> $second['order'];
                });

                if(!$conn->begin_transaction()){
                    $message = "System error: Unable to save candidate order.";
                    $message_type = "danger";
                } else {
                    $order_update_stmt = $conn->prepare("UPDATE candidates SET candidate_order=? WHERE id=? AND position_id=?");
                    if(!$order_update_stmt){
                        $conn->rollback();
                        $message = "System error: Unable to save candidate order.";
                        $message_type = "danger";
                    } else {
                        $save_failed = false;
                        foreach($orders_to_save as $index => $candidate_order_row){
                            $candidate_order_value = $index + 1;
                            $candidate_order_id = $candidate_order_row['id'];
                            $order_update_stmt->bind_param("iii", $candidate_order_value, $candidate_order_id, $position_id);
                            if(!$order_update_stmt->execute()){
                                $save_failed = true;
                                break;
                            }
                        }

                        if($save_failed){
                            $conn->rollback();
                            $message = "System error: Failed to save candidate order.";
                            $message_type = "danger";
                        } elseif(!$conn->commit()){
                            $conn->rollback();
                            $message = "System error: Failed to save candidate order.";
                            $message_type = "danger";
                        } else {
                            $message = "Candidate order saved successfully.";
                            $message_type = "success";
                        }
                    }
                }
            }
        }
    }
}

// 1. ADD CANDIDATE LOGIC
if(isset($_POST['add_candidate'])){
    $last_name = trim($_POST['last_name'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $titles = trim($_POST['titles'] ?? '');
    $fullname = formatCandidateName($last_name, $first_name, $middle_name, $suffix, $titles);
    $position_id = (int)$_POST['position_id'];
    $education = trim($_POST['education'] ?? '');
    $description = buildCandidateDescription(
        $_POST['description_accomplishments'] ?? '',
        $_POST['description_platforms'] ?? ''
    );

    if($last_name === '' || $first_name === ''){
        $message = "Please enter both the candidate's last name and first name.";
        $message_type = "danger";
    } else {
    $position_stmt = $conn->prepare("SELECT position_name FROM positions WHERE id=?");
    $position_stmt->bind_param("i", $position_id);
    $position_stmt->execute();
    $position_result = $position_stmt->get_result();
    $position = $position_result->fetch_assoc();
    $position_name = $position['position_name'] ?? 'Unassigned';
    $order_stmt = $conn->prepare("SELECT COALESCE(MAX(candidate_order), 0) + 1 AS next_order FROM candidates WHERE position_id=?");
    $order_stmt->bind_param("i", $position_id);
    $order_stmt->execute();
    $candidate_order = (int)$order_stmt->get_result()->fetch_assoc()['next_order'];

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
        INSERT INTO candidates (
            fullname, candidate_last_name, candidate_first_name, candidate_middle_name, candidate_suffix, candidate_titles,
            position_name, photo, description, education, position_id, candidate_order
        )
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param("ssssssssssii", $fullname, $last_name, $first_name, $middle_name, $suffix, $titles, $position_name, $photo, $description, $education, $position_id, $candidate_order);

    if($stmt->execute()){
        $message = "Candidate profile registered successfully.";
        $message_type = "success";
    } else {
        $message = "System error: Failed to register candidate profile.";
        $message_type = "danger";
    }
    }
}

// 2. EDIT CANDIDATE LOGIC
if(isset($_POST['edit_candidate'])){
    $candidate_id = (int)$_POST['candidate_id'];
    $last_name = trim($_POST['last_name'] ?? '');
    $first_name = trim($_POST['first_name'] ?? '');
    $middle_name = trim($_POST['middle_name'] ?? '');
    $suffix = trim($_POST['suffix'] ?? '');
    $titles = trim($_POST['titles'] ?? '');
    $has_name_parts = ($last_name !== '' || $first_name !== '' || $middle_name !== '' || $suffix !== '' || $titles !== '');
    $position_id = (int)$_POST['position_id'];
    $education = trim($_POST['education'] ?? '');
    $description = buildCandidateDescription(
        $_POST['description_accomplishments'] ?? '',
        $_POST['description_platforms'] ?? ''
    );

    if($has_name_parts && ($last_name === '' || $first_name === '')){
        $message = "Enter both the candidate's last name and first name, or leave all name fields blank to keep the current name.";
        $message_type = "danger";
    } else {
    $current_name_stmt = $conn->prepare("
        SELECT fullname, candidate_last_name, candidate_first_name, candidate_middle_name, candidate_suffix, candidate_titles, position_id, candidate_order
        FROM candidates
        WHERE id = ?
    ");
    $current_name_stmt->bind_param("i", $candidate_id);
    $current_name_stmt->execute();
    $current_name = $current_name_stmt->get_result()->fetch_assoc();

    if(!$current_name){
        $message = "Candidate record not found.";
        $message_type = "danger";
    } else {
        if($has_name_parts){
            $fullname = formatCandidateName($last_name, $first_name, $middle_name, $suffix, $titles);
        } else {
            $fullname = $current_name['fullname'];
            $last_name = $current_name['candidate_last_name'] ?? '';
            $first_name = $current_name['candidate_first_name'] ?? '';
            $middle_name = $current_name['candidate_middle_name'] ?? '';
            $suffix = $current_name['candidate_suffix'] ?? '';
            $titles = $current_name['candidate_titles'] ?? '';
        }
    
    $position_stmt = $conn->prepare("SELECT position_name FROM positions WHERE id=?");
    $position_stmt->bind_param("i", $position_id);
    $position_stmt->execute();
    $position_result = $position_stmt->get_result();
    $position = $position_result->fetch_assoc();
    $position_name = $position['position_name'] ?? 'Unassigned';
    $candidate_order = (int)$current_name['candidate_order'];
    if((int)$current_name['position_id'] !== $position_id){
        $order_stmt = $conn->prepare("SELECT COALESCE(MAX(candidate_order), 0) + 1 AS next_order FROM candidates WHERE position_id=?");
        $order_stmt->bind_param("i", $position_id);
        $order_stmt->execute();
        $candidate_order = (int)$order_stmt->get_result()->fetch_assoc()['next_order'];
    }

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
            SET fullname=?, candidate_last_name=?, candidate_first_name=?, candidate_middle_name=?, candidate_suffix=?, candidate_titles=?,
                position_name=?, photo=?, description=?, education=?, candidate_order=?, position_id=?
            WHERE id=?
        ");
        $stmt->bind_param("ssssssssssiii", $fullname, $last_name, $first_name, $middle_name, $suffix, $titles, $position_name, $photo, $description, $education, $candidate_order, $position_id, $candidate_id);
    } else {
        $stmt = $conn->prepare("
            UPDATE candidates 
            SET fullname=?, candidate_last_name=?, candidate_first_name=?, candidate_middle_name=?, candidate_suffix=?, candidate_titles=?,
                position_name=?, description=?, education=?, candidate_order=?, position_id=?
            WHERE id=?
        ");
        $stmt->bind_param("sssssssssiii", $fullname, $last_name, $first_name, $middle_name, $suffix, $titles, $position_name, $description, $education, $candidate_order, $position_id, $candidate_id);
    }

    if($stmt->execute()){
        $message = "Candidate profile updated successfully.";
        $message_type = "success";
    } else {
        $message = "System error: Failed to update candidate profile.";
        $message_type = "danger";
    }
    }
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
        c.candidate_last_name,
        c.candidate_first_name,
        c.candidate_middle_name,
        c.candidate_suffix,
        c.candidate_titles,
        c.photo,
        c.description,
        c.education,
        c.position_id,
        c.candidate_order,
        COALESCE(p.position_name, NULLIF(c.position_name, ''), 'Unassigned Position') AS position_name
    FROM candidates c
    LEFT JOIN positions p ON c.position_id = p.id
    ORDER BY position_name ASC, c.candidate_order ASC, c.fullname ASC, c.id ASC
");

$grouped_candidates = [];
$total_candidates = 0;
if($candidates_query){
    while($row = $candidates_query->fetch_assoc()) {
        $pos_name = !empty($row['position_name']) ? $row['position_name'] : 'Unassigned Position';
        $position_key = (int)$row['position_id'];
        if(!isset($grouped_candidates[$position_key])){
            $grouped_candidates[$position_key] = [
                'position_name' => $pos_name,
                'candidates' => []
            ];
        }
        $grouped_candidates[$position_key]['candidates'][] = $row;
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
<title>Candidate Registry | Panabo Co-op Admin</title>

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

.candidate-drag-handle {
    color: var(--text-muted);
    cursor: grab;
    touch-action: none;
}

.candidate-drag-handle:active {
    cursor: grabbing;
}

.candidate-order-row.sortable-ghost {
    opacity: 0.35;
}

.candidate-order-row.sortable-chosen {
    background: var(--input-bg);
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

#cropModal .modal-dialog {
    max-width: min(800px, calc(100vw - 2rem));
}

#cropModal .cropper-modal-body {
    height: min(65vh, 560px);
    min-height: 240px;
    overflow: hidden;
}

#cropperImage {
    display: block;
    max-width: 100%;
    max-height: 100%;
}

@media (max-width: 991.98px) {
    .main {
        width: 100%;
        margin-left: 0;
        padding: 5rem 1.5rem 1.5rem;
    }
}

@media (max-width: 767.98px) {
    .main {
        padding-right: 1rem;
        padding-left: 1rem;
    }

    .position-header-card {
        align-items: flex-start;
        gap: 0.75rem;
        flex-wrap: wrap;
    }

    .position-header-title {
        min-width: 0;
        overflow-wrap: anywhere;
    }

    .custom-table {
        min-width: 900px;
    }

    #editCandidateModal .modal-dialog {
        margin: 0.75rem;
    }
}

@media (max-width: 575.98px) {
    .main {
        padding: 4.75rem 0.75rem 1rem;
    }

    .page-title {
        font-size: 1.5rem;
        overflow-wrap: anywhere;
    }

    .welcome-subtitle {
        font-size: 0.825rem;
    }

    .theme-toggle-btn {
        min-height: 44px;
        padding: 0.5rem 0.85rem;
    }

    .main > .glass-card {
        padding: 1rem !important;
    }

    .btn-enterprise {
        padding: 0.7rem 1rem;
    }

    #editCandidateModal .modal-dialog {
        margin: 0.5rem;
    }

    #editCandidateModal .modal-body {
        padding: 1rem !important;
    }

    #cropModal .cropper-modal-body {
        height: 55vh;
        min-height: 180px;
    }
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
            <i class="bi <?= $message_type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?> fs-5"></i>
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
                    <label class="form-label-custom">Last Name</label>
                    <input type="text" name="last_name" class="form-control custom-input" placeholder="e.g. Doe" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label-custom">First Name</label>
                    <input type="text" name="first_name" class="form-control custom-input" placeholder="e.g. Jane" required>
                </div>

                <div class="col-md-6">
                    <label class="form-label-custom">Middle Name</label>
                    <input type="text" name="middle_name" class="form-control custom-input" placeholder="e.g. Marie">
                </div>

                <div class="col-md-6">
                    <label class="form-label-custom">Suffix</label>
                    <input type="text" name="suffix" class="form-control custom-input" placeholder="e.g. Jr., III">
                </div>

                <div class="col-md-6">
                    <label class="form-label-custom">Titles (after the name)</label>
                    <input type="text" name="titles" class="form-control custom-input" placeholder="e.g. CPA, MBA">
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
                    <label class="form-label-custom">Work Experience</label>
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
                <p class="text-secondary small mb-0">Drag candidates into ballot order for each position, then save to control the order voters see.</p>
            </div>
        </div>

        <?php if(empty($grouped_candidates)){ ?>
            <div class="text-center text-secondary py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2 opacity-50"></i>
                No candidate records registered in system.
            </div>
        <?php } else { ?>

            <?php foreach($grouped_candidates as $position_group){ ?>
                <?php $position_title = $position_group['position_name']; ?>
                <?php $candidates_list = $position_group['candidates']; ?>
                <?php $position_has_ballot = (int)$candidates_list[0]['position_id'] > 0; ?>
                <div class="position-header-card">
                    <div class="position-header-title">
                                                   <i class="bi bi-award"></i> <?= htmlspecialchars($position_title); ?>
                    </div> 
                    <span class="badge bg-white text-primary rounded-pill px-3 py-1 fw-bold fs-7">
                        <?= count($candidates_list); ?> Candidate(s)
                    </span>
                </div>

                <div class="table-responsive">
                    <?php if($position_has_ballot){ ?>
                    <form method="POST">
                    <input type="hidden" name="save_candidate_order" value="1">
                    <input type="hidden" name="order_position_id" value="<?= (int)$candidates_list[0]['position_id']; ?>">
                    <?php } ?>
                    <table class="table custom-table align-middle">
                        <thead>
                            <tr>
                                <?php if($position_has_ballot){ ?>
                                <th width="4%"><span class="visually-hidden">Drag to reorder</span></th>
                                <th width="10%">Ballot Order</th>
                                <?php } ?>
                                <th width="5%">ID</th>
                                <th width="10%">Media</th>
                                <th width="20%">Candidate</th>
                                <th width="18%">Educational Credentials</th>
                                <th>Work Experiences & Platforms</th>
                                <th width="100" class="text-end">Controls</th>
                            </tr>
                        </thead>
                        <tbody class="<?= $position_has_ballot ? 'candidate-order-list' : ''; ?>">
                        <?php foreach($candidates_list as $row){ 
                            $parsed = parseCandidateDescription($row['description']);
                        ?>
                            <tr class="<?= $position_has_ballot ? 'candidate-order-row' : ''; ?>">
                                <?php if($position_has_ballot){ ?>
                                <td class="text-center">
                                    <button
                                        type="button"
                                        class="btn btn-link p-1 candidate-drag-handle"
                                        aria-label="Drag <?= htmlspecialchars($row['fullname']); ?> to reorder"
                                        title="Drag to reorder">
                                        <i class="bi bi-grip-vertical fs-5"></i>
                                    </button>
                                </td>
                                <td>
                                    <input
                                        type="number"
                                        name="candidate_order[<?= (int)$row['id']; ?>]"
                                        class="form-control custom-input"
                                        value="<?= (int)$row['candidate_order']; ?>"
                                        readonly
                                        tabindex="-1"
                                        aria-label="Ballot order for <?= htmlspecialchars($row['fullname']); ?>">
                                </td>
                                <?php } ?>
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
                                        <span class="badge-chip badge-accomplishments"><i class="bi-solid bi-briefcase"></i> Work Experience</span>
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
                    <?php if($position_has_ballot){ ?>
                    <div class="d-flex justify-content-end pb-3">
                        <button type="submit" class="btn btn-enterprise btn-sm d-inline-flex align-items-center gap-2">
                            <i class="bi bi-sort-numeric-down"></i> Save Order
                        </button>
                    </div>
                    </form>
                    <?php } ?>
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
              <label class="form-label-custom">Last Name</label>
              <input type="text" name="last_name" id="edit_last_name" class="form-control custom-input">
            </div>
            <div class="col-md-6">
              <label class="form-label-custom">First Name</label>
              <input type="text" name="first_name" id="edit_first_name" class="form-control custom-input">
            </div>
            <div class="col-md-6">
              <label class="form-label-custom">Middle Name</label>
              <input type="text" name="middle_name" id="edit_middle_name" class="form-control custom-input">
            </div>
            <div class="col-md-6">
              <label class="form-label-custom">Suffix</label>
              <input type="text" name="suffix" id="edit_suffix" class="form-control custom-input" placeholder="e.g. Jr., III">
            </div>
            <div class="col-md-6">
              <label class="form-label-custom">Titles (after the name)</label>
              <input type="text" name="titles" id="edit_titles" class="form-control custom-input">
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
            <div class="col-12">
              <small class="text-secondary">Displayed as Last Name, First Name, Middle Name, Suffix, Titles. Leave all name fields blank to keep the current name. Current name: <span id="edit_current_name"></span></small>
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
            <div class="modal-body cropper-modal-body p-0">
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
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.6/Sortable.min.js"></script>

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

document.querySelectorAll('.candidate-order-list').forEach(function(list){
    Sortable.create(list, {
        animation: 150,
        handle: '.candidate-drag-handle',
        ghostClass: 'sortable-ghost',
        chosenClass: 'sortable-chosen',
        onEnd: function(){
            list.querySelectorAll('input[name^="candidate_order["]').forEach(function(input, index){
                input.value = index + 1;
            });
        }
    });
});

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
    img.onload = function(){
        cropModal.show();
    }
    img.src = currentFileUrl;
}

document.getElementById('cropModal').addEventListener('shown.bs.modal', function(){
    const img = document.getElementById('cropperImage');
    if(!img.naturalWidth) return;
    if(cropper) cropper.destroy();
    cropper = new Cropper(img, { aspectRatio: 1, viewMode: 1, autoCropArea: 1, responsive: true });
});

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
    if(currentFileUrl){ URL.revokeObjectURL(currentFileUrl); currentFileUrl = null; }
});

const bootstrapEditModal = new bootstrap.Modal(document.getElementById('editCandidateModal'));

function openEditModal(candidateData) {
    document.getElementById('edit_id').value = candidateData.id;
    document.getElementById('edit_last_name').value = candidateData.candidate_last_name || '';
    document.getElementById('edit_first_name').value = candidateData.candidate_first_name || '';
    document.getElementById('edit_middle_name').value = candidateData.candidate_middle_name || '';
    document.getElementById('edit_suffix').value = candidateData.candidate_suffix || '';
    document.getElementById('edit_titles').value = candidateData.candidate_titles || '';
    document.getElementById('edit_current_name').textContent = candidateData.fullname || '';
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
