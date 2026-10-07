<?php
/**
 * API Secret Key Manager
 * Quản lý authentication và authorization cho WordPress Scanner
 */

class APISecretKeyManager {
    private $keyFile;
    private $sessionTimeout = 3600; // 1 hour
    private $maxAttempts = 5;
    private $lockoutTime = 900; // 15 minutes
    
    public function __construct($keyFile = 'api_keys.json') {
        $this->keyFile = __DIR__ . '/Data_Config/' . $keyFile;
        $this->ensureKeyFileExists();
    }
    
    /**
     * Ensure API key file exists
     */
    private function ensureKeyFileExists() {
        $dataDir = dirname($this->keyFile);
        if (!is_dir($dataDir)) {
            mkdir($dataDir, 0755, true);
        }
        
        if (!file_exists($this->keyFile)) {
            $defaultData = [
                'api_keys' => [],
                'access_logs' => [],
                'failed_attempts' => [],
                'settings' => [
                    'require_api_key' => true,
                    'session_timeout' => $this->sessionTimeout,
                    'max_attempts' => $this->maxAttempts,
                    'lockout_time' => $this->lockoutTime,
                    'created' => date('Y-m-d H:i:s')
                ]
            ];
            file_put_contents($this->keyFile, json_encode($defaultData, JSON_PRETTY_PRINT));
            chmod($this->keyFile, 0600); // Read/write for owner only
        }
    }
    
    /**
     * Load API key data
     */
    private function loadData() {
        if (!file_exists($this->keyFile)) {
            return [];
        }
        
        $data = json_decode(file_get_contents($this->keyFile), true);
        return $data ?: [];
    }
    
    /**
     * Save API key data
     */
    private function saveData($data) {
        file_put_contents($this->keyFile, json_encode($data, JSON_PRETTY_PRINT));
        chmod($this->keyFile, 0600);
    }
    
    /**
     * Generate new API secret key
     */
    public function generateAPIKey($name, $permissions = ['scan', 'backup'], $expiresIn = null) {
        $data = $this->loadData();
        
        $apiKey = [
            'key' => $this->generateSecureKey(),
            'name' => $name,
            'permissions' => $permissions,
            'created' => date('Y-m-d H:i:s'),
            'expires' => $expiresIn ? date('Y-m-d H:i:s', time() + $expiresIn) : null,
            'last_used' => null,
            'usage_count' => 0,
            'active' => true
        ];
        
        $data['api_keys'][$apiKey['key']] = $apiKey;
        $this->saveData($data);
        
        return $apiKey['key'];
    }
    
    /**
     * Generate secure random key
     */
    private function generateSecureKey($length = 64) {
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $key = '';
        
        for ($i = 0; $i < $length; $i++) {
            $key .= $characters[random_int(0, strlen($characters) - 1)];
        }
        
        return 'wpsk_' . $key; // WordPress Scanner Key prefix
    }
    
    /**
     * Validate API key
     */
    public function validateAPIKey($apiKey, $requiredPermission = null) {
        if (empty($apiKey)) {
            return ['valid' => false, 'error' => 'API key required'];
        }
        
        $data = $this->loadData();
        
        if (!isset($data['api_keys'][$apiKey])) {
            $this->logFailedAttempt($_SERVER['REMOTE_ADDR'], 'Invalid API key');
            return ['valid' => false, 'error' => 'Invalid API key'];
        }
        
        $keyData = $data['api_keys'][$apiKey];
        
        // Check if key is active
        if (!$keyData['active']) {
            return ['valid' => false, 'error' => 'API key is disabled'];
        }
        
        // Check expiration
        if ($keyData['expires'] && strtotime($keyData['expires']) < time()) {
            return ['valid' => false, 'error' => 'API key has expired'];
        }
        
        // Check permission
        if ($requiredPermission && !in_array($requiredPermission, $keyData['permissions'])) {
            return ['valid' => false, 'error' => "Permission '$requiredPermission' not granted"];
        }
        
        // Update usage statistics
        $data['api_keys'][$apiKey]['last_used'] = date('Y-m-d H:i:s');
        $data['api_keys'][$apiKey]['usage_count']++;
        
        // Log access
        $this->logAccess($apiKey, $keyData['name'], $_SERVER['REMOTE_ADDR']);
        
        $this->saveData($data);
        
        return [
            'valid' => true,
            'key_name' => $keyData['name'],
            'permissions' => $keyData['permissions']
        ];
    }
    
    /**
     * Log successful access
     */
    private function logAccess($apiKey, $keyName, $ip) {
        $data = $this->loadData();
        
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'api_key' => substr($apiKey, 0, 16) . '...',
            'key_name' => $keyName,
            'ip' => $ip,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown',
            'action' => $_GET['action'] ?? 'unknown'
        ];
        
