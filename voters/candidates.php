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

<title>PMPC Election Candidates</title>

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
    --royal-blue: #4169E1;
    --royal-hover: #3152c7;
    --gold-light: #FDE047;
    --gold-dark: #CA8A04;
    --emerald-light: #34D399;
    
    /* Dynamic Mesh Gradient & Glass */
    --bg-mesh: radial-gradient(at 0% 0%, rgba(65, 105, 225, 0.08) 0px, transparent 50%),
               radial-gradient(at 100% 0%, rgba(34, 211, 238, 0.08) 0px, transparent 50%),
               radial-gradient(at 100% 100%, rgba(253, 224, 71, 0.06) 0px, transparent 50%),
               #f8fafc;
    --glass-bg: rgba(255, 255, 255, 0.75);
    --glass-card: rgba(255, 255, 255, 0.88);
    --glass-border: rgba(255, 255, 255, 0.9);
    --card-shadow: 0 15px 30px -10px rgba(65, 105, 225, 0.06), 0 0 15px rgba(255, 255, 255, 0.6) inset;
    
    --text-primary: #0f172a;
    --text-secondary: #475569;
    --title-gradient: linear-gradient(135deg, #1e3a8a 0%, #4169E1 50%, #2563eb 100%);
    --notice-bg: rgba(239, 246, 255, 0.85);
    --notice-border: rgba(191, 219, 254, 0.9);
    
    /* Section Colors Light */
    --sec-gold-bg: rgba(253, 224, 71, 0.12);
    --sec-gold-border: rgba(202, 138, 4, 0.3);
    --sec-gold-hdr: linear-gradient(135deg, rgba(254, 240, 138, 0.5), rgba(253, 224, 71, 0.2));

    --sec-navy-bg: rgba(65, 105, 225, 0.06);
    --sec-navy-border: rgba(65, 105, 225, 0.25);
    --sec-navy-hdr: linear-gradient(135deg, rgba(219, 234, 254, 0.6), rgba(191, 219, 254, 0.2));

    --sec-charcoal-bg: rgba(16, 185, 129, 0.06);
    --sec-charcoal-border: rgba(16, 185, 129, 0.25);
    --sec-charcoal-hdr: linear-gradient(135deg, rgba(209, 250, 229, 0.6), rgba(167, 243, 208, 0.2));
}

.dark-theme {
    --bg-mesh: radial-gradient(at 0% 0%, rgba(65, 105, 225, 0.2) 0px, transparent 50%),
               radial-gradient(at 100% 0%, rgba(202, 138, 4, 0.12) 0px, transparent 50%),
               radial-gradient(at 100% 100%, rgba(34, 211, 238, 0.1) 0px, transparent 50%),
               #020617;
    --glass-bg: rgba(15, 23, 42, 0.82);
    --glass-card: rgba(30, 41, 59, 0.65);
    --glass-border: rgba(255, 255, 255, 0.12);
    --card-shadow: 0 20px 40px -10px rgba(0, 0, 0, 0.5), 0 0 15px rgba(65, 105, 225, 0.1) inset;
    
    --text-primary: #f8fafc;
    --text-secondary: #94a3b8;
    --title-gradient: linear-gradient(135deg, #FDE047 0%, #F59E0B 50%, #FDE047 100%);
    --notice-bg: rgba(15, 23, 42, 0.8);
    --notice-border: rgba(65, 105, 225, 0.3);

    /* Section Colors Dark */
    --sec-gold-bg: rgba(202, 138, 4, 0.1);
    --sec-gold-border: rgba(253, 224, 71, 0.35);
    --sec-gold-hdr: linear-gradient(135deg, rgba(202, 138, 4, 0.3), rgba(161, 98, 7, 0.15));

    --sec-navy-bg: rgba(30, 58, 138, 0.18);
    --sec-navy-border: rgba(96, 165, 250, 0.35);
    --sec-navy-hdr: linear-gradient(135deg, rgba(30, 58, 138, 0.4), rgba(23, 37, 84, 0.25));

    --sec-charcoal-bg: rgba(6, 78, 59, 0.18);
    --sec-charcoal-border: rgba(52, 211, 153, 0.35);
    --sec-charcoal-hdr: linear-gradient(135deg, rgba(6, 78, 59, 0.4), rgba(4, 47, 38, 0.25));
}

* {
    box-sizing: border-box;
    transition: background-color 0.3s ease, border-color 0.3s ease, box-shadow 0.3s ease, transform 0.2s ease;
}

body {
    margin: 0;
    min-height: 100vh;
    background-color: #0f172a;
    background-image: var(--bg-mesh);
    background-size: 150% 150%;
    animation: gradientMove 15s ease infinite alternate;
    color: var(--text-primary);
    font-family: 'Plus Jakarta Sans', sans-serif;
    padding-bottom: 3rem;
}

@keyframes gradientMove {
    0% { background-position: 0% 0%; }
    50% { background-position: 100% 100%; }
    100% { background-position: 0% 100%; }
}

/* Background Ambient Orbs */
.orb {
    position: fixed;
    border-radius: 50%;
    filter: blur(90px);
    z-index: -1;
    opacity: 0.35;
    animation: floatOrb 12s ease-in-out infinite alternate;
}
.orb-1 { width: 350px; height: 350px; background: var(--royal-blue); top: -10%; left: -5%; }
.orb-2 { width: 300px; height: 300px; background: var(--gold-dark); bottom: -10%; right: -5%; animation-delay: -6s; }

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
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
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
    box-shadow: 0 2px 8px rgba(0,0,0,0.03);
    white-space: nowrap;
}

