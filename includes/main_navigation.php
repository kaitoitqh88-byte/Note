<?php
/**
 * Main Navigation Component - Unified Menu
 * Reusable navigation menu for all main pages
 * 
 * Usage: include 'includes/main_navigation.php';
 * Optional: Set $currentPage variable before including to highlight active menu
 */

// Determine current page if not set
if (!isset($currentPage)) {
    $currentPage = basename($_SERVER['PHP_SELF'], '.php');
    
    // Handle index.php actions
    if ($currentPage === 'index' && isset($_GET['action'])) {
        $currentPage = $_GET['action'];
    }
}

// Detect if we're in a subdirectory
$isSubdirectory = (strpos($_SERVER['REQUEST_URI'], '/') !== false && basename(dirname($_SERVER['PHP_SELF'])) !== 'Note');
$urlPrefix = $isSubdirectory ? '../' : '';

// Helper function to check if current page is active
if (!function_exists('isActiveMainPage')) {
    function isActiveMainPage($pageKey, $currentPage) {
        // Handle different page matching scenarios
        if ($pageKey === 'homepage' && ($currentPage === 'index' || $currentPage === 'homepage')) return true;
        if ($pageKey === $currentPage) return true;
        
        return false;
    }
}
?>

<style>
    .sidebar-nav {
        background: #000 !important;
        color: #00ff00 !important;
        border-right: 2px solid #00ff00;
        min-height: 100vh;
        width: 280px;
        position: fixed;
        top: 0;
        left: 0;
        z-index: 1001;
        box-shadow: 0 0 24px 0 rgba(0,255,0,0.08);
    }
    .sidebar-header {
        padding: 1.5rem 1rem 1rem 1.5rem;
        border-bottom: 1px solid #00ff00;
    }
        color: #00ff00 !important;
        font-size: 1.5rem;
        font-weight: 700;
        letter-spacing: 2px;
        text-shadow: 0 0 10px #00ff00, 0 0 20px #00ff00;
        text-decoration: none;
    }
    .sidebar-content {
        padding: 0 1rem;
    }
    .sidebar-menu {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    .menu-item, .submenu-item {
        margin-bottom: 0.5rem;
    }
        padding: 0.75rem 1rem;
        font-size: 1rem;
        font-family: 'Roboto', monospace, Arial, sans-serif;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.2s;
        text-shadow: 0 0 8px #00ff00;
    }
    .menu-link.active, .submenu-link.active, .menu-link:hover, .submenu-link:hover {
        background: #001a0d !important;
        color: #00ff88 !important;
        box-shadow: 0 0 12px #00ff88;
        text-shadow: 0 0 16px #00ff88;
        padding-left: 1.5rem;
    }
    .submenu-list {
        padding-left: 1rem;
        border-left: 2px solid #00ff00;
        margin-bottom: 0.5rem;
    }
    .submenu-header {
        color: #00ff88;
        font-size: 0.95rem;
        margin: 0.5rem 0 0.25rem 0.5rem;
        font-weight: 700;
        letter-spacing: 1px;
    }
    .submenu-arrow {
        float: right;
        font-size: 1.1em;
        color: #00ff00;
        margin-left: 0.5rem;
    }
    @media (max-width: 991px) {
        .sidebar-nav {
            width: 100vw;
            min-height: unset;
            position: relative;
            border-right: none;
        }
    }
