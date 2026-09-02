<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// Support multiple relative paths for db configuration to prevent missing file crashes
if (file_exists('../config/db.php')) {
    require_once '../config/db.php';
} elseif (file_exists('../config/conn.php')) {
    require_once '../config/conn.php';
}

// Fallback to active global connection object
$conn = isset($conn) && $conn ? $conn : (isset($pdo) && $pdo ? $pdo : null);

// Check all voter session parameters set during login
if (!isset($_SESSION['member_id']) && !isset($_SESSION['voter_id']) && !isset($_SESSION['voters_id'])) {
    header("Location: login.php");
    exit();
}

$member_id = (int)($_SESSION['member_id'] ?? $_SESSION['voter_id'] ?? $_SESSION['voters_id']);

// Detect database connection mode (mysqli or PDO)
$is_pdo = ($conn instanceof PDO);

// Dynamically detect voter column name in votes table
$voterCol = 'voter_id';
if ($is_pdo) {
    try {
        $stmt = $conn->query("SHOW COLUMNS FROM votes LIKE 'voter_id'");
        $colCheckVotes = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$colCheckVotes) {
            $stmt2 = $conn->query("SHOW COLUMNS FROM votes LIKE 'member_id'");
            if ($stmt2->fetch(PDO::FETCH_ASSOC)) {
                $voterCol = 'member_id';
            }
        }
    } catch (Exception $e) {}
} else {
    $colCheckVotes = $conn->query("SHOW COLUMNS FROM votes LIKE 'voter_id'");
    if (!$colCheckVotes || $colCheckVotes->num_rows === 0) {
        $colCheckMemberId = $conn->query("SHOW COLUMNS FROM votes LIKE 'member_id'");
        if ($colCheckMemberId && $colCheckMemberId->num_rows > 0) {
            $voterCol = 'member_id';
        }
    }
}

// Ensure member_name and branch columns exist in votes table
if ($is_pdo) {
    try {
        $check1 = $conn->query("SHOW COLUMNS FROM votes LIKE 'member_name'")->fetch();
        if (!$check1) {
            $conn->exec("ALTER TABLE votes ADD COLUMN member_name VARCHAR(255) NULL AFTER {$voterCol}");
        }
        $check2 = $conn->query("SHOW COLUMNS FROM votes LIKE 'branch'")->fetch();
        if (!$check2) {
            $conn->exec("ALTER TABLE votes ADD COLUMN branch VARCHAR(100) NULL AFTER member_name");
        }
    } catch (Exception $e) {}
} else {
    $colCheckName = $conn->query("SHOW COLUMNS FROM votes LIKE 'member_name'");
    if (!$colCheckName || $colCheckName->num_rows === 0) {
        $conn->query("ALTER TABLE votes ADD COLUMN member_name VARCHAR(255) NULL AFTER {$voterCol}");
    }
    $colCheckBranch = $conn->query("SHOW COLUMNS FROM votes LIKE 'branch'");
    if (!$colCheckBranch || $colCheckBranch->num_rows === 0) {
        $conn->query("ALTER TABLE votes ADD COLUMN branch VARCHAR(100) NULL AFTER member_name");
    }
}

// Inspect actual columns in members table to prevent SQL errors
$memberColumns = [];
if ($is_pdo) {
    try {
        $columnsResult = $conn->query("SHOW COLUMNS FROM members");
        while ($col = $columnsResult->fetch(PDO::FETCH_ASSOC)) {
            $memberColumns[] = strtolower($col['Field']);
        }
    } catch (Exception $e) {}
} else {
    $columnsResult = $conn->query("SHOW COLUMNS FROM members");
    if ($columnsResult) {
        while ($col = $columnsResult->fetch_assoc()) {
            $memberColumns[] = strtolower($col['Field']);
        }
    }
}

// Determine existing name column in members table
$nameCol = null;
if (in_array('fullname', $memberColumns)) {
    $nameCol = 'fullname';
} elseif (in_array('full_name', $memberColumns)) {
    $nameCol = 'full_name';
} elseif (in_array('name', $memberColumns)) {
    $nameCol = 'name';
} elseif (in_array('member_name', $memberColumns)) {
    $nameCol = 'member_name';
}

$hasBranchCol = in_array('branch', $memberColumns);

// Fetch member details safely with session fallbacks
$member_name = $_SESSION['full_name'] ?? $_SESSION['voter_name'] ?? $_SESSION['fullname'] ?? $_SESSION['name'] ?? '';
$member_branch = $_SESSION['branch_name'] ?? $_SESSION['branch'] ?? '';

