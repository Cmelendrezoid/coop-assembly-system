<?php
require "auth.php";
require "db.php";

/* ===== BRANCH MAPPING ===== */
$branch_names = [
    19 => 'Panabo', 20 => 'Tibungco', 21 => 'Bajada', 22 => 'Matina', 
    23 => 'Tagum', 24 => 'Sto. Tomas', 25 => 'Toril', 26 => 'Surigao', 
    27 => 'CDO', 28 => 'Valencia', 29 => 'Gensan', 30 => 'Koronadal', 
    31 => 'Butuan', 32 => 'Digos', 33 => 'Kidapawan', 34 => 'Calinan', 35 => 'SCWE'
];
$current_branch = $branch_names[$_SESSION['branch_id']] ?? 'Unknown';
$my_user_id = $_SESSION['user_id']; 
$my_role = $_SESSION['role'] ?? 'staff'; // Detect if user is admin or staff

/* ===== SEARCH & FILTER ===== */
$search = trim($_GET['search'] ?? '');
$searchParam = "%$search%";

/** * SECURITY LOGIC:
 * 1. Admin: Sees ALL members who claimed.
 * 2. Staff: ONLY sees members they processed (on their computer/account).
 */
if ($my_role === 'admin') {
    $sql = "SELECT full_name, migs_category, allowance_claimed_at 
            FROM members 
            WHERE allowance_claimed = 1";
} else {
    $sql = "SELECT full_name, migs_category, allowance_claimed_at 
            FROM members 
            WHERE allowance_claimed = 1 
            AND allowance_processed_by = ?";
}

// Add search condition if text is entered
if ($search !== '') {
    $sql .= " AND full_name LIKE ?";
}

$sql .= " ORDER BY allowance_claimed_at DESC";

$stmt = $conn->prepare($sql);

