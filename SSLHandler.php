<?php
/**
 * SSL Handler
 * Xử lý cài đặt SSL/HTTPS
 */

/**
 * Xử lý cài đặt SSL/HTTPS
 */
function handleSSL($cloudflare) {
    $method = $_SERVER['REQUEST_METHOD'];
    $zoneId = $_REQUEST['zone_id'] ?? null;
    
    if (!$zoneId) {
        http_response_code(400);
        echo json_encode(['error' => 'Zone ID is required']);
        return;
    }
    
    try {
        switch ($method) {
            case 'GET':
                // Lấy thông tin SSL settings
                $result = $cloudflare->getSSLSettings($zoneId);
                break;
                
            case 'POST':
            case 'PATCH':
                $action = $_REQUEST['action_type'] ?? 'always_use_https';
                $enabled = filter_var($_REQUEST['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
                
                switch ($action) {
                    case 'always_use_https':
                        $result = $cloudflare->setAlwaysUseHTTPS($zoneId, $enabled);
                        break;
                    case 'ssl_mode':
                        $mode = $_REQUEST['mode'] ?? 'flexible';
                        $result = $cloudflare->setSSLMode($zoneId, $mode);
                        break;
                    default:
                        throw new Exception('Invalid SSL action');
                }
                break;
                
            default:
                http_response_code(405);
                echo json_encode(['error' => 'Method not allowed']);
                return;
        }
        
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
 * Xử lý kiểm tra trạng thái Always Use HTTPS
 */
function handleHTTPSCheck($cloudflare) {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Only GET method allowed']);
        return;
    }
    
    try {
        $checkType = $_GET['type'] ?? 'single';
        
        switch ($checkType) {
            case 'single':
                $zoneId = $_GET['zone_id'] ?? null;
                if (!$zoneId) {
                    throw new Exception('Zone ID is required for single domain check');
                }
                $result = $cloudflare->getAlwaysUseHTTPSStatus($zoneId);
                break;
                
            case 'all':
                $result = $cloudflare->checkAllDomainsHTTPSStatus();
                break;
                
            case 'multiple':
                $zoneIds = $_GET['zone_ids'] ?? null;
                if (!$zoneIds) {
                    throw new Exception('Zone IDs are required for multiple domains check');
                }
                
                // Parse zone IDs from comma-separated string or JSON array
                if (is_string($zoneIds)) {
                    $zoneIds = strpos($zoneIds, '[') === 0 ? 
                        json_decode($zoneIds, true) : 
                        explode(',', $zoneIds);
                }
                
                $result = $cloudflare->checkMultipleDomainsHTTPSStatus($zoneIds);
                break;
                
            default:
                throw new Exception('Invalid check type. Use: single, all, or multiple');
        }
        
        echo json_encode([
            'success' => true,
            'check_type' => $checkType,
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
 * Xử lý test chức năng Always Use HTTPS
 */
function handleHTTPSTest($cloudflare) {
    $method = $_SERVER['REQUEST_METHOD'];
    
    if ($method !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Only GET method allowed']);
        return;
    }
    
    try {
        $result = $cloudflare->testAlwaysHTTPSFunctionality();
        
        echo json_encode([
            'success' => true,
            'test_report' => $result
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'test_failed' => true
        ]);
    }
}