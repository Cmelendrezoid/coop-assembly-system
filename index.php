<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PMPC Election Portal</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-gradient: radial-gradient(circle at 50% 0%, #f8fafc 0%, #e2e8f0 100%);
            --card-bg: #ffffff;
            --card-border: rgba(226, 232, 240, 0.9);
            --text-main: #0f172a;
            --text-muted: #64748b;
            --toggle-btn-bg: #ffffff;
            --toggle-btn-color: #334155;
            --toggle-btn-border: #cbd5e1;
            
            --voter-card-bg: #ffffff;
            --voter-card-border: #e2e8f0;
            --voter-accent: #059669;
            --voter-hover-bg: #f0fdf4;

            --qr-card-bg: #ffffff;
            --qr-card-border: #e2e8f0;
            --qr-accent: #4f46e5;
            --qr-hover-bg: #eef2ff;

            --info-bg: #f8fafc;
            --info-border: #e2e8f0;
            --info-title: #334155;
            --info-text: #64748b;
            --badge-bg: #ecfdf5;
            --badge-color: #047857;
        }

        .dark-theme {
            --bg-gradient: radial-gradient(circle at 50% 0%, #0f172a 0%, #020617 100%);
            --card-bg: #1e293b;
            --card-border: rgba(51, 65, 85, 0.9);
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --toggle-btn-bg: #1e293b;
            --toggle-btn-color: #f8fafc;
            --toggle-btn-border: #475569;

            --voter-card-bg: #0f172a;
            --voter-card-border: #334155;
            --voter-accent: #10b981;
            --voter-hover-bg: rgba(16, 185, 129, 0.1);

            --qr-card-bg: #0f172a;
            --qr-card-border: #334155;
            --qr-accent: #818cf8;
            --qr-hover-bg: rgba(99, 102, 241, 0.1);

            --info-bg: rgba(15, 23, 42, 0.6);
            --info-border: #334155;
            --info-title: #cbd5e1;
            --info-text: #94a3b8;
            --badge-bg: rgba(16, 185, 129, 0.15);
            --badge-color: #34d399;
        }

        * {
            transition: background-color 0.2s ease, border-color 0.2s ease, color 0.2s ease, box-shadow 0.2s ease, transform 0.2s ease;
        }

        body {
            margin: 0;
            min-height: 100vh;
            background: var(--bg-gradient);
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 32px 16px;
        }

        .theme-toggle {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
        }

        .theme-btn {
            background: var(--toggle-btn-bg);
            color: var(--toggle-btn-color);
            border: 1px solid var(--toggle-btn-border);
            padding: 9px 16px;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .theme-btn:hover {
            transform: translateY(-1px);
        }

        .hero {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .portal-card {
            width: 100%;
            max-width: 680px;
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 28px;
            padding: 48px 40px;
            text-align: center;
            box-shadow: 0 20px 40px -15px rgba(0, 0, 0, 0.12);
        }

        .logo {
            width: 96px;
            height: 96px;
            object-fit: contain;
            margin-bottom: 16px;
            filter: drop-shadow(0 4px 6px rgba(0,0,0,0.05));
        }

        .welcome-badge {
            display: inline-block;
            background: var(--badge-bg);
            color: var(--badge-color);
            font-size: 11px;
            font-weight: 700;
            padding: 4px 12px;
            border-radius: 12px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 12px;
        }

        .portal-title {
            font-size: 1.75rem;
            font-weight: 800;
            margin-bottom: 4px;
            color: var(--text-main);
            letter-spacing: -0.02em;
        }

        .portal-subtitle {
            color: var(--text-muted);
            margin-bottom: 36px;
            font-size: 0.95rem;
            font-weight: 500;
        }

        /* Modern Grid Layout */
        .portal-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-bottom: 28px;
        }

        .action-card {
            text-decoration: none;
            border-radius: 20px;
            padding: 28px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            border: 1px solid;
            position: relative;
            overflow: hidden;
            height: 100%;
        }

        .action-card:hover {
            transform: translateY(-4px);
        }

        .action-icon {
            font-size: 2rem;
            margin-bottom: 14px;
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .action-title {
            font-size: 1.05rem;
            font-weight: 800;
            margin-bottom: 6px;
            letter-spacing: -0.01em;
        }

        .action-desc {
            font-size: 0.8rem;
            font-weight: 500;
            line-height: 1.4;
            margin: 0;
            color: var(--text-muted);
        }

        /* Voter Action Card Styling */
        .card-voter {
            background: var(--voter-card-bg);
            border-color: var(--voter-card-border);
            color: var(--text-main);
        }

        .card-voter .action-icon {
            background: rgba(5, 150, 105, 0.1);
            color: var(--voter-accent);
        }

        .card-voter:hover {
            background: var(--voter-hover-bg);
            border-color: var(--voter-accent);
            box-shadow: 0 12px 24px -6px rgba(5, 150, 105, 0.2);
        }

        /* QR Action Card Styling */
        .card-qr {
            background: var(--qr-card-bg);
            border-color: var(--qr-card-border);
            color: var(--text-main);
        }

        .card-qr .action-icon {
            background: rgba(79, 70, 229, 0.1);
            color: var(--qr-accent);
        }

        .card-qr:hover {
            background: var(--qr-hover-bg);
            border-color: var(--qr-accent);
            box-shadow: 0 12px 24px -6px rgba(79, 70, 229, 0.2);
        }

        .info-box {
            padding: 16px 20px;
            border-radius: 16px;
            background: var(--info-bg);
            border: 1px solid var(--info-border);
            text-align: left;
        }

        .info-box strong {
            color: var(--info-title);
            font-size: 0.85rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 6px;
            margin-bottom: 4px;
        }

        .info-box small {
            color: var(--info-text);
            font-size: 0.82rem;
            line-height: 1.45;
            display: block;
            font-weight: 500;
        }

        .footer-note {
            margin-top: 28px;
            color: var(--text-muted);
            font-size: 0.8rem;
            font-weight: 500;
        }

        @media(max-width: 640px) {
            .portal-card {
                padding: 32px 20px;
                border-radius: 22px;
            }

            .portal-grid {
                grid-template-columns: 1fr;
                gap: 14px;
            }

            .action-card {
                padding: 22px 18px;
                flex-direction: row;
                text-align: left;
                align-items: center;
                gap: 16px;
            }

            .action-icon {
                margin-bottom: 0;
                flex-shrink: 0;
            }

            .portal-title {
                font-size: 1.45rem;
            }

            .logo {
                width: 80px;
                height: 80px;
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
            onerror="this.style.display='none';"
        >

        <div>
            <span class="welcome-badge">Official Member Portal</span>
        </div>

        <h1 class="portal-title">
            PMPC Election Portal
        </h1>

        <p class="portal-subtitle">
            Panabo Multi-Purpose Cooperative
        </p>

        <!-- 2-Column Grid Layout -->
        <div class="portal-grid">

            <a href="voters/candidates.php" class="action-card card-voter">
                <div class="action-icon">
                    🗳️
                </div>
                <div>
                    <div class="action-title">Voter Login</div>
                    <p class="action-desc">View candidates and cast your ballot securely</p>
                </div>
            </a>

            <a href="qr-registration/index.php" class="action-card card-qr">
                <div class="action-icon">
                    🔲
                </div>
                <div>
                    <div class="action-title">QR Registration</div>
                    <p class="action-desc">Scan or register member QR codes for verification</p>
                </div>
            </a>

        </div>

        <div class="info-box">
            <strong>
                💡 Election Reminder
            </strong>
            <small>
                Voters will first be directed to the candidate information page before logging in and casting their votes.
            </small>
        </div>

        <div class="footer-note">
            Panabo Multi-Purpose Cooperative E-Voting System
        </div>

    </div>

</div>

<script>

function toggleTheme(){

    document.body.classList.toggle('dark-theme');

    const btn = document.getElementById('themeButton');

    if(document.body.classList.contains('dark-theme')){

        btn.innerHTML = '☀️ Light Mode';

        localStorage.setItem(
            'pmpc_theme',
            'dark'
        );

    } else {

        btn.innerHTML = '🌙 Dark Mode';

        localStorage.setItem(
            'pmpc_theme',
            'light'
        );
    }
}

window.onload = function(){

    if(localStorage.getItem('pmpc_theme') === 'dark'){

        document.body.classList.add('dark-theme');

        document.getElementById('themeButton').innerHTML = '☀️ Light Mode';
    }
};

</script>

</body>
</html>