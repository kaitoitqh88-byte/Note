<?php
/**
 * Cache Handler
 * Xử lý Cache và các thao tác cache
 */

/**
 * Xử lý Cache
 */
function handleCache($cloudflare) {
    // Nếu là GET request, hiển thị UI
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        require_once __DIR__ . '/cache_manager.php';
        return;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $zoneId = $_POST['zone_id'] ?? null;
    
    if (!$zoneId) {
        http_response_code(400);
        echo json_encode(['error' => 'Zone ID is required']);
        return;
    }
    
    $files = $_POST['files'] ?? null;
    
    try {
        $result = $cloudflare->purgeCache($zoneId, $files);
        echo json_encode([
            'success' => true,
            'data' => $result
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Xử lý các thao tác cache (clear, stats, warm)
 */
function handleCacheOperations($cloudflare) {
    // Nếu là GET request, hiển thị Cache Manager UI
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        require_once __DIR__ . '/cache_manager.php';
        return;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $operation = $_POST['operation'] ?? $_GET['operation'] ?? 'stats';
    $cacheType = $_POST['cache_type'] ?? $_GET['cache_type'] ?? null;
    
    try {
        switch ($operation) {
            case 'clear':
                $cleared = $cloudflare->clearCache($cacheType);
                echo json_encode([
                    'success' => true,
                    'operation' => 'clear',
                    'cache_type' => $cacheType ?? 'all',
                    'cleared_items' => $cleared,
                    'message' => $cacheType ? 
                        "Đã xóa {$cleared} items từ cache type '{$cacheType}'" :
                        "Đã xóa {$cleared} items từ toàn bộ cache"
                ]);
                break;
                
            case 'warm':
                $options = [
                    'zones_list' => ($_POST['warm_zones'] ?? 'true') === 'true',
                    'zone_details' => (int)($_POST['warm_zone_details'] ?? 10),
                    'dns_records' => (int)($_POST['warm_dns_records'] ?? 5),
                    'concurrent_batch' => (int)($_POST['concurrent_batch'] ?? 5)
                ];
                
                $result = $cloudflare->warmCache($options);
                echo json_encode([
                    'success' => $result['success'],
                    'operation' => 'warm',
                    'warmed_data' => $result['warmed_data'],
                    'options' => $options,
                    'message' => $result['success'] ? 'Cache warming completed' : 'Cache warming failed'
                ]);
                break;
                
            case 'stats':
            default:
                $apiStats = $cloudflare->getAPIStats();
                echo json_encode([
                    'success' => true,
                    'operation' => 'stats',
                    'stats' => $apiStats,
                    'timestamp' => time(),
                    'formatted_timestamp' => date('Y-m-d H:i:s')
                ]);
                break;
        }
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'operation' => $operation,
            'error' => $e->getMessage()
        ]);
    }
}

/**
 * Get cache manager statistics
 */
function getCacheManagerStatistics() {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'stats' => [
            'total_cached_items' => rand(1500, 5000),
            'cache_hit_rate' => rand(75, 95) . '%',
            'cache_size' => round(rand(100, 800) / 10, 1) . ' MB',
            'last_updated' => date('H:i:s d/m/Y'),
            'zones_cached' => rand(50, 150),
            'dns_records_cached' => rand(200, 800),
            'ssl_certificates_cached' => rand(30, 100)
        ]
    ]);
}

/**
 * Clear domain cache by list (demo function for cache manager)
 */
function clearDomainCacheDemo() {
    header('Content-Type: application/json');
    $domains = json_decode($_POST['domains'] ?? '[]', true);
    
    $results = [];
    foreach ($domains as $domain) {
        $results[] = [
            'domain' => $domain,
            'success' => true,
            'cleared_items' => rand(10, 100),
            'message' => 'Cache cleared successfully'
        ];
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'total_domains' => count($domains)
    ]);
}

/**
 * Warm domain cache (demo function for cache manager)
 */
function warmDomainCacheDemo($cloudflare) {
    header('Content-Type: application/json');
    $options = [
        'zones' => $_POST['warm_zones'] ?? 'true',
        'concurrent' => (int)($_POST['concurrent'] ?? 5)
    ];
    
    echo json_encode([
        'success' => true,
        'result' => [
            'zones_warmed' => rand(20, 50),
            'records_warmed' => rand(100, 300),
            'time_taken' => rand(5, 30) . ' seconds',
            'concurrent_jobs' => $options['concurrent']
        ]
    ]);
}

/**
 * Cache current domains (demo function for cache manager)
 */
function cacheCurrentDomainsDemo($cloudflare) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'cached_domains' => rand(50, 200),
        'cache_size' => round(rand(50, 300) / 10, 1) . ' MB'
    ]);
}

/**
 * View cache details (demo function for cache manager)
 */
function viewCacheDetailsDemo() {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'cache_types' => [
            'zones' => ['count' => rand(50, 150), 'size' => rand(10, 50) . ' MB'],
            'dns' => ['count' => rand(200, 800), 'size' => rand(5, 20) . ' MB'],
            'ssl' => ['count' => rand(30, 100), 'size' => rand(2, 10) . ' MB'],
            'api' => ['count' => rand(100, 500), 'size' => rand(20, 80) . ' MB']
        ]
    ]);
}

/**
 * Export cache data (demo function for cache manager)
 */
function exportCacheDataDemo() {
    $data = [
        'export_date' => date('Y-m-d H:i:s'),
        'cache_stats' => [
            'zones' => rand(50, 150),
            'dns_records' => rand(200, 800),
            'ssl_certificates' => rand(30, 100)
        ],
        'settings' => [
            'auto_cache' => true,
            'cache_ttl' => 3600,
            'max_cache_size' => '100MB'
        ]
    ];
    
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename=cache_export_' . date('Y-m-d') . '.json');
    echo json_encode($data, JSON_PRETTY_PRINT);
}

/**
 * Import cache data (demo function for cache manager)
 */
function importCacheDataDemo() {
    header('Content-Type: application/json');
    
    if (!isset($_FILES['import_file'])) {
        echo json_encode(['success' => false, 'error' => 'No file provided']);
        return;
    }
    
    $file = $_FILES['import_file'];
    $content = file_get_contents($file['tmp_name']);
    $data = json_decode($content, true);
    
    if (!$data) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON file']);
        return;
    }
    
    echo json_encode([
        'success' => true,
        'imported_items' => rand(100, 500),
        'message' => 'Cache data imported successfully'
    ]);
}

/**
 * Lấy DNS Records từ Cloudflare API
 */
function getCloudflaresDNSRecords($domain = null) {
    header('Content-Type: application/json');
    
    // Lấy domain từ POST hoặc GET parameter
    $domain = $domain ?? $_POST['domain'] ?? $_GET['domain'] ?? null;
    $includeProxied = ($_POST['include_proxied'] ?? 'true') === 'true';
    $showOnlyActive = ($_POST['show_only_active'] ?? 'true') === 'true';
    $compareDNS = ($_POST['compare_dns'] ?? 'false') === 'true';
    
    if (!$domain) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Domain parameter is required'
        ]);
        return;
    }
    
    try {
        // Khởi tạo Cloudflare API
        require_once __DIR__ . '/CloudflareAPI.php';
        $cloudflare = new CloudflareAPI();
        
        // Tìm zone ID cho domain - cải tiến logic
        $zoneId = null;
        $actualDomain = $domain;
        
        // Thử tìm exact match trước
        $zones = $cloudflare->listZones(1, 50, $domain);
        
        if (!empty($zones['result'])) {
            foreach ($zones['result'] as $zone) {
                if ($zone['name'] === $domain) {
                    $zoneId = $zone['id'];
                    $actualDomain = $zone['name'];
                    break;
                }
            }
        }
        
        // Nếu không tìm thấy exact match, thử tìm parent domain
        if (!$zoneId) {
            $zones = $cloudflare->listZones(1, 100);
            if (!empty($zones['result'])) {
                foreach ($zones['result'] as $zone) {
                    $zoneName = $zone['name'];
                    // Kiểm tra nếu domain là subdomain của zone này
                    if ($domain === $zoneName || 
                        (strpos($domain, '.') !== false && 
                         substr($domain, -(strlen($zoneName) + 1)) === '.' . $zoneName)) {
                        $zoneId = $zone['id'];
                        $actualDomain = $zoneName;
                        break;
                    }
                }
            }
        }
        
        if (!$zoneId) {
            // List available domains for debugging
            $availableDomains = [];
            if (!empty($zones['result'])) {
                foreach ($zones['result'] as $zone) {
                    $availableDomains[] = $zone['name'];
                }
            }
            
            echo json_encode([
                'success' => false,
                'error' => 'Domain not found in Cloudflare account: ' . $domain,
                'available_domains' => $availableDomains,
                'suggestion' => 'Try one of the available domains listed'
            ]);
            return;
        }
        
        // Lấy DNS records từ Cloudflare với better error handling
        try {
            // Use the correct function with per_page parameter
            $dnsResponse = $cloudflare->listDNSRecords($zoneId);
            
            if (!$dnsResponse) {
                echo json_encode([
                    'success' => false,
                    'error' => 'No response from Cloudflare DNS API',
                    'debug' => 'API returned null or false'
                ]);
                return;
            }
            
            if (!isset($dnsResponse['result'])) {
                $errorMsg = 'Failed to retrieve DNS records from Cloudflare';
                if (isset($dnsResponse['errors']) && !empty($dnsResponse['errors'])) {
                    $errorMsg .= ': ' . implode(', ', array_column($dnsResponse['errors'], 'message'));
                }
                echo json_encode([
                    'success' => false,
                    'error' => $errorMsg,
                    'api_response' => $dnsResponse,
                    'debug' => 'API response missing result field'
                ]);
                return;
            }
            
            $cloudflareRecords = $dnsResponse['result'];
            
            // Debug log
            error_log("Cloudflare DNS: Retrieved " . count($cloudflareRecords) . " records for domain: $actualDomain");
            
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'API call failed: ' . $e->getMessage(),
                'debug' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine()
                ]
            ]);
            return;
        }
        
        // Lọc theo các tùy chọn
        $filteredRecords = [];
        foreach ($cloudflareRecords as $record) {
            // Lọc theo proxy status
            if (!$includeProxied && isset($record['proxied']) && $record['proxied']) {
                continue;
            }
            
            // Skip filtering by active status - all DNS records are considered active by default
            
            $filteredRecords[] = [
                'id' => $record['id'] ?? '',
                'type' => $record['type'] ?? '',
                'name' => $record['name'] ?? '',
                'content' => $record['content'] ?? '',
                'ttl' => $record['ttl'] ?? 1,
                'proxied' => $record['proxied'] ?? false,
                'comment' => $record['comment'] ?? '',
                'created_on' => $record['created_on'] ?? '',
                'modified_on' => $record['modified_on'] ?? '',
                'zone_id' => $record['zone_id'] ?? $zoneId,
                'zone_name' => $record['zone_name'] ?? $actualDomain,
                'priority' => $record['priority'] ?? null,
                'locked' => $record['locked'] ?? false
            ];
        }
        
        // Nhóm records theo type
        $groupedRecords = [];
        $recordStats = [
            'total_records' => count($filteredRecords),
            'proxied_records' => 0,
            'dns_only_records' => 0,
            'record_types' => []
        ];
        
        foreach ($filteredRecords as $record) {
            $type = $record['type'];
            
            if (!isset($groupedRecords[$type])) {
                $groupedRecords[$type] = [];
            }
            $groupedRecords[$type][] = $record;
            
            // Thống kê
            if ($record['proxied']) {
                $recordStats['proxied_records']++;
            } else {
                $recordStats['dns_only_records']++;
            }
            
            if (!isset($recordStats['record_types'][$type])) {
                $recordStats['record_types'][$type] = 0;
            }
            $recordStats['record_types'][$type]++;
        }
        
        $result = [
            'domain' => $actualDomain,
            'zone_id' => $zoneId,
            'cloudflare_records' => $groupedRecords,
            'statistics' => $recordStats,
            'query_time' => date('Y-m-d H:i:s'),
            'original_query' => $domain
        ];
        
        // So sánh với public DNS nếu được yêu cầu
        echo json_encode([
            'success' => true,
            'domain' => $domain,
            'data' => $result,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Cloudflare API error: ' . $e->getMessage(),
            'domain' => $domain
        ]);
    }
}

/**
 * Purge all cached files (demo function for cache manager)
 */
function purgeAllCachedFilesDemo() {
    header('Content-Type: application/json');
    
    // Get selected domains if provided
    $selectedDomains = json_decode($_POST['domains'] ?? '[]', true);
    $purgeAllDomains = ($_POST['purge_all_domains'] ?? 'false') === 'true';
    
    // Simulate purging all cached files
    $baseFileCount = $purgeAllDomains ? rand(10000, 25000) : rand(2000, 8000);
    $zonesCount = $purgeAllDomains ? rand(200, 800) : max(count($selectedDomains), rand(20, 100));
    
    $result = [
        'total_files_purged' => $baseFileCount,
        'zones_affected' => $zonesCount,
        'domains_processed' => $purgeAllDomains ? 'all' : $selectedDomains,
        'total_domains' => $purgeAllDomains ? $zonesCount : count($selectedDomains),
        'cache_types_cleared' => [
            'html' => rand(1000, 4000),
            'css' => rand(500, 2000), 
            'js' => rand(800, 3000),
            'images' => rand(3000, 8000),
            'fonts' => rand(100, 800),
            'api_responses' => rand(200, 1200),
            'video' => rand(50, 300),
            'documents' => rand(100, 600)
        ],
        'size_cleared' => round(rand(800, 3000) / 10, 1) . ' MB',
        'time_taken' => rand(45, 300) . ' seconds',
        'timestamp' => date('Y-m-d H:i:s'),
        'purge_mode' => $_POST['force_mode'] ?? 'normal',
        'options_selected' => [
            'static_assets' => ($_POST['purge_static'] ?? 'false') === 'true',
            'dynamic_content' => ($_POST['purge_dynamic'] ?? 'false') === 'true',
            'edge_cache' => ($_POST['purge_edge'] ?? 'false') === 'true',
            'origin_cache' => ($_POST['purge_origin'] ?? 'false') === 'true'
        ]
    ];
    
    echo json_encode($result);
}

/**
 * List available domains in Cloudflare account with enhanced details
 */
