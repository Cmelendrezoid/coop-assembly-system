<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PMPC Election Portal - VIP Edition</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        :root {
            /* Extravagant Theme Colors */
            --royal-blue: #4169E1;
            --gold-light: #FDE047;
            --gold-dark: #CA8A04;
            --cyan-glow: #22D3EE;
            
            /* Dynamic Light Theme */
            --bg-mesh: radial-gradient(at 0% 0%, rgba(65, 105, 225, 0.15) 0px, transparent 50%),
                       radial-gradient(at 100% 0%, rgba(34, 211, 238, 0.15) 0px, transparent 50%),
                       radial-gradient(at 100% 100%, rgba(253, 224, 71, 0.15) 0px, transparent 50%),
                       #f8fafc;
            --glass-bg: rgba(255, 255, 255, 0.6);
            --glass-border: rgba(255, 255, 255, 0.8);
            --card-shadow: 0 30px 60px -12px rgba(65, 105, 225, 0.15), 0 0 20px rgba(255, 255, 255, 0.8) inset;
            
            --text-main: #0f172a;
            --text-muted: #475569;
            --title-gradient: linear-gradient(135deg, #1e3a8a 0%, #4169E1 50%, #2563eb 100%);
            
            --action-bg: rgba(255, 255, 255, 0.7);
            --action-border: rgba(65, 105, 225, 0.2);
            --action-glow: rgba(65, 105, 225, 0.3);
        }

        .dark-theme {
            /* Dynamic Dark Theme (Deep Luxury) */
            --bg-mesh: radial-gradient(at 0% 0%, rgba(65, 105, 225, 0.25) 0px, transparent 50%),
                       radial-gradient(at 100% 0%, rgba(202, 138, 4, 0.15) 0px, transparent 50%),
                       radial-gradient(at 100% 100%, rgba(34, 211, 238, 0.15) 0px, transparent 50%),
                       #020617;
            --glass-bg: rgba(15, 23, 42, 0.5);
            --glass-border: rgba(255, 255, 255, 0.1);
            --card-shadow: 0 30px 60px -12px rgba(0, 0, 0, 0.8), 0 0 20px rgba(65, 105, 225, 0.1) inset;
            
            --text-main: #ffffff;
            --text-muted: #94a3b8;
            --title-gradient: linear-gradient(to right, #FDE047 0%, #F59E0B 50%, #FDE047 100%);
            
            --action-bg: rgba(30, 41, 59, 0.6);
            --action-border: rgba(255, 255, 255, 0.05);
            --action-glow: rgba(253, 224, 71, 0.2);
        }

        * {
            box-sizing: border-box;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
        }

        /* --- Animated Background --- */
        body {
            margin: 0;
            min-height: 100vh;
            background-color: #0f172a;
            background-image: var(--bg-mesh);
            background-size: 150% 150%;
            animation: gradientMove 15s ease infinite alternate;
            color: var(--text-main);
            font-family: 'Plus Jakarta Sans', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 20px;
            overflow-x: hidden;
        }

        @keyframes gradientMove {
            0% { background-position: 0% 0%; }
            50% { background-position: 100% 100%; }
            100% { background-position: 0% 100%; }
        }

        /* --- Floating Particles/Orbs (Extravagant Touch) --- */
        .orb {
            position: absolute;
            border-radius: 50%;
            filter: blur(80px);
            z-index: -1;
            opacity: 0.6;
            animation: float 10s ease-in-out infinite alternate;
        }
        .orb-1 { width: 300px; height: 300px; background: var(--royal-blue); top: 10%; left: 15%; }
        .orb-2 { width: 250px; height: 250px; background: var(--gold-dark); bottom: 10%; right: 15%; animation-delay: -5s; }

        @keyframes float {
            0% { transform: translateY(0) scale(1); }
            100% { transform: translateY(-50px) scale(1.1); }
        }

        /* --- Premium Theme Toggle --- */
        .theme-toggle {
            position: fixed;
            top: 24px;
            right: 24px;
            z-index: 1000;
        }

        .theme-btn {
            background: var(--glass-bg);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            color: var(--text-main);
            border: 1px solid var(--glass-border);
            padding: 12px 24px;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 700;
            font-size: 13px;
            display: flex;
            align-items: center;
            gap: 10px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .theme-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(65, 105, 225, 0.3);
            border-color: var(--royal-blue);
        }

        /* --- Ultimate Glassmorphism Container --- */
        .hero {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            z-index: 1;
        }

        .portal-card {
            width: 100%;
            max-width: 720px;
            background: var(--glass-bg);
            backdrop-filter: blur(40px) saturate(150%);
            -webkit-backdrop-filter: blur(40px) saturate(150%);
            border: 1px solid var(--glass-border);
            border-radius: 32px;
            padding: 56px 48px;
            text-align: center;
            box-shadow: var(--card-shadow);
            position: relative;
            overflow: hidden;
        }

        /* Shine effect over the card */
        .portal-card::before {
            content: '';
            position: absolute;
            top: 0; left: -100%; width: 50%; height: 100%;
            background: linear-gradient(to right, transparent, rgba(255,255,255,0.2), transparent);
            transform: skewX(-20deg);
            animation: shine 6s infinite;
        }

        @keyframes shine {
            0% { left: -100%; }
            20% { left: 200%; }
            100% { left: 200%; }
        }

        /* --- Extravagant Header Elements --- */
        .logo-container {
            position: relative;
            display: inline-block;
            margin-bottom: 24px;
        }

        .logo-glow {
            position: absolute;
            top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 110px; height: 110px;
            background: var(--royal-blue);
            border-radius: 50%;
            filter: blur(25px);
            opacity: 0.5;
            z-index: -1;
            animation: pulseGlow 3s infinite alternate;
        }

        @keyframes pulseGlow {
            0% { transform: translate(-50%, -50%) scale(0.9); opacity: 0.4; }
            100% { transform: translate(-50%, -50%) scale(1.1); opacity: 0.7; }
        }

        .logo {
            width: 100px;
            height: 100px;
            object-fit: contain;
            filter: drop-shadow(0 10px 15px rgba(0,0,0,0.2));
            position: relative;
            z-index: 2;
        }

        .welcome-badge {
            display: inline-block;
            background: linear-gradient(90deg, rgba(65,105,225,0.1), rgba(65,105,225,0.2));
            border: 1px solid rgba(65, 105, 225, 0.3);
            color: var(--royal-blue);
            font-size: 11px;
            font-weight: 800;
            padding: 8px 20px;
            border-radius: 30px;
            text-transform: uppercase;
            letter-spacing: 0.2em;
            margin-bottom: 16px;
            box-shadow: 0 4px 15px rgba(65, 105, 225, 0.15);
        }
        
        .dark-theme .welcome-badge {
            color: var(--gold-light);
            border-color: rgba(253, 224, 71, 0.3);
            background: linear-gradient(90deg, rgba(253,224,71,0.1), rgba(202,138,4,0.1));
            box-shadow: 0 4px 15px rgba(253, 224, 71, 0.15);
        }

        .portal-title {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 8px;
            background: var(--title-gradient);
            background-size: 200% auto;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: textShine 4s linear infinite;
            letter-spacing: -0.03em;
        }

        @keyframes textShine {
            to { background-position: 200% center; }
        }

        .portal-subtitle {
            color: var(--text-muted);
            font-size: 1.1rem;
            font-weight: 500;
            margin-bottom: 48px;
            letter-spacing: 0.5px;
        }

        /* --- 3D Hover Action Cards --- */
        .portal-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 24px;
            margin-bottom: 32px;
        }

        .action-card {
            text-decoration: none;
            background: var(--action-bg);
            border: 1px solid var(--action-border);
            border-radius: 24px;
            padding: 36px 24px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            text-align: center;
            position: relative;
            overflow: hidden;
            color: var(--text-main);
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            transform-style: preserve-3d;
            perspective: 1000px;
        }

        .action-card::before {
            content: '';
            position: absolute;
            top: 0; left: 0; right: 0; bottom: 0;
            background: radial-gradient(circle at 50% 0%, var(--action-glow), transparent 70%);
            opacity: 0;
            transition: opacity 0.4s ease;
        }

        .action-card:hover {
            transform: translateY(-8px) scale(1.02);
            border-color: var(--royal-blue);
            box-shadow: 0 25px 50px -12px var(--action-glow);
        }

        .action-card:hover::before {
            opacity: 1;
        }

        .action-icon {
            width: 70px;
            height: 70px;
            border-radius: 20px;
            background: linear-gradient(135deg, var(--royal-blue), #1e3a8a);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
            font-size: 1.8rem;
            box-shadow: 0 15px 30px rgba(65, 105, 225, 0.4);
            position: relative;
            z-index: 2;
        }

        .dark-theme .action-icon {
            background: linear-gradient(135deg, var(--gold-light), var(--gold-dark));
            color: #000;
            box-shadow: 0 15px 30px rgba(253, 224, 71, 0.3);
        }

        .action-card:hover .action-icon {
            transform: translateZ(20px);
        }

        .action-title {
            font-size: 1.25rem;
            font-weight: 800;
            margin-bottom: 8px;
            position: relative;
            z-index: 2;
        }

        .action-desc {
            font-size: 0.9rem;
            font-weight: 500;
            line-height: 1.6;
            margin: 0;
            color: var(--text-muted);
            position: relative;
            z-index: 2;
        }

        /* --- Premium Info Box --- */
        .info-box {
            padding: 24px;
            border-radius: 20px;
            background: rgba(65, 105, 225, 0.05);
            border: 1px solid rgba(65, 105, 225, 0.2);
            text-align: left;
            position: relative;
            overflow: hidden;
        }

        .dark-theme .info-box {
            background: rgba(253, 224, 71, 0.05);
            border-color: rgba(253, 224, 71, 0.2);
        }

        .info-box::after {
            content: '';
            position: absolute;
            right: -20px;
            bottom: -20px;
            width: 100px;
            height: 100px;
            background: var(--royal-blue);
            filter: blur(50px);
            opacity: 0.2;
            border-radius: 50%;
        }

        .info-box strong {
            color: var(--text-main);
            font-size: 0.95rem;
            font-weight: 800;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .info-box small {
            color: var(--text-muted);
            font-size: 0.9rem;
            line-height: 1.6;
            display: block;
            font-weight: 500;
        }

        /* --- Footer --- */
        .footer-note {
            margin-top: 40px;
            color: var(--text-muted);
            font-size: 0.85rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.1em;
        }

        /* --- Responsive Design --- */
        @media(max-width: 768px) {
            .portal-card {
                padding: 40px 24px;
                border-radius: 24px;
            }
            .portal-grid {
                grid-template-columns: 1fr;
            }
            .portal-title {
                font-size: 1.8rem;
            }
        }
    </style>
</head>
<body>

<!-- Ambient Background Orbs -->
<div class="orb orb-1"></div>
<div class="orb orb-2"></div>

<div class="theme-toggle">
    <button class="theme-btn" onclick="toggleTheme()" id="themeButton">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg>
        Dark Mode
    </button>
</div>

<div class="hero">
    <div class="portal-card">
        
        <div class="logo-container">
            <div class="logo-glow"></div>
            <!-- Original Logo Restored Here -->
            <img 
                src="assets/images/logo.png" 
                alt="PMPC Logo" 
                class="logo" 
                onerror="this.style.display='none';"
            >
        </div>

        <div>
            <span class="welcome-badge">Member Portal</span>
        </div>

        <h1 class="portal-title">PMPC Election Portal</h1>
        <p class="portal-subtitle">Panabo Multi-Purpose Cooperative</p>

        <div class="portal-grid">
            
            <a href="voters/candidates.php" class="action-card">
                <div class="action-icon">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    </svg>
                </div>
                <div class="action-title">Secure Login</div>
                <p class="action-desc">Authenticate and cast your ballot through our encrypted gateway</p>
            </a>

            <a href="qr-registration/index.php" class="action-card">
                <div class="action-icon">
                    <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="3" width="7" height="7" rx="1.5"></rect>
                        <rect x="14" y="3" width="7" height="7" rx="1.5"></rect>
                        <rect x="14" y="14" width="7" height="7" rx="1.5"></rect>
                        <rect x="3" y="14" width="7" height="7" rx="1.5"></rect>
                    </svg>
                </div>
                <div class="action-title">QR Scanner</div>
                <p class="action-desc">Verify physical member credentials using high-speed QR capture</p>
            </a>

        </div>

        <div class="info-box">
            <strong>
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line></svg>
                System Notice
            </strong>
            <small>
                All voters will be routed through the official candidate information registry prior to securely finalizing their digital ballots.
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
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg> Light Mode';
        localStorage.setItem('pmpc_theme', 'dark');
    } else {
        btn.innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path></svg> Dark Mode';
        localStorage.setItem('pmpc_theme', 'light');
    }
}

window.onload = function(){
    if(localStorage.getItem('pmpc_theme') === 'dark'){
        document.body.classList.add('dark-theme');
        document.getElementById('themeButton').innerHTML = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="5"></circle><line x1="12" y1="1" x2="12" y2="3"></line><line x1="12" y1="21" x2="12" y2="23"></line><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line><line x1="1" y1="12" x2="3" y2="12"></line><line x1="21" y1="12" x2="23" y2="12"></line><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line></svg> Light Mode';
    }
};
</script>

</body>
</html>