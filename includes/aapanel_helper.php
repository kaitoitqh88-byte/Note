<?php
/**
 * aaPanel Configuration Helper
 * Helper functions for managing aaPanel API configuration
 */

class AAPanelConfig {
    private static $configFile = 'aapanel_config.json';
    
    public static function loadServers() {
        if (!file_exists(self::$configFile)) {
            return [];
        }
        
        $config = json_decode(file_get_contents(self::$configFile), true);
        return $config['servers'] ?? [];
    }
    
    public static function getEnabledServers() {
        $servers = self::loadServers();
        return array_filter($servers, function($server) {
            return isset($server['enabled']) && $server['enabled'];
        });
    }
    
    public static function getServer($name) {
        $servers = self::loadServers();
        foreach ($servers as $server) {
            if ($server['name'] === $name) {
                return $server;
            }
        }
        return null;
    }
    
    public static function hasConfiguration() {
        return file_exists(self::$configFile) && count(self::loadServers()) > 0;
    }
    
    public static function makeApiRequest($serverConfig, $endpoint, $params = []) {
        try {
            $requestTime = time();
            $requestData = array_merge([
                'request_time' => $requestTime,
                'request_token' => md5($requestTime . '' . md5($serverConfig['api_secret'] ?? ''))
            ], $params);
            
            $url = rtrim($serverConfig['url'], '/') . '/api/' . ltrim($endpoint, '/');
            
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => http_build_query($requestData),
                CURLOPT_TIMEOUT => $serverConfig['timeout'] ?? 30,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded'
                ]
            ]);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            
            if ($error) {
                return [
                    'success' => false,
                    'error' => 'cURL Error: ' . $error
                ];
            }
            
            if ($httpCode !== 200) {
                return [
                    'success' => false,
                    'error' => 'HTTP Error: ' . $httpCode,
                    'response' => $response
                ];
            }
            
            $data = json_decode($response, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                return [
                    'success' => false,
                    'error' => 'Invalid JSON response',
                    'response' => $response
                ];
            }
            
            return [
                'success' => true,
                'data' => $data
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Exception: ' . $e->getMessage()
            ];
        }
    }
    
    public static function testConnection($serverConfig) {
        $result = self::makeApiRequest($serverConfig, 'GetSystemTotal');
        
        if (!$result['success']) {
            return $result;
        }
        
        $data = $result['data'];
        
        if (isset($data['status']) && $data['status'] === true) {
            return [
                'success' => true,
                'message' => 'Connection successful',
                'data' => $data
            ];
        } else {
            return [
                'success' => false,
                'error' => 'API Error: ' . ($data['msg'] ?? 'Unknown error'),
                'data' => $data
            ];
        }
    }
    
    public static function getSystemInfo($serverConfig) {
        return self::makeApiRequest($serverConfig, 'GetSystemTotal');
    }
    
    public static function getLogFiles($serverConfig) {
        // Get available log files
        $result = self::makeApiRequest($serverConfig, 'GetLogFiles');
        
        if ($result['success'] && isset($result['data']['status']) && $result['data']['status']) {
            return $result['data'];
        }
        
        // If API doesn't support GetLogFiles, return common log paths
        return [
            'status' => true,
            'logs' => [
                '/www/wwwlogs/nginx_error.log',
                '/var/log/nginx/access.log',
                '/var/log/nginx/error.log',
                '/www/server/php/74/var/log/php-fpm.log',
                '/www/server/mysql/logs/mysql-error.log'
            ]
        ];
    }
    
    public static function readLogFile($serverConfig, $logPath, $lines = 100) {
        $params = [
            'path' => $logPath,
            'lines' => $lines
        ];
        
        return self::makeApiRequest($serverConfig, 'GetFileBody', $params);
    }
    
    public static function getProcessList($serverConfig) {
        return self::makeApiRequest($serverConfig, 'GetProcessList');
    }
    
    public static function getServiceStatus($serverConfig) {
        return self::makeApiRequest($serverConfig, 'GetServiceStatus');
    }
    
    public static function getSiteList($serverConfig) {
        return self::makeApiRequest($serverConfig, 'GetSites');
    }
    
    public static function generateMockData($serverName = 'Demo Server') {
        return [
            'system' => [
                'status' => true,
                'data' => [
                    'system' => [
                        'hostname' => $serverName,
                        'os' => 'Ubuntu 20.04.3 LTS',
                        'kernel' => '5.4.0-74-generic',
                        'uptime' => '15 days, 3 hours, 42 minutes'
                    ],
                    'cpu' => [
                        'model' => 'Intel(R) Xeon(R) CPU E5-2686 v4 @ 2.30GHz',
                        'cores' => 4,
                        'usage' => rand(15, 45) . '%'
                    ],
                    'memory' => [
                        'total' => '8GB',
                        'used' => rand(2, 6) . 'GB',
                        'usage' => rand(30, 75) . '%'
                    ],
                    'disk' => [
                        'total' => '100GB',
                        'used' => rand(25, 60) . 'GB',
                        'usage' => rand(25, 60) . '%'
                    ],
                    'network' => [
                        'in' => rand(100, 500) . 'MB',
                        'out' => rand(50, 200) . 'MB'
                    ]
                ]
            ],
            'processes' => [
                'status' => true,
                'data' => [
                    ['pid' => 1234, 'name' => 'nginx', 'cpu' => '2.5%', 'memory' => '45MB', 'status' => 'running'],
                    ['pid' => 5678, 'name' => 'php-fpm', 'cpu' => '1.8%', 'memory' => '128MB', 'status' => 'running'],
                    ['pid' => 9012, 'name' => 'mysql', 'cpu' => '3.2%', 'memory' => '256MB', 'status' => 'running'],
                    ['pid' => 3456, 'name' => 'redis', 'cpu' => '0.5%', 'memory' => '32MB', 'status' => 'running']
                ]
            ],
            'services' => [
                'status' => true,
                'data' => [
                    'nginx' => ['status' => 'running', 'port' => 80],
                    'mysql' => ['status' => 'running', 'port' => 3306],
                    'php-fpm' => ['status' => 'running', 'port' => 9000],
                    'redis' => ['status' => 'running', 'port' => 6379],
                    'memcached' => ['status' => 'stopped', 'port' => 11211]
                ]
            ],
            'sites' => [
                'status' => true,
                'data' => [
                    ['name' => 'example.com', 'status' => 'active', 'ssl' => true, 'php_version' => '7.4'],
                    ['name' => 'demo.site', 'status' => 'active', 'ssl' => false, 'php_version' => '8.0'],
                    ['name' => 'test.local', 'status' => 'paused', 'ssl' => true, 'php_version' => '7.4']
                ]
            ]
        ];
    }
}

?>