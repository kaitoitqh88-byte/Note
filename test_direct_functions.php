<?php
/**
 * Test cache_ttl_bulk_update.php functions directly 
 */

require_once 'd:\Note\config.php';
require_once 'd:\Note\CloudflareAPI.php';

// Manually include functions from cache_ttl_bulk_update.php
function getZoneInfo($api, $domain) {
    try {
        // Clean domain name
        $cleanDomain = cleanDomainName($domain);
        if (empty($cleanDomain)) {
            return null;
        }
        
        // Strategy 1: Try exact match first using searchZones
        $zones = $api->searchZones($cleanDomain, 1, 10);
        
        if (!empty($zones['result']) && count($zones['result']) > 0) {
            // Check for exact match first
            foreach ($zones['result'] as $zone) {
                if (strtolower($zone['name']) === strtolower($cleanDomain)) {
                    return $zone;
                }
            }
        }
        
        // Strategy 2: For subdomains, try parent domains using searchZones
        $domainParts = explode('.', $cleanDomain);
        if (count($domainParts) > 2) {
            // Try removing subdomain(s) progressively
            for ($i = 1; $i < count($domainParts) - 1; $i++) {
                $parentDomain = implode('.', array_slice($domainParts, $i));
                
                try {
                    $parentSearch = $api->searchZones($parentDomain, 1, 5);
                    if (!empty($parentSearch['result'])) {
                        foreach ($parentSearch['result'] as $zone) {
                            if (strtolower($zone['name']) === strtolower($parentDomain)) {
                                return $zone;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // Continue to next parent level
                    continue;
                }
            }
        }
        
        // Strategy 3: Fallback to listZones for comprehensive search
        try {
            $allZones = $api->listZones(1, 100);
            if (!empty($allZones['result'])) {
                $cleanDomainLower = strtolower($cleanDomain);
                
                // First pass: exact match
                foreach ($allZones['result'] as $zone) {
                    if (strtolower($zone['name']) === $cleanDomainLower) {
                        return $zone;
                    }
                }
                
                // Second pass: find parent domain for subdomains
                if (count($domainParts) > 2) {
                    for ($i = 1; $i < count($domainParts) - 1; $i++) {
                        $parentDomain = strtolower(implode('.', array_slice($domainParts, $i)));
                        foreach ($allZones['result'] as $zone) {
                            if (strtolower($zone['name']) === $parentDomain) {
                                return $zone;
                            }
                        }
                    }
                }
            }
        } catch (Exception $e) {
            error_log("Error in listZones fallback for {$domain}: " . $e->getMessage());
        }
        
        return null;
    } catch (Exception $e) {
        error_log("Error getting zone info for {$domain}: " . $e->getMessage());
        return null;
    }
}

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

function checkDomainsInfo($api, $domainListText) {
    $results = [];
    
    // Parse domains
    $domains = array_filter(array_map('trim', explode("\n", $domainListText)), 'strlen');
    
    if (empty($domains)) {
        return $results;
    }
    
    // Get all zones list for similarity suggestions  
    $allZones = $api->listZones(1, 100);
    $allDomainsList = [];
    if ($allZones['success'] && isset($allZones['result'])) {
        foreach ($allZones['result'] as $zone) {
            $allDomainsList[] = $zone['name'];
        }
    }
    
    foreach ($domains as $domain) {
        $result = [
            'domain_name' => $domain,
            'zone_found' => false,
            'zone_info' => null,
            'current_cache_settings' => null,
            'success' => false,
            'error' => null
        ];
        
        try {
            // Use improved zone finding method
            $zoneInfo = getZoneInfo($api, $domain);
            
            if ($zoneInfo) {
                $result['zone_found'] = true;
                $result['zone_info'] = [
                    'id' => $zoneInfo['id'],
                    'name' => $zoneInfo['name'],
                    'status' => $zoneInfo['status'] ?? 'unknown',
                    'plan' => $zoneInfo['plan']['name'] ?? 'Unknown'
                ];
                $result['success'] = true;
            } else {
                // Domain not found - provide helpful error message with suggestions
                $suggestions = [];
                
                // Look for similar domains
                foreach ($allDomainsList as $zoneDomain) {
                    $similarity = 0;
                    similar_text($domain, $zoneDomain, $similarity);
                    if ($similarity > 60) { // 60% similarity threshold
                        $suggestions[] = $zoneDomain;
                    }
                }
                
                $errorMsg = "Domain '$domain' not found in Cloudflare account";
                
                if (!empty($suggestions)) {
                    $errorMsg .= ". Similar domains available: " . implode(', ', array_slice($suggestions, 0, 3));
                } else {
                    $errorMsg .= ". Please verify the domain is added to your Cloudflare account.";
                }
                
                $result['error'] = $errorMsg;
                $result['suggestions'] = $suggestions;
                $result['available_domains_count'] = count($allDomainsList);
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
        }
        
        $results[] = $result;
    }
    
    return $results;
}

echo "Testing cache_ttl_bulk_update.php functions directly:\n";
echo str_repeat('=', 50) . "\n\n";

try {
    $api = new CloudflareAPI();
    
    $domainList = "api.11bet.de.com\ntest.123win.name\nmb66ee.com\n11bet.de.com\nnonexistent.domain.com";
    
    echo "Testing domain list:\n";
    echo str_repeat('-', 30) . "\n";
    echo $domainList . "\n\n";
    
    $results = checkDomainsInfo($api, $domainList);
    
    echo "Results:\n";
    echo str_repeat('-', 30) . "\n";
    
    foreach ($results as $result) {
        echo "Domain: {$result['domain_name']}\n";
        if ($result['zone_found']) {
            echo "  ✅ FOUND: {$result['zone_info']['name']} (ID: {$result['zone_info']['id']})\n";
            echo "  Status: {$result['zone_info']['status']}, Plan: {$result['zone_info']['plan']}\n";
        } else {
            echo "  ❌ NOT FOUND\n";
            if (!empty($result['error'])) {
                echo "  Error: {$result['error']}\n";
            }
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>