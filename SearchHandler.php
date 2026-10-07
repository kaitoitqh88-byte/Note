<?php
/**
 * Search Handler
 * Xử lý tìm kiếm domains với cache và bulk operations tối ưu
 */

require_once __DIR__ . '/CloudflareCache.php';
require_once __DIR__ . '/SearchEnhancements.php';

// Enable error logging for debugging
error_reporting(E_ALL);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/search_debug.log');

/**
 * Tìm kiếm domains trong cache local trước
 */
function searchInLocalCache($query, $page = 1, $perPage = 20, $status = null, $plan = null) {
    $cacheManager = new CloudflareCache();
    
    // Tạo cache key dựa trên search parameters
    $searchParams = [
        'query' => strtolower(trim($query)),
        'page' => $page,
        'per_page' => $perPage,
        'status' => $status,
        'plan' => $plan
    ];
    
    // Tìm trong cache với TTL 4 phút cho search results
    $cachedResult = $cacheManager->get('search', $searchParams, 'search_results');
    
    if ($cachedResult !== null) {
        // Thêm cache metadata
        $cachedResult['cache_hit'] = true;
        $cachedResult['cache_source'] = 'local_cache';
        return $cachedResult;
    }
    
    return null;
}

/**
 * Lưu kết quả tìm kiếm vào cache
 */
function saveSearchToCache($query, $page, $perPage, $status, $plan, $result) {
    $cacheManager = new CloudflareCache();
    
    $searchParams = [
        'query' => strtolower(trim($query)),
        'page' => $page,
        'per_page' => $perPage,
        'status' => $status,
        'plan' => $plan
    ];
    
    // Thêm metadata về cache
    $result['cache_hit'] = false;
    $result['cache_source'] = 'fresh_api';
    $result['cached_at'] = time();
    
    return $cacheManager->set('search', $result, $searchParams, 'search_results');
}

/**
 * Tìm kiếm fuzzy trong tất cả domains đã cache
 */
function fuzzySearchInCache($query) {
    $cacheManager = new CloudflareCache();
    $suggestions = [];
    
    // Tìm trong cache zones_list để có fuzzy search
    $cachedZones = $cacheManager->get('zones', ['page' => 1, 'per_page' => 100], 'zones_list');
    
    if ($cachedZones && isset($cachedZones['result'])) {
        $query = strtolower(trim($query));
        
        foreach ($cachedZones['result'] as $zone) {
            $zoneName = strtolower($zone['name']);
            
            // Exact match hoặc contains
            if ($zoneName === $query || strpos($zoneName, $query) !== false) {
                $suggestions[] = [
                    'zone' => $zone,
                    'match_type' => ($zoneName === $query) ? 'exact' : 'contains',
                    'relevance' => calculateRelevanceScore($zone, $query)
                ];
                continue;
            }
            
            // Fuzzy matching
            $similarity = 0;
            similar_text($query, $zoneName, $similarity);
            
            if ($similarity > 60) { // 60% similarity threshold
                $suggestions[] = [
                    'zone' => $zone,
                    'match_type' => 'fuzzy',
                    'similarity' => $similarity,
                    'relevance' => calculateRelevanceScore($zone, $query)
                ];
            }
        }
        
        // Sort by relevance
        usort($suggestions, function($a, $b) {
            return ($b['relevance'] ?? 0) <=> ($a['relevance'] ?? 0);
        });
        
        // Limit suggestions
        $suggestions = array_slice($suggestions, 0, 10);
    }
    
    return $suggestions;
}

/**
 * Xử lý tìm kiếm domains với cache và bulk operations tối ưu
 */
