<?php
$db = new SQLite3('migs.db');

/* Create table */
$db->exec("
CREATE TABLE IF NOT EXISTS members (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT,
    category TEXT,
    credentials TEXT,
    status TEXT
)
");

/* Optional: import initial data (EDIT if needed) */
$count = $db->querySingle("SELECT COUNT(*) FROM members");

if ($count == 0) {
    $db->exec("
    INSERT INTO members (name, category, credentials, status) VALUES
    ('Bate, Bemejean R.', 'GOLD', 'Username: Bate Password: 008980', ''),
    ('Caballero, Jerald Y.', 'GOLD', 'Username: Caballero Password: 008972', ''),
    ('Heba, Josefa M.', 'GOLD', 'Username: Heba Password: 009274', '')
    ");
}

echo "✅ SQLite database ready!";
