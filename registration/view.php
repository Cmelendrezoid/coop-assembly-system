<?php
require "auth.php";
require "db.php";

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) die("Invalid ID");

$branch_names = [
    19 => 'Panabo', 20 => 'Tibungco', 21 => 'Bajada', 22 => 'Matina', 
    23 => 'Tagum', 24 => 'Sto. Tomas', 25 => 'Toril', 26 => 'Surigao', 
    27 => 'CDO', 28 => 'Valencia', 29 => 'Gensan', 30 => 'Koronadal', 
    31 => 'Butuan', 32 => 'Digos', 33 => 'Kidapawan', 34 => 'Calinan', 
    35 => 'SCWE', 36 => 'Davao City Venue'
];
$current_branch = $branch_names[$_SESSION['branch_id']] ?? 'Unknown';

// SENIOR CONFIG: Change these 3 string names whenever management decides what the items are!
$freebies_list = [
    "PMPC Payong",
    "PMPC T-SHIRT",
    "PMPC Water Bottle"
];

// CATEGORY CONFIG: Set specific tier rules here (e.g., Only 'GOLD' can claim 'PMPC Water Bottle')
// Allowed values inside array: 'GOLD', 'SILVER', 'BRONZE'. Leaving an item out means anyone can claim it.
$freebie_restrictions = [
    "PMPC Water Bottle" => ['GOLD', 'Diamond']
];

/* ================= FETCH MEMBER DETAILS ================= */
$stmt = $conn->prepare("SELECT m.*, b.branch_name AS venue_name FROM members m LEFT JOIN branches b ON m.branch_id = b.branch_id WHERE m.id = ?");
$stmt->bind_param("i", $id); $stmt->execute();
$data = $stmt->get_result()->fetch_assoc();
if (!$data) die("Member not found");

$member_branch_display = !empty($data['branch_name']) ? $data['branch_name'] : ($data['venue_name'] ?? 'Unassigned');
$member_migs_category = strtoupper(trim($data['migs_category'] ?? ''));
$allowedMigs = ['GOLD', 'SILVER', 'BRONZE'];
$isMigs = in_array($member_migs_category, $allowedMigs);

/* ================= ACTIONS ================= */
if (isset($_POST['print'])) {
    $stmt = $conn->prepare("UPDATE members SET printed = 1, printed_at = NOW() WHERE id = ?");
    $stmt->bind_param("i", $id); $stmt->execute();
    header("Location: view.php?id=$id"); exit;
}
if (isset($_POST['reset'])) {
    $stmt = $conn->prepare("UPDATE members SET printed = 0, printed_at = NULL WHERE id = ?");
    $stmt->bind_param("i", $id); $stmt->execute();
    header("Location: view.php?id=$id"); exit;
}
if (isset($_POST['claim_allowance'])) {
    if (!$isMigs) {
        header("Location: view.php?id=$id&claim_error=non_migs"); exit;
    }

    $staff_id = $_SESSION['user_id']; 
    $stmt = $conn->prepare("UPDATE members SET allowance_claimed = 1, allowance_claimed_at = NOW(), allowance_processed_by = ? WHERE id = ?");
    $stmt->bind_param("ii", $staff_id, $id); $stmt->execute();
    header("Location: view.php?id=$id&claimed=success"); exit;
}

// ACTION ROUTE: Dynamically log a freebie item claim entry with full name
if (isset($_POST['claim_freebie'])) {
    $item_to_claim = trim($_POST['claim_freebie']);
    $staff_id = intval($_SESSION['user_id']);
    $member_full_name = $data['full_name'];
    
    if (in_array($item_to_claim, $freebies_list)) {
        // Enforce restriction rules matching backend database check
        $is_allowed = true;
        if (array_key_exists($item_to_claim, $freebie_restrictions)) {
            if (!in_array($member_migs_category, $freebie_restrictions[$item_to_claim])) {
                $is_allowed = false;
            }
        }

        if ($is_allowed) {
            // Inserts member_id, full_name, and item safely into our updated schema
            $stmt = $conn->prepare("INSERT IGNORE INTO member_freebies (member_id, full_name, freebie_item, claimed_at, processed_by) VALUES (?, ?, ?, NOW(), ?)");
            $stmt->bind_param("issi", $id, $member_full_name, $item_to_claim, $staff_id);
            $stmt->execute();
        }
    }
    header("Location: view.php?id=$id"); exit;
}

