<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<style>
/* =========================================================
   PMPC ADMIN - SHARED SIDEBAR
   This is the single sidebar style used by all admin pages.
   ========================================================= */

.sidebar {
    position: fixed;
    top: 0;
    left: 0;
    width: 250px;
    height: 100vh;
    background: #090d16;
    color: #ffffff;
    padding: 1rem;
    overflow-y: auto;
    overflow-x: hidden;
    z-index: 1040;
    display: flex;
    flex-direction: column;
    border-right: 1px solid rgba(255, 255, 255, 0.06);
    box-sizing: border-box;
}

.sidebar::-webkit-scrollbar {
    width: 5px;
}

.sidebar::-webkit-scrollbar-track {
    background: transparent;
}

.sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.12);
    border-radius: 10px;
}

/* Brand Card */
.sidebar-brand-card {
    width: 100%;
    min-height: 72px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.7rem 0.85rem;
    margin-bottom: 1.8rem;
    background: rgba(255, 255, 255, 0.025);
    border: 1px solid rgba(255, 255, 255, 0.09);
    border-radius: 15px;
    box-sizing: border-box;
}

.brand-logo-wrapper {
    width: 42px;
    height: 42px;
    min-width: 42px;
    border-radius: 12px;
    background: rgba(37, 99, 235, 0.12);
    border: 1px solid rgba(59, 130, 246, 0.35);
    display: flex;
    align-items: center;
    justify-content: center;
    overflow: hidden;
}

.brand-logo-img {
    width: 100% !important;
    height: 100% !important;
    max-width: none !important;
    max-height: none !important;
    object-fit: contain !important;
    border-radius: 0 !important;
    display: block;
}

.brand-logo-fallback {
    width: 100%;
    height: 100%;
    align-items: center;
    justify-content: center;
    color: #3b82f6;
    font-size: 0.72rem;
    font-weight: 800;
}

.brand-text-details {
    min-width: 0;
    flex: 1;
}

.sidebar .brand-title {
    margin: 0;
    padding: 0;
    color: #f8fafc;
    font-size: 1rem;
    line-height: 1.15;
    font-weight: 800;
    letter-spacing: -0.02em;
    white-space: nowrap;
}

.brand-status-pill {
    display: flex;
    align-items: center;
    gap: 0.4rem;
    margin-top: 0.25rem;
    color: #94a3b8;
    font-size: 0.68rem;
    font-weight: 600;
    white-space: nowrap;
}

.status-indicator {
    width: 6px;
    height: 6px;
    min-width: 6px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
}

/* Navigation */
.sidebar-nav-wrapper {
    width: 100%;
    flex: 1;
}

.sidebar-section-label {
    color: #64748b;
    font-size: 0.65rem;
    line-height: 1;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    margin: 0 0 0.7rem 0.5rem;
}

.sidebar-section-label:not(:first-child) {
    margin-top: 1.45rem;
}

.sidebar-nav {
    list-style: none !important;
    padding: 0 !important;
    margin: 0 !important;
    display: flex;
    flex-direction: column;
    gap: 0.22rem;
}

.sidebar-nav li {
    list-style: none !important;
    margin: 0 !important;
    padding: 0 !important;
}

.sidebar-nav .nav-link-item {
    width: 100%;
    min-height: 48px;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    padding: 0.7rem 0.85rem;
    box-sizing: border-box;
    color: #8e9bb0;
    background: transparent;
    border: 1px solid transparent;
    border-radius: 12px;
    text-decoration: none !important;
    font-size: 0.875rem;
    font-weight: 600;
    line-height: 1;
    transition:
        background-color 0.2s ease,
        color 0.2s ease,
        transform 0.2s ease,
        box-shadow 0.2s ease;
}

.sidebar-nav .nav-link-item i {
    width: 20px;
    min-width: 20px;
    text-align: center;
    font-size: 1.08rem;
    line-height: 1;
}

.sidebar-nav .nav-link-item:hover {
    color: #ffffff !important;
    background: rgba(255, 255, 255, 0.055) !important;
    transform: translateX(2px);
}

.sidebar-nav .nav-link-item.active {
    color: #ffffff !important;
    background: #2563eb !important;
    border-color: rgba(96, 165, 250, 0.2);
    box-shadow: 0 5px 16px rgba(37, 99, 235, 0.32);
    font-weight: 700;
}

.sidebar-nav .nav-link-item.active:hover {
    background: #2563eb !important;
    transform: none;
}

.sidebar-nav .nav-link-item.text-danger-hover {
    color: #8e9bb0 !important;
}

.sidebar-nav .nav-link-item.text-danger-hover:hover {
    color: #ef4444 !important;
    background: rgba(239, 68, 68, 0.10) !important;
}

/* Mobile Header */
.mobile-header {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 58px;
    padding: 0.7rem 1rem;
    background: #090d16;
    color: #ffffff;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    z-index: 1030;
    box-sizing: border-box;
}

.mobile-brand {
    display: flex;
    align-items: center;
    gap: 0.55rem;
}