function handleSearch($cloudflare) {
    // Debug early exit
    error_log("=== HANDLESEARCH CALLED ===");
    error_log("REQUEST_METHOD: " . $_SERVER['REQUEST_METHOD']);
    error_log("GET params: " . json_encode($_GET));
    
    // Nếu không có tham số API, hiển thị giao diện HTML
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && !isset($_GET['api'])) {
        error_log("Returning HTML interface");
        include 'search.php';
        return;
    }
    
    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        error_log("Invalid method: " . $_SERVER['REQUEST_METHOD']);
        http_response_code(405);
        echo json_encode(['error' => 'Method not allowed']);
        return;
    }
    
    error_log("Processing API request");
    
    $query = $_GET['q'] ?? $_GET['query'] ?? '';
    $page = (int)($_GET['page'] ?? 1);
    $perPage = (int)($_GET['per_page'] ?? 20);  
    $status = $_GET['status'] ?? null;
    $plan = $_GET['plan'] ?? null;
    
    // Enhanced filtering and export options
    $nameFilter = $_GET['name_filter'] ?? null;
    $domainType = $_GET['domain_type'] ?? null;
    $sortBy = $_GET['sort_by'] ?? 'name'; // name, created_on, status, plan, relevance
    $sortOrder = strtoupper($_GET['sort_order'] ?? 'ASC'); // ASC, DESC
    $export = $_GET['export'] ?? null; // csv, json, xml
    $includeStats = ($_GET['include_stats'] ?? 'false') === 'true'; 
    $advancedSearch = ($_GET['advanced'] ?? 'false') === 'true';
    
    // Enhanced filtering options
    $nameFilter = $_GET['name_filter'] ?? null;
    $domainType = $_GET['domain_type'] ?? null;
    $sortBy = $_GET['sort_by'] ?? 'name'; // name, created_on, status, plan
    $sortOrder = strtoupper($_GET['sort_order'] ?? 'ASC'); // ASC, DESC
    $export = $_GET['export'] ?? null; // csv, json, xml
    $includeSSL = ($_GET['include_ssl'] ?? 'false') === 'true';
    $includeDNS = ($_GET['include_dns'] ?? 'false') === 'true';
    $advancedSearch = ($_GET['advanced'] ?? 'false') === 'true';
    
    // Handle cache settings - support both form and API calls
    $useCache = true; // Default to true
    if (isset($_GET['no_cache'])) {
        // API call with no_cache parameter
        $useCache = false;
    } elseif (isset($_GET['use_cache'])) {
        // Form submission with use_cache checkbox
        $useCache = ($_GET['use_cache'] === 'on' || $_GET['use_cache'] === '1');
    }
    
    $bulkMode = isset($_GET['bulk']) ? true : false; // Bulk search mode
    
    // Validate parameters
    if ($page < 1) $page = 1;
    if ($perPage < 1 || $perPage > 100) $perPage = 20;
    
    // Validate sort options
    $validSortFields = ['name', 'created_on', 'status', 'modified_on', 'activated_on', 'plan', 'relevance'];
    if (!in_array($sortBy, $validSortFields)) {
        $sortBy = 'name';
    }
    if (!in_array($sortOrder, ['ASC', 'DESC'])) {
        $sortOrder = 'ASC';
    }
    
    // Validate export format
    $validExportFormats = ['csv', 'json', 'xml'];
    if ($export && !in_array(strtolower($export), $validExportFormats)) {
        $export = null;
    }
    
    // Validate sort options
    $validSortFields = ['name', 'created_on', 'status', 'modified_on', 'activated_on'];
    if (!in_array($sortBy, $validSortFields)) {
        $sortBy = 'name';
    }
    if (!in_array($sortOrder, ['ASC', 'DESC'])) {
        $sortOrder = 'ASC';
    }
    
    try {
        $startTime = microtime(true);
        
        // Step 1: Tìm trong cache trước nếu useCache = true
        if ($useCache && !empty($query)) {
            $cachedResult = searchInLocalCache($query, $page, $perPage, $status, $plan);
            if ($cachedResult !== null) {
                $endTime = microtime(true);
                $executionTime = round(($endTime - $startTime) * 1000, 2);
                
                // Add cache performance metadata
                $cachedResult['search'] = [
                    'query' => $query,
                    'page' => $page,
                    'per_page' => $perPage,
                    'filters' => [
                        'status' => $status,
                        'plan' => $plan
                    ],
                    'total_results' => count($cachedResult['result'] ?? []),
                    'execution_time_ms' => $executionTime,
                    'cache_used' => true,
                    'cache_hit' => true,
                    'cache_source' => 'local_cache'
                ];
                
                error_log("=== CACHE HIT - SEARCH RESULTS ===\nQuery: {$query}\nExecution time: {$executionTime}ms");
                
                echo json_encode([
                    'success' => true,
                    'data' => $cachedResult,
                    'search' => $cachedResult['search']
                ]);
                return;
            }
        }
        
        // Step 2: Nếu không có cache, thử fuzzy search trong cache trước
        if ($useCache && !empty($query) && !$bulkMode) {
            $fuzzySuggestions = fuzzySearchInCache($query);
            if (!empty($fuzzySuggestions)) {
                // Nếu tìm thấy fuzzy matches, có thể return hoặc merge với API results
                error_log("Found {count($fuzzySuggestions)} fuzzy matches in cache for query: {$query}");
            }
        }
        
        // Step 3: Gọi API như bình thường nếu không có cache hit
        // Enhanced search logic với fuzzy search support
        if ($bulkMode && !empty($query)) {
            // Bulk search mode - multiple queries
            $queries = [];
            $searchTerms = array_filter(array_map('trim', explode(',', $query)));
            
            // Validate search terms
            $validTerms = array_filter($searchTerms, function($term) {
                return strlen($term) >= 2 && strlen($term) <= 253; // Valid domain length
            });
            
            if (empty($validTerms)) {
                throw new Exception('Invalid search terms. Domain names must be 2-253 characters.');
            }
            
            // Limit number of bulk queries để tránh overload
            $maxBulkQueries = 10;
            if (count($validTerms) > $maxBulkQueries) {
                $validTerms = array_slice($validTerms, 0, $maxBulkQueries);
            }
            
            foreach ($validTerms as $index => $term) {
                $queries["search_{$index}"] = [
                    'query' => $term,
                    'page' => $page,
                    'per_page' => min($perPage, 30), // Reduced limit per query
                    'status' => $status,
                    'plan' => $plan
                ];
            }
            
            if (count($queries) > 1) {
                $bulkResult = $cloudflare->bulkSearchZones($queries, $useCache);
                
                if (!$bulkResult['success']) {
                    throw new Exception('Bulk search failed: ' . ($bulkResult['error'] ?? 'Unknown error'));
                }
                
                // Enhanced result combination với deduplication
                $combinedResults = [];
                $totalCount = 0;
                $seenIds = [];
                $searchStats = [];
                
                foreach ($bulkResult['results'] as $searchKey => $searchResult) {
                    if (isset($searchResult['success']) && $searchResult['success'] && isset($searchResult['result'])) {
                        foreach ($searchResult['result'] as $zone) {
                            if (!in_array($zone['id'], $seenIds)) {
                                // Add search context to zone data
                                $zone['_search_term'] = $queries[str_replace('search_', '', $searchKey)]['query'] ?? 'unknown';
                                $zone['_relevance_score'] = calculateRelevanceScore($zone, $query);
                                
                                $combinedResults[] = $zone;
                                $seenIds[] = $zone['id'];
                            }
                        }
                        $totalCount += $searchResult['result_info']['count'] ?? 0;
                        
                        $searchStats[$searchKey] = [
                            'term' => $queries[str_replace('search_', '', $searchKey)]['query'] ?? '',
                            'results' => $searchResult['result_info']['count'] ?? 0,
                            'filtered' => $searchResult['result_info']['client_filtered'] ?? 0
                        ];
                    }
                }
                
                // Sort by relevance score (highest first)
                usort($combinedResults, function($a, $b) {
                    return ($b['_relevance_score'] ?? 0) <=> ($a['_relevance_score'] ?? 0);
                });
                
                $result = [
                    'success' => true,
                    'result' => $combinedResults,
                    'result_info' => [
                        'count' => count($combinedResults),
                        'total_count' => count($combinedResults),
                        'page' => $page,
                        'per_page' => $perPage,
                        'is_bulk_search' => true,
                        'bulk_queries' => count($queries),
                        'individual_results' => $totalCount,
                        'search_stats' => $searchStats,
                        'duplicates_removed' => $totalCount - count($combinedResults)
                    ]
                ];
            } else {
                // Single query in bulk mode - use standard search
                $result = $cloudflare->searchZones($validTerms[0], $page, $perPage, $status, $plan, $useCache);
                $result['result_info']['is_bulk_search'] = true;
                $result['result_info']['bulk_queries'] = 1;
            }
        } else {
            // Standard search mode với enhanced features
            if (!empty($query)) {
                // Validate single query
                if (strlen($query) < 1 || strlen($query) > 253) {
                    throw new Exception('Search query must be 1-253 characters long.');
                }
            }
            
            $result = $cloudflare->searchZones($query, $page, $perPage, $status, $plan, $useCache);
            
            // Apply advanced filtering if requested
            if ($advancedSearch && isset($result['result']) && !empty($result['result'])) {
                $originalCount = count($result['result']);
                $result['result'] = applyAdvancedFiltering($result['result'], [
                    'name_filter' => $nameFilter,
                    'domain_type' => $domainType
                ]);
                
                // Update result info for filtered results
                $result['result_info']['count'] = count($result['result']);
                $result['result_info']['filtered_by_advanced'] = true;
                $result['result_info']['filtered_count'] = $originalCount - count($result['result']);
            }
            
            // Apply sorting
            if (isset($result['result']) && count($result['result']) > 1) {
                $result['result'] = applySorting($result['result'], $sortBy, $sortOrder);
                $result['result_info']['sorted_by'] = $sortBy;
                $result['result_info']['sort_order'] = $sortOrder;
            }
            
            // Apply advanced filtering if requested
            if ($advancedSearch && isset($result['result'])) {
                $result['result'] = applyAdvancedFiltering($result['result'], [
                    'name_filter' => $nameFilter,
                    'domain_type' => $domainType,
                    'include_ssl' => $includeSSL,
                    'include_dns' => $includeDNS
                ]);
                
                // Update result info for filtered results
                $result['result_info']['count'] = count($result['result']);
                $result['result_info']['filtered_by_advanced'] = true;
            }
            
            // Apply sorting
            if (isset($result['result']) && count($result['result']) > 1) {
                $result['result'] = applySorting($result['result'], $sortBy, $sortOrder);
            }
            
            // Add relevance scoring for single search
            if (isset($result['result']) && is_array($result['result'])) {
                foreach ($result['result'] as &$zone) {
                    $zone['_relevance_score'] = calculateRelevanceScore($zone, $query);
                }
                
                // Sort by relevance if query provided
                if (!empty($query)) {
                    usort($result['result'], function($a, $b) {
                        return ($b['_relevance_score'] ?? 0) <=> ($a['_relevance_score'] ?? 0);
                    });
                }
            }
            
            // Lưu kết quả API vào cache nếu có kết quả và useCache = true
            if ($useCache && isset($result['result']) && !empty($result['result'])) {
                saveSearchToCache($query, $page, $perPage, $status, $plan, $result);
            }
        }
        
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2); // milliseconds
        
        // Enhanced search metadata với cache information
        $searchMeta = [
            'query' => $query,
            'page' => $page,
            'per_page' => $perPage,
            'filters' => [
                'status' => $status,
                'plan' => $plan,
                'name_filter' => $nameFilter,
                'domain_type' => $domainType,
                'advanced_search' => $advancedSearch
            ],
            'sorting' => [
                'sort_by' => $sortBy,
                'sort_order' => $sortOrder
            ],
            'export_format' => $export,
            'include_stats' => $includeStats,
            'total_results' => count($result['result'] ?? []),
            'search_fields' => ['name', 'status', 'plan.name', 'id'],
            'execution_time_ms' => $executionTime,
            'cache_used' => $useCache,
            'cache_hit' => $result['cache_hit'] ?? false,
            'cache_source' => $result['cache_source'] ?? 'api',
            'bulk_mode' => $bulkMode,
            'api_stats' => $cloudflare->getAPIStats()
        ];
        
        // Add cache information nếu có
        if (isset($result['result_info']['client_filtered'])) {
            $searchMeta['client_filtered'] = $result['result_info']['client_filtered'];
        }
        
        // Thêm suggestions cho empty results - sử dụng cache nếu có thể
        if (empty($result['result']) && !empty($query)) {
            if ($useCache) {
                // Tìm fuzzy suggestions từ cache trước
                $cacheSuggestions = fuzzySearchInCache($query);
                if (!empty($cacheSuggestions)) {
                    $searchMeta['suggestions'] = array_slice(
                        array_map(function($item) { return $item['zone']['name']; }, $cacheSuggestions),
                        0, 5
                    );
                    $searchMeta['suggestions_source'] = 'cache';
                } else {
                    $searchMeta['suggestions'] = generateSearchSuggestions($query, $cloudflare);
                    $searchMeta['suggestions_source'] = 'api';
                }
            } else {
                $searchMeta['suggestions'] = generateSearchSuggestions($query, $cloudflare);
                $searchMeta['suggestions_source'] = 'api';
            }
        }
        
        // Include domain statistics if requested 
        if ($includeStats && !empty($result['result'])) {
            $searchMeta['domain_statistics'] = generateDomainStatistics($result['result']);
        }
        
        // Handle export requests
        if ($export && !empty($result['result'])) {
            $exportData = exportSearchResults($result['result'], $export, $searchMeta);
            if ($exportData) {
                header('Content-Type: ' . $exportData['content_type']);
                header('Content-Disposition: attachment; filename="' . $exportData['filename'] . '"');
                echo $exportData['content'];
                return;
            }
        }
        
        // Debug logging trước khi trả response
        error_log("=== SEARCH API RESPONSE DEBUG ===");
        error_log("Query: " . $query);
        error_log("Result count: " . count($result['result'] ?? []));
        error_log("Result info: " . json_encode($result['result_info'] ?? []));
        error_log("Search meta: " . json_encode($searchMeta));
        
        $response = [
            'success' => true,
            'data' => $result,
            'search' => $searchMeta
        ];
        
        error_log("Final response structure: " . json_encode(array_keys($response)));
        error_log("=== END SEARCH API RESPONSE DEBUG ===");
        
        // Ensure proper headers
        header('Content-Type: application/json; charset=utf-8');
        http_response_code(200);
        echo json_encode($response);
        return;
        
    } catch (Exception $e) {
        $errorTime = microtime(true);
        $executionTime = isset($startTime) ? round(($errorTime - $startTime) * 1000, 2) : 0;
        
        error_log("SEARCH ERROR: " . $e->getMessage());
        error_log("ERROR FILE: " . $e->getFile());
        error_log("ERROR LINE: " . $e->getLine());
        error_log("ERROR TRACE: " . $e->getTraceAsString());
        
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage(),
            'debug' => [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ],
            'search' => [
                'query' => $query ?? '',
                'message' => 'Search failed',
                'execution_time_ms' => $executionTime,
                'cache_used' => $useCache ?? false,
                'bulk_mode' => $bulkMode ?? false
            ]
        ]);
    }
}