/* ================= FETCH COMPLETED CLAIMS ================= */
$claimed_items = [];
$claims_stmt = $conn->prepare("SELECT freebie_item FROM member_freebies WHERE member_id = ?");
$claims_stmt->bind_param("i", $id);
$claims_stmt->execute();
$claims_result = $claims_stmt->get_result();
while ($row = $claims_result->fetch_assoc()) {
    $claimed_items[] = $row['freebie_item'];
}

/* ================= QR CODE LOGIC ================= */
$qr_data = "http://localhost/view.php?id=" . $id; 
$qr_url = "https://api.qrserver.com/v1/create-qr-code/?size=150x150&data=" . urlencode($qr_data);

/* ================= VOUCHER LOGIC ================= */
$isAwardee = strtoupper(trim($data['awardee'] ?? '')) === 'AWARDEE';
$randomVoucher = null;

if ($isMigs) {
    $result = $conn->query("SELECT voucher_id, voucher_code FROM wifi_vouchers WHERE is_used = 0 ORDER BY RAND() LIMIT 1");
    if ($result && $result->num_rows > 0) {
        $rowVoucher = $result->fetch_assoc();
        $randomVoucher = $rowVoucher['voucher_code'];
        if (isset($_GET['claimed'])) $conn->query("UPDATE wifi_vouchers SET is_used = 1 WHERE voucher_id = " . $rowVoucher['voucher_id']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Member Details - <?= htmlspecialchars($data['full_name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
<style>
    :root {
        --royal-blue: #004aad;
        --dark-blue: #002d6b;
        --accent-green: #00ff88;
        --bg-light: #f4f7fe;
        --card-bg: #ffffff;
        --text-main: #333333;
        --text-muted: #64748b;
        --sidebar-bg: #004aad;
        --border-color: #edf2f7;
        --status-bg: #f8f9fa;
        --purple-freebie: #6f42c1;
        --modal-overlay: rgba(0, 0, 0, 0.5);
    }

    body.dark-mode {
        --bg-light: #020617;
        --card-bg: #0f172a;
        --text-main: #f1f5f9;
        --text-muted: #94a3b8;
        --sidebar-bg: #000000;
        --border-color: #334155;
        --dark-blue: #3b82f6;
        --status-bg: #1e293b;
        --purple-freebie: #8b5cf6;
        --modal-overlay: rgba(0, 0, 0, 0.75);
    }

    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
    body { background: var(--bg-light); color: var(--text-main); display: flex; min-height: 100vh; transition: background 0.3s, color 0.3s; }

    .sidebar { width: 280px; background: var(--sidebar-bg); color: white; padding: 30px 20px; display: flex; flex-direction: column; flex-shrink: 0; position: fixed; height: 100vh; }
    .sidebar h2 { font-size: 1.9rem; margin-bottom: 40px; text-align: center; border-bottom: 1px solid rgba(255,255,255,0.1); padding-bottom: 20px; }
    .user-profile { background: rgba(255,255,255,0.1); padding: 15px; border-radius: 12px; margin-bottom: 30px; }
    .user-profile .name { display: block; font-weight: 700; font-size: 14px; color: var(--accent-green); }
    .user-profile .branch { font-size: 12px; opacity: 0.8; }
    
    .sidebar a, .theme-btn { padding: 14px 18px; color: white; text-decoration: none; border-radius: 10px; margin-bottom: 8px; display: flex; align-items: center; font-weight: 500; background: transparent; border: none; width: 100%; cursor: pointer; font-size: 16px; }
    .sidebar a:hover, .theme-btn:hover { background: rgba(255,255,255,0.15); transform: translateX(5px); transition: 0.2s; }
    .logout { margin-top: auto; color: #ff6b6b !important; border: 1px solid rgba(255,107,107,0.2) !important; }

    .main { flex: 1; margin-left: 280px; padding: 40px; display: flex; justify-content: center; align-items: flex-start; }
    .card { width: 100%; max-width: 500px; background: var(--card-bg); padding: 40px; border-radius: 25px; box-shadow: 0 10px 40px rgba(0,0,0,0.1); text-align: center; border: 1px solid var(--border-color); }
    .card h2 { color: var(--dark-blue); font-size: 26px; margin-bottom: 10px; }
    .card p { margin-bottom: 12px; font-size: 15px; }
    .card p strong { color: var(--text-muted); font-size: 12px; text-transform: uppercase; margin-right: 5px; }

    .qr-box { background: #fff; padding: 15px; display: inline-block; border-radius: 15px; border: 2px solid var(--royal-blue); margin-bottom: 20px; }
    .qr-box img { display: block; }

    button { padding: 16px; border: none; border-radius: 12px; cursor: pointer; margin-top: 12px; font-weight: 700; width: 100%; transition: 0.3s; font-size: 14px; }
    .print { background: var(--royal-blue); color: white; }
    .reset { background: #64748b; color: white; font-size: 12px; }
    .claim { background: #10b981; color: white; }
    .voucher-btn { background: #f59e0b; color: white; }
    .freebies-trigger-btn { background: var(--purple-freebie); color: white; }
    button:hover { filter: brightness(1.1); transform: translateY(-2px); }
    button:disabled { background: var(--border-color); color: var(--text-muted); cursor: not-allowed; transform: none; filter: none; }

    .awardee { margin-bottom: 20px; font-weight: 800; color: #d4af37; font-size: 1.4em; letter-spacing: 1px; }
    .status { font-weight: 800; margin: 25px 0; padding: 15px; background: var(--status-bg); border-radius: 12px; letter-spacing: 1px; }
    
    hr { border: none; border-top: 1px solid var(--border-color); margin: 30px 0; }
    h3 { font-size: 14px; text-transform: uppercase; color: var(--text-muted); margin-bottom: 15px; letter-spacing: 1px; }

    /* CHECKBOX LOG TRACKING STYLES */
    .print-claims-container { margin-top: 20px; text-align: left; background: var(--status-bg); padding: 15px; border-radius: 12px; border: 1px dashed var(--border-color); }
    .print-claim-row { display: flex; justify-content: space-between; align-items: center; font-size: 13px; padding: 8px 0; border-bottom: 1px solid var(--border-color); }
    .print-claim-row:last-child { border-bottom: none; }
    
    /* Dynamic Print Checkbox UI Box */
    .print-checkbox { display: inline-block; width: 18px; height: 18px; border: 2px solid var(--text-main); border-radius: 4px; text-align: center; line-height: 14px; font-weight: bold; font-size: 12px; }
    .print-checkbox.checked { color: #10b981; border-color: #10b981; }
    .print-checkbox.unchecked { color: transparent; }

    /* CUSTOM DYNAMIC THEME MODAL */
    .custom-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--modal-overlay); z-index: 9999; justify-content: center; align-items: center; }
    .custom-modal { background: var(--card-bg); border: 2px solid var(--border-color); width: 90%; max-width: 460px; border-radius: 20px; padding: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); text-align: left; position: relative; }
    .custom-modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--modal-overlay); z-index: 9999; justify-content: center; align-items: center; }
    .custom-modal { background: var(--card-bg); border: 2px solid var(--border-color); width: 90%; max-width: 460px; border-radius: 20px; padding: 30px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); text-align: left; position: relative; }
    .custom-modal-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 1px solid var(--border-color); }
    .custom-modal-header h4 { font-size: 18px; color: var(--dark-blue); }
    .custom-modal-close { background: transparent; color: var(--text-muted); font-size: 22px; width: auto; margin: 0; padding: 0; cursor: pointer; font-weight: 300; }
    .custom-modal-close:hover { transform: none; color: #ff6b6b; }
    
    .freebie-row { display: flex; justify-content: space-between; align-items: center; padding: 15px; background: var(--status-bg); margin-bottom: 10px; border-radius: 12px; border: 1px solid var(--border-color); }
    .freebie-details { max-width: 65%; }
    .freebie-details h5 { font-size: 14px; margin-bottom: 2px; }
    .freebie-details span { font-size: 11px; color: var(--text-muted); display: block; }
    .freebie-row button { width: auto; margin-top: 0; padding: 8px 16px; font-size: 13px; }
    .freebie-row .claimed-badge { background: transparent; border: 1px solid var(--border-color); color: #10b981; font-weight: 700; padding: 8px 16px; font-size: 13px; border-radius: 12px; }

    @media print { 
        .sidebar, button, .status, .allowance-section, hr, .logout, .qr-box, .custom-modal-overlay { display: none !important; } 
        .main { margin-left: 0; padding: 0; }
        .card { box-shadow: none; border: none; width: 100%; color: black; background: white !important; } 
        .print-claims-container { border: 1px dashed #000; background: #fff; }
        .print-claim-row { border-bottom: 1px solid #ddd; color: #000; }
        .print-checkbox { border: 2px solid #000 !important; color: #000 !important; }
    }
</style>
</head>
<body class="<?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'dark-mode' : '' ?>">

<aside class="sidebar">
    <h2>PANABO COOP <br> 2026</h2>
    <div class="user-profile">
        <span class="name"><?= htmlspecialchars($_SESSION['username']) ?></span>
        <span class="branch"><?= htmlspecialchars($current_branch) ?> Branch</span>
    </div>
    <nav>
        <a href="index.php">🏠 <span> &nbsp; Search Member</span></a>
        <a href="tracker.php">📈 <span> &nbsp; Live Attendance</span></a>
        <hr style="border: 0.5px solid rgba(255,255,255,0.1); margin: 15px 0;">
        <a href="printed.php">🟢 <span> &nbsp; Printed Members</span></a>
        <a href="allowance_report.php">💵 <span> &nbsp; Allowance Report</span></a>
        
        <button class="theme-btn" onclick="toggleTheme()">
            <span id="theme-icon"><?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? '☀️' : '🌙' ?></span>
            <span id="theme-text"><?= isset($_COOKIE['theme']) && $_COOKIE['theme'] == 'dark' ? 'Light Mode' : 'Dark Mode' ?></span>
        </button>

        <a href="logout.php" class="logout">🚪 <span> &nbsp; Logout</span></a>
    </nav>
</aside>

<main class="main">
    <div class="card">

        <?php if ($isAwardee): ?><div class="awardee">🏆 AWARDEE</div><?php endif; ?>
        <h2><?= htmlspecialchars($data['full_name']) ?></h2>

        <p><strong>Branch:</strong> <?= htmlspecialchars($member_branch_display) ?></p>
        <p><strong>Category:</strong> <?= htmlspecialchars($data['migs_category']) ?></p>
        <p><strong>Username:</strong> <?= htmlspecialchars($data['username']) ?></p>
        <p><strong>Password:</strong> <?= htmlspecialchars($data['password']) ?></p>

        <div class="status">
            <?= $data['printed'] ? "🟢 STATUS: PRINTED" : "🔴 STATUS: NOT PRINTED" ?>
        </div>
        <form method="post" onsubmit="window.print();"><button name="print" class="print" style="margin-top: 25px;">🖨 Print Profile Details</button></form>
        <form method="post"><button name="reset" class="reset">♻ Reset Print Status</button></form>

        <?php if (isset($_GET['claim_error']) && $_GET['claim_error'] === 'non_migs'): ?>
        <div class="status" style="margin-top: 12px; background: #fff7ed; color: #c2410c; border: 1px solid #fdba74;">
            ⚠️ Non-MIGS members are not eligible to claim cash allowance.
        </div>
        <?php endif; ?>

        <?php if ($isMigs): ?>
        <div class="allowance-section">
            <hr>
            <h3>Cash Allowance & WiFi</h3>
            <form method="post"><button name="claim_allowance" class="claim" <?= $data['allowance_claimed'] ? 'disabled' : ''; ?>><?= $data['allowance_claimed'] ? '✅ Allowance Already Claimed' : 'Confirm Allowance Claim'; ?></button></form>
            <?php if ($randomVoucher): ?><button class="voucher-btn" onclick="printVoucherSlip()">🧾 Print WiFi Voucher</button><?php endif; ?>
            
            <button type="button" class="freebies-trigger-btn" onclick="openFreebiesModal()">🎁 Claim Freebies Checklist</button>
        </div>
        <?php endif; ?>
    </div>
</main>

<div class="custom-modal-overlay" id="freebiesOverlay" onclick="closeFreebiesModalOutside(event)">
    <div class="custom-modal">
        <div class="custom-modal-header">
            <h4>🎁 Annual Items Checklist</h4>
            <button class="custom-modal-close" onclick="closeFreebiesModal()">&times;</button>
        </div>
        
        <?php 
        foreach ($freebies_list as $item): 
            // HIDDEN FOR UNQUALIFIED: If item is restricted, completely skip rendering row if user doesn't match tier
            if (array_key_exists($item, $freebie_restrictions)) {
                if (!in_array($member_migs_category, $freebie_restrictions[$item])) {
                    continue; 
                }
            }
        ?>
        <div class="freebie-row">
            <div class="freebie-details">
                <h5><?= htmlspecialchars($item) ?></h5>
                <span>General Assembly Giveaway Item</span>
            </div>
            <?php if (in_array($item, $claimed_items)): ?>
                <div class="claimed-badge">✓ Claimed</div>
            <?php else: ?>
                <form method="post">
                    <button name="claim_freebie" value="<?= htmlspecialchars($item) ?>" class="claim">Claim</button>
                </form>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
function toggleTheme() {
    const body = document.body;
    const icon = document.getElementById('theme-icon');
    const text = document.getElementById('theme-text');
    body.classList.toggle('dark-mode');
    const isDark = body.classList.contains('dark-mode');
    icon.innerText = isDark ? '☀️' : '🌙';
    text.innerText = isDark ? 'Light Mode' : 'Dark Mode';
    document.cookie = "theme=" + (isDark ? "dark" : "light") + ";max-age=" + (30*24*60*60) + ";path=/";
}

function printVoucherSlip() {
    const name = "<?= htmlspecialchars(addslashes($data['full_name'])) ?>";
    const branch = "<?= htmlspecialchars(addslashes($member_branch_display)) ?>";
    const code = "<?= htmlspecialchars($randomVoucher) ?>";
    const win = window.open('', '_blank', 'width=450,height=450');
    win.document.write(`<html><body onload="window.print();window.close();" style="text-align:center;font-family:sans-serif;padding:20px;">
        <div style="border:2px dashed #000;padding:25px;display:inline-block;width:300px;">
        <h3 style="margin-top:0;">WIFI VOUCHER</h3><p>Member: <strong>${name}</strong></p><p>Branch: <strong>${branch}</strong></p>
        <div style="font-size:2em;font-weight:bold;margin:10px 0;background:#eee;padding:10px;">${code}</div>
        <p style="font-size:10px;">PMPC GA 2026</p></div></body></html>`);
    win.document.close();
}

/* MODAL DISPLAY CONTROLS */
function openFreebiesModal() {
    document.getElementById('freebiesOverlay').style.display = 'flex';
}
function closeFreebiesModal() {
    document.getElementById('freebiesOverlay').style.display = 'none';
}
function closeFreebiesModalOutside(event) {
    if (event.target === document.getElementById('freebiesOverlay')) {
        closeFreebiesModal();
    }
}
</script>
</body>
</html>