<?php
/**
 * Main Navigation Component
 * Reusable navigation menu for all pages
 * 
 * Usage: include 'includes/navigation.php';
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
$isSubdirectory = (strpos($_SERVER['REQUEST_URI'], '/IDNPunycodeConverter/') !== false);
$urlPrefix = $isSubdirectory ? '../' : '';

// Define menu items with better organization
$menuItems = [
    'home' => ['url' => $urlPrefix . 'index.php', 'label' => 'Trang Chủ', 'icon' => 'fas fa-home', 'group' => 'primary'],
    'dashboard' => ['url' => $urlPrefix . 'dashboard.php', 'label' => 'Dashboard', 'icon' => 'fas fa-tachometer-alt', 'group' => 'primary'],
    
    'search' => ['url' => $urlPrefix . 'search.php', 'label' => 'Tìm Kiếm', 'icon' => 'fas fa-search', 'group' => 'tools'],
    // 'dns_bulk_update' => ['url' => $urlPrefix . 'dns_bulk_update.php', 'label' => 'DNS Bulk Update', 'icon' => 'fas fa-globe', 'group' => 'tools'],
    'domain_status_checker' => ['url' => $urlPrefix . 'domain_status_checker.php', 'label' => 'Status Check', 'icon' => 'fas fa-check-circle', 'group' => 'tools'],
    // 'redirect_manager' => ['url' => $urlPrefix . 'redirect_manager.php', 'label' => 'Redirects', 'icon' => 'fas fa-exchange-alt', 'group' => 'tools'],
    'check_301' => ['url' => $urlPrefix . 'check_301/', 'label' => '301 Check', 'icon' => 'fas fa-link', 'group' => 'tools']
];

// Tools dropdown items
$toolsItems = [
    ['url' => $urlPrefix . 'dns_tools_overview.php', 'label' => 'DNS Tools Overview', 'icon' => 'fas fa-tools'],
    'divider',
    ['url' => $urlPrefix . 'domain_extractor.php', 'label' => 'Domain Extractor', 'icon' => 'fas fa-search-plus'],
    ['url' => $urlPrefix . 'domain_status_checker.php', 'label' => 'Status Check', 'icon' => 'fas fa-check-circle'],
    ['url' => $urlPrefix . 'IDNPunycodeConverter/index.php', 'label' => 'IDN Converter', 'icon' => 'fas fa-language'],
    ['url' => $urlPrefix . 'dns_simple.php', 'label' => 'DNS Simple', 'icon' => 'fas fa-server'],
    // ['url' => $urlPrefix . 'dns_bulk_update.php', 'label' => 'DNS Bulk Update', 'icon' => 'fas fa-globe'],
    ['url' => $urlPrefix . 'cache_manager.php', 'label' => 'Cache Manager', 'icon' => 'fas fa-database'],
    ['url' => $urlPrefix . 'cache_ttl_bulk_update.php', 'label' => 'Cache TTL Bulk Update', 'icon' => 'fas fa-clock'],
    // ['url' => $urlPrefix . 'redirect_manager.php', 'label' => 'Domain Redirect Manager', 'icon' => 'fas fa-exchange-alt'],
    ['url' => $urlPrefix . 'check_301/', 'label' => '301 Redirect Chain Checker', 'icon' => 'fas fa-link'],
    'divider',
    ['url' => $urlPrefix . 'cloudflare_debug.php', 'label' => 'Cloudflare Debug', 'icon' => 'fas fa-bug'],
    'divider',
    ['url' => $urlPrefix . 'vps_login_checker.php', 'label' => 'VPS Login Checker', 'icon' => 'fas fa-server'],
    ['url' => $urlPrefix . 'vps_manager.php', 'label' => 'VPS Manager', 'icon' => 'fas fa-list-alt'],
    // ['url' => $urlPrefix . 'aapanel_account_checker.php', 'label' => 'aaPanel Account Checker', 'icon' => 'fas fa-user-check'],
    // ['url' => $urlPrefix . 'aapanel_api_config.php', 'label' => 'aaPanel API Config', 'icon' => 'fas fa-key'],
    ['url' => $urlPrefix . 'index.php?action=api-stats', 'label' => 'API Statistics', 'icon' => 'fas fa-chart-line'],
    'divider',
    ['url' => $urlPrefix . 'index.php?action=dns', 'label' => 'DNS Manager', 'icon' => 'fas fa-network-wired'],
    ['url' => $urlPrefix . 'index.php?action=analytics', 'label' => 'Analytics', 'icon' => 'fas fa-chart-pie'],
    ['url' => $urlPrefix . 'index.php?action=background', 'label' => 'Background Tasks', 'icon' => 'fas fa-cogs']
];

if (!function_exists('isActive')) {
    function isActive($pageKey, $currentPage) {
        // Handle different page matching scenarios
        if ($pageKey === 'home' && ($currentPage === 'index' || $currentPage === 'homepage')) return true;
        if ($pageKey === $currentPage) return true;
        
        return false;
    }
}

// Helper function to check if a tools dropdown item should be active
if (!function_exists('isToolsItemActive')) {
    function isToolsItemActive($item, $currentPage) {
        if ($currentPage === 'dns-tools' && strpos($item['url'], 'dns_tools_overview.php') !== false) {
            return true;
        }
        if ($currentPage === 'domain-extractor' && strpos($item['url'], 'domain_extractor.php') !== false) {
            return true;
        }
        if ($currentPage === 'idn-converter' && strpos($item['url'], 'IDNPunycodeConverter') !== false) {
            return true;
        }
        if ($currentPage === 'dns-simple' && strpos($item['url'], 'dns_simple.php') !== false) {
            return true;
        }
        if ($currentPage === 'dns_bulk_update' && strpos($item['url'], 'dns_bulk_update.php') !== false) {
            return true;
        }
        if ($currentPage === 'cache_manager' && strpos($item['url'], 'cache_manager.php') !== false) {
            return true;
        }

        if ($currentPage === 'check_301' && strpos($item['url'], 'check_301/') !== false) {
            return true;
        }
        return false;
    }
}

// Check if tools dropdown should be active
$toolsActive = false;
foreach ($toolsItems as $item) {
    if ($item !== 'divider' && isToolsItemActive($item, $currentPage)) {
        $toolsActive = true;
        break;
    }
}
?>

<!-- Enhanced Navigation -->
<nav class="navbar navbar-expand-lg navbar-dark navbar-main sticky-top">
    <div class="container">
        <a class="navbar-brand fw-bold" href="<?= $urlPrefix ?>index.php">
            <i class="fas fa-cloud me-2"></i>CF Manager
        </a>
        
        <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav me-auto">
                <?php 
                // Group menu items
                $primaryItems = array_filter($menuItems, function($item) { return ($item['group'] ?? 'primary') === 'primary'; });
                $toolItems = array_filter($menuItems, function($item) { return ($item['group'] ?? 'primary') === 'tools'; });
                
                // Display primary items first
                foreach ($primaryItems as $key => $item): ?>
                <li class="nav-item">
                    <a class="nav-link <?= isActive($key, $currentPage) ? 'active' : '' ?><?= isset($item['class']) ? ' ' . $item['class'] : '' ?>" href="<?= $item['url'] ?>">
                        <i class="<?= $item['icon'] ?>"></i><?= $item['label'] ?>
                    </a>
                </li>
                <?php endforeach; ?>
                
                <!-- Tools submenu for better organization -->
                <?php if (!empty($toolItems)): ?>
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="quickToolsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-tools"></i>Tools
                    </a>
                    <ul class="dropdown-menu">
                        <?php foreach ($toolItems as $key => $item): ?>
                        <li>
                            <a class="dropdown-item <?= isActive($key, $currentPage) ? 'active' : '' ?>" href="<?= $item['url'] ?>">
                                <i class="<?= $item['icon'] ?>"></i><?= $item['label'] ?>
                            </a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </li>
                <?php endif; ?>
                
                <!-- Advanced Tools Dropdown -->
                <li class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle <?= $toolsActive ? 'active' : '' ?>" href="#" id="toolsDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-cogs"></i>More
                    </a>
                    <ul class="dropdown-menu dropdown-menu-3col">
                        <div class="dropdown-content-3col">
                            <?php 
                            $column1 = [];
                            $column2 = [];
                            $column3 = [];
                            
                            // Remove dividers and organize items into 3 columns
                            $cleanItems = array_filter($toolsItems, function($item) { return $item !== 'divider'; });
                            $itemsArray = array_values($cleanItems);
                            $totalItems = count($itemsArray);
                            $itemsPerColumn = ceil($totalItems / 3);
                            
                            for ($i = 0; $i < $totalItems; $i++) {
                                if ($i < $itemsPerColumn) {
                                    $column1[] = $itemsArray[$i];
                                } elseif ($i < $itemsPerColumn * 2) {
                                    $column2[] = $itemsArray[$i];
                                } else {
                                    $column3[] = $itemsArray[$i];
                                }
                            }
                            ?>
                            
                            <div class="dropdown-column">
                                <?php foreach ($column1 as $item): ?>
                                    <li>
                                        <a class="dropdown-item <?= isToolsItemActive($item, $currentPage) ? 'active' : '' ?>" href="<?= $item['url'] ?>" <?= isset($item['onclick']) ? 'onclick="' . $item['onclick'] . '"' : '' ?>>
                                            <i class="<?= $item['icon'] ?>"></i><?= $item['label'] ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="dropdown-column">
                                <?php foreach ($column2 as $item): ?>
                                    <li>
                                        <a class="dropdown-item <?= isToolsItemActive($item, $currentPage) ? 'active' : '' ?>" href="<?= $item['url'] ?>" <?= isset($item['onclick']) ? 'onclick="' . $item['onclick'] . '"' : '' ?>>
                                            <i class="<?= $item['icon'] ?>"></i><?= $item['label'] ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="dropdown-column">
                                <?php foreach ($column3 as $item): ?>
                                    <li>
                                        <a class="dropdown-item <?= isToolsItemActive($item, $currentPage) ? 'active' : '' ?>" href="<?= $item['url'] ?>" <?= isset($item['onclick']) ? 'onclick="' . $item['onclick'] . '"' : '' ?>>
                                            <i class="<?= $item['icon'] ?>"></i><?= $item['label'] ?>
                                        </a>
                                    </li>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </ul>
                </li>
            </ul>
        </div>
    </div>
</nav>

<style>
/* Modern Navigation Styling */
:root {
    --nav-primary: #2563eb;
    --nav-primary-dark: #1d4ed8;
    --nav-secondary: #7c3aed;
    --nav-accent: #06b6d4;
    --nav-surface: #1e293b;
    --nav-surface-light: #334155;
    --nav-text: #f8fafc;
    --nav-text-muted: #cbd5e1;
    --nav-shadow: rgba(0, 0, 0, 0.1);
    --nav-shadow-dark: rgba(0, 0, 0, 0.25);
}

