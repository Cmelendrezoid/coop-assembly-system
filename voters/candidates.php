<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();
include '../config/db.php';

$_SESSION['reviewed_candidates'] = true;

$positions = $conn->query("
    SELECT *
    FROM positions
    ORDER BY id
");

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Election Candidates</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

:root{
    --bg:#f8fafc;
    --card:#ffffff;
    --border:#e2e8f0;
    --text:#1e293b;
    --muted:#64748b;
    --primary:#2563eb;
    --success:#16a34a;
    --placeholder-bg:#e2e8f0;
    --notice-bg:#eff6ff;
    --notice-border:#bfdbfe;
}

.dark-theme{
    --bg:#0f172a;
    --card:#162338;
    --border:#334155;
    --text:#f8fafc;
    --muted:#94a3b8;
    --primary:#60a5fa;
    --success:#34d399;
    --placeholder-bg:#334155;
    --notice-bg:#151f2a;
    --notice-border:#22314a;
}

body{
    background:var(--bg);
    font-family:Segoe UI, Arial, sans-serif;
    color:var(--text);
}

.page-header{
    background:var(--card);
    border-bottom:1px solid var(--border);
    padding:20px 0;
    margin-bottom:25px;
}

.page-title{
    font-size:2rem;
    font-weight:700;
    text-align:center;
    margin-bottom:4px;
}

.page-subtitle{
    text-align:center;
    color:var(--muted);
    font-size:0.95rem;
    margin-bottom:0;
    margin-top:4px;
}

.position-section{
    margin-bottom:48px;
    background: rgba(245, 158, 11, 0.18);
    border: 2px solid rgba(245, 158, 11, 0.75);
    border-radius: 24px;
    box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06);
    overflow: hidden;
}

.position-gold {
    background: rgba(245, 158, 11, 0.24);
    border-color: rgba(245, 158, 11, 0.85);
}

.position-navy {
    background: rgba(30, 58, 138, 0.24);
    border-color: rgba(30, 58, 138, 0.85);
}

.position-charcoal {
    background: rgba(22, 101, 52, 0.24);
    border-color: rgba(22, 101, 52, 0.85);
}

.position-header {
    padding: 24px 26px;
    background: rgba(245, 158, 11, 0.35);
    border-bottom: 1px solid rgba(245, 158, 11, 0.4);
    color: #111827;
}

.position-gold .position-header {
    background: rgba(245, 158, 11, 0.35);
    border-bottom: 1px solid rgba(245, 158, 11, 0.4);
}

.position-navy .position-header {
    background: rgba(30, 58, 138, 0.35);
    border-bottom: 1px solid rgba(30, 58, 138, 0.4);
}

.position-charcoal .position-header {
    background: rgba(22, 101, 52, 0.35);
    border-bottom: 1px solid rgba(22, 101, 52, 0.4);
}

.dark-theme .position-section {
    background: rgba(30, 43, 79, 0.96);
    border-color: rgba(204, 130, 2, 0.85);
}

.dark-theme .position-gold {
    background: rgba(255, 178, 46, 0.82);
    border-color: rgba(245, 158, 11, 0.9);
}

.dark-theme .position-navy {
    background: rgba(30, 58, 138, 0.3);
    border-color: rgba(30, 58, 138, 0.95);
}

.dark-theme .position-charcoal {
    background: rgba(22, 101, 52, 0.3);
    border-color: rgba(22, 101, 52, 0.95);
}

.dark-theme .position-header {
    background: rgba(14, 27, 60, 0.96);
    border-bottom: 1px solid rgba(245, 158, 11, 0.3);
    color: #f8fafc;
}

.dark-theme .position-gold .position-header {
    background: rgba(245, 158, 11, 0.24);
    border-bottom: 1px solid rgba(245, 158, 11, 0.32);
}

.dark-theme .position-navy .position-header {
    background: rgba(30, 58, 138, 0.24);
    border-bottom: 1px solid rgba(30, 58, 138, 0.32);
}

.dark-theme .position-charcoal .position-header {
    background: rgba(55, 65, 81, 0.24);
    border-bottom: 1px solid rgba(55, 65, 81, 0.32);
}

.dark-theme .position-title {
    color: #f8fafc;
    text-shadow: 0 2px 5px rgba(0, 0, 0, 0.45);
}

.position-body {
    padding: 32px;
}

.position-title{
    text-align:left;
    font-size:2.05rem;
    font-weight:800;
    margin-bottom:0;
    color: #111827;
    text-shadow: none;
}

.candidate-card{
    background: rgba(255,255,255,0.92);
    border: 1px solid rgba(0, 0, 0, 0.85);
    border-radius: 34px;
    overflow:hidden;
    height:100%;
    transition: transform .35s cubic-bezier(0.22, 1, 0.36, 1), box-shadow .35s ease, border-color .35s ease;
    box-shadow: 0 24px 55px rgba(15, 23, 42, 0.08);
}

