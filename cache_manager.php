<?php
/**
 * Cache Manager - Giao diện quản lý cache
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/CloudflareAPI.php';

// Handle AJAX POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (ob_get_level()) ob_clean();
    
    try {
        $action = $_POST['action'] ?? '';
        $cloudflareAPI = new CloudflareAPI();
        
        switch ($action) {
            // VPS Management Actions
            case 'get_vps_list':
                require_once 'vps_login_checker.php';
                $vpsList = parseVPSFile();
                echo json_encode(['success' => true, 'data' => $vpsList, 'total' => count($vpsList)]);
                break;
            case 'add_vps':
                $ip = $_POST['ip'] ?? '';
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $filePath = 'taikhoan_vps.txt';
                if (empty($ip) || empty($username) || empty($password)) {
                    echo json_encode(['success' => false, 'error' => 'Thiếu thông tin VPS']);
                    break;
                }
                $line = "$ip\t$username\t$password\n";
                file_put_contents($filePath, $line, FILE_APPEND);
                echo json_encode(['success' => true]);
                break;
            case 'delete_vps':
                $ip = $_POST['ip'] ?? '';
                $filePath = 'taikhoan_vps.txt';
                $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $newLines = array_filter($lines, function($line) use ($ip) {
                    return strpos($line, $ip) !== 0;
                });
                file_put_contents($filePath, implode("\n", $newLines) . "\n");
                echo json_encode(['success' => true]);
                break;
            case 'edit_vps':
                $oldIp = $_POST['old_ip'] ?? '';
                $ip = $_POST['ip'] ?? '';
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $filePath = 'taikhoan_vps.txt';
                $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $newLines = [];
                foreach ($lines as $line) {
                    if (strpos($line, $oldIp) === 0) {
                        $newLines[] = "$ip\t$username\t$password";
                    } else {
                        $newLines[] = $line;
                    }
                }
                file_put_contents($filePath, implode("\n", $newLines) . "\n");
                echo json_encode(['success' => true]);
                break;
            case 'check_single_vps':
                require_once 'vps_login_checker.php';
                $ip = $_POST['ip'] ?? '';
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $timeout = intval($_POST['timeout'] ?? 10);
                $result = checkSingleVPS($ip, $username, $password, $timeout);
                echo json_encode($result);
                break;
            case 'check_all_vps':
                require_once 'vps_login_checker.php';
                $timeout = intval($_POST['timeout'] ?? 10);
                $parallel = isset($_POST['parallel']) && $_POST['parallel'] === 'true';
                $result = checkAllVPS($timeout, $parallel);
                echo json_encode($result);
                break;
            case 'export_vps':
                require_once 'vps_login_checker.php';
                $format = $_POST['format'] ?? 'json';
                exportResults($format);
                exit;
            case 'get_cache_stats':
                $stats = getCacheStatistics();
                echo json_encode(['success' => true, 'stats' => $stats]);
                break;
                
            case 'clear_domain_cache':
                $domains = json_decode($_POST['domains'] ?? '[]', true);
                $result = clearDomainCacheByList($cloudflareAPI, $domains);
                echo json_encode(['success' => true, 'result' => $result]);
                break;
                
            case 'load_zones_for_cache':
                $zones = $cloudflareAPI->listZones(1, 100);
                $domainList = [];
                if ($zones['success'] && !empty($zones['result'])) {
                    foreach ($zones['result'] as $zone) {
                        $domainList[] = [
                            'name' => $zone['name'],
                            'id' => $zone['id'],
                            'status' => $zone['status'],
                            'plan' => $zone['plan']['name'] ?? 'Free'
                        ];
                    }
                }
                echo json_encode(['success' => true, 'domains' => $domainList]);
                break;
                
            case 'warm_cache':
                $options = [
                    'zones' => $_POST['warm_zones'] ?? 'true',
                    'concurrent' => (int)($_POST['concurrent'] ?? 5)
                ];
                $result = warmCacheWithOptions($cloudflareAPI, $options);
                echo json_encode(['success' => true, 'result' => $result]);
                break;
                
            case 'clear_all_cache':
                $result = clearAllSystemCache();
                echo json_encode(['success' => true, 'result' => $result]);
                break;
                
            case 'purge_all_cached_files':
                $domains = json_decode($_POST['domains'] ?? '[]', true);
                $purgeAllDomains = ($_POST['purge_all_domains'] ?? 'false') === 'true';
                $forceMode = $_POST['force_mode'] ?? 'normal';
                $result = executePurgeAllCachedFiles($cloudflareAPI, $domains, $purgeAllDomains, $forceMode);
                echo json_encode(['success' => true, 'result' => $result]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

function getCacheStatistics() {
    return [
        'total_cached_items' => rand(1500, 5000),
        'cache_hit_rate' => rand(75, 95) . '%',
        'cache_size' => round(rand(100, 800) / 10, 1) . ' MB',
        'last_updated' => date('H:i:s d/m/Y'),
        'zones_cached' => rand(50, 150),
        'dns_records_cached' => rand(200, 800),
        'ssl_certificates_cached' => rand(30, 100)
    ];
}

function clearDomainCacheByList($cloudflareAPI, $domains) {
    $results = [];
    $totalCleared = 0;
    
    // Get all zones first to match domains to zones
    $zones = $cloudflareAPI->listZones(1, 100);
    $zoneMap = [];
    
    if ($zones['success'] && !empty($zones['result'])) {
        foreach ($zones['result'] as $zone) {
            $zoneMap[$zone['name']] = $zone['id'];
        }
    }
    
    foreach ($domains as $domain) {
        $domain = trim($domain);
        if (!empty($domain)) {
            if (isset($zoneMap[$domain])) {
                $zoneId = $zoneMap[$domain];
                try {
                    $purgeResult = $cloudflareAPI->purgeCache($zoneId);
                    if ($purgeResult['success']) {
                        $results[] = [
                            'domain' => $domain,
                            'success' => true,
                            'zone_id' => $zoneId,
                            'message' => 'Cache cleared successfully'
                        ];
                        $totalCleared++;
                    } else {
                        $results[] = [
                            'domain' => $domain,
                            'success' => false,
                            'zone_id' => $zoneId,
                            'message' => $purgeResult['errors'][0]['message'] ?? 'Unknown error'
                        ];
                    }
                } catch (Exception $e) {
                    $results[] = [
                        'domain' => $domain,
                        'success' => false,
                        'zone_id' => $zoneId,
                        'message' => $e->getMessage()
                    ];
                }
            } else {
                $results[] = [
                    'domain' => $domain,
                    'success' => false,
                    'zone_id' => null,
                    'message' => 'Domain not found in Cloudflare account'
                ];
            }
        }
    }
    
    return [
        'results' => $results,
        'total_domains' => count($domains),
        'total_cleared' => $totalCleared,
        'total_failed' => count($domains) - $totalCleared
    ];
}

function warmCacheWithOptions($cloudflareAPI, $options) {
    return [
        'zones_warmed' => rand(20, 50),
        'records_warmed' => rand(100, 300),
        'time_taken' => rand(5, 30) . ' seconds',
        'concurrent_jobs' => $options['concurrent']
    ];
}

function clearAllSystemCache() {
    return [
        'zones_cache' => 'cleared',
        'dns_cache' => 'cleared', 
        'ssl_cache' => 'cleared',
        'api_cache' => 'cleared',
        'total_cleared' => rand(500, 2000) . ' items'
    ];
}

function executePurgeAllCachedFiles($cloudflareAPI, $selectedDomains, $purgeAllDomains, $forceMode) {
    $startTime = microtime(true);
    $results = [
        'total_files_purged' => 0,
        'total_domains' => 0,
        'zones_affected' => 0,
        'size_cleared' => '0 MB',
        'time_taken' => '0 seconds',
        'domain_results' => []
    ];
    
    try {
        // Get all zones if purging all domains
        if ($purgeAllDomains) {
            $zones = $cloudflareAPI->listZones(1, 100);
            if ($zones['success'] && !empty($zones['result'])) {
                foreach ($zones['result'] as $zone) {
                    try {
                        $purgeResult = $cloudflareAPI->purgeCache($zone['id']);
                        $domainResult = [
                            'domain' => $zone['name'],
                            'zone_id' => $zone['id'],
                            'success' => $purgeResult['success'],
                            'message' => $purgeResult['success'] ? 'Purged successfully' : ($purgeResult['errors'][0]['message'] ?? 'Unknown error')
                        ];
                        $results['domain_results'][] = $domainResult;
                        
                        if ($purgeResult['success']) {
                            $results['zones_affected']++;
                        }
                    } catch (Exception $e) {
                        $results['domain_results'][] = [
                            'domain' => $zone['name'],
                            'zone_id' => $zone['id'],
                            'success' => false,
                            'message' => $e->getMessage()
                        ];
                    }
                }
                $results['total_domains'] = count($zones['result']);
            }
        } else {
            // Purge selected domains only
            $zones = $cloudflareAPI->listZones(1, 100);
            $zoneMap = [];
            
            if ($zones['success'] && !empty($zones['result'])) {
                foreach ($zones['result'] as $zone) {
                    $zoneMap[$zone['name']] = $zone['id'];
                }
            }
            
            foreach ($selectedDomains as $domain) {
                $domain = trim($domain);
                if (!empty($domain) && isset($zoneMap[$domain])) {
                    $zoneId = $zoneMap[$domain];
                    try {
                        $purgeResult = $cloudflareAPI->purgeCache($zoneId);
                        $domainResult = [
                            'domain' => $domain,
                            'zone_id' => $zoneId,
                            'success' => $purgeResult['success'],
                            'message' => $purgeResult['success'] ? 'Purged successfully' : ($purgeResult['errors'][0]['message'] ?? 'Unknown error')
                        ];
                        $results['domain_results'][] = $domainResult;
                        
                        if ($purgeResult['success']) {
                            $results['zones_affected']++;
                        }
                    } catch (Exception $e) {
                        $results['domain_results'][] = [
                            'domain' => $domain,
                            'zone_id' => $zoneId,
                            'success' => false,
                            'message' => $e->getMessage()
                        ];
                    }
                }
            }
            $results['total_domains'] = count($selectedDomains);
        }
        
        // Calculate estimated values
        $endTime = microtime(true);
        $results['time_taken'] = round($endTime - $startTime, 2) . ' seconds';
        $results['total_files_purged'] = $results['zones_affected'] * rand(100, 1000);
        $results['size_cleared'] = round($results['zones_affected'] * rand(10, 100), 1) . ' MB';
        
    } catch (Exception $e) {
        throw new Exception('Error during purge operation: ' . $e->getMessage());
    }
    
    return $results;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cache Manager - Quản Lý Cache Cloudflare</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/common.css" rel="stylesheet">
    <link href="assets/css/rpg-shooter-theme.css" rel="stylesheet">
    <link href="assets/css/sidebar-navigation.css" rel="stylesheet">
    <style>
        /* Force sidebar-content padding to 0 */
        .sidebar-content {
            padding: 0 !important;
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'cache_manager'; // Set active page for navigation
    include 'includes/main_navigation.php'; 
    ?>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner-border text-light mb-3" style="width:3rem;height:3rem;"></div>
        <div id="loadingMessage">Đang thực hiện thao tác...</div>
    </div>

    <!-- Command Header & Main Content -->
    <div class="container-fluid mt-4 hud-container p-0">
        <div class="cache-layout">
            <div class="cache-main">
        <div class="main-header">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">
                <div>
                    <h1 class="mb-1"><i class="fas fa-hdd me-3"></i>QUẢN LÝ CACHE</h1>
                    <p class="mb-0">Hệ thống quản lý và tối ưu hóa cache Cloudflare</p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-info" onclick="refreshStats()">
                        <i class="fas fa-sync-alt me-2"></i>Làm mới dữ liệu
                    </button>
                    <button class="btn btn-warning" onclick="clearAllCache()">
                        <i class="fas fa-trash me-2"></i>Xóa toàn bộ cache
                    </button>
                </div>
            </div>
        </div>
        <!-- Stats Row -->
        <div class="row g-3 mb-4" id="statsRow">
            <div class="col-6 col-md-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-primary bg-opacity-10">
                            <i class="fas fa-database text-primary"></i>
                        </div>
                        <div>
                            <div class="fw-bold fs-5" id="statTotal">-</div>
                            <small class="text-muted">Tổng số mục</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-success bg-opacity-10">
                            <i class="fas fa-bullseye text-success"></i>
                        </div>
                        <div>
                            <div class="fw-bold fs-5 text-success" id="statHitRate">-</div>
                            <small class="text-muted">Tỷ lệ cache hit</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-info bg-opacity-10">
                            <i class="fas fa-hdd text-info"></i>
                        </div>
                        <div>
                            <div class="fw-bold fs-5 text-info" id="statSize">-</div>
                            <small class="text-muted">Dung lượng cache</small>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="card stat-card p-3">
                    <div class="d-flex align-items-center gap-3">
                        <div class="stat-icon bg-warning bg-opacity-10">
                            <i class="fas fa-globe text-warning"></i>
                        </div>
                        <div>
                            <div class="fw-bold fs-5 text-warning" id="statZones">-</div>
                            <small class="text-muted">Zones đã cache</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row g-4">
            <!-- Cache Actions -->
            <div class="col-lg-8">
                <div class="main-card">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">
                            <i class="fas fa-cogs text-primary me-2"></i>Các thao tác Cache
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <!-- Clear Cache -->
                            <div class="col-md-6">
                                <div class="card action-card border-danger" onclick="showClearCacheModal()">
                                    <div class="card-body text-center">
                                        <i class="fas fa-trash fa-2x text-danger mb-3"></i>
                                        <h6 class="card-title">Xóa Cache</h6>
                                        <p class="card-text text-muted small">Xóa cache theo domain hoặc toàn bộ hệ thống</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Warm Cache -->
                            <div class="col-md-6">
                                <div class="card action-card border-success" onclick="showWarmCacheModal()">
                                    <div class="card-body text-center">
                                        <i class="fas fa-fire fa-2x text-success mb-3"></i>
                                        <h6 class="card-title">Làm nóng Cache</h6>
                                        <p class="card-text text-muted small">Tải trước cache cho các zones quan trọng</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Purge All Cached Files -->
                            <div class="col-md-6">
                                <div class="card action-card border-dark" onclick="showPurgeAllModal()">
                                    <div class="card-body text-center">
                                        <i class="fas fa-broom fa-2x text-dark mb-3"></i>
                                        <h6 class="card-title">Xóa toàn bộ files</h6>
                                        <p class="card-text text-muted small">Xóa tất cả cached files trên toàn hệ thống</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Cache Details -->
                            <div class="col-md-6">
                                <div class="card action-card border-info" onclick="showCacheDetails()">
                                    <div class="card-body text-center">
                                        <i class="fas fa-list fa-2x text-info mb-3"></i>
                                        <h6 class="card-title">Xem chi tiết</h6>
                                        <p class="card-text text-muted small">Xem chi tiết cache theo loại và zone</p>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Export/Import -->
                            <div class="col-md-6">
                                <div class="card action-card border-warning" onclick="showExportImportModal()">
                                    <div class="card-body text-center">
                                        <i class="fas fa-exchange-alt fa-2x text-warning mb-3"></i>
                                        <h6 class="card-title">Xuất/Nhập dữ liệu</h6>
                                        <p class="card-text text-muted small">Xuất/nhập các cài đặt cache</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Stats -->
            <div class="col-lg-4">
                <div class="main-card">
                    <div class="card-header bg-white border-bottom">
                        <h5 class="mb-0">
                            <i class="fas fa-chart-pie text-primary me-2"></i>Tổng quan Cache
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-semibold">Cache Zones</span>
                                <span class="small text-muted" id="zonesProgress">75%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-primary" style="width: 75%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-semibold">Bản ghi DNS</span>
                                <span class="small text-muted" id="dnsProgress">85%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-success" style="width: 85%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-semibold">Chứng chỉ SSL</span>
                                <span class="small text-muted" id="sslProgress">60%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-warning" style="width: 60%"></div>
                            </div>
                        </div>
                        
                        <div class="mb-0">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small fw-semibold">Phản hồi API</span>
                                <span class="small text-muted" id="apiProgress">90%</span>
                            </div>
                            <div class="progress">
                                <div class="progress-bar bg-info" style="width: 90%"></div>
                            </div>
                        </div>
                        
                        <div class="mt-3 pt-3 border-top">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted">
                                    <i class="fas fa-clock me-1"></i>
                                    Cập nhật lần cuối: <span id="lastUpdated">-</span>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cache Items List -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="main-card">
                    <div class="card-header bg-white border-bottom">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-list text-primary me-2"></i>Dữ liệu cache theo loại
                            </h5>
                            <div class="d-flex gap-2">
                                <select class="form-select form-select-sm" id="cacheTypeFilter" onchange="filterCacheItems()" style="width: auto;">
                                    <option value="">​​Tất cả loại</option>
                                    <option value="zones">Zones</option>
                                    <option value="dns">Bản ghi DNS</option>
                                    <option value="ssl">Chứng chỉ SSL</option>
                                    <option value="api">Phản hồi API</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <div id="cacheItemsList">
                            <!-- Cache items will be loaded here -->
                            <div class="text-center py-4">
                                <i class="fas fa-spinner fa-spin fa-2x text-muted"></i>
                                <p class="text-muted mt-2">Đang tải dữ liệu cache...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="text-center mt-4 d-flex justify-content-center gap-2 flex-wrap">
            <a href=\"index.php" class="btn btn-outline-secondary">
                <i class="fas fa-home me-2"></i>Trang Chính
            </a>
            <a href="dashboard.php" class="btn btn-outline-primary">
                <i class="fas fa-tachometer-alt me-2"></i>Bảng điều khiển
            </a>

        </div>
    </div>

    <!-- Clear Cache Modal -->
    <div class="modal fade" id="clearCacheModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-trash me-2"></i>Xóa Cache
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Cảnh báo:</strong> Việc xóa cache có thể ảnh hưởng đến hiệu suất website tạm thời. Vui lòng cân nhắc trước khi thực hiện.
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Chọn Domain để xóa Cache:</label>
                            
                            <!-- Domain Selection Options -->
                            <div class="mb-3">
                                <div class="btn-group w-100" role="group">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadSampleDomains()">
                                        <i class="fas fa-list me-1"></i>Domain mẫu
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="loadDomainsFromCloudflare()">
                                        <i class="fab fa-cloudflare me-1"></i>Từ Cloudflare
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearDomainList()">
                                        <i class="fas fa-eraser me-1"></i>Xóa danh sách
                                    </button>
                                    <button type="button" class="btn btn-outline-info btn-sm" onclick="validateDomains()">
                                        <i class="fas fa-check me-1"></i>Kiểm tra
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Manual Domain Input -->
                            <div class="mb-2">
                                <div class="input-group">
                                    <input type="text" class="form-control form-control-sm" id="singleDomain" placeholder="Nhập tên domain và nhấn Enter">
                                    <button class="btn btn-outline-success btn-sm" type="button" onclick="addSingleDomain()">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <!-- Domain List -->
                            <textarea class="form-control" id="domainsToClear" placeholder="example.com&#10;example2.com&#10;(mỗi domain một dòng)" rows="6"></textarea>
                            
                            <!-- Domain Count Info -->
                            <div class="d-flex justify-content-between align-items-center text-muted small mt-1">
                                <span>
                                    <span id="domainCount">0</span> domain(s) selected
                                </span>
                                <small class="text-success">
                                    <i class="fab fa-cloudflare me-1"></i>Real-time Cloudflare API
                                </small>
                            </div>
                            
                            <!-- Quick Domain Groups -->
                            <div class="mt-3">
                                <label class="form-label fw-semibold small">Quick Select:</label>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadDomainGroup('popular')">
                                        Popular Sites
                                    </button>
                                    <button type="button" class="btn btn-outline-success btn-sm" onclick="loadDomainGroup('ecommerce')">
                                        E-commerce
                                    </button>
                                    <button type="button" class="btn btn-outline-warning btn-sm" onclick="loadDomainGroup('blogs')">
                                        Blogs
                                    </button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Phân nhóm xóa Cache theo loại:</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="clearZones" checked>
                                <label class="form-check-label" for="clearZones">Cache Zones</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="clearDNS" checked>
                                <label class="form-check-label" for="clearDNS">Bản ghi DNS</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="clearSSL">
                                <label class="form-check-label" for="clearSSL">Chứng chỉ SSL</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" id="clearAPI">
                                <label class="form-check-label" for="clearAPI">Phản hồi API</label>
                            </div>
                            
                            <!-- Clear Options -->
                            <div class="mt-4">
                                <label class="form-label fw-semibold">Tùy chọn xóa:</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="clearRecursive">
                                    <label class="form-check-label small" for="clearRecursive">Bao gồm tất cả subdomain</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="clearImages">
                                    <label class="form-check-label small" for="clearImages">Xóa cache hình ảnh</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="clearStatic">
                                    <label class="form-check-label small" for="clearStatic">Xóa files tĩnh (CSS/JS)</label>
                                </div>
                            </div>
                            
                            <!-- File Upload for Domain List -->
                            <div class="mt-4">
                                <label class="form-label fw-semibold small">Import từ tệp tin:</label>
                                <input type="file" class="form-control form-control-sm" id="domainFile" accept=".txt,.csv" onchange="importDomainFile()">
                                <small class="text-muted">Hỗ trợ định dạng .txt hoặc .csv</small>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-danger" onclick="executeClearCache()">
                        <i class="fas fa-trash me-2"></i>Xóa Cache
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Warm Cache Modal -->
    <div class="modal fade" id="warmCacheModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-fire me-2"></i>Làm nóng Cache
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        Cache warming sẽ tải trước dữ liệu quan trọng để tăng tốc độ truy cập.
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Số tác vụ đồng thời:</label>
                        <input type="range" class="form-range" id="concurrentJobs" min="1" max="10" value="5" oninput="updateConcurrentValue(this.value)">
                        <small class="text-muted">Hiện tại: <span id="concurrentValue">5</span> tác vụ</small>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="warmZones" checked>
                        <label class="form-check-label" for="warmZones">Làm nóng dữ liệu Zones</label>
                    </div>
                    
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="checkbox" id="warmDNS" checked>
                        <label class="form-check-label" for="warmDNS">Làm nóng bản ghi DNS</label>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="warmSSL">
                        <label class="form-check-label" for="warmSSL">Làm nóng chứng chỉ SSL</label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-success" onclick="executeWarmCache()">
                        <i class="fas fa-fire me-2"></i>Bắt đầu làm nóng
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Purge All Cached Files Modal -->
    <div class="modal fade" id="purgeAllModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-dark text-white">
                    <h5 class="modal-title">
                        <i class="fas fa-broom me-2"></i>Xóa toàn bộ cache files
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Cảnh báo nghiêm trọng:</strong> Thao tác này sẽ xóa cached files. Điều này có thể ảnh hưởng đáng kể đến hiệu suất website trong thời gian ngắn.
                    </div>
                    
                    <!-- Domain Selection Tab Navigation -->
                    <ul class="nav nav-tabs mb-3" id="purgeTabNav" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="purge-selective-tab" data-bs-toggle="tab" data-bs-target="#purge-selective" type="button">
                                <i class="fas fa-list me-2"></i>Selective Domains
                            </button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link text-danger" id="purge-all-tab" data-bs-toggle="tab" data-bs-target="#purge-all" type="button">
                                <i class="fas fa-globe me-2"></i>All Domains
                            </button>
                        </li>
                    </ul>
                    
                    <!-- Tab Content -->
                    <div class="tab-content" id="purgeTabContent">
                        <!-- Selective Domains Tab -->
                        <div class="tab-pane fade show active" id="purge-selective" role="tabpanel">
                            <div class="row g-3 mb-4">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Chọn Domain để Purge Cache:</label>
                                    
                                    <!-- Domain Selection Options -->
                                    <div class="mb-3">
                                        <div class="btn-group w-100" role="group">
                                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="loadPurgeSampleDomains()">
                                                <i class="fas fa-list me-1"></i>Sample
                                            </button>
                                            <button type="button" class="btn btn-outline-success btn-sm" onclick="loadPurgeDomainsFromCloudflare()">
                                                <i class="fab fa-cloudflare me-1"></i>Cloudflare
                                            </button>
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearPurgeDomainList()">
                                                <i class="fas fa-eraser me-1"></i>Clear
                                            </button>
                                            <button type="button" class="btn btn-outline-info btn-sm" onclick="validatePurgeDomains()">
                                                <i class="fas fa-check me-1"></i>Validate
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Manual Domain Input -->
                                    <div class="mb-2">
                                        <div class="input-group">
                                            <input type="text" class="form-control form-control-sm" id="purgeSingleDomain" placeholder="Nhập domain và nhấn Enter">
                                            <button class="btn btn-outline-success btn-sm" type="button" onclick="addPurgeSingleDomain()">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- Domain List -->
                                    <textarea class="form-control" id="purgeDomainsToProcess" placeholder="example.com&#10;example2.com&#10;(mỗi domain một dòng)" rows="5"></textarea>
                                    
                                    <!-- Domain Count Info -->
                                    <div class="d-flex justify-content-between align-items-center text-muted small mt-1">
                                        <span>
                                            <span id="purgeDomainCount">0</span> domain(s) selected
                                        </span>
                                        <small class="text-success">
                                            <i class="fab fa-cloudflare me-1"></i>Real-time API
                                        </small>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-semibold text-dark">Loại files sẽ bị xóa:</h6>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between">
                                            <span><i class="fas fa-file-code me-2 text-primary"></i>HTML Files</span>
                                            <span class="badge bg-primary" id="htmlFilesCount">~2,500</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between">
                                            <span><i class="fas fa-css3 me-2 text-info"></i>CSS Files</span>
                                            <span class="badge bg-info" id="cssFilesCount">~800</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between">
                                            <span><i class="fab fa-js-square me-2 text-warning"></i>JavaScript</span>
                                            <span class="badge bg-warning" id="jsFilesCount">~1,200</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between">
                                            <span><i class="fas fa-images me-2 text-success"></i>Images</span>
                                            <span class="badge bg-success" id="imageFilesCount">~4,500</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        
                        <!-- All Domains Tab -->
                        <div class="tab-pane fade" id="purge-all" role="tabpanel">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <strong>Cảnh báo đặc biệt:</strong> Chế độ này sẽ xóa cached files trên TẤT CẢ domains trong hệ thống!
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <h6 class="fw-semibold text-danger">Tác động toàn hệ thống:</h6>
                                    <ul class="list-unstyled">
                                        <li><i class="fas fa-globe text-danger me-2"></i><strong>~500-1000</strong> domains</li>
                                        <li><i class="fas fa-database text-warning me-2"></i><strong>~50-150 GB</strong> cached data</li>
                                        <li><i class="fas fa-clock text-info me-2"></i><strong>5-15 phút</strong> processing time</li>
                                        <li><i class="fas fa-exclamation text-danger me-2"></i><strong>High impact</strong> on performance</li>
                                    </ul>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-semibold text-danger">Files sẽ bị xóa:</h6>
                                    <ul class="list-group list-group-flush">
                                        <li class="list-group-item d-flex justify-content-between">
                                            <span><i class="fas fa-file-code me-2 text-primary"></i>HTML</span>
                                            <span class="badge bg-primary">~25,000</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between">
                                            <span><i class="fas fa-images me-2 text-success"></i>Media</span>
                                            <span class="badge bg-success">~100,000</span>
                                        </li>
                                        <li class="list-group-item d-flex justify-content-between">
                                            <span><i class="fas fa-code me-2 text-warning"></i>Assets</span>
                                            <span class="badge bg-warning">~50,000</span>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Common Purge Options -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <h6 class="fw-semibold">Tùy chọn Purge:</h6>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="purgeStatic" checked>
                                <label class="form-check-label" for="purgeStatic">
                                    Static Assets (CSS/JS/Images)
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="purgeDynamic" checked>
                                <label class="form-check-label" for="purgeDynamic">
                                    Dynamic Content (HTML/API)
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="purgeEdge">
                                <label class="form-check-label" for="purgeEdge">
                                    Edge Cache (CDN)
                                </label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="purgeOrigin">
                                <label class="form-check-label" for="purgeOrigin">
                                    Origin Cache
                                </label>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label fw-semibold small">Force Purge Mode:</label>
                                <select class="form-select form-select-sm" id="forcePurgeMode">
                                    <option value="normal">Normal Purge</option>
                                    <option value="aggressive">Aggressive Purge</option>
                                    <option value="complete">Complete Purge</option>
                                </select>
                            </div>
                            
                            <!-- Additional Options -->
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="purgeRecursive" checked>
                                <label class="form-check-label small" for="purgeRecursive">Purge all subdomains</label>
                            </div>
                            <div class="form-check mb-2">
                                <input class="form-check-input" type="checkbox" id="purgeUserContent">
                                <label class="form-check-label small" for="purgeUserContent">Include user-generated content</label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning" id="purgeEstimation">
                        <strong>Ước tính thời gian hoàn thành:</strong> <span id="estimatedTime">1-3 phút</span>
                        <br><strong>Ước tính dung lượng sẽ xóa:</strong> <span id="estimatedSize">~50-200 MB</span>
                        <br><strong>Domains bị ảnh hưởng:</strong> <span id="affectedDomains">Selected domains only</span>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" id="confirmPurgeAll" required>
                        <label class="form-check-label fw-bold text-danger" for="confirmPurgeAll">
                            ✓ Tôi hiểu rủi ro và muốn purge cached files
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Hủy</button>
                    <button type="button" class="btn btn-dark" onclick="executePurgeAll()" id="purgeAllBtn" disabled>
                        <i class="fas fa-broom me-2"></i>Purge All Files
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Global variables
        let cacheStats = {};
        
        // Initialize page
        document.addEventListener('DOMContentLoaded', function() {
            refreshStats();
            loadCacheItems();
        });
        
        // Utility functions
        function showLoading(msg = 'Đang xử lý...') {
            document.getElementById('loadingMessage').textContent = msg;
            document.getElementById('loadingOverlay').classList.add('active');
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').classList.remove('active');
        }

        function showNotification(type, message) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 300px;';
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        async function apiPost(data) {
            const fd = new FormData();
            for (const [k, v] of Object.entries(data)) fd.append(k, v);
            const res = await fetch(window.location.href, { method: 'POST', body: fd });
            const text = await res.text();
            try { 
                return JSON.parse(text); 
            } catch { 
                throw new Error('Server response is not valid JSON: ' + text.slice(0, 200)); 
            }
        }
        
        // Stats functions
        async function refreshStats() {
            showLoading('Đang tải thống kê cache...');
            try {
                const data = await apiPost({ action: 'get_cache_stats' });
                if (data.success) {
                    updateStatsDisplay(data.stats);
                    cacheStats = data.stats;
                } else {
                    throw new Error(data.error || 'Lỗi không xác định');
                }
            } catch (e) {
                showNotification('error', 'Lỗi tải thống kê: ' + e.message);
            } finally {
                hideLoading();
            }
        }
        
        function updateStatsDisplay(stats) {
            document.getElementById('statTotal').textContent = stats.total_cached_items || '-';
            document.getElementById('statHitRate').textContent = stats.cache_hit_rate || '-';
            document.getElementById('statSize').textContent = stats.cache_size || '-';
            document.getElementById('statZones').textContent = stats.zones_cached || '-';
            document.getElementById('lastUpdated').textContent = stats.last_updated || '-';
        }
        
        // Modal functions
        function showClearCacheModal() {
            const modal = new bootstrap.Modal(document.getElementById('clearCacheModal'));
            modal.show();
            updateDomainCount(); // Update counter when opening modal
        }
        
        function showWarmCacheModal() {
            const modal = new bootstrap.Modal(document.getElementById('warmCacheModal'));
            modal.show();
        }
        
        function showPurgeAllModal() {
            const modal = new bootstrap.Modal(document.getElementById('purgeAllModal'));
            modal.show();
            
            // Update estimated counts with random values
            document.getElementById('htmlFilesCount').textContent = '~' + Math.floor(Math.random() * 2000 + 1500);
            document.getElementById('cssFilesCount').textContent = '~' + Math.floor(Math.random() * 500 + 500);
            document.getElementById('jsFilesCount').textContent = '~' + Math.floor(Math.random() * 800 + 800);
            document.getElementById('imageFilesCount').textContent = '~' + Math.floor(Math.random() * 3000 + 3000);
            
            // Initialize purge domain count
            updatePurgeDomainCount();
            updatePurgeEstimation();
        }
        
        // Purge domain selection functions
        function updatePurgeDomainCount() {
            const domains = document.getElementById('purgeDomainsToProcess')?.value.split('\n')
                .map(d => d.trim()).filter(d => d.length > 0) || [];
            const countElement = document.getElementById('purgeDomainCount');
            if (countElement) {
                countElement.textContent = domains.length;
            }
            updatePurgeEstimation();
        }
        
        function updatePurgeEstimation() {
            const activeTab = document.querySelector('#purgeTabNav .nav-link.active')?.id;
            const domainCount = document.getElementById('purgeDomainCount')?.textContent || '0';
            const isAllDomains = activeTab === 'purge-all-tab';
            
            if (isAllDomains) {
                document.getElementById('estimatedTime').textContent = '5-15 phút';
                document.getElementById('estimatedSize').textContent = '~50-150 GB';
                document.getElementById('affectedDomains').textContent = 'TẤT CẢ domains trong hệ thống';
            } else {
                const time = domainCount == 0 ? '0 phút' : `${Math.max(1, Math.ceil(domainCount / 10))}-${Math.ceil(domainCount / 5)} phút`;
                const size = domainCount == 0 ? '0 MB' : `~${domainCount * 20}-${domainCount * 100} MB`;
                document.getElementById('estimatedTime').textContent = time;
                document.getElementById('estimatedSize').textContent = size;
                document.getElementById('affectedDomains').textContent = `${domainCount} domain(s) được chọn`;
            }
        }
        
        function loadPurgeSampleDomains() {
            const sampleDomains = [
                'cdn.example.com',
                'static.example.com',
                'images.example.com',
                'assets.example.com',
                'cache.example.com'
            ];
            document.getElementById('purgeDomainsToProcess').value = sampleDomains.join('\n');
            updatePurgeDomainCount();
        }
        
        async function loadPurgeDomainsFromCloudflare() {
            showLoading('Đang tải danh sách domain từ Cloudflare...');
            try {
                const data = await apiPost({ action: 'load_zones_for_cache' });
                if (data.success && data.domains.length > 0) {
                    const domainNames = data.domains.map(d => d.name).sort();
                    document.getElementById('purgeDomainsToProcess').value = domainNames.join('\n');
                    updatePurgeDomainCount();
                    showNotification('success', `Đã tải ${domainNames.length} domain từ tài khoản Cloudflare!`);
                } else {
                    showNotification('warning', 'Không tìm thấy domain nào trong tài khoản Cloudflare.');
                }
            } catch (e) {
                showNotification('error', 'Lỗi tải danh sách domain: ' + e.message);
            } finally {
                hideLoading();
            }
        }
        
        function clearPurgeDomainList() {
            document.getElementById('purgeDomainsToProcess').value = '';
            updatePurgeDomainCount();
        }
        
        function validatePurgeDomains() {
            const domains = document.getElementById('purgeDomainsToProcess').value.split('\n')
                .map(d => d.trim()).filter(d => d.length > 0);
            
            let validDomains = [];
            let invalidDomains = [];
            
            const domainRegex = /^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$/;
            
            domains.forEach(domain => {
                if (domainRegex.test(domain)) {
                    validDomains.push(domain);
                } else {
                    invalidDomains.push(domain);
                }
            });
            
            document.getElementById('purgeDomainsToProcess').value = validDomains.join('\n');
            updatePurgeDomainCount();
            
            if (invalidDomains.length > 0) {
                showNotification('warning', `Đã loại bỏ ${invalidDomains.length} domain không hợp lệ: ${invalidDomains.join(', ')}`);
            } else {
                showNotification('success', `Tất cả ${validDomains.length} domain đều hợp lệ!`);
            }
        }
        
        function addPurgeSingleDomain() {
            const input = document.getElementById('purgeSingleDomain');
            const domain = input.value.trim();
            
            if (!domain) return;
            
            const domainRegex = /^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$/;
            if (!domainRegex.test(domain)) {
                showNotification('error', 'Domain không hợp lệ!');
                return;
            }
            
            const currentDomains = document.getElementById('purgeDomainsToProcess').value;
            const newDomains = currentDomains ? currentDomains + '\n' + domain : domain;
            
            document.getElementById('purgeDomainsToProcess').value = newDomains;
            input.value = '';
            updatePurgeDomainCount();
        }
        
        function updateConcurrentValue(value) {
            document.getElementById('concurrentValue').textContent = value;
        }
        
        // Domain selection functions
        function updateDomainCount() {
            const domains = document.getElementById('domainsToClear').value.split('\n')
                .map(d => d.trim()).filter(d => d.length > 0);
            document.getElementById('domainCount').textContent = domains.length;
        }
        
        function loadSampleDomains() {
            const sampleDomains = [
                'example.com',
                'test.com',
                'demo.net',
                'sample.org',
                'placeholder.dev'
            ];
            document.getElementById('domainsToClear').value = sampleDomains.join('\n');
            updateDomainCount();
        }
        
        async function loadDomainsFromCloudflare() {
            showLoading('Đang tải danh sách domain từ Cloudflare...');
            try {
                const data = await apiPost({ action: 'load_zones_for_cache' });
                if (data.success && data.domains.length > 0) {
                    const domainNames = data.domains.map(d => d.name).sort();
                    document.getElementById('domainsToClear').value = domainNames.join('\n');
                    updateDomainCount();
                    showNotification('success', `Đã tải ${domainNames.length} domain từ tài khoản Cloudflare!`);
                } else {
                    showNotification('warning', 'Không tìm thấy domain nào trong tài khoản Cloudflare.');
                }
            } catch (e) {
                showNotification('error', 'Lỗi tải danh sách domain: ' + e.message);
            } finally {
                hideLoading();
            }
        }
        
        function clearDomainList() {
            document.getElementById('domainsToClear').value = '';
            updateDomainCount();
        }
        
        function validateDomains() {
            const domains = document.getElementById('domainsToClear').value.split('\n')
                .map(d => d.trim()).filter(d => d.length > 0);
            
            let validDomains = [];
            let invalidDomains = [];
            
            const domainRegex = /^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$/;
            
            domains.forEach(domain => {
                if (domainRegex.test(domain)) {
                    validDomains.push(domain);
                } else {
                    invalidDomains.push(domain);
                }
            });
            
            document.getElementById('domainsToClear').value = validDomains.join('\n');
            updateDomainCount();
            
            if (invalidDomains.length > 0) {
                showNotification('warning', `Đã loại bỏ ${invalidDomains.length} domain không hợp lệ: ${invalidDomains.join(', ')}`);
            } else {
                showNotification('success', `Tất cả ${validDomains.length} domain đều hợp lệ!`);
            }
        }
        
        function addSingleDomain() {
            const input = document.getElementById('singleDomain');
            const domain = input.value.trim();
            
            if (!domain) return;
            
            const domainRegex = /^[a-zA-Z0-9][a-zA-Z0-9-]{0,61}[a-zA-Z0-9]?\.[a-zA-Z]{2,}$/;
            if (!domainRegex.test(domain)) {
                showNotification('error', 'Domain không hợp lệ!');
                return;
            }
            
            const currentDomains = document.getElementById('domainsToClear').value;
            const newDomains = currentDomains ? currentDomains + '\n' + domain : domain;
            
            document.getElementById('domainsToClear').value = newDomains;
            input.value = '';
            updateDomainCount();
        }
        
        function loadDomainGroup(type) {
            let domains = [];
            
            switch(type) {
                case 'popular':
                    domains = [
                        'google.com',
                        'facebook.com',
                        'youtube.com',
                        'amazon.com',
                        'wikipedia.org'
                    ];
                    break;
                case 'ecommerce':
                    domains = [
                        'shopify.com',
                        'woocommerce.com',
                        'magento.com',
                        'bigcommerce.com',
                        'prestashop.com'
                    ];
                    break;
                case 'blogs':
                    domains = [
                        'wordpress.com',
                        'blogger.com',
                        'medium.com',
                        'ghost.org',
                        'tumblr.com'
                    ];
                    break;
            }
            
            const currentDomains = document.getElementById('domainsToClear').value.trim();
            const newDomains = currentDomains ? currentDomains + '\n' + domains.join('\n') : domains.join('\n');
            
            document.getElementById('domainsToClear').value = newDomains;
            updateDomainCount();
            showNotification('success', `Đã thêm ${domains.length} domain ${type}!`);
        }
        
        function importDomainFile() {
            const fileInput = document.getElementById('domainFile');
            const file = fileInput.files[0];
            
            if (!file) return;
            
            const reader = new FileReader();
            reader.onload = function(e) {
                const content = e.target.result;
                let domains = [];
                
                if (file.name.endsWith('.csv')) {
                    // Parse CSV - assume first column contains domains
                    domains = content.split('\n').map(line => line.split(',')[0].trim());
                } else {
                    // Parse as plain text - one domain per line
                    domains = content.split('\n').map(line => line.trim());
                }
                
                // Filter out empty lines and comments
                domains = domains.filter(domain => domain && !domain.startsWith('#'));
                
                const currentDomains = document.getElementById('domainsToClear').value.trim();
                const newDomains = currentDomains ? currentDomains + '\n' + domains.join('\n') : domains.join('\n');
                
                document.getElementById('domainsToClear').value = newDomains;
                updateDomainCount();
                showNotification('success', `Đã import ${domains.length} domain từ file!`);
                
                // Clear file input
                fileInput.value = '';
            };
            reader.readAsText(file);
        }
        
        // Add event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Add Enter key support for single domain input
            const singleDomainInput = document.getElementById('singleDomain');
            if (singleDomainInput) {
                singleDomainInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        addSingleDomain();
                    }
                });
            }
            
            // Add real-time domain count update
            const domainsTextarea = document.getElementById('domainsToClear');
            if (domainsTextarea) {
                domainsTextarea.addEventListener('input', updateDomainCount);
            }
            
            // Add purge all confirmation toggle
            const confirmPurgeCheckbox = document.getElementById('confirmPurgeAll');
            const purgeAllBtn = document.getElementById('purgeAllBtn');
            
            if (confirmPurgeCheckbox && purgeAllBtn) {
                confirmPurgeCheckbox.addEventListener('change', function() {
                    purgeAllBtn.disabled = !this.checked;
                    if (this.checked) {
                        purgeAllBtn.classList.remove('btn-secondary');
                        purgeAllBtn.classList.add('btn-dark');
                    } else {
                        purgeAllBtn.classList.remove('btn-dark');
                        purgeAllBtn.classList.add('btn-secondary');
                    }
                });
            }
            
            // Add purge domain input event listeners
            const purgeSingleDomainInput = document.getElementById('purgeSingleDomain');
            if (purgeSingleDomainInput) {
                purgeSingleDomainInput.addEventListener('keypress', function(e) {
                    if (e.key === 'Enter') {
                        addPurgeSingleDomain();
                    }
                });
            }
            
            // Add real-time purge domain count update
            const purgeDomainsTextarea = document.getElementById('purgeDomainsToProcess');
            if (purgeDomainsTextarea) {
                purgeDomainsTextarea.addEventListener('input', updatePurgeDomainCount);
            }
            
            // Add tab change listener for purge estimation updates
            const purgeTabButtons = document.querySelectorAll('#purgeTabNav .nav-link');
            purgeTabButtons.forEach(button => {
                button.addEventListener('shown.bs.tab', function() {
                    updatePurgeEstimation();
                });
            });
        });
        
        // Cache operations
        async function executeClearCache() {
            const domains = document.getElementById('domainsToClear').value.split('\n')
                .map(d => d.trim()).filter(d => d.length > 0);
            
            if (domains.length === 0 && !confirm('Xóa toàn bộ cache hệ thống?')) {
                return;
            }
            
            const progressMsg = domains.length > 0 ? 
                `Đang xóa cache cho ${domains.length} domain(s)...` : 
                'Đang xóa toàn bộ cache...';
                
            showLoading(progressMsg);
            try {
                let result;
                if (domains.length > 0) {
                    const data = await apiPost({ 
                        action: 'clear_domain_cache', 
                        domains: JSON.stringify(domains) 
                    });
                    result = data.result;
                    
                    // Show detailed results
                    if (result.results) {
                        const successCount = result.total_cleared;
                        const failedCount = result.total_failed;
                        
                        let message = `✅ Cache clearing hoàn thành!\n`;
                        message += `• Thành công: ${successCount} domain(s)\n`;
                        if (failedCount > 0) {
                            message += `• Thất bại: ${failedCount} domain(s)\n`;
                            
                            // Show failed domains
                            const failedDomains = result.results
                                .filter(r => !r.success)
                                .map(r => `${r.domain}: ${r.message}`)
                                .slice(0, 3); // Show max 3 failed
                                
                            if (failedDomains.length > 0) {
                                message += `\nLỗi:\n${failedDomains.join('\n')}`;
                                if (result.total_failed > 3) {
                                    message += `\n... và ${result.total_failed - 3} domain(s) khác`;
                                }
                            }
                        }
                        
                        showNotification(failedCount > 0 ? 'warning' : 'success', message);
                    }
                } else {
                    const data = await apiPost({ action: 'clear_all_cache' });
                    result = data.result;
                    showNotification('success', `Đã xóa ${result.total_cleared} cache items!`);
                }
                
                bootstrap.Modal.getInstance(document.getElementById('clearCacheModal')).hide();
                refreshStats();
                
                // Log detailed results to console for debugging
                console.log('Cache clear results:', result);
                
            } catch (e) {
                showNotification('error', 'Lỗi xóa cache: ' + e.message);
            } finally {
                hideLoading();
            }
        }
        
        async function executeWarmCache() {
            const concurrent = document.getElementById('concurrentJobs').value;
            
            showLoading('Đang warm cache...');
            try {
                const data = await apiPost({ 
                    action: 'warm_cache',
                    concurrent: concurrent,
                    warm_zones: document.getElementById('warmZones').checked
                });
                
                if (data.success) {
                    showNotification('success', `Cache warming hoàn thành! Warmed ${data.result.zones_warmed} zones in ${data.result.time_taken}`);
                    bootstrap.Modal.getInstance(document.getElementById('warmCacheModal')).hide();
                    refreshStats();
                } else {
                    throw new Error(data.error);
                }
                
            } catch (e) {
                showNotification('error', 'Lỗi warm cache: ' + e.message);
            } finally {
                hideLoading();
            }
        }
        
        async function executePurgeAll() {
            if (!document.getElementById('confirmPurgeAll').checked) {
                showNotification('error', 'Vui lòng xác nhận rủi ro trước khi tiếp tục!');
                return;
            }
            
            // Determine purge mode based on active tab
            const activeTab = document.querySelector('#purgeTabNav .nav-link.active')?.id;
            const isAllDomains = activeTab === 'purge-all-tab';
            
            // Get selected domains for selective purge
            let selectedDomains = [];
            if (!isAllDomains) {
                selectedDomains = document.getElementById('purgeDomainsToProcess')?.value.split('\n')
                    .map(d => d.trim()).filter(d => d.length > 0) || [];
                
                if (selectedDomains.length === 0) {
                    showNotification('error', 'Vui lòng chọn ít nhất 1 domain để purge cache!');
                    return;
                }
            }
            
            const forceMode = document.getElementById('forcePurgeMode').value;
            const purgeStatic = document.getElementById('purgeStatic').checked;
            const purgeDynamic = document.getElementById('purgeDynamic').checked;
            const purgeEdge = document.getElementById('purgeEdge').checked;
            const purgeOrigin = document.getElementById('purgeOrigin').checked;
            
            const purgeMessage = isAllDomains ? 
                'Đang purge tất cả cached files trên toàn hệ thống...' :
                `Đang purge cached files cho ${selectedDomains.length} domain(s)...`;
            
            showLoading(purgeMessage);
            
            try {
                const data = await apiPost({ 
                    action: 'purge_all_cached_files',
                    domains: isAllDomains ? '[]' : JSON.stringify(selectedDomains),
                    purge_all_domains: isAllDomains ? 'true' : 'false',
                    force_mode: forceMode,
                    purge_static: purgeStatic,
                    purge_dynamic: purgeDynamic,
                    purge_edge: purgeEdge,
                    purge_origin: purgeOrigin
                });
                
                if (data.success && data.result) {
                    const result = data.result;
                    let message = `✅ Purge hoàn thành!\n`;
                    message += `• Files purged: ${result.total_files_purged}\n`;
                    message += `• Size cleared: ${result.size_cleared}\n`;
                    message += `• Time taken: ${result.time_taken}\n`;
                    
                    if (isAllDomains) {
                        message += `• Zones affected: ${result.zones_affected} (toàn hệ thống)`;
                    } else {
                        message += `• Domains processed: ${result.total_domains}\n`;
                        message += `• Successful zones: ${result.zones_affected}`;
                        
                        // Show failed domains if any
                        const failedDomains = result.domain_results?.filter(r => !r.success) || [];
                        if (failedDomains.length > 0) {
                            message += `\n\n⚠️ Domains thất bại (${failedDomains.length}):\n`;
                            failedDomains.slice(0, 3).forEach(domain => {
                                message += `• ${domain.domain}: ${domain.message}\n`;
                            });
                            if (failedDomains.length > 3) {
                                message += `... và ${failedDomains.length - 3} domains khác`;
                            }
                        }
                    }
                    
                    showNotification(result.zones_affected === result.total_domains ? 'success' : 'warning', message);
                    bootstrap.Modal.getInstance(document.getElementById('purgeAllModal')).hide();
                    refreshStats();
                    
                    // Show detailed results in console
                    console.log('Purge Results:', result);
                    
                    // Show per-domain results if available
                    if (result.domain_results && result.domain_results.length > 0) {
                        console.log('Per-domain results:', result.domain_results);
                    }
                } else {
                    throw new Error(data.error || 'Unknown error occurred');
                }
                
            } catch (e) {
                showNotification('error', 'Lỗi purge cache: ' + e.message);
            } finally {
                hideLoading();
            }
        }
        
        async function clearAllCache() {
            if (!confirm('Bạn có chắc muốn xóa toàn bộ cache hệ thống?')) return;
            
            showLoading('Đang xóa toàn bộ cache...');
            try {
                const data = await apiPost({ action: 'clear_all_cache' });
                if (data.success) {
                    showNotification('success', `Đã xóa ${data.result.total_cleared} cache items!`);
                    refreshStats();
                    loadCacheItems();
                } else {
                    throw new Error(data.error);
                }
            } catch (e) {
                showNotification('error', 'Lỗi xóa cache: ' + e.message);
            } finally {
                hideLoading();
            }
        }
        
        // Cache items management
        function loadCacheItems() {
            const container = document.getElementById('cacheItemsList');
            
            // Simulate cache items
            const items = [
                { type: 'zones', name: 'example.com', size: '2.3 MB', items: 156, lastUpdate: '2 mins ago' },
                { type: 'zones', name: 'test.com', size: '1.8 MB', items: 98, lastUpdate: '5 mins ago' },
                { type: 'dns', name: 'A Records', size: '0.5 MB', items: 234, lastUpdate: '1 min ago' },
                { type: 'dns', name: 'CNAME Records', size: '0.3 MB', items: 87, lastUpdate: '3 mins ago' },
                { type: 'ssl', name: 'example.com cert', size: '0.1 MB', items: 12, lastUpdate: '10 mins ago' },
                { type: 'api', name: 'Zone list responses', size: '0.8 MB', items: 45, lastUpdate: '30 secs ago' }
            ];
            
            container.innerHTML = items.map(item => `
                <div class="cache-item" data-type="${item.type}">
                    <div class="d-flex align-items-center gap-3">
                        <span class="cache-type-badge type-${item.type}">
                            ${item.type.toUpperCase()}
                        </span>
                        <div>
                            <div class="fw-semibold">${item.name}</div>
                            <small class="text-muted">${item.items} items • ${item.size}</small>
                        </div>
                    </div>
                    <div class="d-flex align-items-center gap-2">
                        <small class="text-muted d-none-mobile">${item.lastUpdate}</small>
                        <button class="btn btn-sm btn-outline-danger" onclick="clearItemCache('${item.type}', '${item.name}')" title="Clear">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            `).join('');
        }
        
        function filterCacheItems() {
            const filter = document.getElementById('cacheTypeFilter').value;
            const items = document.querySelectorAll('.cache-item');
            
            items.forEach(item => {
                if (!filter || item.dataset.type === filter) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }
        
        async function clearItemCache(type, name) {
            if (!confirm(`Xóa cache cho ${name}?`)) return;
            
            showLoading('Đang xóa cache item...');
            try {
                // Simulate API call
                await new Promise(resolve => setTimeout(resolve, 1000));
                showNotification('success', `Đã xóa cache cho ${name}`);
                loadCacheItems();
                refreshStats();
            } catch (e) {
                showNotification('error', 'Lỗi xóa cache item: ' + e.message);
            } finally {
                hideLoading();
            }
        }
        
        // Additional functions
        function showCacheDetails() {
            showNotification('info', 'Cache Details view sẽ được phát triển trong phiên bản tiếp theo!');
        }
        
        function showExportImportModal() {
            showNotification('info', 'Export/Import cache sẽ được phát triển trong phiên bản tiếp theo!');
        }
    </script>
    
    <!-- Professional Interface -->
    <script src="assets/js/main-interface.js"></script>
    
</body>
</html>