function listCloudflaredomains() {
    header('Content-Type: application/json');
    
    try {
        require_once __DIR__ . '/CloudflareAPI.php';
        $cloudflare = new CloudflareAPI();
        
        $page = (int)($_GET['page'] ?? 1);
        $perPage = (int)($_GET['per_page'] ?? 50);
        $status = $_GET['status'] ?? null; // active, pending, initializing, moved, deleted, deactivated
        
        // Get all zones in account with pagination
        $zones = $cloudflare->listZones($page, $perPage);
        
        if (!$zones || !isset($zones['result'])) {
            echo json_encode([
                'success' => false,
                'error' => 'Failed to retrieve zones from Cloudflare API',
                'api_response' => $zones
            ]);
            return;
        }
        
        $domains = [];
        foreach ($zones['result'] as $zone) {
            // Get DNS records count for this zone
            $dnsRecords = $cloudflare->listDNSRecords($zone['id']);
            $recordsCount = isset($dnsRecords['result']) ? count($dnsRecords['result']) : 0;
            
            $domains[] = [
                'id' => $zone['id'],
                'name' => $zone['name'],
                'status' => $zone['status'],
                'paused' => $zone['paused'] ?? false,
                'plan' => [
                    'id' => $zone['plan']['id'] ?? null,
                    'name' => $zone['plan']['name'] ?? 'Free',
                    'price' => $zone['plan']['price'] ?? 0,
                    'currency' => $zone['plan']['currency'] ?? 'USD'
                ],
                'name_servers' => $zone['name_servers'] ?? [],
                'original_name_servers' => $zone['original_name_servers'] ?? [],
                'development_mode' => $zone['development_mode'] ?? 0,
                'created_on' => $zone['created_on'] ?? null,
                'modified_on' => $zone['modified_on'] ?? null,
                'type' => $zone['type'] ?? 'full',
                'dns_records_count' => $recordsCount,
                'verification_key' => $zone['verification_key'] ?? null,
                'meta' => [
                    'step' => $zone['meta']['step'] ?? null,
                    'custom_certificate_quota' => $zone['meta']['custom_certificate_quota'] ?? 0,
                    'page_rule_quota' => $zone['meta']['page_rule_quota'] ?? 3,
                    'phishing_detected' => $zone['meta']['phishing_detected'] ?? false
                ]
            ];
        }
        
        $response = [
            'success' => true,
            'domains' => $domains,
            'result_info' => [
                'page' => $zones['result_info']['page'] ?? $page,
                'per_page' => $zones['result_info']['per_page'] ?? $perPage,
                'count' => $zones['result_info']['count'] ?? count($domains),
                'total_count' => $zones['result_info']['total_count'] ?? count($domains),
                'total_pages' => $zones['result_info']['total_pages'] ?? 1
            ],
            'filters_applied' => [
                'status' => $status,
                'page' => $page,
                'per_page' => $perPage
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        // Add summary statistics
        $statusCounts = [];
        foreach ($domains as $domain) {
            $status = $domain['status'];
            $statusCounts[$status] = ($statusCounts[$status] ?? 0) + 1;
        }
        
        $response['summary'] = [
            'total_domains' => count($domains),
            'status_breakdown' => $statusCounts,
            'total_dns_records' => array_sum(array_column($domains, 'dns_records_count')),
            'plans_breakdown' => array_count_values(array_column(array_column($domains, 'plan'), 'name'))
        ];
        
        echo json_encode($response, JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => 'API Error: ' . $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
}

/**
 * Debug function for Cloudflare DNS issues
 */
function debugCloudflareDNS() {
    header('Content-Type: application/json');
    
    $domain = $_GET['domain'] ?? 'darleydale.uk.com';
    $debug = [];
    
    try {
        require_once __DIR__ . '/CloudflareAPI.php';
        $cloudflare = new CloudflareAPI();
        
        $debug['step_1_api_connection'] = 'Testing API connection...';
        
        // Test basic connection
        $zones = $cloudflare->listZones(1, 10);
        if (!$zones || !isset($zones['result'])) {
            $debug['step_1_api_connection'] = 'FAILED - No zones response';
            throw new Exception('Cannot connect to Cloudflare API');
        }
        
        $debug['step_1_api_connection'] = 'SUCCESS - Found ' . count($zones['result']) . ' zones';
        $debug['available_zones'] = array_map(function($zone) {
            return ['name' => $zone['name'], 'id' => $zone['id'], 'status' => $zone['status']];
        }, $zones['result']);
        
        // Find target zone
        $debug['step_2_zone_search'] = "Searching for zone: {$domain}";
        $targetZone = null;
        foreach ($zones['result'] as $zone) {
            if ($zone['name'] === $domain) {
                $targetZone = $zone;
                break;
            }
        }
        
        if (!$targetZone) {
            $debug['step_2_zone_search'] = 'FAILED - Zone not found';
            $debug['suggested_domains'] = array_column($zones['result'], 'name');
            throw new Exception("Zone for {$domain} not found");
        }
        
        $debug['step_2_zone_search'] = 'SUCCESS - Zone found';
        $debug['target_zone'] = [
            'id' => $targetZone['id'],
            'name' => $targetZone['name'],
            'status' => $targetZone['status']
        ];
        
        // Get DNS records
        $debug['step_3_dns_records'] = 'Fetching DNS records...';
        $dnsResponse = $cloudflare->listDNSRecords($targetZone['id']);
        
        if (!$dnsResponse || !isset($dnsResponse['result'])) {
            $debug['step_3_dns_records'] = 'FAILED - No DNS records response';
            $debug['dns_api_response'] = $dnsResponse;
            throw new Exception('Failed to get DNS records from API');
        }
        
        $records = $dnsResponse['result'];
        $debug['step_3_dns_records'] = 'SUCCESS - Found ' . count($records) . ' records';
        
        // Analyze records
        $recordsByType = [];
        foreach ($records as $record) {
            $type = $record['type'];
            if (!isset($recordsByType[$type])) {
                $recordsByType[$type] = [];
            }
            $recordsByType[$type][] = [
                'name' => $record['name'],
                'content' => $record['content'],
                'proxied' => $record['proxied'] ?? false,
                'ttl' => $record['ttl']
            ];
        }
        
        $debug['records_by_type'] = $recordsByType;
        $debug['total_records'] = count($records);
        
        // Check specific expected records
        $debug['expected_records'] = [
            'A' => ['name' => $domain, 'content' => '103.213.216.110', 'proxied' => true],
            'CNAME' => ['name' => "www.{$domain}", 'content' => $domain, 'proxied' => true]
        ];
        
        $debug['record_check'] = [];
        foreach ($debug['expected_records'] as $type => $expected) {
            if (isset($recordsByType[$type])) {
                $found = false;
                foreach ($recordsByType[$type] as $actual) {
                    if ($actual['name'] === $expected['name'] && $actual['content'] === $expected['content']) {
                        $found = true;
                        $debug['record_check'][$type] = [
                            'status' => 'FOUND',
                            'matches' => $actual['proxied'] === $expected['proxied'],
                            'actual' => $actual,
                            'expected' => $expected
                        ];
                        break;
                    }
                }
                if (!$found) {
                    $debug['record_check'][$type] = [
                        'status' => 'NOT_FOUND',
                        'available' => $recordsByType[$type],
                        'expected' => $expected
                    ];
                }
            } else {
                $debug['record_check'][$type] = [
                    'status' => 'TYPE_NOT_FOUND',
                    'expected' => $expected
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'domain' => $domain,
            'debug' => $debug,
            'summary' => [
                'api_connection' => 'OK',
                'zone_found' => 'OK',
                'records_retrieved' => count($records),
                'expected_a_record' => $debug['record_check']['A']['status'] ?? 'UNKNOWN',
                'expected_cname_record' => $debug['record_check']['CNAME']['status'] ?? 'UNKNOWN'
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'debug' => $debug
        ]);
    }
}

/**
 * Comprehensive Cloudflare DNS Configuration Manager
 */
function manageCloudflareDNS() {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? $_GET['action'] ?? 'list';
    $zoneId = $_POST['zone_id'] ?? $_GET['zone_id'] ?? null;
    
    try {
        require_once __DIR__ . '/CloudflareAPI.php';
        $cloudflare = new CloudflareAPI();
        
        switch ($action) {
            case 'list_zones':
                return handleListZones($cloudflare);
                
            case 'zone_details':
                return handleZoneDetails($cloudflare, $zoneId);
                
            case 'list_dns_records':
                return handleListDNSRecords($cloudflare, $zoneId);
                
            case 'create_dns_record':
                return handleCreateDNSRecord($cloudflare, $zoneId);
                
            case 'update_dns_record':
                return handleUpdateDNSRecord($cloudflare, $zoneId);
                
            case 'delete_dns_record':
                return handleDeleteDNSRecord($cloudflare, $zoneId);
                
            case 'bulk_dns_operations':
                return handleBulkDNSOperations($cloudflare, $zoneId);
                
            case 'validate_dns_config':
                return handleValidateDNSConfig($cloudflare, $zoneId);
                
            case 'export_dns_config':
                return handleExportDNSConfig($cloudflare, $zoneId);
                
            case 'import_dns_config':
                return handleImportDNSConfig($cloudflare, $zoneId);
                
            default:
                throw new Exception('Invalid action: ' . $action);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'action' => $action
        ]);
    }
}

/**
 * Handle listing zones with enhanced filtering
 */
function handleListZones($cloudflare) {
    $page = (int)($_GET['page'] ?? 1);
    $perPage = min((int)($_GET['per_page'] ?? 20), 100);
    $status = $_GET['status'] ?? null;
    $search = $_GET['search'] ?? null;
    $sortBy = $_GET['sort_by'] ?? 'name';
    $sortOrder = $_GET['sort_order'] ?? 'asc';
    
    // Get zones with pagination
    $zones = $cloudflare->listZones($page, $perPage, $search, $status);
    
    if (!$zones || !isset($zones['result'])) {
        throw new Exception('Failed to retrieve zones');
    }
    
    $enhancedZones = [];
    foreach ($zones['result'] as $zone) {
        // Get additional zone information
        $zoneSettings = $cloudflare->getZoneSettings($zone['id']);
        $dnsRecords = $cloudflare->listDNSRecords($zone['id']);
        
        $enhancedZones[] = [
            'zone_info' => $zone,
            'dns_records_summary' => [
                'total_records' => isset($dnsRecords['result']) ? count($dnsRecords['result']) : 0,
                'record_types' => getDNSRecordTypes($dnsRecords['result'] ?? [])
            ],
            'security_settings' => extractSecuritySettings($zoneSettings['result'] ?? []),
            'performance_settings' => extractPerformanceSettings($zoneSettings['result'] ?? [])
        ];
    }
    
    // Sort zones if requested
    if ($sortBy && in_array($sortBy, ['name', 'status', 'created_on', 'dns_records_count'])) {
        usort($enhancedZones, function($a, $b) use ($sortBy, $sortOrder) {
            $aVal = $sortBy === 'dns_records_count' ? 
                $a['dns_records_summary']['total_records'] : 
                $a['zone_info'][$sortBy];
            $bVal = $sortBy === 'dns_records_count' ? 
                $b['dns_records_summary']['total_records'] : 
                $b['zone_info'][$sortBy];
            
            if ($sortOrder === 'desc') {
                return $bVal <=> $aVal;
            }
            return $aVal <=> $bVal;
        });
    }
    
    echo json_encode([
        'success' => true,
        'zones' => $enhancedZones,
        'pagination' => $zones['result_info'] ?? [],
        'filters' => [
            'status' => $status,
            'search' => $search,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Handle zone details with comprehensive information
 */
function handleZoneDetails($cloudflare, $zoneId) {
    if (!$zoneId) {
        throw new Exception('Zone ID is required');
    }
    
    // Get zone information
    $zoneInfo = $cloudflare->getZoneDetails($zoneId);
    $zoneSettings = $cloudflare->getZoneSettings($zoneId);
    $dnsRecords = $cloudflare->listDNSRecords($zoneId);
    
    // Analyze DNS records
    $records = $dnsRecords['result'] ?? [];
    $recordAnalysis = [
        'total_records' => count($records),
        'by_type' => [],
        'by_ttl' => [],
        'proxied_count' => 0,
        'non_proxied_count' => 0,
        'subdomains' => [],
        'mx_records' => [],
        'txt_records' => []
    ];
    
    foreach ($records as $record) {
        // Count by type
        $type = $record['type'];
        $recordAnalysis['by_type'][$type] = ($recordAnalysis['by_type'][$type] ?? 0) + 1;
        
        // Count by TTL
        $ttl = $record['ttl'] ?? 'auto';
        $recordAnalysis['by_ttl'][$ttl] = ($recordAnalysis['by_ttl'][$ttl] ?? 0) + 1;
        
        // Count proxied vs non-proxied
        if (isset($record['proxied']) && $record['proxied']) {
            $recordAnalysis['proxied_count']++;
        } else {
            $recordAnalysis['non_proxied_count']++;
        }
        
        // Collect interesting records
        if ($type === 'MX') {
            $recordAnalysis['mx_records'][] = [
                'name' => $record['name'],
                'content' => $record['content'],
                'priority' => $record['priority'] ?? 0
            ];
        }
        
        if ($type === 'TXT' && strpos($record['content'], 'v=spf') !== false) {
            $recordAnalysis['txt_records']['spf'] = $record['content'];
        }
        
        if ($type === 'TXT' && strpos($record['content'], 'v=DMARC') !== false) {
            $recordAnalysis['txt_records']['dmarc'] = $record['content'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'zone' => $zoneInfo['result'] ?? [],
        'settings' => $zoneSettings['result'] ?? [],
        'dns_analysis' => $recordAnalysis,
        'recommendations' => generateDNSRecommendations($recordAnalysis),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Handle DNS records listing with filtering and search
 */
function handleListDNSRecords($cloudflare, $zoneId) {
    if (!$zoneId) {
        throw new Exception('Zone ID is required');
    }
    
    $recordType = $_GET['type'] ?? null;
    $recordName = $_GET['name'] ?? null;
    $proxied = $_GET['proxied'] ?? null;
    $page = (int)($_GET['page'] ?? 1);
    $perPage = min((int)($_GET['per_page'] ?? 100), 100);
    
    $records = $cloudflare->listDNSRecords($zoneId, $recordType, $recordName, null, null, $page, $perPage);
    
    if (!$records || !isset($records['result'])) {
        throw new Exception('Failed to retrieve DNS records');
    }
    
    // Filter records if needed
    $filteredRecords = $records['result'];
    if ($proxied !== null) {
        $proxied = filter_var($proxied, FILTER_VALIDATE_BOOLEAN);
        $filteredRecords = array_filter($filteredRecords, function($record) use ($proxied) {
            return ($record['proxied'] ?? false) === $proxied;
        });
    }
    
    // Enhance records with additional information
    foreach ($filteredRecords as &$record) {
        $record['can_proxy'] = in_array($record['type'], ['A', 'AAAA', 'CNAME']);
        $record['ttl_description'] = getTTLDescription($record['ttl'] ?? 1);
        $record['last_modified'] = $record['modified_on'] ?? $record['created_on'] ?? null;
        
        // Add validation status
        $record['validation'] = validateDNSRecord($record);
    }
    
    echo json_encode([
        'success' => true,
        'records' => array_values($filteredRecords),
        'filters_applied' => [
            'type' => $recordType,
            'name' => $recordName,
            'proxied' => $proxied
        ],
        'pagination' => $records['result_info'] ?? [],
        'summary' => [
            'total_records' => count($filteredRecords),
            'types_present' => array_unique(array_column($filteredRecords, 'type'))
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Handle creating DNS records with validation
 */
function handleCreateDNSRecord($cloudflare, $zoneId) {
    if (!$zoneId) {
        throw new Exception('Zone ID is required');
    }
    
    $recordData = [
        'type' => $_POST['type'] ?? null,
        'name' => $_POST['name'] ?? null,
        'content' => $_POST['content'] ?? null,
        'ttl' => (int)($_POST['ttl'] ?? 1),
        'proxied' => filter_var($_POST['proxied'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'comment' => $_POST['comment'] ?? '',
        'priority' => isset($_POST['priority']) ? (int)$_POST['priority'] : null
    ];
    
    // Validate required fields
    if (!$recordData['type'] || !$recordData['name'] || !$recordData['content']) {
        throw new Exception('Type, name, and content are required fields');
    }
    
    // Validate record data
    $validation = validateDNSRecordData($recordData);
    if (!$validation['valid']) {
        throw new Exception('Validation failed: ' . implode(', ', $validation['errors']));
    }
    
    // Create the record
    $result = $cloudflare->createDNSRecord(
        $zoneId,
        $recordData['type'],
        $recordData['name'],
        $recordData['content'],
        $recordData['ttl'],
        $recordData['proxied'],
        $recordData['comment'],
        $recordData['priority']
    );
    
    if (!$result || !$result['success']) {
        $errors = isset($result['errors']) ? array_column($result['errors'], 'message') : ['Unknown error'];
        throw new Exception('Failed to create DNS record: ' . implode(', ', $errors));
    }
    
    echo json_encode([
        'success' => true,
        'record' => $result['result'],
        'message' => 'DNS record created successfully',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Handle updating DNS records
 */
function handleUpdateDNSRecord($cloudflare, $zoneId) {
    if (!$zoneId) {
        throw new Exception('Zone ID is required');
    }
    
    $recordId = $_POST['record_id'] ?? null;
    if (!$recordId) {
        throw new Exception('Record ID is required');
    }
    
    $updateData = [];
    foreach (['type', 'name', 'content', 'comment'] as $field) {
        if (isset($_POST[$field])) {
            $updateData[$field] = $_POST[$field];
        }
    }
    
    if (isset($_POST['ttl'])) {
        $updateData['ttl'] = (int)$_POST['ttl'];
    }
    
    if (isset($_POST['proxied'])) {
        $updateData['proxied'] = filter_var($_POST['proxied'], FILTER_VALIDATE_BOOLEAN);
    }
    
    if (isset($_POST['priority'])) {
        $updateData['priority'] = (int)$_POST['priority'];
    }
    
    if (empty($updateData)) {
        throw new Exception('No update data provided');
    }
    
    // Validate the update data
    $validation = validateDNSRecordData($updateData, false);
    if (!$validation['valid']) {
        throw new Exception('Validation failed: ' . implode(', ', $validation['errors']));
    }
    
    $result = $cloudflare->updateDNSRecord($zoneId, $recordId, $updateData);
    
    if (!$result || !$result['success']) {
        $errors = isset($result['errors']) ? array_column($result['errors'], 'message') : ['Unknown error'];
        throw new Exception('Failed to update DNS record: ' . implode(', ', $errors));
    }
    
    echo json_encode([
        'success' => true,
        'record' => $result['result'],
        'message' => 'DNS record updated successfully',
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Handle deleting DNS records
 */
function handleDeleteDNSRecord($cloudflare, $zoneId) {
    if (!$zoneId) {
        throw new Exception('Zone ID is required');
    }
    
    $recordId = $_POST['record_id'] ?? null;
    if (!$recordId) {
        throw new Exception('Record ID is required');
    }
    
    $result = $cloudflare->deleteDNSRecord($zoneId, $recordId);
    
    if (!$result || !$result['success']) {
        $errors = isset($result['errors']) ? array_column($result['errors'], 'message') : ['Unknown error'];
        throw new Exception('Failed to delete DNS record: ' . implode(', ', $errors));
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'DNS record deleted successfully',
        'deleted_id' => $recordId,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Handle bulk DNS operations
 */
function handleBulkDNSOperations($cloudflare, $zoneId) {
    if (!$zoneId) {
        throw new Exception('Zone ID is required');
    }
    
    $operation = $_POST['operation'] ?? null;
    $recordIds = json_decode($_POST['record_ids'] ?? '[]', true);
    
    if (!$operation || empty($recordIds)) {
        throw new Exception('Operation and record IDs are required');
    }
    
    $results = [];
    $successCount = 0;
    $failureCount = 0;
    
    switch ($operation) {
        case 'delete_multiple':
            foreach ($recordIds as $recordId) {
                try {
                    $result = $cloudflare->deleteDNSRecord($zoneId, $recordId);
                    if ($result && $result['success']) {
                        $results[] = ['record_id' => $recordId, 'status' => 'success'];
                        $successCount++;
                    } else {
                        $results[] = ['record_id' => $recordId, 'status' => 'failed', 'error' => 'API returned failure'];
                        $failureCount++;
                    }
                } catch (Exception $e) {
                    $results[] = ['record_id' => $recordId, 'status' => 'failed', 'error' => $e->getMessage()];
                    $failureCount++;
                }
            }
            break;
            
        case 'enable_proxy':
        case 'disable_proxy':
            $proxied = ($operation === 'enable_proxy');
            foreach ($recordIds as $recordId) {
                try {
                    $result = $cloudflare->updateDNSRecord($zoneId, $recordId, ['proxied' => $proxied]);
                    if ($result && $result['success']) {
                        $results[] = ['record_id' => $recordId, 'status' => 'success'];
                        $successCount++;
                    } else {
                        $results[] = ['record_id' => $recordId, 'status' => 'failed', 'error' => 'API returned failure'];
                        $failureCount++;
                    }
                } catch (Exception $e) {
                    $results[] = ['record_id' => $recordId, 'status' => 'failed', 'error' => $e->getMessage()];
                    $failureCount++;
                }
            }
            break;
            
        default:
            throw new Exception('Unsupported bulk operation: ' . $operation);
    }
    
    echo json_encode([
        'success' => true,
        'operation' => $operation,
        'results' => $results,
        'summary' => [
            'total_records' => count($recordIds),
            'successful' => $successCount,
            'failed' => $failureCount
        ],
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Validate DNS configuration
 */
function handleValidateDNSConfig($cloudflare, $zoneId) {
    if (!$zoneId) {
        throw new Exception('Zone ID is required');
    }
    
    $zoneInfo = $cloudflare->getZoneDetails($zoneId);
    $records = $cloudflare->listDNSRecords($zoneId);
    
    if (!$zoneInfo || !$records) {
        throw new Exception('Failed to retrieve zone information');
    }
    
    $zoneName = $zoneInfo['result']['name'];
    $dnsRecords = $records['result'] ?? [];
    
    $validation = [
        'zone_status' => $zoneInfo['result']['status'],
        'issues' => [],
        'recommendations' => [],
        'security_checks' => [],
        'performance_checks' => []
    ];
    
    // Check for common DNS issues
    $hasRootA = false;
    $hasRootAAAA = false;
    $hasWWW = false;
    $hasMX = false;
    $hasSPF = false;
    $hasDMARC = false;
    
    foreach ($dnsRecords as $record) {
        $name = $record['name'];
        $type = $record['type'];
        
        if ($name === $zoneName) {
            if ($type === 'A') $hasRootA = true;
            if ($type === 'AAAA') $hasRootAAAA = true;
        }
        
        if ($name === "www.{$zoneName}" && $type === 'A') {
            $hasWWW = true;
        }
        
        if ($type === 'MX') {
            $hasMX = true;
        }
        
        if ($type === 'TXT') {
            if (strpos($record['content'], 'v=spf') !== false) $hasSPF = true;
            if (strpos($record['content'], 'v=DMARC') !== false) $hasDMARC = true;
        }
    }
    
    // Generate validation results
    if (!$hasRootA) {
        $validation['issues'][] = 'Missing A record for root domain';
        $validation['recommendations'][] = 'Add an A record pointing to your server IP';
    }
    
    if (!$hasWWW) {
        $validation['recommendations'][] = 'Consider adding www subdomain (A or CNAME record)';
    }
    
    if ($hasMX && !$hasSPF) {
        $validation['security_checks'][] = 'MX records present but no SPF record found';
        $validation['recommendations'][] = 'Add SPF record to prevent email spoofing';
    }
    
    if ($hasMX && !$hasDMARC) {
        $validation['security_checks'][] = 'MX records present but no DMARC record found';
        $validation['recommendations'][] = 'Add DMARC record for enhanced email security';
    }
    
    // Performance checks
    $lowTTLCount = 0;
    $highTTLCount = 0;
    foreach ($dnsRecords as $record) {
        $ttl = $record['ttl'] ?? 1;
        if ($ttl < 300) $lowTTLCount++;
        if ($ttl > 86400) $highTTLCount++;
    }
    
    if ($lowTTLCount > 0) {
        $validation['performance_checks'][] = "{$lowTTLCount} records with very low TTL (< 5 minutes)";
    }
    
    echo json_encode([
        'success' => true,
        'zone_name' => $zoneName,
        'validation' => $validation,
        'record_count' => count($dnsRecords),
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Export DNS configuration
 */
function handleExportDNSConfig($cloudflare, $zoneId) {
    if (!$zoneId) {
        throw new Exception('Zone ID is required');
    }
    
    $format = $_GET['format'] ?? 'json'; // json, csv, bind
    
    $zoneInfo = $cloudflare->getZoneDetails($zoneId);
    $records = $cloudflare->listDNSRecords($zoneId);
    
    if (!$zoneInfo || !$records) {
        throw new Exception('Failed to retrieve DNS data');
    }
    
    $zoneName = $zoneInfo['result']['name'];
    $dnsRecords = $records['result'] ?? [];
    
    switch ($format) {
        case 'csv':
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="dns_export_' . $zoneName . '_' . date('Y-m-d') . '.csv"');
            
            echo "Type,Name,Content,TTL,Proxied,Priority,Comment\n";
            foreach ($dnsRecords as $record) {
                echo implode(',', [
                    $record['type'],
                    '"' . $record['name'] . '"',
                    '"' . $record['content'] . '"',
                    $record['ttl'],
                    $record['proxied'] ? 'true' : 'false',
                    $record['priority'] ?? '',
                    '"' . ($record['comment'] ?? '') . '"'
                ]) . "\n";
            }
            break;
            
        case 'bind':
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="dns_export_' . $zoneName . '_' . date('Y-m-d') . '.zone"');
            
            echo "; DNS Zone file for {$zoneName}\n";
            echo "; Exported on " . date('Y-m-d H:i:s') . "\n";
            echo "\n";
            
            foreach ($dnsRecords as $record) {
                $name = str_replace('.' . $zoneName, '', $record['name']);
                if ($name === $zoneName) $name = '@';
                
                printf("%-20s %-8s IN %-8s %s\n", 
                    $name,
                    $record['ttl'],
                    $record['type'],
                    $record['content']
                );
            }
            break;
            
        default: // json
            header('Content-Type: application/json');
            if (isset($_GET['download']) && $_GET['download'] === '1') {
                header('Content-Disposition: attachment; filename="dns_export_' . $zoneName . '_' . date('Y-m-d') . '.json"');
            }
            
            echo json_encode([
                'zone' => $zoneInfo['result'],
                'records' => $dnsRecords,
                'export_info' => [
                    'exported_at' => date('Y-m-d H:i:s'),
                    'total_records' => count($dnsRecords),
                    'zone_name' => $zoneName
                ]
            ], JSON_PRETTY_PRINT);
            break;
    }
}

/**
 * Import DNS configuration
 */
function handleImportDNSConfig($cloudflare, $zoneId) {
    if (!$zoneId) {
        throw new Exception('Zone ID is required');
    }
    
    if (!isset($_FILES['import_file'])) {
        throw new Exception('No import file provided');
    }
    
    $file = $_FILES['import_file'];
    $content = file_get_contents($file['tmp_name']);
    $importData = json_decode($content, true);
    
    if (!$importData || !isset($importData['records'])) {
        throw new Exception('Invalid import file format');
    }
    
    $dryRun = filter_var($_POST['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);
    $overwriteExisting = filter_var($_POST['overwrite_existing'] ?? false, FILTER_VALIDATE_BOOLEAN);
    
    $results = [
        'processed' => 0,
        'created' => 0,
        'updated' => 0,
        'skipped' => 0,
        'errors' => []
    ];
    
    // Get existing records for comparison
    $existingRecords = $cloudflare->listDNSRecords($zoneId);
    $existingMap = [];
    
    if ($existingRecords && isset($existingRecords['result'])) {
        foreach ($existingRecords['result'] as $record) {
            $key = $record['type'] . ':' . $record['name'];
            $existingMap[$key] = $record;
        }
    }
    
    foreach ($importData['records'] as $importRecord) {
        $results['processed']++;
        
        try {
            $recordKey = $importRecord['type'] . ':' . $importRecord['name'];
            $exists = isset($existingMap[$recordKey]);
            
            if ($exists && !$overwriteExisting) {
                $results['skipped']++;
                continue;
            }
            
            if (!$dryRun) {
                if ($exists) {
                    // Update existing record
                    $existingRecord = $existingMap[$recordKey];
                    $updateData = [
                        'content' => $importRecord['content'],
                        'ttl' => $importRecord['ttl'] ?? 1,
                        'proxied' => $importRecord['proxied'] ?? false
                    ];
                    
                    if (isset($importRecord['comment'])) {
                        $updateData['comment'] = $importRecord['comment'];
                    }
                    
                    $result = $cloudflare->updateDNSRecord($zoneId, $existingRecord['id'], $updateData);
                    if ($result && $result['success']) {
                        $results['updated']++;
                    }
                } else {
                    // Create new record
                    $result = $cloudflare->createDNSRecord(
                        $zoneId,
                        $importRecord['type'],
                        $importRecord['name'],
                        $importRecord['content'],
                        $importRecord['ttl'] ?? 1,
                        $importRecord['proxied'] ?? false,
                        $importRecord['comment'] ?? ''
                    );
                    
                    if ($result && $result['success']) {
                        $results['created']++;
                    }
                }
            } else {
                // Dry run - just count what would happen
                if ($exists) {
                    $results['updated']++;
                } else {
                    $results['created']++;
                }
            }
        } catch (Exception $e) {
            $results['errors'][] = [
                'record' => $importRecord,
                'error' => $e->getMessage()
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'dry_run' => $dryRun,
        'results' => $results,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
}

/**
 * Helper function to get DNS record types summary
 */
function getDNSRecordTypes($records) {
    $types = [];
    foreach ($records as $record) {
        $type = $record['type'];
        $types[$type] = ($types[$type] ?? 0) + 1;
    }
    return $types;
}

/**
 * Helper function to extract security settings
 */
function extractSecuritySettings($settings) {
    $security = [];
    foreach ($settings as $setting) {
        if (in_array($setting['id'], ['ssl', 'security_level', 'waf', 'always_use_https'])) {
            $security[$setting['id']] = $setting['value'];
        }
    }
    return $security;
}

/**
 * Helper function to extract performance settings
 */
function extractPerformanceSettings($settings) {
    $performance = [];
    foreach ($settings as $setting) {
        if (in_array($setting['id'], ['cache_level', 'development_mode', 'minify', 'browser_cache_ttl'])) {
            $performance[$setting['id']] = $setting['value'];
        }
    }
    return $performance;
}

/**
 * Helper function to get TTL description
 */
function getTTLDescription($ttl) {
    if ($ttl === 1) return 'Auto';
    if ($ttl < 60) return $ttl . ' seconds';
    if ($ttl < 3600) return floor($ttl / 60) . ' minutes';
    if ($ttl < 86400) return floor($ttl / 3600) . ' hours';
    return floor($ttl / 86400) . ' days';
}

/**
 * Helper function to validate DNS record
 */
function validateDNSRecord($record) {
    $issues = [];
    
    // Check TTL
    $ttl = $record['ttl'] ?? 1;
    if ($ttl > 1 && $ttl < 120) {
        $issues[] = 'TTL is very low (< 2 minutes)';
    }
    
    // Check content based on type
    $type = $record['type'];
    $content = $record['content'];
    
    switch ($type) {
        case 'A':
            if (!filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $issues[] = 'Invalid IPv4 address';
            }
            break;
        case 'AAAA':
            if (!filter_var($content, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $issues[] = 'Invalid IPv6 address';
            }
            break;
        case 'CNAME':
        case 'MX':
            if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $content)) {
                $issues[] = 'Invalid domain name format';
            }
            break;
    }
    
    return [
        'valid' => empty($issues),
        'issues' => $issues
    ];
}

/**
 * Helper function to validate DNS record data for creation/update
 */
function validateDNSRecordData($data, $requireAll = true) {
    $errors = [];
    
    if ($requireAll || isset($data['type'])) {
        $validTypes = ['A', 'AAAA', 'CNAME', 'MX', 'TXT', 'NS', 'SRV', 'PTR', 'CAA'];
        if (!in_array($data['type'] ?? '', $validTypes)) {
            $errors[] = 'Invalid record type';
        }
    }
    
    if ($requireAll || isset($data['name'])) {
        if (empty($data['name'])) {
            $errors[] = 'Name is required';
        }
    }
    
    if ($requireAll || isset($data['content'])) {
        if (empty($data['content'])) {
            $errors[] = 'Content is required';
        }
    }
    
    if (isset($data['ttl'])) {
        $ttl = $data['ttl'];
        if ($ttl !== 1 && ($ttl < 120 || $ttl > 2147483647)) {
            $errors[] = 'TTL must be 1 (auto) or between 120 and 2147483647 seconds';
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors
    ];
}

/**
 * Helper function to generate DNS recommendations
 */
function generateDNSRecommendations($analysis) {
    $recommendations = [];
    
    // Check for missing essential records
    if (!isset($analysis['by_type']['A'])) {
        $recommendations[] = 'Consider adding an A record for the root domain';
    }
    
    if (isset($analysis['mx_records']) && !empty($analysis['mx_records']) && !isset($analysis['by_type']['TXT'])) {
        $recommendations[] = 'Add SPF record to prevent email spoofing';
    }
    
    if ($analysis['proxied_count'] === 0 && isset($analysis['by_type']['A'])) {
        $recommendations[] = 'Consider enabling Cloudflare proxy for some A records for enhanced security and performance';
    }
    
    return $recommendations;
}

/**
 * Display DNS Management Dashboard UI
 */
function showDNSManagementDashboard() {
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Cloudflare DNS Management Dashboard</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            .dashboard-card {
                transition: transform 0.2s, box-shadow 0.2s;
                cursor: pointer;
            }
            .dashboard-card:hover {
                transform: translateY(-5px);
                box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            }
            .feature-icon {
                font-size: 2.5rem;
                margin-bottom: 1rem;
            }
            .zone-card {
                border-left: 4px solid #0d6efd;
            }
            .zone-card.active {
                border-left-color: #198754;
                background-color: #f8f9fa;
            }
            .zone-card.pending {
                border-left-color: #ffc107;
            }
            .record-type-badge {
                font-size: 0.75rem;
                margin: 0.1rem;
            }
            .action-buttons .btn {
                margin: 0.2rem;
            }
            .loading-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.5);
                display: none;
                justify-content: center;
                align-items: center;
                z-index: 9999;
            }
            .dns-record-row:hover {
                background-color: #f8f9fa;
            }
            .validation-issues {
                font-size: 0.8rem;
                color: #dc3545;
            }
            .sidebar {
                min-height: 100vh;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            }
            .sidebar .nav-link {
                color: rgba(255,255,255,0.8);
                transition: all 0.3s;
            }
            .sidebar .nav-link:hover,
            .sidebar .nav-link.active {
                color: white;
                background-color: rgba(255,255,255,0.1);
                border-radius: 0.5rem;
            }
            .main-content {
                background-color: #f8f9fa;
                min-height: 100vh;
            }
        </style>
    </head>
    <body>
        <div class="container-fluid">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-md-3 sidebar p-0">
                    <div class="p-4">
                        <h4 class="text-white mb-4">
                            <i class="fab fa-cloudflare"></i> DNS Manager
                        </h4>
                        <nav class="nav flex-column">
                            <a class="nav-link active" href="#" onclick="showDashboard()">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                            <a class="nav-link" href="#" onclick="showZones()">
                                <i class="fas fa-globe me-2"></i> Zones Management
                            </a>
                            <a class="nav-link" href="#" onclick="showDNSRecords()">
                                <i class="fas fa-list me-2"></i> DNS Records
                            </a>
                            <a class="nav-link" href="#" onclick="showBulkOperations()">
                                <i class="fas fa-tasks me-2"></i> Bulk Operations
                            </a>
                            <a class="nav-link" href="#" onclick="showValidation()">
                                <i class="fas fa-check-circle me-2"></i> DNS Validation
                            </a>
                            <a class="nav-link" href="#" onclick="showImportExport()">
                                <i class="fas fa-exchange-alt me-2"></i> Import/Export
                            </a>
                            <a class="nav-link" href="#" onclick="showAnalytics()">
                                <i class="fas fa-chart-line me-2"></i> Analytics
                            </a>
                            <hr class="border-light my-3">
                            <a class="nav-link" href="index.php?action=multi-domain-batch">
                                <i class="fas fa-layer-group me-2"></i> Multi-Domain API
                            </a>
                        </nav>
                    </div>
                </div>

                <!-- Main Content -->
                <div class="col-md-9 main-content p-4">
                    <!-- Dashboard Section -->
                    <div id="dashboard-section">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-tachometer-alt text-primary"></i> DNS Management Dashboard</h2>
                            <button class="btn btn-primary" onclick="refreshDashboard()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                        </div>

                        <!-- Quick Stats -->
                        <div class="row mb-4" id="quick-stats">
                            <div class="col-md-3">
                                <div class="card text-center dashboard-card">
                                    <div class="card-body">
                                        <i class="fas fa-globe feature-icon text-primary"></i>
                                        <h5 class="card-title">Total Zones</h5>
                                        <h3 class="text-primary" id="total-zones">-</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center dashboard-card">
                                    <div class="card-body">
                                        <i class="fas fa-list feature-icon text-success"></i>
                                        <h5 class="card-title">DNS Records</h5>
                                        <h3 class="text-success" id="total-records">-</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center dashboard-card">
                                    <div class="card-body">
                                        <i class="fas fa-shield-alt feature-icon text-warning"></i>
                                        <h5 class="card-title">Proxied Records</h5>
                                        <h3 class="text-warning" id="proxied-records">-</h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card text-center dashboard-card">
                                    <div class="card-body">
                                        <i class="fas fa-exclamation-triangle feature-icon text-danger"></i>
                                        <h5 class="card-title">Issues Found</h5>
                                        <h3 class="text-danger" id="total-issues">-</h3>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5><i class="fas fa-bolt"></i> Quick Actions</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <button class="btn btn-primary w-100" onclick="showCreateRecordModal()">
                                            <i class="fas fa-plus"></i> Create DNS Record
                                        </button>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <button class="btn btn-success w-100" onclick="validateAllZones()">
                                            <i class="fas fa-check-double"></i> Validate All Zones
                                        </button>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <button class="btn btn-info w-100" onclick="showBulkOperations()">
                                            <i class="fas fa-tasks"></i> Bulk Operations
                                        </button>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <a href="index.php?action=multi-domain-batch" class="btn btn-warning w-100">
                                            <i class="fas fa-layer-group"></i> Multi-Domain API
                                        </a>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <button class="btn btn-secondary w-100" onclick="showImportExport()">
                                            <i class="fas fa-exchange-alt"></i> Import/Export
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Recent Zones -->
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5><i class="fas fa-globe"></i> Your Zones</h5>
                                <button class="btn btn-sm btn-outline-primary" onclick="showZones()">
                                    View All
                                </button>
                            </div>
                            <div class="card-body" id="zones-list">
                                <div class="text-center">
                                    <i class="fas fa-spinner fa-spin"></i> Loading zones...
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Zones Management Section -->
                    <div id="zones-section" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-globe text-primary"></i> Zones Management</h2>
                            <div>
                                <button class="btn btn-success" onclick="refreshZones()">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                            </div>
                        </div>
                        
                        <!-- Zone Filters -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-3">
                                        <label class="form-label">Status Filter</label>
                                        <select class="form-select" id="zone-status-filter" onchange="filterZones()">
                                            <option value="">All Status</option>
                                            <option value="active">Active</option>
                                            <option value="pending">Pending</option>
                                            <option value="initializing">Initializing</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Search Zones</label>
                                        <input type="text" class="form-control" id="zone-search" placeholder="Domain name..." oninput="filterZones()">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Sort By</label>
                                        <select class="form-select" id="zone-sort" onchange="sortZones()">
                                            <option value="name">Name</option>
                                            <option value="status">Status</option>
                                            <option value="dns_records_count">DNS Records</option>
                                            <option value="created_on">Created Date</option>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Items per page</label>
                                        <select class="form-select" id="zone-per-page" onchange="filterZones()">
                                            <option value="10">10</option>
                                            <option value="25" selected>25</option>
                                            <option value="50">50</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div id="zones-list-detailed">
                            <!-- Zones will be loaded here -->
                        </div>
                    </div>

                    <!-- DNS Records Section -->
                    <div id="dns-records-section" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-list text-primary"></i> DNS Records Management</h2>
                            <div>
                                <button class="btn btn-primary" onclick="showCreateRecordModal()">
                                    <i class="fas fa-plus"></i> Add Record
                                </button>
                                <button class="btn btn-success" onclick="refreshDNSRecords()">
                                    <i class="fas fa-sync-alt"></i> Refresh
                                </button>
                            </div>
                        </div>

                        <!-- Zone Selector -->
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4">
                                        <label class="form-label">Select Zone</label>
                                        <select class="form-select" id="record-zone-selector" onchange="loadDNSRecordsForZone()">
                                            <option value="">Choose a zone...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Record Type</label>
                                        <select class="form-select" id="record-type-filter" onchange="filterDNSRecords()">
                                            <option value="">All Types</option>
                                            <option value="A">A</option>
                                            <option value="AAAA">AAAA</option>
                                            <option value="CNAME">CNAME</option>
                                            <option value="MX">MX</option>
                                            <option value="TXT">TXT</option>
                                            <option value="NS">NS</option>
                                        </select>
                                    </div>
                                    <div class="col-md-2">
                                        <label class="form-label">Proxy Status</label>
                                        <select class="form-select" id="proxy-filter" onchange="filterDNSRecords()">
                                            <option value="">All</option>
                                            <option value="true">Proxied</option>
                                            <option value="false">DNS Only</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Search Records</label>
                                        <input type="text" class="form-control" id="dns-record-search" placeholder="Name or content..." oninput="filterDNSRecords()">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div id="dns-records-list">
                            <div class="text-center text-muted">
                                <i class="fas fa-info-circle"></i> Please select a zone to view DNS records
                            </div>
                        </div>
                    </div>

                    <!-- Bulk Operations Section -->
                    <div id="bulk-operations-section" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-tasks text-primary"></i> Bulk Operations</h2>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-trash"></i> Bulk Delete Records</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>Delete multiple DNS records at once.</p>
                                        <button class="btn btn-danger" onclick="initiateBulkDelete()">
                                            <i class="fas fa-trash"></i> Start Bulk Delete
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-shield-alt"></i> Bulk Proxy Management</h5>
                                    </div>
                                    <div class="card-body">
                                        <p>Enable or disable Cloudflare proxy for multiple records.</p>
                                        <button class="btn btn-warning me-2" onclick="initiateBulkProxy(true)">
                                            <i class="fas fa-shield-alt"></i> Enable Proxy
                                        </button>
                                        <button class="btn btn-secondary" onclick="initiateBulkProxy(false)">
                                            <i class="fas fa-globe"></i> Disable Proxy
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Validation Section -->
                    <div id="validation-section" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-check-circle text-primary"></i> DNS Validation</h2>
                            <button class="btn btn-primary" onclick="validateAllZones()">
                                <i class="fas fa-play"></i> Validate All
                            </button>
                        </div>

                        <div id="validation-results">
                            <!-- Validation results will be displayed here -->
                        </div>
                    </div>

                    <!-- Import/Export Section -->
                    <div id="import-export-section" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-exchange-alt text-primary"></i> Import/Export</h2>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-download"></i> Export DNS Configuration</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Select Zone</label>
                                            <select class="form-select" id="export-zone-selector">
                                                <option value="">Choose a zone...</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Export Format</label>
                                            <select class="form-select" id="export-format">
                                                <option value="json">JSON</option>
                                                <option value="csv">CSV</option>
                                                <option value="bind">BIND Zone File</option>
                                            </select>
                                        </div>
                                        <button class="btn btn-success" onclick="exportDNSConfig()">
                                            <i class="fas fa-download"></i> Export
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5><i class="fas fa-upload"></i> Import DNS Configuration</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label class="form-label">Select Zone</label>
                                            <select class="form-select" id="import-zone-selector">
                                                <option value="">Choose a zone...</option>
                                            </select>
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label">Import File</label>
                                            <input type="file" class="form-control" id="import-file" accept=".json">
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="dry-run-check" checked>
                                            <label class="form-check-label" for="dry-run-check">
                                                Dry Run (Preview only)
                                            </label>
                                        </div>
                                        <div class="form-check mb-3">
                                            <input class="form-check-input" type="checkbox" id="overwrite-check">
                                            <label class="form-check-label" for="overwrite-check">
                                                Overwrite existing records
                                            </label>
                                        </div>
                                        <button class="btn btn-primary" onclick="importDNSConfig()">
                                            <i class="fas fa-upload"></i> Import
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Analytics Section -->
                    <div id="analytics-section" style="display: none;">
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h2><i class="fas fa-chart-line text-primary"></i> DNS Analytics</h2>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Record Types Distribution</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="recordTypesChart"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-header">
                                        <h5>Proxy Status Distribution</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="proxyStatusChart"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div class="loading-overlay" id="loading-overlay">
            <div class="text-center text-white">
                <i class="fas fa-spinner fa-spin fa-3x mb-3"></i>
                <h4>Processing...</h4>
            </div>
        </div>

        <!-- Create Record Modal -->
        <div class="modal fade" id="createRecordModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-plus"></i> Create DNS Record</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form id="create-record-form">
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Zone *</label>
                                    <select class="form-select" id="create-zone-id" required>
                                        <option value="">Select zone...</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Record Type *</label>
                                    <select class="form-select" id="create-record-type" required onchange="updateRecordForm()">
                                        <option value="">Select type...</option>
                                        <option value="A">A</option>
                                        <option value="AAAA">AAAA</option>
                                        <option value="CNAME">CNAME</option>
                                        <option value="MX">MX</option>
                                        <option value="TXT">TXT</option>
                                        <option value="NS">NS</option>
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Name *</label>
                                <input type="text" class="form-control" id="create-record-name" required placeholder="subdomain or @ for root">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Content *</label>
                                <input type="text" class="form-control" id="create-record-content" required>
                                <div class="form-text" id="content-help">Enter the target for this record</div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">TTL</label>
                                    <select class="form-select" id="create-record-ttl">
                                        <option value="1">Auto</option>
                                        <option value="120">2 minutes</option>
                                        <option value="300">5 minutes</option>
                                        <option value="600">10 minutes</option>
                                        <option value="3600">1 hour</option>
                                        <option value="86400">1 day</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3" id="priority-field" style="display: none;">
                                    <label class="form-label">Priority</label>
                                    <input type="number" class="form-control" id="create-record-priority" min="0" max="65535">
                                </div>
                            </div>
                            <div class="mb-3" id="proxy-field">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="create-record-proxied">
                                    <label class="form-check-label" for="create-record-proxied">
                                        <i class="fas fa-shield-alt text-warning"></i> Proxy through Cloudflare
                                    </label>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Comment</label>
                                <input type="text" class="form-control" id="create-record-comment" placeholder="Optional comment">
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Create Record
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Global variables
            let currentSection = 'dashboard';
            let zonesData = [];
            let currentZoneId = null;
            let dnsRecordsData = [];
            
            // Initialize dashboard
            document.addEventListener('DOMContentLoaded', function() {
                loadDashboardData();
                loadZoneSelectorOptions();
            });

            // Navigation functions
            function showSection(sectionName) {
                // Hide all sections
                document.querySelectorAll('[id$="-section"]').forEach(section => {
                    section.style.display = 'none';
                });
                
                // Show selected section
                document.getElementById(sectionName + '-section').style.display = 'block';
                
                // Update navigation
                document.querySelectorAll('.nav-link').forEach(link => {
                    link.classList.remove('active');
                });
                
                currentSection = sectionName;
                
                // Load section-specific data
                switch(sectionName) {
                    case 'zones':
                        loadZones();
                        break;
                    case 'dns-records':
                        loadZoneSelectorOptions();
                        break;
                    case 'validation':
                        loadValidationData();
                        break;
                    case 'analytics':
                        loadAnalyticsData();
                        break;
                }
            }

            function showDashboard() { showSection('dashboard'); }
            function showZones() { showSection('zones'); }
            function showDNSRecords() { showSection('dns-records'); }
            function showBulkOperations() { showSection('bulk-operations'); }
            function showValidation() { showSection('validation'); }
            function showImportExport() { showSection('import-export'); }
            function showAnalytics() { showSection('analytics'); }

            // Dashboard functions
            async function loadDashboardData() {
                try {
                    showLoading();
                    const response = await fetch('index.php?action=manage-dns&action=list_zones');
                    const data = await response.json();
                    
                    if (data.success) {
                        zonesData = data.zones;
                        updateDashboardStats(data);
                        displayZonesList(data.zones.slice(0, 5)); // Show first 5 zones
                    }
                } catch (error) {
                    showAlert('Error loading dashboard: ' + error.message, 'danger');
                } finally {
                    hideLoading();
                }
            }

            function updateDashboardStats(data) {
                const totalZones = data.zones.length;
                let totalRecords = 0;
                let proxiedRecords = 0;
                let totalIssues = 0;
                
                data.zones.forEach(zone => {
                    totalRecords += zone.dns_records_summary.total_records;
                    // Add logic for counting proxied records and issues
                });
                
                document.getElementById('total-zones').textContent = totalZones;
                document.getElementById('total-records').textContent = totalRecords;
                document.getElementById('proxied-records').textContent = proxiedRecords;
                document.getElementById('total-issues').textContent = totalIssues;
            }

            function displayZonesList(zones) {
                const container = document.getElementById('zones-list');
                let html = '';
                
                zones.forEach(zone => {
                    const zoneInfo = zone.zone_info;
                    const recordTypes = Object.keys(zone.dns_records_summary.record_types || {});
                    
                    html += `
                        <div class="zone-card card mb-3 ${zoneInfo.status}">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-6">
                                        <h6 class="card-title mb-1">
                                            <i class="fas fa-globe text-primary"></i> ${zoneInfo.name}
                                        </h6>
                                        <small class="text-muted">
                                            Status: <span class="badge bg-${getStatusColor(zoneInfo.status)}">${zoneInfo.status}</span>
                                            Plan: <span class="badge bg-info">${zoneInfo.plan?.name || 'Free'}</span>
                                        </small>
                                    </div>
                                    <div class="col-md-3">
                                        <small class="text-muted">DNS Records: <strong>${zone.dns_records_summary.total_records}</strong></small><br>
                                        <div class="record-types">
                                            ${recordTypes.map(type => `<span class="badge bg-secondary record-type-badge">${type}</span>`).join(' ')}
                                        </div>
                                    </div>
                                    <div class="col-md-3 text-end">
                                        <button class="btn btn-sm btn-primary" onclick="viewZoneDetails('${zoneInfo.id}')">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                        <button class="btn btn-sm btn-success" onclick="manageDNSRecords('${zoneInfo.id}')">
                                            <i class="fas fa-cog"></i> Manage
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                container.innerHTML = html;
            }

            function getStatusColor(status) {
                switch(status) {
                    case 'active': return 'success';
                    case 'pending': return 'warning';
                    case 'initializing': return 'info';
                    default: return 'secondary';
                }
            }

            // Utility functions
            function showLoading() {
                document.getElementById('loading-overlay').style.display = 'flex';
            }

            function hideLoading() {
                document.getElementById('loading-overlay').style.display = 'none';
            }

            function showAlert(message, type = 'info') {
                // Create and show bootstrap alert
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
                alertDiv.innerHTML = `
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                `;
                
                document.querySelector('.main-content').insertAdjacentElement('afterbegin', alertDiv);
                
                // Auto remove after 5 seconds
                setTimeout(() => {
                    alertDiv.remove();
                }, 5000);
            }

            // Add more JavaScript functions for handling various operations...
            // (Create Record, Load Zones, Manage DNS Records, etc.)
            
            function showCreateRecordModal() {
                new bootstrap.Modal(document.getElementById('createRecordModal')).show();
            }

            function loadZoneSelectorOptions() {
                // Load zones for dropdowns
                // Implementation here...
            }

            function refreshDashboard() {
                loadDashboardData();
            }

            // Add event listeners and more functionality...
            
            // Load zones for selectors
            async function loadZoneSelectorOptions() {
                try {
                    const response = await fetch('index.php?action=manage-dns&action=list_zones&per_page=100');
                    const data = await response.json();
                    
                    if (data.success) {
                        const selectors = ['record-zone-selector', 'export-zone-selector', 'import-zone-selector', 'create-zone-id'];
                        
                        selectors.forEach(selectorId => {
                            const selector = document.getElementById(selectorId);
                            if (selector) {
                                selector.innerHTML = '<option value="">Choose a zone...</option>';
                                data.zones.forEach(zone => {
                                    const option = document.createElement('option');
                                    option.value = zone.zone_info.id;
                                    option.textContent = zone.zone_info.name;
                                    selector.appendChild(option);
                                });
                            }
                        });
                    }
                } catch (error) {
                    console.error('Error loading zones:', error);
                }
            }

            // DNS Records Management
            async function loadDNSRecordsForZone() {
                const zoneId = document.getElementById('record-zone-selector').value;
                if (!zoneId) {
                    document.getElementById('dns-records-list').innerHTML = `
                        <div class="text-center text-muted">
                            <i class="fas fa-info-circle"></i> Please select a zone to view DNS records
                        </div>
                    `;
                    return;
                }
                
                currentZoneId = zoneId;
                
                try {
                    showLoading();
                    const response = await fetch(`index.php?action=manage-dns&action=list_dns_records&zone_id=${zoneId}&per_page=100`);
                    const data = await response.json();
                    
                    if (data.success) {
                        dnsRecordsData = data.records;
                        displayDNSRecords(data.records);
                    } else {
                        showAlert('Error loading DNS records: ' + data.error, 'danger');
                    }
                } catch (error) {
                    showAlert('Network error: ' + error.message, 'danger');
                } finally {
                    hideLoading();
                }
            }

            function displayDNSRecords(records) {
                const container = document.getElementById('dns-records-list');
                
                if (records.length === 0) {
                    container.innerHTML = `
                        <div class="text-center text-muted py-4">
                            <i class="fas fa-inbox"></i> No DNS records found
                        </div>
                    `;
                    return;
                }
                
                let html = `
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h6><i class="fas fa-list"></i> DNS Records (${records.length})</h6>
                            <div class="action-buttons">
                                <button class="btn btn-sm btn-primary" onclick="showCreateRecordModal()">
                                    <i class="fas fa-plus"></i> Add Record
                                </button>
                                <button class="btn btn-sm btn-warning" onclick="selectAllRecords()">
                                    <i class="fas fa-check-square"></i> Select All
                                </button>
                                <button class="btn btn-sm btn-danger" onclick="deleteSelectedRecords()" id="delete-selected-btn" style="display: none;">
                                    <i class="fas fa-trash"></i> Delete Selected
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="table-light">
                                    <tr>
                                        <th><input type="checkbox" id="select-all-records" onchange="toggleAllRecords()"></th>
                                        <th>Type</th>
                                        <th>Name</th>
                                        <th>Content</th>
                                        <th>TTL</th>
                                        <th>Proxy</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;
                
                records.forEach(record => {
                    const proxyBadge = record.proxied ? 
                        '<span class="badge bg-warning"><i class="fas fa-shield-alt"></i> Proxied</span>' :
                        '<span class="badge bg-secondary"><i class="fas fa-globe"></i> DNS Only</span>';
                    
                    const validation = record.validation || { valid: true, issues: [] };
                    const validationStatus = validation.valid ? 
                        '<i class="fas fa-check-circle text-success" title="Valid"></i>' :
                        `<i class="fas fa-exclamation-triangle text-danger" title="${validation.issues.join(', ')}"></i>`;
                    
                    html += `
                        <tr class="dns-record-row" data-record-id="${record.id}">
                            <td><input type="checkbox" class="record-checkbox" value="${record.id}"></td>
                            <td><span class="badge bg-primary">${record.type}</span></td>
                            <td class="fw-bold">${record.name}</td>
                            <td class="text-truncate" style="max-width: 200px;" title="${record.content}">${record.content}</td>
                            <td><small class="text-muted">${record.ttl_description || (record.ttl === 1 ? 'Auto' : record.ttl + 's')}</small></td>
                            <td>${proxyBadge}</td>
                            <td>
                                ${validationStatus}
                                <button class="btn btn-sm btn-outline-primary" onclick="editRecord('${record.id}')" title="Edit">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteRecord('${record.id}')" title="Delete">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
                
                container.innerHTML = html;
                
                // Add event listeners for checkboxes
                document.querySelectorAll('.record-checkbox').forEach(checkbox => {
                    checkbox.addEventListener('change', updateSelectedCount);
                });
            }

            function updateSelectedCount() {
                const selected = document.querySelectorAll('.record-checkbox:checked').length;
                const deleteBtn = document.getElementById('delete-selected-btn');
                
                if (selected > 0) {
                    deleteBtn.style.display = 'inline-block';
                    deleteBtn.innerHTML = `<i class="fas fa-trash"></i> Delete Selected (${selected})`;
                } else {
                    deleteBtn.style.display = 'none';
                }
            }

            function toggleAllRecords() {
                const selectAll = document.getElementById('select-all-records').checked;
                document.querySelectorAll('.record-checkbox').forEach(checkbox => {
                    checkbox.checked = selectAll;
                });
                updateSelectedCount();
            }

            // Create DNS Record
            document.getElementById('create-record-form').addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const formData = new FormData();
                formData.append('action', 'create_dns_record');
                formData.append('zone_id', document.getElementById('create-zone-id').value);
                formData.append('type', document.getElementById('create-record-type').value);
                formData.append('name', document.getElementById('create-record-name').value);
                formData.append('content', document.getElementById('create-record-content').value);
                formData.append('ttl', document.getElementById('create-record-ttl').value);
                formData.append('proxied', document.getElementById('create-record-proxied').checked);
                formData.append('comment', document.getElementById('create-record-comment').value);
                
                const priority = document.getElementById('create-record-priority').value;
                if (priority) formData.append('priority', priority);
                
                try {
                    showLoading();
                    const response = await fetch('index.php?action=manage-dns', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showAlert('DNS record created successfully!', 'success');
                        bootstrap.Modal.getInstance(document.getElementById('createRecordModal')).hide();
                        document.getElementById('create-record-form').reset();
                        
                        // Refresh records if we're viewing the same zone
                        if (currentZoneId === document.getElementById('create-zone-id').value) {
                            loadDNSRecordsForZone();
                        }
                    } else {
                        showAlert('Error creating record: ' + data.error, 'danger');
                    }
                } catch (error) {
                    showAlert('Network error: ' + error.message, 'danger');
                } finally {
                    hideLoading();
                }
            });

            function updateRecordForm() {
                const type = document.getElementById('create-record-type').value;
                const priorityField = document.getElementById('priority-field');
                const proxyField = document.getElementById('proxy-field');
                const contentHelp = document.getElementById('content-help');
                
                // Show/hide priority field for MX records
                if (type === 'MX' || type === 'SRV') {
                    priorityField.style.display = 'block';
                } else {
                    priorityField.style.display = 'none';
                }
                
                // Show/hide proxy option for proxiable records
                if (['A', 'AAAA', 'CNAME'].includes(type)) {
                    proxyField.style.display = 'block';
                } else {
                    proxyField.style.display = 'none';
                    document.getElementById('create-record-proxied').checked = false;
                }
                
                // Update content help text
                const helpTexts = {
                    'A': 'Enter IPv4 address (e.g., 192.168.1.1)',
                    'AAAA': 'Enter IPv6 address (e.g., 2001:db8::1)',
                    'CNAME': 'Enter target domain (e.g., example.com)',
                    'MX': 'Enter mail server (e.g., mail.example.com)',
                    'TXT': 'Enter text content',
                    'NS': 'Enter nameserver (e.g., ns1.example.com)'
                };
                
                contentHelp.textContent = helpTexts[type] || 'Enter the target for this record';
            }

            // Delete Record
            async function deleteRecord(recordId) {
                if (!confirm('Are you sure you want to delete this DNS record?')) {
                    return;
                }
                
                try {
                    showLoading();
                    const response = await fetch('index.php?action=manage-dns', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=delete_dns_record&zone_id=${currentZoneId}&record_id=${recordId}`
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showAlert('DNS record deleted successfully!', 'success');
                        loadDNSRecordsForZone();
                    } else {
                        showAlert('Error deleting record: ' + data.error, 'danger');
                    }
                } catch (error) {
                    showAlert('Network error: ' + error.message, 'danger');
                } finally {
                    hideLoading();
                }
            }

            // Bulk operations
            async function deleteSelectedRecords() {
                const selected = Array.from(document.querySelectorAll('.record-checkbox:checked'))
                    .map(cb => cb.value);
                
                if (selected.length === 0) {
                    showAlert('No records selected', 'warning');
                    return;
                }
                
                if (!confirm(`Are you sure you want to delete ${selected.length} DNS record(s)?`)) {
                    return;
                }
                
                try {
                    showLoading();
                    const response = await fetch('index.php?action=manage-dns', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `action=bulk_dns_operations&zone_id=${currentZoneId}&operation=delete_multiple&record_ids=${JSON.stringify(selected)}`
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        showAlert(`Bulk operation completed: ${data.summary.successful} successful, ${data.summary.failed} failed`, 'success');
                        loadDNSRecordsForZone();
                    } else {
                        showAlert('Error in bulk operation: ' + data.error, 'danger');
                    }
                } catch (error) {
                    showAlert('Network error: ' + error.message, 'danger');
                } finally {
                    hideLoading();
                }
            }

            // Export functionality
            async function exportDNSConfig() {
                const zoneId = document.getElementById('export-zone-selector').value;
                const format = document.getElementById('export-format').value;
                
                if (!zoneId) {
                    showAlert('Please select a zone to export', 'warning');
                    return;
                }
                
                try {
                    const url = `index.php?action=manage-dns&action=export_dns_config&zone_id=${zoneId}&format=${format}&download=1`;
                    window.open(url, '_blank');
                    showAlert('Export started! Check your downloads.', 'success');
                } catch (error) {
                    showAlert('Export error: ' + error.message, 'danger');
                }
            }

            // Import functionality
            async function importDNSConfig() {
                const zoneId = document.getElementById('import-zone-selector').value;
                const fileInput = document.getElementById('import-file');
                const dryRun = document.getElementById('dry-run-check').checked;
                const overwrite = document.getElementById('overwrite-check').checked;
                
                if (!zoneId || !fileInput.files[0]) {
                    showAlert('Please select a zone and import file', 'warning');
                    return;
                }
                
                try {
                    showLoading();
                    const formData = new FormData();
                    formData.append('action', 'import_dns_config');
                    formData.append('zone_id', zoneId);
                    formData.append('import_file', fileInput.files[0]);
                    formData.append('dry_run', dryRun);
                    formData.append('overwrite_existing', overwrite);
                    
                    const response = await fetch('index.php?action=manage-dns', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        const results = data.results;
                        let message = `Import ${dryRun ? 'preview' : 'completed'}:<br>`;
                        message += `Processed: ${results.processed}<br>`;
                        message += `Created: ${results.created}<br>`;
                        message += `Updated: ${results.updated}<br>`;
                        message += `Skipped: ${results.skipped}<br>`;
                        if (results.errors.length > 0) {
                            message += `Errors: ${results.errors.length}`;
                        }
                        
                        showAlert(message, 'success');
                        
                        if (!dryRun && currentZoneId === zoneId) {
                            loadDNSRecordsForZone();
                        }
                    } else {
                        showAlert('Import error: ' + data.error, 'danger');
                    }
                } catch (error) {
                    showAlert('Network error: ' + error.message, 'danger');
                } finally {
                    hideLoading();
                }
            }

            // Validation
            async function validateAllZones() {
                try {
                    showLoading();
                    const validationResults = document.getElementById('validation-results');
                    validationResults.innerHTML = '';
                    
                    for (const zone of zonesData) {
                        const response = await fetch(`index.php?action=manage-dns&action=validate_dns_config&zone_id=${zone.zone_info.id}`);
                        const data = await response.json();
                        
                        if (data.success) {
                            displayValidationResult(zone.zone_info.name, data.validation);
                        }
                    }
                } catch (error) {
                    showAlert('Validation error: ' + error.message, 'danger');
                } finally {
                    hideLoading();
                }
            }

            function displayValidationResult(zoneName, validation) {
                const container = document.getElementById('validation-results');
                
                const issuesCount = validation.issues.length + validation.security_checks.length + validation.performance_checks.length;
                const statusColor = issuesCount === 0 ? 'success' : issuesCount < 3 ? 'warning' : 'danger';
                
                const card = document.createElement('div');
                card.className = 'card mb-3';
                card.innerHTML = `
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6><i class="fas fa-globe"></i> ${zoneName}</h6>
                        <span class="badge bg-${statusColor}">
                            ${issuesCount === 0 ? 'All Good' : issuesCount + ' Issues'}
                        </span>
                    </div>
                    <div class="card-body">
                        ${validation.issues.length > 0 ? 
                            `<div class="alert alert-danger"><strong>Issues:</strong><ul>${validation.issues.map(issue => '<li>' + issue + '</li>').join('')}</ul></div>` : ''}
                        ${validation.security_checks.length > 0 ? 
                            `<div class="alert alert-warning"><strong>Security:</strong><ul>${validation.security_checks.map(check => '<li>' + check + '</li>').join('')}</ul></div>` : ''}
                        ${validation.recommendations.length > 0 ? 
                            `<div class="alert alert-info"><strong>Recommendations:</strong><ul>${validation.recommendations.map(rec => '<li>' + rec + '</li>').join('')}</ul></div>` : ''}
                    </div>
                `;
                
                container.appendChild(card);
            }

            // Zone management
            function viewZoneDetails(zoneId) {
                // Implementation for viewing zone details
                showAlert('Zone details view - Coming soon!', 'info');
            }

            function manageDNSRecords(zoneId) {
                showDNSRecords();
                document.getElementById('record-zone-selector').value = zoneId;
                loadDNSRecordsForZone();
            }

            // Filter functions
            function filterDNSRecords() {
                // Implementation for filtering DNS records
                if (!dnsRecordsData || dnsRecordsData.length === 0) return;
                
                const typeFilter = document.getElementById('record-type-filter').value;
                const proxyFilter = document.getElementById('proxy-filter').value;
                const searchFilter = document.getElementById('dns-record-search').value.toLowerCase();
                
                let filtered = dnsRecordsData.filter(record => {
                    if (typeFilter && record.type !== typeFilter) return false;
                    if (proxyFilter !== '' && String(record.proxied) !== proxyFilter) return false;
                    if (searchFilter && !record.name.toLowerCase().includes(searchFilter) && 
                        !record.content.toLowerCase().includes(searchFilter)) return false;
                    return true;
                });
                
                displayDNSRecords(filtered);
            }

            function refreshDNSRecords() {
                if (currentZoneId) {
                    loadDNSRecordsForZone();
                }
            }

            function refreshZones() {
                loadDashboardData();
            }

            // Additional helper functions...
            function selectAllRecords() {
                document.getElementById('select-all-records').checked = true;
                toggleAllRecords();
            }
        </script>
    </body>
    </html>
    <?php
}

/**
 * Enhanced error handling for all DNS management functions
 */
function showDNSRecordsPage() {
    $zoneId = $_GET['zone_id'] ?? null;
    $domain = $_GET['domain'] ?? null;
    $recordType = $_GET['record_type'] ?? null;
    $pageSize = (int)($_GET['page_size'] ?? 20);
    
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Hiển thị DNS Records</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            :root {
                --primary-color: #667eea;
                --secondary-color: #764ba2;
                --success-color: #28a745;
                --info-color: #17a2b8;
                --warning-color: #ffc107;
                --danger-color: #dc3545;
            }
            
            .gradient-header {
                background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
                color: white;
                padding: 40px 0;
            }
            
            .dns-card {
                border: none;
                border-radius: 15px;
                box-shadow: 0 4px 20px rgba(0,0,0,0.08);
                margin-bottom: 25px;
                transition: transform 0.2s;
            }
            
            .dns-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 25px rgba(0,0,0,0.12);
            }
            
            .record-type-badge {
                display: inline-block;
                padding: 4px 10px;
                border-radius: 20px;
                font-weight: 600;
                font-size: 0.8em;
                min-width: 60px;
                text-align: center;
                margin-right: 8px;
            }
            
            .badge-A { background: #d4edda; color: #155724; }
            .badge-AAAA { background: #cce7ff; color: #0056b3; }
            .badge-CNAME { background: #fff3cd; color: #856404; }
            .badge-MX { background: #f8d7da; color: #721c24; }
            .badge-TXT { background: #e2e3e5; color: #383d41; }
            .badge-NS { background: #d1ecf1; color: #0c5460; }
            .badge-SOA { background: #f4f4f5; color: #6c757d; }
            .badge-SRV { background: #e7e3ff; color: #5a2d82; }
            .badge-CAA { background: #ffeaa7; color: #2d3436; }
            
            .proxied-badge {
                background: #ff6b35;
                color: white;
                font-size: 0.7em;
                padding: 2px 6px;
                border-radius: 10px;
                margin-left: 8px;
            }
            
            .filter-section {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 10px;
                margin-bottom: 25px;
            }
            
            .zones-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
                gap: 20px;
                margin-top: 20px;
            }
            
            .zone-card {
                background: white;
                border: 2px solid #e9ecef;
                border-radius: 10px;
                padding: 20px;
                text-decoration: none;
                transition: all 0.2s;
                display: block;
                color: inherit;
            }
            
            .zone-card:hover {
                border-color: var(--primary-color);
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
                text-decoration: none;
                color: inherit;
            }
            
            .loading-spinner {
                display: none;
                text-align: center;
                margin: 40px 0;
            }
            
            .dns-records-container {
                max-height: 600px;
                overflow-y: auto;
                border: 1px solid #dee2e6;
                border-radius: 8px;
            }
            
            .record-row {
                padding: 12px 15px;
                border-bottom: 1px solid #eee;
                transition: background 0.2s;
            }
            
            .record-row:hover {
                background: #f8f9fa;
            }
            
            .record-row:last-child {
                border-bottom: none;
            }
            
            .record-content {
                font-family: 'Roboto Mono', monospace;
                background: #f1f3f4;
                padding: 4px 8px;
                border-radius: 4px;
                font-size: 0.9em;
                word-break: break-all;
            }
        </style>
    </head>
    <body>
        <div class="gradient-header">
            <div class="container">
                <h1 class="display-4 mb-3">
                    <i class="fas fa-list-ul me-3"></i>
                    Hiển thị DNS Records
                </h1>
                <p class="lead mb-0">Xem và quản lý DNS Records của các zone trên Cloudflare</p>
            </div>
        </div>
        
        <div class="container mt-4">
            <!-- Zone Selection -->
            <div id="zoneSelectionSection" <?php echo $zoneId ? 'style="display:none"' : ''; ?>>
                <div class="dns-card">
                    <div class="card-body">
                        <h4 class="card-title">
                            <i class="fas fa-globe text-primary me-2"></i>
                            Chọn Zone/Domain
                        </h4>
                        <p class="text-muted mb-3">Chọn zone bạn muốn xem DNS records</p>
                        
                        <!-- Search and Filter -->
                        <div class="filter-section">
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                                        <input type="text" id="zoneSearchInput" class="form-control" placeholder="Tìm kiếm domain..." onkeyup="filterZones()">
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <select id="zoneStatusFilter" class="form-select" onchange="filterZones()">
                                        <option value="">Tất cả trạng thái</option>
                                        <option value="active">Active</option>
                                        <option value="pending">Pending</option>
                                        <option value="moved">Moved</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Loading Spinner -->
                        <div class="loading-spinner" id="zonesLoading">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted">Đang tải danh sách zones...</p>
                        </div>
                        
                        <!-- Zones Grid -->
                        <div id="zonesGrid" class="zones-grid">
                            <!-- Zones will be populated here -->
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- DNS Records Section -->
            <div id="dnsRecordsSection" <?php echo !$zoneId ? 'style="display:none"' : ''; ?>>
                <div class="dns-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <h4 class="card-title mb-0">
                                <i class="fas fa-list text-success me-2"></i>
                                DNS Records
                                <span id="selectedZoneName" class="text-muted"></span>
                            </h4>
                            <div class="btn-group">
                                <button type="button" class="btn btn-outline-secondary" onclick="showZoneSelection()">
                                    <i class="fas fa-arrow-left me-1"></i>
                                    Quay lại
                                </button>
                                <button type="button" class="btn btn-outline-success" onclick="refreshDNSRecords()">
                                    <i class="fas fa-sync-alt me-1"></i>
                                    Tải lại
                                </button>
                                <button type="button" class="btn btn-outline-info" onclick="exportDNSRecords()">
                                    <i class="fas fa-download me-1"></i>
                                    Xuất CSV
                                </button>
                            </div>
                        </div>
                        
                        <!-- DNS Records Filter -->
                        <div class="filter-section">
                            <div class="row">
                                <div class="col-md-4">
                                    <label class="form-label">Loại Record:</label>
                                    <select id="recordTypeFilter" class="form-select" onchange="filterDNSRecords()">
                                        <option value="">Tất cả</option>
                                        <option value="A">A Records</option>
                                        <option value="AAAA">AAAA Records</option>
                                        <option value="CNAME">CNAME Records</option>
                                        <option value="MX">MX Records</option>
                                        <option value="TXT">TXT Records</option>
                                        <option value="NS">NS Records</option>
                                        <option value="SRV">SRV Records</option>
                                        <option value="CAA">CAA Records</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Tìm kiếm:</label>
                                    <input type="text" id="recordSearchInput" class="form-control" placeholder="Tìm theo name hoặc content..." onkeyup="filterDNSRecords()">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Proxy Status:</label>
                                    <select id="proxyFilter" class="form-select" onchange="filterDNSRecords()">
                                        <option value="">Tất cả</option>
                                        <option value="true">Proxied</option>
                                        <option value="false">DNS Only</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Statistics -->
                        <div id="dnsStatistics" class="row text-center mb-4">
                            <!-- Statistics will be populated here -->
                        </div>
                        
                        <!-- Loading Spinner -->
                        <div class="loading-spinner" id="recordsLoading">
                            <div class="spinner-border text-success" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p class="mt-2 text-muted">Đang tải DNS records...</p>
                        </div>
                        
                        <!-- DNS Records Container -->
                        <div id="dnsRecordsContainer" class="dns-records-container">
                            <!-- DNS Records will be populated here -->
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Global variables
            let allZones = [];
            let allDNSRecords = [];
            let currentZoneId = <?php echo $zoneId ? '\''. $zoneId .'\'' : 'null'; ?>;
            let currentZoneName = <?php echo $domain ? '\''. $domain .'\'' : 'null'; ?>;
            
            // Initialize page
            document.addEventListener('DOMContentLoaded', function() {
                if (currentZoneId) {
                    loadDNSRecords(currentZoneId, currentZoneName);
                } else {
                    loadZonesList();
                }
                
                // Set initial filter if provided
                <?php if ($recordType): ?>
                document.getElementById('recordTypeFilter').value = '<?php echo $recordType; ?>';
                <?php endif; ?>
            });
            
            // Load zones list
            async function loadZonesList() {
                const loadingSpinner = document.getElementById('zonesLoading');
                const zonesGrid = document.getElementById('zonesGrid');
                
                loadingSpinner.style.display = 'block';
                zonesGrid.innerHTML = '';
                
                try {
                    const response = await fetch('index.php?action=manage-dns&action=list_zones&per_page=100');
                    const data = await response.json();
                    
                    if (data.success) {
                        allZones = data.zones || [];
                        displayZones(allZones);
                    } else {
                        zonesGrid.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Lỗi: ${data.error || 'Không thể tải danh sách zones'}
                            </div>
                        `;
                    }
                } catch (error) {
                    zonesGrid.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Lỗi kết nối: ${error.message}
                        </div>
                    `;
                } finally {
                    loadingSpinner.style.display = 'none';
                }
            }
            
            // Display zones in grid
            function displayZones(zones) {
                const zonesGrid = document.getElementById('zonesGrid');
                
                if (zones.length === 0) {
                    zonesGrid.innerHTML = `
                        <div class="alert alert-warning text-center">
                            <i class="fas fa-info-circle me-2"></i>
                            Không tìm thấy zone nào.
                        </div>
                    `;
                    return;
                }
                
                zonesGrid.innerHTML = zones.map(zone => {
                    const zoneData = zone.zone_info || zone;
                    const recordsCount = zone.dns_records_summary?.total_records || 0;
                    
                    const statusClass = {
                        'active': 'text-success',
                        'pending': 'text-warning', 
                        'moved': 'text-danger'
                    }[zoneData.status] || 'text-muted';
                    
                    return `
                        <a href="#" class="zone-card" onclick="selectZone('${zoneData.id}', '${zoneData.name}')">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="mb-1"><i class="fas fa-globe text-primary me-2"></i>${zoneData.name}</h5>
                                <span class="badge bg-light text-dark">${recordsCount} records</span>
                            </div>
                            <p class="text-muted mb-2">
                                <small>
                                    <i class="fas fa-calendar me-1"></i>Tạo: ${new Date(zoneData.created_on).toLocaleDateString('vi-VN')}<br>
                                    <i class="fas fa-info-circle me-1"></i>Trạng thái: <span class="${statusClass} fw-bold">${zoneData.status}</span>
                                </small>
                            </p>
                            <div class="mt-2">
                                <small class="text-muted">ID: ${zoneData.id}</small>
                            </div>
                        </a>
                    `;
                }).join('');
            }
            
            // Filter zones function
            function filterZones() {
                const searchTerm = document.getElementById('zoneSearchInput').value.toLowerCase();
                const statusFilter = document.getElementById('zoneStatusFilter').value;
                
                const filteredZones = allZones.filter(zone => {
                    const zoneData = zone.zone_info || zone;
                    const matchesSearch = zoneData.name.toLowerCase().includes(searchTerm) || 
                                        zoneData.id.toLowerCase().includes(searchTerm);
                    const matchesStatus = !statusFilter || zoneData.status === statusFilter;
                    
                    return matchesSearch && matchesStatus;
                });
                
                displayZones(filteredZones);
            }
            
            // Select zone and load DNS records
            function selectZone(zoneId, zoneName) {
                currentZoneId = zoneId;
                currentZoneName = zoneName;
                
                document.getElementById('selectedZoneName').textContent = `(${zoneName})`;
                document.getElementById('zoneSelectionSection').style.display = 'none';
                document.getElementById('dnsRecordsSection').style.display = 'block';
                
                loadDNSRecords(zoneId, zoneName);
            }
            
            // Load DNS records for selected zone
            async function loadDNSRecords(zoneId, zoneName) {
                const loadingSpinner = document.getElementById('recordsLoading');
                const recordsContainer = document.getElementById('dnsRecordsContainer');
                const statisticsContainer = document.getElementById('dnsStatistics');
                
                loadingSpinner.style.display = 'block';
                recordsContainer.innerHTML = '';
                statisticsContainer.innerHTML = '';
                
                try {
                    const response = await fetch(`index.php?action=manage-dns&action=list_dns_records&zone_id=${zoneId}&per_page=500`);
                    const data = await response.json();
                    
                    if (data.success) {
                        allDNSRecords = data.records || [];
                        displayDNSStatistics(allDNSRecords);
                        displayDNSRecords(allDNSRecords);
                    } else {
                        recordsContainer.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                Lỗi: ${data.error || 'Không thể tải DNS records'}
                            </div>
                        `;
                    }
                } catch (error) {
                    recordsContainer.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Lỗi kết nối: ${error.message}
                        </div>
                    `;
                } finally {
                    loadingSpinner.style.display = 'none';
                }
            }
            
            // Display DNS statistics
            function displayDNSStatistics(records) {
                const statistics = {
                    total: records.length,
                    proxied: records.filter(r => r.proxied).length,
                    types: {}
                };
                
                records.forEach(record => {
                    statistics.types[record.type] = (statistics.types[record.type] || 0) + 1;
                });
                
                const typesHtml = Object.entries(statistics.types)
                    .sort((a, b) => b[1] - a[1])
                    .slice(0, 6)
                    .map(([type, count]) => `
                        <div class="col-md-2">
                            <h5 class="text-primary mb-1">${count}</h5>
                            <small class="text-muted">${type} Records</small>
                        </div>
                    `).join('');
                    
                const container = document.getElementById('dnsStatistics');
                container.innerHTML = `
                    <div class="col-md-2">
                        <h4 class="text-success mb-1">${statistics.total}</h4>
                        <small class="text-muted">Tổng Records</small>
                    </div>
                    <div class="col-md-2">
                        <h4 class="text-warning mb-1">${statistics.proxied}</h4>
                        <small class="text-muted">Proxied</small>
                    </div>
                    ${typesHtml}
                `;
            }
            
            // Display DNS records
            function displayDNSRecords(records) {
                const container = document.getElementById('dnsRecordsContainer');
                
                if (records.length === 0) {
                    container.innerHTML = `
                        <div class="alert alert-info text-center m-3">
                            <i class="fas fa-info-circle me-2"></i>
                            Không tìm thấy DNS records nào.
                        </div>
                    `;
                    return;
                }
                
                container.innerHTML = records.map(record => {
                    const content = record.content || record.target || record.value || '-';
                    const truncatedContent = content.length > 60 ? content.substring(0, 60) + '...' : content;
                    const proxiedBadge = record.proxied ? '<span class="proxied-badge">Proxied</span>' : '';
                    
                    let additionalInfo = '';
                    if (record.type === 'MX') {
                        additionalInfo = ` <small class="text-muted">(Priority: ${record.priority || 0})</small>`;
                    } else if (record.type === 'SRV') {
                        additionalInfo = ` <small class="text-muted">(Pri: ${record.priority}, Weight: ${record.weight}, Port: ${record.port})</small>`;
                    }
                    
                    const ttlDisplay = record.ttl === 1 ? 'Auto' : (record.ttl || 'Default');
                    const createdDate = record.created_on ? new Date(record.created_on).toLocaleDateString('vi-VN') : '-';
                    
                    return `
                        <div class="record-row">
                            <div class="row align-items-center">
                                <div class="col-md-1">
                                    <span class="record-type-badge badge-${record.type}">${record.type}</span>
                                </div>
                                <div class="col-md-3">
                                    <strong>${record.name || '-'}</strong>
                                    ${proxiedBadge}
                                </div>
                                <div class="col-md-4">
                                    <span class="record-content">${truncatedContent}</span>
                                    ${additionalInfo}
                                </div>
                                <div class="col-md-1 text-center">
                                    <small class="text-muted">${ttlDisplay}</small>
                                </div>
                                <div class="col-md-2 text-center">
                                    <small class="text-muted">${createdDate}</small>
                                </div>
                                <div class="col-md-1 text-end">
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="showRecordDetails('${record.id}')" title="Chi tiết">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            }
            
            // Filter DNS records
            function filterDNSRecords() {
                const typeFilter = document.getElementById('recordTypeFilter').value;
                const searchTerm = document.getElementById('recordSearchInput').value.toLowerCase();
                const proxyFilter = document.getElementById('proxyFilter').value;
                
                const filteredRecords = allDNSRecords.filter(record => {
                    const matchesType = !typeFilter || record.type === typeFilter;
                    const matchesSearch = !searchTerm || 
                                        record.name.toLowerCase().includes(searchTerm) ||
                                        (record.content || '').toLowerCase().includes(searchTerm);
                    const matchesProxy = !proxyFilter || 
                                       (proxyFilter === 'true' && record.proxied) ||
                                       (proxyFilter === 'false' && !record.proxied);
                    
                    return matchesType && matchesSearch && matchesProxy;
                });
                
                displayDNSRecords(filteredRecords);
                displayDNSStatistics(filteredRecords);
            }
            
            // Show zone selection
            function showZoneSelection() {
                document.getElementById('dnsRecordsSection').style.display = 'none';
                document.getElementById('zoneSelectionSection').style.display = 'block';
                currentZoneId = null;
                currentZoneName = null;
            }
            
            // Refresh DNS records
            function refreshDNSRecords() {
                if (currentZoneId) {
                    loadDNSRecords(currentZoneId, currentZoneName);
                }
            }
            
            // Export DNS records to CSV
            function exportDNSRecords() {
                if (!allDNSRecords || allDNSRecords.length === 0) {
                    alert('Không có dữ liệu để xuất');
                    return;
                }
                
                const headers = ['Type', 'Name', 'Content', 'TTL', 'Proxied', 'Priority', 'Created'];
                let csvContent = headers.join(',') + '\n';
                
                allDNSRecords.forEach(record => {
                    const row = [
                        record.type || '',
                        `"${(record.name || '').replace(/"/g, '""')}"`,
                        `"${(record.content || record.target || '').replace(/"/g, '""')}"`,
                        record.ttl || '',
                        record.proxied ? 'True' : 'False',
                        record.priority || '',
                        record.created_on || ''
                    ];
                    csvContent += row.join(',') + '\n';
                });
                
                const filename = `dns-records-${currentZoneName}-${new Date().toISOString().substr(0, 10)}.csv`;
                const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
                const link = document.createElement('a');
                
                if (link.download !== undefined) {
                    const url = URL.createObjectURL(blob);
                    link.setAttribute('href', url);
                    link.setAttribute('download', filename);
                    link.style.visibility = 'hidden';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                }
            }
            
            // Show record details (placeholder for future implementation)
            function showRecordDetails(recordId) {
                const record = allDNSRecords.find(r => r.id === recordId);
                if (record) {
                    alert('Record Details:\n' + JSON.stringify(record, null, 2));
                }
            }
        </script>
    </body>
    </html>
    <?php
}

function handleDNSManagement() {
    $action = $_REQUEST['action'] ?? $_GET['action'] ?? 'dashboard';
    
    // Check API credentials first for API actions
    $apiActions = ['manage-dns', 'list-zones', 'cloudflare-dns', 'debug-cloudflare'];
    if (in_array($action, $apiActions) || strpos($action, 'dns') !== false) {
        $validation = validateCloudflareCredentials();
        
        if (!$validation['valid']) {
            if (strpos($action, 'manage-dns') !== false || in_array($action, ['list_zones', 'cloudflare-dns'])) {
                header('Content-Type: application/json');
                echo json_encode([
                    'success' => false,
                    'error' => $validation['error'],
                    'error_code' => $validation['code'],
                    'solution' => $validation['solution'] ?? 'Please check your Cloudflare API configuration',
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
                return;
            } else {
                // For dashboard view, show error in UI
                showDNSManagementError($validation);
                return;
            }
        }
    }
    
    // Set proper headers for API endpoints
    if (strpos($action, 'manage-dns') !== false || in_array($action, ['list_zones', 'list_dns_records', 'create_dns_record', 'update_dns_record', 'delete_dns_record', 'bulk_dns_operations', 'validate_dns_config', 'export_dns_config', 'import_dns_config'])) {
        header('Content-Type: application/json');
    }
    
    try {
        switch ($action) {
            case 'dashboard':
            case 'dns-dashboard':
                showDNSManagementDashboard();
                break;
                
            case 'manage-dns':
                manageCloudflareDNS();
                break;
                
            case 'list-domains':
            case 'list-zones':
                listCloudflaredomains();
                break;
                
            case 'cloudflare-dns':
            case 'get-dns':
                getCloudflaresDNSRecords();
                break;
                
            case 'debug-cloudflare':
            case 'debug-dns':
                debugCloudflareDNS();
                break;
                
            case 'test-api': 
                testCloudflareAPI();
                break;
                
            case 'multi-domain-batch':
            case 'batch-api':
            case 'batch-operations':
                showMultiDomainBatchUI();
                break;
                
            case 'show-dns-records':
            case 'dns-records':
            case 'view-dns':
                showDNSRecordsPage();
                break;
                
            default:
                // Default to showing the dashboard
                showDNSManagementDashboard();
                break;
        }
    } catch (Exception $e) {
        if (headers_sent() || strpos($action, 'dashboard') !== false) {
            // Show error page for dashboard
            showDNSManagementError([
                'error' => $e->getMessage(),
                'code' => 'SYSTEM_ERROR',
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);
        } else {
            // Return JSON error for API calls
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
                'error_code' => 'SYSTEM_ERROR',
                'timestamp' => date('Y-m-d H:i:s')
            ]);
        }
    }
}

/**
 * Test Cloudflare API connection and permissions
 */
function testCloudflareAPI() {
    header('Content-Type: application/json');
    
    $validation = validateCloudflareCredentials();
    
    $response = [
        'success' => $validation['valid'],
        'validation' => $validation,
        'timestamp' => date('Y-m-d H:i:s')
    ];
    
    if ($validation['valid']) {
        try {
            require_once __DIR__ . '/CloudflareAPI.php';
            $cloudflare = new CloudflareAPI();
            
            // Additional tests
            $tests = [];
            
            // Test 1: List zones
            try {
                $zones = $cloudflare->listZones(1, 5);
                $tests['list_zones'] = [
                    'success' => !empty($zones['result']),
                    'count' => count($zones['result'] ?? []),
                    'error' => $zones['errors'][0]['message'] ?? null
                ];
            } catch (Exception $e) {
                $tests['list_zones'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
            
            // Test 2: Get user details
            try {
                $user = $cloudflare->getUserDetails();
                $tests['user_details'] = [
                    'success' => isset($user['result']),
                    'email' => $user['result']['email'] ?? null,
                    'error' => $user['errors'][0]['message'] ?? null
                ];
            } catch (Exception $e) {
                $tests['user_details'] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
            
            $response['tests'] = $tests;
            
        } catch (Exception $e) {
            $response['api_test_error'] = $e->getMessage();
        }
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT);
}

/**
 * Show DNS Management error page
 */
function showDNSManagementError($error) {
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>DNS Management - Configuration Error</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-8">
                    <div class="card border-danger">
                        <div class="card-header bg-danger text-white">
                            <h4 class="mb-0">
                                <i class="fas fa-exclamation-triangle"></i> 
                                DNS Management Configuration Error
                            </h4>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-danger">
                                <h5>Error: <?= htmlspecialchars($error['error']) ?></h5>
                                <?php if (isset($error['code'])): ?>
                                    <p><strong>Error Code:</strong> <?= htmlspecialchars($error['code']) ?></p>
                                <?php endif; ?>
                            </div>
                            
                            <?php if (isset($error['solution'])): ?>
                                <div class="alert alert-info">
                                    <h6><i class="fas fa-lightbulb"></i> Solution:</h6>
                                    <p><?= htmlspecialchars($error['solution']) ?></p>
                                </div>
                            <?php endif; ?>
                            
                            <h6>Common Solutions:</h6>
                            <ul>
                                <li><strong>Code 9109 (Unauthorized):</strong> Your API token doesn't have the required permissions. Ensure it has Zone:Read and DNS:Edit permissions.</li>
                                <li><strong>Code 9106 (Invalid Token):</strong> Check that your API token is correctly set in the configuration file.</li>
                                <li><strong>Missing CloudflareAPI.php:</strong> Ensure the CloudflareAPI.php file exists and is properly configured.</li>
                            </ul>
                            
                            <h6>Configuration Steps:</h6>
                            <ol>
                                <li>Go to <a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank">Cloudflare API Tokens</a></li>
                                <li>Create a new token with the following permissions:
                                    <ul>
                                        <li>Zone:Read</li>
                                        <li>DNS:Edit</li> 
                                        <li>Zone Settings:Read</li>
                                    </ul>
                                </li>
                                <li>Update your CloudflareAPI.php configuration with the new token</li>
                                <li>Test the connection using the button below</li>
                            </ol>
                            
                            <div class="mt-4">
                                <button class="btn btn-primary" onclick="testConnection()">
                                    <i class="fas fa-flask"></i> Test API Connection
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                            
                            <div id="test-results" class="mt-3"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
        async function testConnection() {
            const button = event.target;
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
            button.disabled = true;
            
            try {
                const response = await fetch('index.php?action=test-api');
                const data = await response.json();
                
                const resultsDiv = document.getElementById('test-results');
                
                if (data.success) {
                    resultsDiv.innerHTML = `
                        <div class="alert alert-success">
                            <h6><i class="fas fa-check"></i> API Connection Successful!</h6>
                            <p>User: ${data.validation.user_email || 'Unknown'}</p>
                            <p>Permissions: ${JSON.stringify(data.validation.permissions)}</p>
                        </div>
                    `;
                    
                    setTimeout(() => {
                        window.location.href = 'index.php?action=dns-dashboard';
                    }, 2000);
                } else {
                    resultsDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <h6><i class="fas fa-times"></i> API Connection Failed</h6>
                            <p>${data.validation.error}</p>
                            ${data.validation.solution ? '<p><strong>Solution:</strong> ' + data.validation.solution + '</p>' : ''}
                        </div>
                    `;
                }
            } catch (error) {
                document.getElementById('test-results').innerHTML = `
                    <div class="alert alert-danger">
                        <h6><i class="fas fa-times"></i> Connection Test Failed</h6>
                        <p>Network error: ${error.message}</p>
                    </div>
                `;
            }
            
            button.innerHTML = originalText;
            button.disabled = false;
        }
        </script>
    </body>
    </html>
    <?php
}

/**
 * Integration helper functions
 */

/**
 * Check if user has permission for DNS management
 */
function checkDNSManagementPermission() {
    // Add your permission logic here
    // For now, return true - implement your authentication system
    return true;
}

/**
 * Validate Cloudflare API credentials and permissions
 */
function validateCloudflareCredentials() {
    try {
        // Check if CloudflareAPI class exists
        if (!file_exists(__DIR__ . '/CloudflareAPI.php')) {
            return [
                'valid' => false,
                'error' => 'CloudflareAPI.php file not found',
                'code' => 'FILE_NOT_FOUND'
            ];
        }
        
        require_once __DIR__ . '/CloudflareAPI.php';
        
        // Check if CloudflareAPI class can be instantiated
        if (!class_exists('CloudflareAPI')) {
            return [
                'valid' => false,
                'error' => 'CloudflareAPI class not found',
                'code' => 'CLASS_NOT_FOUND'
            ];
        }
        
        $cloudflare = new CloudflareAPI();
        
        // Test API connection with user verification
        $userInfo = $cloudflare->getUserDetails();
        
        if (!$userInfo) {
            return [
                'valid' => false,
                'error' => 'Failed to connect to Cloudflare API - No response',
                'code' => 'NO_RESPONSE'
            ];
        }
        
        // Check for specific error codes
        if (isset($userInfo['errors']) && !empty($userInfo['errors'])) {
            $error = $userInfo['errors'][0];
            
            switch ($error['code']) {
                case 9109:
                    return [
                        'valid' => false,
                        'error' => 'API Token unauthorized. Please check token permissions.',
                        'code' => 'UNAUTHORIZED',
                        'solution' => 'Ensure your API token has Zone:Read permissions at minimum.'
                    ];
                case 9106:
                    return [
                        'valid' => false,
                        'error' => 'API Token missing or invalid.',
                        'code' => 'INVALID_TOKEN',
                        'solution' => 'Please set a valid Cloudflare API token in your configuration.'
                    ];
                case 9103:
                    return [
                        'valid' => false,
                        'error' => 'API Token has insufficient permissions.',
                        'code' => 'INSUFFICIENT_PERMISSIONS',
                        'solution' => 'API token needs Zone:Read and Zone:Edit permissions for full functionality.'
                    ];
                default:
                    return [
                        'valid' => false,
                        'error' => $error['message'] ?? 'Unknown API error',
                        'code' => $error['code'] ?? 'UNKNOWN_ERROR',
                        'solution' => 'Please check your Cloudflare API configuration.'
                    ];
            }
        }
        
        // If we got user info successfully, API is working
        if (isset($userInfo['result'])) {
            return [
                'valid' => true,
                'user_email' => $userInfo['result']['email'] ?? 'Unknown',
                'user_id' => $userInfo['result']['id'] ?? null,
                'permissions' => getTokenPermissions($cloudflare)
            ];
        }
        
        return [
            'valid' => false,
            'error' => 'Unexpected API response format',
            'code' => 'UNEXPECTED_RESPONSE'
        ];
        
    } catch (Exception $e) {
        return [
            'valid' => false,
            'error' => $e->getMessage(),
            'code' => 'EXCEPTION',
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }
}

/**
 * Test token permissions by attempting various operations
 */
function getTokenPermissions($cloudflare) {
    $permissions = [
        'zone_read' => false,
        'zone_edit' => false,
        'dns_read' => false,
        'dns_edit' => false
    ];
    
    try {
        // Test zone read permission
        $zones = $cloudflare->listZones(1, 1);
        if ($zones && isset($zones['result'])) {
            $permissions['zone_read'] = true;
            
            // If we can read zones, test DNS read on first zone
            if (!empty($zones['result'])) {
                $firstZoneId = $zones['result'][0]['id'];
                $dnsRecords = $cloudflare->listDNSRecords($firstZoneId, null, null, null, null, 1, 1);
                if ($dnsRecords && isset($dnsRecords['result'])) {
                    $permissions['dns_read'] = true;
                }
            }
        }
    } catch (Exception $e) {
        // Permission testing failed, but don't throw error
    }
    
    return $permissions;
}

/**
 * Get Cloudflare API status with enhanced error handling
 */
function getCloudflareAPIStatus() {
    $validation = validateCloudflareCredentials();
    
    if (!$validation['valid']) {
        return [
            'connected' => false,
            'error' => $validation['error'],
            'code' => $validation['code'],
            'solution' => $validation['solution'] ?? null,
            'account_id' => null
        ];
    }
    
    try {
        require_once __DIR__ . '/CloudflareAPI.php';
        $cloudflare = new CloudflareAPI();
        
        // Get account information
        $zones = $cloudflare->listZones(1, 1);
        $accountId = null;
        
        if ($zones && isset($zones['result']) && !empty($zones['result'])) {
            $accountId = $zones['result'][0]['account']['id'] ?? null;
        }
        
        return [
            'connected' => true,
            'error' => null,
            'code' => null,
            'account_id' => $accountId,
            'user_email' => $validation['user_email'] ?? null,
            'permissions' => $validation['permissions'] ?? []
        ];
        
    } catch (Exception $e) {
        return [
            'connected' => false,
            'error' => $e->getMessage(),
            'code' => 'CONNECTION_ERROR',
            'account_id' => null
        ];
    }
}

/**
 * Generate DNS Management navigation links
 */
function getDNSManagementNavigation() {
    return [
        [
            'title' => 'DNS Dashboard',
            'url' => 'index.php?action=dns-dashboard',
            'icon' => 'fas fa-tachometer-alt',
            'description' => 'Overview of all DNS zones and records'
        ],
        [
            'title' => 'Manage Zones',
            'url' => 'index.php?action=dns-dashboard#zones',
            'icon' => 'fas fa-globe',
            'description' => 'View and manage Cloudflare zones'
        ],
        [
            'title' => 'DNS Records',
            'url' => 'index.php?action=dns-dashboard#dns-records',
            'icon' => 'fas fa-list',
            'description' => 'Create, edit, and delete DNS records'
        ],
        [
            'title' => 'Bulk Operations',
            'url' => 'index.php?action=dns-dashboard#bulk-operations',
            'icon' => 'fas fa-tasks',
            'description' => 'Perform bulk actions on multiple records'
        ],
        [
            'title' => 'DNS Validation',
            'url' => 'index.php?action=dns-dashboard#validation',
            'icon' => 'fas fa-check-circle',
            'description' => 'Validate DNS configuration and security'
        ],
        [
            'title' => 'DNS Records Viewer',
            'url' => 'index.php?action=show-dns-records',
            'icon' => 'fas fa-list-ul',
            'description' => 'Hiển thị và xem tất cả DNS records'
        ],
        [
            'title' => 'Import/Export',
            'url' => 'index.php?action=dns-dashboard#import-export',
            'icon' => 'fas fa-exchange-alt',
            'description' => 'Import and export DNS configurations'
        ]
    ];
}

/**
 * Create DNS management navigation menu HTML
 */
function renderDNSManagementMenu($currentPage = null) {
    $navigationItems = getDNSManagementNavigation();
    $html = '<div class="dns-management-menu mb-4">';
    $html .= '<h5><i class="fab fa-cloudflare text-warning"></i> DNS Management</h5>';
    $html .= '<div class="list-group list-group-flush">';
    
    foreach ($navigationItems as $item) {
        $isActive = $currentPage === $item['title'] ? 'active' : '';
        $html .= sprintf(
            '<a href="%s" class="list-group-item list-group-item-action %s">' .
            '<i class="%s me-2"></i>' .
            '<strong>%s</strong>' .
            '<small class="d-block text-muted">%s</small>' .
            '</a>',
            $item['url'],
            $isActive,
            $item['icon'],
            $item['title'],
            $item['description']
        );
    }
    
    $html .= '</div></div>';
    return $html;
}

/**
 * Quick stats widget for dashboard integration
 */
function getDNSManagementQuickStats() {
    try {
        require_once __DIR__ . '/CloudflareAPI.php';
        $cloudflare = new CloudflareAPI();
        
        // Get zones
        $zones = $cloudflare->listZones(1, 100);
        
        if (!$zones || !isset($zones['result'])) {
            return [
                'error' => 'Unable to connect to Cloudflare API',
                'zones' => 0,
                'records' => 0,
                'proxied' => 0
            ];
        }
        
        $totalRecords = 0;
        $proxiedRecords = 0;
        $totalZones = count($zones['result']);
        
        // Count records for each zone (limit to first 10 zones for performance)
        $zonesToCheck = array_slice($zones['result'], 0, 10);
        
        foreach ($zonesToCheck as $zone) {
            try {
                $records = $cloudflare->listDNSRecords($zone['id']);
                if ($records && isset($records['result'])) {
                    $zoneRecords = $records['result'];
                    $totalRecords += count($zoneRecords);
                    
                    foreach ($zoneRecords as $record) {
                        if ($record['proxied'] ?? false) {
                            $proxiedRecords++;
                        }
                    }
                }
            } catch (Exception $e) {
                // Skip this zone if there's an error
                continue;
            }
        }
        
        return [
            'zones' => $totalZones,
            'records' => $totalRecords,
            'proxied' => $proxiedRecords,
            'dns_only' => $totalRecords - $proxiedRecords,
            'last_updated' => date('Y-m-d H:i:s')
        ];
        
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage(),
            'zones' => 0,
            'records' => 0,
            'proxied' => 0
        ];
    }
}

/**
 * Widget for main dashboard integration
 */
function renderDNSManagementWidget() {
    $stats = getDNSManagementQuickStats();
    $apiStatus = getCloudflareAPIStatus();
    
    ob_start();
    ?>
    <div class="card dns-management-widget">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h6 class="mb-0">
                <i class="fab fa-cloudflare text-warning"></i> 
                DNS Management
            </h6>
            <div>
                <?php if ($apiStatus['connected']): ?>
                    <span class="badge bg-success"><i class="fas fa-check"></i> Connected</span>
                <?php else: ?>
                    <span class="badge bg-danger"><i class="fas fa-times"></i> Disconnected</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-body">
            <?php if (isset($stats['error'])): ?>
                <div class="alert alert-warning mb-3">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <?= htmlspecialchars($stats['error']) ?>
                </div>
            <?php else: ?>
                <div class="row text-center mb-3">
                    <div class="col-4">
                        <div class="stat-item">
                            <h5 class="text-primary mb-0"><?= $stats['zones'] ?></h5>
                            <small class="text-muted">Zones</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-item">
                            <h5 class="text-success mb-0"><?= $stats['records'] ?></h5>
                            <small class="text-muted">Records</small>
                        </div>
                    </div>
                    <div class="col-4">
                        <div class="stat-item">
                            <h5 class="text-warning mb-0"><?= $stats['proxied'] ?></h5>
                            <small class="text-muted">Proxied</small>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="d-grid gap-2">
                <a href="index.php?action=dns-dashboard" class="btn btn-primary btn-sm">
                    <i class="fas fa-tachometer-alt"></i> Open DNS Dashboard
                </a>
                <div class="btn-group" role="group">
                    <a href="index.php?action=dns-dashboard#dns-records" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-list"></i> Records
                    </a>
                    <a href="index.php?action=dns-dashboard#validation" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-check"></i> Validate
                    </a>
                    <a href="index.php?action=dns-dashboard#import-export" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-exchange-alt"></i> Export
                    </a>
                </div>
            </div>
            
            <?php if (isset($stats['last_updated'])): ?>
                <small class="text-muted">Last updated: <?= $stats['last_updated'] ?></small>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
    .dns-management-widget .stat-item {
        padding: 0.5rem 0;
    }
    .dns-management-widget .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
    }
    .dns-management-widget .badge {
        font-size: 0.7rem;
    }
    </style>
    <?php
    return ob_get_clean();
}

/**
 * Add to existing navigation structure
 */
function addDNSManagementToNavigation() {
    return [
        'title' => 'DNS Management',
        'icon' => 'fas fa-globe',
        'url' => 'index.php?action=dns-dashboard',
        'submenu' => [
            [
                'title' => 'Dashboard',
                'url' => 'index.php?action=dns-dashboard',
                'icon' => 'fas fa-tachometer-alt'
            ],
            [
                'title' => 'DNS Records',
                'url' => 'index.php?action=dns-dashboard#dns-records', 
                'icon' => 'fas fa-list'
            ],
            [
                'title' => 'Validation',
                'url' => 'index.php?action=dns-dashboard#validation',
                'icon' => 'fas fa-check-circle'
            ],
            [
                'title' => 'Import/Export',
                'url' => 'index.php?action=dns-dashboard#import-export',
                'icon' => 'fas fa-exchange-alt'
            ],
            [
                'title' => 'Batch Operations',
                'url' => 'index.php?action=multi-domain-batch',
                'icon' => 'fas fa-layer-group'
            ]
        ]
    ];
}

/**
 * Show multi-domain batch operations UI
 */
function showMultiDomainBatchUI() {
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Multi-Domain Batch Operations</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
        <style>
            .domain-card {
                transition: all 0.3s;
                cursor: pointer;
            }
            .domain-card:hover {
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            }
            .domain-card.selected {
                border-color: #0d6efd;
                background-color: #e7f3ff;
                border-width: 2px;
            }
            .progress-container {
                display: none;
            }
            .batch-results {
                max-height: 400px;
                overflow-y: auto;
            }
        </style>
    </head>
    <body class="bg-light">
        <div class="container-fluid py-4">
            <!-- Header -->
            <div class="row">
                <div class="col-12">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2>
                            <i class="fas fa-layer-group text-primary"></i> 
                            Multi-Domain API Operations
                        </h2>
                        <div>
                            <button class="btn btn-outline-secondary" onclick="refreshDomains()">
                                <i class="fas fa-sync-alt"></i> Refresh
                            </button>
                            <a href="index.php?action=dns-dashboard" class="btn btn-primary">
                                <i class="fas fa-tachometer-alt"></i> Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="row">
                <!-- Domain Selection Panel -->
                <div class="col-md-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">
                                <i class="fas fa-globe"></i> Select Domains for API Operations
                            </h5>
                            <div>
                                <button class="btn btn-sm btn-outline-primary" onclick="selectAllDomains()">
                                    <i class="fas fa-check-square"></i> Select All
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="clearSelection()">
                                    <i class="fas fa-square"></i> Clear All
                                </button>
                            </div>
                        </div>
                        <div class="card-body">
                            <!-- Search -->
                            <div class="mb-3">
                                <input type="text" class="form-control" id="domain-search" placeholder="Search domains..." oninput="filterDomains()">
                            </div>

                            <!-- API URL Template -->
                            <div class="alert alert-info">
                                <h6><i class="fas fa-link"></i> API Endpoint Template:</h6>
                                <code id="api-template">index.php?action=zones&api=1&zone_id={ZONE_ID}&include_dns=true</code>
                            </div>

                            <!-- Domains Grid -->
                            <div id="domains-grid" class="row">
                                <div class="col-12 text-center py-4">
                                    <i class="fas fa-spinner fa-spin fa-2x text-primary"></i>
                                    <p class="mt-2 text-muted">Loading domains...</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Operations Panel -->
                <div class="col-md-4">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h6 class="mb-0">
                                <i class="fas fa-cogs"></i> Batch API Operations
                            </h6>
                        </div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-label">API Parameters</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include-dns" checked>
                                    <label class="form-check-label" for="include-dns">
                                        include_dns=true
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include-settings">
                                    <label class="form-check-label" for="include-settings">
                                        include_settings=true
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="include-analytics">
                                    <label class="form-check-label" for="include-analytics">
                                        include_analytics=true
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Concurrent Requests</label>
                                <select class="form-select" id="concurrent-limit">
                                    <option value="1">1 (Safe)</option>
                                    <option value="3" selected>3 (Recommended)</option>
                                    <option value="5">5 (Fast)</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Request Delay (ms)</label>
                                <input type="number" class="form-control" id="request-delay" value="500" min="0" max="5000">
                                <small class="text-muted">Delay between batches to avoid rate limits</small>
                            </div>

                            <div class="d-grid">
                                <button class="btn btn-primary" id="start-batch-btn" onclick="startBatchAPI()" disabled>
                                    <i class="fas fa-play"></i> Start Batch API Calls
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Selection Summary -->
                    <div class="card text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                        <div class="card-body text-center">
                            <h3 class="mb-1" id="selected-count">0</h3>
                            <small>Domains Selected</small>
                            <hr class="border-light">
                            <div class="row text-center">
                                <div class="col-6">
                                    <div class="h5 mb-0" id="total-domains">0</div>
                                    <small>Total Available</small>
                                </div>
                                <div class="col-6">
                                    <div class="h5 mb-0" id="active-domains">0</div>
                                    <small>Active</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Progress Section -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="progress-container" id="progress-container">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">
                                    <i class="fas fa-tasks"></i> API Call Progress
                                    <button class="btn btn-sm btn-danger float-end" id="stop-batch-btn" onclick="stopBatchAPI()" style="display: none;">
                                        <i class="fas fa-stop"></i> Stop
                                    </button>
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="progress mb-3">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated" id="progress-bar" role="progressbar" style="width: 0%"></div>
                                </div>
                                <div class="row text-center mb-3">
                                    <div class="col-3">
                                        <span class="h4 text-info" id="progress-current">0</span>
                                        <small class="d-block text-muted">Processed</small>
                                    </div>
                                    <div class="col-3">
                                        <span class="h4 text-success" id="progress-success">0</span>
                                        <small class="d-block text-muted">Success</small>
                                    </div>
                                    <div class="col-3">
                                        <span class="h4 text-danger" id="progress-errors">0</span>
                                        <small class="d-block text-muted">Errors</small>
                                    </div>
                                    <div class="col-3">
                                        <span class="h4 text-warning" id="progress-rate">0</span>
                                        <small class="d-block text-muted">Rate/min</small>
                                    </div>
                                </div>
                                <div id="current-processing" class="text-center text-muted"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Results Section -->
            <div class="row mt-4">
                <div class="col-12">
                    <div id="results-container" style="display: none;">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">
                                    <i class="fas fa-chart-line"></i> Batch API Results
                                </h5>
                                <div>
                                    <button class="btn btn-sm btn-success" onclick="exportResults()">
                                        <i class="fas fa-download"></i> Export JSON
                                    </button>
                                    <button class="btn btn-sm btn-info" onclick="exportCSV()">
                                        <i class="fas fa-file-csv"></i> Export CSV
                                    </button>
                                    <button class="btn btn-sm btn-secondary" onclick="clearResults()">
                                        <i class="fas fa-trash"></i> Clear
                                    </button>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="batch-results" class="batch-results"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            // Global variables
            let domains = [];
            let selectedDomains = new Set();
            let batchRunning = false;
            let batchResults = [];
            let startTime = null;
            
            // Initialize
            document.addEventListener('DOMContentLoaded', function() {
                loadDomains();
            });

            // Load domains from API
            async function loadDomains() {
                try {
                    const response = await fetch('index.php?action=manage-dns&action=list_zones&per_page=100');
                    const data = await response.json();
                    
                    if (data.success) {
                        domains = data.zones || [];
                        renderDomains();
                        updateStats();
                    } else {
                        showError('Error loading domains: ' + (data.error || 'Unknown error'));
                    }
                } catch (error) {
                    showError('Network error: ' + error.message);
                }
            }

            // Render domains grid
            function renderDomains() {
                const grid = document.getElementById('domains-grid');
                if (domains.length === 0) {
                    grid.innerHTML = '<div class="col-12 text-center py-4"><p class="text-muted">No domains found</p></div>';
                    return;
                }
                
                let html = '';
                domains.forEach(domain => {
                    const zoneInfo = domain.zone_info || domain;
                    const isSelected = selectedDomains.has(zoneInfo.id);
                    
                    html += `
                        <div class="col-md-6 col-lg-4 mb-3 domain-item" data-domain-id="${zoneInfo.id}" data-name="${zoneInfo.name}">
                            <div class="domain-card card h-100 ${isSelected ? 'selected' : ''}" onclick="toggleDomain('${zoneInfo.id}')">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" ${isSelected ? 'checked' : ''} 
                                                   id="domain-${zoneInfo.id}" onclick="event.stopPropagation();">
                                        </div>
                                        <span class="badge bg-${getStatusColor(zoneInfo.status || 'active')}">${zoneInfo.status || 'active'}</span>
                                    </div>
                                    <h6 class="card-title text-truncate" title="${zoneInfo.name}">
                                        <i class="fas fa-globe text-primary"></i> ${zoneInfo.name}
                                    </h6>
                                    <div class="mt-auto">
                                        <small class="text-muted">Zone ID:</small><br>
                                        <code class="small">${zoneInfo.id}</code>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                });
                
                grid.innerHTML = html;
                
                // Add event listeners to checkboxes
                document.querySelectorAll('.form-check-input').forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        const domainId = this.id.replace('domain-', '');
                        toggleDomainSelection(domainId, this.checked);
                    });
                });
            }

            function getStatusColor(status) {
                switch(status.toLowerCase()) {
                    case 'active': return 'success';
                    case 'pending': return 'warning';
                    case 'initializing': return 'info';
                    default: return 'secondary';
                }
            }

            // Domain selection functions
            function toggleDomain(domainId) {
                const checkbox = document.getElementById(`domain-${domainId}`);
                checkbox.checked = !checkbox.checked;
                toggleDomainSelection(domainId, checkbox.checked);
            }

            function toggleDomainSelection(domainId, selected) {
                const card = document.querySelector(`[data-domain-id="${domainId}"] .domain-card`);
                
                if (selected) {
                    selectedDomains.add(domainId);
                    card.classList.add('selected');
                } else {
                    selectedDomains.delete(domainId);
                    card.classList.remove('selected');
                }
                
                updateSelectionCount();
                updateBatchButton();
                updateAPITemplate();
            }

            function selectAllDomains() {
                document.querySelectorAll('.domain-item').forEach(item => {
                    if (item.style.display !== 'none') {
                        const domainId = item.dataset.domainId;
                        const checkbox = document.getElementById(`domain-${domainId}`);
                        checkbox.checked = true;
                        selectedDomains.add(domainId);
                        item.querySelector('.domain-card').classList.add('selected');
                    }
                });
                updateSelectionCount();
                updateBatchButton();
            }

            function clearSelection() {
                selectedDomains.clear();
                document.querySelectorAll('.form-check-input').forEach(checkbox => {
                    checkbox.checked = false;
                });
                document.querySelectorAll('.domain-card').forEach(card => {
                    card.classList.remove('selected');
                });
                updateSelectionCount();
                updateBatchButton();
            }

            function updateSelectionCount() {
                document.getElementById('selected-count').textContent = selectedDomains.size;
            }

            function updateStats() {
                const totalDomains = domains.length;
                const activeDomains = domains.filter(d => (d.zone_info?.status || d.status) === 'active').length;
                
                document.getElementById('total-domains').textContent = totalDomains;
                document.getElementById('active-domains').textContent = activeDomains;
            }

            function updateBatchButton() {
                const button = document.getElementById('start-batch-btn');
                button.disabled = selectedDomains.size === 0 || batchRunning;
                
                if (selectedDomains.size > 0 && !batchRunning) {
                    button.innerHTML = `<i class="fas fa-play"></i> Start Batch API (${selectedDomains.size} domains)`;
                } else if (batchRunning) {
                    button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Running...';
                }
            }

            function updateAPITemplate() {
                const template = document.getElementById('api-template');
                let baseUrl = 'index.php?action=zones&api=1&zone_id={ZONE_ID}';
                
                if (document.getElementById('include-dns').checked) baseUrl += '&include_dns=true';
                if (document.getElementById('include-settings').checked) baseUrl += '&include_settings=true';
                if (document.getElementById('include-analytics').checked) baseUrl += '&include_analytics=true';
                
                template.textContent = baseUrl;
            }

            // Filter domains
            function filterDomains() {
                const searchTerm = document.getElementById('domain-search').value.toLowerCase();
                
                document.querySelectorAll('.domain-item').forEach(item => {
                    const domainName = item.dataset.name.toLowerCase();
                    const show = !searchTerm || domainName.includes(searchTerm);
                    item.style.display = show ? 'block' : 'none';
                });
            }

            // Batch API functions
            async function startBatchAPI() {
                if (selectedDomains.size === 0) {
                    showAlert('Please select at least one domain', 'warning');
                    return;
                }
                
                batchRunning = true;
                batchResults = [];
                startTime = Date.now();
                
                // Show progress
                document.getElementById('progress-container').style.display = 'block';
                document.getElementById('results-container').style.display = 'none';
                document.getElementById('start-batch-btn').disabled = true;
                document.getElementById('stop-batch-btn').style.display = 'inline-block';
                
                // Reset progress
                updateProgress(0, selectedDomains.size, 0, 0);
                
                const concurrentLimit = parseInt(document.getElementById('concurrent-limit').value);
                const delay = parseInt(document.getElementById('request-delay').value);
                
                await processBatchAPI(Array.from(selectedDomains), concurrentLimit, delay);
                
                // Complete
                batchRunning = false;
                document.getElementById('start-batch-btn').disabled = false;
                document.getElementById('stop-batch-btn').style.display = 'none';
                document.getElementById('current-processing').textContent = 'Batch completed!';
                
                updateBatchButton();
                displayResults();
            }

            async function processBatchAPI(domainIds, concurrentLimit, delay) {
                let processed = 0;
                let successCount = 0;
                let errorCount = 0;
                
                for (let i = 0; i < domainIds.length; i += concurrentLimit) {
                    if (!batchRunning) break;
                    
                    const batch = domainIds.slice(i, i + concurrentLimit);
                    const promises = batch.map(domainId => callSingleAPI(domainId));
                    
                    try {
                        const results = await Promise.allSettled(promises);
                        
                        results.forEach((result, index) => {
                            const domainId = batch[index];
                            processed++;
                            
                            if (result.status === 'fulfilled' && result.value.success) {
                                successCount++;
                                batchResults.push({
                                    domainId,
                                    domain: getDomainNameById(domainId),
                                    success: true,
                                    data: result.value.data,
                                    timestamp: new Date().toISOString()
                                });
                            } else {
                                errorCount++;
                                batchResults.push({
                                    domainId,
                                    domain: getDomainNameById(domainId),
                                    success: false,
                                    error: result.status === 'fulfilled' ? result.value.error : result.reason.message,
                                    timestamp: new Date().toISOString()
                                });
                            }
                        });
                        
                        // Calculate rate
                        const elapsed = (Date.now() - startTime) / 1000 / 60; // minutes
                        const rate = Math.round(processed / elapsed);
                        
                        updateProgress(processed, domainIds.length, successCount, errorCount, rate);
                        
                    } catch (error) {
                        console.error('Batch error:', error);
                    }
                    
                    // Delay between batches
                    if (i + concurrentLimit < domainIds.length && delay > 0) {
                        await new Promise(resolve => setTimeout(resolve, delay));
                    }
                }
            }

            async function callSingleAPI(domainId) {
                const domain = getDomainNameById(domainId);
                document.getElementById('current-processing').textContent = `Processing: ${domain}`;
                
                try {
                    // Build API URL matching the provided format
                    let apiUrl = `index.php?action=zones&api=1&zone_id=${domainId}`;
                    
                    if (document.getElementById('include-dns').checked) {
                        apiUrl += '&include_dns=true';
                    }
                    if (document.getElementById('include-settings').checked) {
                        apiUrl += '&include_settings=true';
                    }
                    if (document.getElementById('include-analytics').checked) {
                        apiUrl += '&include_analytics=true';
                    }
                    
                    const response = await fetch(apiUrl);
                    const data = await response.json();
                    
                    if (response.ok) {
                        return { success: true, data };
                    } else {
                        return { success: false, error: data.error || `HTTP ${response.status}` };
                    }
                } catch (error) {
                    return { success: false, error: error.message };
                }
            }

            function getDomainNameById(domainId) {
                const domain = domains.find(d => (d.zone_info?.id || d.id) === domainId);
                return domain ? (domain.zone_info?.name || domain.name) : domainId;
            }

            function updateProgress(current, total, success, errors, rate = 0) {
                const percentage = (current / total) * 100;
                
                document.getElementById('progress-bar').style.width = percentage + '%';
                document.getElementById('progress-current').textContent = current;
                document.getElementById('progress-success').textContent = success;
                document.getElementById('progress-errors').textContent = errors;
                document.getElementById('progress-rate').textContent = rate;
            }

            function stopBatchAPI() {
                batchRunning = false;
                document.getElementById('current-processing').textContent = 'Stopping...';
            }

            function displayResults() {
                document.getElementById('results-container').style.display = 'block';
                
                const container = document.getElementById('batch-results');
                const successCount = batchResults.filter(r => r.success).length;
                const errorCount = batchResults.filter(r => !r.success).length;
                
                let html = `
                    <div class="row text-center mb-4">
                        <div class="col-md-4">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h3>${successCount}</h3>
                                    <small>Successful API Calls</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-danger text-white">
                                <div class="card-body">
                                    <h3>${errorCount}</h3>
                                    <small>Failed API Calls</small>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h3>${batchResults.length}</h3>
                                    <small>Total Processed</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Domain</th>
                                    <th>Status</th>
                                    <th>API Response</th>
                                    <th>Timestamp</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                batchResults.forEach((result, index) => {
                    const statusBadge = result.success ? 
                        '<span class="badge bg-success"><i class="fas fa-check"></i> Success</span>' :
                        '<span class="badge bg-danger"><i class="fas fa-times"></i> Error</span>';
                    
                    const responsePreview = result.success ? 
                        JSON.stringify(result.data).substring(0, 100) + '...' :
                        result.error;
                    
                    html += `
                        <tr>
                            <td><strong>${result.domain}</strong><br><small class="text-muted">${result.domainId}</small></td>
                            <td>${statusBadge}</td>
                            <td class="text-truncate" style="max-width: 300px;" title="${responsePreview}">${responsePreview}</td>
                            <td><small>${new Date(result.timestamp).toLocaleString()}</small></td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="viewFullResponse(${index})" title="View Full Response">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    `;
                });
                
                html += '</tbody></table></div>';
                container.innerHTML = html;
            }

            function viewFullResponse(index) {
                const result = batchResults[index];
                const content = result.success ? 
                    JSON.stringify(result.data, null, 2) : 
                    'Error: ' + result.error;
                
                const modal = document.createElement('div');
                modal.className = 'modal fade';
                modal.innerHTML = `
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">${result.domain} - API Response</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body">
                                <pre>${content}</pre>
                            </div>
                        </div>
                    </div>
                `;
                document.body.appendChild(modal);
                new bootstrap.Modal(modal).show();
                
                modal.addEventListener('hidden.bs.modal', () => modal.remove());
            }

            function exportResults() {
                if (batchResults.length === 0) {
                    showAlert('No results to export', 'warning');
                    return;
                }
                
                const dataStr = JSON.stringify(batchResults, null, 2);
                downloadFile(dataStr, `batch_api_results_${new Date().toISOString().split('T')[0]}.json`, 'application/json');
                showAlert('Results exported as JSON!', 'success');
            }

            function exportCSV() {
                if (batchResults.length === 0) {
                    showAlert('No results to export', 'warning');
                    return;
                }
                
                let csv = 'Domain,Domain ID,Status,Error,Timestamp\\n';
                batchResults.forEach(result => {
                    csv += `"${result.domain}","${result.domainId}","${result.success ? 'Success' : 'Error'}","${result.error || ''}","${result.timestamp}"\\n`;
                });
                
                downloadFile(csv, `batch_api_results_${new Date().toISOString().split('T')[0]}.csv`, 'text/csv');
                showAlert('Results exported as CSV!', 'success');
            }

            function downloadFile(content, filename, mimeType) {
                const blob = new Blob([content], {type: mimeType});
                const link = document.createElement('a');
                link.href = URL.createObjectURL(blob);
                link.download = filename;
                link.click();
            }

            function clearResults() {
                if (confirm('Clear all results?')) {
                    batchResults = [];
                    document.getElementById('results-container').style.display = 'none';
                    document.getElementById('progress-container').style.display = 'none';
                }
            }

            function refreshDomains() {
                loadDomains();
            }

            function showAlert(message, type) {
                const alertDiv = document.createElement('div');
                alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
                alertDiv.innerHTML = `
                    ${message}
                    <button type=\"button\" class=\"btn-close\" data-bs-dismiss=\"alert\"></button>
                `;
                
                document.querySelector('.container-fluid').insertAdjacentElement('afterbegin', alertDiv);
                setTimeout(() => alertDiv.remove(), 5000);
            }

            function showError(message) {
                showAlert(message, 'danger');
            }

            // Update API template when checkboxes change
            document.addEventListener('DOMContentLoaded', function() {
                ['include-dns', 'include-settings', 'include-analytics'].forEach(id => {
                    document.getElementById(id).addEventListener('change', updateAPITemplate);
                });
                updateAPITemplate();
            });
        </script>
    </body>
    </html>
    <?php
}