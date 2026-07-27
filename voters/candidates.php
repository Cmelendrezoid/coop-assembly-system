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
    margin-bottom:60px;
}

.position-title{
    text-align:center;
    font-size:2rem;
    font-weight:700;
    margin-bottom:25px;
    color:var(--primary);
}

.candidate-card{
    background:var(--card);
    border:1px solid var(--border);
    border-radius:20px;
    overflow:hidden;
    height:100%;
    transition:.3s;
    box-shadow:0 5px 20px rgba(0,0,0,.06);
}

.candidate-card:hover{
    transform:translateY(-4px);
}

.candidate-photo{
    width:100%;
    height: auto;
    aspect-ratio: 1 / 1;
    object-fit:cover;
    image-rendering: -webkit-optimize-contrast;
    display: block;
}

.candidate-placeholder{
    width: 100%;
    height: auto;
    aspect-ratio: 1 / 1;
    display:flex;
    align-items:center;
    justify-content:center;
    font-size:80px;
    font-weight:bold;
    background:var(--placeholder-bg);
    color:var(--muted);
}

.candidate-body{
    padding:20px;
}

.candidate-name{
    font-size:1.4rem;
    font-weight:700;
    margin-bottom:10px;
}

.candidate-description{
    color:var(--muted);
    font-size:1rem;
    line-height:1.6;
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
    ORDER BY full_name
");

?>

<div class="position-section">

    <h2 class="position-title">
        <?php echo htmlspecialchars($position['position_name']); ?>
    </h2>

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

    if(!empty($candidate['full_name'])){

        $parts = explode(' ', $candidate['full_name']);

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
                    <?php echo htmlspecialchars($candidate['full_name']); ?>
                </div>

                <?php if(!empty($candidate['description'])){ ?>

                    <div class="candidate-description">
                        <?php echo nl2br(htmlspecialchars($candidate['description'])); ?>
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