.navbar-main {
    background: linear-gradient(135deg, var(--nav-surface) 0%, var(--nav-surface-light) 100%);
    backdrop-filter: saturate(180%) blur(20px);
    box-shadow: 0 1px 3px var(--nav-shadow), 0 1px 2px var(--nav-shadow-dark);
    border-bottom: 1px solid rgba(255, 255, 255, 0.08);
    transition: all 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
    padding: 0.5rem 0;
    position: sticky;
    top: 0;
    z-index: 1000;
}

.navbar-main.scrolled {
    background: rgba(30, 41, 59, 0.95);
    box-shadow: 0 4px 6px -1px var(--nav-shadow), 0 2px 4px -1px var(--nav-shadow-dark);
}

.navbar-brand {
    font-weight: 700;
    color: var(--nav-text) !important;
    font-size: 1.25rem;
    text-decoration: none;
    transition: all 0.3s ease;
    letter-spacing: -0.025em;
}

.navbar-brand:hover {
    color: var(--nav-accent) !important;
    transform: scale(1.02);
}

.navbar-nav {
    gap: 0.25rem;
    align-items: center;
}

.nav-item {
    position: relative;
}

.nav-link {
    color: var(--nav-text-muted) !important;
    font-weight: 500;
    font-size: 0.9rem;
    padding: 0.5rem 0.75rem !important;
    border-radius: 0.5rem;
    transition: all 0.2s cubic-bezier(0.4, 0.0, 0.2, 1);
    position: relative;
    text-decoration: none;
    display: flex;
    align-items: center;
    white-space: nowrap;
}

