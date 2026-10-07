<?php
/**
 * Test Cloudflare DNS Lookup Functionality
 * Test script cho chức năng lấy DNS records từ Cloudflare API
 */

require_once 'config.php';
require_once 'CacheHandler.php';
require_once 'CloudflareAPI.php';

echo "=== TEST CLOUDFLARE DNS LOOKUP FUNCTIONALITY ===\n\n";

// Test domains để kiểm tra (thay thế bằng domains trong Cloudflare account của bạn)
$testDomains = [
    'example.com',      // Thay thế bằng domain thật trong CF account
    'test-domain.com',  // Thay thế bằng domain thật trong CF account
];

echo "⚠️  QUAN TRỌNG: Thay thế domains trên bằng domains thật trong Cloudflare account của bạn!\n\n";

foreach ($testDomains as $domain) {
    echo "Testing Cloudflare DNS for domain: {$domain}\n";
    echo str_repeat('-', 50) . "\n";
    
    try {
        // Test lấy DNS records từ Cloudflare
        $_POST['domain'] = $domain;
        $_POST['include_proxied'] = 'true';
        $_POST['show_only_active'] = 'true';
        $_POST['compare_dns'] = 'true';
        
        // Capture output
        ob_start();
        getCloudflaresDNSRecords();
        $jsonOutput = ob_get_clean();
        
        // Parse and display results
        $result = json_decode($jsonOutput, true);
        
        if ($result && $result['success']) {
            $data = $result['data'];
            
            echo "✓ Cloudflare DNS lookup successful for {$domain}\n";
            echo "Zone ID: " . $data['zone_id'] . "\n";
            echo "Total Records: " . $data['statistics']['total_records'] . "\n";
            echo "Proxied Records: " . $data['statistics']['proxied_records'] . "\n";
            echo "DNS Only Records: " . $data['statistics']['dns_only_records'] . "\n";
            echo "Record Types: " . count($data['statistics']['record_types']) . "\n";
            
            // Display records by type
            if (!empty($data['cloudflare_records'])) {
                echo "\nCloudflare DNS Records:\n";
                foreach ($data['cloudflare_records'] as $type => $records) {
                    echo "  {$type}: " . count($records) . " records\n";
                    foreach (array_slice($records, 0, 3) as $record) { // Show first 3
                        $proxy = $record['proxied'] ? '🟠 Proxied' : '⚫ DNS Only';
                        echo "    - {$record['name']} → {$record['content']} ({$proxy})\n";
                    }
                    if (count($records) > 3) {
                        echo "    ... and " . (count($records) - 3) . " more\n";
                    }
                }
            }
            
            // Display comparison if available
            if (isset($data['comparison'])) {
                $comp = $data['comparison'];
                echo "\nDNS Comparison Results:\n";
                echo "  Matches: " . $comp['summary']['total_matches'] . "\n";
                echo "  Mismatches: " . $comp['summary']['total_mismatches'] . "\n";
                echo "  Cloudflare Only: " . $comp['summary']['cloudflare_only_count'] . "\n";
                echo "  Public Only: " . $comp['summary']['public_only_count'] . "\n";
            }
            
        } else {
            echo "✗ Cloudflare DNS lookup failed: " . ($result['error'] ?? 'Unknown error') . "\n";
            if (strpos($result['error'] ?? '', 'not found in Cloudflare account') !== false) {
                echo "  💡 Tip: Đảm bảo domain '{$domain}' có trong Cloudflare account của bạn\n";
            }
        }
        
        // Clean POST data for next iteration
        unset($_POST['domain'], $_POST['include_proxied'], $_POST['show_only_active'], $_POST['compare_dns']);
        
    } catch (Exception $e) {
        echo "✗ Exception: " . $e->getMessage() . "\n";
    }
    
    echo "\n" . str_repeat('=', 60) . "\n\n";
}

// Test direct Cloudflare API access
echo "=== TESTING DIRECT CLOUDFLARE API ACCESS ===\n";
try {
    $cloudflare = new CloudflareAPI();
    
    echo "Testing Cloudflare API connection:\n";
    $zones = $cloudflare->listZones(1, 5);
    
    if ($zones && isset($zones['result'])) {
        echo "✓ Cloudflare API connection successful\n";
        echo "Available zones in account: " . count($zones['result']) . "\n";
        
        foreach ($zones['result'] as $zone) {
            echo "  - {$zone['name']} (Status: {$zone['status']}, Plan: " . ($zone['plan']['name'] ?? 'Unknown') . ")\n";
        }
        
        // Test DNS records for first zone
        if (!empty($zones['result'])) {
            $firstZone = $zones['result'][0];
            echo "\nTesting DNS records for: {$firstZone['name']}\n";
            
            $dnsRecords = $cloudflare->listDNSRecords($firstZone['id'], null, null, true);
            if (isset($dnsRecords['result'])) {
                echo "DNS records found: " . count($dnsRecords['result']) . "\n";
                
                $recordTypes = [];
                $proxiedCount = 0;
                foreach ($dnsRecords['result'] as $record) {
                    $type = $record['type'];
                    $recordTypes[$type] = ($recordTypes[$type] ?? 0) + 1;
                    if ($record['proxied'] ?? false) {
                        $proxiedCount++;
                    }
                }
                
                echo "Record types breakdown:\n";
                foreach ($recordTypes as $type => $count) {
                    echo "  {$type}: {$count}\n";
                }
                echo "Proxied records: {$proxiedCount}\n";
                echo "DNS Only records: " . (count($dnsRecords['result']) - $proxiedCount) . "\n";
                
            } else {
                echo "No DNS records found or API error\n";
            }
        }
        
    } else {
        echo "✗ Cloudflare API connection failed\n";
        echo "Check your API credentials in config.php\n";
    }
    
} catch (Exception $e) {
    echo "✗ Cloudflare API Exception: " . $e->getMessage() . "\n";
    echo "Make sure CLOUDFLARE_API_TOKEN and CLOUDFLARE_EMAIL are set correctly\n";
}

echo "\n=== TEST COMPLETED ===\n";
echo "\n📌 Các bước để test đầy đủ:\n";
echo "1. Cập nhật \$testDomains với domains thật trong Cloudflare account\n";
echo "2. Kiểm tra config.php có CLOUDFLARE_API_TOKEN và CLOUDFLARE_EMAIL\n";
echo "3. Truy cập search.php để sử dụng search tool\n";
echo "4. Sử dụng tab 'Cloudflare DNS' để test UI\n";
echo "5. Test comparison feature bằng cách check 'Compare with public DNS'\n";
?>