<?php
/**
 * DNS Bulk Update - Cập nhật DNS @ và WWW cho danh sách domain
 */
require_once 'config.php';
require_once 'CloudflareAPI.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (ob_get_level()) ob_clean();
    
    try {
        $action = $_POST['action'] ?? '';
        $cloudflareAPI = new CloudflareAPI();
        
        switch ($action) {
            case 'bulk_update_dns':
                $domainList = $_POST['domain_list'] ?? '';
                $ipAddress = $_POST['ip_address'] ?? '';
                $recordType = $_POST['record_type'] ?? 'A';
                $wwwType = $_POST['www_type'] ?? 'A';
                
                $results = bulkUpdateDNSByDomainList($cloudflareAPI, $domainList, $ipAddress, $recordType, $wwwType);
                echo json_encode([
                    'success' => true,
                    'results' => $results
                ]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

/**
 * Bulk update DNS records for multiple domains from domain list
 */
function bulkUpdateDNSByDomainList($api, $domainListText, $ipAddress, $recordType, $wwwType = 'A') {
    $results = [];
    
    // Parse domain list
    $domains = array_filter(array_map('trim', explode("\n", $domainListText)));
    
    if (empty($domains) || empty($ipAddress)) {
        return $results;
    }
    
    // Get all zones from Cloudflare account
    $allZones = $api->listZones(1, 100);
    $zoneMap = [];
    
    if ($allZones['success'] && isset($allZones['result'])) {
        foreach ($allZones['result'] as $zone) {
            $zoneMap[strtolower($zone['name'])] = [
                'id' => $zone['id'],
                'name' => $zone['name']
            ];
        }
    }
    
    foreach ($domains as $domain) {
        $domain = strtolower(trim($domain));
        if (empty($domain)) continue;
        
        $result = [
            'domain_name' => $domain,
            'zone_found' => false,
            'root_update' => null,
            'www_update' => null,
            'success' => false,
            'error' => null
        ];
        
        try {
            // Find zone for this domain
            if (isset($zoneMap[$domain])) {
                $zoneId = $zoneMap[$domain]['id'];
                $zoneName = $zoneMap[$domain]['name'];
                $result['zone_found'] = true;
                $result['zone_id'] = $zoneId;
                
                // Update @ record
                $result['root_update'] = updateDNSRecord($api, $zoneId, $zoneName, '@', $ipAddress, $recordType);
                
                // Update www record
                $wwwContent = ($wwwType === 'CNAME') ? $zoneName : $ipAddress;
                $result['www_update'] = updateDNSRecord($api, $zoneId, $zoneName, 'www', $wwwContent, $wwwType);
                
                // Success if at least one record was updated
                $result['success'] = ($result['root_update']['success'] ?? false) || ($result['www_update']['success'] ?? false);
                
            } else {
                $result['error'] = 'Domain not found in Cloudflare account';
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        $results[] = $result;
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
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DNS Bulk Update - Cập nhật DNS hàng loạt</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/rpg-shooter-theme.css" rel="stylesheet">
    <link href="assets/css/sidebar-navigation.css" rel="stylesheet">
</head>
<body>
    <?php 
    $currentPage = 'dns-bulk-update';
    include 'includes/main_navigation.php'; 
    ?>

    <div class="main-wrapper">
        <div class="container-fluid my-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="fas fa-globe me-2"></i>DNS Bulk Update</h1>
                        <p class="text-muted mb-0">Cập nhật DNS @ và WWW cho nhiều domain cùng lúc</p>
                    </div>
                    <div>
                        <a href="DeleteDNS.php" class="btn btn-outline-danger">
                            <i class="fas fa-trash me-2"></i>Trang xóa DNS
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <!-- DNS Configuration Form -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-cog me-2"></i>Cấu hình thao tác DNS</h5>
                    </div>
                    <div class="card-body">
                        <form id="dnsConfigForm">
                            <div class="dns-input-group">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label">Loại Record</label>
                                        <select class="form-select" id="recordType" name="record_type">
                                            <option value="A">A Record (IPv4)</option>
                                            <option value="AAAA">AAAA Record (IPv6)</option>
                                            <option value="CNAME">CNAME Record</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">IP Address (cho @ record)</label>
                                        <div class="input-group">
                                            <span class="input-group-text"><i class="fas fa-server"></i></span>
                                            <input type="text" class="form-control" id="ipAddress" name="ip_address" 
                                                   placeholder="Ví dụ: 103.213.216.170">
                                        </div>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">WWW Record Type</label>
                                        <select class="form-select" id="wwwType" name="www_type">
                                            <option value="A">A (Same IP)</option>
                                            <option value="CNAME">CNAME (Point to domain)</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button type="button" class="btn btn-success w-100" id="updateBtn" disabled>
                                            <i class="fas fa-sync-alt me-2"></i>Cập nhật DNS
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                Nhập danh sách domain và IP address để tự động tạo @ và www records
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Container -->
        <div class="progress-container alert alert-info" id="progressContainer">
            <h5><i class="fas fa-sync-alt fa-spin me-2"></i>Đang cập nhật DNS...</h5>
            <div class="progress mb-2">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     id="progressBar" style="width: 0%"></div>
            </div>
            <div id="progressText">0 / 0 domains đã xử lý</div>
        </div>

        <!-- Domain List Input -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Danh sách Domains</h5>
                        <div>
                            <button class="btn btn-outline-secondary btn-sm" onclick="clearDomainList()">
                                <i class="fas fa-trash me-2"></i>Xóa tất cả
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <label class="form-label">Nhập danh sách domains (mỗi domain một dòng)</label>
                                <textarea class="form-control" id="domainList" name="domain_list" rows="10" 
                                          placeholder="example1.com&#10;example2.com&#10;example3.com&#10;...">
                                </textarea>
                                <div class="form-text mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Nhập mỗi domain trên một dòng. Hệ thống sẽ tự động tìm và cập nhật DNS cho các domain có trong Cloudflare account.
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="selection-counter" id="domainCounter" style="display: none;">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <span id="domainCount">0</span> domain đã nhập
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Results -->
        <div class="row" id="resultsContainer" style="display: none;">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Kết quả xử lý</h5>
                    </div>
                    <div class="card-body">
                        <div id="resultsContent"></div>
                        <div class="mt-3">
                            <button class="btn btn-primary" onclick="location.reload()">
                                <i class="fas fa-redo me-2"></i>Cập nhật mới
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let domainCount = 0;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Update button click handler
            document.getElementById('updateBtn').addEventListener('click', startBulkUpdate);
            
            // Form validation
            document.getElementById('ipAddress').addEventListener('input', validateForm);
            document.getElementById('domainList').addEventListener('input', function() {
                updateDomainCounter();
                validateForm();
            });
            
            validateForm();
        });

        function updateDomainCounter() {
            const domainList = document.getElementById('domainList').value.trim();
            const domains = domainList ? domainList.split('\n').filter(domain => domain.trim()) : [];
            domainCount = domains.length;
            
            const counter = document.getElementById('domainCounter');
            const countSpan = document.getElementById('domainCount');
            
            if (domainCount > 0) {
                counter.style.display = 'block';
                countSpan.textContent = domainCount;
            } else {
                counter.style.display = 'none';
            }
        }

        function clearDomainList() {
            document.getElementById('domainList').value = '';
            updateDomainCounter();
            validateForm();
        }

        function validateForm() {
            const ipAddress = document.getElementById('ipAddress').value.trim();
            const domainList = document.getElementById('domainList').value.trim();
            const updateBtn = document.getElementById('updateBtn');
            
            const hasDomains = domainList.length > 0;
            const hasIP = ipAddress.length > 0;
            updateBtn.disabled = !(hasIP && hasDomains);
        }

        function startBulkUpdate() {
            const formData = new FormData();
            formData.append('action', 'bulk_update_dns');
            formData.append('domain_list', document.getElementById('domainList').value);
            formData.append('ip_address', document.getElementById('ipAddress').value);
            formData.append('record_type', document.getElementById('recordType').value);
            formData.append('www_type', document.getElementById('wwwType').value);
            
            showProgress();
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideProgress();
                if (data.success) {
                    displayResults(data.results);
                } else {
                    showError('Lỗi cập nhật: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                hideProgress();
                showError('Lỗi kết nối: ' + error.message);
            });
        }

        function showProgress() {
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('updateBtn').disabled = true;
        }

        function hideProgress() {
            document.getElementById('progressContainer').style.display = 'none';
            validateForm();
        }

        function displayResults(results) {
            const container = document.getElementById('resultsContainer');
            const content = document.getElementById('resultsContent');
            
            let successCount = 0;
            let errorCount = 0;
            
            const resultsHtml = results.map(result => {
                let status = 'success';
                let statusIcon = 'fa-check-circle';
                let statusText = 'Thành công';
                
                if (!result.zone_found) {
                    status = 'error';
                    statusIcon = 'fa-times-circle';
                    statusText = 'Domain không tồn tại trong Cloudflare';
                    errorCount++;
                } else if (result.error) {
                    status = 'error';
                    statusIcon = 'fa-times-circle';
                    statusText = 'Lỗi';
                    errorCount++;
                } else if (!result.success) {
                    status = 'error';
                    statusIcon = 'fa-times-circle';
                    statusText = 'Lỗi cập nhật';
                    errorCount++;
                } else {
                    successCount++;
                }
                
                return `
                    <div class="result-item ${status}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-1">
                                    <i class="fas ${statusIcon} me-2"></i>
                                    ${result.domain_name}
                                </h6>
                                ${result.zone_found ? `
                                    <div class="row">
                                        ${result.root_update ? `
                                            <div class="col-md-6">
                                                <small class="text-muted d-block">@ Record:</small>
                                                <span class="badge bg-${result.root_update.success ? 'success' : 'danger'}">
                                                    ${result.root_update.action} ${result.root_update.type}
                                                </span>
                                                <code class="ms-2">${result.root_update.content}</code>
                                            </div>
                                        ` : ''}
                                        ${result.www_update ? `
                                            <div class="col-md-6">
                                                <small class="text-muted d-block">WWW Record:</small>
                                                <span class="badge bg-${result.www_update.success ? 'success' : 'danger'}">
                                                    ${result.www_update.action} ${result.www_update.type}
                                                </span>
                                                <code class="ms-2">${result.www_update.content}</code>
                                            </div>
                                        ` : ''}
                                    </div>
                                ` : ''}
                                ${result.error ? `
                                    <div class="mt-2">
                                        <small class="text-danger">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            ${result.error}
                                        </small>
                                    </div>
                                ` : ''}
                            </div>
                            <span class="badge bg-${status === 'success' ? 'success' : 'danger'}">
                                ${statusText}
                            </span>
                        </div>
                    </div>
                `;
            }).join('');
            
            content.innerHTML = `
                <div class="row mb-3">
                    <div class="col-md-4">
                        <div class="card text-center border-success">
                            <div class="card-body">
                                <h5 class="text-success">${successCount}</h5>
                                <small>Thành công</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center border-danger">
                            <div class="card-body">
                                <h5 class="text-danger">${errorCount}</h5>
                                <small>Lỗi</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center border-primary">
                            <div class="card-body">
                                <h5 class="text-primary">${results.length}</h5>
                                <small>Tổng cộng</small>
                            </div>
                        </div>
                    </div>
                </div>
                ${resultsHtml}
            `;
            
            container.style.display = 'block';
            container.scrollIntoView({ behavior: 'smooth' });
        }

        function showError(message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = 'alert alert-danger alert-dismissible fade show';
            alertDiv.innerHTML = `
                <i class="fas fa-exclamation-triangle me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.querySelector('.container-fluid').insertBefore(alertDiv, document.querySelector('.container-fluid').firstChild);
            
            setTimeout(() => alertDiv.remove(), 5000);
        }
    </script>
    
    </div> <!-- End main-wrapper -->
    
</body>
</html>