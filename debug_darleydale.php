<?php
/**
 * Debug Cloudflare DNS API để tìm vấn đề
 */

require_once 'config.php';
require_once 'CloudflareAPI.php';
require_once 'CacheHandler.php';

echo "=== DEBUGGING DARLEYDALE.UK.COM DNS LOOKUP ===\n\n";

$domain = 'darleydale.uk.com';

try {
    $cloudflare = new CloudflareAPI();
    
    echo "1. Testing API connection...\n";
    echo "API Token: " . substr(CLOUDFLARE_API_TOKEN, 0, 10) . "...\n";
    echo "Email: " . CLOUDFLARE_EMAIL . "\n\n";
    
    // Step 1: Find zone
    echo "2. Finding zone for domain: {$domain}\n";
    $zones = $cloudflare->listZones(1, 100);
    
    if (!$zones || !isset($zones['result'])) {
        die("❌ Failed to get zones from API\n");
    }
    
    echo "Found " . count($zones['result']) . " total zones in account:\n";
    $zoneId = null;
    $targetDomain = null;
    
    foreach ($zones['result'] as $zone) {
        echo "  - {$zone['name']} (ID: {$zone['id']})\n";
        if ($zone['name'] === $domain) {
            $zoneId = $zone['id'];
            $targetDomain = $zone['name'];
            echo "  🎯 FOUND TARGET ZONE! ID: {$zoneId}\n";
        }
    }
    
    if (!$zoneId) {
        echo "❌ Zone for {$domain} not found in account!\n";
        echo "Available zones: " . implode(', ', array_column($zones['result'], 'name')) . "\n";
        exit();
    }
    
    echo "\n3. Getting DNS records for zone: {$targetDomain} (ID: {$zoneId})\n";
    
    // Step 2: Get DNS records - with per_page parameter
    $dnsResponse = $cloudflare->getDNSRecords($zoneId, ['per_page' => 100]);
    
    echo "Raw API response structure:\n";
    echo "Success: " . (isset($dnsResponse['success']) ? ($dnsResponse['success'] ? 'true' : 'false') : 'not set') . "\n";
    
    if (isset($dnsResponse['errors']) && !empty($dnsResponse['errors'])) {
        echo "API Errors:\n";
        foreach ($dnsResponse['errors'] as $error) {
            echo "  - {$error['message']} (Code: {$error['code']})\n";
        }
    }
    
    if (!isset($dnsResponse['result'])) {
        echo "❌ No 'result' field in API response!\n";
        echo "Full response: " . json_encode($dnsResponse, JSON_PRETTY_PRINT) . "\n";
        exit();
    }
    
    $records = $dnsResponse['result'];
    echo "✅ Found " . count($records) . " DNS records\n\n";
    
    if (empty($records)) {
        echo "⚠️ No DNS records found in response!\n";
        exit();
    }
    
    // Step 3: Analyze records
    echo "4. Analyzing records:\n";
    echo "Type    | Name                    | Content                 | Proxied | TTL\n";
    echo "--------|-------------------------|-------------------------|---------|----\n";
    
    $recordsByType = [];
    foreach ($records as $record) {
        $type = $record['type'] ?? 'UNKNOWN';
        $name = $record['name'] ?? 'NO_NAME';
        $content = $record['content'] ?? 'NO_CONTENT';
        $proxied = ($record['proxied'] ?? false) ? 'YES' : 'NO';
        $ttl = $record['ttl'] ?? 'N/A';
        
        printf("%-7s | %-23s | %-23s | %-7s | %s\n", 
            $type, 
            substr($name, 0, 23), 
            substr($content, 0, 23), 
            $proxied,
            $ttl == 1 ? 'Auto' : $ttl
        );
        
        if (!isset($recordsByType[$type])) {
            $recordsByType[$type] = [];
        }
        $recordsByType[$type][] = $record;
    }
    
    echo "\n5. Expected vs Actual comparison:\n";
    
    $expectedRecords = [
        'A' => ['name' => 'darleydale.uk.com', 'content' => '103.213.216.110', 'proxied' => true],
        'CNAME' => ['name' => 'www.darleydale.uk.com', 'content' => 'darleydale.uk.com', 'proxied' => true]
    ];
    
    foreach ($expectedRecords as $expectedType => $expectedRecord) {
        echo "\nChecking {$expectedType} record:\n";
        echo "Expected: {$expectedRecord['name']} -> {$expectedRecord['content']} (Proxied: " . ($expectedRecord['proxied'] ? 'YES' : 'NO') . ")\n";
        
        if (isset($recordsByType[$expectedType])) {
            echo "Found " . count($recordsByType[$expectedType]) . " {$expectedType} record(s):\n";
            
            $found = false;
            foreach ($recordsByType[$expectedType] as $actual) {
                $actualName = $actual['name'];
                $actualContent = $actual['content'];
                $actualProxied = $actual['proxied'] ?? false;
                
                echo "  Actual: {$actualName} -> {$actualContent} (Proxied: " . ($actualProxied ? 'YES' : 'NO') . ")\n";
                
                if ($actualName === $expectedRecord['name'] && $actualContent === $expectedRecord['content']) {
                    $found = true;
                    echo "  ✅ MATCH!\n";
                }
            }
            
            if (!$found) {
                echo "  ❌ Expected record not found!\n";
            }
        } else {
            echo "❌ No {$expectedType} records found in API response!\n";
        }
    }
    
    echo "\n6. Testing với function getCloudflaresDNSRecords():\n";
    
    $_POST['domain'] = $domain;
    $_POST['include_proxied'] = 'true';
    $_POST['compare_dns'] = 'false';
    
    ob_start();
    getCloudflaresDNSRecords();
    $functionOutput = ob_get_clean();
    
    unset($_POST['domain'], $_POST['include_proxied'], $_POST['compare_dns']);
    
    echo "Function output:\n";
    $functionResult = json_decode($functionOutput, true);
    
    if ($functionResult && $functionResult['success']) {
        echo "✅ Function successful!\n";
        $data = $functionResult['data'];
        echo "Records returned by function:\n";
        foreach ($data['cloudflare_records'] as $type => $records) {
            echo "  {$type}: " . count($records) . " record(s)\n";
        }
        echo "Statistics: Total={$data['statistics']['total_records']}, Proxied={$data['statistics']['proxied_records']}\n";
    } else {
        echo "❌ Function failed: " . ($functionResult['error'] ?? 'Unknown error') . "\n";
        echo "Full function output:\n" . $functionOutput . "\n";
    }
    
    echo "\n=== DEBUG COMPLETE ===\n";
    echo "API Token working: " . (count($records) > 0 ? 'YES' : 'NO') . "\n";
    echo "Records found: " . count($records) . "\n";
    echo "Function working: " . ($functionResult && $functionResult['success'] ? 'YES' : 'NO') . "\n";
    
} catch (Exception $e) {
    echo "❌ EXCEPTION: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}
?>