<?php
/**
 * Debug getZoneInfo function step by step
 */

require_once 'd:\Note\config.php';
require_once 'd:\Note\CloudflareAPI.php';

// Copy getZoneInfo function with debug output
function debugGetZoneInfo($api, $domain) {
    echo "\n🔍 DEBUG: getZoneInfo for '$domain'\n";
    echo str_repeat('-', 40) . "\n";
    
    try {
        // Clean domain name
        $cleanDomain = trim($domain);
        $cleanDomain = preg_replace('/^[a-zA-Z]+:\/\//', '', $cleanDomain);
        $cleanDomain = preg_replace('/\/.*$/', '', $cleanDomain);
        $cleanDomain = preg_replace('/:.*$/', '', $cleanDomain);
        $cleanDomain = strtolower(trim($cleanDomain));
        if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $cleanDomain)) {
            echo "❌ Invalid domain format\n";
            return null;
        }
        $cleanDomain = trim($cleanDomain, '.');
        
        echo "✅ Cleaned domain: '$cleanDomain'\n";
        
        // Strategy 1: Try exact match first using searchZones
        echo "\n📍 Strategy 1: Exact match with searchZones\n";
        try {
            $zones = $api->searchZones($cleanDomain, 1, 10);
            echo "searchZones returned: " . (isset($zones['result']) ? count($zones['result']) : 0) . " results\n";
            
            if (!empty($zones['result']) && count($zones['result']) > 0) {
                foreach ($zones['result'] as $zone) {
                    echo "Checking zone: {$zone['name']} vs $cleanDomain\n";
                    if (strtolower($zone['name']) === strtolower($cleanDomain)) {
                        echo "✅ Found exact match!\n";
                        return $zone;
                    }
                }
                echo "❌ No exact match found\n";
            }
        } catch (Exception $e) {
            echo "❌ Strategy 1 error: " . $e->getMessage() . "\n";
        }
        
        // Strategy 2: For subdomains, try parent domains using searchZones
        echo "\n📍 Strategy 2: Parent domain search\n";
        $domainParts = explode('.', $cleanDomain);
        echo "Domain parts: " . implode(' | ', $domainParts) . "\n";
        
        if (count($domainParts) > 2) {
            echo "Trying parent domains:\n";
            for ($i = 1; $i < count($domainParts) - 1; $i++) {
                $parentDomain = implode('.', array_slice($domainParts, $i));
                echo "  Level $i: Trying parent '$parentDomain'\n";
                
                try {
                    $parentSearch = $api->searchZones($parentDomain, 1, 5);
                    echo "    searchZones for parent returned: " . (isset($parentSearch['result']) ? count($parentSearch['result']) : 0) . " results\n";
                    
                    if (!empty($parentSearch['result'])) {
                        foreach ($parentSearch['result'] as $zone) {
                            echo "    Checking parent zone: {$zone['name']} vs $parentDomain\n";
                            if (strtolower($zone['name']) === strtolower($parentDomain)) {
                                echo "    ✅ Found parent match!\n";
                                return $zone;
                            }
                        }
                        echo "    ❌ No parent match in results\n";
                    } else {
                        echo "    ❌ No results from searchZones\n";
                    }
                } catch (Exception $e) {
                    echo "    ❌ Parent search error: " . $e->getMessage() . "\n";
                    continue;
                }
            }
        } else {
            echo "Not a subdomain (only " . count($domainParts) . " parts)\n";
        }
        
        // Strategy 3: Fallback to listZones
        echo "\n📍 Strategy 3: listZones fallback\n";
        try {
            $allZones = $api->listZones(1, 100);
            if (!empty($allZones['result'])) {
                echo "listZones returned: " . count($allZones['result']) . " domains\n";
                $cleanDomainLower = strtolower($cleanDomain);
                
                // First pass: exact match
                echo "First pass: Looking for exact match '$cleanDomainLower'\n";
                foreach ($allZones['result'] as $zone) {
                    if (strtolower($zone['name']) === $cleanDomainLower) {
                        echo "✅ Found exact match in listZones!\n";
                        return $zone;
                    }
                }
                echo "❌ No exact match in listZones\n";
                
                // Second pass: find parent domain for subdomains
                if (count($domainParts) > 2) {
                    echo "Second pass: Looking for parent domains\n";
                    for ($i = 1; $i < count($domainParts) - 1; $i++) {
                        $parentDomain = strtolower(implode('.', array_slice($domainParts, $i)));
                        echo "  Looking for parent '$parentDomain'\n";
                        foreach ($allZones['result'] as $zone) {
                            if (strtolower($zone['name']) === $parentDomain) {
                                echo "  ✅ Found parent '$parentDomain'!\n";
                                return $zone;
                            }
                        }
                        echo "  ❌ Parent '$parentDomain' not found\n";
                    }
                }
            }
        } catch (Exception $e) {
            echo "❌ Strategy 3 error: " . $e->getMessage() . "\n";
        }
        
        echo "\n❌ All strategies failed\n";
        return null;
    } catch (Exception $e) {
        echo "❌ Overall error: " . $e->getMessage() . "\n";
        return null;
    }
}

try {
    $api = new CloudflareAPI();
    
    // Test the problematic cases
    $testCases = ['api.11bet.de.com', 'test.123win.name'];
    
    foreach ($testCases as $testDomain) {
        $result = debugGetZoneInfo($api, $testDomain);
        if ($result) {
            echo "\n🎉 Final result: {$result['name']} (ID: {$result['id']})\n";
        } else {
            echo "\n❌ Final result: NULL\n";
        }
        echo "\n" . str_repeat('=', 50) . "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>