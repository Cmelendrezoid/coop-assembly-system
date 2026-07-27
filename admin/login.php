<?php
// 1. Set session cookie lifetime (e.g., 8 hours = 28800 seconds)
ini_set('session.cookie_lifetime', 28800);

// 2. Set the maximum lifetime of the session data on the server (8 hours)
ini_set('session.gc_maxlifetime', 28800);

// 3. Set the custom session folder path (Must match dashboard.php exactly)
if (!file_exists(__DIR__ . '/../sessions')) {
    mkdir(__DIR__ . '/../sessions', 0700, true);
}
ini_set('session.save_path', __DIR__ . '/../sessions');

session_start();
include '../config/db.php';

$error = "";

if(isset($_POST['login'])){

    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $conn->prepare(
        "SELECT * FROM users
         WHERE username=? AND role='admin'"
    );

    $stmt->bind_param("s",$username);
    $stmt->execute();

    $result = $stmt->get_result();

    if($result->num_rows > 0){

        $user = $result->fetch_assoc();

        if(password_verify($password, $user['password'])){

            $_SESSION['admin_id'] = $user['user_id'];
            $_SESSION['admin_username'] = $user['username'];

            header("Location: dashboard.php");
            exit();

        }else{
            $error = "Invalid password.";
        }

    }else{
        $error = "Admin account not found.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>PMPC Admin Portal</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

:root{
    --bg:#f1f5f9;
    --card:#ffffff;
    --text:#1e293b;
    --secondary:#64748b;
    --border:#e2e8f0;
}

.dark-theme{
    --bg:#020617;
    --card:#162338;
    --text:#f8fafc;
    --secondary:#94a3b8;
    --border:#334155;
}

body{
    min-height:100vh;
    background:
        radial-gradient(circle at top,
        #0f172a 0%,
        #020617 100%);
    font-family:'Segoe UI',sans-serif;
}

.portal-container{
    min-height:100vh;
    display:flex;
    justify-content:center;
    align-items:center;
    padding:30px;
}

.login-card{
    width:100%;
    max-width:500px;
    background:rgba(22,35,56,.95);
    border:1px solid rgba(255,255,255,.08);
    border-radius:24px;
    backdrop-filter:blur(20px);
    overflow:hidden;
}

.logo{
    width:110px;
    height:110px;
    object-fit:contain;
}

.portal-title{
    color:white;
    font-weight:700;
    font-size:2rem;
}

.portal-subtitle{
    color:#94a3b8;
}

.form-label{
    color:#cbd5e1;
    font-weight:500;
}

.form-control{
    background:#0f172a;
    border:1px solid #334155;
    color:white;
    padding:12px;
}

.form-control:focus{
    background:#0f172a;
    color:white;
    border-color:#3b82f6;
    box-shadow:none;
}

.btn-login{
    background:#2563eb;
    border:none;
    padding:12px;
    font-weight:600;
    border-radius:12px;
}

.btn-login:hover{
    background:#1d4ed8;
}

.btn-back{
    position:fixed;
    top:25px;
    left:25px;
    border:none;
    background:white;
    color:#1e293b;
    padding:10px 18px;
    border-radius:30px;
    font-weight:600;
    text-decoration:none;
}

.theme-btn{
    position:fixed;
    top:25px;
    right:25px;
    border:none;
    background:white;
    color:#1e293b;
    padding:10px 18px;
    border-radius:30px;
    font-weight:600;
}

.dark-theme .theme-btn{
    background:#334155;
    color:white;
}

.dark-theme .btn-back{
    background:#334155;
    color:white;
}

.footer{
    color:#94a3b8;
    font-size:.9rem;
}

</style>

</head>
<body>

<a href="../index.php" class="btn-back">
← Back
</a>

<button class="theme-btn" onclick="toggleTheme()">
    <span id="themeText">🌙 Dark Mode</span>
</button>

<div class="portal-container">

<div class="card login-card shadow-lg">

<div class="card-body p-5 text-center">

<img
    src="../assets/images/logo.png"
    class="logo mb-4"
    alt="PMPC Logo"
    onerror="this.style.display='none';"
>

<h2 class="portal-title">
    Admin Portal
</h2>

<p class="portal-subtitle mb-4">
    PMPC E-Voting Management System
</p>

<?php if(!empty($error)){ ?>

<div class="alert alert-danger text-start">
    <?php echo $error; ?>
</div>

<?php } ?>

<form method="POST">

<div class="mb-3 text-start">

<label class="form-label">
Username
</label>

<input
    type="text"
    name="username"
    class="form-control"
    required>

</div>

<div class="mb-4 text-start">

<label class="form-label">
Password
</label>

<input
    type="password"
    name="password"
    class="form-control"
    required>

</div>

<button
    type="submit"
    name="login"
    class="btn btn-primary btn-login w-100">

    Login to Admin Panel

</button>

</form>

<hr class="my-4 border-secondary">

<div class="footer">
    Panabo Multipurpose Cooperative<br>
    Election Management System
</div>

</div>

</div>

</div>

<script>

function toggleTheme(){

    document.body.classList.toggle('dark-theme');

    if(document.body.classList.contains('dark-theme')){

        localStorage.setItem('admin-theme','dark');
        document.getElementById('themeText').innerHTML =
            '☀️ Light Mode';

    }else{

        localStorage.setItem('admin-theme','light');
        document.getElementById('themeText').innerHTML =
            '🌙 Dark Mode';

    }
}

window.onload = function(){

    if(localStorage.getItem('admin-theme') === 'dark'){

        document.body.classList.add('dark-theme');

        document.getElementById('themeText').innerHTML =
            '☀️ Light Mode';
    }
}

</script>

</body>
</html>