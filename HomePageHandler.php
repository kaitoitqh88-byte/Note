<?php
/**
 * HomePage Handler
 * Xử lý trang chủ với thông kê và danh sách chức năng
 */

/**
 * Hiển thị Trang chủ với thông kê và danh sách chức năng
 */
function showHomepage($cloudflare) {
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['api'])) {
        // Hiển thị giao diện HTML
        include 'homepage.php';
        return;
    }
    
    // API response cho trang chủ
    try {
        $zones = $cloudflare->listZones(1, 100); // Get first 100 zones for stats
        $totalZones = $zones['result_info']['total_count'] ?? 0;
        $activeZones = 0;
        $sslEnabled = 0;
        $dnsOnlyZones = 0;
        
        if (!empty($zones['result'])) {
            foreach ($zones['result'] as $zone) {
                if ($zone['status'] === 'active') {
                    $activeZones++;
                }
                // Check SSL status - this is a simplified check
                if (isset($zone['ssl']) || $zone['status'] === 'active') {
                    $sslEnabled++;
                }
                if (isset($zone['plan']['name']) && strpos(strtolower($zone['plan']['name']), 'dns') !== false) {
                    $dnsOnlyZones++;
                }
            }
        }
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'stats' => [
                'total_zones' => $totalZones,
                'active_zones' => $activeZones,
                'ssl_enabled' => $sslEnabled,
                'dns_only' => $dnsOnlyZones
            ],
            'recent_zones' => array_slice($zones['result'] ?? [], 0, 5) // 5 zones gần nhất
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}