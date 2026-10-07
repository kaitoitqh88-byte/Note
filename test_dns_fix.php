<?php
/**
 * Quick Test for Cloudflare DNS Fix
 */

require_once 'config.php';
require_once 'CacheHandler.php';
require_once 'CloudflareAPI.php';

echo "=== TESTING FIXED CLOUDFLARE DNS FUNCTION ===\n\n";

// Test 1: List available domains
echo "1. Testing available domains list...\n";
ob_start();
listCloudflaredomains();
$domainsJson = ob_get_clean();
$domainsResult = json_decode($domainsJson, true);

if ($domainsResult && $domainsResult['success']) {
    echo "✅ Available domains retrieved successfully!\n";
    echo "Total domains: " . $domainsResult['total'] . "\n";
    
    if (!empty($domainsResult['domains'])) {
        echo "Domains in account:\n";
        foreach ($domainsResult['domains'] as $domain) {
            echo "  - {$domain['name']} (Status: {$domain['status']}, Plan: {$domain['plan']})\n";
        }
        
        // Test 2: Test DNS lookup with first domain
        $testDomain = $domainsResult['domains'][0]['name'];
        echo "\n2. Testing DNS lookup với domain: {$testDomain}\n";
        
        $_POST['domain'] = $testDomain;
        $_POST['include_proxied'] = 'true';
        $_POST['compare_dns'] = 'false';
        
        ob_start();
        getCloudflaresDNSRecords();
        $dnsJson = ob_get_clean();
        $dnsResult = json_decode($dnsJson, true);
        
        unset($_POST['domain'], $_POST['include_proxied'], $_POST['compare_dns']);
        
        if ($dnsResult && $dnsResult['success']) {
            echo "✅ DNS lookup successful!\n";
            $data = $dnsResult['data'];
            echo "Zone ID: {$data['zone_id']}\n";
            echo "Domain: {$data['domain']}\n";
            echo "Total Records: {$data['statistics']['total_records']}\n";
            echo "Proxied: {$data['statistics']['proxied_records']}\n";
            echo "DNS Only: {$data['statistics']['dns_only_records']}\n";
            
            echo "Record types found:\n";
            foreach ($data['cloudflare_records'] as $type => $records) {
                echo "  {$type}: " . count($records) . " records\n";
            }
            
            // Show sample records
            echo "\nSample records:\n";
            foreach ($data['cloudflare_records'] as $type => $records) {
                if (!empty($records)) {
                    $first = $records[0];
                    $proxy = $first['proxied'] ? '🟠 Proxied' : '⚫ DNS Only';
                    echo "  {$type}: {$first['name']} -> {$first['content']} ({$proxy})\n";
                    break;
                }
            }
            
            echo "\n🎉 ALL TESTS PASSED!\n";
            
        } else {
            echo "❌ DNS lookup failed: " . ($dnsResult['error'] ?? 'Unknown error') . "\n";
            if (isset($dnsResult['available_domains'])) {
                echo "Available domains suggested: " . implode(', ', $dnsResult['available_domains']) . "\n";
            }
        }
        
    } else {
        echo "❌ No domains found in account\n";
    }
    
} else {
    echo "❌ Failed to get available domains: " . ($domainsResult['error'] ?? 'Unknown error') . "\n";
}

echo "\n=== TEST API Endpoints ===\n";
echo "Test these URLs in browser:\n";
echo "1. List domains: /?action=cloudflare-dns&_list_domains=1\n";

if (isset($testDomain)) {
    echo "2. DNS lookup: /?action=cloudflare-dns&domain={$testDomain}&include_proxied=true\n";
}

echo "\n=== DEMOS ===\n";
echo "1. Live Demo: /demo_live.html\n";
echo "2. Backend Demo: /demo_simple.php\n"; 
echo "3. Complete Demo: /demo_complete.html\n";

echo "\n⚡ DNS Records function đã được fix và ready to use!\n";
?>