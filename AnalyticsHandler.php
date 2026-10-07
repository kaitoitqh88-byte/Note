<?php
/**
 * Analytics Handler
 * Xử lý Analytics
 */

/**
 * Xử lý Analytics
 */
function handleAnalytics($cloudflare) {
    $zoneId = $_GET['zone_id'] ?? null;
    
    if (!$zoneId) {
        http_response_code(400);
        echo json_encode(['error' => 'Zone ID is required']);
        return;
    }
    
    $since = $_GET['since'] ?? null;
    $until = $_GET['until'] ?? null;
    
    try {
        $result = $cloudflare->getAnalytics($zoneId, $since, $until);
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