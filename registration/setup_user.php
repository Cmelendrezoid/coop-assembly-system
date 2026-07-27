<?php
require "db.php";

// 1. Database Prep
$conn->query("ALTER TABLE users MODIFY COLUMN password VARCHAR(255) NOT NULL");
$conn->query("DELETE FROM users"); // Fresh start

// 2. Branch List
$branches = [
    19 => 'Panabo', 20 => 'Tibungco', 21 => 'Bajada', 22 => 'Matina', 
    23 => 'Tagum', 24 => 'Sto. Tomas', 25 => 'Toril', 26 => 'Surigao', 
    27 => 'CDO', 28 => 'Valencia', 29 => 'Gensan', 30 => 'Koronadal', 
    31 => 'Butuan', 32 => 'Digos', 33 => 'Kidapawan', 34 => 'Calinan', 35 => 'SCWE'
];

// 3. Default Passwords
$adminPass = password_hash('admin2026', PASSWORD_DEFAULT);
$staffPass = password_hash('pmpc2026', PASSWORD_DEFAULT);

echo "<body style='font-family:sans-serif; background:#f4f7f6; padding:20px;'>";
echo "<h1>🚀 PMPC 2026 Final User Deployment</h1>";

/* ================= CREATE 5 MASTER ADMINS ================= */
echo "<h2>🛡️ Master Admin Accounts (Full Access)</h2>";
echo "<p>Password: <strong>admin2026</strong></p>";
echo "<div style='display:grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom:30px;'>";

for ($a = 1; $a <= 5; $a++) {
    $adminUser = "admin{$a}_pmpc";
    $stmtA = $conn->prepare("INSERT INTO users (username, password, branch_id, role) VALUES (?, ?, 19, 'admin')");
    $stmtA->bind_param("ss", $adminUser, $adminPass);
    $stmtA->execute();
    echo "<div style='background:#1e3c72; color:white; padding:10px; border-radius:4px;'><code>$adminUser</code></div>";
}
echo "</div><hr>";

/* ================= CREATE BRANCH STAFF ================= */
echo "<h2>👥 Branch Staff Accounts</h2>";
echo "<p>Password: <strong>pmpc2026</strong></p>";

foreach ($branches as $id => $name) {
    $cleanName = strtolower(str_replace(['.', ' ', '/'], '', $name));
    $count = ($id == 19) ? 10 : 5;
    
    echo "<strong>$name (ID: $id)</strong> - $count accounts: ";
    
    $accounts = [];
    for ($i = 1; $i <= $count; $i++) {
        $username = "staff{$i}_{$cleanName}";
        $stmtS = $conn->prepare("INSERT INTO users (username, password, branch_id, role) VALUES (?, ?, ?, 'staff')");
        $stmtS->bind_param("ssi", $username, $staffPass, $id);
        $stmtS->execute();
        $accounts[] = $username;
    }
    echo "<small style='color:#666;'>" . implode(', ', $accounts) . "</small><br><br>";
}

echo "</body>";
?>