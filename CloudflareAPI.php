<?php

require_once __DIR__ . '/CloudflareCache.php';

class CloudflareAPI {
    private $apiUrl;
    private $email;
    private $apiToken;
    private $headers;
    private $defaultPerPage;
    private $maxPerPage;
    private $cache;
    private $enableCache;
    private $requestCount;
    private $cacheHitCount;

    public function __construct($email = null, $apiToken = null, $defaultPerPage = 50, $enableCache = true) {
        // Auto-load configuration when this class is instantiated standalone.
        if (!defined('CLOUDFLARE_API_URL')) {
            $configPath = __DIR__ . '/config.php';
            if (file_exists($configPath)) {
                require_once $configPath;
            }
        }

        // Check if constants are defined
        if (!defined('CLOUDFLARE_API_URL')) {
            throw new Exception('Configuration not loaded. Missing CLOUDFLARE_API_URL. Ensure config.php exists at ' . __DIR__ . '/config.php');
        }

        $this->apiUrl = CLOUDFLARE_API_URL;
        $this->email = $email ?? (defined('CLOUDFLARE_EMAIL') ? CLOUDFLARE_EMAIL : null);
        $this->apiToken = $apiToken ?? (defined('CLOUDFLARE_API_TOKEN') ? CLOUDFLARE_API_TOKEN : null);
        $this->defaultPerPage = min($defaultPerPage, 100); // Cloudflare limit is 100
        $this->maxPerPage = 100; // Cloudflare API maximum
        $this->enableCache = $enableCache;
        $this->requestCount = 0;
        $this->cacheHitCount = 0;

        // Validate required configuration
        if (empty($this->email)) {
            throw new Exception('Cloudflare email is required');
        }

        if (empty($this->apiToken)) {
            throw new Exception('Cloudflare API token is required. Please create token.txt file or set CLOUDFLARE_API_TOKEN environment variable.');
        }

        $this->headers = [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json',
            'X-Auth-Email: ' . $this->email
        ];

        // Khởi tạo cache system
        if ($this->enableCache) {
            $this->cache = new CloudflareCache();
        }
    }

    /**
     * Cập nhật Security Level cho một zone
     * @param string $zoneId
     * @param string $level (high|medium|low|essentially_off|under_attack)
     * @return array
     */
    public function setSecurityLevel($zoneId, $level) {
        $endpoint = "zones/{$zoneId}/settings/security_level";
        $data = ["value" => $level];
        return $this->makeRequest($endpoint, 'PATCH', $data, false, 'zone_settings');
    }