</style>
<!-- Matrix Sidebar Navigation -->
<nav class="sidebar-nav" id="sidebarNav">
    <div class="sidebar-header">
        <a class="sidebar-brand" href="<?= $urlPrefix ?>index.php">
            <span class="brand-text">CF Manager</span>
        </a>  
    </div>
    <div class="sidebar-content">
        <ul class="sidebar-menu">
            <li class="menu-item">
                <a class="menu-link <?= isActiveMainPage('homepage', $currentPage) ? 'active' : '' ?>" href="<?= $urlPrefix ?>homepage.php">
                    <span class="menu-text"><b>1.</b> Trang Chủ</span>
                </a>
            </li>
            <li class="menu-item">
                <a class="menu-link <?= isActiveMainPage('dashboard', $currentPage) ? 'active' : '' ?>" href="<?= $urlPrefix ?>dashboard.php">
                    <span class="menu-text"><b>2.</b> Dashboard</span>
                </a>
            </li>
            <li class="menu-item">
                <a class="menu-link <?= isActiveMainPage('bulk_redirect_manager', $currentPage) ? 'active' : '' ?>" href="<?= $urlPrefix ?>bulk_redirect_manager.php">
                    <span class="menu-text"><b>3.</b> 301 Domain </span>
                </a>
            </li>

            
            <!-- Server Management Menu (main) -->
            <li class="menu-item has-submenu">
                <a class="menu-link submenu-toggle" href="#" data-bs-toggle="collapse" data-bs-target="#serverSubmenu" aria-expanded="false">
                    <span class="menu-text"><b>4.</b> Server Management</span>
                    <span class="submenu-arrow">▼</span>
                </a>
                <div class="collapse submenu" id="serverSubmenu">
                    <ul class="submenu-list">
                        <li class="submenu-item">
                            <a class="submenu-link" href="<?= $urlPrefix ?>vps_manager.php"><b>4.1</b> VPS Manager</a>
                        </li>
                        <li class="submenu-item">
                            <a class="submenu-link <?= isActiveMainPage('runterminal', $currentPage) ? 'active' : '' ?>" href="<?= $urlPrefix ?>IDNPunycodeConverter/runterminal.php"><b>4.2</b> SSH Multi Terminal</a>
                        </li>
                        <li class="submenu-item">
                            <a class="submenu-link<?= (basename($_SERVER['PHP_SELF']) === 'vpsdomain.php') ? ' active' : '' ?>" href="<?= $urlPrefix ?>IDNPunycodeConverter/vpsdomain.php"><b>4.4</b> VPS Domain Tool</a>
                        </li>
                        <li class="submenu-item">
                            <a class="submenu-link <?= isActiveMainPage('aapanel_bulk_wp_password_reset_ui', $currentPage) ? 'active' : '' ?>" href="<?= $urlPrefix ?>aapanel_bulk_wp_password_reset_ui.php"><b>4.3</b> WP Toolkit - Bulk Reset WP</a>
                        </li>
                        <li class="submenu-item">
                            <a class="submenu-link <?= isActiveMainPage('resetpassword_web', $currentPage) ? 'active' : '' ?>" href="<?= $urlPrefix ?>resetpassword_web.php"><b>4.5</b> Reset Password Web</a>
                        </li>
                        <li class="submenu-item">
                            <a class="submenu-link <?= isActiveMainPage('delete_site_ui', $currentPage) ? 'active' : '' ?>" href="<?= $urlPrefix ?>delete_site_ui.php"><b>4.6</b> Xóa Site aaPanel</a>
                        </li>
                        <li class="submenu-item">
                            <a class="submenu-link <?= isActiveMainPage('aapanel_bulk_rewrite_wordpress', $currentPage) ? 'active' : '' ?>" href="<?= $urlPrefix ?>aapanel_bulk_rewrite_wordpress.php"><b>4.7</b> Rewrite WordPress (Bulk)</a>
                        </li>
                    </ul>
                </div>
            </li>
            <!-- Cache & Redirect Menu (main) -->
            <li class="menu-item has-submenu">
                <a class="menu-link submenu-toggle" href="#" data-bs-toggle="collapse" data-bs-target="#cacheSubmenu" aria-expanded="false">
                    <span class="menu-text"><b>5.</b> Cache & Redirect</span>
                    <span class="submenu-arrow">▼</span>
                </a>
                <div class="collapse submenu" id="cacheSubmenu">
                    <ul class="submenu-list">
                        <li class="submenu-item">
                            <a class="submenu-link" href="<?= $urlPrefix ?>cache_manager.php"><b>5.1</b> Cache Manager</a>
                        </li>
                        <li class="submenu-item">
                            <a class="submenu-link" href="<?= $urlPrefix ?>cache_ttl_bulk_update.php"><b>5.2</b> Cache TTL Bulk Update</a>
                        </li>
                        <li class="submenu-item">
                            <a class="submenu-link" href="<?= $urlPrefix ?>check_301/index.php"><b>5.3</b> 301 Chain Checker</a>
                        </li>
                    </ul>
                </div>
            </li>
            <!-- DNS & Domain Tools Menu (main) -->
            <li class="menu-item has-submenu">
                <a class="menu-link submenu-toggle" href="#" data-bs-toggle="collapse" data-bs-target="#dnsSubmenu" aria-expanded="false">
                    <span class="menu-text"><b>6.</b> DNS & Domain Tools</span>
                    <span class="submenu-arrow">▼</span>
                </a>
                <div class="collapse submenu" id="dnsSubmenu">
                    <ul class="submenu-list">
                        <li class="submenu-item">
                            <a class="submenu-link <?= isActiveMainPage('add_domain_cf', $currentPage) ? 'active' : '' ?>" href="<?= $urlPrefix ?>add_domain_cf.php"><b>6.1</b> Thêm Domain vào CF</a>
                        </li>
                        <li class="submenu-item">
                            <a class="submenu-link <?= isActiveMainPage('domain_actions', $currentPage) ? 'active' : '' ?>" href="<?= $urlPrefix ?>domain_actions.php"><b>6.2</b> Domain Actions (SSL/Cache)</a>
                        </li>
                        <li class="submenu-item">
                            <a class="submenu-link" href="<?= $urlPrefix ?>domain_status_checker.php"><b>6.3</b> Domain Status Checker</a>
                        </li>
                        <li class="submenu-item">
                            <a class="submenu-link" href="<?= $urlPrefix ?>dns_simple.php"><b>6.4</b> Thêm DNS Records</a>
                        </li>
                        <li class="submenu-item">
                            <a class="submenu-link" href="<?= $urlPrefix ?>domain_extractor.php"><b>6.5</b> Domain Extractor</a>
                        </li>
                        <li class="submenu-item">
                            <a class="submenu-link" href="<?= $urlPrefix ?>IDNPunycodeConverter/index.php"><b>6.6</b> IDN Converter</a>
                        </li>
                        <li class="submenu-item">
                            <a class="submenu-link<?= (basename($_SERVER['PHP_SELF']) === 'find_domains_by_ip.php') ? ' active' : '' ?>" href="<?= $urlPrefix ?>find_domains_by_ip.php"><b>6.7</b> Tìm domain theo IP</a>
                        </li>
                        <li class="submenu-item">
                            <a class="submenu-link<?= (basename($_SERVER['PHP_SELF']) === 'SecurityLevel.php') ? ' active' : '' ?>" href="<?= $urlPrefix ?>SecurityLevel.php"><b>6.8</b> Under Attack Mode</a>
                        </li>
                        <li class="submenu-item">
                            <a class="submenu-link <?= (isActiveMainPage('delete-dns', $currentPage) || isActiveMainPage('DeleteDNS', $currentPage)) ? 'active' : '' ?>" href="<?= $urlPrefix ?>DeleteDNS.php"><b>6.9</b> Delete DNS Records</a>
                        </li>
                    </ul>
                </div>
            </li>

            <!-- Search Functionality -->
            <li class="menu-item">
                <form class="d-flex mt-2 mb-3" id="functionSearchForm" onsubmit="return false;">
                    <input class="form-control me-2" type="search" id="functionSearchInput" placeholder="Tìm chức năng..." aria-label="Search" style="max-width: 180px;">
                </form>
            </li>

            <!-- Admin Menu -->
            <!-- Đã xóa menu Admin theo yêu cầu -->
        </ul>
    </div>
