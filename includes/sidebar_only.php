<?php
// Unified Sidebar Only (for include)
if (!isset($currentPage)) {
    $currentPage = basename($_SERVER['PHP_SELF'], '.php');
    if ($currentPage === 'index' && isset($_GET['action'])) {
        $currentPage = $_GET['action'];
    }
}
$isSubdirectory = (strpos($_SERVER['REQUEST_URI'], '/') !== false && basename(dirname($_SERVER['PHP_SELF'])) !== 'Note');
$urlPrefix = $isSubdirectory ? '../' : '';
if (!function_exists('isActiveMainPage')) {
    function isActiveMainPage($pageKey, $currentPage) {
        if ($pageKey === 'homepage' && ($currentPage === 'index' || $currentPage === 'homepage')) return true;
        if ($pageKey === $currentPage) return true;
        return false;
    }
}
?>
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
                    <span class="menu-text">1. Trang Chủ</span>
                </a>
            </li>
            <li class="menu-item">
                <a class="menu-link <?= isActiveMainPage('dashboard', $currentPage) ? 'active' : '' ?>" href="<?= $urlPrefix ?>dashboard.php">
                    <span class="menu-text">2. Dashboard</span>
                </a>
            </li>
            <li class="menu-item">
                <a class="menu-link <?= isActiveMainPage('bulk_redirect_manager', $currentPage) ? 'active' : '' ?>" href="<?= $urlPrefix ?>bulk_redirect_manager.php">
                    <span class="menu-text">4. Bulk Redirect Manager</span>
                </a>
            </li>
            <!-- ... (add more menu items as needed) ... -->
        </ul>
    </div>
</nav>
