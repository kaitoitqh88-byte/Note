<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Navigation Demo - Modular Navigation System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/common.css" rel="stylesheet">
    <style>
        .code-block {
            background: #2d3748;
            color: #e2e8f0;
            padding: 20px;
            border-radius: 8px;
            font-family: 'Roboto Mono', monospace;
            font-size: 0.9rem;
            margin-bottom: 20px;
            overflow-x: auto;
        }
        .feature-card {
            transition: transform 0.2s;
            height: 100%;
        }
        .feature-card:hover {
            transform: translateY(-5px);
        }
        .demo-section {
            padding: 40px 0;
            border-bottom: 1px dashed #dee2e6;
        }
        .demo-section:last-child {
            border-bottom: none;
        }
        .highlight {
            background: linear-gradient(45deg, #ffeaa7, #fab1a0);
            padding: 2px 6px;
            border-radius: 4px;
            color: #2d3436;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'demo'; // Custom page identifier
    include 'includes/navigation.php'; 
    ?>

    <div class="container my-5">
        <!-- Header -->
        <div class="row">
            <div class="col-12 text-center mb-5">
                <h1 class="display-4 fw-bold text-primary">
                    <i class="fas fa-puzzle-piece text-warning"></i>
                    Modular Navigation System
                </h1>
                <p class="lead text-muted">Hệ thống menu chính đã được tách thành component reusable</p>
                <div class="alert alert-success mt-4">
                    <i class="fas fa-check-circle me-2"></i>
                    <strong>Hoàn thành!</strong> Menu chính đã được tách thành file riêng và có thể include vào bất kỳ trang nào.
                </div>
            </div>
        </div>

        <!-- Benefits Section -->
        <div class="demo-section">
            <h2 class="text-center mb-4">
                <i class="fas fa-star text-warning"></i> Lợi ích của hệ thống mới
            </h2>
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <div class="card feature-card border-primary">
                        <div class="card-body text-center">
                            <i class="fas fa-recycle fa-3x text-primary mb-3"></i>
                            <h5 class="card-title">Code Reusability</h5>
                            <p class="card-text">Menu chính được viết 1 lần, sử dụng ở nhiều nơi. Không còn duplicate code.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="card feature-card border-success">
                        <div class="card-body text-center">
                            <i class="fas fa-wrench fa-3x text-success mb-3"></i>
                            <h5 class="card-title">Easy Maintenance</h5>
                            <p class="card-text">Chỉ cần update 1 file để thay đổi menu trên toàn bộ website.</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-4 mb-4">
                    <div class="card feature-card border-info">
                        <div class="card-body text-center">
                            <i class="fas fa-sync-alt fa-3x text-info mb-3"></i>
                            <h5 class="card-title">Consistent UI</h5>
                            <p class="card-text">Đảm bảo menu consistent và active states chính xác trên mọi trang.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Implementation Section -->
        <div class="demo-section">
            <h2 class="text-center mb-4">
                <i class="fas fa-code text-primary"></i> Cách sử dụng
            </h2>
            
            <div class="row">
                <div class="col-lg-6 mb-4">
                    <h4><i class="fas fa-file-code text-success"></i> File Structure</h4>
                    <div class="code-block">
project/
├── includes/
│   └── navigation.php          <-- <span class="highlight">Navigation component</span>
├── assets/css/
│   └── common.css             <-- <span class="highlight">Shared styles</span>
├── homepage.php               <-- <span class="highlight">Updated to use include</span>
├── dashboard.php              <-- <span class="highlight">Updated to use include</span>
├── search.php                 <-- <span class="highlight">Updated to use include</span>
└── ...other files
                    </div>
                </div>
                
                <div class="col-lg-6 mb-4">
                    <h4><i class="fas fa-terminal text-info"></i> Usage Example</h4>
                    <div class="code-block">
&lt;!DOCTYPE html&gt;
&lt;html lang="vi"&gt;
&lt;head&gt;
    &lt;!-- Your head content --&gt;
&lt;/head&gt;
&lt;body&gt;
    &lt;?php 
    $currentPage = '<span class="highlight">your-page-id</span>'; 
    include '<span class="highlight">includes/navigation.php</span>'; 
    ?&gt;
    
    &lt;!-- Your page content --&gt;
&lt;/body&gt;
&lt;/html&gt;
                    </div>
                </div>
            </div>
        </div>

        <!-- Updated Files Section -->
        <div class="demo-section">
            <h2 class="text-center mb-4">
                <i class="fas fa-file-alt text-success"></i> Files đã được cập nhật
            </h2>
            
            <div class="row">
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-success text-white">
                            <h5 class="mb-0"><i class="fas fa-check-circle"></i> Core Navigation Files</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <i class="fas fa-puzzle-piece text-primary me-2"></i>
                                    <strong>includes/navigation.php</strong> - Main navigation component
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-paint-brush text-warning me-2"></i>
                                    <strong>assets/css/common.css</strong> - Enhanced navigation styles
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-6">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h5 class="mb-0"><i class="fas fa-sync-alt"></i> Updated Page Files</h5>
                        </div>
                        <div class="card-body">
                            <ul class="list-group list-group-flush">
                                <li class="list-group-item">
                                    <i class="fas fa-home text-info me-2"></i>homepage.php
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-tachometer-alt text-info me-2"></i>dashboard.php
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-search text-info me-2"></i>search.php
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-search text-primary me-2"></i>search.php
                                </li>
                                <li class="list-group-item">

                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-check-circle text-info me-2"></i>domain_status_checker.php
                                </li>
                                <li class="list-group-item">
                                    <i class="fas fa-search-plus text-info me-2"></i>CacheHandler.php (DNS Lookup)
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features Section -->
        <div class="demo-section">
            <h2 class="text-center mb-4">
                <i class="fas fa-magic text-purple"></i> Navigation Features
            </h2>
            
            <div class="row">
                <div class="col-md-6 mb-4">
                    <div class="card border-warning">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="fas fa-lightbulb"></i> Smart Active Detection</h5>
                        </div>
                        <div class="card-body">
                            <ul>
                                <li>Automatic active state detection dựa trên <code>$currentPage</code></li>
                                <li>Support cho complex routing (index.php?action=...)</li>
                                <li>Fallback detection từ filename</li>
                                <li>Custom page identifiers</li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 mb-4">
                    <div class="card border-info">
                        <div class="card-header bg-info text-white">
                            <h5 class="mb-0"><i class="fas fa-cogs"></i> Dynamic Menu Structure</h5>
                        </div>
                        <div class="card-body">
                            <ul>
                                <li>Configurable menu items trong arrays</li>
                                <li>Support cho dropdown menus với dividers</li>
                                <li>Icon integration với FontAwesome</li>
                                <li>Easy để thêm/remove menu items</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Test Links Section -->
        <div class="demo-section">
            <h2 class="text-center mb-4">
                <i class="fas fa-link text-primary"></i> Test Navigation
            </h2>
            <div class="text-center">
                <p class="mb-4">Click các link bên dưới để test navigation với active states:</p>
                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="homepage.php" class="btn btn-outline-primary">
                        <i class="fas fa-home me-1"></i>Homepage
                    </a>
                    <a href="dashboard.php" class="btn btn-outline-success">
                        <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                    </a>
                    <a href="search.php" class="btn btn-outline-info">
                        <i class="fas fa-search me-1"></i>Search
                    </a>
                    <a href="search.php" class="btn btn-outline-primary">
                        <i class="fas fa-globe me-1"></i>Zones
                    </a>

                    <a href="search.php" class="btn btn-outline-primary">
                        <i class="fas fa-search-plus me-1"></i>DNS Lookup
                    </a>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-5 pt-4 border-top">
            <h5 class="text-success">
                <i class="fas fa-check-circle me-2"></i>
                Menu chính đã được modularized thành công!
            </h5>
            <p class="text-muted">
                Bây giờ bạn có thể dễ dàng maintain và update navigation across toàn bộ DNS lookup application.
            </p>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>