.dark-theme .candidate-card {
    background: rgba(18, 29, 45, 0.92);
    border: 1px solid rgba(255, 255, 255, 0.85);
}

.candidate-card:hover{
    transform: translateY(-8px);
    box-shadow: 0 34px 82px rgba(15, 23, 42, 0.16);
    border-color: rgba(37, 99, 235, 0.45);
}

.candidate-photo{
    width:100%;
    height:auto;
    aspect-ratio: 1 / 1;
    object-fit:cover;
    image-rendering: -webkit-optimize-contrast;
    display: block;
    border-bottom: 1px solid rgba(96, 165, 250, 0.12);
}

.dark-theme .candidate-photo {
    border-bottom-color: rgba(96, 165, 250, 0.18);
}

.candidate-placeholder{
    width: 100%;
    height: auto;
    aspect-ratio: 1 / 1;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:80px;
    font-weight:900;
    background: linear-gradient(135deg, rgba(96, 165, 250, 0.16), rgba(59, 130, 246, 0.08));
    color: #1e40af;
}

.candidate-body{
    padding: 28px 26px 30px;
}

.candidate-name{
    font-size:1.5rem;
    font-weight:800;
    margin-bottom:10px;
    letter-spacing: 0.02em;
}

.candidate-divider {
    height: 1px;
    background: rgba(148, 163, 184, 0.18);
    margin: 16px 0 18px;
    border-radius: 999px;
}

.dark-theme .candidate-divider {
    background: rgba(148, 163, 184, 0.28);
}

.candidate-education {
    margin-bottom: 16px;
    font-size: 0.98rem;
    line-height: 1.6;
    color: var(--text);
}

.candidate-education-label {
    font-weight: 700;
    letter-spacing: 0.01em;
    margin-bottom: 6px;
    display: block;
}

.candidate-education-value {
    color: var(--muted);
    font-size: 0.96rem;
}

.candidate-description{
    color:var(--muted);
    font-size:1rem;
    line-height:1.75;
    margin-bottom:0;
}


.notice-box{
    background:var(--notice-bg);
    border:1px solid var(--notice-border);
    border-radius:15px;
    padding:15px 20px;
    margin-bottom:35px;
}

.notice-title{
    font-size:1.25rem;
    font-weight:700;
    color:var(--primary);
}

.login-btn{
    padding:18px 40px;
    font-size:1.2rem;
    font-weight:700;
    border-radius:14px;
}

.footer-area{
    text-align:center;
    margin-top:50px;
    margin-bottom:60px;
}

@media(max-width:768px){
    .position-title{
        font-size:1.6rem;
    }

    .candidate-name{
        font-size:1.25rem;
    }
    
    .page-title{
        font-size:1.6rem;
    }
}

</style>
</head>
<body>

<div class="page-header">

    <div class="container">

        <h1 class="page-title">
            PMPC Election Candidates
        </h1>
        
        <p class="page-subtitle">
            Review all candidates before proceeding to vote.
        </p>
        
        <div style="display:flex; justify-content:space-between; align-items:center; gap:12px; margin-top:15px;">
            <div style="text-align:left;">
                <a href="../index.php" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">← Back to Home</a>
            </div>
            <div style="text-align:right;">
                <button id="themeToggleBtn" class="btn btn-sm btn-outline-secondary" style="border-radius:8px;">🌙 Dark Mode</button>
            </div>
        </div>

    </div>

</div>

<div class="container">

    <div class="notice-box">

        <div class="notice-title">
            Important Notice
        </div>

        <p class="mb-0 mt-1" style="font-size: 0.95rem; color: var(--text); opacity: 0.9;">
            Please take time to review each candidate carefully.
            After reviewing, click the button below to proceed to the voter login page.
        </p>

    </div>

