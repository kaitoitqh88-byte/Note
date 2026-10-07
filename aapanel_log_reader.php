<?php
/**
 * aaPanel Log Viewer - Enhanced log reading and analysis
 * Đọc và phân tích log files từ aaPanel servers
 */

class aaPanelLogReader {
    private $supportedLogs = [
        'nginx_access' => [
            'pattern' => '/^(\S+) - - \[([^\]]+)\] "([^"]*)" (\d+) (\d+|-) "([^"]*)" "([^"]*)"/',
            'fields' => ['ip', 'timestamp', 'request', 'status', 'size', 'referer', 'user_agent']
        ],
        'nginx_error' => [
            'pattern' => '/^(\d{4}\/\d{2}\/\d{2} \d{2}:\d{2}:\d{2}) \[(\w+)\] (\d+)#(\d+): (.*)/',
            'fields' => ['timestamp', 'level', 'pid', 'tid', 'message']
        ],
        'php_error' => [
            'pattern' => '/^\[([^\]]+)\] ([^:]+): (.*)/',
            'fields' => ['timestamp', 'level', 'message']
        ],
        'mysql_error' => [
            'pattern' => '/^(\d{4}-\d{2}-\d{2}[T\s]\d{2}:\d{2}:\d{2}\.\d+Z?)\s+(\d+)\s+\[([^\]]+)\]\s*(.*)/',
            'fields' => ['timestamp', 'thread_id', 'level', 'message']
        ]
    ];
    
    /**
     * Parse log line based on log type
     */
    public function parseLogLine($line, $logType) {
        $logType = $this->detectLogType($logType);
        
        if (!isset($this->supportedLogs[$logType])) {
            return ['raw' => $line, 'parsed' => false];
        }
        
        $config = $this->supportedLogs[$logType];
        if (preg_match($config['pattern'], $line, $matches)) {
            $parsed = [];
            for ($i = 0; $i < count($config['fields']); $i++) {
                $parsed[$config['fields'][$i]] = $matches[$i + 1] ?? '';
            }
            return ['raw' => $line, 'parsed' => true, 'data' => $parsed, 'type' => $logType];
        }
        
        return ['raw' => $line, 'parsed' => false, 'type' => $logType];
    }
    
    /**
     * Detect log type from filename
     */
    private function detectLogType($filename) {
        $filename = strtolower($filename);
        
        if (strpos($filename, 'access') !== false || strpos($filename, 'nginx') !== false && strpos($filename, 'access') !== false) {
            return 'nginx_access';
        }
        if (strpos($filename, 'error') !== false && strpos($filename, 'nginx') !== false) {
            return 'nginx_error';
        }
        if (strpos($filename, 'php') !== false && strpos($filename, 'error') !== false) {
            return 'php_error';
        }
        if (strpos($filename, 'mysql') !== false || strpos($filename, 'mariadb') !== false) {
            return 'mysql_error';
        }
        
        return 'generic';
    }
    
    /**
     * Analyze log content
     */
    public function analyzeLog($content, $logType) {
        $lines = explode("\n", $content);
        $analysis = [
            'total_lines' => count($lines),
            'error_count' => 0,
            'warning_count' => 0,
            'info_count' => 0,
            'ip_stats' => [],
            'status_codes' => [],
            'error_messages' => [],
            'time_range' => ['start' => null, 'end' => null],
            'top_errors' => [],
            'suspicious_activity' => []
        ];
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            
            $parsed = $this->parseLogLine($line, $logType);
            
            if ($parsed['parsed'] && isset($parsed['data'])) {
                $data = $parsed['data'];
                
                // Count log levels
                if (isset($data['level'])) {
                    $level = strtolower($data['level']);
                    if (in_array($level, ['error', 'err', 'fatal'])) {
                        $analysis['error_count']++;
                    } elseif (in_array($level, ['warn', 'warning'])) {
                        $analysis['warning_count']++;
                    } else {
                        $analysis['info_count']++;
                    }
                }
                
                // IP statistics for access logs
                if (isset($data['ip'])) {
                    $ip = $data['ip'];
                    $analysis['ip_stats'][$ip] = ($analysis['ip_stats'][$ip] ?? 0) + 1;
                }
                
                // Status codes for access logs
                if (isset($data['status'])) {
                    $status = $data['status'];
                    $analysis['status_codes'][$status] = ($analysis['status_codes'][$status] ?? 0) + 1;
                }
                
                // Time range
                if (isset($data['timestamp'])) {
                    $time = $this->parseTimestamp($data['timestamp']);
                    if ($time) {
                        if (!$analysis['time_range']['start'] || $time < $analysis['time_range']['start']) {
                            $analysis['time_range']['start'] = $time;
                        }
                        if (!$analysis['time_range']['end'] || $time > $analysis['time_range']['end']) {
                            $analysis['time_range']['end'] = $time;
                        }
                    }
                }
                
                // Error messages
                if (isset($data['message']) && isset($data['level']) && in_array(strtolower($data['level']), ['error', 'err', 'fatal'])) {
                    $message = substr($data['message'], 0, 100);
                    $analysis['error_messages'][$message] = ($analysis['error_messages'][$message] ?? 0) + 1;
                }
            }
        }
        
        // Sort and limit results
        arsort($analysis['ip_stats']);
        $analysis['ip_stats'] = array_slice($analysis['ip_stats'], 0, 10, true);
        