if ($nameCol || $hasBranchCol) {
    $selectFields = [];
    if ($nameCol) $selectFields[] = "{$nameCol} AS member_display_name";
    if ($hasBranchCol) $selectFields[] = "branch";

    $selectClause = implode(', ', $selectFields);
    
    if ($is_pdo) {
        try {
            $memStmt = $conn->prepare("SELECT {$selectClause} FROM members WHERE id = :id OR member_id = :id LIMIT 1");
            $memStmt->execute(['id' => $member_id]);
            if ($memRow = $memStmt->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($memRow['member_display_name'])) {
                    $member_name = $memRow['member_display_name'];
                }
                if (!empty($memRow['branch'])) {
                    $member_branch = $memRow['branch'];
                }
            }
        } catch (Exception $e) {}
    } else {
        $memStmt = $conn->prepare("SELECT {$selectClause} FROM members WHERE id = ? OR member_id = ? LIMIT 1");
        if ($memStmt) {
            $memStmt->bind_param("ii", $member_id, $member_id);
            $memStmt->execute();
            $memRes = $memStmt->get_result();
            if ($memRow = $memRes->fetch_assoc()) {
                if (!empty($memRow['member_display_name'])) {
                    $member_name = $memRow['member_display_name'];
                }
                if (!empty($memRow['branch'])) {
                    $member_branch = $memRow['branch'];
                }
            }
            $memStmt->close();
        }
    }
}

$hasVotedColumnExists = false;
$memberHasVoted = false;

if ($is_pdo) {
    try {
        $columnCheck = $conn->query("SHOW COLUMNS FROM members LIKE 'has_voted'")->fetch();
        if (!$columnCheck) {
            $conn->exec("ALTER TABLE members ADD COLUMN has_voted tinyint(1) NOT NULL DEFAULT 0");
        }
        $statusStmt = $conn->prepare("SELECT has_voted FROM members WHERE id = :id OR member_id = :id LIMIT 1");
        $statusStmt->execute(['id' => $member_id]);
        $statusRow = $statusStmt->fetch(PDO::FETCH_ASSOC);
        $memberHasVoted = !empty($statusRow['has_voted']);
    } catch (Exception $e) {}
} else {
    $columnCheck = $conn->query("SHOW COLUMNS FROM members LIKE 'has_voted'");
    if ($columnCheck && $columnCheck->num_rows === 0) {
        $conn->query("ALTER TABLE members ADD COLUMN has_voted tinyint(1) NOT NULL DEFAULT 0");
    }
    $statusStmt = $conn->prepare("SELECT has_voted FROM members WHERE id = ? OR member_id = ?");
    if ($statusStmt) {
        $statusStmt->bind_param("ii", $member_id, $member_id);
        $statusStmt->execute();
        $statusResult = $statusStmt->get_result();
        if ($statusRow = $statusResult->fetch_assoc()) {
            $memberHasVoted = !empty($statusRow['has_voted']);
        }
        $statusStmt->close();
    }
}

if ($memberHasVoted) {
    header("Location: thankyou.php");
    exit();
}

if ($is_pdo) {
    try {
        $check = $conn->prepare("SELECT COUNT(*) AS total FROM votes WHERE {$voterCol} = :id");
        $check->execute(['id' => $member_id]);
        $row = $check->fetch(PDO::FETCH_ASSOC);
        if ($row && $row['total'] > 0) {
            header("Location: thankyou.php");
            exit();
        }
    } catch (Exception $e) {}
} else {
    $check = $conn->prepare("SELECT COUNT(*) AS total FROM votes WHERE {$voterCol} = ?");
    if ($check) {
        $check->bind_param("i", $member_id);
        $check->execute();
        $result = $check->get_result();
        $row = $result->fetch_assoc();
        $check->close();
        if ($row['total'] > 0) {
            header("Location: thankyou.php");
            exit();
        }
    }
}

// Explicit candidate name column based on database structure
$candidateNameCol = 'fullname';
if ($is_pdo) {
    try {
        $candCheck = $conn->query("SHOW COLUMNS FROM candidates LIKE 'fullname'")->fetch();
        if (!$candCheck) {
            $candCheck2 = $conn->query("SHOW COLUMNS FROM candidates LIKE 'full_name'")->fetch();
            $candidateNameCol = $candCheck2 ? 'full_name' : 'name';
        }
    } catch (Exception $e) {}
} else {
    $candColCheck = $conn->query("SHOW COLUMNS FROM candidates LIKE 'fullname'");
    if (!$candColCheck || $candColCheck->num_rows === 0) {
        $candColCheck2 = $conn->query("SHOW COLUMNS FROM candidates LIKE 'full_name'");
        if ($candColCheck2 && $candColCheck2->num_rows > 0) {
            $candidateNameCol = 'full_name';
        } else {
            $candidateNameCol = 'name';
        }
    }
}