<?php while($position = $positions->fetch_assoc()){ ?>

<?php

$position_id = $position['id'];

$candidates = $conn->query("
    SELECT *
    FROM candidates
    WHERE position_id = $position_id
    ORDER BY fullname
");

?>

<?php
    $sectionClass = '';
    $positionLabel = strtolower(trim($position['position_name']));
    if(strpos($positionLabel, 'board') !== false || strpos($positionLabel, 'director') !== false) {
        $sectionClass = 'position-gold';
    } elseif(strpos($positionLabel, 'audit') !== false) {
        $sectionClass = 'position-navy';
    } elseif(strpos($positionLabel, 'election') !== false) {
        $sectionClass = 'position-charcoal';
    }
?>

<div class="position-section <?= $sectionClass ?>">

    <div class="position-header">
        <h2 class="position-title">
            <?php echo htmlspecialchars($position['position_name']); ?>
        </h2>
    </div>

    <div class="position-body">
        <div class="row g-4">

        <?php while($candidate = $candidates->fetch_assoc()){ ?>

    <?php

    $imageFilename = '';

    if(!empty($candidate['photo'])){
        $imageFilename = $candidate['photo'];
    }
    elseif(!empty($candidate['picture'])){
        $imageFilename = $candidate['picture'];
    }
    elseif(!empty($candidate['image'])){
        $imageFilename = $candidate['image'];
    }

    $imagePath = '';
    if(!empty($imageFilename)){
        $imagePath = "../assets/images/" . $imageFilename;
    }

    $initials = "NA";

    if(!empty($candidate['fullname'])){

        $parts = explode(' ', $candidate['fullname']);

        $initials = strtoupper(substr($parts[0],0,1));

        if(count($parts) > 1){
            $initials .= strtoupper(
                substr($parts[count($parts)-1],0,1)
            );
        }
    }

    ?>

    <div class="col-lg-4 col-md-6">

        <div class="candidate-card">

            <?php if(!empty($imagePath) && file_exists($imagePath)){ ?>

                <img
                    src="<?php echo htmlspecialchars($imagePath); ?>"
                    class="candidate-photo"
                    alt="Candidate"
                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                >

                <div class="candidate-placeholder" style="display:none;">
                    <?php echo $initials; ?>
                </div>

            <?php } else { ?>

                <div class="candidate-placeholder">
                    <?php echo $initials; ?>
                </div>

            <?php } ?>

            <div class="candidate-body">

                <div class="candidate-name">
                    <?php echo htmlspecialchars($candidate['fullname']); ?>
                </div>

                <?php if(!empty($candidate['education'])){ ?>
                    <div class="candidate-education">
                        <span class="candidate-education-label">Education</span>
                        <span class="candidate-education-value"><?= htmlspecialchars($candidate['education']); ?></span>
                    </div>
                    <div class="candidate-divider"></div>
                <?php } ?>

                <?php if(!empty($candidate['description'])){ ?>

                    <?php
                        $lines = preg_split('/\r\n|\n|\r/', trim($candidate['description']));
                        $current = 'accomplishments';
                        $accomplishments = [];
                        $platforms = [];

                        foreach($lines as $line){
                            $text = trim($line);
                            if($text === '') continue;
                            if(preg_match('/^accomplishments\s*[:]?$/i', $text)){
                                $current = 'accomplishments';
                                continue;
                            }
                            if(preg_match('/^platforms\s*[:]?$/i', $text)){
                                $current = 'platforms';
                                continue;
                            }
                            $text = preg_replace('/^[\-\*•]\s*/u', '', $text);
                            if($current === 'platforms'){
                                $platforms[] = $text;
                            } else {
                                $accomplishments[] = $text;
                            }
                        }
                    ?>

                    <div class="candidate-description">
                        <?php if(!empty($accomplishments)){ ?>
                            <strong>Accomplishments</strong>
                            <ul class="mb-2">
                                <?php foreach($accomplishments as $item){ ?>
                                    <li><?= htmlspecialchars($item); ?></li>
                                <?php } ?>
                            </ul>
                        <?php } ?>

                        <?php if(!empty($platforms)){ ?>
                            <strong>Platforms</strong>
                            <ul class="mb-0">
                                <?php foreach($platforms as $item){ ?>
                                    <li><?= htmlspecialchars($item); ?></li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                    </div>

                <?php } else { ?>

                    <div class="candidate-description">
                        No candidate profile available.
                    </div>

                <?php } ?>

            </div>

        </div>

    </div>

    <?php } ?>

        </div>
    </div>

</div>

<?php } ?>

<div class="footer-area">

    <a
        href="login.php"
        class="btn btn-success login-btn"
    >
        Proceed to Voter Login
    </a>

</div>

</div>

</body>
<script>
function toggleTheme(){
    document.body.classList.toggle('dark-theme');
    const btn = document.getElementById('themeToggleBtn');
    if(document.body.classList.contains('dark-theme')){
        btn.innerText = '☀️ Light Mode';
        localStorage.setItem('pmpc-theme','dark');
    } else {
        btn.innerText = '🌙 Dark Mode';
        localStorage.setItem('pmpc-theme','light');
    }
}

document.getElementById('themeToggleBtn').addEventListener('click', toggleTheme);

window.onload = function(){
    const stored = localStorage.getItem('pmpc-theme');
    if(stored === 'dark'){
        document.body.classList.add('dark-theme');
        document.getElementById('themeToggleBtn').innerText = '☀️ Light Mode';
    } else {
        document.getElementById('themeToggleBtn').innerText = '🌙 Dark Mode';
    }
}
</script>
</html>