</nav>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>

<!-- Sidebar JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const sidebarNav = document.getElementById('sidebarNav');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mainWrapper = document.getElementById('mainWrapper') || document.body;

    // Toggle sidebar function
    function toggleSidebar() {
        sidebarNav.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
        mainWrapper.classList.toggle('sidebar-open');
    }

    // Close sidebar function
    function closeSidebar() {
        sidebarNav.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        mainWrapper.classList.remove('sidebar-open');
    }

    // Event listeners
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            toggleSidebar();
        });
    }

    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }

    // Close sidebar on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });

    // Handle submenu toggles
    const submenuToggles = document.querySelectorAll('.submenu-toggle');
    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            
            const targetId = this.getAttribute('data-bs-target');
            const submenu = document.querySelector(targetId);
            const arrow = this.querySelector('.submenu-arrow');
            
            if (submenu) {
                submenu.classList.toggle('show');
                arrow.innerHTML = submenu.classList.contains('show') ? '▲' : '▼';
            }
        });
    });

    // Auto-close sidebar on mobile when clicking menu items
    const menuLinks = document.querySelectorAll('.sidebar-nav .menu-link, .sidebar-nav .submenu-link');
    menuLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                setTimeout(closeSidebar, 150);
            }
        });
    });
});

// Function search logic
const functionSearchInput = document.getElementById('functionSearchInput');
if (functionSearchInput) {
    functionSearchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase();
        const links = document.querySelectorAll('.menu-link, .submenu-link');
        links.forEach(link => {
            const text = link.textContent.toLowerCase();
            if (query && !text.includes(query)) {
                link.style.display = 'none';
            } else {
                link.style.display = '';
            }
        });
    });
}
</script>
</style>
<style>
/* Matrix Sidebar Navigation - Neon Green, Glow, Dark, Responsive */
.sidebar-nav {
    position: fixed;
    top: 0;
    left: 0; 
    height: 100vh;
    background: linear-gradient(180deg, #101010 80%, #0f0f0f 100%);
    z-index: 1001;
    transition: left 0.3s;
    border-right: 2.5px solid #00ff00;
    outline: 1.5px solid #00ff00;
    outline-offset: -2px;
    filter: drop-shadow(0 0 0 #00ff00);
    animation: matrixSidebarGlow 2s infinite alternate;
}
@keyframes matrixSidebarGlow {
    0% { box-shadow: 0 0 0 #00ff00; }
    100% { box-shadow: 0 0 16px #00ff00; }
}
.sidebar-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 18px 10px 18px;
    border-bottom: 2px solid #00ff00;
    background: #181818;
    border-top-left-radius: 16px;
    border-top-right-radius: 16px;
    box-shadow: none;
}
.sidebar-brand {
    font-size: 22px;
    font-weight: 700;
    color: #00ff00;
    text-decoration: none;
    letter-spacing: 1px;
    text-shadow: none !important;
}
    background: none;
    border: none;
    font-size: 22px;
    cursor: pointer;
    color: #00ff00;
    padding: 0 6px;
    text-shadow: none !important;
    outline: none;
}
.sidebar-content {
    padding: 0 !important;
    padding: 0px !important;
    height: calc(100vh - 60px);
    overflow-y: auto;
    background: transparent;
}
.menu-link {
    display: flex;
    align-items: center;
    padding: 12px 22px;
    font-size: 16px;
    color: #00ff00;
    text-decoration: none;
    border-radius: 6px;
    transition: background 0.2s, color 0.2s;
    font-weight: 500;
    text-shadow: none !important;
    background: transparent;
    border: 1.5px solid transparent;
}
.menu-link.active, .menu-link:hover {
    background: linear-gradient(90deg, #00ff00 8%, #101010 100%);
    color: #fff;
    text-shadow: 0 0 18px #00ff00, 0 0 4px #00ff00;
    border: 1.5px solid #00ff00;
    filter: none;
}
.menu-text {
    flex: 1;
}
.has-submenu .submenu-arrow {
    margin-left: 8px;
    font-size: 14px;
    color: #00ff00;
    text-shadow: 0 0 8px #00ff00;
}
.collapse.submenu {
    padding-left: 12px;
    background: rgba(0,255,0,0.08);
    border-radius: 8px;
    margin-top: 2px;
    box-shadow: none;
}
.submenu-list {
    list-style: none;
    margin: 0;
    padding: 0;
}
.submenu-header {
    font-size: 14px;
    font-weight: 600;
    color: #00ff00;
    margin: 10px 0 4px 0;
    padding-left: 6px;
    text-shadow: 0 0 8px #00ff00;
}
.submenu-item {
    margin-bottom: 2px;
}
.submenu-link {
    display: block;
    padding: 8px 18px;
    font-size: 15px;
    color: green;
    text-decoration: none;
    border-radius: 5px;
    transition: background 0.2s, color 0.2s;
    /* text-shadow: 0 0 12px #00ff00, 0 0 2px #00ff00; */
    background: transparent;
    border: 1px solid transparent;
}
.submenu-link:hover {
    background: linear-gradient(90deg, #00ff00 8%, #101010 100%);
    color: #fff;
    /* text-shadow: 0 0 18px #00ff00, 0 0 4px #00ff00; */
    border: 1px solid #00ff00;
    filter: none;
}
.sidebar-overlay {
    display: none;
    position: fixed;
    top: 0;
    left: 0;
    width: 100vw;
    height: 100vh;
    background: rgba(0,0,0,0.22);
    z-index: 1000;
    transition: opacity 0.2s;
    box-shadow: none;
}
.sidebar-nav.active {
    left: 0;
}
.sidebar-overlay.active {
    display: block;
    opacity: 1;
}
body.sidebar-open {
    overflow: hidden;
}
@media (max-width: 900px) {
    .sidebar-nav {
        left: -240px;
        width: 220px;
        transition: left 0.3s;
    }
    .sidebar-nav.active {
        left: 0;
    }
    .sidebar-content {
        padding: 0 !important;
        padding: 8px 0 0 0;
    }
}
@media (max-width: 700px) {
    .sidebar-nav {
        left: -220px;
        width: 100vw;
        max-width: 320px;
        background: #111;
        border-radius: 0 16px 16px 0;
    }
    .sidebar-nav.active {
        left: 0;
    }
    .sidebar-header {
        padding: 14px 12px 8px 12px;
    }
    .sidebar-content {
        padding: 4px 0 0 0;
    }
    .menu-link, .submenu-link {
        font-size: 15px;
        padding: 10px 14px;
    }
}
</style>
</style>