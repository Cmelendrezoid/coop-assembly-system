<?php
session_start();

if(!isset($_SESSION['member_id'])){
    header("Location: login.php");
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Thank You</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
/* Base Light Theme Variables */
:root {
    --bg-gradient: radial-gradient(circle at top, #ffffff 0%, #f1f3f5 100%);
    --card-bg: #ffffff;
    --card-border: rgba(0, 0, 0, 0.05);
    --text-main-gradient: linear-gradient(135deg, #1e293b 0%, #475569 100%);
    --body-text: #1e293b;
    --text-muted: #64748b;
    --success-accent: #10b981;
    --toggle-btn-bg: #f1f5f9;
    --toggle-btn-color: #334155;
}

/* Dark Theme Variables */
.dark-theme {
    --bg-gradient: radial-gradient(circle at top, #1e293b 0%, #0f172a 100%);
    --card-bg: #1e293b;
    --card-border: rgba(255, 255, 255, 0.05);
    --text-main-gradient: linear-gradient(135deg, #ffffff 0%, #cbd5e1 100%);
    --body-text: #f8fafc;
    --text-muted: #94a3b8;
    --success-accent: #34d399;
    --toggle-btn-bg: #334155;
    --toggle-btn-color: #f8fafc;
}

body {
    background: var(--bg-gradient);
    color: var(--body-text);
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
    min-height: 100vh;
    transition: background 0.3s ease, color 0.3s ease;
}

/* Theme Toggle Button Positioned Top Right */
.theme-toggle-btn {
    position: absolute;
    top: 20px;
    right: 20px;
    background-color: var(--toggle-btn-bg);
    color: var(--toggle-btn-color);
    border: none;
    padding: 10px 16px;
    border-radius: 30px;
    font-weight: 600;
    font-size: 0.85rem;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    transition: all 0.3s ease;
    display: flex;
    align-items: center;
    gap: 6px;
    z-index: 10;
}

.theme-toggle-btn:hover {
    transform: translateY(-1px);
}

.portal {
    max-width: 500px;
    margin: auto;
    margin-top: 120px;
    border: 1px solid var(--card-border);
    border-radius: 16px;
    background-color: var(--card-bg);
    transition: background-color 0.3s ease, border-color 0.3s ease;
}

/* Success Visual Presentation Element */
.success-icon-wrapper {
    width: 72px;
    height: 72px;
    background-color: rgba(16, 185, 129, 0.1);
    color: var(--success-accent);
    font-size: 2rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    margin: 0 auto 24px auto;
}

h1 {
    font-weight: 700;
    letter-spacing: -0.5px;
    background: var(--text-main-gradient);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 12px;
}

p {
    color: var(--text-muted);
    font-size: 1.05rem;
    margin-bottom: 32px;
    transition: color 0.3s ease;
}

/* Premium Navigation Button Configuration */
.btn-primary {
    background-color: #3b82f6 !important;
    color: #ffffff !important;
    padding: 12px 32px;
    font-size: 1rem;
    font-weight: 600;
    border-radius: 10px;
    transition: all 0.25s ease;
    border: none;
    box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

.btn-primary:hover {
    background-color: #2563eb !important;
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(59, 130, 246, 0.3);
}

.btn-primary:active {
    transform: translateY(0);
}
</style>

</head>
<body>

<button class="theme-toggle-btn" id="themeToggleBtn" onclick="toggleTheme()">
    <span id="themeToggleIcon">🌙</span> <span id="themeToggleText">Dark Mode</span>
</button>

<div class="container text-center">

<div class="card shadow-lg portal">
<div class="card-body p-5">

<div class="success-icon-wrapper">
    ✓
</div>

<h1>Vote Submitted</h1>

<p>
Thank you for participating in the election.
</p>

<a href="logout.php?redirect=candidates.php" class="btn btn-primary">
    Back to Candidates
</a>

</div>
</div>

</div>

<script>
function toggleTheme() {
    const body = document.body;
    const icon = document.getElementById('themeToggleIcon');
    const text = document.getElementById('themeToggleText');
    
    body.classList.toggle('dark-theme');
    
    if(body.classList.contains('dark-theme')) {
        icon.innerText = '☀️';
        text.innerText = 'Light Mode';
        localStorage.setItem('pmpc-theme', 'dark');
    } else {
        icon.innerText = '🌙';
        text.innerText = 'Dark Mode';
        localStorage.setItem('pmpc-theme', 'light');
    }
}

// Automatically match user-facing presentation tokens
window.onload = function() {
    if(localStorage.getItem('pmpc-theme') === 'dark') {
        document.body.classList.add('dark-theme');
        document.getElementById('themeToggleIcon').innerText = '☀️';
        document.getElementById('themeToggleText').innerText = 'Light Mode';
    }
}
</script>

</body>
</html>