    /**
     * Cập nhật Security Level cho danh sách domain (theo tên domain, tự động tìm zone_id)
     * @param array $domains Danh sách domain (chuỗi hoặc mảng)
     * @param string $level Security Level (high|medium|low|essentially_off|under_attack)
     * @return array Tổng hợp kết quả từng domain
     */
    public function batchSetSecurityLevelByDomains($domains, $level) {
        if (!is_array($domains)) {
            $domains = preg_split('/[\r\n,]+/', $domains, -1, PREG_SPLIT_NO_EMPTY);
        }
        $zonesResp = $this->getZones(100, true);
        $zoneMap = [];
        if (isset($zonesResp['result'])) {
            foreach ($zonesResp['result'] as $zone) {
                $zoneMap[strtolower($zone['name'])] = $zone['id'];
            }
        }
        $results = [];
        foreach ($domains as $domain) {
            $domain = trim(strtolower($domain));
            $zoneId = $zoneMap[$domain] ?? null;
            if (!$zoneId) {
                $results[] = [
                    'domain' => $domain,
                    'success' => false,
                    'error' => 'Zone not found'
                ];
                continue;
            }
            try {
                $resp = $this->setSecurityLevel($zoneId, $level);
                $results[] = [
                    'domain' => $domain,
                    'zone_id' => $zoneId,
                    'success' => $resp['success'] ?? false,
                    'result' => $resp['result'] ?? null,
                    'errors' => $resp['errors'] ?? null
                ];
            } catch (Exception $e) {
                $results[] = [
                    'domain' => $domain,
                    'zone_id' => $zoneId,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
            usleep(400000); // 0.4s tránh rate limit
        }
        return $results;
    }

    /**
     * Lấy toàn bộ settings của một zone (Cloudflare API: GET /zones/:zone_identifier/settings)
     * @param string $zoneId
     * @param bool $useCache
     * @return array|null
     */
    public function getZoneSettings($zoneId, $useCache = true) {
        $endpoint = "zones/{$zoneId}/settings";
        return $this->makeRequest($endpoint, 'GET', null, $useCache, 'zone_settings');
    }
    
    /**
     * Gửi request đến Cloudflare API với hỗ trợ cache
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null, $useCache = true, $cacheType = 'default') {
        $this->requestCount++;
        
        // Chỉ cache cho GET requests và nếu cache enabled
        $cacheKey = null;
        if ($this->enableCache && $useCache && $method === 'GET' && !$data) {
            $params = ['method' => $method, 'endpoint' => $endpoint];
            $cachedResponse = $this->cache->get($endpoint, $params, $cacheType);
            
            if ($cachedResponse !== null) {
                $this->cacheHitCount++;
                return $cachedResponse;
            }
        }
        
        $url = $this->apiUrl . ltrim($endpoint, '/');
        
        $ch = curl_init();
        
        // Create a temporary stream for debug info if available
        $debugStream = null;
        if (defined('CURLINFO_STDERR')) {
            $debugStream = fopen('php://temp', 'w+');
        }
        
        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $this->headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 60,        // Increase timeout to 60 seconds
            CURLOPT_CONNECTTIMEOUT => 30, // Increase connect timeout to 30 seconds
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_USERAGENT => 'CloudflareAPI-PHP/1.0'
        ];
        
        if ($debugStream) {
            $curlOptions[CURLOPT_VERBOSE] = true;
            $curlOptions[CURLOPT_STDERR] = $debugStream;
        }
        
        curl_setopt_array($ch, $curlOptions);

        if ($data && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlInfo = curl_getinfo($ch);
        $error = curl_error($ch);
        
        // Get curl debug info if available
        $debugInfo = '';
        if ($debugStream) {
            rewind($debugStream);
            $debugInfo = stream_get_contents($debugStream);
            fclose($debugStream);
        }
        
        curl_close($ch);

        if ($error) {
            $errorMessage = "cURL Error: " . $error;
            if (!empty($debugInfo)) {
                error_log("cURL Debug Info: " . $debugInfo);
                $errorMessage .= " (Debug info logged)";
            }
            error_log("cURL failed - URL: $url, HTTP Code: $httpCode, Error: $error");
            
            // Check if it's a connectivity issue and provide helpful message
            if (strpos($error, 'timed out') !== false || strpos($error, 'Connection refused') !== false) {
                $errorMessage = "Network connectivity issue: Cannot connect to Cloudflare API. " .
                              "Please check your internet connection, firewall settings, or proxy configuration.";
            }
            
            throw new Exception($errorMessage);
        }
        
        $decodedResponse = json_decode($response, true);
        
        if ($httpCode >= 400) {
            $errorDetails = [
                'http_code' => $httpCode,
                'url' => $url,
                'response' => $response ? substr($response, 0, 500) : 'No response body'
            ];
            
            if (is_array($decodedResponse) && isset($decodedResponse['errors'])) {
                $errorMsg = "Cloudflare API Error: " . json_encode($decodedResponse['errors']);
            } else {
                $errorMsg = "HTTP Error {$httpCode}";
                if ($httpCode == 502) {
                    $errorMsg .= " - Bad Gateway. Cloudflare API may be temporarily unavailable.";
                } elseif ($httpCode == 403) {
                    $errorMsg .= " - Forbidden. Check your API token permissions.";
                } elseif ($httpCode == 401) {
                    $errorMsg .= " - Unauthorized. Your API token may be invalid.";
                }
            }
            
            error_log("CloudflareAPI HTTP Error: " . json_encode($errorDetails));
            throw new Exception($errorMsg);
        }
        
        // Cache successful GET responses
        if ($this->enableCache && $useCache && $method === 'GET' && !$data && $decodedResponse) {
            $params = ['method' => $method, 'endpoint' => $endpoint];
            $this->cache->set($endpoint, $decodedResponse, $params, $cacheType);
        }
        
        return $decodedResponse;
    }
    
    /**
     * Bulk request với retry và rate limiting
     */
    private function makeBulkRequests($requests, $batchSize = 5, $delay = 1000000) { // 1 second delay
        $responses = [];
        $batches = array_chunk($requests, $batchSize);
        
        foreach ($batches as $batchIndex => $batch) {
            $batchResponses = [];
            
            foreach ($batch as $requestIndex => $request) {
                try {
                    $response = $this->makeRequest(
                        $request['endpoint'],
                        $request['method'] ?? 'GET',
                        $request['data'] ?? null,
                        $request['useCache'] ?? true,
                        $request['cacheType'] ?? 'bulk_operations'
                    );
                    $batchResponses[$request['key'] ?? $requestIndex] = $response;
                } catch (Exception $e) {
                    $batchResponses[$request['key'] ?? $requestIndex] = [
                        'success' => false,
                        'error' => $e->getMessage()
                    ];
                }
                
                // Small delay between requests trong cùng batch
                if (count($batch) > 1) {
                    usleep(200000); // 0.2 second
                }
            }
            
            $responses = array_merge($responses, $batchResponses);
            
            // Delay giữa các batches (except cho batch cuối)
            if ($batchIndex < count($batches) - 1) {
                usleep($delay);
            }
        }
        
        return $responses;
    }
    
    /**
     * Lấy thống kê cache và API usage
     */
    public function getAPIStats() {
        $stats = [
            'total_requests' => $this->requestCount,
            'cache_hits' => $this->cacheHitCount,
            'cache_hit_rate' => $this->requestCount > 0 ? 
                round(($this->cacheHitCount / $this->requestCount) * 100, 2) : 0,
            'cache_enabled' => $this->enableCache
        ];
        
        if ($this->enableCache && $this->cache) {
            $stats['cache_stats'] = $this->cache->getStats();
        }
        
        return $stats;
    }
    
    /**
     * Clear cache
     */
    public function clearCache($cacheType = null) {
        if (!$this->enableCache || !$this->cache) {
            return false;
        }
        
        if ($cacheType) {
            return $this->cache->deleteByCacheType($cacheType);
        }
        
        return $this->cache->clear();
    }
    
    /**
     * Disable/Enable cache
     */
    public function setCacheEnabled($enabled) {
        $this->enableCache = $enabled;
        if ($enabled && !$this->cache) {
            $this->cache = new CloudflareCache();
        }
        return $this;
    }
    
    /**
     * Thiết lập số lượng records mặc định mỗi trang
     */
    public function setDefaultPerPage($perPage) {
        $this->defaultPerPage = min($perPage, $this->maxPerPage);
        return $this;
    }
    
    /**
     * Lấy số lượng records mặc định mỗi trang
     */
    public function getDefaultPerPage() {
        return $this->defaultPerPage;
    }
    
    /**
     * Lấy số lượng records tối đa mỗi trang
     */
    public function getMaxPerPage() {
        return $this->maxPerPage;
    }
    
    /**
     * Validate và điều chỉnh perPage theo giới hạn API
     */
    private function validatePerPage($perPage = null) {
        $perPage = $perPage ?? $this->defaultPerPage;
        return min($perPage, $this->maxPerPage);
    }
    
    /**
     * Lấy danh sách tất cả zones với cache support
     */
    public function listZones($page = 1, $perPage = null, $useCache = true) {
        $perPage = $this->validatePerPage($perPage);
        $endpoint = "zones?page={$page}&per_page={$perPage}";
        return $this->makeRequest($endpoint, 'GET', null, $useCache, 'zones_list');
    }
    
    /**
     * Lấy tất cả zones (không giới hạn trang)
     */
    public function getZones($perPage = null, $useCache = true) {
        // Lấy tất cả zones với giới hạn cao để tránh phân trang
        $perPage = $this->validatePerPage($perPage ?: 100);
        $endpoint = "zones?per_page={$perPage}";
        return $this->makeRequest($endpoint, 'GET', null, $useCache, 'zones_list');
    }
    
    /**
     * Tìm kiếm zones với query string và filters - optimized với cache
     */
    public function searchZones($query = '', $page = 1, $perPage = null, $status = null, $plan = null, $useCache = true) {
        // Ensure perPage doesn't exceed Cloudflare API limit
        $perPage = $this->validatePerPage($perPage);
        
        $params = [
            "page={$page}",
            "per_page={$perPage}"
        ];
        
        // Add name filter if query provided
        if (!empty($query)) {
            $params[] = "name=" . urlencode($query);
        }
        
        // Add status filter
        if ($status) {
            $params[] = "status={$status}";
        }
        
        $queryString = '?' . implode('&', $params);
        $endpoint = "zones{$queryString}";
        
        // Use search_results cache type cho search queries
        $cacheType = !empty($query) ? 'search_results' : 'zones_list';
        $response = $this->makeRequest($endpoint, 'GET', null, $useCache, $cacheType);
        
        // Additional client-side filtering for more comprehensive search
        if (!empty($query) && isset($response['result'])) {
            $query = strtolower($query);
            $originalCount = count($response['result']);
            
            $response['result'] = array_filter($response['result'], function($zone) use ($query, $plan) {
                $searchableText = strtolower(
                    $zone['name'] . ' ' . 
                    $zone['status'] . ' ' . 
                    ($zone['plan']['name'] ?? '') . ' ' .
                    $zone['id']
                );
                
                $matchesQuery = empty($query) || strpos($searchableText, $query) !== false;
                $matchesPlan = empty($plan) || (isset($zone['plan']['name']) && 
                    strtolower($zone['plan']['name']) === strtolower($plan));
                
                return $matchesQuery && $matchesPlan;
            });
            
            // Re-index array and update result info
            $response['result'] = array_values($response['result']);
            $filteredCount = count($response['result']);
            
            // Update result info để phản ánh client-side filtering
            if (isset($response['result_info'])) {
                $response['result_info']['count'] = $filteredCount;
                $response['result_info']['client_filtered'] = $originalCount - $filteredCount;
            }
        }
        
        return $response;
    }
    
    /**
     * Lấy tất cả zones với phân trang tự động và bulk caching (cho số lượng lớn)
     */
    public function getAllZonesPaginated($maxZones = 500, $useCache = true, $prefetchDetails = false) {
        $allZones = [];
        $page = 1;
        $perPage = $this->maxPerPage; // Use maximum allowed by Cloudflare API
        $bulkRequests = [];
        
        do {
            $response = $this->listZones($page, $perPage, $useCache);
            
            if (!$response || !isset($response['success']) || !$response['success']) {
                break;
            }
            
            $zones = $response['result'] ?? [];
            $allZones = array_merge($allZones, $zones);
            
            // Nếu prefetchDetails enabled, chuẩn bị bulk requests cho zone details
            if ($prefetchDetails && !empty($zones)) {
                foreach ($zones as $zone) {
                    $bulkRequests[] = [
                        'key' => 'zone_' . $zone['id'],
                        'endpoint' => "zones/{$zone['id']}",
                        'method' => 'GET',
                        'cacheType' => 'zone_details'
                    ];
                }
            }
            
            $resultInfo = $response['result_info'] ?? [];
            $totalPages = $resultInfo['total_pages'] ?? 1;
            $currentCount = count($allZones);
            
            $page++;
            
            // Safety limits
            if ($currentCount >= $maxZones || $page > $totalPages || $page > 50) {
                break;
            }
            
            // Adaptive rate limiting dựa trên API response time
            usleep(80000); // 0.08 second delay (optimized)
            
        } while (count($zones) === $perPage);
        
        $result = [
            'success' => true,
            'result' => array_slice($allZones, 0, $maxZones),
            'result_info' => [
                'count' => min(count($allZones), $maxZones),
                'total_count' => count($allZones),
                'pages_fetched' => $page - 1,
                'truncated' => count($allZones) > $maxZones,
                'cache_enabled' => $useCache
            ]
        ];
        
        // Execute bulk prefetch cho zone details nếu cần
        if ($prefetchDetails && !empty($bulkRequests)) {
            $this->prefetchBulkData($bulkRequests);
            $result['result_info']['prefetched_details'] = count($bulkRequests);
        }
        
        return $result;
    }
    
    /**
     * Bulk search với smart caching - tìm kiếm qua nhiều criteria cùng lúc
     */
    public function bulkSearchZones($queries, $useCache = true) {
        if (empty($queries)) {
            return [
                'success' => false,
                'error' => 'No search queries provided',
                'results' => []
            ];
        }
        
        $allResults = [];
        $bulkRequests = [];
        $requestParams = []; // Store params separately for easier access
        
        foreach ($queries as $queryKey => $searchParams) {
            $query = $searchParams['query'] ?? '';
            $page = $searchParams['page'] ?? 1;
            $perPage = min($searchParams['per_page'] ?? 20, 50); // Limit per query
            $status = $searchParams['status'] ?? null;
            $plan = $searchParams['plan'] ?? null;
            
            // Store params for later use
            $requestParams[$queryKey] = $searchParams;
            
            // Tạo endpoint cho search request
            $params = [
                "page={$page}",
                "per_page={$perPage}"
            ];
            
            if (!empty($query)) {
                $params[] = "name=" . urlencode($query);
            }
            if ($status) {
                $params[] = "status={$status}";
            }
            
            $endpoint = "zones?" . implode('&', $params);
            
            $bulkRequests[] = [
                'key' => $queryKey,
                'endpoint' => $endpoint,
                'method' => 'GET',
                'cacheType' => 'search_results',
                'useCache' => $useCache
            ];
        }
        
        // Execute bulk requests với optimized batching
        $batchSize = min(5, count($bulkRequests)); // Max 5 concurrent
        $responses = $this->makeBulkRequests($bulkRequests, $batchSize, 300000); // 0.3s delay
        
        // Process responses với enhanced client-side filtering
        foreach ($responses as $queryKey => $response) {
            if (isset($response['error']) || (isset($response['success']) && !$response['success'])) {
                $allResults[$queryKey] = [
                    'success' => false,
                    'error' => $response['error'] ?? 'Search failed',
                    'result' => [],
                    'result_info' => ['count' => 0, 'total_count' => 0]
                ];
                continue;
            }
            
            // Get search params for this query
            $searchParams = $requestParams[$queryKey] ?? [];
            $query = strtolower($searchParams['query'] ?? '');
            $plan = $searchParams['plan'] ?? null;
            
            // Enhanced client-side filtering
            if (isset($response['result']) && is_array($response['result'])) {
                $originalCount = count($response['result']);
                $filteredResults = [];
                
                foreach ($response['result'] as $zone) {
                    $searchableText = strtolower(
                        ($zone['name'] ?? '') . ' ' . 
                        ($zone['status'] ?? '') . ' ' . 
                        ($zone['plan']['name'] ?? '') . ' ' .
                        ($zone['id'] ?? '')
                    );
                    
                    $matchesQuery = empty($query) || 
                                   strpos($searchableText, $query) !== false ||
                                   levenshtein($query, substr($zone['name'] ?? '', 0, strlen($query))) <= 2;
                    
                    $matchesPlan = empty($plan) || 
                                  (isset($zone['plan']['name']) && 
                                   strtolower($zone['plan']['name']) === strtolower($plan));
                    
                    if ($matchesQuery && $matchesPlan) {
                        $filteredResults[] = $zone;
                    }
                }
                
                $response['result'] = $filteredResults;
                
                // Update result info
                if (isset($response['result_info'])) {
                    $response['result_info']['count'] = count($filteredResults);
                    $response['result_info']['client_filtered'] = $originalCount - count($filteredResults);
                    $response['result_info']['original_count'] = $originalCount;
                } else {
                    $response['result_info'] = [
                        'count' => count($filteredResults),
                        'total_count' => count($filteredResults),
                        'client_filtered' => $originalCount - count($filteredResults),
                        'original_count' => $originalCount,
                        'page' => $searchParams['page'] ?? 1,
                        'per_page' => $searchParams['per_page'] ?? 20
                    ];
                }
                
                // Mark as successful
                $response['success'] = true;
            } else {
                // No results or invalid response
                $response = [
                    'success' => true,
                    'result' => [],
                    'result_info' => [
                        'count' => 0,
                        'total_count' => 0,
                        'page' => $searchParams['page'] ?? 1,
                        'per_page' => $searchParams['per_page'] ?? 20
                    ]
                ];
            }
            
            $allResults[$queryKey] = $response;
        }
        
        // Calculate summary stats
        $totalResults = 0;
        $successfulQueries = 0;
        
        foreach ($allResults as $result) {
            if (isset($result['success']) && $result['success']) {
                $successfulQueries++;
                $totalResults += $result['result_info']['count'] ?? 0;
            }
        }
        
        return [
            'success' => true,
            'results' => $allResults,
            'bulk_info' => [
                'total_queries' => count($queries),
                'successful_queries' => $successfulQueries,
                'failed_queries' => count($queries) - $successfulQueries,
                'total_results' => $totalResults,
                'cache_enabled' => $useCache,
                'execution_time' => microtime(true)
            ]
        ];
    }
    
    /**
     * Prefetch bulk data in background
     */
    private function prefetchBulkData($requests) {
        if (empty($requests)) {
            return;
        }
        
        try {
            $this->makeBulkRequests($requests, 8, 400000); // 8 per batch, 0.4s delay - aggressive
        } catch (Exception $e) {
            // Silent fail for prefetch - không làm ảnh hưởng main operation
            error_log("Prefetch failed: " . $e->getMessage());
        }
    }
    
    /**
     * Smart cache warming - pre-load commonly accessed data
     */
    public function warmCache($options = []) {
        $defaultOptions = [
            'zones_list' => true,        // Cache first page of zones
            'zone_details' => 10,        // Cache details of first N zones  
            'dns_records' => 5,          // Cache DNS records of first N zones
            'concurrent_batch' => 5       // Max concurrent requests per batch
        ];
        
        $options = array_merge($defaultOptions, $options);
        $warmedData = [];
        
        try {
            // 1. Cache zones list
            if ($options['zones_list']) {
                $zones = $this->listZones(1, 50, false); // Force fresh data 
                if ($zones && isset($zones['result'])) {
                    $warmedData['zones_list'] = count($zones['result']);
                }
            }
            
            // 2. Cache zone details for top zones
            if ($options['zone_details'] > 0 && isset($zones['result'])) {
                $topZones = array_slice($zones['result'], 0, $options['zone_details']);
                $detailRequests = [];
                
                foreach ($topZones as $zone) {
                    $detailRequests[] = [
                        'key' => 'zone_' . $zone['id'],
                        'endpoint' => "zones/{$zone['id']}",
                        'method' => 'GET',
                        'cacheType' => 'zone_details',
                        'useCache' => false // Force fresh
                    ];
                }
                
                if (!empty($detailRequests)) {
                    $responses = $this->makeBulkRequests($detailRequests, $options['concurrent_batch']);
                    $warmedData['zone_details'] = count(array_filter($responses, function($r) {
                        return !isset($r['error']);
                    }));
                }
            }
            
            // 3. Cache DNS records for top zones
            if ($options['dns_records'] > 0 && isset($zones['result'])) {
                $topZones = array_slice($zones['result'], 0, $options['dns_records']);
                $dnsRequests = [];
                
                foreach ($topZones as $zone) {
                    $dnsRequests[] = [
                        'key' => 'dns_' . $zone['id'],
                        'endpoint' => "zones/{$zone['id']}/dns_records?per_page=50",
                        'method' => 'GET',
                        'cacheType' => 'dns_records',
                        'useCache' => false // Force fresh
                    ];
                }
                
                if (!empty($dnsRequests)) {
                    $responses = $this->makeBulkRequests($dnsRequests, $options['concurrent_batch']);
                    $warmedData['dns_records'] = count(array_filter($responses, function($r) {
                        return !isset($r['error']);
                    }));
                }
            }
            
        } catch (Exception $e) {
            $warmedData['error'] = $e->getMessage();
        }
        
        return [
            'success' => !isset($warmedData['error']),
            'warmed_data' => $warmedData,
            'timestamp' => time()
        ];
    }
    
    /**
     * Lấy thông tin zone cụ thể với cache support
     */
    public function getZone($zoneId, $useCache = true) {
        $endpoint = "zones/{$zoneId}";
        return $this->makeRequest($endpoint, 'GET', null, $useCache, 'zone_details');
    }
    
    /**
     * Lấy danh sách DNS records của một zone với cache support
     */
    public function listDNSRecords($zoneId, $type = null, $name = null, $useCache = true) {
        $params = ['per_page' => 100]; // Đảm bảo lấy đủ records
        if ($type) $params['type'] = $type;
        if ($name) $params['name'] = $name;
        
        $queryString = '?' . http_build_query($params);
        $endpoint = "zones/{$zoneId}/dns_records{$queryString}";
        return $this->makeRequest($endpoint, 'GET', null, $useCache, 'dns_records');
    }
    
    /**
     * Bulk get zone details - lấy thông tin chi tiết nhiều zones cùng lúc
     */
    public function bulkGetZoneDetails($zoneIds, $useCache = true) {
        if (empty($zoneIds)) {
            return [];
        }
        
        $requests = [];
        foreach ($zoneIds as $zoneId) {
            $requests[] = [
                'key' => $zoneId,
                'endpoint' => "zones/{$zoneId}",
                'method' => 'GET',
                'cacheType' => 'zone_details',
                'useCache' => $useCache
            ];
        }
        
        return $this->makeBulkRequests($requests, 8, 300000); // 8 concurrent, 0.3s delay
    }
    
    /**
     * Lấy DNS records của một zone
     */
    public function getDNSRecords($zoneId, $params = [], $useCache = true) {
        $queryString = '';
        if (!empty($params)) {
            $queryString = '?' . http_build_query($params);
        }
        
        $endpoint = "zones/{$zoneId}/dns_records{$queryString}";
        return $this->makeRequest($endpoint, 'GET', null, $useCache, 'dns_records');
    }

    /**
     * Lấy toàn bộ DNS records của một zone với phân trang tự động
     */
    public function getAllDNSRecordsForZone($zoneId, $useCache = true, $perPage = 100) {
        $allRecords = [];
        $page = 1;
        $perPage = min(max((int) $perPage, 1), $this->maxPerPage);

        do {
            $response = $this->getDNSRecords($zoneId, [
                'page' => $page,
                'per_page' => $perPage
            ], $useCache);

            if (!$response || !isset($response['success']) || !$response['success']) {
                return [
                    'success' => false,
                    'error' => $response['errors'] ?? ['Failed to get DNS records'],
                    'result' => $allRecords,
                    'result_info' => $response['result_info'] ?? null
                ];
            }

            $records = $response['result'] ?? [];
            $allRecords = array_merge($allRecords, $records);

            $resultInfo = $response['result_info'] ?? [];
            $totalPages = $resultInfo['total_pages'] ?? 1;
            $page++;
        } while (!empty($records) && $page <= $totalPages);

        return [
            'success' => true,
            'result' => $allRecords,
            'result_info' => [
                'count' => count($allRecords),
                'pages_fetched' => $page - 1,
                'total_count' => count($allRecords)
            ]
        ];
    }

    /**
     * Bulk get DNS records - lấy DNS records của nhiều zones cùng lúc
     */
    public function bulkGetDNSRecords($zoneIds, $recordTypes = null, $useCache = true) {
        if (empty($zoneIds)) {
            return [];
        }
        
        $requests = [];
        foreach ($zoneIds as $zoneId) {
            $params = [];
            if ($recordTypes) {
                if (is_array($recordTypes)) {
                    // Với nhiều record types, tạo multiple requests
                    foreach ($recordTypes as $type) {
                        $requests[] = [
                            'key' => "{$zoneId}_{$type}",
                            'endpoint' => "zones/{$zoneId}/dns_records?type={$type}",
                            'method' => 'GET',
                            'cacheType' => 'dns_records',
                            'useCache' => $useCache
                        ];
                    }
                } else {
                    // Single record type
                    $requests[] = [
                        'key' => "{$zoneId}_{$recordTypes}",
                        'endpoint' => "zones/{$zoneId}/dns_records?type={$recordTypes}",
                        'method' => 'GET',
                        'cacheType' => 'dns_records',  
                        'useCache' => $useCache
                    ];
                }
            } else {
                // All DNS records
                $requests[] = [
                    'key' => $zoneId,
                    'endpoint' => "zones/{$zoneId}/dns_records",
                    'method' => 'GET',
                    'cacheType' => 'dns_records',
                    'useCache' => $useCache
                ];
            }
        }
        
        return $this->makeBulkRequests($requests, 6, 400000); // 6 concurrent, 0.4s delay
    }
    
    /**
     * Tạo DNS record mới
     */
    public function createDNSRecord($zoneId, $type, $name, $content, $ttl = 1, $proxied = false) {
        $data = [
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl
        ];
        
        // Add proxied for A/AAAA/CNAME records
        if (in_array($type, ['A', 'AAAA', 'CNAME'])) {
            $data['proxied'] = $proxied;
        }
        
        return $this->makeRequest("zones/{$zoneId}/dns_records", 'POST', $data);
    }
    
    /**
     * Cập nhật DNS record
     */
    public function updateDNSRecord($zoneId, $recordId, $type, $name, $content, $ttl = 1, $proxied = false) {
        $data = [
            'type' => $type,
            'name' => $name,
            'content' => $content,
            'ttl' => $ttl
        ];
        
        // Add proxied for A/AAAA/CNAME records
        if (in_array($type, ['A', 'AAAA', 'CNAME'])) {
            $data['proxied'] = $proxied;
        }
        
        return $this->makeRequest("zones/{$zoneId}/dns_records/{$recordId}", 'PUT', $data);
    }
    
    /**
     * Xóa DNS record
     */
    public function deleteDNSRecord($zoneId, $recordId) {
        return $this->makeRequest("zones/{$zoneId}/dns_records/{$recordId}", 'DELETE');
    }

    /**
     * Xóa toàn bộ DNS records của danh sách domain đã cho
     */
    public function batchDeleteDNSRecordsByDomains($domains, $useCache = true) {
        if (!is_array($domains)) {
            $domains = preg_split('/[\r\n,;]+/', (string) $domains, -1, PREG_SPLIT_NO_EMPTY);
        }

        $normalizedDomains = [];
        foreach ($domains as $domain) {
            $normalized = $this->normalizeDomainName($domain);
            if ($normalized !== null) {
                $normalizedDomains[$normalized] = true;
            }
        }

        $domains = array_keys($normalizedDomains);
        if (empty($domains)) {
            return [
                'success' => false,
                'error' => 'No valid domains provided',
                'results' => []
            ];
        }

        $zonesResponse = $this->getAllZonesPaginated(1000, $useCache);
        $zoneMap = [];
        foreach (($zonesResponse['result'] ?? []) as $zone) {
            if (!empty($zone['name']) && !empty($zone['id'])) {
                $zoneMap[strtolower($zone['name'])] = $zone;
            }
        }

        $results = [];
        $overallSuccess = true;

        foreach ($domains as $domain) {
            $result = [
                'domain' => $domain,
                'zone_found' => false,
                'zone_id' => null,
                'zone_name' => null,
                'total_records' => 0,
                'deleted_count' => 0,
                'failed_count' => 0,
                'success' => false,
                'errors' => []
            ];

            $zone = $zoneMap[$domain] ?? null;
            if (!$zone) {
                $result['errors'][] = 'Zone not found in Cloudflare account';
                $overallSuccess = false;
                $results[] = $result;
                continue;
            }

            $result['zone_found'] = true;
            $result['zone_id'] = $zone['id'];
            $result['zone_name'] = $zone['name'];

            $recordsResponse = $this->getAllDNSRecordsForZone($zone['id'], $useCache);
            if (!$recordsResponse['success']) {
                $errors = $recordsResponse['error'] ?? ['Failed to fetch DNS records'];
                $result['errors'] = array_merge($result['errors'], is_array($errors) ? $errors : [$errors]);
                $overallSuccess = false;
                $results[] = $result;
                continue;
            }

            $records = $recordsResponse['result'] ?? [];
            $result['total_records'] = count($records);

            foreach ($records as $record) {
                $recordId = $record['id'] ?? null;
                if (empty($recordId)) {
                    $result['failed_count']++;
                    $result['errors'][] = 'Missing record ID while deleting DNS record';
                    $overallSuccess = false;
                    continue;
                }

                try {
                    $deleteResponse = $this->deleteDNSRecord($zone['id'], $recordId);
                    if ($deleteResponse && isset($deleteResponse['success']) && $deleteResponse['success']) {
                        $result['deleted_count']++;
                    } else {
                        $result['failed_count']++;
                        $result['errors'][] = $record['name'] . ' (' . $record['type'] . '): delete failed';
                        $overallSuccess = false;
                    }
                } catch (Exception $e) {
                    $result['failed_count']++;
                    $result['errors'][] = $record['name'] . ' (' . $record['type'] . '): ' . $e->getMessage();
                    $overallSuccess = false;
                }

                usleep(120000);
            }

            $result['success'] = $result['failed_count'] === 0;
            $results[] = $result;

            usleep(300000);
        }

        return [
            'success' => $overallSuccess,
            'results' => $results,
            'summary' => [
                'domains_total' => count($domains),
                'domains_successful' => count(array_filter($results, function ($item) {
                    return !empty($item['success']);
                })),
                'domains_failed' => count(array_filter($results, function ($item) {
                    return empty($item['success']);
                }))
            ]
        ];
    }

    /**
     * Chuẩn hóa domain trước khi đối chiếu với zone name
     */
    private function normalizeDomainName($domain) {
        $domain = trim(strtolower((string) $domain));
        if ($domain === '') {
            return null;
        }

        $domain = preg_replace('#^https?://#', '', $domain);
        $domain = preg_replace('#^www\\.#', '', $domain);
        $domain = preg_split('#[/?#]#', $domain, 2)[0] ?? $domain;
        $domain = rtrim($domain, '.');

        return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) ? $domain : null;
    }
    
    /**
     * Cập nhật DNS record với dữ liệu đầy đủ
     */
    public function updateDNSRecordAdvanced($zoneId, $recordId, $data) {
        return $this->makeRequest("zones/{$zoneId}/dns_records/{$recordId}", 'PUT', $data);
    }
    
    /**
     * Tạo DNS record với dữ liệu đầy đủ
     */
    public function createDNSRecordAdvanced($zoneId, $data) {
        return $this->makeRequest("zones/{$zoneId}/dns_records", 'POST', $data);
    }
    
    /**
     * Lấy danh sách accounts
     */
    public function listAccounts() {
        return $this->makeRequest('accounts', 'GET', null, false);
    }

    /**
     * Thêm domain vào Cloudflare với plan Free
     * @param string $domain    Tên domain (vd: example.com)
     * @param string $accountId Account ID Cloudflare
     * @param bool   $jumpStart Tự động quét DNS records hiện có
     * @return array
     */
    public function addZone(string $domain, string $accountId, bool $jumpStart = true): array {
        $data = [
            'name'       => $domain,
            'account'    => ['id' => $accountId],
            'jump_start' => $jumpStart,
            'type'       => 'full',
            'plan'       => ['id' => 'free'],
        ];
        try {
            return $this->makeRequest('zones', 'POST', $data, false);
        } catch (Exception $e) {
            // Trả về cấu trúc lỗi chuẩn thay vì throw để caller xử lý
            $msg = $e->getMessage();
            // Thử parse errors JSON từ message nếu có
            if (preg_match('/Cloudflare API Error: (\[.*\])/s', $msg, $m)) {
                $errors = json_decode($m[1], true);
                if (is_array($errors)) {
                    return ['success' => false, 'errors' => $errors, 'result' => null];
                }
            }
            return ['success' => false, 'errors' => [['code' => 0, 'message' => $msg]], 'result' => null];
        }
    }

    /**
     * Verify API token
     */
    public function verifyToken() {
        return $this->makeRequest('user/tokens/verify', 'GET', null, false);
    }
    
    /**
     * Get user details
     */
    public function getUserDetails() {
        return $this->makeRequest('user', 'GET', null, false);
    }
    
    /**
     * Test API connection
     */
    public function testAPIConnection() {
        try {
            $result = $this->verifyToken();
            if ($result['success']) {
                return [
                    'success' => true,
                    'message' => 'API connection successful',
                    'data' => $result
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'API connection failed',
                    'details' => $result
                ];
            }
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Connection error: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Purge cache
     */
    public function purgeCache($zoneId, $files = null) {
        $data = [];
        if ($files) {
            $data['files'] = is_array($files) ? $files : [$files];
        } else {
            $data['purge_everything'] = true;
        }
        
        return $this->makeRequest("zones/{$zoneId}/purge_cache", 'POST', $data);
    }
    
    /**
     * Lấy thông tin analytics của zone
     */
    public function getAnalytics($zoneId, $since = null, $until = null) {
        $params = [];
        if ($since) $params[] = "since={$since}";
        if ($until) $params[] = "until={$until}";
        
        $queryString = !empty($params) ? '?' . implode('&', $params) : '';
        return $this->makeRequest("zones/{$zoneId}/analytics/dashboard{$queryString}");
    }
    
    /**
     * Bật/tắt Development Mode
     */
    public function setDevelopmentMode($zoneId, $enabled = true) {
        $data = ['value' => $enabled ? 'on' : 'off'];
        return $this->makeRequest("zones/{$zoneId}/settings/development_mode", 'PATCH', $data);
    }
    
    /**
     * Lấy thông tin cài đặt SSL/HTTPS của zone
     */
    public function getSSLSettings($zoneId) {
        return $this->makeRequest("zones/{$zoneId}/settings");
    }
    
    /**
     * Lấy trạng thái Always Use HTTPS của một domain cụ thể
     */
    public function getAlwaysUseHTTPSStatus($zoneId) {
        $response = $this->makeRequest("zones/{$zoneId}/settings/always_use_https");
        
        if ($response && isset($response['success']) && $response['success']) {
            $value = $response['result']['value'] ?? 'off';
            return [
                'success' => true,
                'zone_id' => $zoneId,
                'always_use_https' => $value === 'on',
                'value' => $value,
                'raw_response' => $response
            ];
        }
        
        return [
            'success' => false,
            'zone_id' => $zoneId,
            'always_use_https' => false,
            'error' => $response['errors'] ?? ['Unknown error'],
            'raw_response' => $response
        ];
    }
    
    /**
     * Kiểm tra trạng thái Always Use HTTPS của tất cả domains
     */
    public function checkAllDomainsHTTPSStatus() {
        // Lấy danh sách tất cả zones
        $zonesResponse = $this->getZones();
        
        if (!$zonesResponse || !isset($zonesResponse['success']) || !$zonesResponse['success']) {
            return [
                'success' => false,
                'error' => 'Failed to get zones list',
                'raw_response' => $zonesResponse
            ];
        }
        
        $zones = $zonesResponse['result'] ?? [];
        $results = [];
        
        foreach ($zones as $zone) {
            $zoneId = $zone['id'];
            $zoneName = $zone['name'];
            
            // Kiểm tra trạng thái Always Use HTTPS cho từng zone
            $httpsStatus = $this->getAlwaysUseHTTPSStatus($zoneId);
            
            $results[] = [
                'zone_id' => $zoneId,
                'zone_name' => $zoneName,
                'always_use_https' => $httpsStatus['always_use_https'],
                'status' => $httpsStatus['success'] ? 'checked' : 'error',
                'error' => $httpsStatus['error'] ?? null
            ];
            
            // Thêm delay để tránh rate limit
            usleep(500000); // 0.5 seconds delay
        }
        
        return [
            'success' => true,
            'total_domains' => count($zones),
            'results' => $results,
            'summary' => [
                'enabled' => count(array_filter($results, fn($r) => $r['always_use_https'])),
                'disabled' => count(array_filter($results, fn($r) => !$r['always_use_https'] && $r['status'] === 'checked')),
                'errors' => count(array_filter($results, fn($r) => $r['status'] === 'error'))
            ]
        ];
    }
    
    /**
     * Kiểm tra trạng thái Always Use HTTPS cho nhiều domains cụ thể
     */
    public function checkMultipleDomainsHTTPSStatus($zoneIds) {
        if (!is_array($zoneIds) || empty($zoneIds)) {
            return [
                'success' => false,
                'error' => 'Invalid zone IDs provided'
            ];
        }
        
        $results = [];
        
        foreach ($zoneIds as $zoneId) {
            $httpsStatus = $this->getAlwaysUseHTTPSStatus($zoneId);
            $results[] = $httpsStatus;
            
            // Thêm delay để tránh rate limit
            usleep(300000); // 0.3 seconds delay
        }
        
        return [
            'success' => true,
            'total_checked' => count($results),
            'results' => $results,
            'summary' => [
                'enabled' => count(array_filter($results, fn($r) => $r['always_use_https'])),
                'disabled' => count(array_filter($results, fn($r) => !$r['always_use_https'] && $r['success'])),
                'errors' => count(array_filter($results, fn($r) => !$r['success']))
            ]
        ];
    }    
    /**
     * Test method để kiểm tra kết nối và chức năng Always Use HTTPS
     */
    public function testAlwaysHTTPSFunctionality() {
        $results = [
            'timestamp' => date('Y-m-d H:i:s'),
            'tests' => []
        ];
        
        // Test 1: Kiểm tra kết nối API
        $results['tests']['api_connection'] = [
            'name' => 'API Connection Test',
            'status' => 'running'
        ];
        
        $zonesResponse = $this->getZones();
        if ($zonesResponse && isset($zonesResponse['success']) && $zonesResponse['success']) {
            $results['tests']['api_connection']['status'] = 'passed';
            $results['tests']['api_connection']['message'] = 'API connection successful';
            $results['tests']['api_connection']['zones_found'] = count($zonesResponse['result'] ?? []);
        } else {
            $results['tests']['api_connection']['status'] = 'failed';
            $results['tests']['api_connection']['message'] = 'Failed to connect to API';
            $results['tests']['api_connection']['error'] = $zonesResponse['errors'] ?? ['Unknown error'];
            return $results;
        }
        
        // Test 2: Kiểm tra Always Use HTTPS cho domain đầu tiên
        $zones = $zonesResponse['result'] ?? [];
        if (!empty($zones)) {
            $firstZone = $zones[0];
            $results['tests']['https_check'] = [
                'name' => 'Always Use HTTPS Status Check',
                'status' => 'running',
                'zone_tested' => $firstZone['name']
            ];
            
            $httpsStatus = $this->getAlwaysUseHTTPSStatus($firstZone['id']);
            if ($httpsStatus['success']) {
                $results['tests']['https_check']['status'] = 'passed';
                $results['tests']['https_check']['always_use_https'] = $httpsStatus['always_use_https'];
                $results['tests']['https_check']['value'] = $httpsStatus['value'];
            } else {
                $results['tests']['https_check']['status'] = 'failed';
                $results['tests']['https_check']['error'] = $httpsStatus['error'];
            }
        }
        
        // Test 3: Kiểm tra tốc độ response
        $startTime = microtime(true);
        if (!empty($zones)) {
            $this->getAlwaysUseHTTPSStatus($zones[0]['id']);
        }
        $endTime = microtime(true);
        
        $results['tests']['performance'] = [
            'name' => 'API Response Time Test',
            'status' => 'passed',
            'response_time_ms' => round(($endTime - $startTime) * 1000, 2)
        ];
        
        // Tổng kết
        $passedTests = count(array_filter($results['tests'], fn($test) => $test['status'] === 'passed'));
        $totalTests = count($results['tests']);
        
        $results['summary'] = [
            'total_tests' => $totalTests,
            'passed' => $passedTests,
            'failed' => $totalTests - $passedTests,
            'success_rate' => round(($passedTests / $totalTests) * 100, 1) . '%',
            'overall_status' => $passedTests === $totalTests ? 'all_passed' : 'has_failures'
        ];
        
        return $results;
    }
    
    /**
     * Set SSL/TLS encryption mode
     */
    public function setSSLMode($zoneId, $mode = 'flexible') {
        $validModes = ['off', 'flexible', 'full', 'strict'];
        if (!in_array($mode, $validModes)) {
            throw new Exception("Invalid SSL mode. Valid modes: " . implode(', ', $validModes));
        }
        
        $data = ['value' => $mode];
        return $this->makeRequest("zones/{$zoneId}/settings/ssl", 'PATCH', $data);
    }
    
    /**
     * Set Always Use HTTPS
     */
    public function setAlwaysUseHTTPS($zoneId, $enabled = true) {
        $data = ['value' => $enabled ? 'on' : 'off'];
        return $this->makeRequest("zones/{$zoneId}/settings/always_use_https", 'PATCH', $data);
    }
    
    /**
     * Set HSTS (HTTP Strict Transport Security)
     */
    public function setHSTS($zoneId, $enabled = true, $maxAge = 31536000, $includeSubdomains = true, $nosniff = true) {
        $settings = [
            'value' => [
                'enabled' => $enabled,
                'max_age' => $maxAge,
                'include_subdomains' => $includeSubdomains,
                'nosniff' => $nosniff
            ]
        ];
        
        return $this->makeRequest("zones/{$zoneId}/settings/security_header", 'PATCH', $settings);
    }
    
    /**
     * Set Minimum TLS Version
     */
    public function setMinTLSVersion($zoneId, $version = '1.2') {
        $validVersions = ['1.0', '1.1', '1.2', '1.3'];
        if (!in_array($version, $validVersions)) {
            throw new Exception("Invalid TLS version. Valid versions: " . implode(', ', $validVersions));
        }
        
        $data = ['value' => $version];
        return $this->makeRequest("zones/{$zoneId}/settings/min_tls_version", 'PATCH', $data);
    }
    
    /**
     * Set Opportunistic Encryption
     */
    public function setOpportunisticEncryption($zoneId, $enabled = true) {
        $data = ['value' => $enabled ? 'on' : 'off'];
        return $this->makeRequest("zones/{$zoneId}/settings/opportunistic_encryption", 'PATCH', $data);
    }
    
    /**
     * Set TLS 1.3
     */
    public function setTLS13($zoneId, $enabled = true) {
        $data = ['value' => $enabled ? 'on' : 'off'];
        return $this->makeRequest("zones/{$zoneId}/settings/tls_1_3", 'PATCH', $data);
    }
    
    /**
     * Set Automatic HTTPS Rewrites
     */
    public function setAutomaticHTTPSRewrites($zoneId, $enabled = true) {
        $data = ['value' => $enabled ? 'on' : 'off'];
        return $this->makeRequest("zones/{$zoneId}/settings/automatic_https_rewrites", 'PATCH', $data);
    }
    
    /**
     * Get comprehensive SSL settings
     */
    public function getSSLSettingsDetailed($zoneId) {
        try {
            $allSettings = $this->getSSLSettings($zoneId);
            
            if (!$allSettings || !isset($allSettings['success']) || !$allSettings['success']) {
                return ['success' => false, 'error' => 'Failed to get SSL settings'];
            }
            
            $settings = $allSettings['result'] ?? [];
            $sslSettings = [];
            
            // Parse relevant SSL settings
            foreach ($settings as $setting) {
                $id = $setting['id'] ?? '';
                $value = $setting['value'] ?? null;
                
                switch ($id) {
                    case 'ssl':
                        $sslSettings['ssl_mode'] = $value;
                        break;
                    case 'always_use_https':
                        $sslSettings['always_use_https'] = $value === 'on';
                        break;
                    case 'security_header':
                        $sslSettings['hsts'] = $value;
                        break;
                    case 'min_tls_version':
                        $sslSettings['min_tls_version'] = $value;
                        break;
                    case 'opportunistic_encryption':
                        $sslSettings['opportunistic_encryption'] = $value === 'on';
                        break;
                    case 'tls_1_3':
                        $sslSettings['tls_1_3'] = $value === 'on';
                        break;
                    case 'automatic_https_rewrites':
                        $sslSettings['automatic_https_rewrites'] = $value === 'on';
                        break;
                }
            }
            
            return [
                'success' => true,
                'zone_id' => $zoneId,
                'ssl_settings' => $sslSettings
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Configure multiple SSL settings at once
     */
    public function configureSSLBulk($zoneId, $settings = []) {
        $results = [];
        $errors = [];
        
        try {
            // SSL Mode
            if (isset($settings['ssl_mode'])) {
                $result = $this->setSSLMode($zoneId, $settings['ssl_mode']);
                $results['ssl_mode'] = $result['success'] ?? false;
                if (!$results['ssl_mode']) {
                    $errors[] = 'SSL Mode: ' . ($result['errors'][0]['message'] ?? 'Unknown error');
                }
                usleep(300000); // 0.3s delay
            }
            
            // Always Use HTTPS
            if (isset($settings['always_use_https'])) {
                $result = $this->setAlwaysUseHTTPS($zoneId, $settings['always_use_https']);
                $results['always_use_https'] = $result['success'] ?? false;
                if (!$results['always_use_https']) {
                    $errors[] = 'Always HTTPS: ' . ($result['errors'][0]['message'] ?? 'Unknown error');
                }
                usleep(300000); // 0.3s delay
            }
            
            // Minimum TLS Version
            if (isset($settings['min_tls_version'])) {
                $result = $this->setMinTLSVersion($zoneId, $settings['min_tls_version']);
                $results['min_tls_version'] = $result['success'] ?? false;
                if (!$results['min_tls_version']) {
                    $errors[] = 'Min TLS: ' . ($result['errors'][0]['message'] ?? 'Unknown error');
                }
                usleep(300000); // 0.3s delay
            }
            
            // TLS 1.3
            if (isset($settings['tls_1_3'])) {
                $result = $this->setTLS13($zoneId, $settings['tls_1_3']);
                $results['tls_1_3'] = $result['success'] ?? false;
                if (!$results['tls_1_3']) {
                    $errors[] = 'TLS 1.3: ' . ($result['errors'][0]['message'] ?? 'Unknown error');
                }
                usleep(300000); // 0.3s delay
            }
            
            // Automatic HTTPS Rewrites
            if (isset($settings['automatic_https_rewrites'])) {
                $result = $this->setAutomaticHTTPSRewrites($zoneId, $settings['automatic_https_rewrites']);
                $results['automatic_https_rewrites'] = $result['success'] ?? false;
                if (!$results['automatic_https_rewrites']) {
                    $errors[] = 'Auto HTTPS Rewrites: ' . ($result['errors'][0]['message'] ?? 'Unknown error');
                }
                usleep(300000); // 0.3s delay
            }
            
            // Opportunistic Encryption
            if (isset($settings['opportunistic_encryption'])) {
                $result = $this->setOpportunisticEncryption($zoneId, $settings['opportunistic_encryption']);
                $results['opportunistic_encryption'] = $result['success'] ?? false;
                if (!$results['opportunistic_encryption']) {
                    $errors[] = 'Opportunistic Encryption: ' . ($result['errors'][0]['message'] ?? 'Unknown error');
                }
                usleep(300000); // 0.3s delay
            }
            
            $successCount = count(array_filter($results));
            $totalCount = count($results);
            
            return [
                'success' => empty($errors),
                'zone_id' => $zoneId,
                'results' => $results,
                'summary' => [
                    'total_settings' => $totalCount,
                    'successful' => $successCount,
                    'failed' => $totalCount - $successCount
                ],
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'results' => $results
            ];
        }
    }
    
    /**
     * Get Browser Cache TTL setting
     */
    public function getBrowserCacheTTL($zoneId) {
        try {
            $response = $this->makeRequest("zones/{$zoneId}/settings/browser_cache_ttl");
            return [
                'success' => $response['success'] ?? false,
                'zone_id' => $zoneId,
                'browser_cache_ttl' => $response['result']['value'] ?? null,
                'raw_response' => $response
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'zone_id' => $zoneId,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Set Browser Cache TTL
     */
    public function setBrowserCacheTTL($zoneId, $ttl) {
        $validTTLs = [
            30, 60, 300, 1200, 1800, 3600, 7200, 10800, 14400, 18000, 28800, 43200, 57600,
            72000, 86400, 172800, 259200, 345600, 432000, 691200, 1382400, 2073600, 
            2678400, 5356800, 16070400, 31536000
        ];
        
        if (!in_array($ttl, $validTTLs)) {
            throw new Exception("Invalid Browser Cache TTL. Valid values: " . implode(', ', $validTTLs));
        }
        
        $data = ['value' => $ttl];
        return $this->makeRequest("zones/{$zoneId}/settings/browser_cache_ttl", 'PATCH', $data);
    }
    
    /**
     * Get Edge Cache TTL setting
     */
    public function getEdgeCacheTTL($zoneId) {
        try {
            $response = $this->makeRequest("zones/{$zoneId}/settings/edge_cache_ttl");
            return [
                'success' => $response['success'] ?? false,
                'zone_id' => $zoneId,
                'edge_cache_ttl' => $response['result']['value'] ?? null,
                'raw_response' => $response
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'zone_id' => $zoneId,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Set Edge Cache TTL
     */
    public function setEdgeCacheTTL($zoneId, $ttl) {
        $validTTLs = [
            30, 60, 300, 1200, 1800, 3600, 7200, 10800, 14400, 18000, 28800, 43200, 57600,
            72000, 86400, 172800, 259200, 345600, 432000, 691200, 1382400, 2073600, 
            2678400, 5356800, 16070400, 31536000
        ];
        
        if (!in_array($ttl, $validTTLs)) {
            throw new Exception("Invalid Edge Cache TTL. Valid values: " . implode(', ', $validTTLs));
        }
        
        $data = ['value' => $ttl];
        return $this->makeRequest("zones/{$zoneId}/settings/edge_cache_ttl", 'PATCH', $data);
    }
    
    /**
     * Get Cache Level setting
     */
    public function getCacheLevel($zoneId) {
        try {
            $response = $this->makeRequest("zones/{$zoneId}/settings/cache_level");
            return [
                'success' => $response['success'] ?? false,
                'zone_id' => $zoneId,
                'cache_level' => $response['result']['value'] ?? null,
                'raw_response' => $response
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'zone_id' => $zoneId,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Set Cache Level
     */
    public function setCacheLevel($zoneId, $level) {
        $validLevels = ['aggressive', 'basic', 'simplified'];
        
        if (!in_array($level, $validLevels)) {
            throw new Exception("Invalid Cache Level. Valid values: " . implode(', ', $validLevels));
        }
        
        $data = ['value' => $level];
        return $this->makeRequest("zones/{$zoneId}/settings/cache_level", 'PATCH', $data);
    }
    
    /**
     * Get comprehensive cache settings
     */
    public function getCacheSettingsDetailed($zoneId) {
        try {
            $results = [];
            
            // Get Browser Cache TTL
            $browserTTL = $this->getBrowserCacheTTL($zoneId);
            $results['browser_cache_ttl'] = [
                'success' => $browserTTL['success'],
                'value' => $browserTTL['browser_cache_ttl'],
                'human_readable' => $this->formatTTLToHuman($browserTTL['browser_cache_ttl'])
            ];
            
            // Get Edge Cache TTL
            $edgeTTL = $this->getEdgeCacheTTL($zoneId);
            $results['edge_cache_ttl'] = [
                'success' => $edgeTTL['success'],
                'value' => $edgeTTL['edge_cache_ttl'],
                'human_readable' => $this->formatTTLToHuman($edgeTTL['edge_cache_ttl'])
            ];
            
            // Get Cache Level
            $cacheLevel = $this->getCacheLevel($zoneId);
            $results['cache_level'] = [
                'success' => $cacheLevel['success'],
                'value' => $cacheLevel['cache_level']
            ];
            
            return [
                'success' => true,
                'zone_id' => $zoneId,
                'cache_settings' => $results
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'zone_id' => $zoneId,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Configure multiple cache settings at once
     */
    public function configureCacheSettingsBulk($zoneId, $settings = []) {
        $results = [];
        $errors = [];
        
        try {
            // Browser Cache TTL
            if (isset($settings['browser_cache_ttl'])) {
                $result = $this->setBrowserCacheTTL($zoneId, $settings['browser_cache_ttl']);
                $results['browser_cache_ttl'] = $result['success'] ?? false;
                if (!$results['browser_cache_ttl']) {
                    $errors[] = 'Browser Cache TTL: ' . ($result['errors'][0]['message'] ?? 'Unknown error');
                }
                usleep(300000); // 0.3s delay
            }
            
            // Edge Cache TTL
            if (isset($settings['edge_cache_ttl'])) {
                $result = $this->setEdgeCacheTTL($zoneId, $settings['edge_cache_ttl']);
                $results['edge_cache_ttl'] = $result['success'] ?? false;
                if (!$results['edge_cache_ttl']) {
                    $errors[] = 'Edge Cache TTL: ' . ($result['errors'][0]['message'] ?? 'Unknown error');
                }
                usleep(300000); // 0.3s delay
            }
            
            // Cache Level
            if (isset($settings['cache_level'])) {
                $result = $this->setCacheLevel($zoneId, $settings['cache_level']);
                $results['cache_level'] = $result['success'] ?? false;
                if (!$results['cache_level']) {
                    $errors[] = 'Cache Level: ' . ($result['errors'][0]['message'] ?? 'Unknown error');
                }
                usleep(300000); // 0.3s delay
            }
            
            $successCount = count(array_filter($results));
            $totalCount = count($results);
            
            return [
                'success' => empty($errors),
                'zone_id' => $zoneId,
                'results' => $results,
                'summary' => [
                    'total_settings' => $totalCount,
                    'successful' => $successCount,
                    'failed' => $totalCount - $successCount
                ],
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'zone_id' => $zoneId,
                'error' => $e->getMessage(),
                'results' => $results
            ];
        }
    }
    
    /**
     * Format TTL seconds to human readable format
     */
    private function formatTTLToHuman($ttlSeconds) {
        if (!$ttlSeconds || !is_numeric($ttlSeconds)) {
            return 'Unknown';
        }
        
        $mappings = [
            30 => '30 seconds',
            60 => '1 minute',
            300 => '5 minutes',
            1200 => '20 minutes',
            1800 => '30 minutes',
            3600 => '1 hour',
            7200 => '2 hours',
            10800 => '3 hours',
            14400 => '4 hours',
            18000 => '5 hours',
            28800 => '8 hours',
            43200 => '12 hours',
            57600 => '16 hours',
            72000 => '20 hours',
            86400 => '1 day',
            172800 => '2 days',
            259200 => '3 days',
            345600 => '4 days',
            432000 => '5 days',
            691200 => '8 days',
            1382400 => '16 days',
            2073600 => '24 days',
            2678400 => '1 month',
            5356800 => '2 months',
            16070400 => '6 months',
            31536000 => '1 year'
        ];
        
        return $mappings[$ttlSeconds] ?? $ttlSeconds . ' seconds';
    }
    
    /**
     * Get TTL options for forms
     */
    public function getTTLOptions() {
        return [
            30 => '30 seconds',
            60 => '1 minute',
            300 => '5 minutes',
            1200 => '20 minutes',
            1800 => '30 minutes',
            3600 => '1 hour (Recommended for blogs)',
            7200 => '2 hours',
            10800 => '3 hours',
            14400 => '4 hours',
            18000 => '5 hours',
            28800 => '8 hours',
            43200 => '12 hours',
            57600 => '16 hours',
            72000 => '20 hours',
            86400 => '1 day (Recommended for business)',
            172800 => '2 days',
            259200 => '3 days',
            345600 => '4 days',
            432000 => '5 days',
            691200 => '8 days',
            1382400 => '16 days',
            2073600 => '24 days',
            2678400 => '1 month (Recommended for static)',
            5356800 => '2 months',
            16070400 => '6 months',
            31536000 => '1 year (Maximum)'
        ];
    }
    
    // ==================== SECURITY RULES WITH EXPRESSION SUPPORT ====================
    
    /**
     * Get all firewall rules for a zone
     */
    public function getFirewallRules($zoneId, $useCache = true) {
        try {
            $endpoint = "zones/{$zoneId}/firewall/rules";
            return $this->makeRequest($endpoint, 'GET', null, $useCache, 'security_rules');
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'zone_id' => $zoneId
            ];
        }
    }
    
    /**
     * Get filters (used by firewall rules) for a zone
     */
    public function getFilters($zoneId, $useCache = true) {
        try {
            $endpoint = "zones/{$zoneId}/filters";
            return $this->makeRequest($endpoint, 'GET', null, $useCache, 'security_filters');
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'zone_id' => $zoneId
            ];
        }
    }
    
    /**
     * Create a custom security rule with Expression
     * 
     * @param string $zoneId Zone identifier
     * @param string $expression Cloudflare Expression (e.g., "ip.src eq 192.168.1.1")
     * @param string $action Action to take (block, allow, challenge, js_challenge, managed_challenge, log, bypass)
     * @param string $description Description of the rule
     * @param bool $enabled Whether the rule is enabled
     * @param array $actionParameters Additional action parameters (optional)
     * @return array API response
     */
    public function createSecurityRule($zoneId, $expression, $action = 'block', $description = '', $enabled = true, $actionParameters = []) {
        try {
            // Validate action
            $validActions = ['block', 'allow', 'challenge', 'js_challenge', 'managed_challenge', 'log', 'bypass'];
            if (!in_array($action, $validActions)) {
                throw new Exception("Invalid action. Valid actions: " . implode(', ', $validActions));
            }
            
            // Validate expression
            $expressionValidation = $this->validateExpression($expression);
            if (!$expressionValidation['valid']) {
                return [
                    'success' => false,
                    'error' => 'Invalid expression: ' . $expressionValidation['error'],
                    'expression' => $expression
                ];
            }
            
            // First create a filter
            $filterData = [
                'expression' => $expression,
                'description' => $description ?: 'Security rule filter'
            ];
            
            $filterResponse = $this->makeRequest("zones/{$zoneId}/filters", 'POST', $filterData);
            
            if (!$filterResponse || !isset($filterResponse['success']) || !$filterResponse['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to create filter',
                    'response' => $filterResponse
                ];
            }
            
            $filterId = $filterResponse['result']['id'];
            
            // Then create the firewall rule
            $ruleData = [
                'filter' => ['id' => $filterId],
                'action' => $action,
                'description' => $description ?: "Security rule - {$action} action",
                'paused' => !$enabled
            ];
            
            // Add action parameters if provided
            if (!empty($actionParameters)) {
                $ruleData['action_parameters'] = $actionParameters;
            }
            
            $ruleResponse = $this->makeRequest("zones/{$zoneId}/firewall/rules", 'POST', $ruleData);
            
            if ($ruleResponse && isset($ruleResponse['success']) && $ruleResponse['success']) {
                // Clear cache for security rules
                $this->clearCache('security_rules');
                $this->clearCache('security_filters');
                
                return [
                    'success' => true,
                    'rule' => $ruleResponse['result'],
                    'filter' => $filterResponse['result'],
                    'zone_id' => $zoneId,
                    'expression' => $expression,
                    'action' => $action
                ];
            } else {
                // If firewall rule creation failed, try to delete the filter
                try {
                    $this->makeRequest("zones/{$zoneId}/filters/{$filterId}", 'DELETE');
                } catch (Exception $e) {
                    // Ignore cleanup errors
                }
                
                return [
                    'success' => false,
                    'error' => 'Failed to create firewall rule',
                    'response' => $ruleResponse
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Update an existing security rule
     */
    public function updateSecurityRule($zoneId, $ruleId, $expression = null, $action = null, $description = null, $enabled = null, $actionParameters = null) {
        try {
            // Get existing rule
            $existingRule = $this->makeRequest("zones/{$zoneId}/firewall/rules/{$ruleId}");
            
            if (!$existingRule || !isset($existingRule['success']) || !$existingRule['success']) {
                return [
                    'success' => false,
                    'error' => 'Rule not found',
                    'rule_id' => $ruleId
                ];
            }
            
            $rule = $existingRule['result'];
            $filterId = $rule['filter']['id'];
            
            // Update filter if expression is provided
            if ($expression !== null) {
                $expressionValidation = $this->validateExpression($expression);
                if (!$expressionValidation['valid']) {
                    return [
                        'success' => false,
                        'error' => 'Invalid expression: ' . $expressionValidation['error'],
                        'expression' => $expression
                    ];
                }
                
                $filterData = ['expression' => $expression];
                if ($description !== null) {
                    $filterData['description'] = $description;
                }
                
                $filterResponse = $this->makeRequest("zones/{$zoneId}/filters/{$filterId}", 'PUT', $filterData);
                
                if (!$filterResponse || !isset($filterResponse['success']) || !$filterResponse['success']) {
                    return [
                        'success' => false,
                        'error' => 'Failed to update filter',
                        'response' => $filterResponse
                    ];
                }
            }
            
            // Update rule
            $ruleData = [];
            if ($action !== null) {
                $validActions = ['block', 'allow', 'challenge', 'js_challenge', 'managed_challenge', 'log', 'bypass'];
                if (!in_array($action, $validActions)) {
                    throw new Exception("Invalid action. Valid actions: " . implode(', ', $validActions));
                }
                $ruleData['action'] = $action;
            }
            
            if ($description !== null) {
                $ruleData['description'] = $description;
            }
            
            if ($enabled !== null) {
                $ruleData['paused'] = !$enabled;
            }
            
            if ($actionParameters !== null) {
                $ruleData['action_parameters'] = $actionParameters;
            }
            
            if (!empty($ruleData)) {
                $ruleResponse = $this->makeRequest("zones/{$zoneId}/firewall/rules/{$ruleId}", 'PUT', $ruleData);
                
                if ($ruleResponse && isset($ruleResponse['success']) && $ruleResponse['success']) {
                    // Clear cache
                    $this->clearCache('security_rules');
                    
                    return [
                        'success' => true,
                        'rule' => $ruleResponse['result'],
                        'zone_id' => $zoneId,
                        'rule_id' => $ruleId
                    ];
                } else {
                    return [
                        'success' => false,
                        'error' => 'Failed to update rule',
                        'response' => $ruleResponse
                    ];
                }
            } else {
                return [
                    'success' => true,
                    'message' => 'No changes to apply',
                    'rule' => $rule
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete a security rule
     */
    public function deleteSecurityRule($zoneId, $ruleId, $deleteFilter = true) {
        try {
            // Get rule details first if we need to delete the filter
            $filterId = null;
            if ($deleteFilter) {
                $existingRule = $this->makeRequest("zones/{$zoneId}/firewall/rules/{$ruleId}");
                if ($existingRule && isset($existingRule['success']) && $existingRule['success']) {
                    $filterId = $existingRule['result']['filter']['id'] ?? null;
                }
            }
            
            // Delete the firewall rule
            $ruleResponse = $this->makeRequest("zones/{$zoneId}/firewall/rules/{$ruleId}", 'DELETE');
            
            if (!$ruleResponse || !isset($ruleResponse['success']) || !$ruleResponse['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to delete rule',
                    'response' => $ruleResponse
                ];
            }
            
            // Delete the filter if requested and we have the ID
            $filterDeleted = false;
            if ($deleteFilter && $filterId) {
                try {
                    $filterResponse = $this->makeRequest("zones/{$zoneId}/filters/{$filterId}", 'DELETE');
                    $filterDeleted = $filterResponse && isset($filterResponse['success']) && $filterResponse['success'];
                } catch (Exception $e) {
                    // Filter deletion failed, but rule deletion succeeded
                }
            }
            
            // Clear cache
            $this->clearCache('security_rules');
            if ($filterDeleted) {
                $this->clearCache('security_filters');
            }
            
            return [
                'success' => true,
                'rule_deleted' => true,
                'filter_deleted' => $filterDeleted,
                'zone_id' => $zoneId,
                'rule_id' => $ruleId,
                'filter_id' => $filterId
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Validate Expression syntax
     */
    public function validateExpression($expression) {
        if (empty($expression)) {
            return [
                'valid' => false,
                'error' => 'Expression cannot be empty'
            ];
        }
        
        // Basic validation - check for common expression patterns
        $commonPatterns = [
            // IP address patterns
            '/ip\.src\s+(eq|ne|in)\s+/',
            '/ip\.geoip\.country\s+(eq|ne|in)\s+/',
            '/ip\.geoip\.asnum\s+(eq|ne)\s+/',
            
            // HTTP patterns
            '/http\.request\.method\s+(eq|ne|in)\s+/',
            '/http\.host\s+(eq|ne|contains|matches)\s+/',
            '/http\.request\.uri\.path\s+(eq|ne|contains|matches)\s+/',
            '/http\.user_agent\s+(eq|ne|contains|matches)\s+/',
            
            // Rate limiting patterns
            '/rate\([^)]+\)\s*(eq|ne|gt|lt|ge|le)\s+[0-9]+/',
            
            // Bot detection
            '/cf\.bot_management\.score\s*(eq|ne|gt|lt|ge|le)\s+[0-9]+/',
            
            // Combined patterns
            '/\s+(and|or|not)\s+/'
        ];
        
        $hasValidPattern = false;
        foreach ($commonPatterns as $pattern) {
            if (preg_match($pattern, $expression)) {
                $hasValidPattern = true;
                break;
            }
        }
        
        // Check for basic syntax issues
        $syntaxErrors = [];
        
        // Check for unmatched parentheses
        $openParens = substr_count($expression, '(');
        $closeParens = substr_count($expression, ')');
        if ($openParens !== $closeParens) {
            $syntaxErrors[] = 'Unmatched parentheses';
        }
        
        // Check for empty strings in quotes
        if (preg_match('/["\']["\']/', $expression)) {
            $syntaxErrors[] = 'Empty quoted strings are not allowed';
        }
        
        // Check for invalid operators
        $invalidOps = ['==', '!=', '>=', '<='];
        foreach ($invalidOps as $op) {
            if (strpos($expression, $op) !== false) {
                $syntaxErrors[] = "Use 'eq' instead of '==', 'ne' instead of '!=', 'ge' instead of '>=', 'le' instead of '<='";
                break;
            }
        }
        
        if (!empty($syntaxErrors)) {
            return [
                'valid' => false,
                'error' => 'Syntax errors: ' . implode(', ', $syntaxErrors)
            ];
        }
        
        if (!$hasValidPattern) {
            return [
                'valid' => false,
                'error' => 'Expression does not match common patterns. Please check syntax.',
                'note' => 'Use patterns like: ip.src eq 192.168.1.1, http.host contains "example.com", etc.'
            ];
        }
        
        return [
            'valid' => true,
            'message' => 'Expression syntax appears valid'
        ];
    }
    
    /**
     * Get security rule templates
     */
    /**
     * Get comprehensive security rule templates
     * Hệ thống templates cho các tình huống bảo mật phổ biến
     */
    public function getSecurityRuleTemplates() {
        return [
            // === GEOGRAPHIC PROTECTION ===
            'block_china_russia' => [
                'name' => 'Chặn Trung Quốc & Nga',
                'description' => 'Chặn hoàn toàn lưu lượng từ Trung Quốc và Nga',
                'expression' => 'ip.geoip.country in {"CN" "RU"}',
                'action' => 'block',
                'category' => 'Geographic',
                'severity' => 'high'
            ],
            'allow_vietnam_only' => [
                'name' => 'Chỉ cho phép Việt Nam',
                'description' => 'Chặn tất cả lưu lượng ngoài Việt Nam',
                'expression' => 'ip.geoip.country ne "VN"',
                'action' => 'block',
                'category' => 'Geographic',
                'severity' => 'medium'
            ],
            'block_high_risk_countries' => [
                'name' => 'Chặn các nước có rủi ro cao',
                'description' => 'Chặn các quốc gia thường có hoạt động tấn công',
                'expression' => 'ip.geoip.country in {"CN" "RU" "KP" "IR" "PK"}',
                'action' => 'challenge',
                'category' => 'Geographic',
                'severity' => 'high'
            ],
            'challenge_asean_only' => [
                'name' => 'Chỉ ASEAN được truy cập',
                'description' => 'Challenge lưu lượng ngoài khu vực ASEAN',
                'expression' => 'not ip.geoip.country in {"VN" "TH" "MY" "SG" "ID" "PH" "LA" "MM" "KH" "BN"}',
                'action' => 'managed_challenge',
                'category' => 'Geographic',
                'severity' => 'medium'
            ],

            // === BOT PROTECTION ===
            'block_bad_bots' => [
                'name' => 'Chặn Bot độc hại',
                'description' => 'Chặn bot xấu nhưng cho phép search engines',
                'expression' => 'http.user_agent contains "bot" and not http.user_agent contains "Googlebot" and not http.user_agent contains "Bingbot" and not http.user_agent contains "facebookexternalhit"',
                'action' => 'block',
                'category' => 'Bot Management',
                'severity' => 'high'
            ],
            'challenge_scrapers' => [
                'name' => 'Challenge Scrapers',
                'description' => 'Thử thách các công cụ scraping/crawling',
                'expression' => 'http.user_agent contains "scraper" or http.user_agent contains "spider" or http.user_agent contains "crawler" or http.user_agent contains "harvester"',
                'action' => 'js_challenge',
                'category' => 'Bot Management',
                'severity' => 'medium'
            ],
            'block_automation_tools' => [
                'name' => 'Chặn công cụ tự động',
                'description' => 'Chặn các công cụ automation phổ biến',
                'expression' => 'http.user_agent contains "selenium" or http.user_agent contains "phantomjs" or http.user_agent contains "headless" or http.user_agent contains "python-requests" or http.user_agent contains "curl"',
                'action' => 'block',
                'category' => 'Bot Management',
                'severity' => 'high'
            ],
            'challenge_low_bot_score' => [
                'name' => 'Challenge bot score thấp',
                'description' => 'Thử thách traffic có bot management score thấp',
                'expression' => 'cf.bot_management.score < 30',
                'action' => 'managed_challenge',
                'category' => 'Bot Management',
                'severity' => 'medium'
            ],

            // === WEB APPLICATION SECURITY ===
            'block_sql_injection' => [
                'name' => 'Chặn SQL Injection',
                'description' => 'Chặn các cuộc tấn công SQL injection',
                'expression' => 'http.request.uri.query contains "union select" or http.request.uri.query contains "drop table" or http.request.uri.query contains "insert into" or http.request.uri.query contains "delete from" or http.request.uri.query contains "\'" or http.request.uri.query contains "1=1" or http.request.uri.query contains "or 1=1"',
                'action' => 'block',
                'category' => 'Web Security',
                'severity' => 'critical'
            ],
            'block_xss_attempts' => [
                'name' => 'Chặn XSS Attacks',
                'description' => 'Chặn các cuộc tấn công Cross-Site Scripting',
                'expression' => 'http.request.uri.query contains "<script" or http.request.uri.query contains "javascript:" or http.request.uri.query contains "onload=" or http.request.uri.query contains "onerror=" or http.request.body contains "<script"',
                'action' => 'block',
                'category' => 'Web Security',
                'severity' => 'critical'
            ],
            'block_lfi_attempts' => [
                'name' => 'Chặn Local File Inclusion',
                'description' => 'Chặn các cuộc tấn công Local File Inclusion',
                'expression' => 'http.request.uri.query contains "../" or http.request.uri.query contains "..\\\\..." or http.request.uri.query contains "/etc/passwd" or http.request.uri.query contains "php://filter"',
                'action' => 'block',
                'category' => 'Web Security',
                'severity' => 'critical'
            ],
            'block_command_injection' => [
                'name' => 'Chặn Command Injection',
                'description' => 'Chặn các cuộc tấn công command injection',
                'expression' => 'http.request.uri.query contains ";cat " or http.request.uri.query contains "|cat " or http.request.uri.query contains "&cat " or http.request.uri.query contains ";ls " or http.request.uri.query contains "|ls " or http.request.uri.query contains "&ls " or http.request.uri.query contains "$(cat" or http.request.uri.query contains "`cat"',
                'action' => 'block',
                'category' => 'Web Security',
                'severity' => 'critical'
            ],

            // === RATE LIMITING ===
            'rate_limit_strict' => [
                'name' => 'Rate Limit nghiêm ngặt',
                'description' => 'Chặn IP có hơn 60 requests/phút',
                'expression' => 'rate(ip.src, 1m) > 60',
                'action' => 'block',
                'category' => 'Rate Limiting',
                'severity' => 'medium'
            ],
            'rate_limit_moderate' => [
                'name' => 'Rate Limit vừa phải',
                'description' => 'Challenge IP có hơn 100 requests/phút',
                'expression' => 'rate(ip.src, 1m) > 100',
                'action' => 'challenge',
                'category' => 'Rate Limiting',
                'severity' => 'low'
            ],
            'rate_limit_login' => [
                'name' => 'Rate Limit Login',
                'description' => 'Giới hạn thử đăng nhập',
                'expression' => 'http.request.uri.path contains "/login" and rate(ip.src, 5m) > 10',
                'action' => 'block',
                'category' => 'Rate Limiting',
                'severity' => 'high'
            ],
            'rate_limit_api' => [
                'name' => 'Rate Limit API',
                'description' => 'Giới hạn calls API',
                'expression' => 'http.request.uri.path contains "/api/" and rate(ip.src, 1m) > 30',
                'action' => 'challenge',
                'category' => 'Rate Limiting',
                'severity' => 'medium'
            ],

            // === PATH PROTECTION ===
            'protect_admin_vietnam' => [
                'name' => 'Bảo vệ Admin chỉ VN',
                'description' => 'Chỉ cho phép truy cập admin từ Việt Nam',
                'expression' => 'http.request.uri.path matches "^/admin" and ip.geoip.country ne "VN"',
                'action' => 'block',
                'category' => 'Path Protection',
                'severity' => 'high'
            ],
            'protect_wp_admin' => [
                'name' => 'Bảo vệ WordPress Admin',
                'description' => 'Bảo vệ khu vực quản trị WordPress',
                'expression' => 'http.request.uri.path matches "^/wp-admin" and ip.geoip.country ne "VN"',
                'action' => 'challenge',
                'category' => 'Path Protection',
                'severity' => 'high'
            ],
            'protect_sensitive_files' => [
                'name' => 'Bảo vệ file nhạy cảm',
                'description' => 'Chặn truy cập các file cấu hình',
                'expression' => 'http.request.uri.path contains ".env" or http.request.uri.path contains "config.php" or http.request.uri.path contains ".git" or http.request.uri.path contains ".htaccess" or http.request.uri.path contains "composer.json"',
                'action' => 'block',
                'category' => 'Path Protection',
                'severity' => 'critical'
            ],
            'protect_backup_files' => [
                'name' => 'Chặn truy cập backup',
                'description' => 'Chặn download backup và log files',
                'expression' => 'http.request.uri.path matches "\\.(?:bak|backup|log|sql|tar\\.gz|zip)$"',
                'action' => 'block',
                'category' => 'Path Protection',
                'severity' => 'high'
            ],

            // === VIETNAM SPECIFIC ===
            'allow_office_ips' => [
                'name' => 'Cho phép IP văn phòng',
                'description' => 'Whitelist IP công ty/văn phòng',
                'expression' => 'ip.src in {203.113.131.0/24 14.225.254.0/24}',
                'action' => 'allow',
                'category' => 'IP Whitelist',
                'severity' => 'low'
            ],
            'challenge_outside_business_hours' => [
                'name' => 'Challenge ngoài giờ làm việc',
                'description' => 'Thử thách truy cập ngoài 8h-18h',
                'expression' => 'not cf.edge.server_time matches "^(08|09|10|11|12|13|14|15|16|17):"',
                'action' => 'js_challenge',
                'category' => 'Time Based',
                'severity' => 'low'
            ],
            'block_competitor_analysis' => [
                'name' => 'Chặn phân tích đối thủ',
                'description' => 'Chặn các tool phân tích đối thủ',
                'expression' => 'http.user_agent contains "ahref" or http.user_agent contains "semrush" or http.user_agent contains "majestic" or http.user_agent contains "moz.com" or http.referer contains "ahrefs.com"',
                'action' => 'block',
                'category' => 'Business Protection',
                'severity' => 'medium'
            ],

            // === E-COMMERCE SPECIFIC ===
            'protect_checkout' => [
                'name' => 'Bảo vệ thanh toán',
                'description' => 'Bảo vệ flow thanh toán khỏi bot',
                'expression' => 'http.request.uri.path contains "/checkout" and cf.bot_management.score < 50',
                'action' => 'managed_challenge',
                'category' => 'E-commerce',
                'severity' => 'high'
            ],
            'block_price_scraping' => [
                'name' => 'Chặn scrape giá',
                'description' => 'Chặn bot scraping giá sản phẩm',
                'expression' => 'http.request.uri.path contains "/product" and rate(ip.src, 5m) > 50',
                'action' => 'challenge',
                'category' => 'E-commerce',
                'severity' => 'medium'
            ],

            // === CONTENT PROTECTION ===
            'block_content_theft' => [
                'name' => 'Chặn ăn cắp nội dung',
                'description' => 'Chặn download hàng loạt nội dung',
                'expression' => 'http.request.uri.path matches "\\.(jpg|jpeg|png|pdf|doc|docx)$" and rate(ip.src, 1m) > 20',
                'action' => 'block',
                'category' => 'Content Protection',
                'severity' => 'medium'
            ],
            'block_hotlinking' => [
                'name' => 'Chặn Hotlinking',
                'description' => 'Chặn hotlink hình ảnh từ domain khác',
                'expression' => 'http.request.uri.path matches "\\.(jpg|jpeg|png|gif|svg|webp)$" and not http.referer contains http.host and http.referer ne ""',
                'action' => 'block',
                'category' => 'Content Protection',
                'severity' => 'low'
            ],

            // === EMERGENCY TEMPLATES ===
            'emergency_block_all' => [
                'name' => '🚨 Khẩn cấp: Chặn tất cả',
                'description' => 'Chặn tất cả lưu lượng (emergency mode)',
                'expression' => 'true',
                'action' => 'block',
                'category' => 'Emergency',
                'severity' => 'critical'
            ],
            'emergency_challenge_all' => [
                'name' => '🚨 Khẩn cấp: Challenge tất cả',
                'description' => 'Challenge tất cả requests (DDoS protection)',
                'expression' => 'true',
                'action' => 'managed_challenge',
                'category' => 'Emergency',
                'severity' => 'critical'
            ],
            'emergency_whitelist_only' => [
                'name' => '🚨 Khẩn cấp: Chỉ whitelist',
                'description' => 'Chỉ cho phép IP đã whitelist',
                'expression' => 'not ip.src in {1.1.1.1 8.8.8.8}',
                'action' => 'block',
                'category' => 'Emergency',
                'severity' => 'critical'
            ],

            // === MOBILE & API ===
            'challenge_mobile_apps' => [
                'name' => 'Challenge Mobile Apps',
                'description' => 'Thử thách các mobile app không chính thức',
                'expression' => 'http.user_agent contains "Mobile" and not http.user_agent contains "official-app"',
                'action' => 'js_challenge',
                'category' => 'Mobile',
                'severity' => 'medium'
            ],
            'protect_api_endpoints' => [
                'name' => 'Bảo vệ API Endpoints',
                'description' => 'Bảo vệ API khỏi abuse',
                'expression' => 'http.request.uri.path contains "/api/" and not http.referer contains http.host',
                'action' => 'challenge',
                'category' => 'API',
                'severity' => 'medium'
            ],

            // === LEGACY COMPATIBILITY ===
            'block_country' => [
                'name' => 'Block Country (Legacy)',
                'description' => 'Block traffic from specific countries',
                'expression' => 'ip.geoip.country in {"CN" "RU"}',
                'action' => 'block',
                'category' => 'Geographic',
                'severity' => 'medium'
            ],
            'block_ip_range' => [
                'name' => 'Block IP Range (Legacy)',
                'description' => 'Block specific IP address or range',
                'expression' => 'ip.src eq 192.168.1.1',
                'action' => 'block',
                'category' => 'IP Based',
                'severity' => 'medium'
            ]
        ];
    }
    
    /**
     * Get templates by category
     */
    public function getTemplatesByCategory() {
        $templates = $this->getSecurityRuleTemplates();
        $categorized = [];
        
        foreach ($templates as $key => $template) {
            $category = $template['category'];
            if (!isset($categorized[$category])) {
                $categorized[$category] = [];
            }
            $categorized[$category][$key] = $template;
        }
        
        return $categorized;
    }
    
    /**
     * Get templates by severity
     */
    public function getTemplatesBySeverity($severity = null) {
        $templates = $this->getSecurityRuleTemplates();
        
        if ($severity) {
            return array_filter($templates, function($template) use ($severity) {
                return ($template['severity'] ?? 'medium') === $severity;
            });
        }
        
        $severities = [];
        foreach ($templates as $key => $template) {
            $sev = $template['severity'] ?? 'medium';
            if (!isset($severities[$sev])) {
                $severities[$sev] = [];
            }
            $severities[$sev][$key] = $template;
        }
        
        return $severities;
    }
    
    /**
     * Search templates by keywords
     */
    public function searchTemplates($keywords) {
        $templates = $this->getSecurityRuleTemplates();
        $results = [];
        
        $keywords = strtolower($keywords);
        
        foreach ($templates as $key => $template) {
            $searchText = strtolower(
                $template['name'] . ' ' . 
                $template['description'] . ' ' . 
                $template['category'] . ' ' . 
                $template['expression']
            );
            
            if (strpos($searchText, $keywords) !== false) {
                $results[$key] = $template;
            }
        }
        
        return $results;
    }
    
    /**
     * Create security rule from template
     */
    public function createSecurityRuleFromTemplate($zoneId, $templateKey, $customValues = []) {
        $templates = $this->getSecurityRuleTemplates();
        
        if (!isset($templates[$templateKey])) {
            return [
                'success' => false,
                'error' => 'Template not found',
                'available_templates' => array_keys($templates)
            ];
        }
        
        $template = $templates[$templateKey];
        
        // Apply custom values
        $expression = $customValues['expression'] ?? $template['expression'];
        $action = $customValues['action'] ?? $template['action'];
        $description = $customValues['description'] ?? $template['description'];
        $enabled = $customValues['enabled'] ?? true;
        
        return $this->createSecurityRule($zoneId, $expression, $action, $description, $enabled);
    }
    
    /**
     * Bulk create security rules
     */
    public function bulkCreateSecurityRules($zoneId, $rules) {
        if (empty($rules)) {
            return [
                'success' => false,
                'error' => 'No rules provided'
            ];
        }
        
        $results = [];
        $successful = 0;
        $failed = 0;
        
        foreach ($rules as $index => $ruleData) {
            $expression = $ruleData['expression'] ?? '';
            $action = $ruleData['action'] ?? 'block';
            $description = $ruleData['description'] ?? "Bulk rule #{$index}";
            $enabled = $ruleData['enabled'] ?? true;
            
            $result = $this->createSecurityRule($zoneId, $expression, $action, $description, $enabled);
            
            $results[] = [
                'index' => $index,
                'expression' => $expression,
                'action' => $action,
                'success' => $result['success'],
                'error' => $result['error'] ?? null,
                'rule_id' => $result['success'] ? $result['rule']['id'] : null
            ];
            
            if ($result['success']) {
                $successful++;
            } else {
                $failed++;
            }
            
            // Rate limiting
            usleep(500000); // 0.5 second delay
        }
        
        return [
            'success' => $failed === 0,
            'zone_id' => $zoneId,
            'total_rules' => count($rules),
            'successful' => $successful,
            'failed' => $failed,
            'results' => $results
        ];
    }
    
    /**
     * Get security rules summary
     */
    public function getSecurityRulesSummary($zoneId) {
        try {
            $rules = $this->getFirewallRules($zoneId);
            
            if (!$rules || !isset($rules['success']) || !$rules['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to get firewall rules'
                ];
            }
            
            $rulesList = $rules['result'] ?? [];
            $summary = [
                'total_rules' => count($rulesList),
                'enabled_rules' => 0,
                'disabled_rules' => 0,
                'actions' => [],
                'recent_rules' => []
            ];
            
            foreach ($rulesList as $rule) {
                if ($rule['paused']) {
                    $summary['disabled_rules']++;
                } else {
                    $summary['enabled_rules']++;
                }
                
                $action = $rule['action'];
                $summary['actions'][$action] = ($summary['actions'][$action] ?? 0) + 1;
                
                // Collect recent rules (last 5)
                if (count($summary['recent_rules']) < 5) {
                    $summary['recent_rules'][] = [
                        'id' => $rule['id'],
                        'description' => $rule['description'],
                        'action' => $rule['action'],
                        'enabled' => !$rule['paused'],
                        'created_on' => $rule['created_on'] ?? null
                    ];
                }
            }
            
            return [
                'success' => true,
                'zone_id' => $zoneId,
                'summary' => $summary
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Test security rule (dry run)
     */
    public function testSecurityRule($zoneId, $expression, $testRequests = []) {
        // This simulates how a rule would behave without actually creating it
        $validation = $this->validateExpression($expression);
        
        if (!$validation['valid']) {
            return [
                'success' => false,
                'error' => $validation['error'],
                'expression' => $expression
            ];
        }
        
        $testResults = [];
        
        // If no test requests provided, use default examples
        if (empty($testRequests)) {
            $testRequests = [
                [
                    'ip' => '192.168.1.1',
                    'user_agent' => 'Mozilla/5.0',
                    'country' => 'US',
                    'path' => '/',
                    'method' => 'GET'
                ],
                [
                    'ip' => '10.0.0.1',
                    'user_agent' => 'BadBot/1.0',
                    'country' => 'CN',
                    'path' => '/admin',
                    'method' => 'POST'
                ]
            ];
        }
        
        foreach ($testRequests as $index => $request) {
            $wouldMatch = $this->evaluateExpressionAgainstRequest($expression, $request);
            $testResults[] = [
                'request_index' => $index,
                'request' => $request,
                'would_match' => $wouldMatch,
                'note' => $wouldMatch ? 'This request would trigger the rule' : 'This request would not trigger the rule'
            ];
        }
        
        return [
            'success' => true,
            'expression' => $expression,
            'validation' => $validation,
            'test_results' => $testResults,
            'note' => 'This is a simulation. Actual Cloudflare evaluation may differ.'
        ];
    }
    
    /**
     * Simple expression evaluation for testing (basic simulation)
     */
    private function evaluateExpressionAgainstRequest($expression, $request) {
        // This is a basic simulation - actual Cloudflare evaluation is much more complex
        
        // IP source checks
        if (preg_match('/ip\.src\s+eq\s+(["\']?)([^"\s]+)\1/', $expression, $matches)) {
            return $request['ip'] === $matches[2];
        }
        
        // Country checks
        if (preg_match('/ip\.geoip\.country\s+(eq|in)\s+(["\']?)([^"\s}]+)[\'"}\s]*/', $expression, $matches)) {
            $countries = str_replace(['"', "'", '{', '}'], '', $matches[3]);
            $countryList = preg_split('/\s+/', trim($countries));
            return in_array($request['country'], $countryList);
        }
        
        // User agent checks
        if (preg_match('/http\.user_agent\s+contains\s+["\']([^"\']+)["\']/', $expression, $matches)) {
            return strpos($request['user_agent'], $matches[1]) !== false;
        }
        
        // Path checks
        if (preg_match('/http\.request\.uri\.path\s+matches\s+["\']([^"\']+)["\']/', $expression, $matches)) {
            return preg_match('/' . str_replace('/', '\/', $matches[1]) . '/', $request['path']);
        }
        
        // Default to false for unhandled expressions
        return false;
    }
    
    /**
     * ========================================
     * PAGE RULES METHODS (for Redirect Rules)
     * ========================================
     */
    
    /**
     * Get all page rules for a zone
     */
    public function getPageRules($zoneId, $useCache = true, $page = 1, $perPage = 100) {
        try {
            $endpoint = "zones/{$zoneId}/pagerules?page={$page}&per_page={$perPage}";
            return $this->makeRequest($endpoint, 'GET', null, $useCache, 'page_rules');
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'zone_id' => $zoneId
            ];
        }
    }
    
    /**
     * Create a page rule (primarily for redirects)
     */
    public function createPageRule($zoneId, $ruleData) {
        try {
            $endpoint = "zones/{$zoneId}/pagerules";
            
            // Validate required fields
            if (empty($ruleData['targets'])) {
                // If targets not provided, create from expression
                if (!empty($ruleData['expression'])) {
                    // Parse expression to create targets
                    $target = $this->parseExpressionToTarget($ruleData['expression']);
                    if ($target) {
                        $ruleData['targets'] = [$target];
                    } else {
                        return [
                            'success' => false,
                            'error' => 'Could not parse expression to create target'
                        ];
                    }
                } else {
                    return [
                        'success' => false,
                        'error' => 'Targets or expression required'
                    ];
                }
            }
            
            // Validate actions
            if (empty($ruleData['actions'])) {
                // If actions not provided but we have action_parameters, create actions
                if (!empty($ruleData['action']) && $ruleData['action'] === 'redirect') {
                    $actionParams = $ruleData['action_parameters'] ?? [];
                    if (!empty($actionParams['from_value'])) {
                        $ruleData['actions'] = [[
                            'id' => 'forwarding_url',
                            'value' => $actionParams['from_value']
                        ]];
                    }
                }
                
                if (empty($ruleData['actions'])) {
                    return [
                        'success' => false,
                        'error' => 'Actions required'
                    ];
                }
            }
            
            // Prepare data for Cloudflare API
            $data = [
                'targets' => $ruleData['targets'],
                'actions' => $ruleData['actions'],
                'status' => $ruleData['enabled'] ?? true ? 'active' : 'disabled'
            ];
            
            // Add priority if specified
            if (isset($ruleData['priority'])) {
                $data['priority'] = (int)$ruleData['priority'];
            }
            
            $response = $this->makeRequest($endpoint, 'POST', $data, false);
            
            if ($response && isset($response['success']) && $response['success']) {
                return [
                    'success' => true,
                    'result' => $response['result'] ?? null,
                    'zone_id' => $zoneId
                ];
            } else {
                return [
                    'success' => false,
                    'error' => isset($response['errors'][0]['message']) 
                        ? $response['errors'][0]['message'] 
                        : 'Failed to create page rule',
                    'details' => $response
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'zone_id' => $zoneId
            ];
        }
    }
    
    /**
     * Update a page rule
     */
    public function updatePageRule($zoneId, $ruleId, $ruleData) {
        try {
            $endpoint = "zones/{$zoneId}/pagerules/{$ruleId}";
            
            $data = [];
            
            // Update targets if provided
            if (!empty($ruleData['targets'])) {
                $data['targets'] = $ruleData['targets'];
            }
            
            // Update actions if provided
            if (!empty($ruleData['actions'])) {
                $data['actions'] = $ruleData['actions'];
            }
            
            // Update status if provided
            if (isset($ruleData['enabled'])) {
                $data['status'] = $ruleData['enabled'] ? 'active' : 'disabled';
            }
            
            // Update priority if provided
            if (isset($ruleData['priority'])) {
                $data['priority'] = (int)$ruleData['priority'];
            }
            
            if (empty($data)) {
                return [
                    'success' => false,
                    'error' => 'No data provided to update'
                ];
            }
            
            $response = $this->makeRequest($endpoint, 'PATCH', $data, false);
            
            if ($response && isset($response['success']) && $response['success']) {
                // Clear cache
                if ($this->enableCache) {
                    $this->cache->delete("zones/{$zoneId}/pagerules", [], 'page_rules');
                }
                
                return [
                    'success' => true,
                    'result' => $response['result'] ?? null,
                    'zone_id' => $zoneId,
                    'rule_id' => $ruleId
                ];
            } else {
                return [
                    'success' => false,
                    'error' => isset($response['errors'][0]['message']) 
                        ? $response['errors'][0]['message'] 
                        : 'Failed to update page rule',
                    'details' => $response
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'zone_id' => $zoneId,
                'rule_id' => $ruleId
            ];
        }
    }
    
    /**
     * Delete a page rule
     */
    public function deletePageRule($zoneId, $ruleId) {
        try {
            $endpoint = "zones/{$zoneId}/pagerules/{$ruleId}";
            
            $response = $this->makeRequest($endpoint, 'DELETE', null, false);
            
            if ($response && isset($response['success']) && $response['success']) {
                // Clear cache
                if ($this->enableCache) {
                    $this->cache->delete("zones/{$zoneId}/pagerules", [], 'page_rules');
                }
                
                return [
                    'success' => true,
                    'zone_id' => $zoneId,
                    'rule_id' => $ruleId,
                    'message' => 'Page rule deleted successfully'
                ];
            } else {
                return [
                    'success' => false,
                    'error' => isset($response['errors'][0]['message']) 
                        ? $response['errors'][0]['message'] 
                        : 'Failed to delete page rule',
                    'details' => $response
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'zone_id' => $zoneId,
                'rule_id' => $ruleId
            ];
        }
    }
    
    /**
     * Parse security rule expression to Page Rule target format
     * This is for converting security rule expressions to page rule targets
     */
    private function parseExpressionToTarget($expression) {
        // Handle basic http.host expressions
        if (preg_match('/http\.host\s+eq\s+"([^"]+)"/', $expression, $matches)) {
            $hostname = $matches[1];
            return [
                'target' => 'url',
                'constraint' => [
                    'operator' => 'matches',
                    'value' => "*{$hostname}/*"
                ]
            ];
        }
        
        // Handle more complex expressions - add as needed
        if (preg_match('/http\.host\s+contains\s+"([^"]+)"/', $expression, $matches)) {
            $hostname = $matches[1];
            return [
                'target' => 'url',
                'constraint' => [
                    'operator' => 'matches',
                    'value' => "*{$hostname}*"
                ]
            ];
        }
        
        return null;
    }
    
    /**
     * Create redirect rule helper
     * Simplified method for creating redirect rules
     */
    public function createRedirectRule($zoneId, $sourcePattern, $targetUrl, $statusCode = 301, $preserveQueryString = true) {
        $ruleData = [
            'targets' => [[
                'target' => 'url',
                'constraint' => [
                    'operator' => 'matches',
                    'value' => $sourcePattern
                ]
            ]],
            'actions' => [[
                'id' => 'forwarding_url',
                'value' => [
                    'url' => $targetUrl,
                    'status_code' => (int)$statusCode,
                    'preserve_query_string' => $preserveQueryString
                ]
            ]],
            'enabled' => true
        ];
        
        return $this->createPageRule($zoneId, $ruleData);
    }
    
    /**
     * Bulk create redirect rules
     */
    public function bulkCreateRedirectRules($zoneId, $redirects) {
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        
        foreach ($redirects as $index => $redirect) {
            $sourcePattern = $redirect['source_pattern'] ?? '';
            $targetUrl = $redirect['target_url'] ?? '';
            $statusCode = $redirect['status_code'] ?? 301;
            $preserveQueryString = $redirect['preserve_query_string'] ?? true;
            
            if (empty($sourcePattern) || empty($targetUrl)) {
                $results[] = [
                    'index' => $index,
                    'source_pattern' => $sourcePattern,
                    'target_url' => $targetUrl,
                    'success' => false,
                    'error' => 'Source pattern and target URL are required'
                ];
                $errorCount++;
                continue;
            }
            
            $result = $this->createRedirectRule($zoneId, $sourcePattern, $targetUrl, $statusCode, $preserveQueryString);
            
            $results[] = [
                'index' => $index,
                'source_pattern' => $sourcePattern,
                'target_url' => $targetUrl,
                'success' => $result['success'],
                'error' => $result['success'] ? null : ($result['error'] ?? 'Unknown error'),
                'rule_id' => $result['success'] ? ($result['result']['id'] ?? null) : null
            ];
            
            if ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
            }
            
            // Rate limiting - small delay between requests
            if ($index < count($redirects) - 1) {
                usleep(200000); // 0.2 second delay
            }
        }
        
        return [
            'success' => $errorCount === 0,
            'zone_id' => $zoneId,
            'total_redirects' => count($redirects),
            'successful' => $successCount,
            'failed' => $errorCount,
            'results' => $results
        ];
    }
    
    // ==================== RULESETS API FOR MODERN REDIRECTS ====================
    
    /**
     * Get all rulesets for a zone
     */
    public function getRulesets($zoneId, $useCache = true) {
        try {
            $endpoint = "zones/{$zoneId}/rulesets";
            return $this->makeRequest($endpoint, 'GET', null, $useCache, 'rulesets');
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'zone_id' => $zoneId
            ];
        }
    }
    
    /**
     * Get specific ruleset details
     */
    public function getRuleset($zoneId, $rulesetId, $useCache = true) {
        try {
            $endpoint = "zones/{$zoneId}/rulesets/{$rulesetId}";
            return $this->makeRequest($endpoint, 'GET', null, $useCache, 'ruleset_details');
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'zone_id' => $zoneId,
                'ruleset_id' => $rulesetId
            ];
        }
    }
    
    /**
     * Create a new ruleset with redirect rules
     */
    public function createRedirectRuleset($zoneId, $name, $description, $redirectRules) {
        try {
            // Validate input
            if (empty($name)) {
                throw new Exception('Ruleset name is required');
            }
            
            if (empty($redirectRules) || !is_array($redirectRules)) {
                throw new Exception('Redirect rules array is required');
            }
            
            // Build rules array
            $rules = [];
            foreach ($redirectRules as $index => $rule) {
                $sourceUrl = $rule['source_url'] ?? '';
                $targetUrl = $rule['target_url'] ?? '';
                $statusCode = $rule['status_code'] ?? 301;
                $preservePath = $rule['preserve_path'] ?? true;
                $preserveQuery = $rule['preserve_query'] ?? true;
                $allIncoming = $rule['all_incoming_requests'] ?? false;
                
                if (empty($sourceUrl) || empty($targetUrl)) {
                    continue;
                }
                
                // Check if this is "All incoming requests" rule
                if ($allIncoming) {
                    // All incoming requests rule
                    $expression = "true";
                    
                    // Build action parameters - use concat if preserve path, otherwise direct URL
                    if ($preservePath) {
                        // Ensure HTTPS protocol for concat expression
                        $httpsTargetUrl = preg_replace('#^https?://#i', '', $targetUrl);
                        $httpsTargetUrl = 'https://' . ltrim($httpsTargetUrl, '/');
                        
                        $actionParameters = [
                            'from_value' => [
                                'status_code' => (int)$statusCode,
                                'target_url' => [
                                    'expression' => 'concat("' . rtrim($httpsTargetUrl, '/') . '", http.request.uri.path)'
                                ],
                                'preserve_query_string' => $preserveQuery
                            ]
                        ];
                    } else {
                        $actionParameters = [
                            'from_value' => [
                                'status_code' => (int)$statusCode,
                                'target_url' => [
                                    'value' => $targetUrl
                                ],
                                'preserve_query_string' => $preserveQuery
                            ]
                        ];
                    }
                } else {
                    // Standard redirect
                    $expression = $this->buildRedirectExpression($sourceUrl, $preservePath);
                    
                    // Build action parameters
                    $actionParameters = [
                        'from_value' => [
                            'status_code' => (int)$statusCode,
                            'target_url' => [
                                'value' => $targetUrl
                            ],
                            'preserve_query_string' => $preserveQuery
                        ]
                    ];
                }
                
                $rules[] = [
                    'expression' => $expression,
                    'action' => 'redirect',
                    'action_parameters' => $actionParameters,
                    'description' => $rule['description'] ?? "Redirect from {$sourceUrl} to {$targetUrl}",
                    'enabled' => $rule['enabled'] ?? true
                ];
            }
            
            if (empty($rules)) {
                throw new Exception('No valid redirect rules to create');
            }
            
            // Create ruleset data
            $rulesetData = [
                'name' => $name,
                'description' => $description,
                'kind' => 'zone',
                'phase' => 'http_request_dynamic_redirect',
                'rules' => $rules
            ];
            
            $response = $this->makeRequest("zones/{$zoneId}/rulesets", 'POST', $rulesetData);
            
            if ($response && isset($response['success']) && $response['success']) {
                // Clear cache
                $this->clearCache('rulesets');
                $this->clearCache('ruleset_details');
                
                return [
                    'success' => true,
                    'ruleset' => $response['result'],
                    'zone_id' => $zoneId,
                    'rules_created' => count($rules)
                ];
            } else {
                // Check for quota limit error and try to clean up
                $errorMessage = 'Failed to create ruleset';
                if (isset($response['errors'][0]['message'])) {
                    $errorMessage = $response['errors'][0]['message'];
                    
                    // If it's a quota limit error, try to delete all redirect rulesets and retry
                    if (stripos($errorMessage, 'exceeded maximum number') !== false || 
                        stripos($errorMessage, 'zone rulesets') !== false) {
                        
                        // Try to clean up all redirect rulesets
                        $cleanupResult = $this->findAndDeleteRedirectRulesets($zoneId);
                        if ($cleanupResult['success'] && ($cleanupResult['deleted_count'] ?? 0) > 0) {
                            // Wait a moment for deletions to process
                            sleep(1);
                            
                            // Retry creating the ruleset
                            $retryResponse = $this->makeRequest("zones/{$zoneId}/rulesets", 'POST', $rulesetData);
                            
                            if ($retryResponse && isset($retryResponse['success']) && $retryResponse['success']) {
                                $this->clearCache('rulesets');
                                $this->clearCache('ruleset_details');
                                
                                return [
                                    'success' => true,
                                    'ruleset' => $retryResponse['result'],
                                    'zone_id' => $zoneId,
                                    'rules_created' => count($rules),
                                    'note' => 'Created after cleanup of old rulesets'
                                ];
                            }
                        }
                    }
                }
                
                return [
                    'success' => false,
                    'error' => $errorMessage,
                    'response' => $response
                ];
            }
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Delete a ruleset
     */
    public function deleteRuleset($zoneId, $rulesetId) {
        try {
            $response = $this->makeRequest("zones/{$zoneId}/rulesets/{$rulesetId}", 'DELETE');

            // DELETE returns 204 No Content on success — $response is null/empty, that is fine
            $this->clearCache('rulesets');
            $this->clearCache('ruleset_details');

            return [
                'success' => true,
                'zone_id' => $zoneId,
                'ruleset_id' => $rulesetId,
                'deleted' => true
            ];

        } catch (Exception $e) {
            // Error 10001 = zone entry-point ruleset — cannot be deleted, clear rules instead
            if (strpos($e->getMessage(), '10001') !== false) {
                try {
                    $rulesetDetails = $this->getRuleset($zoneId, $rulesetId, false);
                    $existingRuleset = $rulesetDetails['result'] ?? [];

                    $updateData = [
                        'rules' => []
                    ];

                    // For some zones Cloudflare requires these fields on PUT
                    if (!empty($existingRuleset['name'])) {
                        $updateData['name'] = $existingRuleset['name'];
                    }
                    if (array_key_exists('description', $existingRuleset)) {
                        $updateData['description'] = $existingRuleset['description'];
                    }
                    if (!empty($existingRuleset['kind'])) {
                        $updateData['kind'] = $existingRuleset['kind'];
                    }
                    if (!empty($existingRuleset['phase'])) {
                        $updateData['phase'] = $existingRuleset['phase'];
                    }

                    $clearResp = $this->makeRequest(
                        "zones/{$zoneId}/rulesets/{$rulesetId}",
                        'PUT',
                        $updateData
                    );
                    if ($clearResp && !empty($clearResp['success'])) {
                        $this->clearCache('rulesets');
                        $this->clearCache('ruleset_details');
                        return [
                            'success' => true,
                            'zone_id' => $zoneId,
                            'ruleset_id' => $rulesetId,
                            'deleted' => false,
                            'cleared' => true,
                            'note' => 'Entry-point ruleset — rules cleared (CF does not allow DELETE)'
                        ];
                    }
                } catch (Exception $e2) {
                    return [
                        'success' => false,
                        'error' => $e2->getMessage(),
                        'zone_id' => $zoneId,
                        'ruleset_id' => $rulesetId
                    ];
                }
            }

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'zone_id' => $zoneId,
                'ruleset_id' => $rulesetId
            ];
        }
    }
    
    /**
     * Find and delete old redirect rulesets
     */
    public function findAndDeleteRedirectRulesets($zoneId, $namePattern = null) {
        try {
            $rulesets = $this->getRulesets($zoneId);
            
            if (!$rulesets || !isset($rulesets['success']) || !$rulesets['success']) {
                return [
                    'success' => false,
                    'error' => 'Failed to get rulesets',
                    'zone_id' => $zoneId
                ];
            }
            
            $deletedRulesets = [];
            $skippedRulesets = [];
            $errors = [];
            
            foreach ($rulesets['result'] ?? [] as $ruleset) {
                // Skip if not a redirect ruleset
                if (($ruleset['phase'] ?? '') !== 'http_request_dynamic_redirect') {
                    continue;
                }

                $rulesetName = trim((string)($ruleset['name'] ?? ''));

                // If a custom pattern is provided, only delete matching names.
                if ($namePattern && stripos($rulesetName, $namePattern) === false) {
                    continue;
                }
                
                $deleteResult = $this->deleteRuleset($zoneId, $ruleset['id']);
                
                if ($deleteResult['success']) {
                    $deletedRulesets[] = [
                        'id' => $ruleset['id'],
                        'name' => $ruleset['name'],
                        'description' => $ruleset['description'] ?? ''
                    ];
                } else {
                    $errors[] = [
                        'id' => $ruleset['id'],
                        'name' => $ruleset['name'],
                        'error' => $deleteResult['error']
                    ];
                }
                
                // Small delay between deletions
                usleep(200000); // 0.2 seconds
            }
            
            return [
                'success' => empty($errors),
                'zone_id' => $zoneId,
                'deleted_count' => count($deletedRulesets),
                'deleted_rulesets' => $deletedRulesets,
                'skipped_rulesets' => $skippedRulesets,
                'errors' => $errors
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'zone_id' => $zoneId
            ];
        }
    }

    /**
     * Delete redirect rulesets and legacy Page Rules for a zone.
     */
    public function findAndDeleteOldRedirectRules($zoneId) {
        $rulesetResult = $this->findAndDeleteRedirectRulesets($zoneId);
        $deletedPageRules = [];
        $pageRulesToDelete = [];
        $errors = $rulesetResult['errors'] ?? [];
        if (empty($rulesetResult['success']) && empty($errors)) {
            $errors[] = [
                'name' => 'Redirect Rulesets',
                'error' => $rulesetResult['error'] ?? 'Failed to get or delete redirect rulesets'
            ];
        }
        $page = 1;
        $perPage = 100;
        $totalPages = null;

        do {
            $pageRules = $this->getPageRules($zoneId, false, $page, $perPage);
            if (!$pageRules || empty($pageRules['success'])) {
                $errors[] = [
                    'name' => 'Page Rules',
                    'error' => $pageRules['error'] ?? 'Failed to get Page Rules'
                ];
                break;
            }

            $pageRulesList = $pageRules['result'] ?? [];
            $pageRulesToDelete = array_merge($pageRulesToDelete, $pageRulesList);

            $totalPages = $pageRules['result_info']['total_pages'] ?? $totalPages;
            $hasNextPage = $totalPages !== null
                ? $page < (int)$totalPages
                : count($pageRulesList) >= $perPage;
            $page++;
        } while ($hasNextPage);

        foreach ($pageRulesToDelete as $pageRule) {
            if (empty($pageRule['id'])) {
                $errors[] = [
                    'name' => $pageRule['targets'][0]['constraint']['value'] ?? 'Page Rule',
                    'error' => 'Page Rule is missing its ID'
                ];
                continue;
            }

            $deleteResult = $this->deletePageRule($zoneId, $pageRule['id']);
            if (!empty($deleteResult['success'])) {
                $deletedPageRules[] = [
                    'id' => $pageRule['id'],
                    'targets' => $pageRule['targets'] ?? [],
                    'actions' => $pageRule['actions'] ?? []
                ];
            } else {
                $errors[] = [
                    'name' => $pageRule['targets'][0]['constraint']['value'] ?? 'Page Rule',
                    'id' => $pageRule['id'],
                    'error' => $deleteResult['error'] ?? 'Failed to delete Page Rule'
                ];
            }

            usleep(200000);
        }

        $deletedRulesets = $rulesetResult['deleted_rulesets'] ?? [];
        $deletedRulesetCount = count($deletedRulesets);
        $deletedPageRuleCount = count($deletedPageRules);

        return [
            'success' => empty($errors) && !empty($rulesetResult['success']),
            'zone_id' => $zoneId,
            'deleted_count' => $deletedRulesetCount + $deletedPageRuleCount,
            'deleted_ruleset_count' => $deletedRulesetCount,
            'deleted_page_rule_count' => $deletedPageRuleCount,
            'deleted_rulesets' => $deletedRulesets,
            'deleted_page_rules' => $deletedPageRules,
            'skipped_rulesets' => $rulesetResult['skipped_rulesets'] ?? [],
            'errors' => $errors
        ];
    }
    
    /**
     * Build redirect expression based on URL pattern
     */
    private function buildRedirectExpression($sourceUrl, $preservePath = true) {
        // Remove protocol and normalize
        $cleanUrl = preg_replace('#^https?://#i', '', $sourceUrl);
        $cleanUrl = rtrim($cleanUrl, '/');
        
        if ($preservePath) {
            // Match domain with any path
            return "(http.host eq \"{$cleanUrl}\") or (http.host eq \"www.{$cleanUrl}\")";
        } else {
            // Exact match only
            return "(http.request.full_uri eq \"https://{$cleanUrl}/\") or (http.request.full_uri eq \"http://{$cleanUrl}/\") or (http.request.full_uri eq \"https://www.{$cleanUrl}/\") or (http.request.full_uri eq \"http://www.{$cleanUrl}/\")";
        }
    }
    
    /**
     * Bulk redirect manager - complete workflow
     */
    public function bulkRedirectManager($domains, $targetUrl, $statusCode = 301, $preservePath = true, $preserveQuery = true, $deleteOld = true, $allIncomingRequests = false) {
        $results = [
            'processed_domains' => [],
            'total_domains' => 0,
            'successful_domains' => 0,
            'failed_domains' => 0,
            'total_old_rulesets_deleted' => 0,
            'total_old_page_rules_deleted' => 0,
            'total_new_rulesets_created' => 0,
            'errors' => [],
            'processing_time' => 0
        ];
        
        $startTime = microtime(true);
        
        try {
            // Parse and validate domains
            if (is_string($domains)) {
                $domains = array_filter(array_map('trim', explode("\n", $domains)));
            }
            
            if (empty($domains)) {
                throw new Exception('No domains provided');
            }
            
            $results['total_domains'] = count($domains);
            
            foreach ($domains as $index => $domain) {
                $domainResult = [
                    'domain' => $domain,
                    'zone_id' => null,
                    'old_rulesets_deleted' => 0,
                    'old_page_rules_deleted' => 0,
                    'new_ruleset_created' => false,
                    'new_ruleset_id' => null,
                    'success' => false,
                    'error' => null,
                    'steps' => []
                ];
                
                try {
                    // Step 1: Get Zone ID from domain
                    $domainResult['steps'][] = 'Getting zone ID...';
                    $zoneId = $this->getZoneIdByDomain($domain);
                    
                    if (!$zoneId) {
                        throw new Exception("Zone not found for domain: {$domain}");
                    }
                    
                    $domainResult['zone_id'] = $zoneId;
                    $domainResult['steps'][] = "Zone ID found: {$zoneId}";
                    
                    // Step 2: Delete old redirect rulesets if requested
                    if ($deleteOld) {
                        $domainResult['steps'][] = 'Looking for old redirect rulesets and Page Rules...';
                        $deleteResult = $this->findAndDeleteOldRedirectRules($zoneId);
                        $domainResult['old_rulesets_deleted'] = $deleteResult['deleted_ruleset_count'] ?? 0;
                        $domainResult['old_page_rules_deleted'] = $deleteResult['deleted_page_rule_count'] ?? 0;
                        $results['total_old_rulesets_deleted'] += $domainResult['old_rulesets_deleted'];
                        $results['total_old_page_rules_deleted'] += $domainResult['old_page_rules_deleted'];
                        
                        if ($deleteResult['success']) {
                            $deletedCount = $domainResult['old_rulesets_deleted'] + $domainResult['old_page_rules_deleted'];
                            
                            if ($deletedCount > 0) {
                                $domainResult['steps'][] = "Deleted {$domainResult['old_rulesets_deleted']} redirect ruleset(s) and {$domainResult['old_page_rules_deleted']} Page Rule(s)";
                                // Wait for Cloudflare to process deletions to avoid quota limit
                                sleep(1);
                            } else {
                                $domainResult['steps'][] = 'No old redirect rulesets or Page Rules found';
                            }
                        } else {
                            $domainResult['steps'][] = 'Warning: Could not delete all old rules: ' . ($deleteResult['error'] ?? 'Some deletions failed');
                        }
                    }
                    
                    // Step 3: Create new redirect ruleset
                    $domainResult['steps'][] = 'Creating new redirect ruleset...';
                    
                    if ($allIncomingRequests) {
                        // Clean domain and target URL for display
                        $cleanDomain = preg_replace('#^https?://#i', '', $domain);
                        $cleanDomain = preg_replace('#^www\.#i', '', $cleanDomain);
                        $cleanDomain = explode('/', $cleanDomain)[0];
                        
                        $cleanTargetUrl = preg_replace('#^https?://#i', '', $targetUrl);
                        $cleanTargetUrl = preg_replace('#^www\.#i', '', $cleanTargetUrl);
                        $cleanTargetUrl = rtrim($cleanTargetUrl, '/');
                        
                        // All incoming requests redirect
                        $redirectRules = [[
                            'source_url' => $domain,
                            'target_url' => $targetUrl,
                            'status_code' => $statusCode,
                            'preserve_path' => $preservePath,
                            'preserve_query' => $preserveQuery,
                            'description' => "{$statusCode} [All→{$cleanDomain}] → [{$cleanTargetUrl}]",
                            'enabled' => true,
                            'all_incoming_requests' => true
                        ]];
                        $rulesetDescription = "Chuyển hướng tất cả requests từ {$cleanDomain} đến {$cleanTargetUrl}" . ($preservePath ? " (preserve path)" : "");
                        $rulesetName = "{$statusCode} [All→{$cleanDomain}] → [{$cleanTargetUrl}]";
                    } else {
                        // Clean domain and target URL for display
                        $cleanDomain = preg_replace('#^https?://#i', '', $domain);
                        $cleanDomain = preg_replace('#^www\.#i', '', $cleanDomain);
                        $cleanDomain = explode('/', $cleanDomain)[0];
                        
                        $cleanTargetUrl = preg_replace('#^https?://#i', '', $targetUrl);
                        $cleanTargetUrl = preg_replace('#^www\.#i', '', $cleanTargetUrl);
                        $cleanTargetUrl = rtrim($cleanTargetUrl, '/');
                        
                        // Standard redirect
                        $redirectRules = [[
                            'source_url' => $domain,
                            'target_url' => $targetUrl,
                            'status_code' => $statusCode,
                            'preserve_path' => $preservePath,
                            'preserve_query' => $preserveQuery,
                            'description' => "{$statusCode} [{$cleanDomain}] → [{$cleanTargetUrl}]",
                            'enabled' => true
                        ]];
                        $rulesetDescription = "Tự động chuyển hướng từ {$cleanDomain} đến URL đích {$cleanTargetUrl}";
                        $rulesetName = "{$statusCode} [{$cleanDomain}] → [{$cleanTargetUrl}]";
                    }
                    
                    $createResult = $this->createRedirectRuleset(
                        $zoneId,
                        $rulesetName,
                        $rulesetDescription,
                        $redirectRules
                    );
                    
                    if ($createResult['success']) {
                        $domainResult['new_ruleset_created'] = true;
                        $domainResult['new_ruleset_id'] = $createResult['ruleset']['id'];
                        $domainResult['steps'][] = "New ruleset created: {$createResult['ruleset']['id']}";
                        $domainResult['success'] = true;
                        $results['successful_domains']++;
                        $results['total_new_rulesets_created']++;
                    } else {
                        throw new Exception('Failed to create ruleset: ' . ($createResult['error'] ?? 'Unknown error'));
                    }
                    
                } catch (Exception $e) {
                    $domainResult['error'] = $e->getMessage();
                    $domainResult['steps'][] = 'ERROR: ' . $e->getMessage();
                    $results['failed_domains']++;
                    $results['errors'][] = [
                        'domain' => $domain,
                        'error' => $e->getMessage()
                    ];
                }
                
                $results['processed_domains'][] = $domainResult;
                
                // Progress delay
                if ($index < count($domains) - 1) {
                    usleep(500000); // 0.5 second delay
                }
            }
            
            $results['processing_time'] = round(microtime(true) - $startTime, 2);
            
        } catch (Exception $e) {
            $results['error'] = $e->getMessage();
            $results['processing_time'] = round(microtime(true) - $startTime, 2);
        }
        
        return $results;
    }
    
    /**
     * Get Zone ID by domain name (public method)
     */
    public function getZoneIdByDomain($domain) {
        try {
            // Clean domain - remove protocol, www, paths
            $cleanDomain = preg_replace('#^https?://#i', '', $domain);
            $cleanDomain = preg_replace('#^www\.#i', '', $cleanDomain);
            $cleanDomain = explode('/', $cleanDomain)[0];
            
            // Search for exact zone match
            $zones = $this->searchZones($cleanDomain, 1, 50);
            
            if ($zones && isset($zones['success']) && $zones['success'] && !empty($zones['result'])) {
                foreach ($zones['result'] as $zone) {
                    if (strtolower($zone['name']) === strtolower($cleanDomain)) {
                        return $zone['id'];
                    }
                }
            }
            
            return null;
            
        } catch (Exception $e) {
            return null;
        }
    }
}