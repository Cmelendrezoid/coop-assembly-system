<?php
require "db.php";

$result = $conn->query("SELECT full_name, printed FROM members LIMIT 5");

while ($row = $result->fetch_assoc()) {
    echo $row['full_name'] . " - Printed: " . $row['printed'] . "<br>";
}
