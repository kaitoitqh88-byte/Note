<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick DNS Update - go99ii.com</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/common.css" rel="stylesheet">
    <style>
        .quick-form {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 20px;
            padding: 40px;
            color: white;
            text-align: center;
        }
        .domain-card {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 20px;
            margin: 20px 0;
            backdrop-filter: blur(10px);
        }
        .btn-glass {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
        }
        .btn-glass:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'dns-simple';
    include 'includes/navigation.php'; 
    ?>

    <div class="container mt-4">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="quick-form">
                    <h2><i class="fas fa-bolt me-2"></i>Quick DNS Update</h2>
                    <p class="lead">Cập nhật DNS cho go99ii.com</p>
                    
                    <div class="domain-card">
                        <h4><i class="fas fa-globe me-2"></i>go99ii.com</h4>
                        <p class="mb-3">Nhập IP address để cập nhật DNS records</p>
                        
                        <div class="row">
                            <div class="col-md-8 mx-auto">
                                <div class="input-group input-group-lg">
                                    <span class="input-group-text bg-transparent text-white border-white">
                                        <i class="fas fa-network-wired"></i>
                                    </span>
                                    <input type="text" 
                                           class="form-control bg-transparent text-white border-white" 
                                           id="ipInput" 
                                           placeholder="103.213.216.170"
                                           value="103.213.216.170">
                                </div>
                                <small class="text-light">IP cho A record (@) và CNAME record (www)</small>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <button onclick="updateDNS()" class="btn btn-glass btn-lg me-3">
                                <i class="fas fa-magic me-2"></i>Cập nhật DNS
                            </button>
                            <a href="dns_simple.php" class="btn btn-outline-light btn-lg">
                                <i class="fas fa-server me-2"></i>DNS Simple
                            </a>
                        </div>
                    </div>
                    
                    <div class="row mt-4">
                        <div class="col-md-6">
                            <h6><i class="fas fa-at me-1"></i> A Record</h6>
                            <small>go99ii.com → IP của bạn</small>
                        </div>
                        <div class="col-md-6">
                            <h6><i class="fas fa-link me-1"></i> CNAME Record</h6>
                            <small>www.go99ii.com → go99ii.com</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Quick Links -->
        <div class="row mt-4">
            <div class="col-12 text-center">
                <div class="card">
                    <div class="card-body">
                        <h5><i class="fas fa-tools me-2"></i>Quick Actions</h5>
                        <div class="btn-group" role="group">
                            <a href="update_go99ii_dns.php" class="btn btn-primary">
                                <i class="fas fa-cog me-1"></i>Check Status
                            </a>
                            <a href="dns_simple.php" class="btn btn-success">
                                <i class="fas fa-server me-1"></i>DNS Simple
                            </a>
                            <a href="dashboard.php" class="btn btn-info">
                                <i class="fas fa-tachometer-alt me-1"></i>Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function updateDNS() {
            const ip = document.getElementById('ipInput').value.trim();
            
            if (!ip) {
                alert('Vui lòng nhập IP address');
                return;
            }
            
            // Redirect to DNS Simple with pre-filled data
            const params = new URLSearchParams({
                domain: 'go99ii.com',
                ip: ip
            });
            
            window.location.href = `dns_simple.php?${params.toString()}`;
        }
        
        // Auto-fill form if coming from URL params
        document.addEventListener('DOMContentLoaded', function() {
            const urlParams = new URLSearchParams(window.location.search);
            const ip = urlParams.get('ip');
            
            if (ip) {
                document.getElementById('ipInput').value = ip;
            }
        });
    </script>
</body>
</html>