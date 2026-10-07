<?php
/**
 * Background Data Loader
 * Tự động tải và cache dữ liệu trong background để cải thiện performance
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/CloudflareAPI.php';

class BackgroundLoader {
    private $cloudflare;
    private $config;
    private $logFile;
    private $isRunning;
    private $lastRun;
    
    public function __construct() {
        $this->cloudflare = new CloudflareAPI();
        $this->config = $this->getDefaultConfig();
        $this->logFile = __DIR__ . '/cache/background_loader.log';
        $this->isRunning = false;
        $this->loadLastRunTime();
    }
    
    /**
     * Default configuration cho background loader
     */
    private function getDefaultConfig() {
        return [
            'enabled' => true,
            'run_interval' => 300, // 5 minutes
            'max_execution_time' => 120, // 2 minutes max runtime
            'concurrent_requests' => 8,
            'tasks' => [
                'zones_list' => [
                    'enabled' => true,
                    'pages' => 3,        // Load first 3 pages của zones list  
                    'per_page' => 50,
                    'priority' => 1      // Highest priority
                ],
                'zone_details' => [
                    'enabled' => true,
                    'max_zones' => 20,   // Load details của top 20 zones
                    'priority' => 2
                ],
                'dns_records' => [
                    'enabled' => true,
                    'max_zones' => 10,   // Load DNS records của top 10 zones
                    'priority' => 3
                ],
                'ssl_info' => [
                    'enabled' => false,  // Disabled by default  
                    'max_zones' => 5,
                    'priority' => 4
                ],
                'search_warm' => [
                    'enabled' => true,
                    'common_queries' => ['com', 'net', 'org', 'io', 'jp'], // Common search terms
                    'priority' => 5
                ]
            ]
        ];
    }
    
    /**
     * Check if background loader should run
     */
    public function shouldRun($force = false) {
        if (!$this->config['enabled'] && !$force) {
            return false;
        }
        
        if ($this->isRunning) {
            return false;
        }
        
        $timeSinceLastRun = time() - $this->lastRun;
        return $force || $timeSinceLastRun >= $this->config['run_interval'];
    }
    
    /**
     * Run background loading process
     */
    public function run($force = false) {
        if (!$this->shouldRun($force)) {
            return [
                'success' => false,
                'message' => 'Background loader not ready to run',
                'time_until_next_run' => max(0, $this->config['run_interval'] - (time() - $this->lastRun))
            ];
        }
        
        $this->isRunning = true;
        $startTime = microtime(true);
        $this->log("Starting background loading process");
        
        try {
            set_time_limit($this->config['max_execution_time']);
            
            $results = [];
            $tasks = $this->getSortedTasks();
            
            foreach ($tasks as $taskName => $taskConfig) {
                if (!$taskConfig['enabled']) {
                    continue;
                }
                
                $taskStartTime = microtime(true);
                $taskResult = $this->executeTask($taskName, $taskConfig);
                $taskDuration = round((microtime(true) - $taskStartTime) * 1000, 2);
                
                $results[$taskName] = [
                    'success' => $taskResult['success'],
                    'duration_ms' => $taskDuration,
                    'items_processed' => $taskResult['items_processed'] ?? 0,
                    'error' => $taskResult['error'] ?? null
                ];
                
                $this->log("Task '{$taskName}' completed in {$taskDuration}ms" . 
                          ($taskResult['success'] ? '' : " with error: " . $taskResult['error']));
                
                // Break if we're running out of time
                if ((microtime(true) - $startTime) > ($this->config['max_execution_time'] - 10)) {
                    $this->log("Stopping due to execution time limit");
                    break;
                }
                
                // Small delay between tasks
                usleep(100000); // 0.1 second
            }
            
            $totalDuration = round((microtime(true) - $startTime) * 1000, 2);
            $this->updateLastRunTime();
            
            $result = [
                'success' => true,
                'total_duration_ms' => $totalDuration,
                'tasks_executed' => count($results),
                'results' => $results,
                'api_stats' => $this->cloudflare->getAPIStats()
            ];
            
            $this->log("Background loading completed in {$totalDuration}ms");
            return $result;
            
        } catch (Exception $e) {
            $this->log("Background loading failed: " . $e->getMessage(), 'ERROR');
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'duration_ms' => round((microtime(true) - $startTime) * 1000, 2)
            ];
        } finally {
            $this->isRunning = false;
        }
    }
    
    /**
     * Get tasks sorted by priority
     */
    private function getSortedTasks() {
        $tasks = $this->config['tasks'];
        uasort($tasks, function($a, $b) {
            return ($a['priority'] ?? 999) - ($b['priority'] ?? 999);
        });
        return $tasks;
    }
    
    /**
     * Execute specific task
     */
    private function executeTask($taskName, $taskConfig) {
        try {
            switch ($taskName) {
                case 'zones_list':
                    return $this->loadZonesList($taskConfig);
                    
                case 'zone_details':
                    return $this->loadZoneDetails($taskConfig);
                    
                case 'dns_records':
                    return $this->loadDNSRecords($taskConfig);
                    
                case 'ssl_info':
                    return $this->loadSSLInfo($taskConfig);
                    
                case 'search_warm':
                    return $this->warmSearchCache($taskConfig);
                    
                default:
                    return [
                        'success' => false,
                        'error' => "Unknown task: {$taskName}"
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
     * Load zones list với multiple pages
     */
    private function loadZonesList($config) {
        $itemsProcessed = 0;
        $pages = $config['pages'] ?? 3;
        $perPage = $config['per_page'] ?? 50;
        
        for ($page = 1; $page <= $pages; $page++) {
            $result = $this->cloudflare->listZones($page, $perPage, false); // Force fresh data
            if ($result && isset($result['result'])) {
                $itemsProcessed += count($result['result']);
            }
            
            // Break if we get less than expected results (end of data)
            if (!$result || !isset($result['result']) || count($result['result']) < $perPage) {
                break;
            }
            
            usleep(200000); // 0.2 second delay between pages
        }
        
        return [
            'success' => true,
            'items_processed' => $itemsProcessed
        ];
    }
    
    /**
     * Load zone details for top zones
     */
    private function loadZoneDetails($config) {
        $maxZones = $config['max_zones'] ?? 20;
        
        // Get list of zones first
        $zonesList = $this->cloudflare->listZones(1, min($maxZones, 50), true); // Use cache for list
        
        if (!$zonesList || !isset($zonesList['result'])) {
            return [
                'success' => false,
                'error' => 'Could not get zones list for details loading'
            ];
        }
        
        $topZones = array_slice($zonesList['result'], 0, $maxZones);
        $zoneIds = array_column($topZones, 'id');
        
        // Bulk load zone details
        $results = $this->cloudflare->bulkGetZoneDetails($zoneIds, false); // Force fresh data
        
        return [
            'success' => true,
            'items_processed' => count(array_filter($results, function($r) {
                return !isset($r['error']);
            }))
        ];
    }
    
    /**
     * Load DNS records for top zones
     */
    private function loadDNSRecords($config) {
        $maxZones = $config['max_zones'] ?? 10;
        
        // Get list of zones first
        $zonesList = $this->cloudflare->listZones(1, min($maxZones, 50), true);
        
        if (!$zonesList || !isset($zonesList['result'])) {
            return [
                'success' => false,
                'error' => 'Could not get zones list for DNS records loading'
            ];
        }
        
        $topZones = array_slice($zonesList['result'], 0, $maxZones);
        $zoneIds = array_column($topZones, 'id');
        
        // Bulk load DNS records
        $results = $this->cloudflare->bulkGetDNSRecords($zoneIds, null, false); // Force fresh data
        
        return [
            'success' => true,
            'items_processed' => count(array_filter($results, function($r) {
                return !isset($r['error']);
            }))
        ];
    }
    
    /**
     * Load SSL info for zones
     */
    private function loadSSLInfo($config) {
        // TODO: Implement SSL info loading khi có SSL API methods
        return [
            'success' => true,
            'items_processed' => 0
        ];
    }
    
    /**
     * Warm search cache với common queries
     */
    private function warmSearchCache($config) {
        $queries = $config['common_queries'] ?? ['com', 'net', 'org'];
        $itemsProcessed = 0;
        
        foreach ($queries as $query) {
            $result = $this->cloudflare->searchZones($query, 1, 20, null, null, false); // Force fresh
            if ($result && isset($result['result'])) {
                $itemsProcessed += count($result['result']);
            }
            usleep(300000); // 0.3 second delay between searches
        }
        
        return [
            'success' => true,
            'items_processed' => $itemsProcessed
        ];
    }
    
    /**
     * Update configuration
     */
    public function updateConfig($newConfig) {
        $this->config = array_merge($this->config, $newConfig);
        return $this;
    }
    
    /**
     * Get current status
     */
    public function getStatus() {
        return [
            'enabled' => $this->config['enabled'],
            'is_running' => $this->isRunning,
            'last_run' => $this->lastRun,
            'last_run_formatted' => date('Y-m-d H:i:s', $this->lastRun),
            'next_run' => $this->lastRun + $this->config['run_interval'],
            'next_run_formatted' => date('Y-m-d H:i:s', $this->lastRun + $this->config['run_interval']),
            'time_until_next_run' => max(0, ($this->lastRun + $this->config['run_interval']) - time()),
            'config' => $this->config
        ];
    }
    
    /**
     * Load last run time
     */
    private function loadLastRunTime() {
        $stateFile = __DIR__ . '/cache/background_loader_state.json';
        if (file_exists($stateFile)) {
            $state = json_decode(file_get_contents($stateFile), true);
            $this->lastRun = $state['last_run'] ?? 0;
        } else {
            $this->lastRun = 0;
        }
    }
    
    /**
     * Update last run time
     */
    private function updateLastRunTime() {
        $this->lastRun = time();
        $stateFile = __DIR__ . '/cache/background_loader_state.json';
        $state = ['last_run' => $this->lastRun];
        file_put_contents($stateFile, json_encode($state));
    }
    
    /**
     * Log messages
     */
    private function log($message, $level = 'INFO') {
        $timestamp = date('Y-m-d H:i:s');
        $logMessage = "[{$timestamp}] [{$level}] {$message}\n";
        file_put_contents($this->logFile, $logMessage, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Get recent logs
     */
    public function getLogs($lines = 100) {
        if (!file_exists($this->logFile)) {
            return [];
        }
        
        $content = file_get_contents($this->logFile);
        $logLines = array_filter(explode("\n", $content));
        return array_slice($logLines, -$lines);
    }
}