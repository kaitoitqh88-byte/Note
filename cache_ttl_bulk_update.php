<?php
/**
 * Browser Cache TTL Bulk Update - Cập nhật Browser Cache TTL cho danh sách domain
 */

require_once 'config.php';
require_once 'CloudflareAPI.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (ob_get_level()) ob_clean();
    
    // Add error logging for debugging
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/error.log');
    
    try {
        $action = $_POST['action'] ?? '';
        
        // Debug log
        error_log("Action: " . $action . ", POST data: " . json_encode($_POST));
        
        if (empty($action)) {
            throw new Exception('Missing action parameter');
        }
        
        try {
            $cloudflareAPI = new CloudflareAPI();
        } catch (Exception $e) {
            error_log("CloudflareAPI initialization failed: " . $e->getMessage());
            
            // Check if it's a connectivity issue - offer demo mode
            if (strpos($e->getMessage(), 'Network connectivity') !== false || 
                strpos($e->getMessage(), 'timed out') !== false) {
                
                echo json_encode([
                    'success' => false,
                    'error' => 'Network connectivity issue detected',
                    'message' => 'Cannot connect to Cloudflare API. This could be due to firewall, proxy, or internet connection issues.',
                    'suggestion' => 'Try checking your network connection or testing when online.',
                    'demo_mode' => true,
                    'debug_info' => [
                        'token_file_exists' => file_exists(__DIR__ . '/token.txt'),
                        'email_defined' => defined('CLOUDFLARE_EMAIL'),
                        'token_defined' => defined('CLOUDFLARE_API_TOKEN'),
                        'token_length' => defined('CLOUDFLARE_API_TOKEN') ? strlen(CLOUDFLARE_API_TOKEN) : 0
                    ]
                ]);
            } else {
                echo json_encode([
                    'success' => false,
                    'error' => 'Configuration Error: ' . $e->getMessage(),
                    'debug_info' => [
                        'token_file_exists' => file_exists(__DIR__ . '/token.txt'),
                        'email_defined' => defined('CLOUDFLARE_EMAIL'),
                        'token_defined' => defined('CLOUDFLARE_API_TOKEN'),
                        'token_empty' => empty(CLOUDFLARE_API_TOKEN)
                    ]
                ]);
            }
            exit;
        }
        
        switch ($action) {
            case 'get_zones':
                try {
                    $zones = $cloudflareAPI->listZones(1, 100);
                    echo json_encode([
                        'success' => true,
                        'zones' => $zones['result'] ?? []
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Failed to fetch zones: ' . $e->getMessage(),
                        'network_issue' => strpos($e->getMessage(), 'connectivity') !== false
                    ]);
                }
                break;
                
            case 'check_domains':
                try {
                    $domainList = $_POST['domain_list'] ?? '';
                    $results = checkDomainsInfo($cloudflareAPI, $domainList);
                    echo json_encode([
                        'success' => true,
                        'results' => $results
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Domain check failed: ' . $e->getMessage(),
                        'network_issue' => strpos($e->getMessage(), 'connectivity') !== false
                    ]);
                }
                break;
                
            case 'bulk_update_cache_ttl':
                try {
                    $domainList = $_POST['domain_list'] ?? '';
                    $browserTTL = $_POST['browser_ttl'] ?? 86400;
                    $edgeTTL = $_POST['edge_ttl'] ?? '';
                    $cacheLevel = $_POST['cache_level'] ?? '';
                    
                    $results = bulkUpdateCacheTTL($cloudflareAPI, $domainList, $browserTTL, $edgeTTL, $cacheLevel);
                    echo json_encode([
                        'success' => true,
                        'results' => $results
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Cache update failed: ' . $e->getMessage(),
                        'network_issue' => strpos($e->getMessage(), 'connectivity') !== false
                    ]);
                }
                break;

            case 'search_domain':
                try {
                    $keyword = $_POST['keyword'] ?? '';
                    $results = searchDomainsByKeyword($cloudflareAPI, $keyword);
                    echo json_encode([
                        'success' => true,
                        'search_keyword' => $keyword,
                        'results' => $results
                    ]);
                } catch (Exception $e) {
                    echo json_encode([
                        'success' => false,
                        'error' => 'Domain search failed: ' . $e->getMessage(),
                        'network_issue' => strpos($e->getMessage(), 'connectivity') !== false
                    ]);
                }
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

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
 * Check domains information and current cache settings
 */
function checkDomainsInfo($api, $domainListText) {
    $results = [];
    
    // Parse and validate domain list
    $domains = parseAndValidateDomains($domainListText);
    
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
                // Zone found - proceed with cache settings retrieval
                $result['zone_found'] = true;
                $result['zone_info'] = [
                    'id' => $zoneInfo['id'],
                    'name' => $zoneInfo['name'],
                    'status' => $zoneInfo['status'] ?? 'unknown',
                    'plan' => $zoneInfo['plan']['name'] ?? 'Unknown'
                ];
                
                // Show info if domain was matched via parent domain
                if (strtolower($zoneInfo['name']) !== strtolower($domain)) {
                    $result['match_info'] = "Subdomain '$domain' matched via parent domain '{$zoneInfo['name']}'";
                }
                
                $zoneId = $zoneInfo['id'];
                
                // Get current cache settings
                $cacheSettings = [];
                
                // Get Browser Cache TTL
                try {
                    $browserTTL = $api->getBrowserCacheTTL($zoneId);
                    if ($browserTTL['success'] && isset($browserTTL['result'])) {
                        $ttlValue = $browserTTL['result']['value'] ?? 14400;
                        $cacheSettings['browser_cache_ttl'] = [
                            'value' => $ttlValue,
                            'human_readable' => formatTTLToHuman($ttlValue)
                        ];
                    } else {
                        $cacheSettings['browser_cache_ttl'] = ['error' => 'Unable to retrieve browser cache TTL'];
                    }
                } catch (Exception $e) {
                    $cacheSettings['browser_cache_ttl'] = ['error' => $e->getMessage()];
                }
                
                // Get Edge Cache TTL
                try {
                    $edgeTTL = $api->getEdgeCacheTTL($zoneId);
                    if ($edgeTTL['success'] && isset($edgeTTL['result'])) {
                        $ttlValue = $edgeTTL['result']['value'] ?? 0;
                        $cacheSettings['edge_cache_ttl'] = [
                            'value' => $ttlValue,
                            'human_readable' => formatTTLToHuman($ttlValue)
                        ];
                    } else {
                        $cacheSettings['edge_cache_ttl'] = ['error' => 'Unable to retrieve edge cache TTL'];
                    }
                } catch (Exception $e) {
                    $cacheSettings['edge_cache_ttl'] = ['error' => $e->getMessage()];
                }
                
                // Get Cache Level
                try {
                    $cacheLevel = $api->getCacheLevel($zoneId);
                    if ($cacheLevel['success'] && isset($cacheLevel['result'])) {
                        $cacheSettings['cache_level'] = [
                            'value' => $cacheLevel['result']['value'] ?? 'basic'
                        ];
                    } else {
                        $cacheSettings['cache_level'] = ['error' => 'Unable to retrieve cache level'];
                    }
                } catch (Exception $e) {
                    $cacheSettings['cache_level'] = ['error' => $e->getMessage()];
                }
                
                $result['current_cache_settings'] = $cacheSettings;
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
                
                // Look for domains with similar TLD
                $domainParts = explode('.', $domain);
                $inputTLD = end($domainParts);
                foreach ($allDomainsList as $zoneDomain) {
                    $zoneParts = explode('.', $zoneDomain);
                    $zoneTLD = end($zoneParts);
                    if ($zoneTLD === $inputTLD && !in_array($zoneDomain, $suggestions)) {
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

/**
 * Parse and validate domain list - removes duplicates and invalid domains
 */
function parseAndValidateDomains($domainListText) {
    if (empty($domainListText)) {
        return [];
    }
    
    $rawDomains = explode("\n", $domainListText);
    $validDomains = [];
    
    foreach ($rawDomains as $domain) {
        // Clean and normalize domain
        $domain = strtolower(trim($domain));
        
        // Remove protocol if present
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        
        // Remove trailing slash and path
        $domain = preg_replace('/\/.*$/', '', $domain);
        
        // Remove www prefix for consistency
        $domain = preg_replace('/^www\./', '', $domain);
        
        // Skip empty domains
        if (empty($domain)) {
            continue;
        }
        
        // Basic domain validation
        if (isValidDomain($domain)) {
            // Avoid duplicates
            if (!in_array($domain, $validDomains)) {
                $validDomains[] = $domain;
            }
        }
    }
    
    return $validDomains;
}

/**
 * Format TTL seconds to human readable format
 */
function formatTTLToHuman($seconds) {
    if ($seconds == 0) {
        return 'Respect existing headers';
    } elseif ($seconds < 60) {
        return $seconds . ' seconds';
    } elseif ($seconds < 3600) {
        return round($seconds / 60) . ' minutes';
    } elseif ($seconds < 86400) {
        return round($seconds / 3600) . ' hours';
    } elseif ($seconds < 2592000) {
        return round($seconds / 86400) . ' days';
    } elseif ($seconds < 31536000) {
        return round($seconds / 2592000) . ' months';
    } else {
        return round($seconds / 31536000) . ' years';
    }
}

/**
 * Validate if string is a proper domain format
 */
function isValidDomain($domain) {
    // Check basic domain format
    if (!preg_match('/^[a-z0-9]([a-z0-9\-\.]*[a-z0-9])?\.[a-z]{2,}$/i', $domain)) {
        return false;
    }
    
    // Check if domain has at least one dot
    if (strpos($domain, '.') === false) {
        return false;
    }
    
    // Check domain length
    if (strlen($domain) > 253 || strlen($domain) < 3) {
        return false;
    }
    
    // Check individual label lengths
    $labels = explode('.', $domain);
    foreach ($labels as $label) {
        if (strlen($label) > 63 || strlen($label) < 1) {
            return false;
        }
    }
    
    return true;
}

/**
 * Bulk update cache TTL for multiple domains from domain list
 */
function bulkUpdateCacheTTL($api, $domainListText, $browserTTL, $edgeTTL = null, $cacheLevel = null) {
    $results = [];
    
    // Parse and validate domain list
    $domains = parseAndValidateDomains($domainListText);
    
    if (empty($domains) || empty($browserTTL)) {
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
            'browser_ttl_update' => null,
            'edge_ttl_update' => null,
            'cache_level_update' => null,
            'success' => false,
            'error' => null
        ];
        
        try {
            // Use improved zone finding method
            $zoneInfo = getZoneInfo($api, $domain);
            
            if ($zoneInfo) {
                // Zone found - proceed with cache update
                $result['zone_found'] = true;
                $zoneId = $zoneInfo['id'];
                
                // Show info if domain was matched via parent domain
                if (strtolower($zoneInfo['name']) !== strtolower($domain)) {
                    $result['match_info'] = "Subdomain '$domain' matched via parent domain '{$zoneInfo['name']}'";
                }
                
                $cacheSettings = [];
                
                // Update Browser Cache TTL
                if (!empty($browserTTL)) {
                    $cacheSettings['browser_cache_ttl'] = intval($browserTTL);
                }
                
                // Update Edge Cache TTL if provided
                if (!empty($edgeTTL)) {
                    $cacheSettings['edge_cache_ttl'] = intval($edgeTTL);
                }
                
                // Update Cache Level if provided
                if (!empty($cacheLevel)) {
                    $cacheSettings['cache_level'] = $cacheLevel;
                }
                
                // Apply cache settings
                $cacheResult = $api->configureCacheSettingsBulk($zoneId, $cacheSettings);
                
                $result['browser_ttl_update'] = [
                    'success' => $cacheResult['results']['browser_cache_ttl'] ?? false,
                    'value' => $browserTTL,
                    'human_readable' => formatTTLToHuman($browserTTL)
                ];
                
                if (!empty($edgeTTL)) {
                    $result['edge_ttl_update'] = [
                        'success' => $cacheResult['results']['edge_cache_ttl'] ?? false,
                        'value' => $edgeTTL,
                        'human_readable' => formatTTLToHuman($edgeTTL)
                    ];
                }
                
                if (!empty($cacheLevel)) {
                    $result['cache_level_update'] = [
                        'success' => $cacheResult['results']['cache_level'] ?? false,
                        'value' => $cacheLevel
                    ];
                }
                
                // Overall success if at least browser TTL was updated
                $result['success'] = $result['browser_ttl_update']['success'] ?? false;
                
                if (!$result['success'] && !empty($cacheResult['errors'])) {
                    $result['error'] = implode(', ', $cacheResult['errors']);
                }
                
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
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Browser Cache TTL Bulk Update</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/rpg-shooter-theme.css" rel="stylesheet">
    <link href="assets/css/sidebar-navigation.css" rel="stylesheet">
</head>
<body>
    <?php 
    $currentPage = 'cache-ttl-bulk';
    include 'includes/main_navigation.php'; 
    ?>

    <div class="main-wrapper">
        <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-12 text-center">
                <h1><i class="fas fa-clock me-2"></i>Browser Cache TTL Bulk Update</h1>
                <p class="lead text-muted">Cập nhật Browser Cache TTL cho nhiều domain cùng lúc</p>
            </div>
        </div>

        <!-- Cache Settings Form -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="cache-form">
                    <h4 class="text-center mb-4">
                        <i class="fas fa-cog me-2"></i>Cấu hình Cache TTL Settings
                    </h4>
                    
                    <form id="cacheForm">
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-clock me-1"></i>Browser Cache TTL
                                </label>
                                <select class="form-select form-select-lg" id="browserTTL" name="browser_ttl" required>
                                    <option value="">Chọn thời gian cache</option>
                                    <option value="3600">1 hour (Blog/News)</option>
                                    <option value="86400" selected>1 day (Recommended)</option>
                                    <option value="259200">3 days</option>
                                    <option value="604800">1 week</option>
                                    <option value="2678400">1 month (Static)</option>
                                    <option value="31536000">1 year (Assets)</option>
                                </select>
                                <div class="form-text text-light">
                                    <small>Thời gian cache tại browser người dùng</small>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-server me-1"></i>Edge Cache TTL (Optional)
                                </label>
                                <select class="form-select form-select-lg" id="edgeTTL" name="edge_ttl">
                                    <option value="">Không thay đổi</option>
                                    <option value="7200">2 hours</option>
                                    <option value="86400">1 day</option>
                                    <option value="259200">3 days</option>
                                    <option value="604800">1 week</option>
                                </select>
                                <div class="form-text text-light">
                                    <small>Thời gian cache tại Cloudflare Edge</small>
                                </div>
                            </div>
                            
                            <div class="col-md-4 mb-3">
                                <label class="form-label">
                                    <i class="fas fa-layer-group me-1"></i>Cache Level (Optional)
                                </label>
                                <select class="form-select form-select-lg" id="cacheLevel" name="cache_level">
                                    <option value="">Không thay đổi</option>
                                    <option value="aggressive">Aggressive</option>
                                    <option value="basic">Basic</option>
                                    <option value="simplified">Simplified</option>
                                </select>
                                <div class="form-text text-light">
                                    <small>Mức độ cache của Cloudflare</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-center mt-4">
                            <button type="submit" class="btn btn-light btn-lg px-5" id="updateBtn">
                                <i class="fas fa-clock me-2"></i>Cập nhật Cache TTL
                            </button>
                        </div>
                    </form>
                    
                </div>
            </div>
        </div>

        <!-- Domain List Input -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-list me-2"></i>Danh sách Domains</h5>
                        <div>
                            <button class="btn btn-outline-secondary btn-sm" onclick="clearDomainList()">
                                <i class="fas fa-trash me-2"></i>Xóa tất cả
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-12">
                                <label class="form-label">Nhập danh sách domains (mỗi domain một dòng)</label>
                                <textarea class="form-control" id="domainList" name="domain_list" rows="10" 
                                          placeholder="example1.com&#10;example2.com&#10;example3.com&#10;..."></textarea>
                                <div class="form-text mt-2">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Nhập mỗi domain trên một dòng. Hệ thống sẽ tự động tìm và cập nhật cache TTL cho các domain có trong Cloudflare account.
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12">
                                <div class="alert alert-info" id="domainCounter" style="display: none;">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <span id="domainCount">0 domain đã nhập</span>
                                </div>
                            </div>
                        </div>
                        <div class="row mt-3">
                            <div class="col-12 text-center">
                                <button type="button" class="btn btn-outline-primary btn-lg me-3" id="checkDomainsBtn" onclick="checkDomains()">
                                    <i class="fas fa-search me-2"></i>Check Domains Info
                                </button>
                                <small class="text-muted d-block mt-2">
                                    Kiểm tra domains và xem cache settings hiện tại trước khi cập nhật
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Domain Check Results Section -->
        <div class="row mb-4" id="domainCheckSection" style="display: none;">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">
                            <i class="fas fa-info-circle me-2"></i>
                            Thông tin Domains & Cache Settings
                        </h5>
                        <button class="btn btn-sm btn-outline-light" onclick="hideDomainCheck()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="card-body" id="domainCheckContent">
                        <!-- Domain check results will be populated here -->
                    </div>
                </div>
            </div>
        </div>

        <!-- Progress Container -->
        <div class="alert alert-info" id="progressContainer" style="display: none;">
            <h5><i class="fas fa-clock fa-spin me-2"></i>Đang cập nhật Cache TTL...</h5>
            <div class="progress mb-2">
                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                     id="progressBar" style="width: 0%"></div>
            </div>
            <div id="progressText">0 / 0 domains đã xử lý</div>
        </div>

        <!-- Results Section -->
        <div class="row" id="resultsSection" style="display: none;">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">
                            <i class="fas fa-check-circle me-2"></i>
                            Kết quả cập nhật Cache TTL
                        </h5>
                    </div>
                    <div class="card-body" id="resultsContent">
                        <!-- Results will be populated here -->
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let domainCount = 0;

        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Update button click handler
            document.getElementById('cacheForm').addEventListener('submit', function(e) {
                e.preventDefault();
                startBulkUpdate();
            });
            
            // Form validation
            document.getElementById('browserTTL').addEventListener('change', validateForm);
            document.getElementById('domainList').addEventListener('input', function() {
                updateDomainCounter();
                validateForm();
                // Hide domain check results when domain list changes
                hideDomainCheck();
            });
            
            validateForm();
        });

        function updateDomainCounter() {
            const domainList = document.getElementById('domainList').value.trim();
            const rawDomains = domainList ? domainList.split('\n').filter(domain => domain.trim()) : [];
            
            // Validate and clean domains
            const domains = [];
            const invalidDomains = [];
            
            rawDomains.forEach(domain => {
                domain = domain.trim().toLowerCase();
                
                // Remove protocol if present
                domain = domain.replace(/^https?:\/\//, '');
                
                // Remove trailing slash and path
                domain = domain.replace(/\/.*$/, '');
                
                // Remove www prefix for consistency
                domain = domain.replace(/^www\./, '');
                
                if (domain && isValidDomainFormat(domain)) {
                    if (!domains.includes(domain)) {
                        domains.push(domain);
                    }
                } else if (domain) {
                    invalidDomains.push(domain);
                }
            });
            
            domainCount = domains.length;
            
            const counter = document.getElementById('domainCounter');
            const countSpan = document.getElementById('domainCount');
            
            if (domainCount > 0) {
                counter.style.display = 'block';
                counter.className = 'alert alert-info';
                
                let message = `${domainCount} domain hợp lệ đã nhập`;
                if (invalidDomains.length > 0) {
                    message += ` (${invalidDomains.length} domain không hợp lệ đã bỏ qua)`;
                    counter.className = 'alert alert-warning';
                }
                
                countSpan.innerHTML = message;
            } else {
                if (invalidDomains.length > 0) {
                    counter.style.display = 'block';
                    counter.className = 'alert alert-danger';
                    countSpan.innerHTML = `${invalidDomains.length} domain không hợp lệ. Vui lòng kiểm tra lại định dạng.`;
                } else {
                    counter.style.display = 'none';
                }
            }
        }
        
        function isValidDomainFormat(domain) {
            // Basic domain validation
            const domainRegex = /^[a-z0-9]([a-z0-9\-\.]*[a-z0-9])?\.[a-z]{2,}$/i;
            
            if (!domainRegex.test(domain)) {
                return false;
            }
            
            // Check if domain has at least one dot
            if (domain.indexOf('.') === -1) {
                return false;
            }
            
            // Check domain length
            if (domain.length > 253 || domain.length < 3) {
                return false;
            }
            
            // Check individual label lengths
            const labels = domain.split('.');
            for (let label of labels) {
                if (label.length > 63 || label.length < 1) {
                    return false;
                }
            }
            
            return true;
        }

        function clearDomainList() {
            document.getElementById('domainList').value = '';
            updateDomainCounter();
            validateForm();
        }

        function validateForm() {
            const browserTTL = document.getElementById('browserTTL').value;
            const domainList = document.getElementById('domainList').value.trim();
            const updateBtn = document.getElementById('updateBtn');
            const checkBtn = document.getElementById('checkDomainsBtn');
            
            const hasTTL = browserTTL.length > 0;
            const hasDomains = domainList.length > 0;
            
            updateBtn.disabled = !(hasTTL && hasDomains);
            checkBtn.disabled = !hasDomains;
        }

        function checkDomains() {
            const domainList = document.getElementById('domainList').value.trim();
            
            if (!domainList) {
                alert('Vui lòng nhập danh sách domains trước');
                return;
            }
            
            const formData = new FormData();
            formData.append('action', 'check_domains');
            formData.append('domain_list', domainList);
            
            // Show loading state
            const checkBtn = document.getElementById('checkDomainsBtn');
            const originalText = checkBtn.innerHTML;
            checkBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Checking...';
            checkBtn.disabled = true;
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                checkBtn.innerHTML = originalText;
                checkBtn.disabled = false;
                
                if (data.success) {
                    displayDomainCheckResults(data.results);
                } else {
                    alert('Lỗi: ' + (data.error || 'Không thể check domains'));
                }
            })
            .catch(error => {
                checkBtn.innerHTML = originalText;
                checkBtn.disabled = false;
                alert('Lỗi kết nối: ' + error.message);
            });
        }

        function hideDomainCheck() {
            document.getElementById('domainCheckSection').style.display = 'none';
        }

        function displayDomainCheckResults(results) {
            const domainCheckSection = document.getElementById('domainCheckSection');
            const domainCheckContent = document.getElementById('domainCheckContent');
            
            let foundCount = 0;
            let notFoundCount = 0;
            
            const resultsHtml = results.map(result => {
                let cardClass = 'domain-info-card';
                let statusIcon = 'fa-times-circle text-danger';
                let statusText = 'Không tìm thấy';
                
                if (result.zone_found) {
                    cardClass += ' found';
                    statusIcon = 'fa-check-circle text-success';
                    statusText = 'Tìm thấy';
                    foundCount++;
                } else {
                    cardClass += ' not-found';
                    notFoundCount++;
                }
                
                return `
                    <div class="${cardClass}">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <div>
                                <h6 class="mb-1">
                                    <i class="fas ${statusIcon} me-2"></i>
                                    ${result.domain_name}
                                </h6>
                                <small class="text-muted">${statusText}</small>
                            </div>
                            ${result.zone_info ? `
                                <div class="text-end">
                                    <span class="badge bg-info">${result.zone_info.plan}</span>
                                    <span class="badge bg-${result.zone_info.status === 'active' ? 'success' : 'warning'}">${result.zone_info.status}</span>
                                </div>
                            ` : ''}
                        </div>
                        
                        ${result.zone_found && result.current_cache_settings ? `
                            <div class="row">
                                ${result.current_cache_settings.browser_cache_ttl ? `
                                    <div class="col-md-4">
                                        <div class="cache-setting-item">
                                            <small class="text-muted d-block">Browser Cache TTL</small>
                                            <strong class="text-primary">
                                                ${result.current_cache_settings.browser_cache_ttl.human_readable || 'N/A'}
                                            </strong>
                                        </div>
                                    </div>
                                ` : ''}
                                ${result.current_cache_settings.edge_cache_ttl ? `
                                    <div class="col-md-4">
                                        <div class="cache-setting-item">
                                            <small class="text-muted d-block">Edge Cache TTL</small>
                                            <strong class="text-info">
                                                ${result.current_cache_settings.edge_cache_ttl.human_readable || 'N/A'}
                                            </strong>
                                        </div>
                                    </div>
                                ` : ''}
                                ${result.current_cache_settings.cache_level ? `
                                    <div class="col-md-4">
                                        <div class="cache-setting-item">
                                            <small class="text-muted d-block">Cache Level</small>
                                            <strong class="text-warning">
                                                ${result.current_cache_settings.cache_level.value || 'N/A'}
                                            </strong>
                                        </div>
                                    </div>
                                ` : ''}
                            </div>
                        ` : ''}
                        
                        ${result.error ? `
                            <div class="mt-2">
                                <small class="text-danger">
                                    <i class="fas fa-exclamation-triangle me-1"></i>
                                    ${result.error}
                                </small>
                            </div>
                        ` : ''}
                        
                        ${!result.zone_found && !result.error ? `
                            <div class="mt-2">
                                <small class="text-warning">
                                    <i class="fas fa-info-circle me-1"></i>
                                    Domain này chưa được thêm vào tài khoản Cloudflare hoặc không có quyền truy cập.
                                </small>
                            </div>
                        ` : ''}
                    </div>
                `;
            }).join('');
            
            const summaryHtml = `
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-center border-success">
                            <div class="card-body">
                                <h5 class="text-success">${foundCount}</h5>
                                <small>Tìm thấy</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center border-danger">
                            <div class="card-body">
                                <h5 class="text-danger">${notFoundCount}</h5>
                                <small>Không tìm thấy</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center border-primary">
                            <div class="card-body">
                                <h5 class="text-primary">${results.length}</h5>
                                <small>Tổng cộng</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                ${foundCount > 0 ? `
                    <div class="alert alert-success">
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>${foundCount}</strong> domain được tìm thấy và sẵn sàng để cập nhật cache settings.
                    </div>
                ` : ''}
                
                ${notFoundCount > 0 ? `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>${notFoundCount}</strong> domain không tìm thấy trong Cloudflare account.
                    </div>
                ` : ''}
                
                <div class="mb-3">
                    ${resultsHtml}
                </div>
                
                ${foundCount > 0 ? `
                    <div class="text-center">
                        <button onclick="hideDomainCheck(); document.getElementById('cacheForm').scrollIntoView({behavior: 'smooth'})" class="btn btn-success btn-lg">
                            <i class="fas fa-arrow-up me-2"></i>Proceed to Update Cache Settings
                        </button>
                    </div>
                ` : ''}
            `;
            
            domainCheckContent.innerHTML = summaryHtml;
            domainCheckSection.style.display = 'block';
            domainCheckSection.scrollIntoView({ behavior: 'smooth' });
        }

        function startBulkUpdate() {
            const formData = new FormData();
            formData.append('action', 'bulk_update_cache_ttl');
            formData.append('domain_list', document.getElementById('domainList').value);
            formData.append('browser_ttl', document.getElementById('browserTTL').value);
            formData.append('edge_ttl', document.getElementById('edgeTTL').value);
            formData.append('cache_level', document.getElementById('cacheLevel').value);
            
            // Show progress
            showProgress();
            
            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                hideProgress();
                if (data.success) {
                    displayResults(data.results);
                } else {
                    showError(data.error || 'Có lỗi xảy ra');
                }
            })
            .catch(error => {
                hideProgress();
                showError('Lỗi kết nối: ' + error.message);
            });
        }

        function showProgress() {
            document.getElementById('progressContainer').style.display = 'block';
            document.getElementById('updateBtn').disabled = true;
        }

        function hideProgress() {
            document.getElementById('progressContainer').style.display = 'none';
            document.getElementById('updateBtn').disabled = false;
        }

        function displayResults(results) {
            const resultsSection = document.getElementById('resultsSection');
            const resultsContent = document.getElementById('resultsContent');
            
            let successCount = 0;
            let errorCount = 0;
            
            const resultsHtml = results.map(result => {
                let status = 'success';
                let statusIcon = 'fa-check-circle';
                let statusText = 'Thành công';
                
                if (!result.zone_found) {
                    status = 'failed';
                    statusIcon = 'fa-times-circle';
                    statusText = 'Domain không tồn tại';
                    errorCount++;
                } else if (result.error) {
                    status = 'failed';
                    statusIcon = 'fa-times-circle';
                    statusText = 'Lỗi';
                    errorCount++;
                } else if (!result.success) {
                    status = 'failed';
                    statusIcon = 'fa-times-circle';
                    statusText = 'Lỗi cập nhật';
                    errorCount++;
                } else {
                    successCount++;
                }
                
                return `
                    <div class="result-item ${status === 'failed' ? 'failed' : ''}">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h6 class="mb-2">
                                    <i class="fas ${statusIcon} me-2"></i>
                                    ${result.domain_name}
                                </h6>
                                ${result.zone_found ? `
                                    <div class="row">
                                        ${result.browser_ttl_update ? `
                                            <div class="col-md-4">
                                                <small class="text-muted d-block">Browser Cache TTL:</small>
                                                <span class="badge bg-${result.browser_ttl_update.success ? 'success' : 'danger'} cache-badge">
                                                    ${result.browser_ttl_update.human_readable}
                                                </span>
                                            </div>
                                        ` : ''}
                                        ${result.edge_ttl_update ? `
                                            <div class="col-md-4">
                                                <small class="text-muted d-block">Edge Cache TTL:</small>
                                                <span class="badge bg-${result.edge_ttl_update.success ? 'info' : 'danger'} cache-badge">
                                                    ${result.edge_ttl_update.human_readable}
                                                </span>
                                            </div>
                                        ` : ''}
                                        ${result.cache_level_update ? `
                                            <div class="col-md-4">
                                                <small class="text-muted d-block">Cache Level:</small>
                                                <span class="badge bg-${result.cache_level_update.success ? 'warning' : 'danger'} cache-badge">
                                                    ${result.cache_level_update.value}
                                                </span>
                                            </div>
                                        ` : ''}
                                    </div>
                                ` : ''}
                                ${result.error ? `
                                    <div class="mt-2">
                                        <small class="text-danger">
                                            <i class="fas fa-exclamation-triangle me-1"></i>
                                            ${result.error}
                                        </small>
                                    </div>
                                ` : ''}
                            </div>
                            <span class="badge bg-${status === 'success' ? 'success' : 'danger'} ms-3">
                                ${statusText}
                            </span>
                        </div>
                    </div>
                `;
            }).join('');
            
            const summaryHtml = `
                <div class="row mb-4">
                    <div class="col-md-4">
                        <div class="card text-center border-success">
                            <div class="card-body">
                                <h5 class="text-success">${successCount}</h5>
                                <small>Thành công</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center border-danger">
                            <div class="card-body">
                                <h5 class="text-danger">${errorCount}</h5>
                                <small>Lỗi</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card text-center border-primary">
                            <div class="card-body">
                                <h5 class="text-primary">${results.length}</h5>
                                <small>Tổng số</small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="mb-4">
                    ${resultsHtml}
                </div>
                
                <div class="text-center">
                    <button onclick="location.reload()" class="btn btn-primary me-2">
                        <i class="fas fa-redo me-1"></i>Cập nhật khác
                    </button>
                    <a href="cache_manager.php" class="btn btn-outline-primary">
                        <i class="fas fa-database me-1"></i>Cache Manager
                    </a>
                </div>
            `;
            
            resultsContent.innerHTML = summaryHtml;
            resultsSection.style.display = 'block';
            resultsSection.scrollIntoView({ behavior: 'smooth' });
        }

        function showError(message) {
            const resultsSection = document.getElementById('resultsSection');
            const resultsContent = document.getElementById('resultsContent');
            
            resultsContent.innerHTML = `
                <div class="alert alert-danger">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>Lỗi</h5>
                    <p class="mb-0">${message}</p>
                </div>
                <div class="text-center mt-3">
                    <button onclick="location.reload()" class="btn btn-primary">
                        <i class="fas fa-redo me-1"></i>Thử lại
                    </button>
                </div>
            `;
            
            resultsSection.style.display = 'block';
            resultsSection.scrollIntoView({ behavior: 'smooth' });
        }

        // TTL card selection (if needed for future enhancements)
        function selectTTLCard(cardElement, ttlValue) {
            // Remove previous selections
            document.querySelectorAll('.ttl-card').forEach(card => {
                card.classList.remove('selected');
            });
            
            // Select current card
            cardElement.classList.add('selected');
            
            // Update select value
            document.getElementById('browserTTL').value = ttlValue;
            validateForm();
        }
    </script>
    
    </div> <!-- End main-wrapper -->
    
</body>
</html>