.nav-link i {
    width: 16px;
    margin-right: 0.5rem;
    text-align: center;
    font-size: 0.875rem;
}

.nav-link:hover {
    color: var(--nav-text) !important;
    background: rgba(255, 255, 255, 0.08);
    transform: translateY(-1px);
}

.nav-link.active {
    color: var(--nav-text) !important;
    background: linear-gradient(135deg, var(--nav-primary), var(--nav-secondary));
    box-shadow: 0 1px 3px rgba(37, 99, 235, 0.3);
    font-weight: 600;
}

.nav-link.active::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(135deg, var(--nav-primary), var(--nav-secondary));
    border-radius: 0.5rem;
    opacity: 0.1;
    z-index: -1;
}

/* Navbar Toggle */
.navbar-toggler {
    border: none;
    padding: 0.375rem 0.5rem;
    border-radius: 0.5rem;
    background: rgba(255, 255, 255, 0.08);
    transition: all 0.2s ease;
}

.navbar-toggler:hover {
    background: rgba(255, 255, 255, 0.12);
}

.navbar-toggler:focus {
    box-shadow: 0 0 0 2px rgba(37, 99, 235, 0.5);
    outline: none;
}

.navbar-toggler-icon {
    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28248, 250, 252, 0.85%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
}

/* Dropdown Styles */
.dropdown-menu {
    background: #ffffff;
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 0.75rem;
    box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.3), 0 4px 6px -2px rgba(0, 0, 0, 0.1);
    padding: 0.5rem;
    margin-top: 0.25rem;
    min-width: 200px;
    backdrop-filter: blur(20px);
}