.btn-glass:hover {
    transform: translateY(-1px);
    border-color: var(--royal-blue);
    color: var(--royal-blue);
}

.dark-theme .btn-glass:hover {
    border-color: var(--gold-light);
    color: var(--gold-light);
}

/* Compact Notice Box */
.notice-box {
    background: var(--notice-bg);
    backdrop-filter: blur(20px);
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
    color: var(--gold-light);
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

.position-gold {
    background: var(--sec-gold-bg);
    border-color: var(--sec-gold-border);
}

.position-navy {
    background: var(--sec-navy-bg);
    border-color: var(--sec-navy-border);
}

.position-charcoal {
    background: var(--sec-charcoal-bg);
    border-color: var(--sec-charcoal-border);
}

.position-header {
    padding: 0.85rem 1.5rem;
    border-bottom: 1px solid var(--glass-border);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.position-gold .position-header { background: var(--sec-gold-hdr); }
.position-navy .position-header { background: var(--sec-navy-hdr); }
.position-charcoal .position-header { background: var(--sec-charcoal-hdr); }

.position-title {
    text-align: left;
    font-size: 1.25rem;
    font-weight: 800;
    margin: 0;
    color: var(--text-primary);
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
    border: 1px solid var(--glass-border);
    border-radius: 18px;
    overflow: hidden;
    height: 100%;
    display: flex;
    flex-direction: column;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.03);
}

.candidate-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 16px 32px rgba(65, 105, 225, 0.12);
    border-color: rgba(65, 105, 225, 0.4);
}

.dark-theme .candidate-card:hover {
    box-shadow: 0 16px 32px rgba(0, 0, 0, 0.6);
    border-color: rgba(253, 224, 71, 0.4);
}

.candidate-photo-wrapper {
    position: relative;
    width: 100%;
    aspect-ratio: 1 / 1;
    overflow: hidden;
    background: rgba(0, 0, 0, 0.03);
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
    background: linear-gradient(135deg, rgba(65, 105, 225, 0.15), rgba(34, 211, 238, 0.1));
    color: var(--royal-blue);
    letter-spacing: -0.05em;
}

.dark-theme .candidate-placeholder {
    background: linear-gradient(135deg, rgba(253, 224, 71, 0.15), rgba(202, 138, 4, 0.1));
    color: var(--gold-light);
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
    background: rgba(0, 0, 0, 0.025);
    border: 1px solid var(--glass-border);
    border-radius: 10px;
    padding: 0.5rem 0.75rem;
    margin-bottom: 0.75rem;
}

.dark-theme .candidate-education {
    background: rgba(255, 255, 255, 0.03);
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
    color: var(--gold-light);
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
    background: linear-gradient(135deg, #16a34a, #15803d);
    border: none;
    color: white;
    box-shadow: 0 10px 25px rgba(22, 163, 74, 0.25);
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.6rem;
}

.login-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 14px 30px rgba(22, 163, 74, 0.35);
    background: linear-gradient(135deg, #15803d, #16a34a);
    color: white;
}

@media(max-width: 576px) {
    .page-title { font-size: 1.05rem; }
    .position-title { font-size: 1.1rem; }
    .candidate-name { font-size: 1.05rem; }
    .position-body { padding: 1rem; }
    .login-btn { width: 100%; justify-content: center; }
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
                <h1 class="page-title">PMPC Election Candidates</h1>
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
                            <strong><i class="bi bi-trophy-fill me-1 text-warning"></i> Accomplishments</strong>
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