<?php
/**
 * Example Usage File
 * Ví dụ cách sử dụng CloudflareAPI class
 */

require_once 'config.php';
require_once 'CloudflareAPI.php';

try {
    // Khởi tạo API
    $cloudflare = new CloudflareAPI();
    
    // 1. Lấy danh sách zones
    echo "=== DANH SÁCH ZONES ===\n";
    $zones = $cloudflare->listZones();
    
    if (!empty($zones['result'])) {
        foreach ($zones['result'] as $zone) {
            echo "Zone: {$zone['name']} (ID: {$zone['id']}) - Status: {$zone['status']}\n";
        }
        
        // Lấy zone đầu tiên để demo
        $zoneId = $zones['result'][0]['id'];
        $zoneName = $zones['result'][0]['name'];
        
        echo "\n=== DNS RECORDS CHO ZONE: {$zoneName} ===\n";
        
        // 2. Lấy DNS records
        $dnsRecords = $cloudflare->listDNSRecords($zoneId);
        
        if (!empty($dnsRecords['result'])) {
            foreach ($dnsRecords['result'] as $record) {
                echo "Type: {$record['type']} | Name: {$record['name']} | Content: {$record['content']} | TTL: {$record['ttl']}\n";
            }
        } else {
            echo "Không có DNS records nào.\n";
        }
        
        // 3. Tạo DNS record mới (ví dụ)
        /*
        echo "\n=== TẠO DNS RECORD MỚI ===\n";
        $newRecord = $cloudflare->createDNSRecord(
            $zoneId,
            'A',
            'test.' . $zoneName,
            '192.168.1.1',
            300
        );
        
        if ($newRecord['success']) {
            echo "Đã tạo DNS record thành công: {$newRecord['result']['name']}\n";
        }
        */
        
        // 4. Lấy analytics
        echo "\n=== ANALYTICS ===\n";
        try {
            $analytics = $cloudflare->getAnalytics($zoneId);
            
            if (!empty($analytics['result']['totals'])) {
                $totals = $analytics['result']['totals'];
                echo "Total Requests: " . ($totals['requests']['all'] ?? 'N/A') . "\n";
                echo "Total Bandwidth: " . ($totals['bandwidth']['all'] ?? 'N/A') . " bytes\n";
                echo "Threats Blocked: " . ($totals['threats']['all'] ?? 'N/A') . "\n";
            } else {
                echo "Không có dữ liệu analytics.\n";
            }
        } catch (Exception $e) {
            echo "Analytics không khả dụng: " . $e->getMessage() . "\n";
        }
        
        // 5. Purge cache (uncomment để test)
        /*
        echo "\n=== PURGE CACHE ===\n";
        $purgeResult = $cloudflare->purgeCache($zoneId);
        
        if ($purgeResult['success']) {
            echo "Cache đã được purge thành công!\n";
        }
        */
        
        // 6. SSL Settings
        echo "\n=== SSL SETTINGS ===\n";
        try {
            $sslMode = $cloudflare->setSSLMode($zoneId, 'flexible');
            if ($sslMode['success']) {
                echo "SSL mode đã được set thành 'flexible'\n";
            }
        } catch (Exception $e) {
            echo "Không thể thay đổi SSL mode: " . $e->getMessage() . "\n";
        }
        
    } else {
        echo "Không tìm thấy zones nào.\n";
    }
    
} catch (Exception $e) {
    echo "Lỗi: " . $e->getMessage() . "\n";
    echo "Vui lòng kiểm tra:\n";
    echo "1. API Token trong file token.txt\n";
    echo "2. Email trong config.php\n";
    echo "3. Kết nối internet\n";
    echo "4. PHP curl extension\n";
}

echo "\n=== HOÀN THÀNH ===\n";