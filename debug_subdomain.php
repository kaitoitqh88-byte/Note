<?php
/**
 * Debug Subdomain Matching
 */

require_once 'd:\Note\config.php';
require_once 'd:\Note\CloudflareAPI.php';

function cleanDomainName($domain) {
    $domain = trim($domain);
    $domain = preg_replace('/^[a-zA-Z]+:\/\//', '', $domain);
    $domain = preg_replace('/\/.*$/', '', $domain);
    $domain = preg_replace('/:.*$/', '', $domain);
    $domain = strtolower(trim($domain));
    if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
        return '';
    }
    $domain = trim($domain, '.');
    return $domain;
}

echo "=== Debugging Subdomain Matching ===\n\n";

try {
    $api = new CloudflareAPI();
    
    // First, list all domains to see what we have
    echo "1. Available domains in account:\n";
    echo str_repeat('-', 40) . "\n";
    
    $allZones = $api->listZones(1, 100);
    $availableDomains = [];
    if ($allZones['success'] && isset($allZones['result'])) {
        foreach ($allZones['result'] as $zone) {
            $availableDomains[] = $zone['name'];
            echo "  - {$zone['name']}\n";
        }
    }
    
    echo "\nTotal domains: " . count($availableDomains) . "\n\n";
    
    // Test subdomain parsing  
    $testSubdomains = ['api.11bet.de.com', 'test.123win.name', 'www.11bet.de.com'];
    
    foreach ($testSubdomains as $subdomain) {
        echo "2. Testing subdomain: $subdomain\n";
        echo str_repeat('-', 30) . "\n";
        
        $cleanDomain = cleanDomainName($subdomain);
        echo "Cleaned domain: $cleanDomain\n";
        
        $domainParts = explode('.', $cleanDomain);
        echo "Domain parts: " . implode(' | ', $domainParts) . "\n";
        echo "Parts count: " . count($domainParts) . "\n";
        
        if (count($domainParts) > 2) {
            echo "Testing parent domains:\n";
            for ($i = 1; $i < count($domainParts) - 1; $i++) {
                $parentDomain = implode('.', array_slice($domainParts, $i));
                echo "  Level $i: $parentDomain\n";
                
                // Check if this parent domain exists in our list
                if (in_array($parentDomain, $availableDomains)) {
                    echo "    ✅ Found in available domains!\n";
                } else {
                    echo "    ❌ Not in available domains\n";
                }
                
                // Also test searchZones for this parent
                try {
                    $searchResult = $api->searchZones($parentDomain, 1, 3);
                    if (!empty($searchResult['result'])) {
                        echo "    👀 searchZones found: ";
                        foreach ($searchResult['result'] as $zone) {
                            echo $zone['name'] . " ";
                        }
                        echo "\n";
                    } else {
                        echo "    👀 searchZones: no results\n";
                    }
                } catch (Exception $e) {
                    echo "    👀 searchZones error: " . $e->getMessage() . "\n";
                }
            }
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>