        $data['access_logs'][] = $logEntry;
        
        // Keep only last 1000 logs
        if (count($data['access_logs']) > 1000) {
            $data['access_logs'] = array_slice($data['access_logs'], -1000);
        }
        
        $this->saveData($data);
    }
    
    /**
     * Log failed attempt
     */
    private function logFailedAttempt($ip, $reason) {
        $data = $this->loadData();
        
        $key = $ip . '_' . date('Y-m-d H');
        if (!isset($data['failed_attempts'][$key])) {
            $data['failed_attempts'][$key] = [
                'ip' => $ip,
                'count' => 0,
                'first_attempt' => date('Y-m-d H:i:s'),
                'last_attempt' => null,
                'locked_until' => null
            ];
        }
        
        $data['failed_attempts'][$key]['count']++;
        $data['failed_attempts'][$key]['last_attempt'] = date('Y-m-d H:i:s');
        $data['failed_attempts'][$key]['reason'] = $reason;
        
        // Check if should lock
        if ($data['failed_attempts'][$key]['count'] >= $this->maxAttempts) {
            $data['failed_attempts'][$key]['locked_until'] = date('Y-m-d H:i:s', time() + $this->lockoutTime);
        }
        
        $this->saveData($data);
    }
    
    /**
     * Check if IP is locked out
     */
    public function isIPLocked($ip) {
        $data = $this->loadData();
        $key = $ip . '_' . date('Y-m-d H');
        
        if (isset($data['failed_attempts'][$key])) {
            $attempt = $data['failed_attempts'][$key];
            if ($attempt['locked_until'] && strtotime($attempt['locked_until']) > time()) {
                return [
                    'locked' => true,
                    'until' => $attempt['locked_until'],
                    'attempts' => $attempt['count']
                ];
            }
        }
        
        return ['locked' => false];
    }
    
    /**
     * Get all API keys
     */
    public function getAPIKeys() {
        $data = $this->loadData();
        
        $keys = [];
        foreach ($data['api_keys'] as $key => $keyData) {
            $keys[] = [
                'key' => substr($key, 0, 16) . '...',
                'full_key' => $key,
                'name' => $keyData['name'],
                'permissions' => $keyData['permissions'],
                'created' => $keyData['created'],
                'expires' => $keyData['expires'],
                'last_used' => $keyData['last_used'],
                'usage_count' => $keyData['usage_count'],
                'active' => $keyData['active']
            ];
        }
        
        return $keys;
    }
    
    /**
     * Deactivate API key
     */
    public function deactivateAPIKey($apiKey) {
        $data = $this->loadData();
        
        if (isset($data['api_keys'][$apiKey])) {
            $data['api_keys'][$apiKey]['active'] = false;
            $this->saveData($data);
            return true;
        }
        
        return false;
    }
    
    /**
     * Delete API key
     */
    public function deleteAPIKey($apiKey) {
        $data = $this->loadData();
        
        if (isset($data['api_keys'][$apiKey])) {
            unset($data['api_keys'][$apiKey]);
            $this->saveData($data);
            return true;
        }
        
        return false;
    }
    
    /**
     * Get access logs
     */
    public function getAccessLogs($limit = 50) {
        $data = $this->loadData();
        $logs = $data['access_logs'] ?? [];
        
        return array_slice(array_reverse($logs), 0, $limit);
    }
    
    /**
     * Get security statistics
     */
    public function getSecurityStats() {
        $data = $this->loadData();
        
        $totalKeys = count($data['api_keys']);
        $activeKeys = count(array_filter($data['api_keys'], function($k) { return $k['active']; }));
        $totalAccess = count($data['access_logs'] ?? []);
        $failedAttempts = count($data['failed_attempts'] ?? []);
        
        // Recent activity (last 24 hours)
        $recentLogs = array_filter($data['access_logs'] ?? [], function($log) {
            return strtotime($log['timestamp']) > (time() - 86400);
        });
        
        return [
            'total_keys' => $totalKeys,
            'active_keys' => $activeKeys,
            'total_access' => $totalAccess,
            'recent_access_24h' => count($recentLogs),
            'failed_attempts' => $failedAttempts,
            'settings' => $data['settings']
        ];
    }
    
    /**
     * Create session with API key
     */
    public function createSession($apiKey) {
        $validation = $this->validateAPIKey($apiKey);
        
        if (!$validation['valid']) {
            return $validation;
        }
        
        $sessionToken = bin2hex(random_bytes(32));
        $sessionData = [
            'token' => $sessionToken,
            'api_key' => $apiKey,
            'key_name' => $validation['key_name'],
            'permissions' => $validation['permissions'],
            'created' => time(),
            'expires' => time() + $this->sessionTimeout,
            'ip' => $_SERVER['REMOTE_ADDR']
        ];
        
        $_SESSION['wp_scanner_auth'] = $sessionData;
        
        return [
            'success' => true,
            'session_token' => $sessionToken,
            'expires_in' => $this->sessionTimeout,
            'permissions' => $validation['permissions']
        ];
    }
    
    /**
     * Validate session
     */
    public function validateSession($requiredPermission = null) {
        if (!isset($_SESSION['wp_scanner_auth'])) {
            return ['valid' => false, 'error' => 'No active session'];
        }
        
        $session = $_SESSION['wp_scanner_auth'];
        
        // Check expiration
        if ($session['expires'] < time()) {
            unset($_SESSION['wp_scanner_auth']);
            return ['valid' => false, 'error' => 'Session expired'];
        }
        
        // Check IP (basic security)
        if ($session['ip'] !== $_SERVER['REMOTE_ADDR']) {
            unset($_SESSION['wp_scanner_auth']);
            return ['valid' => false, 'error' => 'Session IP mismatch'];
        }
        
        // Check permission
        if ($requiredPermission && !in_array($requiredPermission, $session['permissions'])) {
            return ['valid' => false, 'error' => "Permission '$requiredPermission' required"];
        }
        
        // Extend session
        $_SESSION['wp_scanner_auth']['expires'] = time() + $this->sessionTimeout;
        
        return [
            'valid' => true,
            'key_name' => $session['key_name'],
            'permissions' => $session['permissions']
        ];
    }
    
    /**
     * Destroy session
     */
    public function destroySession() {
        unset($_SESSION['wp_scanner_auth']);
        return ['success' => true];
    }
    
    /**
     * Generate secure VPS connection token
     */
    public function generateVPSToken($vpsHost, $expireIn = 300) {
        $tokenData = [
            'vps_host' => $vpsHost,
            'expires' => time() + $expireIn,
            'nonce' => bin2hex(random_bytes(16))
        ];
        
        $token = base64_encode(json_encode($tokenData));
        return $token;
    }
    
    /**
     * Validate VPS token
     */
    public function validateVPSToken($token, $expectedHost) {
        try {
            $tokenData = json_decode(base64_decode($token), true);
            
            if (!$tokenData || 
                $tokenData['vps_host'] !== $expectedHost || 
                $tokenData['expires'] < time()) {
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}

/**
 * Authentication middleware
 */
function requireAPIAuthentication($requiredPermission = null) {
    session_start();
    
    $keyManager = new APISecretKeyManager();
    
    // Check IP lockout first
    $lockStatus = $keyManager->isIPLocked($_SERVER['REMOTE_ADDR']);
    if ($lockStatus['locked']) {
        http_response_code(429);
        die(json_encode([
            'error' => 'Too many failed attempts',
            'locked_until' => $lockStatus['until'],
            'attempts' => $lockStatus['attempts']
        ]));
    }
    
    // Check if already authenticated via session
    $sessionValidation = $keyManager->validateSession($requiredPermission);
    if ($sessionValidation['valid']) {
        return $sessionValidation;
    }
    
    // Check for API key in request
    $apiKey = $_POST['api_key'] ?? $_GET['api_key'] ?? $_SERVER['HTTP_X_API_KEY'] ?? null;
    
    if ($apiKey) {
        // Validate API key
        $validation = $keyManager->validateAPIKey($apiKey, $requiredPermission);
        
        if ($validation['valid']) {
            // Create session
            $sessionResult = $keyManager->createSession($apiKey);
            if ($sessionResult['success']) {
                return $validation;
            }
        }
        
        // Invalid API key
        http_response_code(401);
        die(json_encode(['error' => $validation['error'] ?? 'Authentication failed']));
    }
    
    // No authentication provided
    if (isset($_GET['action']) && $_GET['action'] === 'wordpress') {
        // Redirect to auth page for WordPress scanner
        header('Location: ?action=wordpress&wp_action=auth');
        exit;
    }
    
    http_response_code(401);
    die(json_encode(['error' => 'API key required']));
}

/**
 * Check if user is authenticated
 */
function isAuthenticated() {
    session_start();
    $keyManager = new APISecretKeyManager();
    return $keyManager->validateSession()['valid'] ?? false;
}

/**
 * Get current user permissions
 */
function getCurrentPermissions() {
    session_start();
    $keyManager = new APISecretKeyManager();
    $session = $keyManager->validateSession();
    return $session['valid'] ? $session['permissions'] : [];
}
?>