// Bind parameters dynamically based on role and search
if ($my_role === 'admin') {
    if ($search !== '') {
        $stmt->bind_param("s", $searchParam);
    }
} else {
    if ($search !== '') {
        $stmt->bind_param("is", $my_user_id, $searchParam);
    } else {
        $stmt->bind_param("i", $my_user_id);
    }
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Claimed Allowances – PMPC 2026</title>

<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    background: linear-gradient(135deg, #3a7bd5, #1e3c72);
    color: #fff;
}

/* ===== SIDEBAR ===== */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 240px;
    height: 100%;
    background: linear-gradient(180deg, #3a7bd5, #1e3c72);
    padding-top: 20px;
    transition: width 0.3s ease;
    display: flex;
    flex-direction: column;
    z-index: 1000;
}

.sidebar.collapsed { width: 70px; }
.sidebar h2 { text-align: center; margin-bottom: 20px; font-size: 1.2rem; }
.sidebar.collapsed h2, .sidebar.collapsed .user-info { display: none; }

.user-info {
    padding: 15px 20px;
    background: rgba(0, 0, 0, 0.2);
    margin-bottom: 10px;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
}
.user-label { font-size: 10px; color: #add8e6; text-transform: uppercase; letter-spacing: 1px; }
.user-name { font-weight: bold; font-size: 14px; margin-top: 4px; display: block; }
.user-branch { font-size: 12px; color: #2ecc71; margin-top: 2px; }

.sidebar a {
    display: flex;
    align-items: center;
    padding: 14px 20px;
    color: white;
    text-decoration: none;
}
.sidebar a:hover { background: rgba(255,255,255,0.15); }
.sidebar span { margin-left: 12px; }
.sidebar.collapsed span { display: none; }

.logout-link {
    margin-top: auto;
    background: rgba(192, 57, 43, 0.8);
    margin-bottom: 20px;
}
.logout-link:hover { background: #c0392b !important; }

.toggle-btn {
    position: absolute;
    top: 15px;
    right: -18px;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    cursor: pointer;
    background: #1e3c72;
    color: white;
    box-shadow: 0 4px 10px rgba(0,0,0,0.3);
}

/* ===== MAIN ===== */
.main {
    margin-left: 240px;
    padding: 40px;
    transition: margin-left 0.3s ease;
}
.main.collapsed { margin-left: 70px; }

.card {
    background: white;
    color: #333;
    padding: 25px;
    border-radius: 12px;
    box-shadow: 0 10px 25px rgba(0,0,0,0.2);
}

.search-box { margin: 15px 0; }
input[type="text"] {
    padding: 10px;
    width: 280px;
    border-radius: 6px;
    border: 1px solid #ccc;
    outline: none;
}

button, .export-btn {
    padding: 10px 16px;
    border-radius: 6px;
    border: none;
    cursor: pointer;
    background: #1e3c72;
    color: white;
    font-weight: bold;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    font-size: 14px;
}

.print-btn {
    margin-top: 15px;
    background: #27ae60;
    gap: 8px;
}
.print-btn:hover { background: #219150; }

.export-btn {
    margin-top: 15px;
    background: #f39c12;
    gap: 8px;
}
.export-btn:hover { background: #e67e22; }

/* ===== TABLE ===== */
table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
}
th, td {
    padding: 12px;
    border-bottom: 1px solid #eee;
    text-align: left;
}
th { background: #f8f9fa; color: #555; text-transform: uppercase; font-size: 12px; }
tr:hover { background: #fdfdfd; }

/* ===== PRINT ===== */
@media print {
    body { background: none !important; color: #000 !important; }
    .sidebar, .toggle-btn, .search-box, .action-buttons { display: none !important; }
    .main { margin: 0 !important; padding: 0 !important; }
    .card { box-shadow: none; border: none; width: 100%; }
    th { background: #eee !important; color: #000 !important; }
}
</style>
</head>

<body>

<div class="sidebar" id="sidebar">
    <button class="toggle-btn" onclick="toggleSidebar()">☰</button>
    <h2>PANABO COOP</h2>

    <div class="user-info">
        <div class="user-label">Logged in as</div>
        <span class="user-name">👤 <?= htmlspecialchars($_SESSION['username']) ?></span>
        <div class="user-branch">📍 <?= htmlspecialchars($current_branch) ?></div>
    </div>

    <a href="index.php">🏠 <span>Search Member</span></a>
    
    <a href="tracker.php" style="background: rgba(46, 204, 113, 0.1);">📈 <span>Live Attendance</span></a>
    
    <hr style="width: 80%; border: 0.5px solid rgba(255,255,255,0.1); margin: 10px auto;">

    <a href="printed.php">🟢 <span>Printed Members</span></a>
    <a href="allowance.php">💵 <span>Claimed Allowance</span></a>
    <a href="allowance_report.php">💵 <span>Allowance</span></a>
    
    <a href="logout.php" class="logout-link">🚪 <span>Logout</span></a>
</div>

<div class="main" id="main">
    <div class="card">
        <h2>💵 <?= ($my_role === 'admin') ? 'Master Claim List (Administrator)' : 'My Processed Claims' ?></h2>

        <form method="get" class="search-box">
            <input type="text" name="search" placeholder="<?= ($my_role === 'admin') ? 'Search all claims...' : 'Search my claims...' ?>" value="<?= htmlspecialchars($search) ?>">
            <button type="submit">Search</button>
        </form>

        <table>
            <thead>
                <tr>
                    <th>Full Name</th>
                    <th>MIGS Category</th>
                    <th>Date & Time Claimed</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($row['full_name']) ?></strong></td>
                            <td><?= htmlspecialchars($row['migs_category']) ?></td>
                            <td><?= date("M d, Y | h:i A", strtotime($row['allowance_claimed_at'])) ?></td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3" style="text-align:center; padding: 40px; color: #999;">
                            <?= ($my_role === 'admin') ? 'No claims found in the database.' : 'You have not processed any claims on this account yet.' ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
function toggleSidebar() {
    document.getElementById("sidebar").classList.toggle("collapsed");
    document.getElementById("main").classList.toggle("collapsed");
}
</script>

</body>
</html>