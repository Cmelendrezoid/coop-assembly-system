<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">

<title>PMPC Election Portal</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>

:root{
    --bg:#f8fafc;
    --card:#ffffff;
    --border:#e2e8f0;
    --text:#0f172a;
    --muted:#64748b;
    --success:#16a34a;
    --primary:#2563eb;
    --warning:#d97706;
}

.dark-theme{
    --bg:#0f172a;
    --card:#1e293b;
    --border:#334155;
    --text:#f8fafc;
    --muted:#94a3b8;
}

body{
    margin:0;
    min-height:100vh;
    background:var(--bg);
    color:var(--text);
    font-family:'Segoe UI',sans-serif;
    transition:.3s;
}

.theme-toggle{
    position:fixed;
    top:20px;
    right:20px;
    z-index:1000;
}

.theme-btn{
    border:none;
    border-radius:30px;
    padding:10px 18px;
    background:var(--card);
    color:var(--text);
    box-shadow:0 5px 15px rgba(0,0,0,.15);
    font-weight:600;
}

.hero{
    min-height:100vh;
    display:flex;
    align-items:center;
    justify-content:center;
    padding:30px;
}

.portal-card{
    width:100%;
    max-width:550px;
    background:var(--card);
    border:1px solid var(--border);
    border-radius:25px;
    padding:40px;
    text-align:center;
    box-shadow:0 15px 40px rgba(0,0,0,.08);
}

.logo{
    width:120px;
    height:120px;
    object-fit:contain;
    margin-bottom:25px;
}

.portal-title{
    font-size:2rem;
    font-weight:700;
    margin-bottom:10px;
}

.portal-subtitle{
    color:var(--muted);
    margin-bottom:35px;
    font-size:1rem;
}

.btn-portal{
    width:100%;
    padding:16px;
    font-size:1.05rem;
    font-weight:700;
    border-radius:14px;
    border:none;
    transition:.3s;
}

.btn-portal:hover{
    transform:translateY(-2px);
}

.btn-voter{
    background:linear-gradient(
        135deg,
        #22c55e,
        #16a34a
    );
    color:white;
}

.btn-admin{
    background:linear-gradient(
        135deg,
        #3b82f6,
        #2563eb
    );
    color:white;
}

.btn-register{
    background:linear-gradient(
        135deg,
        #f59e0b,
        #d97706
    );
    color:white;
}

.btn-qr-register{
    background:linear-gradient(
        135deg,
        #f50ba7,
        #7306d9
    );
    color:white;
}

.info-box{
    margin-top:25px;
    padding:15px;
    border-radius:12px;
    background:rgba(59,130,246,.08);
    border:1px solid rgba(59,130,246,.15);
}

.info-box small{
    color:var(--muted);
}

.footer-note{
    margin-top:20px;
    color:var(--muted);
    font-size:.9rem;
}

@media(max-width:576px){

    .portal-card{
        padding:30px 20px;
    }

    .portal-title{
        font-size:1.6rem;
    }

    .logo{
        width:90px;
        height:90px;
    }
}

</style>
</head>
<body>

<div class="theme-toggle">
    <button
        class="theme-btn"
        onclick="toggleTheme()"
        id="themeButton">
        🌙 Dark Mode
    </button>
</div>

<div class="hero">

    <div class="portal-card">

        <img
            src="assets/images/logo.png"
            alt="PMPC Logo"
            class="logo"
        >

        <h1 class="portal-title">
            PMPC Election Portal
        </h1>

        <p class="portal-subtitle">
            Panabo Multipurpose Cooperative
        </p>

        <div class="d-grid gap-3">

            <a
                href="voters/candidates.php"
                class="btn btn-portal btn-voter"
            >
                🗳️ Voter Login
            </a>

            <a
                href="admin/login.php"
                class="btn btn-portal btn-admin"
            >
                ⚙️ Admin Login
            </a>

            <a
                href="registration/index.php"
                class="btn btn-portal btn-register"
            >
                📝 Registration System
            </a>

            <a
                href="qr-registration/index.php"
                class="btn btn-portal btn-qr-register"
            >
                🔲 QR Registration System
            </a>

        </div>

        <div class="info-box">

            <strong>
                Election Reminder
            </strong>

            <br>

            <small>
                Voters will first be directed to the
                candidate information page before
                logging in and casting their votes.
            </small>

        </div>

        <div class="footer-note">
            PMPC Online Voting System
        </div>

    </div>

</div>

<script>

function toggleTheme(){

    document.body.classList.toggle('dark-theme');

    const btn =
        document.getElementById('themeButton');

    if(
        document.body.classList.contains(
            'dark-theme'
        )
    ){

        btn.innerHTML =
            '☀️ Light Mode';

        localStorage.setItem(
            'pmpc_theme',
            'dark'
        );

    }else{

        btn.innerHTML =
            '🌙 Dark Mode';

        localStorage.setItem(
            'pmpc_theme',
            'light'
        );
    }
}

window.onload = function(){

    if(
        localStorage.getItem(
            'pmpc_theme'
        ) === 'dark'
    ){

        document.body.classList.add(
            'dark-theme'
        );

        document.getElementById(
            'themeButton'
        ).innerHTML =
            '☀️ Light Mode';
    }
};

</script>

</body>
</html>