$error = "";
$action = $_POST['action'] ?? '';

$positionsArray = [];
if ($is_pdo) {
    try {
        $positions = $conn->query("SELECT * FROM positions ORDER BY id");
        $positionsArray = $positions->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
} else {
    $positions = $conn->query("SELECT * FROM positions ORDER BY id");
    if ($positions) {
        while ($position = $positions->fetch_assoc()) {
            $positionsArray[] = $position;
        }
    }
}
$totalPositions = count($positionsArray);

$candidateMetadata = [];
foreach ($positionsArray as $position) {
    $position_id = $position['id'];
    $candidateMetadata[$position_id] = [
        'position_name' => $position['position_name'],
        'vote_limit' => $position['vote_limit'],
        'candidates' => []
    ];

    if ($is_pdo) {
        try {
            $stmt = $conn->prepare("SELECT id, {$candidateNameCol} AS candidate_display_name, photo FROM candidates WHERE position_id = :pid ORDER BY candidate_display_name");
            $stmt->execute(['pid' => $position_id]);
            $candidateResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($candidateResults as $candidate) {
                $imageFilename = $candidate['photo'] ?? '';
                $cleanPath = !empty($imageFilename) ? '../assets/images/' . basename($imageFilename) : '';
                $initials = '';
                $displayName = $candidate['candidate_display_name'] ?? '';
                if (!empty($displayName)) {
                    $parts = explode(' ', $displayName);
                    $initials = strtoupper(substr($parts[0], 0, 1));
                    if (count($parts) > 1) {
                        $initials .= strtoupper(substr($parts[count($parts) - 1], 0, 1));
                    }
                }
                $candidateMetadata[$position_id]['candidates'][$candidate['id']] = [
                    'full_name' => $displayName,
                    'imgsrc' => $cleanPath,
                    'initials' => $initials
                ];
            }
        } catch (Exception $e) {}
    } else {
        $candidateResults = $conn->query(
            "SELECT id, {$candidateNameCol} AS candidate_display_name, photo FROM candidates WHERE position_id = $position_id ORDER BY candidate_display_name"
        );
        if ($candidateResults) {
            while ($candidate = $candidateResults->fetch_assoc()) {
                $imageFilename = $candidate['photo'] ?? '';
                $cleanPath = !empty($imageFilename) ? '../assets/images/' . basename($imageFilename) : '';
                $initials = '';
                $displayName = $candidate['candidate_display_name'] ?? '';
                if (!empty($displayName)) {
                    $parts = explode(' ', $displayName);
                    $initials = strtoupper(substr($parts[0], 0, 1));
                    if (count($parts) > 1) {
                        $initials .= strtoupper(substr($parts[count($parts) - 1], 0, 1));
                    }
                }
                $candidateMetadata[$position_id]['candidates'][$candidate['id']] = [
                    'full_name' => $displayName,
                    'imgsrc' => $cleanPath,
                    'initials' => $initials
                ];
            }
        }
    }
}

$currentPage = 0;
if (isset($_POST['current_page'])) {
    $currentPage = max(0, min($totalPositions - 1, intval($_POST['current_page'])));
} elseif (isset($_GET['page'])) {
    $currentPage = max(0, min($totalPositions - 1, intval($_GET['page'])));
} else {
    foreach ($positionsArray as $idx => $position) {
        if (strtolower(trim($position['position_name'])) === 'board of directors') {
            $currentPage = $idx;
            break;
        }
    }
}

$voteData = $_POST['vote'] ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit') {
    $totalVotesSelected = 0;
    foreach ($positionsArray as $position) {
        $position_id = $position['id'];
        $vote_limit = $position['vote_limit'];
        $selected = isset($voteData[$position_id]) && is_array($voteData[$position_id]) ? count($voteData[$position_id]) : 0;
        $totalVotesSelected += $selected;

        if ($selected > $vote_limit) {
            $error = "Maximum allowed for " . $position['position_name'] . " is " . $vote_limit . " candidate(s).";
            break;
        }
    }

    if (empty($error)) {
        if ($is_pdo) {
            try {
                $conn->beginTransaction();

                if (!empty($voteData)) {
                    $stmt = $conn->prepare("INSERT INTO votes ({$voterCol}, member_name, branch, position_id, candidate_id) VALUES (:voter_id, :mname, :branch, :pid, :cid)");
                    foreach ($voteData as $position_id => $candidate_ids) {
                        if (is_array($candidate_ids)) {
                            foreach ($candidate_ids as $candidate_id) {
                                $stmt->execute([
                                    'voter_id' => $member_id,
                                    'mname'    => $member_name,
                                    'branch'   => $member_branch,
                                    'pid'      => $position_id,
                                    'cid'      => $candidate_id
                                ]);
                            }
                        }
                    }
                }

                $updateStmt = $conn->prepare("UPDATE members SET has_voted = 1 WHERE id = :id OR member_id = :id");
                $updateStmt->execute(['id' => $member_id]);

                $conn->commit();
                header("Location: thankyou.php");
                exit();

            } catch (Exception $e) {
                if ($conn->inTransaction()) {
                    $conn->rollBack();
                }
                $error = "System Error: " . $e->getMessage();
            }
        } else {
            $conn->begin_transaction();
            $stmt = null;
            $updateStmt = null;

            try {
                if (!empty($voteData)) {
                    $stmt = $conn->prepare("INSERT INTO votes ({$voterCol}, member_name, branch, position_id, candidate_id) VALUES (?, ?, ?, ?, ?)");
                    if ($stmt === false) {
                        throw new Exception("Failed to prepare ballot processing statement.");
                    }

                    foreach ($voteData as $position_id => $candidate_ids) {
                        if (is_array($candidate_ids)) {
                            foreach ($candidate_ids as $candidate_id) {
                                $stmt->bind_param("issii", $member_id, $member_name, $member_branch, $position_id, $candidate_id);
                                $stmt->execute();
                            }
                        }
                    }
                }

                $updateStmt = $conn->prepare("UPDATE members SET has_voted = 1 WHERE id = ? OR member_id = ?");
                if ($updateStmt === false) {
                    throw new Exception("Failed to update voter status.");
                }
                $updateStmt->bind_param("ii", $member_id, $member_id);
                $updateStmt->execute();

                $conn->commit();
                header("Location: thankyou.php");
                exit();

            } catch (Exception $e) {
                $conn->rollback();
                $error = "System Error: " . $e->getMessage();
            } finally {
                if ($stmt) $stmt->close();
                if ($updateStmt) $updateStmt->close();
            }
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action !== 'submit') {
    if ($action === 'next' && $currentPage < $totalPositions - 1) {
        $currentPage++;
    } elseif ($action === 'previous' && $currentPage > 0) {
        $currentPage--;
    }
}

$currentPosition = $positionsArray[$currentPage] ?? null;
$currentPositionVotes = [];
if ($currentPosition && isset($voteData[$currentPosition['id']]) && is_array($voteData[$currentPosition['id']])) {
    $currentPositionVotes = $voteData[$currentPosition['id']];
}

function renderHiddenVoteInputs($voteData, $currentPositionId) {
    $html = '';
    foreach ($voteData as $positionId => $candidateIds) {
        if ((int)$positionId === (int)$currentPositionId) {
            continue;
        }
        if (is_array($candidateIds)) {
            foreach ($candidateIds as $candidateId) {
                $html .= '<input type="hidden" name="vote[' . htmlspecialchars($positionId) . '][]" value="' . htmlspecialchars($candidateId) . '">';
            }
        }
    }
    return $html;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Vote</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
:root {
    --bg-gradient: radial-gradient(circle at top, #ffffff 0%, #f1f3f5 100%);
    --card-bg: #ffffff;
    --card-border: rgba(0, 0, 0, 0.05);
    --text-main-gradient: linear-gradient(135deg, #1e293b 0%, #475569 100%);
    --body-text: #1e293b;
    --text-muted: #64748b;
    --ballot-section-bg: #f8fafc;
    --ballot-section-border: #e2e8f0;
    --ballot-header-text: #1e293b;
    --candidate-label: #334155;
    --input-border: #cbd5e1;
    --toggle-btn-bg: #f1f5f9;
    --toggle-btn-color: #334155;
    --accent-color: #10b981;
    --accent-color-hover: #059669;
    --alert-danger-bg: #fee2e2;
    --alert-danger-border: #fca5a5;
    --alert-danger-text: #991b1b;
    --btn-success-text: #ffffff;
    --candidate-hover: #f1f5f9;
    --avatar-fallback: #e2e8f0;
    --recap-list-bg: #f8fafc;
}

.dark-theme {
    --bg-gradient: radial-gradient(circle at top, #1e293b 0%, #0f172a 100%);
    --card-bg: #1e293b;
    --card-border: rgba(255, 255, 255, 0.05);
    --text-main-gradient: linear-gradient(135deg, #ffffff 0%, #cbd5e1 100%);
    --body-text: #f8fafc;
    --text-muted: #94a3b8;
    --ballot-section-bg: #151f32;
    --ballot-section-border: #334155;
    --ballot-header-text: #f8fafc;
    --candidate-label: #f1f5f9;
    --input-border: #475569;
    --toggle-btn-bg: #334155;
    --toggle-btn-color: #f8fafc;
    --accent-color: #10b981;
    --accent-color-hover: #059669;
    --alert-danger-bg: #4b1220;
    --alert-danger-border: #7f1d1d;
    --alert-danger-text: #f8d7da;
    --btn-success-text: #ffffff;
    --candidate-hover: #1c283e;
    --avatar-fallback: #334155;
    --recap-list-bg: #151f32;
}

body {
    background: var(--bg-gradient);
    color: var(--body-text);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    min-height: 100vh;
    transition: background 0.3s ease, color 0.3s ease;
}

.container {
    padding-top: 90px;
    padding-bottom: 60px;
    max-width: 900px;
}

.theme-toggle-btn {
    position: fixed;
    top: 20px;
    right: 20px;
    background-color: var(--toggle-btn-bg);
    color: var(--toggle-btn-color);
    border: none;
    padding: 10px 18px;
    border-radius: 30px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.08);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 6px;
    z-index: 1000;
}

.theme-toggle-btn:hover {
    transform: translateY(-1px);
}

.main-portal-card {
    border: 1px solid var(--card-border);
    border-radius: 16px;
    background-color: var(--card-bg);
    transition: background-color 0.3s ease, border-color 0.3s ease;
}

.main-card-body {
    color: var(--body-text);
}

.position-header {
    margin-bottom: 30px;
    text-align: center;
}

.position-header h3 {
    color: var(--body-text);
    font-weight: 700;
    margin-bottom: 0.5rem;
}

.position-header .position-info,
.position-header .position-limit {
    color: var(--text-muted);
}

h2 {
    font-weight: 700;
    letter-spacing: -0.5px;
    background: var(--text-main-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    font-size: clamp(1.5rem, 4vw, 2.2rem);
}

.user-welcome-text {
    color: var(--text-muted);
    font-size: clamp(0.9rem, 2vw, 1.05rem);
    transition: color 0.3s ease;
}

.candidate-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 20px;
    padding: 10px 0;
    max-width: 500px;
    margin: 0 auto;
}

.candidate-card {
    display: flex;
    flex-direction: column;
    align-items: stretch;
    border-radius: 20px;
    border: 2px solid var(--ballot-section-border);
    transition: all 0.3s ease;
    cursor: pointer;
    background-color: var(--card-bg);
    overflow: hidden;
    box-shadow: 0 5px 20px rgba(0,0,0,.04);
}

.candidate-card:hover:not(.disabled-option) {
    border-color: var(--accent-color, #10b981);
    transform: translateY(-4px);
}

.candidate-card.selected {
    border-color: var(--accent-color, #10b981);
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

.candidate-card.disabled-option {
    opacity: 0.6;
    cursor: not-allowed;
}

.candidate-img-wrapper {
    width: 100%;
    height: auto;
    aspect-ratio: 1 / 1;
    overflow: hidden;
    background-color: var(--avatar-fallback);
    display: flex;
    align-items: center;
    justify-content: center;
    border-bottom: 1px solid var(--ballot-section-border);
    flex-shrink: 0;
}

.candidate-avatar {
    width: 100%;
    height: 100%;
    object-fit: cover;
    image-rendering: -webkit-optimize-contrast;
    display: block;
}

.candidate-avatar-placeholder {
    font-weight: 700;
    font-size: 80px;
    color: var(--text-muted);
}

.candidate-details-text {
    padding: 16px 20px 10px 20px;
}

.candidate-name {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--body-text);
    text-align: center;
    margin-bottom: 0;
    line-height: 1.3;
}

.candidate-checkbox {
    padding: 0 20px 20px 20px;
    display: flex;
    justify-content: center;
}

.form-check {
    padding-left: 0;
    margin-bottom: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.vote-label {
    margin-left: 8px;
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--body-text);
    user-select: none;
    cursor: inherit;
}

.form-check-input {
    cursor: pointer;
    border-color: var(--input-border) !important;
    background-color: var(--card-bg);
    width: 1.8em;
    height: 1.8em;
    margin-top: 0;
    flex-shrink: 0;
    transition: all 0.2s ease;
}

.form-check-input:checked {
    background-color: var(--accent-color, #10b981) !important;
    border-color: var(--accent-color, #10b981) !important;
}

.alert-danger {
    background-color: var(--alert-danger-bg, #fee2e2);
    border: 1px solid var(--alert-danger-border, #fca5a5);
    color: var(--alert-danger-text, #991b1b);
    font-size: 0.95rem;
    font-weight: 500;
}

.position-nav-buttons {
    display: flex;
    justify-content: space-between;
    margin-top: 30px;
    gap: 15px;
}

.btn-primary {
    padding: 14px 36px;
    font-size: 1.05rem;
    font-weight: 600;
    border-radius: 10px;
}

.btn-success {
    background-color: var(--accent-color, #10b981) !important;
    color: var(--btn-success-text, #ffffff) !important;
    padding: 14px 36px;
    font-size: 1.05rem;
    font-weight: 600;
    border-radius: 10px;
    transition: all 0.25s ease;
    border: none;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
}

.btn-success:hover {
    background-color: var(--accent-color-hover, #059669) !important;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(16, 185, 129, 0.3);
}

.btn-secondary {
    font-size: 1rem;
    padding: 14px 36px;
    color: var(--body-text);
    background-color: var(--card-bg);
    border: 1px solid var(--ballot-section-border);
    border-radius: 10px;
}

.modal-content {
    background-color: var(--card-bg);
    color: var(--body-text);
    border: 1px solid var(--ballot-section-border);
    border-radius: 14px;
}

.modal-header {
    border-bottom: 1px solid var(--ballot-section-border);
    padding: 20px;
}

.modal-header h5 {
    font-size: 1.4rem;
    font-weight: 700;
}

.modal-footer {
    border-top: 1px solid var(--ballot-section-border);
}

.recap-group {
    background-color: var(--recap-list-bg);
    border: 1px solid var(--ballot-section-border);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 12px;
}

.recap-pos-title {
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: var(--text-muted);
    font-weight: 700;
    margin-bottom: 12px;
}

.recap-candidate-row {
    display: flex;
    align-items: center;
    padding: 10px 0;
}

.recap-candidate-row:not(:last-child) {
    border-bottom: 1px dashed var(--ballot-section-border);
}

.recap-cand-img {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    margin-right: 16px;
    background-color: var(--avatar-fallback);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    border: 1px solid var(--ballot-section-border);
}

.recap-cand-name {
    font-size: 1.1rem;
    font-weight: 600;
    color: var(--body-text);
}

@media (max-width: 768px) {
    .container {
        padding-top: 80px;
        padding-left: 12px;
        padding-right: 12px;
    }
    .candidate-grid {
        max-width: 100%;
        gap: 15px;
    }
    .candidate-avatar-placeholder {
        font-size: 60px;
    }
    .theme-toggle-btn {
        top: 15px;
        right: 15px;
        padding: 8px 14px;
        font-size: 0.8rem;
    }
}
</style>
</head>
<body>

<button class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()">
    <span id="themeToggleIcon">🌙</span> <span id="themeToggleText">Dark Mode</span>
</button>

<div class="container">

<div class="card shadow-lg main-portal-card">

<div class="card-body main-card-body p-5">

<h2 class="mb-1">Cast Your Vote</h2>

<p class="user-welcome-text mb-4">
    Welcome, <strong><?php echo htmlspecialchars($member_name ?: 'Voter'); ?></strong>
</p>

<?php if(!empty($error)){ ?>
<div class="alert alert-danger p-3 mb-4">
    <?php echo $error; ?>
</div>
<?php } ?>

<form method="POST" id="votingBallotForm">

    <?php echo renderHiddenVoteInputs($voteData, $currentPosition['id'] ?? 0); ?>
    <input type="hidden" name="current_page" value="<?php echo htmlspecialchars($currentPage); ?>">
    <input type="hidden" name="action" value="">

    <?php if($currentPosition): ?>

        <div class="position-header">
            <h3><?php echo htmlspecialchars($currentPosition['position_name']); ?></h3>
            <div class="position-info">Select up to <?php echo $currentPosition['vote_limit']; ?> candidate(s)</div>
            <div class="position-limit">Position <?php echo $currentPage + 1; ?> of <?php echo $totalPositions; ?></div>
        </div>

        <div class="candidate-grid">
            <?php
            $position_id = $currentPosition['id'];
            $candidatesList = [];

            if ($is_pdo) {
                try {
                    $cStmt = $conn->prepare("SELECT id, {$candidateNameCol} AS candidate_display_name, photo FROM candidates WHERE position_id = :pid ORDER BY candidate_display_name");
                    $cStmt->execute(['pid' => $position_id]);
                    $candidatesList = $cStmt->fetchAll(PDO::FETCH_ASSOC);
                } catch (Exception $e) {}
            } else {
                $cRes = $conn->query("SELECT id, {$candidateNameCol} AS candidate_display_name, photo FROM candidates WHERE position_id = $position_id ORDER BY candidate_display_name");
                if ($cRes) {
                    while ($cRow = $cRes->fetch_assoc()) {
                        $candidatesList[] = $cRow;
                    }
                }
            }

            foreach($candidatesList as $candidate){
                $displayName = $candidate['candidate_display_name'] ?? '';
                $initials = '';
                if (!empty($displayName)) {
                    $parts = explode(' ', $displayName);
                    $initials = strtoupper(substr($parts[0], 0, 1));
                    if (count($parts) > 1) {
                        $initials .= strtoupper(substr($parts[count($parts) - 1], 0, 1));
                    }
                }

                $imageFilename = $candidate['photo'] ?? '';
                $cleanPath = !empty($imageFilename) ? '../assets/images/' . htmlspecialchars(basename($imageFilename)) : '';
                $checked = in_array($candidate['id'], $currentPositionVotes) ? 'checked' : '';
            ?>

            <div class="candidate-card" id="candidate_card_<?php echo $candidate['id']; ?>" onclick="handleCardClick(event, <?php echo $candidate['id']; ?>, <?php echo $position_id; ?>)">
                <div class="candidate-img-wrapper">
                    <?php if(!empty($cleanPath) && file_exists($cleanPath)){ ?>
                        <img src="<?php echo $cleanPath; ?>" alt="Profile" class="candidate-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                        <div class="candidate-avatar-placeholder" style="display:none;"><?php echo $initials; ?></div>
                    <?php } else { ?>
                        <div class="candidate-avatar-placeholder"><?php echo $initials; ?></div>
                    <?php } ?>
                </div>

                <div class="candidate-details-text">
                    <div class="candidate-name"><?php echo htmlspecialchars($displayName); ?></div>
                </div>

                <div class="candidate-checkbox">
                    <div class="form-check">
                        <input
                            class="form-check-input candidate-box"
                            type="checkbox"
                            name="vote[<?php echo $position_id; ?>][]"
                            value="<?php echo $candidate['id']; ?>"
                            data-position="<?php echo $position_id; ?>"
                            data-limit="<?php echo $currentPosition['vote_limit']; ?>"
                            id="candidate_<?php echo $candidate['id']; ?>"
                            onchange="handleCheckboxChange(this, <?php echo $position_id; ?>);"
                            <?php echo $checked; ?>
                        >
                        <label for="candidate_<?php echo $candidate['id']; ?>" class="vote-label" onclick="event.stopPropagation();">Vote</label>
                    </div>
                </div>
            </div>

            <?php } ?>
        </div>

        <div class="position-nav-buttons">
            <div>
                <?php if($currentPage > 0): ?>
                    <button type="submit" class="btn btn-secondary" onclick="setFormAction('previous')">← Previous</button>
                <?php endif; ?>
            </div>

            <div>
                <?php if($currentPage < $totalPositions - 1): ?>
                    <button type="submit" class="btn btn-primary" onclick="setFormAction('next')">Next →</button>
                <?php else: ?>
                    <button type="button" class="btn btn-success" onclick="showVoteRecapModal()">Submit Vote</button>
                <?php endif; ?>
            </div>
        </div>

    <?php else: ?>
        <div class="alert alert-danger">No position available.</div>
    <?php endif; ?>

</form>

</div>

</div>

</div>

<div class="modal fade" id="recapVoteModal" tabindex="-1" aria-labelledby="recapVoteModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
    <div class="modal-content shadow">
      <div class="modal-header">
        <h5 class="modal-title fw-bold" id="recapVoteModalLabel">Review Your Choices</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body" id="recapModalBodyContainer">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary px-4" data-bs-dismiss="modal" style="border-radius:8px; font-weight:500;">Go Back</button>
        <button type="button" onclick="setFormAction('submit'); executeFinalFormSubmit();" class="btn btn-success px-4" style="border-radius:8px; font-weight:600;">Confirm & Submit</button>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const positionsData = <?php echo json_encode($candidateMetadata); ?>;

function handleCardClick(event, candidateId, positionId) {
    const cb = document.getElementById(`candidate_${candidateId}`);
    if (!cb) return;

    if (event.target === cb || event.target.tagName === 'LABEL') {
        return;
    }

    const checkedCount = document.querySelectorAll(`input[data-position="${positionId}"]:checked`).length;
    const limit = parseInt(cb.dataset.limit || 0);

    if (!cb.checked && checkedCount >= limit) {
        event.preventDefault();
        return;
    }

    cb.checked = !cb.checked;
    evaluateLimits(positionId);
}

function handleCheckboxChange(checkbox, positionId) {
    const checkedCount = document.querySelectorAll(`input[data-position="${positionId}"]:checked`).length;
    const limit = parseInt(checkbox.dataset.limit || 0);

    if (checkbox.checked && checkedCount > limit) {
        checkbox.checked = false;
    }
    evaluateLimits(positionId);
}

function evaluateLimits(positionId) {
    const items = document.querySelectorAll(`input[data-position="${positionId}"]`);
    const checkedItems = document.querySelectorAll(`input[data-position="${positionId}"]:checked`);
    const limit = parseInt(items[0]?.dataset.limit || 0);

    items.forEach(item => {
        const card = item.closest('.candidate-card');
        if(!card) return;
        
        if(item.checked) {
            card.classList.add('selected');
            card.classList.remove('disabled-option');
        } else if(checkedItems.length >= limit) {
            card.classList.add('disabled-option');
            card.classList.remove('selected');
        } else {
            card.classList.remove('disabled-option');
            card.classList.remove('selected');
        }
    });
}

function showVoteRecapModal() {
    const recapContainer = document.getElementById('recapModalBodyContainer');
    recapContainer.innerHTML = '';
    let totalVotesSelected = 0;

    const formData = new FormData(document.getElementById('votingBallotForm'));
    const votes = {};
    for(const [key, value] of formData.entries()){
        const match = key.match(/^vote\[(\d+)\]\[\]$/);
        if(match){
            const positionId = match[1];
            votes[positionId] = votes[positionId] || [];
            votes[positionId].push(value);
        }
    }

    Object.keys(positionsData).forEach(positionId => {
        const position = positionsData[positionId];
        const selected = votes[positionId] || [];
        let candidatesHTML = '';

        if(selected.length > 0) {
            selected.forEach(candidateId => {
                totalVotesSelected++;
                const candidate = position.candidates[candidateId] || {};
                const fullname = candidate.full_name || 'Selected candidate';
                const imgsrc = candidate.imgsrc || '';
                const initials = candidate.initials || '';
                const avatarMarkup = imgsrc
                    ? `<img src="${imgsrc}" alt="Profile" class="candidate-avatar" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"><div class="candidate-avatar-placeholder" style="display:none;">${initials}</div>`
                    : `<div class="candidate-avatar-placeholder">${initials}</div>`;
                candidatesHTML += `<div class="recap-candidate-row"><div class="recap-cand-img">${avatarMarkup}</div><div class="recap-cand-name">${fullname}</div></div>`;
            });
        } else {
            candidatesHTML = '<div class="recap-cand-name text-muted italic small py-1">No candidate chosen (Abstain)</div>';
        }

        const groupDiv = document.createElement('div');
        groupDiv.className = 'recap-group';
        groupDiv.innerHTML = `<div class="recap-pos-title">${position.position_name}</div>${candidatesHTML}`;
        recapContainer.appendChild(groupDiv);
    });

    if(totalVotesSelected === 0) {
        recapContainer.innerHTML = '<div class="alert alert-warning text-center py-3"><strong>Empty Ballot Warning</strong><br>You have not selected any candidates. Submitting now counts as an abstinence for all roles.</div>';
    }

    const recapModalEl = new bootstrap.Modal(document.getElementById('recapVoteModal'));
    recapModalEl.show();
}

function executeFinalFormSubmit() {
    document.getElementById('votingBallotForm').submit();
}

function setFormAction(value) {
    const actionInput = document.querySelector('#votingBallotForm input[name="action"]');
    if(actionInput) {
        actionInput.value = value;
    }
}

function toggleTheme() {
    const body = document.body;
    const icon = document.getElementById('themeToggleIcon');
    const text = document.getElementById('themeToggleText');
    body.classList.toggle('dark-theme');
    if(body.classList.contains('dark-theme')) {
        icon.innerText = '☀️';
        text.innerText = 'Light Mode';
        localStorage.setItem('pmpc-theme', 'dark');
    } else {
        icon.innerText = '🌙';
        text.innerText = 'Dark Mode';
        localStorage.setItem('pmpc-theme', 'light');
    }
}

window.onload = function() {
    if(localStorage.getItem('pmpc-theme') === 'dark') {
        document.body.classList.add('dark-theme');
        document.getElementById('themeToggleIcon').innerText = '☀️';
        document.getElementById('themeToggleText').innerText = 'Light Mode';
    }
    const currentPosId = <?php echo intval($currentPosition['id'] ?? 0); ?>;
    if(currentPosId) {
        evaluateLimits(currentPosId);
    }
}
</script>
</body>
</html>