/**
 * Generate search suggestions cho empty results
 */
function generateSearchSuggestions($query, $cloudflare) {
    $suggestions = [];
    $query = strtolower(trim($query));
    
    try {
        // Get a sample of zones for suggestions
        $sampleZones = $cloudflare->listZones(1, 30, true); // Use cache
        
        if (isset($sampleZones['result'])) {
            $allDomains = array_map(function($zone) {
                return strtolower($zone['name']);
            }, $sampleZones['result']);
            
            // Find similar domains using simple string similarity
            foreach ($allDomains as $domain) {
                $similarity = 0;
                similar_text($query, $domain, $similarity);
                
                if ($similarity > 60) { // 60% similarity threshold
                    $suggestions[] = $domain;
                }
                
                // Check if query is part of domain name
                if (strpos($domain, $query) !== false) {
                    $suggestions[] = $domain;
                }
            }
            
            // Remove duplicates và limit suggestions
            $suggestions = array_unique($suggestions);
            $suggestions = array_slice($suggestions, 0, 5);
            
            // Add common TLD suggestions
            if (strpos($query, '.') === false && strlen($query) > 2) {
                $commonTLDs = ['.com', '.net', '.org', '.io', '.co'];
                foreach ($commonTLDs as $tld) {
                    $suggestions[] = $query . $tld;
                }
            }
        }
        
    } catch (Exception $e) {
        // Silent fail for suggestions
    }
    
    return array_unique(array_slice($suggestions, 0, 8));
}