.dropdown-item {
    color: #000 !important;
    padding: 0.5rem 0.75rem;
    font-weight: 500;
    font-size: 0.875rem;
    border-radius: 0.5rem;
    transition: all 0.2s ease;
    text-decoration: none;
    display: flex;
    align-items: center;
}

.dropdown-item i {
    width: 16px;
    margin-right: 0.75rem;
    text-align: center;
    font-size: 0.8rem;
}

.dropdown-item:hover {
    color: var(--nav-text);
    background: rgba(255, 255, 255, 0.08);
    transform: translateX(2px);
}

.dropdown-item.active {
    color: var(--nav-text);
    background: linear-gradient(135deg, var(--nav-primary), var(--nav-secondary));
    font-weight: 600;
}

.dropdown-divider {
    border-color: rgba(255, 255, 255, 0.08);
    margin: 0.5rem 0;
}
    box-shadow: 0 0 0 0.2rem rgba(99, 102, 241, 0.5);
}

.dropdown-menu {
    background: #1a202c;
    border: 1px solid rgba(255, 255, 255, 0.1);
    border-radius: 0.75rem;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
    padding: 0.5rem 0;
    margin-top: 0.5rem;
    min-width: 250px;
}

.dropdown-item {
    color: rgba(255, 255, 255, 0.85);
    padding: 0.7rem 1.25rem;
    font-weight: 500;
    transition: all 0.3s ease;
    border-radius: 0.5rem;
    margin: 0 0.5rem;
}

.dropdown-item:hover {
    color: white;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    transform: translateX(5px);
}

