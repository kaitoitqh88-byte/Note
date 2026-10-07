<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>[TACTICAL-CF] Command Center - Mission Control Interface</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Roboto+Mono:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="assets/css/rpg-shooter-theme.css" rel="stylesheet">
    <link href="assets/css/sidebar-navigation.css" rel="stylesheet">
    <style>
    .sidebar-content { padding: 0px !important; }
    </style>
    <style>
        /* Matrix Theme with Roboto Font */
        * {
            font-family: 'Roboto', sans-serif !important; 
        }
        
        body {
            background: #000;
            
            font-family: 'Roboto', sans-serif;
             
            overflow-x: hidden;
        }
        
        /* Matrix Animation Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(
                90deg,
                transparent,
                transparent 98px,
                rgba(0, 255, 0, 0.03) 100px
            );
            pointer-events: none;
            z-index: -1;
            animation: matrixLines 10s linear infinite;
        }
        
        @keyframes matrixLines {
            0% { transform: translateX(0); }
            100% { transform: translateX(100px); }
        }
        
        /* Body with sidebar open - adjust main content */
        body {
            padding-left: 280px;
            transition: padding-left 0.3s ease;
        }
        
        body.sidebar-collapsed {
            padding-left: 0;
        }
        
        body.sidebar-collapsed .sidebar-nav {
            left: -280px !important;
        }
        
        /* HUD Container */
        .hud-container {
            min-height: calc(100vh - 200px);
            padding: 1.5rem;
            background: rgba(0, 0, 0, 0.8);
        }
        
        /* Matrix Text Glow */
        h1, h2, h3, h4, h5, h6 {  
            font-family: 'Roboto', sans-serif !important;
            font-weight: 500;
        }
        
        /* Enhanced Matrix Cards */
        .matrix-card {
            background: rgba(0, 0, 0, 0.9);
            border: 2px solid #00ff00;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            backdrop-filter: blur(10px);
            box-shadow: 0 0 0px rgba(0, 255, 0, 0.3);
            transition: all 0.3s ease;
        }
        
        .matrix-card:hover {
            border-color: #00ff88;
            box-shadow: 0 0 0px rgba(0, 255, 0, 0.6);
            transform: translateY(-5px);
        }
        
        .card-title {
            color: green;
            font-weight: 700;
            margin-bottom: 1rem; 
        }
        
        /* Action Buttons */
        .action-btn {
            background: rgba(0, 255, 0, 0.1);
            border: 2px solid #00ff00;
            color: #00ff00;
            padding: 0.75rem 1.5rem;
            border-radius: 8px;
            text-decoration: none;
            display: inline-block;
            margin: 0.5rem 0.5rem 0.5rem 0;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .action-btn:hover {
            background: #00ff00;
            color: #000;
            box-shadow: 0 0 0px rgba(0, 255, 0, 0.6);
            transform: scale(1.05);
        }
        
        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: rgba(0, 0, 0, 0.9);
            border: 1px solid #00ff00;
            border-radius: 8px;
            padding: 1.5rem;
            text-align: center;
        }
        
        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            color: #00ff00;
            text-shadow: 0 0 10px rgba(0, 255, 0, 0.8);
        }
        
        .stat-label {
            color: rgba(0, 255, 0, 0.7);
            font-size: 0.9rem;
            margin-top: 0.5rem;
        } 
        
        /* .sidebar-link {
            display: block;
            color: #fff;
            text-decoration: none;
            padding: 0.5rem 0;
            font-family: 'Roboto', sans-serif;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            border-bottom: 1px solid rgba(0, 255, 102, 0.2);
        } */
        
        .sidebar-link:hover {
            color: #00ff66;
            padding-left: 10px;
            text-decoration: none;
        }
        
        .sidebar-link i {
            width: 20px;
            margin-right: 8px;
        }
        
        /* Mobile Responsive */
        @media (max-width: 767px) {
            .homepage-layout {
                flex-direction: column;
                margin-top: 10px;
                padding-top: 10px;
            }
            
            .homepage-sidebar {
                width: 100%;
                min-width: auto;
                max-height: none;
                height: auto;
                border-right: none;
                border-bottom: 2px solid var(--tactical-primary);
                margin-bottom: 1rem;
            }
            
            .homepage-main {
                padding: 1rem;
            }
        }
    </style>
    <script>
        // Enhanced tactical navigation scroll effect
        window.addEventListener('scroll', function() {
            const navbar = document.querySelector('.navbar-main');
            if (window.scrollY > 50) {
                navbar.classList.add('scrolled');
            } else {
                navbar.classList.remove('scrolled');
            }
        });
    </script>