        arsort($analysis['status_codes']);
        arsort($analysis['error_messages']);
        $analysis['top_errors'] = array_slice($analysis['error_messages'], 0, 5, true);
        
        // Detect suspicious activity
        $analysis['suspicious_activity'] = $this->detectSuspiciousActivity($analysis);
        
        return $analysis;
    }
    
    /**
     * Parse timestamp from various formats
     */
    private function parseTimestamp($timestamp) {
        $formats = [
            'Y-m-d H:i:s',
            'd/M/Y:H:i:s O',
            'Y/m/d H:i:s',
            'Y-m-d\TH:i:s.uP',
            'Y-m-d\TH:i:s\Z'
        ];
        
        foreach ($formats as $format) {
            $time = DateTime::createFromFormat($format, $timestamp);
            if ($time) {
                return $time;
            }
        }
        
        return null;
    }
    
    /**
     * Detect suspicious activity
     */
    private function detectSuspiciousActivity($analysis) {
        $suspicious = [];
        
        // High error rate
        $total = $analysis['error_count'] + $analysis['warning_count'] + $analysis['info_count'];
        if ($total > 0) {
            $errorRate = ($analysis['error_count'] / $total) * 100;
            if ($errorRate > 10) {
                $suspicious[] = "High error rate: {$errorRate}%";
            }
        }
        
        // Multiple requests from same IP
        foreach ($analysis['ip_stats'] as $ip => $count) {
            if ($count > 1000) {
                $suspicious[] = "High request count from IP {$ip}: {$count} requests";
            }
        }
        
        // High 4xx/5xx status codes
        $badStatuses = 0;
        foreach ($analysis['status_codes'] as $status => $count) {
            if ($status >= 400) {
                $badStatuses += $count;
            }
        }
        
        if ($badStatuses > 100) {
            $suspicious[] = "High error status codes: {$badStatuses} requests";
        }
        
        return $suspicious;
    }
    
    /**
     * Format log line for display
     */
    public function formatLogLine($line, $logType) {
        $parsed = $this->parseLogLine($line, $logType);
        
        if (!$parsed['parsed']) {
            return '<span class="log-raw">' . htmlspecialchars($line) . '</span>';
        }
        
        $data = $parsed['data'];
        $formatted = '';
        
        switch ($parsed['type']) {
            case 'nginx_access':
                $statusColor = $this->getStatusColor($data['status'] ?? '');
                $formatted = sprintf(
                    '<span class="log-ip">%s</span> - [<span class="log-time">%s</span>] "<span class="log-request">%s</span>" <span class="log-status %s">%s</span> <span class="log-size">%s</span>',
                    htmlspecialchars($data['ip'] ?? ''),
                    htmlspecialchars($data['timestamp'] ?? ''),
                    htmlspecialchars($data['request'] ?? ''),
                    $statusColor,
                    htmlspecialchars($data['status'] ?? ''),
                    htmlspecialchars($data['size'] ?? '')
                );
                break;
                
            case 'nginx_error':
            case 'php_error':
                $levelColor = $this->getLevelColor($data['level'] ?? '');
                $formatted = sprintf(
                    '[<span class="log-time">%s</span>] <span class="log-level %s">%s</span>: <span class="log-message">%s</span>',
                    htmlspecialchars($data['timestamp'] ?? ''),
                    $levelColor,
                    htmlspecialchars($data['level'] ?? ''),
                    htmlspecialchars($data['message'] ?? '')
                );
                break;
                
            default:
                $formatted = '<span class="log-raw">' . htmlspecialchars($line) . '</span>';
        }
        
        return $formatted;
    }
    
    private function getStatusColor($status) {
        if ($status >= 500) return 'log-error';
        if ($status >= 400) return 'log-warning';
        if ($status >= 300) return 'log-info';
        if ($status >= 200) return 'log-success';
        return '';
    }
    
    private function getLevelColor($level) {
        $level = strtolower($level);
        if (in_array($level, ['error', 'err', 'fatal'])) return 'log-error';
        if (in_array($level, ['warn', 'warning'])) return 'log-warning';
        if (in_array($level, ['info', 'notice'])) return 'log-info';
        if (in_array($level, ['debug'])) return 'log-debug';
        return '';
    }
}

// Handle enhanced log operations
if (isset($_GET['action']) && strpos($_GET['action'], 'enhanced_') === 0) {
    header('Content-Type: application/json');
    
    try {
        $logReader = new aaPanelLogReader();
        
        switch ($_GET['action']) {
            case 'enhanced_analyze_log':
                $content = $_POST['content'] ?? '';
                $logType = $_POST['log_type'] ?? 'generic';
                
                $analysis = $logReader->analyzeLog($content, $logType);
                
                echo json_encode([
                    'success' => true,
                    'analysis' => $analysis
                ]);
                break;
                
            case 'enhanced_format_log':
                $content = $_POST['content'] ?? '';
                $logType = $_POST['log_type'] ?? 'generic';
                
                $lines = explode("\n", $content);
                $formattedLines = [];
                
                foreach ($lines as $line) {
                    if (!empty(trim($line))) {
                        $formattedLines[] = $logReader->formatLogLine($line, $logType);
                    }
                }
                
                echo json_encode([
                    'success' => true,
                    'formatted_content' => implode("\n", $formattedLines)
                ]);
                break;
                
            default:
                throw new Exception('Invalid enhanced action');
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}
?>