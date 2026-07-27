<?php require "auth.php"; ?>
<?php
require "db.php";

/* ===== INPUTS ===== */
$letter = $_GET['letter'] ?? '';
$migs   = $_GET['migs'] ?? '';

/* ===== BASE QUERY ===== */
$sql = "
    SELECT id, full_name, migs_category, printed
    FROM members
    WHERE 1
";

$params = [];
$types  = "";

/* ===== FILTER BY LETTER ===== */
if ($letter !== '' && preg_match('/^[A-Z]$/', $letter)) {
    $sql .= " AND full_name LIKE ?";
    $params[] = "$letter%";
    $types .= "s";
}

/* ===== FILTER BY MIGS ===== */
if (in_array($migs, ['Gold', 'Silver', 'Bronze'])) {
    $sql .= " AND migs_category = ?";
    $params[] = $migs;
    $types .= "s";
}

/* ===== SORT ===== */
$sql .= " ORDER BY full_name ASC LIMIT 500";

$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html>
<head>
<title>All Members</title>

<style>
body {
    margin: 0;
    font-family: Arial, sans-serif;
    min-height: 100vh;
    background: linear-gradient(135deg, #3a7bd5, #1e3c72);
    color: white;
}

/* ===== SIDEBAR ===== */
.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 230px;
    height: 100%;
    background: linear-gradient(180deg, #3a7bd5, #1e3c72);
    padding-top: 20px;
}

.sidebar a {
    display: block;
    padding: 14px 20px;
    color: white;
    text-decoration: none;
}

.sidebar a:hover {
    background: rgba(255,255,255,0.15);
}

/* ===== MAIN ===== */
.main {
    margin-left: 230px;
    padding: 30px;
}

/* ===== FILTERS ===== */
.filters {
    max-width: 900px;
    margin: auto;
    background: rgba(255,255,255,0.15);
    padding: 20px;
    border-radius: 12px;
    margin-bottom: 20px;
}

.letters a {
    display: inline-block;
    margin: 4px;
    padding: 8px 12px;
    background: rgba(255,255,255,0.2);
    color: white;
    border-radius: 6px;
    text-decoration: none;
    font-weight: bold;
}

.letters a.active,
.letters a:hover {
    background: #f39c12;
    color: #000;
}

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

select {
    padding: 10px;
    border-radius: 6px;
    border: none;
    margin-top: 10px;
}

/* ===== TABLE ===== */
.table-wrap {
    max-width: 900px;
    margin: auto;
    background: white;
    color: #333;
    border-radius: 12px;
    overflow: hidden;
}

table {
    width: 100%;
    border-collapse: collapse;
}

th, td {
    padding: 14px;
}

th {
    background: #f1f4f9;
}

tr:nth-child(even) {
    background: #f9fbff;
}

tr:hover {
    background: #eef4ff;
    cursor: pointer;
}

.printed { color: #27ae60; font-weight: bold; }
.not-printed { color: #c0392b; font-weight: bold; }

.empty {
    text-align: center;
    padding: 30px;
}

h2 {
        font-size: 28pt;
        text-align: center;
    }
</style>
</head>

<body>

<!-- SIDEBAR -->
<div class="sidebar" id="sidebar">
    <button class="toggle-btn" onclick="toggleSidebar()">☰</button>
    <h2>PANABO COOP</h2>
    <a href="index.php">🏠 <span>Search</span></a>
    <a href="printed.php">🟢 <span>Printed Members</span></a>
    <a href="allowance.php">💵 <span>Claimed Allowance</span></a>
</div>

<div class="main">

<h1>👥 Members Directory</h1>

<!-- FILTER PANEL -->
<div class="filters">

    <strong>Filter by Last Name (First Letter):</strong>
    <div class="letters">
        <?php foreach (range('A','Z') as $char): ?>
            <a 
                href="?letter=<?= $char ?>&migs=<?= urlencode($migs) ?>"
                class="<?= $letter === $char ? 'active' : '' ?>"
            >
                <?= $char ?>
            </a>
        <?php endforeach; ?>
    </div>

    <form method="get">
        <input type="hidden" name="letter" value="<?= htmlspecialchars($letter) ?>">

        <strong>MIGS Category:</strong><br>
        <select name="migs" onchange="this.form.submit()">
            <option value="">All</option>
            <option value="Gold" <?= $migs==='Gold'?'selected':'' ?>>Gold</option>
            <option value="Silver" <?= $migs==='Silver'?'selected':'' ?>>Silver</option>
            <option value="Bronze" <?= $migs==='Bronze'?'selected':'' ?>>Bronze</option>
        </select>
    </form>

</div>

<!-- TABLE -->
<div class="table-wrap">
<table>
<thead>
<tr>
    <th>Full Name</th>
    <th>MIGS</th>
    <th>Status</th>
</tr>
</thead>
<tbody>

<?php if ($letter === '' && $migs === ''): ?>
<tr>
    <td colspan="3" class="empty">
        Please select a letter or MIGS category to load members.
    </td>
</tr>

<?php elseif ($result->num_rows === 0): ?>
<tr>
    <td colspan="3" class="empty">No members found.</td>
</tr>

<?php else: ?>
<?php while ($row = $result->fetch_assoc()): ?>
<tr onclick="location.href='view.php?id=<?= $row['id'] ?>'">
    <td><?= htmlspecialchars($row['full_name']) ?></td>
    <td><?= htmlspecialchars($row['migs_category']) ?></td>
    <td class="<?= $row['printed'] ? 'printed' : 'not-printed' ?>">
        <?= $row['printed'] ? '🟢 PRINTED' : '🔴 NOT PRINTED' ?>
    </td>
</tr>
<?php endwhile; ?>
<?php endif; ?>

</tbody>
</table>
</div>

</div>
</body>
</html>