</head>
<body>
    <?php 
    $currentPage = 'homepage'; // Set active page for navigation
    include 'includes/main_navigation.php'; 
    ?>

    <!-- Tactical Command Center -->
    <section class="hero-section hud-container">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-3">
                        TACTICAL CLOUDFLARE
                        <span class="text-accent-orange">COMMAND CENTER</span>
                    </h1>
                    <p class="lead mb-4">
                        Unified Infrastructure Management System for domains, DNS, SSL/TLS operations, and complete Cloudflare service control from a single tactical interface.
                    </p>
                    <div class="d-flex gap-3">
                        <a href="dashboard.php" class="btn btn-primary btn-lg">
                            <i class="fas fa-chart-line"></i> TACTICAL DASHBOARD
                        </a>
                        <a href="search.php" class="btn btn-warning btn-lg">
                            <i class="fas fa-search"></i> RECON DOMAIN
                        </a>

                            <i class="fab fa-wordpress"></i> WP ARSENAL
                        </a>
                        <a href="/" class="btn btn-outline-light btn-lg">
                            <i class="fas fa-search-plus"></i> DNS Lookup
                        </a>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="fas fa-cloud fa-10x opacity-25"></i>
                </div>
            </div>
        </div>
    </section>

    <div class="container-fluid">
        <div class="homepage-layout">
            <!-- Sidebar -->
            <div class="homepage-sidebar">
                <!-- Quick Actions -->
                <div class="sidebar-section">
                    <h6 class="sidebar-title">
                        <i class="fas fa-rocket me-2"></i>Quick Deploy
                    </h6>
                    <a href="dns_simple.php" class="sidebar-link">
                        <i class="fas fa-satellite-dish"></i>Quick DNS Deploy
                    </a>
                    <a href="bulk_redirect_manager.php" class="sidebar-link">
                        <i class="fas fa-arrow-right"></i>Bulk Redirects
                    </a>
                    <a href="cache_manager.php" class="sidebar-link">
                        <i class="fas fa-tachometer-alt"></i>Cache Manager
                    </a>
                </div>
                
                <!-- Management Tools -->
                <div class="sidebar-section">
                    <h6 class="sidebar-title">
                        <i class="fas fa-tools me-2"></i>Management
                    </h6>
                    <a href="domain_status_checker.php" class="sidebar-link">
                        <i class="fas fa-search"></i>Domain Checker
                    </a>
                    <a href="dns_tools_overview.php" class="sidebar-link">
                        <i class="fas fa-cogs"></i>DNS Tools
                    </a>
                </div>
                
                <!-- System Info -->
                <div class="sidebar-section">
                    <h6 class="sidebar-title">
                        <i class="fas fa-info-circle me-2"></i>System
                    </h6>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">Version:</small>
                        <small class="text-success">2.0.1</small>
                    </div>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">Status:</small>
                        <small class="text-success">⚡ Active</small>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">Mode:</small>
                        <small class="text-warning">🎮 Tactical</small>
                    </div>
                </div>
            </div>
            
            <!-- Main Content -->
            <div class="homepage-main">
        <!-- Thống Kê Tổng Quan -->
        <section class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="fas fa-chart-pie text-primary"></i>
                    Thống Kê Tổng Quan
                </h2>
                <button class="btn btn-outline-primary btn-sm" onclick="refreshStats()">
                    <i class="fas fa-sync-alt"></i> Làm mới
                </button>
            </div>
            
            <div class="row g-4" id="stats-container">
                <div class="col-lg-3 col-md-6">
                    <div class="card stats-card bg-primary-gradient text-white">
                        <div class="card-body text-center position-relative">
                            <i class="fas fa-globe stats-icon"></i>
                            <h5 class="card-title mb-0">Tổng Domains</h5>
                            <div class="stats-number loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                            <div class="stats-number" id="total-domains">0</div>
                            <p class="card-text opacity-75 mb-0">Domains đang quản lý</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card stats-card bg-success-gradient text-white">
                        <div class="card-body text-center position-relative">
                            <i class="fas fa-check-circle stats-icon"></i>
                            <h5 class="card-title mb-0">Active Domains</h5>
                            <div class="stats-number loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                            <div class="stats-number" id="active-domains">0</div>
                            <p class="card-text opacity-75 mb-0">Đang hoạt động</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card stats-card bg-warning-gradient text-white">
                        <div class="card-body text-center position-relative">
                            <i class="fas fa-shield-alt stats-icon"></i>
                            <h5 class="card-title mb-0">SSL Enabled</h5>
                            <div class="stats-number loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                            <div class="stats-number" id="ssl-enabled">0</div>
                            <p class="card-text opacity-75 mb-0">SSL được bật</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="card stats-card bg-info-gradient text-white">
                        <div class="card-body text-center position-relative">
                            <i class="fas fa-server stats-icon"></i>
                            <h5 class="card-title mb-0">DNS Only</h5>
                            <div class="stats-number loading-spinner">
                                <i class="fas fa-spinner fa-spin"></i>
                            </div>
                            <div class="stats-number" id="dns-only">0</div>
                            <p class="card-text opacity-75 mb-0">DNS Only mode</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Danh Sách Chức Năng -->
        <section class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="fas fa-tools text-primary"></i>
                    Danh Sách Chức Năng
                </h2>
                <span class="badge bg-primary fs-6">9 chức năng</span>
            </div>
            
            <div class="row g-4">
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon bg-primary-gradient">
                                <i class="fas fa-tachometer-alt"></i>
                            </div>
                            <h5 class="card-title">Dashboard</h5>
                            <p class="card-text text-muted">
                                Xem tổng quan tất cả domains, quản lý zones và theo dõi trạng thái hoạt động.
                            </p>
                            <a href="dashboard.php" class="btn btn-primary">
                                <i class="fas fa-arrow-right"></i> Truy cập
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon bg-success-gradient">
                                <i class="fas fa-search"></i>
                            </div>
                            <h5 class="card-title">Tìm Kiếm Domain</h5>
                            <p class="card-text text-muted">
                                Tìm kiếm nhanh domains theo tên, trạng thái hoặc các tiêu chí khác.
                            </p>
                            <a href="search.php" class="btn btn-success">
                                <i class="fas fa-arrow-right"></i> Tìm kiếm
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon bg-info-gradient">
                                <i class="fas fa-exchange-alt"></i>
                            </div>
                            <h5 class="card-title">IDN Converter</h5>
                            <p class="card-text text-muted">
                                Chuyển đổi tên miền quốc tế (IDN) giữa Unicode và Punycode.
                            </p>
                            <a href="IDNPunycodeConverter/" class="btn btn-info">
                                <i class="fas fa-arrow-right"></i> Chuyển đổi
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon bg-info-gradient">
                                <i class="fas fa-link"></i>
                            </div>
                            <h5 class="card-title">301 Chain Checker</h5>
                            <p class="card-text text-muted">
                                Kiểm tra chuỗi redirect 301/302, phát hiện Meta refresh và JavaScript redirects.
                            </p>
                            <a href="check_301/" class="btn btn-info">
                                <i class="fas fa-arrow-right"></i> Phân tích Redirects
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon bg-purple-gradient">
                                <i class="fas fa-cog"></i>
                            </div>
                            <h5 class="card-title">DNS Management</h5>
                            <p class="card-text text-muted">
                                Quản lý records DNS, thêm/sửa/xóa A, CNAME, MX records.
                            </p>
                            <a href="/?action=dns" class="btn" style="background: #00ff66; color: #000;">
                                <i class="fas fa-arrow-right"></i> Quản lý DNS
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon bg-info-gradient">
                                <i class="fas fa-search-plus"></i>
                            </div>
                            <h5 class="card-title">Domain Extractor</h5>
                            <p class="card-text text-muted">
                                Lấy danh sách domains từ văn bản, URLs, emails một cách nhanh chóng.
                            </p>
                            <a href="domain_extractor.php" class="btn btn-info">
                                <i class="fas fa-arrow-right"></i> Extract Domains
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon bg-info-gradient">
                                <i class="fas fa-search-plus"></i>
                            </div>
                            <h5 class="card-title">DNS Lookup Tool</h5>
                            <p class="card-text text-muted">
                                Tra cứu DNS records và nameservers của bất kỳ domain nào.
                            </p>
                            <a href="search.php" class="btn btn-info">
                                <i class="fas fa-arrow-right"></i> DNS Lookup
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon bg-success-gradient">
                                <i class="fas fa-rocket"></i>
                            </div>
                            <h5 class="card-title">Cache Management</h5>
                            <p class="card-text text-muted">
                                Quản lý cache, purge cache và tối ưu hiệu suất website.
                            </p>
                            <a href="cache_manager.php" class="btn btn-success">
                                <i class="fas fa-arrow-right"></i> Cache Manager
                            </a>
                        </div>
                    </div>
                </div>
                

                
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon bg-primary-gradient">
                                <i class="fas fa-list-alt"></i>
                            </div>
                            <h5 class="card-title">Zones Management</h5>
                            <p class="card-text text-muted">
                                Quản lý zones, thêm/xóa domains và cấu hình zones.
                            </p>
                            <a href="search.php" class="btn btn-primary">
                                <i class="fas fa-arrow-right"></i> Quản lý Zones
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="card feature-card h-100">
                        <div class="card-body text-center">
                            <div class="feature-icon bg-danger-gradient">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h5 class="card-title">WAF & Security Manager</h5>
                            <p class="card-text text-muted">
                                Quản lý WAF rules, Firewall, Page Rules và các lớp bảo mật Cloudflare.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <!-- Domains Gần Đây -->
        <section class="mb-5">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>
                    <i class="fas fa-clock text-primary"></i>
                    Domains Gần Đây
                </h2>
                <a href="dashboard.php" class="btn btn-outline-primary btn-sm">
                    <i class="fas fa-list"></i> Xem tất cả
                </a>
            </div>
            
            <div class="recent-domains">
                <div id="recent-domains-list">
                    <div class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                        <p class="text-muted mt-2">Đang tải danh sách domains...</p>
                    </div>
                </div>
            </div>
        </section>
            </div> <!-- End homepage-main -->
        </div> <!-- End homepage-layout -->
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-light py-4 mt-5">
        <div class="container-fluid text-center">
            <p class="mb-0">
                <i class="fas fa-cloud text-primary"></i>
                <strong>Cloudflare Management System</strong> - 
                Quản lý Cloudflare hiệu quả và chuyên nghiệp
            </p>
            <small class="text-muted">© 2026 - Phiên bản 2.0</small>
        </div>
    </footer>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/common.js"></script>
    <script src="assets/js/homepage.js"></script>
    
    <!-- Additional Menu Functions -->
    <script>
        // Export Data Function
        function exportData() {
            if (confirm('Xuất dữ liệu cấu hình hiện tại?')) {
                showLoading('Đang xuất dữ liệu...');
                
                fetch('index.php?action=export_cache_data')
                    .then(response => response.blob())
                    .then(blob => {
                        const url = window.URL.createObjectURL(blob);
                        const a = document.createElement('a');
                        a.style.display = 'none';
                        a.href = url;
                        a.download = `cloudflare_data_${new Date().toISOString().split('T')[0]}.json`;
                        document.body.appendChild(a);
                        a.click();
                        window.URL.revokeObjectURL(url);
                        hideLoading();
                        showNotification('success', 'Dữ liệu đã được xuất thành công!');
                    })
                    .catch(error => {
                        hideLoading();
                        showNotification('error', 'Lỗi khi xuất dữ liệu: ' + error.message);
                    });
            }
        }

        // Import Data Function
        function importData() {
            const input = document.createElement('input');
            input.type = 'file';
            input.accept = '.json';
            input.onchange = function(e) {
                const file = e.target.files[0];
                if (!file) return;
                
                if (confirm('Nhập dữ liệu từ file đã chọn? Thao tác này có thể ghi đè cấu hình hiện tại.')) {
                    showLoading('Đang nhập dữ liệu...');
                    
                    const formData = new FormData();
                    formData.append('import_file', file);
                    
                    fetch('index.php?action=import_cache_data', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        hideLoading();
                        if (data.success) {
                            showNotification('success', 'Dữ liệu đã được nhập thành công!');
                            setTimeout(() => location.reload(), 1500);
                        } else {
                            showNotification('error', 'Lỗi khi nhập dữ liệu: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        hideLoading();
                        showNotification('error', 'Lỗi khi nhập dữ liệu: ' + error.message);
                    });
                }
            };
            input.click();
        }

        // Utility Functions
        function showLoading(message = 'Đang xử lý...') {
            // Create loading overlay if it doesn't exist
            let overlay = document.getElementById('loadingOverlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.id = 'loadingOverlay';
                overlay.className = 'loading-overlay';
                overlay.innerHTML = `
                    <div class="spinner-border text-light mb-3" style="width: 3rem; height: 3rem;"></div>
                    <div id="loadingMessage">${message}</div>
                `;
                document.body.appendChild(overlay);
                
                // Add CSS if not exists
                if (!document.getElementById('loadingStyles')) {
                    const style = document.createElement('style');
                    style.id = 'loadingStyles';
                    style.textContent = `
                        .loading-overlay {
                            position: fixed;
                            top: 0;
                            left: 0;
                            width: 100%;
                            height: 100%;
                            background: rgba(0, 0, 0, 0.8);
                            display: flex;
                            flex-direction: column;
                            align-items: center;
                            justify-content: center;
                            z-index: 9999;
                            color: #00ff66;
                        }
                    `;
                    document.head.appendChild(style);
                }
            } else {
                document.getElementById('loadingMessage').textContent = message;
            }
            overlay.style.display = 'flex';
        }

        function hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }

        function showNotification(type, message) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 300px;';
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
    </script>
    
    <!-- Tactical Gaming Interface -->
    <script src="assets/js/tactical-interface.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Initialize Menu Toggle
        function initializeMenuToggle() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    document.body.classList.toggle('sidebar-collapsed');
                    
                    const isCollapsed = document.body.classList.contains('sidebar-collapsed');
                    this.innerHTML = isCollapsed ? '☰' : '✖';
                    this.setAttribute('title', isCollapsed ? 'Hiện Menu' : 'Ẩn Menu');
                    
                    this.style.boxShadow = '0 0 20px rgba(0, 255, 0, 0.8)';
                    this.style.background = 'rgba(0, 255, 0, 0.3)';
                    setTimeout(() => {
                        this.style.boxShadow = '0 0 15px rgba(0, 255, 0, 0.6)';
                        this.style.background = 'rgba(0, 255, 0, 0.1)';
                    }, 300);
                });
                
                // Keyboard shortcuts
                document.addEventListener('keydown', function(e) {
                    if ((e.ctrlKey && e.key === 'b') || (e.ctrlKey && e.key === 'm')) {
                        e.preventDefault();
                        sidebarToggle.click();
                    }
                });
                
                sidebarToggle.innerHTML = '✖';
                sidebarToggle.setAttribute('title', 'Ẩn Menu (Ctrl+B)');
            }
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                initializeMenuToggle();
            }, 100);
        });
    </script>
    
</body>
</html>