.mobile-brand-logo {
    width: 34px;
    height: 34px;
    border-radius: 10px;
    object-fit: contain;
    background: rgba(37, 99, 235, 0.12);
    border: 1px solid rgba(59, 130, 246, 0.35);
}

.mobile-brand-title {
    margin: 0;
    color: #f8fafc;
    font-size: 0.9rem;
    font-weight: 800;
    line-height: 1.1;
}

.mobile-brand-subtitle {
    margin: 2px 0 0;
    color: #64748b;
    font-size: 0.58rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.04em;
}

.sidebar-toggle-button {
    border: 0;
    background: transparent;
    color: #ffffff;
    padding: 0;
    font-size: 1.65rem;
    line-height: 1;
}

.sidebar-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(2, 6, 23, 0.72);
    backdrop-filter: blur(5px);
    -webkit-backdrop-filter: blur(5px);
    z-index: 1035;
}

@media (max-width: 991.98px) {
    .mobile-header {
        display: flex;
    }

    .sidebar {
        transform: translateX(-100%);
        transition: transform 0.3s ease;
    }

    .sidebar.show {
        transform: translateX(0);
    }

    .sidebar-overlay.show {
        display: block;
    }
}
</style>

<!-- Shared Mobile Header -->
<div class="mobile-header">
    <div class="mobile-brand">
        <img
            src="../assets/img/logo.png"
            alt="PMPC Logo"
            class="mobile-brand-logo"
            onerror="this.style.display='none';"
        >
        <div>
            <div class="mobile-brand-title">PMPC</div>
            <div class="mobile-brand-subtitle">E-Voting Admin</div>
        </div>
    </div>

    <button
        type="button"
        class="sidebar-toggle-button"
        onclick="toggleMenu()"
        aria-label="Toggle Sidebar Navigation"
    >
        <i class="bi bi-list"></i>
    </button>
</div>

<!-- Shared Mobile Overlay -->
<div
    class="sidebar-overlay"
    id="sidebarOverlay"
    onclick="toggleMenu()"
></div>

<!-- Shared Sidebar -->
<aside class="sidebar" id="sidebarNav">
    <!-- Modern Enterprise Brand Header Card -->
    <div class="sidebar-brand-card">
        <div class="brand-logo-wrapper">
            <img
                src="../assets/img/logo.png"
                alt="PMPC Logo"
                class="brand-logo-img"
                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
            >
            <div class="brand-logo-fallback" style="display:none;">PMPC</div>
        </div>

        <div class="brand-text-details">
            <h1 class="brand-title">PMPC Admin</h1>

            <div class="brand-status-pill">
                <span class="status-indicator"></span>
                <span class="status-text">Election Portal</span>
            </div>
        </div>
    </div>

    <!-- Navigation Menu -->
    <div class="sidebar-nav-wrapper">
        <div class="sidebar-section-label">Main Menu</div>

        <ul class="sidebar-nav">
            <li>
                <a
                    href="dashboard.php"
                    class="nav-link-item <?php echo ($currentPage === 'dashboard.php') ? 'active' : ''; ?>"
                >
                    <i class="bi bi-grid-1x2-fill"></i>
                    <span>Dashboard</span>
                </a>
            </li>

            <li>
                <a
                    href="candidates.php"
                    class="nav-link-item <?php echo ($currentPage === 'candidates.php') ? 'active' : ''; ?>"
                >
                    <i class="bi bi-person-square"></i>
                    <span>Candidates</span>
                </a>
            </li>

            <li>
                <a
                    href="positions.php"
                    class="nav-link-item <?php echo ($currentPage === 'positions.php') ? 'active' : ''; ?>"
                >
                    <i class="bi bi-award-fill"></i>
                    <span>Positions</span>
                </a>
            </li>

            <li>
                <a
                    href="voters.php"
                    class="nav-link-item <?php echo ($currentPage === 'voters.php') ? 'active' : ''; ?>"
                >
                    <i class="bi bi-people-fill"></i>
                    <span>Voters</span>
                </a>
            </li>

            <li>
                <a
                    href="pre-registered.php"
                    class="nav-link-item <?php echo ($currentPage === 'pre-registered.php') ? 'active' : ''; ?>"
                >
                    <i class="bi bi-clipboard-check-fill"></i>
                    <span>Pre-registered</span>
                </a>
            </li>

            <li>
                <a
                    href="elections.php"
                    class="nav-link-item <?php echo ($currentPage === 'elections.php') ? 'active' : ''; ?>"
                >
                    <i class="bi bi-building-fill"></i>
                    <span>Branches</span>
                </a>
            </li>

            <li>
                <a
                    href="results.php"
                    class="nav-link-item <?php echo ($currentPage === 'results.php') ? 'active' : ''; ?>"
                >
                    <i class="bi bi-bar-chart-line-fill"></i>
                    <span>Results</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-section-label">Account</div>

        <ul class="sidebar-nav">
            <li>
                <a
                    href="logout.php"
                    class="nav-link-item text-danger-hover"
                >
                    <i class="bi bi-box-arrow-right"></i>
                    <span>Logout</span>
                </a>
            </li>
        </ul>
    </div>
</aside>
