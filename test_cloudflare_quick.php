<?php
/**
 * Test Cloudflare DNS API - Lấy DNS Records cho domain
 */

require_once 'config.php';
require_once 'CloudflareAPI.php';

echo "=== CLOUDFLARE DNS API TEST ===\n\n";

// Thay đổi domain này thành domain trong Cloudflare account của bạn
$testDomain = 'example.com'; // ⚠️ THAY ĐỔI DOMAIN NÀY

echo "🔍 Testing Cloudflare API for domain: {$testDomain}\n";
echo "📧 Cloudflare Email: " . CLOUDFLARE_EMAIL . "\n";
echo "🔑 API Token: " . substr(CLOUDFLARE_API_TOKEN, 0, 8) . "...\n\n";

try {
    $cloudflare = new CloudflareAPI();
    
    // 1. Get Zone ID for domain
    echo "Step 1: Lấy Zone ID cho domain {$testDomain}\n";
    $zones = $cloudflare->listZones(1, 20, $testDomain);
    
    if (!$zones || !isset($zones['result']) || empty($zones['result'])) {
        echo "❌ Domain '{$testDomain}' không tìm thấy trong Cloudflare account!\n";
        echo "💡 Kiểm tra:\n";
        echo "   - Domain đã được add vào Cloudflare chưa?\n";
        echo "   - API token có quyền truy cập không?\n";
        echo "   - Email trong config.php có đúng không?\n\n";
        
        // List all domains in account
        echo "📋 Domains có sẵn trong Cloudflare account:\n";
        $allZones = $cloudflare->listZones(1, 50);
        if ($allZones && isset($allZones['result'])) {
            foreach ($allZones['result'] as $zone) {
                echo "   - {$zone['name']} (Status: {$zone['status']})\n";
            }
        } else {
            echo "   Không lấy được danh sách domains!\n";
        }
        exit();
    }
    
    $zone = $zones['result'][0];
    $zoneId = $zone['id'];
    echo "✅ Zone ID: {$zoneId}\n";
    echo "   Domain: {$zone['name']}\n";
    echo "   Status: {$zone['status']}\n\n";
    
    // 2. Get DNS Records
    echo "Step 2: Lấy DNS Records cho Zone {$zone['name']}\n";
    $dnsRecords = $cloudflare->listDNSRecords($zoneId);
    
    if (!$dnsRecords || !isset($dnsRecords['result'])) {
        echo "❌ Không lấy được DNS records!\n";
        exit();
    }
    
    $records = $dnsRecords['result'];
    echo "✅ Tìm thấy " . count($records) . " DNS records\n\n";
    
    // 3. Analyze and display records
    $recordTypes = [];
    $proxiedCount = 0;
    $dnsOnlyCount = 0;
    
    echo "📋 CHI TIẾT DNS RECORDS:\n";
    echo str_repeat('-', 80) . "\n";
    printf("%-20s %-25s %-20s %-8s %s\n", 'TYPE', 'NAME', 'CONTENT', 'PROXIED', 'TTL');
    echo str_repeat('-', 80) . "\n";
    
    foreach ($records as $record) {
        $type = $record['type'];
        $name = $record['name'];
        $content = strlen($record['content']) > 18 ? substr($record['content'], 0, 18) . '...' : $record['content'];
        $proxied = $record['proxied'] ? '🟠 YES' : '⚫ NO';
        $ttl = $record['ttl'] == 1 ? 'Auto' : $record['ttl'];
        
        printf("%-20s %-25s %-20s %-8s %s\n", $type, $name, $content, $proxied, $ttl);
        
        // Count by type
        $recordTypes[$type] = ($recordTypes[$type] ?? 0) + 1;
        
        // Count by proxy status
        if ($record['proxied']) {
            $proxiedCount++;
        } else {
            $dnsOnlyCount++;
        }
    }
    
    echo str_repeat('-', 80) . "\n\n";
    
    // 4. Statistics
    echo "📊 THỐNG KÊ DNS RECORDS:\n";
    echo "🔢 Total Records: " . count($records) . "\n";
    echo "🟠 Proxied Records: {$proxiedCount}\n";
    echo "⚫ DNS Only Records: {$dnsOnlyCount}\n\n";
    
    echo "📋 Records by Type:\n";
    foreach ($recordTypes as $type => $count) {
        echo "   {$type}: {$count} records\n";
    }
    
    echo "\n🎉 TEST THÀNH CÔNG!\n";
    echo "💡 Để sử dụng đầy đủ, truy cập: search.php\n";
    
} catch (Exception $e) {
    echo "❌ LỖI API: " . $e->getMessage() . "\n";
    echo "💡 Kiểm tra:\n";
    echo "   - API Token có đúng không?\n";
    echo "   - Email Cloudflare có đúng không?\n";
    echo "   - Internet connection\n";
}

echo "\n" . str_repeat('=', 60) . "\n";
?>