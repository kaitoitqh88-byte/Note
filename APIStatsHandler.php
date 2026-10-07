<?php
/**
 * API Stats Handler
 * Xử lý API statistics và performance metrics
 */

/**
 * Xử lý API statistics và performance metrics
 */
function handleAPIStats($cloudflare) {
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    $detailed = ($_GET['detailed'] ?? 'false') === 'true';
    
    try {
        $apiStats = $cloudflare->getAPIStats();
        $response = [
            'success' => true,
            'api_stats' => $apiStats,
            'timestamp' => time(),
            'formatted_timestamp' => date('Y-m-d H:i:s')
        ];
        
        if ($detailed && isset($apiStats['cache_stats'])) {
            $cacheStats = $apiStats['cache_stats'];
            
            // Calculate additional metrics
            $response['detailed_metrics'] = [
                'cache_efficiency' => [
                    'hit_rate_percentage' => $apiStats['cache_hit_rate'],
                    'miss_rate_percentage' => round(100 - $apiStats['cache_hit_rate'], 2),
                    'total_requests' => $apiStats['total_requests'],
                    'cache_hits' => $apiStats['cache_hits'],
                    'cache_misses' => $apiStats['total_requests'] - $apiStats['cache_hits']
                ],
                'cache_storage' => [
                    'total_files' => $cacheStats['total_files'],
                    'valid_files' => $cacheStats['valid_files'], 
                    'expired_files' => $cacheStats['expired_files'],
                    'storage_used_mb' => $cacheStats['total_size_mb'],
                    'storage_limit_mb' => $cacheStats['max_cache_size_mb'],
                    'storage_utilization_percent' => round(($cacheStats['total_size_mb'] / $cacheStats['max_cache_size_mb']) * 100, 2)
                ],
                'cache_breakdown' => $cacheStats['cache_types']
            ];
            
            // Performance recommendations
            $recommendations = [];
            if ($apiStats['cache_hit_rate'] < 50) {
                $recommendations[] = 'Cache hit rate thấp (<50%). Hãy warm cache hoặc tăng TTL.';
            }
            if ($cacheStats['expired_files'] > $cacheStats['valid_files']) {
                $recommendations[] = 'Có nhiều cache files expired. Hãy cleanup cache.';
            }
            if ($response['detailed_metrics']['cache_storage']['storage_utilization_percent'] > 80) {
                $recommendations[] = 'Cache storage gần đầy (>80%). Hãy increase cache size limit.';
            }
            
            $response['recommendations'] = $recommendations;
        }
        
        echo json_encode($response);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
}