/**
 * Calculate relevance score cho search results
 */
function calculateRelevanceScore($zone, $query) {
    if (empty($query)) return 50; // Default score
    
    $score = 0;
    $query = strtolower(trim($query));
    $zoneName = strtolower($zone['name'] ?? '');
    
    // Exact match gets highest score
    if ($zoneName === $query) {
        return 100;
    }
    
    // Domain starts with query
    if (strpos($zoneName, $query) === 0) {
        $score += 80;
    }
    
    // Domain contains query
    if (strpos($zoneName, $query) !== false) {
        $score += 60;
    }
    
    // Check TLD match
    $queryParts = explode('.', $query);
    $zoneParts = explode('.', $zoneName);
    
    if (count($queryParts) > 1 && count($zoneParts) > 1) {
        $queryTld = end($queryParts);
        $zoneTld = end($zoneParts);
        if ($queryTld === $zoneTld) {
            $score += 20;
        }
    }
    
    // Bonus for active zones
    if (($zone['status'] ?? '') === 'active') {
        $score += 10;
    }
    
    // Penalty for very long domain names (less relevant)
    if (strlen($zoneName) > strlen($query) + 20) {
        $score -= 5;
    }
    
    // Fuzzy matching bonus
    $similarity = 0;
    similar_text($query, $zoneName, $similarity);
    if ($similarity > 70) {
        $score += ($similarity - 70) / 2; // Up to 15 bonus points
    }
    
    return max(0, min(100, $score)); // Clamp between 0-100
}