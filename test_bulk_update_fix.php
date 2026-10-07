<?php
/**
 * Test bulkUpdateCacheTTL fix for undefined $found variable
 */

require_once 'd:\Note\config.php';
require_once 'd:\Note\CloudflareAPI.php';
require_once 'd:\Note\cache_ttl_bulk_update.php';

try {
    $api = new CloudflareAPI();
    echo "Testing bulkUpdateCacheTTL function...\n";
    echo "====================================\n\n";
    
    // Test with mb66ee.com - the domain that caused the issue
    $result = bulkUpdateCacheTTL($api, 'mb66ee.com', '2678400', '', '');
    
    echo "Results:\n";
    foreach ($result as $domainResult) {
        echo "Domain: {$domainResult['domain_name']}\n";
        echo "Zone found: " . ($domainResult['zone_found'] ? 'Yes' : 'No') . "\n";
        echo "Success: " . ($domainResult['success'] ? 'Yes' : 'No') . "\n";
        
        if (isset($domainResult['match_info'])) {
            echo "Match info: {$domainResult['match_info']}\n";
        }
        
        if ($domainResult['error']) {
            echo "Error: {$domainResult['error']}\n";
        }
        
        if ($domainResult['zone_found'] && isset($domainResult['browser_ttl_update'])) {
            echo "Browser TTL update: " . ($domainResult['browser_ttl_update']['success'] ? 'Success' : 'Failed') . "\n";
            if (isset($domainResult['browser_ttl_update']['human_readable'])) {
                echo "New TTL: {$domainResult['browser_ttl_update']['human_readable']}\n";
            }
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>