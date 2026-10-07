<?php
/**
 * aaPanel Management System với API Secret Key Authentication
 * Quản lý aaPanel và xem log từ các VPS với bảo mật cao
 */
require_once 'config.php';
require_once 'aapanel_log_reader.php';
require_once 'includes/aapanel_helper.php';
require_once 'APISecretKeyManager.php';
require_once 'APIKeyAuthInterface.php';
// Khởi tạo API authentication
session_start();
$keyManager = new APISecretKeyManager();
$authResult = $keyManager->validateSession();

// Kiểm tra authentication
if (!$authResult['valid']) {
    showAPIAuthPage('aapanel');
    exit;
}

// Handle logout action
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $keyManager->destroySession();
    session_destroy();
    header('Location: index.php'); // Redirect to main page instead
    exit;
}

// Handle redirect to dashboard
if (isset($_GET['action']) && $_GET['action'] === 'dashboard') {
    header('Location: index.php');
    exit;
}

// Kiểm tra permissions
$permissions = $authResult['permissions'] ?? [];
if (!in_array('admin', $permissions) && !in_array('vps', $permissions)) {
    header('HTTP/1.0 403 Forbidden');
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - aaPanel Manager</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="row justify-content-center">
                <div class="col-md-6">
                    <div class="card">
                        <div class="card-header bg-danger text-white text-center">
                            <i class="fas fa-lock me-2"></i>Access Denied
                        </div>
                        <div class="card-body text-center">
                            <h5 class="mb-3">Insufficient Permissions</h5>
                            <p>You need 'admin' or 'vps' permissions to access aaPanel Manager.</p>
                            <p><strong>Current permissions:</strong> <?= implode(', ', $permissions) ?: 'None' ?></p>
                            <a href="?action=dashboard" class="btn btn-primary">
                                <i class="fas fa-home me-2"></i>Return to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

// Check if real configuration exists
$hasRealConfig = AAPanelConfig::hasConfiguration();

// Get available servers from configuration
$configuredServers = $hasRealConfig ? AAPanelConfig::getEnabledServers() : [];

// aaPanel API Configuration
class aaPanelAPI {
    private $baseUrl;
    private $apiKey;
    private $apiSecret;
    private $timeout;
    
    public function __construct($panelUrl, $apiKey = null, $apiSecret = null, $timeout = 30) {
        $this->baseUrl = rtrim($panelUrl, '/');
        $this->apiKey = $apiKey;
        $this->apiSecret = $apiSecret;
        $this->timeout = $timeout;
    }
    
    /**
     * Make API request to aaPanel
     */
    private function makeRequest($endpoint, $data = [], $method = 'POST') {
        $url = $this->baseUrl . '/api/' . ltrim($endpoint, '/');
        
        // Add timestamp and signature
        $data['timestamp'] = time();
        if ($this->apiKey && $this->apiSecret) {
            $data['api_key'] = $this->apiKey;
            $data['signature'] = $this->generateSignature($data);
        }
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: aaPanel-Manager/1.0'
            ]
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($error) {
            throw new Exception("cURL Error: $error");
        }
        
        if ($httpCode !== 200) {
            throw new Exception("HTTP Error: $httpCode");
        }
        
        $decoded = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON response: " . json_last_error_msg());
        }
        
        return $decoded;
    }
    
    /**
     * Generate API signature
     */
    private function generateSignature($data) {
        ksort($data);
        $string = '';
        foreach ($data as $key => $value) {
            $string .= $key . '=' . $value . '&';
        }
        $string = rtrim($string, '&');
        return md5($string . $this->apiSecret);
    }
    
    /**
     * Get system information
     */
    public function getSystemInfo() {
        return $this->makeRequest('system/info');
    }
    
    /**
     * Get server loads
     */
    public function getSystemLoad() {
        return $this->makeRequest('system/load');
    }
    
    /**
     * Get log files list
     */
    public function getLogsList() {
        return $this->makeRequest('logs/list');
    }
    
    /**
     * Read log file content
     */
    public function readLogFile($logFile, $lines = 100) {
        return $this->makeRequest('logs/read', [
            'file' => $logFile,
            'lines' => $lines
        ]);
    }
    
    /**
     * Get website list
     */
    public function getWebsites() {
        return $this->makeRequest('sites/list');
    }
    
    /**
     * Get database list
     */
    public function getDatabases() {
        return $this->makeRequest('databases/list');
    }
    
    /**
     * Get process list
     */
    public function getProcessList() {
        return $this->makeRequest('system/processes');
    }
    
    /**
     * Get service status
     */
    public function getServiceStatus($service = null) {
        $data = [];
        if ($service) {
            $data['service'] = $service;
        }
        return $this->makeRequest('system/services', $data);
    }
    
    /**
     * Get access logs
     */
    public function getAccessLogs($site = null, $lines = 100) {
        $data = ['lines' => $lines];
        if ($site) {
            $data['site'] = $site;
        }
        return $this->makeRequest('logs/access', $data);
    }
    
    /**
     * Get error logs
     */
    public function getErrorLogs($lines = 100) {
        return $this->makeRequest('logs/error', ['lines' => $lines]);
    }
    
    /**
     * Test connection to aaPanel
     */
    public function testConnection() {
        try {
            $response = $this->getSystemInfo();
            return [
                'success' => true,
                'message' => 'Connection successful',
                'data' => $response
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
}

/**
 * Parse VPS list and create aaPanel connections
 */
function getaaPanelServers() {
    global $hasRealConfig, $configuredServers;

    // Priority 1: Load from vps.json if available
    $vpsJsonFile = 'vps.json';
    if (file_exists($vpsJsonFile)) {
        $jsonData = json_decode(file_get_contents($vpsJsonFile), true);
        if (is_array($jsonData) && !empty($jsonData)) {
            $servers = [];
            foreach ($jsonData as $index => $server) {
                if (!is_array($server) || empty($server['ip'])) {
                    continue;
                }

                $panelUrl = !empty($server['info']) ? $server['info'] : ('http://' . $server['ip'] . ':7800');
                $servers[] = [
                    'id' => count($servers) + 1,
                    'ip' => $server['ip'],
                    'username' => $server['username'] ?? 'root',
                    'password' => $server['password'] ?? '',
                    'panel_url' => $panelUrl,
                    'name' => !empty($server['name']) ? $server['name'] : ('VPS ' . $server['ip']),
                    'status' => 'unknown',
                    'config_source' => 'vps_json'
                ];
            }

            if (!empty($servers)) {
                return $servers;
            }
        }
    }
    
    // Priority 2: Use real aaPanel API configuration if available
    if ($hasRealConfig && !empty($configuredServers)) {
        $servers = [];
        foreach ($configuredServers as $index => $server) {
            $servers[] = [
                'id' => $index + 1,
                'name' => $server['name'],
                'panel_url' => $server['url'],
                'api_key' => $server['api_key'] ?? '',
                'api_secret' => $server['api_secret'],
                'timeout' => $server['timeout'] ?? 30,
                'status' => 'configured',
                'config_source' => 'api_config'
            ];
        }
        return $servers;
    }
    
    // Priority 3: Fallback to legacy VPS file method
    $servers = [];
    $vpsFile = 'taikhoan_vps.txt';
    
    if (!file_exists($vpsFile)) {
        // Return demo server if no configuration exists
        return [
            [
                'id' => 1,
                'name' => 'Demo Server',
                'panel_url' => 'demo',
                'status' => 'demo',
                'config_source' => 'demo'
            ]
        ];
    }
    
    $lines = file($vpsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    
    foreach ($lines as $index => $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) {
            continue;
        }
        
        $parts = preg_split('/\s+/', $line, 3);
        if (count($parts) >= 3) {
            $servers[] = [
                'id' => $index + 1,
                'ip' => $parts[0],
                'username' => $parts[1],
                'password' => $parts[2],
                'panel_url' => 'http://' . $parts[0] . ':7800', // Default aaPanel port
                'name' => 'VPS Server ' . ($index + 1),
                'status' => 'unknown',
                'config_source' => 'vps_file'
            ];
        }
    }
    
    return $servers;
}

// Handle API requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    if (ob_get_level()) ob_clean();
    
    try {
        switch ($_GET['action']) {
            case 'get_servers':
                $servers = getaaPanelServers();
                echo json_encode([
                    'success' => true,
                    'data' => $servers,
                    'total' => count($servers)
                ]);
                break;
                
            case 'test_connection':
                $serverId = intval($_POST['server_id'] ?? 0);
                $servers = getaaPanelServers();
                
                if (!isset($servers[$serverId - 1])) {
                    throw new Exception('Server not found');
                }
                
                $server = $servers[$serverId - 1];
                $api = new aaPanelAPI($server['panel_url']);
                $result = $api->testConnection();
                
                echo json_encode($result);
                break;
                
            case 'get_system_info':
                $serverId = intval($_POST['server_id'] ?? 0);
                $servers = getaaPanelServers();
                
                if (!isset($servers[$serverId - 1])) {
                    throw new Exception('Server not found');
                }
                
                $server = $servers[$serverId - 1];
                $api = new aaPanelAPI($server['panel_url']);
                $info = $api->getSystemInfo();
                
                echo json_encode([
                    'success' => true,
                    'data' => $info
                ]);
                break;
                
            case 'get_logs_list':
                $serverId = intval($_POST['server_id'] ?? 0);
                $servers = getaaPanelServers();
                
                if (!isset($servers[$serverId - 1])) {
                    throw new Exception('Server not found');
                }
                
                $server = $servers[$serverId - 1];
                $api = new aaPanelAPI($server['panel_url']);
                $logs = $api->getLogsList();
                
                echo json_encode([
                    'success' => true,
                    'data' => $logs
                ]);
                break;
                
            case 'read_log':
                $serverId = intval($_POST['server_id'] ?? 0);
                $logFile = $_POST['log_file'] ?? '';
                $lines = intval($_POST['lines'] ?? 100);
                
                $servers = getaaPanelServers();
                
                if (!isset($servers[$serverId - 1])) {
                    throw new Exception('Server not found');
                }
                
                $server = $servers[$serverId - 1];
                $api = new aaPanelAPI($server['panel_url']);
                $content = $api->readLogFile($logFile, $lines);
                
                echo json_encode([
                    'success' => true,
                    'data' => $content,
                    'log_file' => $logFile,
                    'server' => $server['name']
                ]);
                break;
                
            case 'get_services':
                $serverId = intval($_POST['server_id'] ?? 0);
                $servers = getaaPanelServers();
                
                if (!isset($servers[$serverId - 1])) {
                    throw new Exception('Server not found');
                }
                
                $server = $servers[$serverId - 1];
                $api = new aaPanelAPI($server['panel_url']);
                $services = $api->getServiceStatus();
                
                echo json_encode([
                    'success' => true,
                    'data' => $services
                ]);
                break;
                
            case 'get_websites':
                $serverId = intval($_POST['server_id'] ?? 0);
                $servers = getaaPanelServers();
                
                if (!isset($servers[$serverId - 1])) {
                    throw new Exception('Server not found');
                }
                
                $server = $servers[$serverId - 1];
                $api = new aaPanelAPI($server['panel_url']);
                $websites = $api->getWebsites();
                
                echo json_encode([
                    'success' => true,
                    'data' => $websites
                ]);
                break;
                
            case 'batch_connection_test':
                $servers = getaaPanelServers();
                $results = [];
                
                foreach ($servers as $server) {
                    $api = new aaPanelAPI($server['panel_url']);
                    $result = $api->testConnection();
                    $results[] = [
                        'server' => $server,
                        'result' => $result
                    ];
                }
                
                echo json_encode([
                    'success' => true,
                    'data' => $results
                ]);
                break;
                
            case 'analyze_log_content':
                $content = $_POST['content'] ?? '';
                $logType = $_POST['log_type'] ?? 'generic';
                
                $logReader = new aaPanelLogReader();
                $analysis = $logReader->analyzeLog($content, $logType);
                
                echo json_encode([
                    'success' => true,
                    'analysis' => $analysis
                ]);
                break;
                
            case 'format_log_content':
                $content = $_POST['content'] ?? '';
                $logType = $_POST['log_type'] ?? 'generic';
                
                $logReader = new aaPanelLogReader();
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
                throw new Exception('Invalid action');
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
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>aaPanel Management - Cloudflare Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/common.css" rel="stylesheet">
    <style>
        .server-card {
            transition: all 0.3s ease;
            border-radius: 8px;
            border: 1px solid #dee2e6;
            cursor: pointer;
        }
        .server-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .server-card.active {
            border-color: #0d6efd;
            box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
        }
        .status-badge {
            font-size: 0.8em;
            padding: 0.35em 0.65em;
        }
        .status-online { background-color: #198754; }
        .status-offline { background-color: #dc3545; }
        .status-unknown { background-color: #6c757d; }
        .status-checking { 
            background-color: #ffc107; 
            animation: pulse-warning 1.5s infinite;
        }
        @keyframes pulse-warning {
            0% { opacity: 1; }
            50% { opacity: 0.6; }
            100% { opacity: 1; }
        }
        .log-viewer {
            background: #1a1a1a;
            color: #00ff00;
            font-family: 'Roboto Mono', monospace;
            padding: 1rem;
            border-radius: 4px;
            height: 400px;
            overflow-y: auto;
            white-space: pre-wrap;
            font-size: 0.875rem;
            line-height: 1.4;
        }
        .log-viewer::-webkit-scrollbar {
            width: 8px;
        }
        .log-viewer::-webkit-scrollbar-track {
            background: #2a2a2a;
        }
        .log-viewer::-webkit-scrollbar-thumb {
            background: #555;
            border-radius: 4px;
        }
        .log-line {
            margin-bottom: 2px;
        }
        .log-error { color: #ff6b6b; }
        .log-warning { color: #feca57; }
        .log-info { color: #48cae4; }
        .log-success { color: #51cf66; }
        .log-debug { color: #9c88ff; }
        .log-ip { color: #ff9f43; font-weight: bold; }
        .log-time { color: #54a0ff; }
        .log-request { color: #5f27cd; }
        .log-status { font-weight: bold; }
        .log-size { color: #00d2d3; }
        .log-message { color: #ffffff; }
        .log-level { font-weight: bold; text-transform: uppercase; }
        .log-raw { color: #ddd; }
        .system-info-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 8px;
        }
        .service-item {
            padding: 0.5rem;
            border-radius: 4px;
            margin-bottom: 0.25rem;
            transition: background-color 0.2s;
        }
        .service-item:hover {
            background-color: #f8f9fa;
        }
        .service-running { border-left: 4px solid #198754; }
        .service-stopped { border-left: 4px solid #dc3545; }
        .service-unknown { border-left: 4px solid #6c757d; }
        .tabs-content {
            border: 1px solid #dee2e6;
            border-top: none;
            border-radius: 0 0 0.375rem 0.375rem;
            padding: 1.5rem;
            background: white;
        }
        .nav-tabs .nav-link.active {
            background-color: #fff;
            border-color: #dee2e6 #dee2e6 #fff;
        }
        .real-time-indicator {
            width: 8px;
            height: 8px;
            background: #dc3545;
            border-radius: 50%;
            display: inline-block;
            margin-left: 5px;
            animation: blink 1s infinite;
        }
        .real-time-indicator.active {
            background: #198754;
        }
        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0.3; }
        }
    </style>
</head>
<body>
    <!-- API Secret Key Authentication Header -->
    <nav class="navbar navbar-main navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="aapanel_manager.php">
                <i class="fas fa-server me-2"></i>
                aaPanel Manager
                <span class="badge bg-success ms-2">Secured</span>
            </a>
            
            <div class="navbar-nav ms-auto">
                <div class="nav-item dropdown">
                    <a class="nav-link dropdown-toggle" href="#" id="authDropdown" role="button" data-bs-toggle="dropdown">
                        <i class="fas fa-user-shield me-2"></i>
                        <?= htmlspecialchars($authResult['key_name'] ?? 'Unknown User') ?>
                    </a>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li class="dropdown-header">
                            <i class="fas fa-key me-2"></i>
                            Authentication Info
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li class="dropdown-item-text">
                            <strong>Permissions:</strong><br>
                            <?php foreach ($permissions as $perm): ?>
                                <span class="badge bg-primary me-1"><?= ucfirst($perm) ?></span>
                            <?php endforeach; ?>
                        </li>
                        <li class="dropdown-item-text">
                            <small class="text-muted">
                                <i class="fas fa-clock me-1"></i>
                                Session expires: <?= date('H:i', time() + 3600) ?>
                            </small>
                        </li>
                        <li><hr class="dropdown-divider"></li>


                        <li>
                            <a class="dropdown-item" href="check_301/">
                                <i class="fas fa-link me-2"></i>
                                301 Chain Checker
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="?action=dashboard">
                                <i class="fas fa-home me-2"></i>
                                Dashboard
                            </a>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item text-danger" href="?action=logout">
                                <i class="fas fa-sign-out-alt me-2"></i>
                                Secure Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Authentication Status Alert -->
    <div class="container-fluid">
        <div class="alert alert-success alert-dismissible fade show m-3" role="alert">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h6 class="mb-2">
                        <i class="fas fa-shield-check me-2"></i>
                        API Authenticated Access
                    </h6>
                    <p class="mb-0">
                        <strong>User:</strong> <?= htmlspecialchars($authResult['key_name']) ?> |
                        <strong>Permissions:</strong> aaPanel management với <?= implode(', ', $permissions) ?> access
                    </p>
                </div>
                <div class="col-md-4 text-end">
                    <small class="text-muted">
                        <i class="fas fa-lock me-1"></i>
                        All aaPanel actions are secured and logged
                    </small>
                </div>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    </div>

    <?php 
    $currentPage = 'aapanel-manager'; 
    include 'includes/navigation.php'; 
    ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-server"></i> aaPanel Management</h2>
                    <div class="btn-group">
                        <button class="btn btn-primary" onclick="loadServers()">
                            <i class="fas fa-sync-alt"></i> Tải danh sách
                        </button>
                        <button class="btn btn-success" onclick="testAllConnections()">
                            <i class="fas fa-network-wired"></i> Test tất cả
                        </button>
                        <a class="btn btn-warning" href="aapnel_password.php">
                            <i class="fas fa-key"></i> Cập nhật mật khẩu
                        </a>
                        <button class="btn btn-info" onclick="toggleRealTimeUpdate()">
                            <i class="fas fa-clock"></i> Real-time
                            <span class="real-time-indicator" id="real-time-indicator"></span>
                        </button>
                    </div>
                </div>

                <?php if (!$hasRealConfig): ?>
                <!-- Configuration Status Alert -->
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-info-circle fa-2x me-3"></i>
                        <div class="flex-grow-1">
                            <h5 class="alert-heading mb-1">Demo Mode Active</h5>
                            <p class="mb-2">You're currently viewing demo data. To manage real aaPanel servers:</p>
                            <ol class="mb-2">
                                <!-- Đã xóa liên kết aaPanel API Config theo yêu cầu -->
                                <li>Enable API interface in your aaPanel settings</li>
                                <li>Add your server IPs to the API whitelist</li>
                            </ol>
                        </div>
                        <div>

                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php else: ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="fas fa-check-circle fa-lg me-2"></i>
                        <span><strong>Live Mode:</strong> Connected to <?= count($configuredServers) ?> configured server(s)</span>

                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <div class="row">
                    <!-- Server List Sidebar -->
                    <div class="col-md-3">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">
                                    <i class="fas fa-list"></i> Danh sách Server
                                    <span class="badge bg-secondary" id="server-count">0</span>
                                </h6>
                            </div>
                            <div class="card-body p-0">
                                <div id="servers-list">
                                    <div class="text-center p-3">
                                        <div class="spinner-border spinner-border-sm"></div>
                                        <p class="mt-2 mb-0">Đang tải...</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Quick Actions -->
                        <div class="card mt-3">
                            <div class="card-header">
                                <h6 class="mb-0"><i class="fas fa-bolt"></i> Thao tác nhanh</h6>
                            </div>
                            <div class="card-body">
                                <div class="d-grid gap-2">
                                    <button class="btn btn-outline-primary btn-sm" onclick="refreshCurrentServer()">
                                        <i class="fas fa-refresh"></i> Refresh Server
                                    </button>
                                    <button class="btn btn-outline-info btn-sm" onclick="viewAllLogs()">
                                        <i class="fas fa-file-alt"></i> View All Logs
                                    </button>
                                    <button class="btn btn-outline-warning btn-sm" onclick="exportServerData()">
                                        <i class="fas fa-download"></i> Export Data
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Main Content Area -->
                    <div class="col-md-9">
                        <div id="main-content">
                            <div class="text-center p-5">
                                <i class="fas fa-server fa-3x text-muted mb-3"></i>
                                <h5 class="text-muted">Chọn server để xem thông tin</h5>
                                <p class="text-muted">Click vào một server trong danh sách bên trái để bắt đầu</p>
                            </div>
                        </div>

                        <!-- Server Management Tabs (Hidden initially) -->
                        <div id="server-management" style="display: none;">
                            <div class="card">
                                <div class="card-header">
                                    <h5 id="current-server-name">Server Management</h5>
                                </div>
                                <div class="card-body p-0">
                                    <ul class="nav nav-tabs" id="managementTabs" role="tablist">
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link active" id="overview-tab" data-bs-toggle="tab" data-bs-target="#overview" type="button" role="tab">
                                                <i class="fas fa-tachometer-alt"></i> Tổng quan
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="logs-tab" data-bs-toggle="tab" data-bs-target="#logs" type="button" role="tab">
                                                <i class="fas fa-file-alt"></i> Logs
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="services-tab" data-bs-toggle="tab" data-bs-target="#services" type="button" role="tab">
                                                <i class="fas fa-cogs"></i> Services
                                            </button>
                                        </li>
                                        <li class="nav-item" role="presentation">
                                            <button class="nav-link" id="websites-tab" data-bs-toggle="tab" data-bs-target="#websites" type="button" role="tab">
                                                <i class="fas fa-globe"></i> Websites
                                            </button>
                                        </li>
                                    </ul>

                                    <div class="tab-content tabs-content" id="managementTabsContent">
                                        <!-- Overview Tab -->
                                        <div class="tab-pane fade show active" id="overview" role="tabpanel">
                                            <div class="row">
                                                <div class="col-md-8">
                                                    <div id="system-info-container">
                                                        <div class="text-center">
                                                            <div class="spinner-border"></div>
                                                            <p class="mt-2">Đang tải thông tin hệ thống...</p>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">Kết nối</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <div id="connection-status">
                                                                <div class="text-center">
                                                                    <div class="spinner-border spinner-border-sm"></div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Logs Tab -->
                                        <div class="tab-pane fade" id="logs" role="tabpanel">
                                            <div class="row">
                                                <div class="col-md-3">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">Log Files</h6>
                                                        </div>
                                                        <div class="card-body p-0">
                                                            <div id="logs-list">
                                                                <div class="text-center p-3">
                                                                    Click "Logs" tab to load
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <div class="mt-3">
                                                        <label class="form-label">Lines to read:</label>
                                                        <select class="form-select form-select-sm" id="log-lines-count">
                                                            <option value="50">50 lines</option>
                                                            <option value="100" selected>100 lines</option>
                                                            <option value="200">200 lines</option>
                                                            <option value="500">500 lines</option>
                                                            <option value="1000">1000 lines</option>
                                                        </select>
                                                    </div>
                                                    
                                                    <div class="mt-2">
                                                        <button class="btn btn-primary btn-sm w-100" onclick="refreshCurrentLog()">
                                                            <i class="fas fa-sync-alt"></i> Refresh Log
                                                        </button>
                                                        <button class="btn btn-info btn-sm w-100 mt-1" onclick="analyzeCurrentLog()">
                                                            <i class="fas fa-chart-bar"></i> Analyze Log
                                                        </button>
                                                        <button class="btn btn-warning btn-sm w-100 mt-1" onclick="formatCurrentLog()">
                                                            <i class="fas fa-magic"></i> Format Log
                                                        </button>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                                        <h6 class="mb-0">Log Content</h6>
                                                        <div class="btn-group btn-group-sm">
                                                            <button class="btn btn-outline-secondary" onclick="clearLogViewer()">Clear</button>
                                                            <button class="btn btn-outline-secondary" onclick="downloadCurrentLog()">Download</button>
                                                        </div>
                                                    </div>
                                                    <div class="log-viewer" id="log-viewer">
                                                        <div class="text-center" style="color: #888; padding: 2rem;">
                                                            Select a log file to view content
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-3">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">Log Analysis</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <div id="log-analysis">
                                                                <div class="text-center text-muted">
                                                                    <i class="fas fa-chart-pie fa-2x mb-2"></i>
                                                                    <p>Click "Analyze Log" to view statistics</p>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Services Tab -->
                                        <div class="tab-pane fade" id="services" role="tabpanel">
                                            <div id="services-container">
                                                <div class="text-center">
                                                    Click "Services" tab to load
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Websites Tab -->
                                        <div class="tab-pane fade" id="websites" role="tabpanel">
                                            <div id="websites-container">
                                                <div class="text-center">
                                                    Click "Websites" tab to load
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/common.js"></script>
    <script>
        let currentServer = null;
        let currentLogFile = null;
        let realTimeUpdate = false;
        let realTimeInterval = null;
        let allServers = [];

        // Load servers on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadServers();
            
            // Add event listeners for tabs
            document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
                tab.addEventListener('shown.bs.tab', function(e) {
                    const targetTab = e.target.getAttribute('data-bs-target');
                    handleTabSwitch(targetTab);
                });
            });
        });

        async function loadServers() {
            try {
                const response = await fetch('?action=get_servers');
                const data = await response.json();

                if (data.success) {
                    allServers = data.data;
                    displayServersList(allServers);
                    document.getElementById('server-count').textContent = data.total;
                } else {
                    showNotification('error', 'Lỗi tải danh sách server: ' + data.message);
                }
            } catch (error) {
                showNotification('error', 'Lỗi kết nối: ' + error.message);
            }
        }

        function displayServersList(servers) {
            const container = document.getElementById('servers-list');
            
            if (servers.length === 0) {
                container.innerHTML = `
                    <div class="text-center p-3">
                        <div class="alert alert-warning mb-0">
                            <i class="fas fa-exclamation-triangle"></i>
                            Không tìm thấy server nào
                        </div>
                    </div>
                `;
                return;
            }

            let html = '';
            servers.forEach(server => {
                const isActive = currentServer && currentServer.id === server.id ? 'active' : '';
                const serverInfo = server.ip || server.panel_url || 'N/A';
                
                html += `
                    <div class="server-card ${isActive} p-3 border-bottom" onclick="selectServer(${server.id})">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <strong>${server.name}</strong>
                                <br>
                                <small class="text-muted">${serverInfo}</small>
                            </div>
                            <span class="badge status-${server.status} status-badge" id="server-status-${server.id}">
                                ${server.status}
                            </span>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
        }

        async function selectServer(serverId) {
            const server = allServers.find(s => s.id === serverId);
            if (!server) return;

            currentServer = server;
            
            // Update UI
            document.querySelectorAll('.server-card').forEach(card => {
                card.classList.remove('active');
            });
            event.currentTarget.classList.add('active');
            
            // Show management tabs
            document.getElementById('main-content').style.display = 'none';
            document.getElementById('server-management').style.display = 'block';
            document.getElementById('current-server-name').textContent = `${server.name} (${server.ip})`;
            
            // Load overview data
            await loadServerOverview(serverId);
            
            showNotification('info', `Selected server: ${server.name}`);
        }

        async function loadServerOverview(serverId) {
            const container = document.getElementById('system-info-container');
            const connectionContainer = document.getElementById('connection-status');
            
            container.innerHTML = '<div class="text-center"><div class="spinner-border"></div></div>';
            connectionContainer.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm"></div></div>';

            try {
                // Test connection first
                const connectionResponse = await fetch('?action=test_connection', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `server_id=${serverId}`
                });
                
                const connectionData = await connectionResponse.json();
                
                if (connectionData.success) {
                    connectionContainer.innerHTML = `
                        <div class="text-success">
                            <i class="fas fa-check-circle"></i> Connected
                            <br><small>Panel accessible</small>
                        </div>
                    `;
                    updateServerStatus(serverId, 'online');
                    
                    // Load system info if connected
                    const infoResponse = await fetch('?action=get_system_info', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `server_id=${serverId}`
                    });
                    
                    const infoData = await infoResponse.json();
                    if (infoData.success) {
                        displaySystemInfo(infoData.data);
                    } else {
                        container.innerHTML = '<div class="alert alert-warning">Unable to load system info</div>';
                    }
                } else {
                    connectionContainer.innerHTML = `
                        <div class="text-danger">
                            <i class="fas fa-times-circle"></i> Disconnected
                            <br><small>${connectionData.message}</small>
                        </div>
                    `;
                    updateServerStatus(serverId, 'offline');
                    container.innerHTML = '<div class="alert alert-danger">Server not accessible</div>';
                }

            } catch (error) {
                connectionContainer.innerHTML = `
                    <div class="text-danger">
                        <i class="fas fa-exclamation-triangle"></i> Error
                        <br><small>${error.message}</small>
                    </div>
                `;
                updateServerStatus(serverId, 'offline');
                container.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
            }
        }

        function displaySystemInfo(data) {
            const container = document.getElementById('system-info-container');
            
            // This is a mock display since we don't have real aaPanel API
            const mockData = {
                hostname: currentServer.name,
                os: 'Ubuntu 20.04 LTS',
                uptime: '15 days, 3 hours',
                load: '0.45, 0.52, 0.48',
                memory: { used: '2.1GB', total: '4GB', percent: 52 },
                disk: { used: '45GB', total: '80GB', percent: 56 },
                cpu_percent: 23
            };
            
            container.innerHTML = `
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <div class="system-info-card p-3">
                            <h6><i class="fas fa-microchip"></i> CPU Usage</h6>
                            <div class="progress mb-2">
                                <div class="progress-bar" style="width: ${mockData.cpu_percent}%">${mockData.cpu_percent}%</div>
                            </div>
                            <small>Load Average: ${mockData.load}</small>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="system-info-card p-3">
                            <h6><i class="fas fa-memory"></i> Memory Usage</h6>
                            <div class="progress mb-2">
                                <div class="progress-bar" style="width: ${mockData.memory.percent}%">${mockData.memory.percent}%</div>
                            </div>
                            <small>${mockData.memory.used} / ${mockData.memory.total}</small>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="system-info-card p-3">
                            <h6><i class="fas fa-hdd"></i> Disk Usage</h6>
                            <div class="progress mb-2">
                                <div class="progress-bar" style="width: ${mockData.disk.percent}%">${mockData.disk.percent}%</div>
                            </div>
                            <small>${mockData.disk.used} / ${mockData.disk.total}</small>
                        </div>
                    </div>
                    <div class="col-md-6 mb-3">
                        <div class="system-info-card p-3">
                            <h6><i class="fas fa-clock"></i> Uptime</h6>
                            <p class="mb-1">${mockData.uptime}</p>
                            <small>OS: ${mockData.os}</small>
                        </div>
                    </div>
                </div>
            `;
        }

        function updateServerStatus(serverId, status) {
            const statusBadge = document.getElementById(`server-status-${serverId}`);
            if (statusBadge) {
                statusBadge.textContent = status;
                statusBadge.className = `badge status-${status} status-badge`;
            }
            
            // Update in allServers array
            const serverIndex = allServers.findIndex(s => s.id === serverId);
            if (serverIndex !== -1) {
                allServers[serverIndex].status = status;
            }
        }

        function handleTabSwitch(targetTab) {
            if (!currentServer) return;
            
            switch (targetTab) {
                case '#logs':
                    loadLogsList();
                    break;
                case '#services':
                    loadServices();
                    break;
                case '#websites':
                    loadWebsites();
                    break;
            }
        }

        async function loadLogsList() {
            const container = document.getElementById('logs-list');
            
            // Mock logs list since we don't have real API
            const mockLogs = [
                { name: 'nginx_access.log', size: '2.3MB', modified: '2 mins ago' },
                { name: 'nginx_error.log', size: '156KB', modified: '5 mins ago' },
                { name: 'php_error.log', size: '89KB', modified: '1 hour ago' },
                { name: 'mysql_error.log', size: '45KB', modified: '3 hours ago' },
                { name: 'system.log', size: '1.2MB', modified: '10 mins ago' },
                { name: 'bt_panel.log', size: '234KB', modified: '1 hour ago' }
            ];
            
            let html = '';
            mockLogs.forEach(log => {
                html += `
                    <div class="list-group-item list-group-item-action" onclick="loadLogContent('${log.name}')">
                        <div class="d-flex w-100 justify-content-between">
                            <h6 class="mb-1">${log.name}</h6>
                            <small class="text-muted">${log.modified}</small>
                        </div>
                        <small class="text-muted">${log.size}</small>
                    </div>
                `;
            });
            
            container.innerHTML = '<div class="list-group">' + html + '</div>';
        }

        async function loadLogContent(logFile) {
            currentLogFile = logFile;
            const container = document.getElementById('log-viewer');
            const lines = document.getElementById('log-lines-count').value;
            
            container.innerHTML = '<div class="text-center" style="color: #ffc107; padding: 2rem;">Loading log content...</div>';
            
            // Mock log content since we don't have real API
            setTimeout(() => {
                const mockContent = generateMockLogContent(logFile, parseInt(lines));
                displayLogContent(mockContent);
            }, 1000);
        }

        function generateMockLogContent(logFile, lineCount) {
            const logTypes = ['INFO', 'WARN', 'ERROR', 'DEBUG'];
            const logMessages = [
                'Server started successfully',
                'Database connection established', 
                'User authentication successful',
                'Failed to connect to database',
                'Memory usage warning: 85%',
                'Backup completed successfully',
                'SSL certificate renewed',
                'Disk space warning: 90% full',
                'Service restart initiated',
                'Performance optimization applied'
            ];
            
            let content = '';
            const now = new Date();
            
            for (let i = 0; i < lineCount; i++) {
                const timestamp = new Date(now.getTime() - (i * 1000 * 60)).toISOString();
                const level = logTypes[Math.floor(Math.random() * logTypes.length)];
                const message = logMessages[Math.floor(Math.random() * logMessages.length)];
                const ip = `192.168.1.${Math.floor(Math.random() * 255)}`;
                
                content = `[${timestamp}] ${level}: ${message} (IP: ${ip})\n` + content;
            }
            
            return content;
        }

        function displayLogContent(content) {
            const container = document.getElementById('log-viewer');
            
            // Add syntax highlighting
            const highlightedContent = content
                .replace(/\[.*?\]/g, '<span class="log-info">$&</span>')
                .replace(/ERROR:/g, '<span class="log-error">ERROR:</span>')
                .replace(/WARN:/g, '<span class="log-warning">WARN:</span>')
                .replace(/INFO:/g, '<span class="log-success">INFO:</span>');
                
            container.innerHTML = highlightedContent;
            container.scrollTop = container.scrollHeight;
        }

        async function loadServices() {
            const container = document.getElementById('services-container');
            
            // Mock services data
            const mockServices = [
                { name: 'nginx', status: 'running', description: 'Web Server' },
                { name: 'mysql', status: 'running', description: 'Database Server' },
                { name: 'php-fpm', status: 'running', description: 'PHP FastCGI Process Manager' },
                { name: 'redis', status: 'stopped', description: 'In-Memory Data Store' },
                { name: 'memcached', status: 'running', description: 'Memory Object Caching' },
                { name: 'ssh', status: 'running', description: 'SSH Server' }
            ];
            
            let html = '<div class="row">';
            mockServices.forEach(service => {
                const statusClass = service.status === 'running' ? 'service-running' : 'service-stopped';
                const statusBadge = service.status === 'running' ? 'bg-success' : 'bg-danger';
                
                html += `
                    <div class="col-md-6 mb-3">
                        <div class="service-item ${statusClass}">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong>${service.name}</strong>
                                    <br><small class="text-muted">${service.description}</small>
                                </div>
                                <span class="badge ${statusBadge}">${service.status}</span>
                            </div>
                        </div>
                    </div>
                `;
            });
            html += '</div>';
            
            container.innerHTML = html;
        }

        async function loadWebsites() {
            const container = document.getElementById('websites-container');
            
            // Mock websites data
            const mockWebsites = [
                { domain: 'example1.com', status: 'running', ssl: true, created: '2024-01-15' },
                { domain: 'example2.com', status: 'running', ssl: false, created: '2024-02-20' },
                { domain: 'api.example.com', status: 'stopped', ssl: true, created: '2024-03-01' }
            ];
            
            let html = `
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Domain</th>
                                <th>Status</th>
                                <th>SSL</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
            `;
            
            mockWebsites.forEach(site => {
                const statusBadge = site.status === 'running' ? 'bg-success' : 'bg-danger';
                const sslIcon = site.ssl ? '<i class="fas fa-lock text-success"></i>' : '<i class="fas fa-unlock text-muted"></i>';
                
                html += `
                    <tr>
                        <td><strong>${site.domain}</strong></td>
                        <td><span class="badge ${statusBadge}">${site.status}</span></td>
                        <td>${sslIcon}</td>
                        <td>${site.created}</td>
                        <td>
                            <div class="btn-group">
                                <button class="btn btn-sm btn-outline-primary">View</button>
                                <button class="btn btn-sm btn-outline-info">Logs</button>
                                <button class="btn btn-sm btn-outline-warning">Config</button>
                            </div>
                        </td>
                    </tr>
                `;
            });
            
            html += '</tbody></table></div>';
            container.innerHTML = html;
        }

        function refreshCurrentLog() {
            if (currentLogFile) {
                loadLogContent(currentLogFile);
            } else {
                showNotification('warning', 'Chọn một log file trước');
            }
        }

        async function analyzeCurrentLog() {
            if (!currentLogFile) {
                showNotification('warning', 'Chọn một log file trước khi analyze');
                return;
            }

            const logContent = document.getElementById('log-viewer').textContent;
            if (!logContent || logContent.trim() === 'Select a log file to view content') {
                showNotification('warning', 'Không có nội dung log để analyze');
                return;
            }

            const analysisContainer = document.getElementById('log-analysis');
            analysisContainer.innerHTML = '<div class="text-center"><div class="spinner-border spinner-border-sm"></div><p class="mt-2">Analyzing...</p></div>';

            try {
                const formData = new FormData();
                formData.append('content', logContent);
                formData.append('log_type', currentLogFile);

                const response = await fetch('?action=analyze_log_content', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    displayLogAnalysis(data.analysis);
                } else {
                    analysisContainer.innerHTML = '<div class="alert alert-danger">Analysis failed</div>';
                }
            } catch (error) {
                analysisContainer.innerHTML = '<div class="alert alert-danger">Analysis error: ' + error.message + '</div>';
            }
        }

        function displayLogAnalysis(analysis) {
            const container = document.getElementById('log-analysis');
            
            let html = `
                <div class="mb-3">
                    <h6>Statistics</h6>
                    <div class="row text-center">
                        <div class="col-4">
                            <div class="bg-light p-2 rounded">
                                <strong>${analysis.total_lines}</strong>
                                <br><small>Total Lines</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-danger text-white p-2 rounded">
                                <strong>${analysis.error_count}</strong>
                                <br><small>Errors</small>
                            </div>
                        </div>
                        <div class="col-4">
                            <div class="bg-warning p-2 rounded">
                                <strong>${analysis.warning_count}</strong>
                                <br><small>Warnings</small>
                            </div>
                        </div>
                    </div>
                </div>
            `;

            if (Object.keys(analysis.ip_stats).length > 0) {
                html += `
                    <div class="mb-3">
                        <h6>Top IPs</h6>
                        <div class="small">
                `;
                Object.entries(analysis.ip_stats).slice(0, 5).forEach(([ip, count]) => {
                    html += `<div class="d-flex justify-content-between"><span>${ip}</span><span>${count}</span></div>`;
                });
                html += '</div></div>';
            }

            if (Object.keys(analysis.status_codes).length > 0) {
                html += `
                    <div class="mb-3">
                        <h6>Status Codes</h6>
                        <div class="small">
                `;
                Object.entries(analysis.status_codes).slice(0, 5).forEach(([code, count]) => {
                    const colorClass = code >= 500 ? 'text-danger' : code >= 400 ? 'text-warning' : 'text-success';
                    html += `<div class="d-flex justify-content-between"><span class="${colorClass}">${code}</span><span>${count}</span></div>`;
                });
                html += '</div></div>';
            }

            if (analysis.suspicious_activity.length > 0) {
                html += `
                    <div class="mb-3">
                        <h6>🚨 Suspicious Activity</h6>
                        <div class="small text-danger">
                `;
                analysis.suspicious_activity.forEach(activity => {
                    html += `<div>• ${activity}</div>`;
                });
                html += '</div></div>';
            }

            if (analysis.time_range.start && analysis.time_range.end) {
                html += `
                    <div class="mb-3">
                        <h6>Time Range</h6>
                        <div class="small">
                            <div>Start: ${analysis.time_range.start}</div>
                            <div>End: ${analysis.time_range.end}</div>
                        </div>
                    </div>
                `;
            }

            container.innerHTML = html;
        }

        async function formatCurrentLog() {
            if (!currentLogFile) {
                showNotification('warning', 'Chọn một log file trước khi format');
                return;
            }

            const logViewer = document.getElementById('log-viewer');
            const logContent = logViewer.textContent;
            
            if (!logContent || logContent.trim() === 'Select a log file to view content') {
                showNotification('warning', 'Không có nội dung log để format');
                return;
            }

            try {
                const formData = new FormData();
                formData.append('content', logContent);
                formData.append('log_type', currentLogFile);

                const response = await fetch('?action=format_log_content', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();
                if (data.success) {
                    logViewer.innerHTML = data.formatted_content;
                    showNotification('success', 'Log formatted successfully');
                } else {
                    showNotification('error', 'Format failed');
                }
            } catch (error) {
                showNotification('error', 'Format error: ' + error.message);
            }
        }

        function clearLogViewer() {
            document.getElementById('log-viewer').innerHTML = '<div class="text-center" style="color: #888; padding: 2rem;">Log viewer cleared</div>';
            document.getElementById('log-analysis').innerHTML = '<div class="text-center text-muted"><i class="fas fa-chart-pie fa-2x mb-2"></i><p>Click "Analyze Log" to view statistics</p></div>';
            currentLogFile = null;
        }

        function downloadCurrentLog() {
            if (!currentLogFile) {
                showNotification('warning', 'Chọn một log file trước');
                return;
            }

            const content = document.getElementById('log-viewer').textContent;
            if (!content || content.trim() === 'Select a log file to view content') {
                showNotification('warning', 'Không có nội dung để download');
                return;
            }

            const blob = new Blob([content], { type: 'text/plain' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `${currentServer.name}_${currentLogFile}_${new Date().toISOString().split('T')[0]}.log`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            showNotification('success', 'Log downloaded successfully');
        }

        function refreshCurrentServer() {
            if (currentServer) {
                loadServerOverview(currentServer.id);
                showNotification('info', 'Refreshing server data...');
            } else {
                showNotification('warning', 'Chọn server trước');
            }
        }

        async function testAllConnections() {
            if (allServers.length === 0) {
                showNotification('warning', 'Không có server nào để test');
                return;
            }

            showNotification('info', 'Testing connections to all servers...');
            
            // Update all to checking status
            allServers.forEach(server => {
                updateServerStatus(server.id, 'checking');
            });

            try {
                const response = await fetch('?action=batch_connection_test', {
                    method: 'POST'
                });
                
                const data = await response.json();
                
                if (data.success) {
                    data.data.forEach(result => {
                        const status = result.result.success ? 'online' : 'offline';
                        updateServerStatus(result.server.id, status);
                    });
                    
                    const onlineCount = data.data.filter(r => r.result.success).length;
                    showNotification('success', `Test completed: ${onlineCount}/${allServers.length} servers online`);
                } else {
                    showNotification('error', 'Batch test failed: ' + data.message);
                }
            } catch (error) {
                showNotification('error', 'Error testing connections: ' + error.message);
                allServers.forEach(server => {
                    updateServerStatus(server.id, 'unknown');
                });
            }
        }

        function toggleRealTimeUpdate() {
            realTimeUpdate = !realTimeUpdate;
            const indicator = document.getElementById('real-time-indicator');
            
            if (realTimeUpdate) {
                indicator.classList.add('active');
                realTimeInterval = setInterval(() => {
                    if (currentServer) {
                        refreshCurrentServer();
                    }
                }, 30000); // Update every 30 seconds
                showNotification('info', 'Real-time update enabled');
            } else {
                indicator.classList.remove('active');
                if (realTimeInterval) {
                    clearInterval(realTimeInterval);
                }
                showNotification('info', 'Real-time update disabled');
            }
        }

        function viewAllLogs() {
            if (!currentServer) {
                showNotification('warning', 'Chọn server trước');
                return;
            }
            
            // Switch to logs tab
            const logsTab = document.getElementById('logs-tab');
            const tabInstance = new bootstrap.Tab(logsTab);
            tabInstance.show();
        }

        function exportServerData() {
            if (!currentServer) {
                showNotification('warning', 'Chọn server trước');
                return;
            }
            
            const data = {
                server: currentServer,
                timestamp: new Date().toISOString(),
                logs: currentLogFile ? document.getElementById('log-viewer').textContent : null
            };
            
            const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `aapanel_${currentServer.name}_${new Date().toISOString().split('T')[0]}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
            
            showNotification('success', 'Data exported successfully');
        }

        function showNotification(type, message) {
            // Create notification element
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : type === 'info' ? 'info' : 'danger'} alert-dismissible fade show`;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.style.maxWidth = '400px';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            // Add to page
            document.body.appendChild(notification);
            
            // Auto dismiss after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }
    </script>
</body>
</html>