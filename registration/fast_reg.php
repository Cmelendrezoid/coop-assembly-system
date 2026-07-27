<?php
require "db.php";
require "auth.php";

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$member = null;

if ($id > 0) {
    // 1. Fetch member + credentials
    $stmt = $conn->prepare("SELECT full_name, migs_category, username, password, printed FROM members WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $member = $stmt->get_result()->fetch_assoc();

    // 2. Mark as printed immediately
    if ($member && !$member['printed']) {
        $conn->query("UPDATE members SET printed = 1, printed_at = NOW() WHERE id = $id");
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Fast Print</title>
    <style>
        /* Hidden from screen, visible for printer */
        #badge { width: 300px; padding: 20px; border: 1px solid #000; text-align: center; }
        @media print {
            .no-print { display: none; }
            #badge { border: none; }
        }
    </style>
</head>
<body onload="<?= $member ? 'window.print(); setTimeout(()=>window.location.href=\'index.php\', 1000);' : '' ?>">

    <div class="no-print">
        <?php if (!$member): ?>
            <h1 style="color:red;">INVALID QR CODE</h1>
            <a href="index.php">Go Back</a>
        <?php else: ?>
            <h1>Printing Badge for <?= htmlspecialchars($member['full_name']) ?>...</h1>
            <p>Please wait, you will be redirected automatically.</p>
        <?php endif; ?>
    </div>

    <?php if ($member): ?>
    <div id="badge">
        <h2><?= htmlspecialchars($member['full_name']) ?></h2>
        <p><strong>Category:</strong> <?= htmlspecialchars($member['migs_category']) ?></p>
        <hr>
        <p>VOTING CREDENTIALS</p>
        <p>User: <?= htmlspecialchars($member['username']) ?></p>
        <p>Pass: <?= htmlspecialchars($member['password']) ?></p>
    </div>
    <?php endif; ?>

</body>
</html>