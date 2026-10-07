<?php
/**
 * Test New API Pattern
 */

require_once 'd:\Note\config.php';
require_once 'd:\Note\CloudflareAPI.php';

/**
 * Get zone information for domain using searchZones API
 */
function getZoneInfo($api, $domain) {
    try {
        // Clean domain name
        $cleanDomain = cleanDomainName($domain);
        if (empty($cleanDomain)) {
            return null;
        }
        
        // Search for zones by domain name
        $zones = $api->searchZones($cleanDomain, 1, 10);
        
        if (!empty($zones['result']) && count($zones['result']) > 0) {
            // Check for exact match first
            foreach ($zones['result'] as $zone) {
                if (strtolower($zone['name']) === strtolower($cleanDomain)) {
                    return $zone;
                }
            }
            
            // Check for parent domain match (for subdomains)
            $domainParts = explode('.', $cleanDomain);
            if (count($domainParts) > 2) {
                // Try removing subdomain(s)
                for ($i = 1; $i < count($domainParts) - 1; $i++) {
                    $parentDomain = implode('.', array_slice($domainParts, $i));
                    foreach ($zones['result'] as $zone) {
                        if (strtolower($zone['name']) === strtolower($parentDomain)) {
                            return $zone;
                        }
                    }
                }
            }
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting zone info for {$domain}: " . $e->getMessage());
        return null;
    }
}

/**
 * Clean domain name by removing http/https and path
 */
function cleanDomainName($domain) {
    // Remove extra whitespace
    $domain = trim($domain);
    
    // Remove protocol (http:// or https://, ftp://, etc.)
    $domain = preg_replace('/^[a-zA-Z]+:\/\//', '', $domain);
    
    // Remove trailing slash and everything after it (path, query, fragment)
    $domain = preg_replace('/\/.*$/', '', $domain);
    
    // Remove port number (e.g., :8080, :443)
    $domain = preg_replace('/:.*$/', '', $domain);
    
    // Convert to lowercase
    $domain = strtolower(trim($domain));
    
    // Basic domain validation - must contain at least one dot and valid characters
    if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
        return '';
    }
    
    // Remove leading/trailing dots
    $domain = trim($domain, '.');
    
    return $domain;
}

/**
 * Search for domains in Cloudflare account by keyword
 */
function searchDomainsByKeyword($api, $keyword) {
    $matchingDomains = [];
    
    if (empty($keyword)) {
        // No keyword - return all domains (with pagination)
        $allZones = $api->listZones(1, 100);
        if ($allZones['success'] && isset($allZones['result'])) {
            foreach ($allZones['result'] as $zone) {
                $matchingDomains[] = [
                    'domain' => $zone['name'],
                    'match_type' => 'all',
                    'status' => $zone['status'] ?? 'unknown',
                    'plan' => $zone['plan']['name'] ?? 'Unknown',
                    'similarity' => 100
                ];
            }
        }
        return $matchingDomains;
    }
    
    // Strategy 1: Try exact domain search first using searchZones
    try {
        $exactSearch = $api->searchZones($keyword, 1, 10);
        if ($exactSearch['success'] && !empty($exactSearch['result'])) {
            foreach ($exactSearch['result'] as $zone) {
                $domainName = strtolower($zone['name']);
                $keywordLower = strtolower($keyword);
                
                if ($domainName === $keywordLower) {
                    $matchingDomains[] = [
                        'domain' => $zone['name'],
                        'match_type' => 'exact',
                        'status' => $zone['status'] ?? 'unknown',
                        'plan' => $zone['plan']['name'] ?? 'Unknown',
                        'similarity' => 100
                    ];
                }
            }
        }
    } catch (Exception $e) {
        // Continue to fallback method
    }
    
    // Strategy 2: Use listZones for keyword/pattern matching
    try {
        $allZones = $api->listZones(1, 100);
        if ($allZones['success'] && isset($allZones['result'])) {
            $keyword = strtolower($keyword);
            $exactDomainNames = array_column($matchingDomains, 'domain'); // Avoid duplicates
            
            foreach ($allZones['result'] as $zone) {
                $domainName = strtolower($zone['name']);
                $originalDomainName = $zone['name'];
                
                // Skip if already found in exact search
                if (in_array($originalDomainName, $exactDomainNames)) {
                    continue;
                }
                
                // Contains keyword
                if (strpos($domainName, $keyword) !== false) {
                    $matchingDomains[] = [
                        'domain' => $zone['name'],
                        'match_type' => 'contains',
                        'status' => $zone['status'] ?? 'unknown',
                        'plan' => $zone['plan']['name'] ?? 'Unknown',
                        'similarity' => 90
                    ];
                }
                // Similar text (fuzzy matching)
                else {
                    $similarity = 0;
                    similar_text($keyword, $domainName, $similarity);
                    if ($similarity > 60) {
                        $matchingDomains[] = [
                            'domain' => $zone['name'],
                            'match_type' => 'similar',
                            'status' => $zone['status'] ?? 'unknown',
                            'plan' => $zone['plan']['name'] ?? 'Unknown',
                            'similarity' => round($similarity)
                        ];
                    }
                }
            }
        }
    } catch (Exception $e) {
        error_log("Error in listZones fallback: " . $e->getMessage());
    }
    
    // Sort by similarity (highest first)
    usort($matchingDomains, function($a, $b) {
        return $b['similarity'] - $a['similarity'];
    });
    
    return array_slice($matchingDomains, 0, 10); // Return top 10 matches
}

echo "Testing new API pattern...\n";
echo "========================\n\n";

try {
    $api = new CloudflareAPI();
    echo "✅ CloudflareAPI initialized\n\n";
    
    // Test getZoneInfo function
    $testDomains = [
        '11bet.de.com',      // Should be found (exact match)
        'api.11bet.de.com',  // Should find parent domain (11bet.de.com)
        'mb66ee.com',        // Should not be found
        'test.123win.name'   // Should find parent domain (123win.name)
    ];
    
    foreach ($testDomains as $domain) {
        echo "Testing domain: $domain\n";
        echo str_repeat('-', 30) . "\n";
        
        $zoneInfo = getZoneInfo($api, $domain);
        if ($zoneInfo) {
            echo "  ✅ Found: {$zoneInfo['name']}\n";
            echo "  Zone ID: {$zoneInfo['id']}\n";
            echo "  Status: " . ($zoneInfo['status'] ?? 'unknown') . "\n";
            echo "  Plan: " . ($zoneInfo['plan']['name'] ?? 'Unknown') . "\n";
        } else {
            echo "  ❌ Not found in Cloudflare account\n";
        }
        echo "\n";
    }
    
    echo "\n" . str_repeat('=', 50) . "\n";
    echo "Testing Domain Search with searchZones API\n";
    echo str_repeat('=', 50) . "\n";
    
    // Test new search function
    $keywords = ['bet', 'win', 'mb66'];
    foreach ($keywords as $keyword) {
        echo "\nSearching for: '$keyword'\n";
        echo str_repeat('-', 20) . "\n";
        
        $results = searchDomainsByKeyword($api, $keyword);
        if (!empty($results)) {
            echo "Found " . count($results) . " matches:\n";
            foreach (array_slice($results, 0, 3) as $result) {
                echo "  - {$result['domain']} ({$result['match_type']}, {$result['similarity']}% match)\n";
            }
        } else {
            echo "No matches found for '$keyword'\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>