<?php
$mysqli = new mysqli('localhost', 'root', '', 'migs_db');
if ($mysqli->connect_error) {
    echo "connect_error\n";
    exit(1);
}
$res = $mysqli->query('SHOW COLUMNS FROM candidates');
if (!$res) {
    echo "query_error: " . $mysqli->error . "\n";
    exit(1);
}
while ($row = $res->fetch_assoc()) {
    echo $row['Field'] . '|' . $row['Type'] . '|' . $row['Null'] . "\n";
}
