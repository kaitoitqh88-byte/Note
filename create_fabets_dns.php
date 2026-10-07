<?php
/**
 * Tạo DNS Records cho fabets.co.com
 */

require_once 'config.php';
require_once 'CloudflareAPI.php';

function createDNSRecordsForFabets($api) {
    $domain = 'fabets.co.com';
    $ip = '103.213.216.170';
    
    echo "<h3>Tạo DNS Records cho $domain</h3>\n";
    
    // Find zone ID for fabets.co.com
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
        echo "<div class='alert alert-danger'>❌ Domain $domain không tìm thấy trong Cloudflare account!</div>\n";
        return false;
    }
    
    echo "<div class='alert alert-success'>✅ Tìm thấy zone: $zoneName (ID: $zoneId)</div>\n";
    
    $results = [];
    
    // 1. Tạo A record cho @ (root domain)
    echo "<h4>1. Tạo A record: @ → $ip</h4>\n";
    try {
        $rootResult = updateDNSRecord($api, $zoneId, $zoneName, '@', $ip, 'A');
        $results['root'] = $rootResult;
        
        if ($rootResult['success']) {
            echo "<div class='alert alert-success'>✅ A record được " . $rootResult['action'] . " thành công</div>\n";
            echo "<p><strong>Record:</strong> " . $rootResult['record_name'] . " → " . $rootResult['content'] . " (Type: " . $rootResult['type'] . ")</p>\n";
        } else {
            echo "<div class='alert alert-danger'>❌ Lỗi tạo A record</div>\n";
        }
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>❌ Lỗi: " . $e->getMessage() . "</div>\n";
        $results['root'] = ['success' => false, 'error' => $e->getMessage()];
    }
    
    // 2. Tạo CNAME record cho www
    echo "<h4>2. Tạo CNAME record: www → $domain</h4>\n";
    try {
        $wwwResult = updateDNSRecord($api, $zoneId, $zoneName, 'www', $domain, 'CNAME');
        $results['www'] = $wwwResult;
        
        if ($wwwResult['success']) {
            echo "<div class='alert alert-success'>✅ CNAME record được " . $wwwResult['action'] . " thành công</div>\n";
            echo "<p><strong>Record:</strong> " . $wwwResult['record_name'] . " → " . $wwwResult['content'] . " (Type: " . $wwwResult['type'] . ")</p>\n";
        } else {
            echo "<div class='alert alert-danger'>❌ Lỗi tạo CNAME record</div>\n";
        }
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>❌ Lỗi: " . $e->getMessage() . "</div>\n";
        $results['www'] = ['success' => false, 'error' => $e->getMessage()];
    }
    
    return $results;
}

/**
 * Update DNS record (from dns_bulk_update.php)
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
    $results = createDNSRecordsForFabets($cloudflareAPI);
} catch (Exception $e) {
    echo "<div class='alert alert-danger'>❌ Lỗi khởi tạo API: " . $e->getMessage() . "</div>\n";
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tạo DNS Records - fabets.co.com</title>
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
            padding: 10px;
            border-radius: 5px;
            margin-top: 10px;
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'dns-bulk-update';
    include 'includes/navigation.php'; 
    ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card result-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-globe me-2"></i>
                            Tạo DNS Records cho fabets.co.com
                        </h5>
                    </div>
                    <div class="card-body">
                        <?php if (isset($results)): ?>
                            <div class="row">
                                <div class="col-12">
                                    <h4>📝 Yêu cầu:</h4>
                                    <ul class="list-group list-group-flush mb-4">
                                        <li class="list-group-item">
                                            <strong>A Record:</strong> fabets.co.com → 103.213.216.170
                                        </li>
                                        <li class="list-group-item">
                                            <strong>CNAME Record:</strong> www → fabets.co.com
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <hr>
                            
                            <h4>🎯 Kết quả thực hiện:</h4>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="record-info">
                                        <h6><i class="fas fa-at me-1"></i> @ Record (Root Domain)</h6>
                                        <?php if (isset($results['root'])): ?>
                                            <?php if ($results['root']['success']): ?>
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
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="record-info">
                                        <h6><i class="fas fa-link me-1"></i> WWW Record (CNAME)</h6>
                                        <?php if (isset($results['www'])): ?>
                                            <?php if ($results['www']['success']): ?>
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
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-4">
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-info-circle me-1"></i> Thông tin DNS Records:</h6>
                                    <ul class="mb-0">
                                        <li><strong>Domain:</strong> fabets.co.com</li>
                                        <li><strong>@ Record (A):</strong> fabets.co.com → 103.213.216.170</li>
                                        <li><strong>WWW Record (CNAME):</strong> www.fabets.co.com → fabets.co.com</li>
                                        <li><strong>TTL:</strong> Auto (Cloudflare managed)</li>
                                        <li><strong>Proxy:</strong> Disabled (DNS-only)</li>
                                    </ul>
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="mt-4 text-center">
                            <a href="dashboard.php" class="btn btn-primary me-2">
                                <i class="fas fa-tachometer-alt me-1"></i> Dashboard
                            </a>
                            <a href="dns_bulk_update.php" class="btn btn-success me-2">
                                <i class="fas fa-globe me-1"></i> DNS Bulk Update
                            </a>
                            <button onclick="location.reload()" class="btn btn-secondary">
                                <i class="fas fa-redo me-1"></i> Refresh
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>