<?php
/**
 * Config Handler
 * Xử lý các cấu hình như perPage
 */

/**
 * Xử lý cấu hình và test perPage
 */
function handlePerPageConfig($cloudflare) {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method === 'GET') {
        // Hiển thị thông tin cấu hình hiện tại
        $action = $_GET['test'] ?? 'info';
        
        switch ($action) {
            case 'info':
                echo json_encode([
                    'success' => true,
                    'config' => [
                        'default_per_page' => $cloudflare->getDefaultPerPage(),
                        'max_per_page' => $cloudflare->getMaxPerPage()
                    ],
                    'usage_examples' => [
                        'get_zones_default' => '?action=perpage-config&test=zones&per_page=default',
                        'get_zones_custom' => '?action=perpage-config&test=zones&per_page=25',
                        'search_zones' => '?action=perpage-config&test=search&query=domain&per_page=10'
                    ]
                ]);
                break;
                
            case 'zones':
                $perPage = $_GET['per_page'] ?? 'default';
                $perPageValue = ($perPage === 'default') ? null : intval($perPage);
                
                try {
                    $startTime = microtime(true);
                    $zones = $cloudflare->getZones($perPageValue);
                    $endTime = microtime(true);
                    
                    echo json_encode([
                        'success' => true,
                        'test_type' => 'zones',
                        'requested_per_page' => $perPage,
                        'actual_per_page' => $perPageValue ?? $cloudflare->getDefaultPerPage(),
                        'results_count' => count($zones['result'] ?? []),
                        'response_time_ms' => round(($endTime - $startTime) * 1000, 2),
                        'data' => $zones
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
                break;
                
            case 'search':
                $query = $_GET['query'] ?? '';
                $perPage = $_GET['per_page'] ?? 'default';
                $perPageValue = ($perPage === 'default') ? null : intval($perPage);
                
                try {
                    $startTime = microtime(true);
                    $results = $cloudflare->searchZones($query, 1, $perPageValue);
                    $endTime = microtime(true);
                    
                    echo json_encode([
                        'success' => true,
                        'test_type' => 'search',
                        'query' => $query,
                        'requested_per_page' => $perPage,
                        'actual_per_page' => $perPageValue ?? $cloudflare->getDefaultPerPage(),
                        'results_count' => count($results['result'] ?? []),
                        'response_time_ms' => round(($endTime - $startTime) * 1000, 2),
                        'data' => $results
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => $e->getMessage()
                    ]);
                }
                break;
                
            default:
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid test type. Use: info, zones, search'
                ]);
        }
        
    } elseif ($method === 'POST') {
        // Thay đổi cấu hình perPage
        $newPerPage = intval($_POST['per_page'] ?? 50);
        
        try {
            $oldPerPage = $cloudflare->getDefaultPerPage();
            $cloudflare->setDefaultPerPage($newPerPage);
            
            echo json_encode([
                'success' => true,
                'message' => 'PerPage configuration updated',
                'old_per_page' => $oldPerPage,
                'new_per_page' => $cloudflare->getDefaultPerPage(),
                'max_per_page' => $cloudflare->getMaxPerPage()
            ]);
        } catch (Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
        
    } else {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
    }
}