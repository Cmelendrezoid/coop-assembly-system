<?php
require "db.php";
require "auth.php";

// 1. Get and Sanitize ID
$id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if ($id > 0) {
    // 2. Update the database directly
    // This is 100x faster than editing a CSV file
    $stmt = $conn->prepare("UPDATE members SET printed = 1, printed_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $id);
    
    if ($stmt->execute()) {
        // Success: Redirect back to index or view
        header("Location: view.php?id=" . $id . "&success=1");
    } else {
        echo "Error updating record: " . $conn->error;
    }
} else {
    header("Location: index.php");
}
exit;