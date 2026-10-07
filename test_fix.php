<?php
/**
 * Test fix for undefined $searchDomain variable
 */

require_once 'd:\Note\config.php';
require_once 'd:\Note\CloudflareAPI.php';
require_once 'd:\Note\cache_ttl_bulk_update.php';

try {
    $api = new CloudflareAPI();
    echo "Testing checkDomainsInfo function...\n";
    echo "===================================\n\n";
    
    // Test the specific domain that caused the issue
    $result = checkDomainsInfo($api, 'fulin.cn.com');
    
    echo "Results:\n";
    foreach ($result as $domainResult) {
        echo "Domain: {$domainResult['domain_name']}\n";
        echo "Zone found: " . ($domainResult['zone_found'] ? 'Yes' : 'No') . "\n";
        
        if ($domainResult['zone_found']) {
            echo "Zone info: {$domainResult['zone_info']['name']} (ID: {$domainResult['zone_info']['id']})\n";
            if (isset($domainResult['match_info'])) {
                echo "Match info: {$domainResult['match_info']}\n";
            }
        }
        
        if ($domainResult['error']) {
            echo "Error: {$domainResult['error']}\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>