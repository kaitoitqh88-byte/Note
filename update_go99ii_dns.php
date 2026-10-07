<?php
/**
 * Cập nhật DNS Records cho go99ii.com
 */

require_once 'config.php';
require_once 'CloudflareAPI.php';

function updateDNSForGo99ii($api) {
    $domain = 'go99ii.com';
    $ip = '103.213.216.170'; // IP mặc định, có thể thay đổi
    
    echo "<h3>Kiểm tra và cập nhật DNS Records cho $domain</h3>\n";
    
    // Find zone ID for go99ii.com
    $zones = $api->listZones();
    $zoneId = null;
    $zoneName = null;
    
    if ($zones['success'] && isset($zones['result'])) {
        foreach ($zones['result'] as $zone) {
            if (strtolower($zone['name']) === strtolower($domain)) {
                $zoneId = $zone['id'];
                $zoneName = $zone['name'];
                break;
            }
        }
    }
    
    if (!$zoneId) {
        echo "<div class='alert alert-warning'>⚠️ Domain $domain không tìm thấy trong Cloudflare account!</div>\n";
        echo "<div class='alert alert-info'>";
        echo "<h6>Hướng dẫn thêm domain vào Cloudflare:</h6>";
        echo "<ol>";
        echo "<li>Đăng nhập <a href='https://dash.cloudflare.com' target='_blank'>Cloudflare Dashboard</a></li>";
        echo "<li>Click 'Add a Site' hoặc 'Add Site'</li>";
        echo "<li>Nhập domain: <strong>$domain</strong></li>";
        echo "<li>Chọn plan (Free hoặc Pro)</li>";
        echo "<li>Cập nhật nameservers tại nhà đăng ký domain</li>";
        echo "<li>Chờ DNS propagation (24-48 giờ)</li>";
        echo "</ol>";
        echo "</div>";
        
        // Hiển thị danh sách domains hiện có
        echo "<h5>Domains hiện có trong account:</h5>";
        if ($zones['success'] && isset($zones['result'])) {
            echo "<ul class='list-group'>";
            foreach ($zones['result'] as $zone) {
                $status = $zone['status'] === 'active' ? 'success' : 'warning';
                echo "<li class='list-group-item d-flex justify-content-between align-items-center'>";
                echo $zone['name'];
                echo "<span class='badge bg-$status'>" . $zone['status'] . "</span>";
                echo "</li>";
            }
            echo "</ul>";
        }
        return false;
    }
    
    echo "<div class='alert alert-success'>✅ Tìm thấy zone: $zoneName (ID: $zoneId)</div>\n";
    
    $results = [];
    
    // 1. Tạo/Cập nhật A record cho @ (root domain)
    echo "<h4>1. Cập nhật A record: @ → $ip</h4>\n";
    try {
        $rootResult = updateDNSRecord($api, $zoneId, $zoneName, '@', $ip, 'A');
        $results['root'] = $rootResult;
        
        if ($rootResult['success']) {
            echo "<div class='alert alert-success'>✅ A record được " . $rootResult['action'] . " thành công</div>\n";
            echo "<p><strong>Record:</strong> " . $rootResult['record_name'] . " → " . $rootResult['content'] . " (Type: " . $rootResult['type'] . ")</p>\n";
        } else {
            echo "<div class='alert alert-danger'>❌ Lỗi cập nhật A record</div>\n";
        }
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>❌ Lỗi: " . $e->getMessage() . "</div>\n";
        $results['root'] = ['success' => false, 'error' => $e->getMessage()];
    }
    
    // 2. Tạo/Cập nhật CNAME record cho www
    echo "<h4>2. Cập nhật CNAME record: www → $domain</h4>\n";
    try {
        $wwwResult = updateDNSRecord($api, $zoneId, $zoneName, 'www', $domain, 'CNAME');
        $results['www'] = $wwwResult;
        
        if ($wwwResult['success']) {
            echo "<div class='alert alert-success'>✅ CNAME record được " . $wwwResult['action'] . " thành công</div>\n";
            echo "<p><strong>Record:</strong> " . $wwwResult['record_name'] . " → " . $wwwResult['content'] . " (Type: " . $wwwResult['type'] . ")</p>\n";
        } else {
            echo "<div class='alert alert-danger'>❌ Lỗi cập nhật CNAME record</div>\n";
        }
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>❌ Lỗi: " . $e->getMessage() . "</div>\n";
        $results['www'] = ['success' => false, 'error' => $e->getMessage()];
    }
    
    // 3. Hiển thị tất cả DNS records hiện tại
    echo "<h4>3. DNS Records hiện tại:</h4>\n";
    try {
        $dnsRecords = $api->listDNSRecords($zoneId);
        if ($dnsRecords['success'] && isset($dnsRecords['result'])) {
            echo "<div class='table-responsive'>";
            echo "<table class='table table-striped'>";
            echo "<thead><tr><th>Type</th><th>Name</th><th>Content</th><th>TTL</th><th>Proxied</th></tr></thead>";
            echo "<tbody>";
            foreach ($dnsRecords['result'] as $record) {
                $proxied = $record['proxied'] ? '<span class="badge bg-warning">Yes</span>' : '<span class="badge bg-secondary">No</span>';
                $ttl = $record['ttl'] == 1 ? 'Auto' : $record['ttl'];
                echo "<tr>";
                echo "<td><span class='badge bg-primary'>" . $record['type'] . "</span></td>";
                echo "<td>" . $record['name'] . "</td>";
                echo "<td><code>" . $record['content'] . "</code></td>";
                echo "<td>" . $ttl . "</td>";
                echo "<td>" . $proxied . "</td>";
                echo "</tr>";
            }
            echo "</tbody></table>";
            echo "</div>";
        }
    } catch (Exception $e) {
        echo "<div class='alert alert-warning'>⚠️ Không thể lấy danh sách DNS records: " . $e->getMessage() . "</div>\n";
    }
    
    return $results;
}