.dropdown-item.active {
    color: white;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

.dropdown-divider {
    border-color: rgba(255, 255, 255, 0.1);
    margin: 0.5rem 0;
}

/* 3-Column Dropdown Styles */
.dropdown-menu-3col {
    min-width: 700px;
    max-width: 800px;
    padding: 1rem;
}

.dropdown-content-3col {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1rem;
}

.dropdown-column {
    display: flex;
    flex-direction: column;
    min-width: 200px;
}

.dropdown-column li {
    list-style: none;
    margin-bottom: 0.25rem;
}

.dropdown-column .dropdown-item {
    padding: 0.5rem 0.75rem;
    margin: 0;
    border-radius: 0.375rem;
    font-size: 0.875rem;
    white-space: nowrap;
    text-overflow: ellipsis;
    overflow: hidden;
}

.dropdown-column .dropdown-item:hover {
    transform: translateX(3px);
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
}

/* Tablet responsive styles for 3-column dropdown */
@media (max-width: 1199.98px) and (min-width: 768px) {
    .dropdown-menu-3col {
        min-width: 600px;
        max-width: 650px;
    }
    
    .dropdown-content-3col {
        grid-template-columns: repeat(2, 1fr);
    }
}

/* Responsive Design */
@media (max-width: 991.98px) {
    .navbar-collapse {
        background: rgba(30, 41, 59, 0.98);
        border-radius: 0.75rem;
        padding: 1rem;
        margin-top: 0.5rem;
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
        border: 1px solid rgba(255, 255, 255, 0.08);
    }
    
    .navbar-nav {
        gap: 0.125rem;
    }
    
    .nav-link {
        padding: 0.625rem 0.75rem !important;
        border-radius: 0.5rem;
        margin: 0.125rem 0;
        font-size: 0.95rem;
    }
    
    .nav-link:hover {
        background: linear-gradient(135deg, var(--nav-primary), var(--nav-secondary));
        color: var(--nav-text) !important;
        transform: translateX(8px);
    }
    
    .dropdown-menu {
        background: #ffffff;
        border: 1px solid rgba(255, 255, 255, 0.1);
        margin-left: 0.75rem;
        margin-top: 0.25rem;
        min-width: calc(100% - 1.5rem);
    }
    
    /* 3-Column Dropdown Mobile Styles */
    .dropdown-menu-3col {
        min-width: calc(100% - 1.5rem);
        max-width: none;
        padding: 0.75rem;
    }
    
    .dropdown-content-3col {
        display: grid;
        grid-template-columns: 1fr;
        gap: 0.5rem;
    }
    
    .dropdown-column {
        min-width: auto;
    }
    
    .dropdown-item {
        color: #000;
        padding: 0.5rem 0.75rem;
        font-size: 0.875rem;
    }
    
    /* Touch-friendly sizing */
    .nav-link, .dropdown-item {
        min-height: 44px;
        display: flex;
        align-items: center;
    }
    
    /* Smooth animation */
    .navbar-collapse {
        animation: slideDown 0.3s cubic-bezier(0.4, 0.0, 0.2, 1);
    }
    
    @keyframes slideDown {
        from {
            opacity: 0;
            transform: translateY(-8px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }
}

@media (max-width: 767.98px) {
    .navbar-main {
        padding: 0.375rem 0;
    }
    
    .navbar-brand {
        font-size: 1.1rem;
    }
    
    .nav-link {
        font-size: 0.9rem;
        padding: 0.5rem 0.625rem !important;
    }
    
    .dropdown-item {
        color: #000;
        padding: 0.5rem 0.625rem;
        font-size: 0.85rem;
    }
}

@media (max-width: 575.98px) {
    .container {
        padding-left: 1rem;
        padding-right: 1rem;
    }
    
    .navbar-collapse {
        margin-left: -1rem;
        margin-right: -1rem;
        border-radius: 0;
    }
    
    .navbar-brand {
        font-size: 1rem;
    }
}

/* Touch device enhancements */
@media (hover: none) {
    .nav-link:hover {
        background: none;
        transform: none;
    }
    
    .nav-link:active {
        background: linear-gradient(135deg, var(--nav-primary), var(--nav-secondary));
        color: var(--nav-text) !important;
        transform: scale(0.98);
    }
    
    .dropdown-item:hover {
        background: none;
        transform: none;
    }
    
    .dropdown-item:active {
        background: linear-gradient(135deg, var(--nav-primary), var(--nav-secondary));
        color: var(--nav-text) !important;
    }
}

/* Focus and accessibility */
.nav-link:focus,
.dropdown-item:focus {
    outline: 2px solid var(--nav-primary);
    outline-offset: 2px;
    border-radius: 0.375rem;
}

/* Reduced motion support */
@media (prefers-reduced-motion: reduce) {
    .nav-link,
    .dropdown-item,
    .navbar-toggler,
    .navbar-collapse,
    .navbar-brand {
        transition: none;
        animation: none;
    }
    
    .nav-link:hover,
    .dropdown-item:hover {
        transform: none;
    }
}

/* Dark mode enhancements */
@media (prefers-color-scheme: dark) {
    :root {
        --nav-shadow: rgba(0, 0, 0, 0.3);
        --nav-shadow-dark: rgba(0, 0, 0, 0.4);
    }
    
    .dropdown-menu {
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5);
    }
}

/* High DPI displays */
@media (-webkit-min-device-pixel-ratio: 2), (min-resolution: 192dpi) {
    .navbar-main {
        border-bottom: 0.5px solid rgba(255, 255, 255, 0.08);
    }
}
        transition: none;
        animation: none;
    }
    
    .nav-link:hover {
        transform: none;
    }
}

