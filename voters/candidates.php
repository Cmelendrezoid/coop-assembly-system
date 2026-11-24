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
$candidate_order_column = $conn->query("SHOW COLUMNS FROM candidates LIKE 'candidate_order'");
$candidate_order_clause = ($candidate_order_column && $candidate_order_column->num_rows > 0)
    ? 'candidate_order ASC, '
    : '';

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>Panabo Co-op Election Candidates</title>

<!-- Google Fonts: Plus Jakarta Sans -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- Bootstrap 5 & Bootstrap Icons -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">


<style>
:root {
    /* Royal Blue & Glass Palette */
    --royal-blue: #2563EB;
    --royal-hover: #1D4ED8;
    --gold-light: #93C5FD;
    --gold-dark: #1E40AF;
    --emerald-light: #60A5FA;

    /* Solid Background & Glass */
    --bg-mesh: #EFF4FC;
    --glass-bg: rgba(255, 255, 255, 0.78);
    --glass-card: rgba(255, 255, 255, 0.88);
    --glass-border: rgba(147, 197, 253, 0.65);
    --card-shadow: 0 12px 30px -10px rgba(30, 64, 175, 0.10),
                   0 0 15px rgba(255, 255, 255, 0.5) inset;

    --text-primary: #102A56;
    --text-secondary: #526987;
    --title-gradient: linear-gradient(135deg, #0F2F68 0%, #2563EB 50%, #60A5FA 100%);
    --notice-bg: rgba(219, 234, 254, 0.75);
    --notice-border: rgba(96, 165, 250, 0.6);

    /* Section Colors Light */
    --sec-gold-bg: rgba(219, 234, 254, 0.35);
    --sec-gold-border: rgba(96, 165, 250, 0.65);
    --sec-gold-hdr: linear-gradient(135deg, rgba(191, 219, 254, 0.65), rgba(219, 234, 254, 0.35));

    --sec-navy-bg: rgba(59, 130, 246, 0.06);
    --sec-navy-border: rgba(96, 165, 250, 0.55);
    --sec-navy-hdr: linear-gradient(135deg, rgba(219, 234, 254, 0.75), rgba(191, 219, 254, 0.35));

    --sec-charcoal-bg: rgba(37, 99, 235, 0.05);
    --sec-charcoal-border: rgba(96, 165, 250, 0.55);
    --sec-charcoal-hdr: linear-gradient(135deg, rgba(219, 234, 254, 0.65), rgba(147, 197, 253, 0.25));
}

.dark-theme {
    --bg-mesh: #07152D;
    --glass-bg: rgba(11, 30, 59, 0.88);
    --glass-card: rgba(17, 40, 75, 0.85);
    --glass-border: rgba(96, 165, 250, 0.35);
    --card-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.45),
                   0 0 15px rgba(59, 130, 246, 0.08) inset;

    --text-primary: #F1F7FF;
    --text-secondary: #A8BEDD;
    --title-gradient: linear-gradient(135deg, #93C5FD 0%, #60A5FA 50%, #BFDBFE 100%);
    --notice-bg: rgba(17, 40, 75, 0.85);
    --notice-border: rgba(96, 165, 250, 0.4);

    /* Section Colors Dark */
    --sec-gold-bg: rgba(30, 64, 175, 0.16);
    --sec-gold-border: rgba(96, 165, 250, 0.45);
    --sec-gold-hdr: linear-gradient(135deg, rgba(30, 64, 175, 0.65), rgba(37, 99, 235, 0.3));

    --sec-navy-bg: rgba(30, 58, 138, 0.22);
    --sec-navy-border: rgba(96, 165, 250, 0.45);
    --sec-navy-hdr: linear-gradient(135deg, rgba(30, 64, 175, 0.7), rgba(29, 78, 216, 0.3));

    --sec-charcoal-bg: rgba(30, 64, 175, 0.15);
    --sec-charcoal-border: rgba(96, 165, 250, 0.4);
    --sec-charcoal-hdr: linear-gradient(135deg, rgba(30, 64, 175, 0.6), rgba(37, 99, 235, 0.25));
}

* {
    box-sizing: border-box;
    transition: background-color 0.3s ease,
                border-color 0.3s ease,
                box-shadow 0.3s ease,
                transform 0.2s ease;
}

body {
    margin: 0;
    min-height: 100vh;
    background-color: var(--bg-mesh);
    background-image: none;
    color: var(--text-primary);
    font-family: 'Plus Jakarta Sans', sans-serif;
    padding-bottom: 3rem;
}

/* Background Ambient Orbs */
.orb {
    position: fixed;
    border-radius: 50%;
    filter: blur(90px);
    z-index: -1;
    opacity: 0.20;
    animation: floatOrb 12s ease-in-out infinite alternate;
}

.orb-1 {
    width: 350px;
    height: 350px;
    background: var(--royal-blue);
    top: -10%;
    left: -5%;
}

.orb-2 {
    width: 300px;
    height: 300px;
    background: var(--gold-dark);
    bottom: -10%;
    right: -5%;
    animation-delay: -6s;
}

@keyframes floatOrb {
    0% { transform: translateY(0) scale(1); }
    100% { transform: translateY(-30px) scale(1.05); }
}

/* Compact Page Header */
.page-header {
    background: var(--glass-bg);
    backdrop-filter: blur(25px) saturate(160%);
    -webkit-backdrop-filter: blur(25px) saturate(160%);
    border-bottom: 1px solid var(--glass-border);
    padding: 0.65rem 0;
    margin-bottom: 1.25rem;
    position: sticky;
    top: 0;
    z-index: 1000;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
}

.page-title {
    font-size: 1.25rem;
    font-weight: 800;
    margin: 0;
    background: var(--title-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    letter-spacing: -0.02em;
    line-height: 1.2;
}

.page-subtitle {
    color: var(--text-secondary);
    font-size: 0.8rem;
    font-weight: 600;
    margin: 0;
    line-height: 1.1;
}

/* Glass Buttons */
.btn-glass {
    background: var(--glass-card);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    color: var(--text-primary);
    font-weight: 700;
    font-size: 0.82rem;
    padding: 0.45rem 0.9rem;
    border-radius: 10px;
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    text-decoration: none;
    box-shadow: 0 2px 8px rgba(0,0,0,0.04);
    white-space: nowrap;
}

.btn-glass:hover {
    transform: translateY(-1px);
    border-color: var(--royal-blue);
    color: var(--royal-blue);
}

.dark-theme .btn-glass:hover {
    border-color: #93C5FD;
    color: #93C5FD;
}

/* Compact Notice Box */
.notice-box {
    background: var(--notice-bg);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--notice-border);
    border-radius: 16px;
    padding: 0.75rem 1.25rem;
    margin-bottom: 1.5rem;
    box-shadow: var(--card-shadow);
    display: flex;
    align-items: center;
    gap: 0.85rem;
}

.notice-icon {
    font-size: 1.35rem;
    color: var(--royal-blue);
    line-height: 1;
}

.dark-theme .notice-icon {
    color: #93C5FD;
}

.notice-title {
    font-size: 0.95rem;
    font-weight: 800;
    color: var(--text-primary);
    margin: 0;
}

/* Position Section Container */
.position-section {
    margin-bottom: 2.5rem;
    background: var(--glass-bg);
    backdrop-filter: blur(25px) saturate(150%);
    -webkit-backdrop-filter: blur(25px) saturate(150%);
    border: 1px solid var(--glass-border);
    border-radius: 22px;
    box-shadow: var(--card-shadow);
    overflow: hidden;
}

.position-gold,
.position-navy,
.position-charcoal {
    background: var(--glass-bg);
    border-color: var(--glass-border);
}

.position-header {
    padding: 0.85rem 1.5rem;
    border-bottom: 1px solid rgba(147, 197, 253, 0.65);
    display: flex;
    align-items: center;
    justify-content: space-between;

    /* Dark Blue to Blue Gradient */
    background: linear-gradient(120deg, #0B1F4B 0%, #173F8A 50%, #2563EB 100%);
}

.position-gold .position-header,
.position-navy .position-header,
.position-charcoal .position-header {
    background: linear-gradient(120deg, #0B1F4B 0%, #173F8A 50%, #2563EB 100%);
}

.position-title {
    text-align: left;
    font-size: 1.25rem;
    font-weight: 800;
    margin: 0;
    color: #FFFFFF;
    letter-spacing: -0.02em;
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

.position-body {
    padding: 1.25rem;
}

/* Candidate Cards */
.candidate-card {
    background: var(--glass-card);
    backdrop-filter: blur(20px);
    -webkit-backdrop-filter: blur(20px);
    border: 1px solid var(--glass-border);
    border-radius: 18px;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
    box-shadow: 0 8px 20px rgba(30, 64, 175, 0.07);
}

.candidate-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 32px rgba(37, 99, 235, 0.16);
    border-color: #60A5FA;
}

.dark-theme .candidate-card:hover {
    box-shadow: 0 16px 32px rgba(0, 0, 0, 0.55);
    border-color: #93C5FD;
}

.candidate-photo-wrapper {
    position: relative;
    width: 100%;
    aspect-ratio: 1 / 1;
    overflow: hidden;
    background: rgba(96, 165, 250, 0.12);
}

.candidate-photo {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
    transition: transform 0.4s ease;
}

.candidate-card:hover .candidate-photo {
    transform: scale(1.03);
}

.candidate-placeholder {
    width: 100%;
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 3.5rem;
    font-weight: 800;
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.15), rgba(147, 197, 253, 0.2));
    color: #1D4ED8;
    letter-spacing: -0.05em;
}

.dark-theme .candidate-placeholder {
    background: linear-gradient(135deg, rgba(37, 99, 235, 0.3), rgba(96, 165, 250, 0.12));
    color: #BFDBFE;
}

.candidate-body {
    padding: 1.15rem;
    display: flex;
    flex-direction: column;
    flex-grow: 1;
}

.candidate-name {
    font-size: 1.15rem;
    font-weight: 800;
    margin-bottom: 0.6rem;
    letter-spacing: -0.02em;
    color: var(--text-primary);
    line-height: 1.25;
}

.candidate-education {
    font-size: 0.82rem;
    color: var(--text-secondary);
    background: rgba(96, 165, 250, 0.07);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    padding: 0.5rem 0.75rem;
    margin-bottom: 0.75rem;
}

.dark-theme .candidate-education {
    background: rgba(96, 165, 250, 0.08);
}

.candidate-education-label {
    font-weight: 700;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--royal-blue);
    display: flex;
    align-items: center;
    gap: 0.3rem;
    margin-bottom: 0.15rem;
}

