<?php
/**
 * Debug searchZones API behavior
 */

require_once 'd:\Note\config.php';
require_once 'd:\Note\CloudflareAPI.php';

try {
    $api = new CloudflareAPI();
    echo "=== Debugging searchZones API ===\n\n";
    
    // Test 1: Compare listZones vs searchZones
    echo "1. listZones() vs searchZones() comparison:\n";
    echo str_repeat('-', 40) . "\n";
    
    $listResult = $api->listZones(1, 5);
    echo "listZones (first 5):\n";
    if ($listResult['success'] && isset($listResult['result'])) {
        foreach ($listResult['result'] as $zone) {
            echo "  - {$zone['name']} (ID: {$zone['id']})\n";
        }
    }
    
    echo "\n";
    
    // Test different search terms
    $searchTerms = ['bet', 'win', 'mb66', '11bet', '123win', 'de.com'];
    
    foreach ($searchTerms as $term) {
        echo "searchZones('$term'):\n";
        $searchResult = $api->searchZones($term, 1, 5);
        
        if ($searchResult['success'] && isset($searchResult['result'])) {
            if (empty($searchResult['result'])) {
                echo "  No results\n";
            } else {
                foreach ($searchResult['result'] as $zone) {
                    echo "  - {$zone['name']} (ID: {$zone['id']})\n";
                }
            }
        } else {
            echo "  Error or no results\n";
        }
        echo "\n";
    }
    
    // Test 2: Direct search for known domains
    echo "\n" . str_repeat('=', 50) . "\n";
    echo "2. Direct search for known domains:\n";
    echo str_repeat('-', 40) . "\n";
    
    $knownDomains = ['11bet.de.com', 'mb66ee.com', '123win.name'];
    foreach ($knownDomains as $domain) {
        echo "Searching for exact domain: '$domain'\n";
        $result = $api->searchZones($domain, 1, 5);
        
        if ($result['success'] && isset($result['result'])) {
            if (empty($result['result'])) {
                echo "  No results for exact match\n";
            } else {
                foreach ($result['result'] as $zone) {
                    $match = ($zone['name'] === $domain) ? " [EXACT MATCH]" : "";
                    echo "  - {$zone['name']} (ID: {$zone['id']})$match\n";
                }
            }
        }
        echo "\n";
    }
    
    // Test 3: Check if mb66ee.com really exists
    echo "\n" . str_repeat('=', 50) . "\n";
    echo "3. Verify mb66ee.com exists:\n";
    echo str_repeat('-', 40) . "\n";
    
    // Get total zones count
    $allZones = $api->listZones(1, 100);
    if ($allZones['success'] && isset($allZones['result'])) {
        echo "Total zones in account: " . count($allZones['result']) . "\n\n";
        
        $found = false;
        foreach ($allZones['result'] as $zone) {
            if (stripos($zone['name'], 'mb66') !== false) {
                echo "Found MB66-related domain: {$zone['name']} (ID: {$zone['id']})\n";
                $found = true;
            }
        }
        
        if (!$found) {
            echo "No MB66-related domains found in listZones\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>