<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DNS Tools Overview</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/rpg-shooter-theme.css" rel="stylesheet">
    <link href="assets/css/sidebar-navigation.css" rel="stylesheet">
    <style>
        .tool-card {
            transition: all 0.3s ease;
            border: none;
            border-radius: 15px;
            overflow: hidden;
        }
        .tool-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        .card-header {
            border-radius: 15px 15px 0 0 !important;
        }
        .feature-badge {
            position: absolute;
            top: -5px;
            right: 10px;
            background: linear-gradient(45deg, #ff6b6b, #ee5a6f);
            color: white;
            font-size: 0.7em;
            padding: 5px 10px;
            border-radius: 15px;
        }
        .domain-status {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            padding: 20px;
            color: white;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'dns-tools';
    include 'includes/main_navigation.php'; 
    ?>

    <div class="main-wrapper">
        <div class="container-fluid my-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12 text-center">
                <h1><i class="fas fa-tools me-2 text-primary"></i>DNS Management Tools</h1>
                <p class="lead text-muted">Quản lý DNS records dễ dàng và nhanh chóng</p>
            </div>
        </div>

        <!-- Domain Status for go99ii.com -->
        <div class="domain-status">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h4><i class="fas fa-globe me-2"></i>go99ii.com</h4>
                    <p class="mb-0">Domain của bạn - Cần cập nhật DNS records</p>
                </div>
                <div class="col-md-4 text-end">
                    <a href="quick_go99ii_dns.php" class="btn btn-light btn-lg">
                        <i class="fas fa-bolt me-2"></i>Quick Update
                    </a>
                </div>
            </div>
        </div>

        <!-- DNS Tools Grid -->
        <div class="row">
            <!-- DNS Simple -->
            <div class="col-md-4 mb-4">
                <div class="card tool-card h-100">
                    <div class="card-header bg-success text-white text-center position-relative">
                        <span class="feature-badge">RECOMMENDED</span>
                        <i class="fas fa-server fa-2x mb-2"></i>
                        <h5 class="mb-0">DNS Simple</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">Chỉ cần nhập domain và IP - tự động tạo @ và www records</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Siêu đơn giản</li>
                            <li><i class="fas fa-check text-success me-2"></i>Tự động CNAME</li>
                            <li><i class="fas fa-check text-success me-2"></i>Giao diện đẹp</li>
                        </ul>
                    </div>
                    <div class="card-footer">
                        <a href="dns_simple.php" class="btn btn-success w-100">
                            <i class="fas fa-server me-2"></i>Sử dụng
                        </a>
                    </div>
                </div>
            </div>

            <!-- DNS Bulk Update -->
            <div class="col-md-4 mb-4">
                <div class="card tool-card h-100">
                    <div class="card-header bg-primary text-white text-center">
                        <i class="fas fa-globe fa-2x mb-2"></i>
                        <h5 class="mb-0">DNS Bulk Update</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">Cập nhật DNS cho nhiều domain cùng lúc bằng danh sách</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Nhiều domain</li>
                            <li><i class="fas fa-check text-success me-2"></i>Bulk operation</li>
                            <li><i class="fas fa-check text-success me-2"></i>Progress tracking</li>
                        </ul>
                    </div>
                    <div class="card-footer">
                        <a href="dns_bulk_update.php" class="btn btn-primary w-100">
                            <i class="fas fa-globe me-2"></i>Sử dụng
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick DNS for go99ii.com -->
            <div class="col-md-4 mb-4">
                <div class="card tool-card h-100">
                    <div class="card-header bg-warning text-dark text-center">
                        <i class="fas fa-bolt fa-2x mb-2"></i>
                        <h5 class="mb-0">Quick DNS</h5>
                    </div>
                    <div class="card-body">
                        <p class="card-text">Cập nhật nhanh DNS cho go99ii.com với giao diện đặc biệt</p>
                        <ul class="list-unstyled">
                            <li><i class="fas fa-check text-success me-2"></i>Pre-configured</li>
                            <li><i class="fas fa-check text-success me-2"></i>Glass design</li>
                            <li><i class="fas fa-check text-success me-2"></i>One-click</li>
                        </ul>
                    </div>
                    <div class="card-footer">
                        <a href="quick_go99ii_dns.php" class="btn btn-warning w-100">
                            <i class="fas fa-bolt me-2"></i>Sử dụng
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Management Tools -->
        <div class="row mb-4">
            <div class="col-12">
                <h3><i class="fas fa-cogs me-2"></i>Management Tools</h3>
            </div>
        </div>

        <div class="row">
            <!-- Check go99ii.com Status -->
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-search fa-2x text-info mb-3"></i>
                        <h6>Check Status</h6>
                        <p class="small text-muted">Kiểm tra trạng thái go99ii.com</p>
                        <a href="update_go99ii_dns.php" class="btn btn-info btn-sm">
                            <i class="fas fa-search me-1"></i>Check
                        </a>
                    </div>
                </div>
            </div>

            <!-- Dashboard -->
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-tachometer-alt fa-2x text-primary mb-3"></i>
                        <h6>Dashboard</h6>
                        <p class="small text-muted">Quản lý tổng quan</p>
                        <a href="dashboard.php" class="btn btn-primary btn-sm">
                            <i class="fas fa-tachometer-alt me-1"></i>Open
                        </a>
                    </div>
                </div>
            </div>

            <!-- Search -->
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-search fa-2x text-success mb-3"></i>
                        <h6>Search Domains</h6>
                        <p class="small text-muted">Tìm kiếm domains</p>
                        <a href="search.php" class="btn btn-success btn-sm">
                            <i class="fas fa-search me-1"></i>Search
                        </a>
                    </div>
                </div>
            </div>

            <!-- Debug -->
            <div class="col-md-3 mb-3">
                <div class="card">
                    <div class="card-body text-center">
                        <i class="fas fa-bug fa-2x text-danger mb-3"></i>
                        <h6>Debug Tools</h6>
                        <p class="small text-muted">Cloudflare debug</p>
                        <a href="cloudflare_debug.php" class="btn btn-danger btn-sm">
                            <i class="fas fa-bug me-1"></i>Debug
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Links -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="alert alert-info">
                    <h5><i class="fas fa-lightbulb me-2"></i>Gợi ý</h5>
                    <p class="mb-2"><strong>Để cập nhật go99ii.com nhanh nhất:</strong></p>
                    <ol class="mb-0">
                        <li>Sử dụng <a href="quick_go99ii_dns.php" class="alert-link">Quick DNS</a> với IP 103.213.216.170</li>
                        <li>Hoặc dùng <a href="dns_simple.php?domain=go99ii.com&ip=103.213.216.170" class="alert-link">DNS Simple với pre-filled data</a></li>
                        <li>Kiểm tra trạng thái tại <a href="update_go99ii_dns.php" class="alert-link">Check Status</a></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    </div> <!-- End main-wrapper -->

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>