/* Dark mode support */
@media (prefers-color-scheme: dark) {
    .navbar-main {
        border-bottom: 1px solid rgba(255, 255, 255, 0.05);
    }
    
    .dropdown-menu {
        box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
    }
}
</style>

<!-- Navigation Scroll Effect Script -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Enhanced navigation scroll effect
    window.addEventListener('scroll', function() {
        const navbar = document.querySelector('.navbar-main');
        if (navbar) {
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        }
    });
    
    // Mobile Navigation Enhancements
    const navbarToggler = document.querySelector('.navbar-toggler');
    const navbarCollapse = document.querySelector('.navbar-collapse');
    const navLinks = document.querySelectorAll('.nav-link:not(.dropdown-toggle)');
    const dropdownItems = document.querySelectorAll('.dropdown-item');
    
    // Close mobile menu when clicking on nav links
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth < 992) {
                const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
                if (bsCollapse) {
                    bsCollapse.hide();
                }
            }
        });
    });
    
    // Close mobile menu when clicking on dropdown items
    dropdownItems.forEach(item => {
        item.addEventListener('click', () => {
            if (window.innerWidth < 992) {
                setTimeout(() => {
                    const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
                    if (bsCollapse) {
                        bsCollapse.hide();
                    }
                }, 150);
            }
        });
    });
    
    // Close mobile menu when clicking outside
    document.addEventListener('click', function(event) {
        if (window.innerWidth < 992) {
            const navbar = document.querySelector('.navbar-main');
            const isClickInsideNav = navbar.contains(event.target);
            
            if (!isClickInsideNav && navbarCollapse.classList.contains('show')) {
                const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
                if (bsCollapse) {
                    bsCollapse.hide();
                }
            }
        }
    });
    
    // Smooth scrolling for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start'
                });
            }
        });
    });
    
    // Touch improvements for mobile
    if ('ontouchstart' in window) {
        // Add touch class for touch-specific styles
        document.body.classList.add('touch-device');
        
        // Improve touch interactions
        navLinks.forEach(link => {
            link.addEventListener('touchstart', function() {
                this.classList.add('touch-hover');
            });
            
            link.addEventListener('touchend', function() {
                setTimeout(() => {
                    this.classList.remove('touch-hover');
                }, 300);
            });
        });
    }
    
    // Accessibility improvements
    navbarToggler.addEventListener('click', function() {
        const expanded = this.getAttribute('aria-expanded') === 'true';
        this.setAttribute('aria-expanded', !expanded);
    });
    
    // Keyboard navigation support
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && navbarCollapse.classList.contains('show')) {
            const bsCollapse = bootstrap.Collapse.getInstance(navbarCollapse);
            if (bsCollapse) {
                bsCollapse.hide();
            }
            navbarToggler.focus();
        }
    });
});
</script>