/**
 * Update specific DNS record
 */
function updateDNSRecord($api, $zoneId, $zoneName, $recordName, $content, $type) {
    // Get existing DNS records
    $records = $api->listDNSRecords($zoneId);
    $targetName = $recordName === '@' ? $zoneName : $recordName . '.' . $zoneName;
    
    $existingRecord = null;
    foreach ($records['result'] ?? [] as $record) {
        if ($record['name'] === $targetName && $record['type'] === $type) {
            $existingRecord = $record;
            break;
        }
    }
    
    $recordData = [
        'type' => $type,
        'name' => $targetName,
        'content' => $content,
        'ttl' => 1, // Auto TTL
        'proxied' => false
    ];
    
    if ($existingRecord) {
        // Update existing record
        $result = $api->updateDNSRecordAdvanced($zoneId, $existingRecord['id'], $recordData);
        return [
            'action' => 'updated',
            'record_id' => $existingRecord['id'],
            'record_name' => $targetName,
            'content' => $content,
            'type' => $type,
            'success' => $result['success'] ?? false
        ];
    } else {
        // Create new record
        $result = $api->createDNSRecordAdvanced($zoneId, $recordData);
        return [
            'action' => 'created',
            'record_id' => $result['result']['id'] ?? null,
            'record_name' => $targetName,
            'content' => $content,
            'type' => $type,
            'success' => $result['success'] ?? false
        ];
    }
}

// Execute
try {
    $cloudflareAPI = new CloudflareAPI();
    $results = updateDNSForGo99ii($cloudflareAPI);
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>❌ Lỗi khởi tạo API: " . $e->getMessage() . "</div>\n";
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cập nhật DNS - go99ii.com</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/common.css" rel="stylesheet">
    <style>
        .result-card {
            border-left: 4px solid #007bff;
            margin-bottom: 1rem;
        }
        .record-info {
            background-color: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        .dns-table {
            font-size: 0.9em;
        }
        .status-indicator {
            width: 10px;
            height: 10px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }
        .status-active {
            background-color: #28a745;
        }
        .status-pending {
            background-color: #ffc107;
            animation: pulse 2s infinite;
        }
        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'dns-simple';
    include 'includes/navigation.php'; 
    ?>

    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card result-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-globe me-2"></i>
                            Cập nhật DNS Records - go99ii.com
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <h4>🔍 Kiểm tra Domain Status</h4>
                                <div class="alert alert-info">
                                    <strong>Domain:</strong> go99ii.com<br>
                                    <strong>Target IP:</strong> 103.213.216.170<br>
                                    <strong>Records:</strong> @ (A) + www (CNAME)
                                </div>
                            </div>
                        </div>
                        
                        <hr>
                        
                        <div class="row">
                            <div class="col-12">
                                <?php if (isset($results) && $results): ?>
                                    <div class="alert alert-success">
                                        <h5><i class="fas fa-check-circle me-2"></i>Cập nhật thành công!</h5>
                                        <p class="mb-0">DNS Records đã được cập nhật cho domain go99ii.com</p>
                                    </div>
                                    
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="record-info">
                                                <h6><i class="fas fa-at me-1"></i> @ Record (Root Domain)</h6>
                                                <?php if (isset($results['root']) && $results['root']['success']): ?>
                                                    <div class="text-success">
                                                        <i class="fas fa-check-circle me-1"></i>
                                                        <strong><?= ucfirst($results['root']['action']) ?></strong>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($results['root']['record_name']) ?> → 
                                                        <?= htmlspecialchars($results['root']['content']) ?> 
                                                        (<?= htmlspecialchars($results['root']['type']) ?>)
                                                    </small>
                                                <?php else: ?>
                                                    <div class="text-danger">
                                                        <i class="fas fa-times-circle me-1"></i>
                                                        Failed
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="col-md-6">
                                            <div class="record-info">
                                                <h6><i class="fas fa-link me-1"></i> WWW Record (CNAME)</h6>
                                                <?php if (isset($results['www']) && $results['www']['success']): ?>
                                                    <div class="text-success">
                                                        <i class="fas fa-check-circle me-1"></i>
                                                        <strong><?= ucfirst($results['www']['action']) ?></strong>
                                                    </div>
                                                    <small class="text-muted">
                                                        <?= htmlspecialchars($results['www']['record_name']) ?> → 
                                                        <?= htmlspecialchars($results['www']['content']) ?> 
                                                        (<?= htmlspecialchars($results['www']['type']) ?>)
                                                    </small>
                                                <?php else: ?>
                                                    <div class="text-danger">
                                                        <i class="fas fa-times-circle me-1"></i>
                                                        Failed
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div class="mt-4 text-center">
                            <a href="dns_simple.php" class="btn btn-primary me-2">
                                <i class="fas fa-server me-1"></i> DNS Simple
                            </a>
                            <a href="dashboard.php" class="btn btn-success me-2">
                                <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                            </a>
                            <button onclick="location.reload()" class="btn btn-secondary">
                                <i class="fas fa-redo me-1"></i> Refresh
                            </button>
                        </div>
                        
                        <div class="mt-4">
                            <div class="alert alert-info">
                                <h6><i class="fas fa-info-circle me-1"></i> Lưu ý:</h6>
                                <ul class="mb-0">
                                    <li>Nếu domain chưa có trong Cloudflare, cần thêm domain vào account trước</li>
                                    <li>DNS propagation có thể mất 24-48 giờ để có hiệu lực</li>
                                    <li>Sử dụng <a href="dns_simple.php">DNS Simple</a> để cập nhật domain khác dễ dàng</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>