.dark-theme .candidate-education-label {
    color: #93C5FD;
}

.candidate-education-value {
    color: var(--text-primary);
    font-weight: 600;
}

.candidate-description {
    color: var(--text-secondary);
    font-size: 0.85rem;
    line-height: 1.5;
    flex-grow: 1;
}

.candidate-description strong {
    color: var(--text-primary);
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    display: block;
    margin-top: 0.6rem;
    margin-bottom: 0.25rem;
}

.candidate-description strong:first-child {
    margin-top: 0;
}

.candidate-description ul {
    padding-left: 1rem;
    margin-bottom: 0.35rem;
}

.candidate-description li {
    margin-bottom: 0.25rem;
    font-weight: 500;
}

/* Footer Section */
.footer-area {
    text-align: center;
    margin-top: 2.5rem;
    margin-bottom: 1.5rem;
}

.login-btn {
    padding: 0.85rem 2.5rem;
    font-size: 1.05rem;
    font-weight: 800;
    border-radius: 14px;
    background: linear-gradient(135deg, #1D4ED8, #2563EB);
    border: 1px solid #60A5FA;
    color: white;
    box-shadow: 0 10px 25px rgba(37, 99, 235, 0.25);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
}

.login-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(37, 99, 235, 0.35);
    background: linear-gradient(135deg, #1E40AF, #1D4ED8);
    color: white;
}

/* Responsive */
@media(max-width: 576px) {
    .page-title { font-size: 1.05rem; }
    .position-title { font-size: 1.1rem; }
    .candidate-name { font-size: 1.05rem; }
    .position-body { padding: 1rem; }
    .login-btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

</head>
<body>

<!-- Visual Ambient Background Elements -->
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<!-- Compact Header Bar -->
<div class="page-header">
    <div class="container d-flex align-items-center justify-content-between gap-2">
        <div class="d-flex align-items-center gap-2 me-2">
            <a href="../index.php" class="btn-glass">
                <i class="bi bi-arrow-left"></i> <span class="d-none d-sm-inline">Back</span>
            </a>
            <div>
                <h1 class="page-title">Panabo Co-op Election Candidates</h1>
                <p class="page-subtitle d-none d-md-block">Review candidates carefully before voting</p>
            </div>
        </div>
        <div>
            <button id="themeToggleBtn" class="btn-glass">
                <i class="bi bi-moon-stars-fill"></i> <span class="d-none d-sm-inline">Dark Mode</span>
            </button>
        </div>
    </div>
</div>

<div class="container">

    <!-- Compact Notice Banner -->
    <div class="notice-box">
        <i class="bi bi-info-circle-fill notice-icon"></i>
        <div class="d-flex flex-column flex-sm-row align-items-sm-center gap-1 gap-sm-2">
            <span class="notice-title me-1">Notice:</span>
            <span class="text-secondary fw-semibold" style="font-size: 0.85rem;">
                Review all candidates below. When ready, proceed to voter login at the bottom.
            </span>
        </div>
    </div>

<?php while($position = $positions->fetch_assoc()){ ?>

<?php

$position_id = $position['id'];

$candidates = $conn->query("
    SELECT *
    FROM candidates
    WHERE position_id = $position_id
    ORDER BY {$candidate_order_clause}fullname ASC, id ASC
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
            <i class="bi bi-award-fill opacity-75"></i>
            <?php echo htmlspecialchars($position['position_name']); ?>
        </h2>
    </div>

    <div class="position-body">
        <div class="row g-3 g-md-4">

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
        $firstNameInitial = trim($candidate['candidate_first_name'] ?? '');
        $lastNameInitial = trim($candidate['candidate_last_name'] ?? '');

        if($firstNameInitial !== '' && $lastNameInitial !== ''){
            $initials = strtoupper(substr($firstNameInitial, 0, 1) . substr($lastNameInitial, 0, 1));
        } else {
            $parts = explode(' ', $candidate['fullname']);
            $initials = strtoupper(substr($parts[0], 0, 1));

            if(count($parts) > 1){
                $initials .= strtoupper(substr($parts[count($parts)-1], 0, 1));
            }
        }
    }

    ?>

    <div class="col-lg-4 col-md-6">

        <div class="candidate-card">

            <div class="candidate-photo-wrapper">
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
            </div>

            <div class="candidate-body">

                <div class="candidate-name">
                    <?php echo htmlspecialchars($candidate['fullname']); ?>
                </div>

                <?php if(!empty($candidate['education'])){ ?>
                    <div class="candidate-education">
                        <span class="candidate-education-label">
                            <i class="bi bi-mortarboard-fill"></i> Education
                        </span>
                        <span class="candidate-education-value"><?= htmlspecialchars($candidate['education']); ?></span>
                    </div>
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
                            <strong><i class="bi bi-trophy-fill me-1 text-warning"></i> Work Experience</strong>
                            <ul>
                                <?php foreach($accomplishments as $item){ ?>
                                    <li><?= htmlspecialchars($item); ?></li>
                                <?php } ?>
                            </ul>
                        <?php } ?>

                        <?php if(!empty($platforms)){ ?>
                            <strong><i class="bi bi-megaphone-fill me-1 text-primary"></i> Platforms</strong>
                            <ul>
                                <?php foreach($platforms as $item){ ?>
                                    <li><?= htmlspecialchars($item); ?></li>
                                <?php } ?>
                            </ul>
                        <?php } ?>
                    </div>

                <?php } else { ?>

                    <div class="candidate-description opacity-50 fstyle-italic fw-semibold">
                        No candidate profile description available.
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
    <a href="login.php" class="login-btn">
        Proceed to Voter Login <i class="bi bi-arrow-right-circle-fill"></i>
    </a>
</div>

</div>

<script>
function toggleTheme(){
    document.body.classList.toggle('dark-theme');
    const btn = document.getElementById('themeToggleBtn');
    if(document.body.classList.contains('dark-theme')){
        btn.innerHTML = '<i class="bi bi-sun-fill"></i> <span class="d-none d-sm-inline">Light Mode</span>';
        localStorage.setItem('pmpc-theme','dark');
    } else {
        btn.innerHTML = '<i class="bi bi-moon-stars-fill"></i> <span class="d-none d-sm-inline">Dark Mode</span>';
        localStorage.setItem('pmpc-theme','light');
    }
}

document.getElementById('themeToggleBtn').addEventListener('click', toggleTheme);

window.onload = function(){
    const stored = localStorage.getItem('pmpc-theme');
    const btn = document.getElementById('themeToggleBtn');
    if(stored === 'dark'){
        document.body.classList.add('dark-theme');
        btn.innerHTML = '<i class="bi bi-sun-fill"></i> <span class="d-none d-sm-inline">Light Mode</span>';
    } else {
        btn.innerHTML = '<i class="bi bi-moon-stars-fill"></i> <span class="d-none d-sm-inline">Dark Mode</span>';
    }
}
</script>
</body>
</html>