<?php
/**
 * aaPanel API Helper Class
 * Hỗ trợ kết nối và lấy dữ liệu từ aaPanel
 */

class AaPanelAPI {
        /**
         * Đổi/reset mật khẩu tài khoản WordPress qua wp-toolkit
         * @param string $siteId ID hoặc domain của site (tùy API aaPanel)
         * @param string $wpUser Tên tài khoản WordPress
         * @param string $wpPass Mật khẩu mới
         * @return array
         */
        public function changeWordPressPassword($siteId, $wpUser, $wpPass) {
            if (!$this->isLoggedIn && !$this->login()) {
                return ['success' => false, 'error' => 'Không thể đăng nhập aaPanel'];
            }

            // Endpoint chuẩn của wp-toolkit aaPanel (có thể cần điều chỉnh tùy hệ thống)
            $endpoint = '/plugin?action=a&name=wp_toolkit&s=save_wp_configurations';
            $postData = [
                'id' => $siteId, // hoặc 'site_id' tùy API
                'user' => $wpUser,
                'password' => $wpPass
            ];

            $response = $this->makeRequest($endpoint, 'POST', $postData);
            $data = json_decode($response, true);

            if (isset($data['status']) && $data['status'] === true) {
                return ['success' => true, 'data' => $data];
            }
            if (isset($data['msg']) && stripos($data['msg'], 'success') !== false) {
                return ['success' => true, 'data' => $data];
            }

            return [
                'success' => false,
                'error' => $data['msg'] ?? $data['error'] ?? 'Lỗi không xác định',
                'response' => $data
            ];
        }
    private $panelUrl;
    private $username;
    private $password;
    private $cookieJar;
    private $isLoggedIn = false;
    
    public function __construct($panelUrl, $username, $password) {
        $this->panelUrl = rtrim($panelUrl, '/');
        $this->username = $username;
        $this->password = $password;
        $this->cookieJar = tempnam(sys_get_temp_dir(), 'aapanel_cookies_');
    }
    
    public function __destruct() {
        if (file_exists($this->cookieJar)) {
            unlink($this->cookieJar);
        }
    }
    
    /**
     * Đăng nhập vào aaPanel
     */
    public function login() {
        // Lấy csrf token trước
        $loginPage = $this->makeRequest('/login', 'GET');
        
        // Extract CSRF token
        $csrfToken = $this->extractCSRFToken($loginPage);
        
        // Prepare login data
        $postData = [
            'username' => $this->username,
            'password' => $this->password
        ];
        
        if ($csrfToken) {
            $postData['csrf_token'] = $csrfToken;
        }
        
        // Attempt login
        $response = $this->makeRequest('/login', 'POST', $postData);
        
        // Check if login was successful
        if (strpos($response, 'dashboard') !== false || strpos($response, 'index') !== false) {
            $this->isLoggedIn = true;
            return true;
        } else {
            // Try alternative login endpoint
            $altResponse = $this->makeRequest('/', 'POST', $postData);
            if (strpos($altResponse, 'dashboard') !== false || $this->checkLoginStatus()) {
                $this->isLoggedIn = true;
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Kiểm tra trạng thái đăng nhập
     */
    public function checkLoginStatus() {
        $response = $this->makeRequest('/system?action=GetSystemTotal', 'POST');
        $data = json_decode($response, true);
        return isset($data['status']) || isset($data['data']);
    }
    
    /**
     * Lấy danh sách websites
     */
    public function getWebsites() {
        if (!$this->isLoggedIn && !$this->login()) {
            return ['error' => 'Không thể đăng nhập'];
        }
        
        // Try multiple endpoints for getting websites
        $endpoints = [
            '/site?action=GetSites',
            '/data?action=getData&table=sites',
            '/plugin?action=a&name=wp_toolkit&s=get_sites'
        ];
        
        foreach ($endpoints as $endpoint) {
            $response = $this->makeRequest($endpoint, 'POST', ['p' => 1, 'limit' => 100]);
            $data = json_decode($response, true);
            
            if (isset($data['data']) && is_array($data['data'])) {
                return $data['data'];
            }
        }
        
        return [];
    }
    
    /**
     * Lấy danh sách WordPress sites từ WP-toolkit
     */
    public function getWordPressSites() {
        if (!$this->isLoggedIn && !$this->login()) {
            return ['error' => 'Không thể đăng nhập'];
        }
        
        // WP-toolkit endpoints
        $endpoints = [
            '/plugin?action=a&name=wp_toolkit&s=get_list',
            '/plugin?action=a&name=wp_toolkit&s=GetSites',
            '/wp_toolkit',
            '/wp_toolkit?action=get_sites'
        ];
        
        foreach ($endpoints as $endpoint) {
            $response = $this->makeRequest($endpoint, 'POST');
            $data = json_decode($response, true);
            
            if (isset($data['data']) && is_array($data['data'])) {
                return $this->formatWordPressSites($data['data']);
            }
            
            // Try with parameters
            $response = $this->makeRequest($endpoint, 'POST', ['action' => 'get_sites']);
            $data = json_decode($response, true);
            
            if (isset($data['data']) && is_array($data['data'])) {
                return $this->formatWordPressSites($data['data']);
            }
        }
        
        // Fallback: get all sites and filter WordPress
        $allSites = $this->getWebsites();
        return $this->filterWordPressSites($allSites);
    }
    
    /**
     * Format WordPress sites data
     */
    private function formatWordPressSites($sites) {
        $formatted = [];
        
        foreach ($sites as $site) {
            $formatted[] = [
                'domain' => $site['domain'] ?? $site['name'] ?? 'N/A',
                'path' => $site['path'] ?? $site['document_root'] ?? '/',
                'wp_version' => $site['wp_version'] ?? $site['version'] ?? 'N/A',
                'status' => $site['status'] ?? 'active',
                'admin_url' => $this->buildAdminUrl($site),
                'site_url' => $this->buildSiteUrl($site),
                'database' => $site['database'] ?? 'N/A',
                'db_user' => $site['db_user'] ?? 'N/A'
            ];
        }
        
        return $formatted;
    }
    
    /**
     * Filter WordPress sites from all sites
     */
    private function filterWordPressSites($sites) {
        $wpSites = [];
        
        foreach ($sites as $site) {
            $domain = $site['name'] ?? $site['domain'] ?? '';
            $path = $site['path'] ?? '/';
            
            // Check if WordPress is installed
            if ($this->isWordPressInstalled($domain, $path)) {
                $wpSites[] = [
                    'domain' => $domain,
                    'path' => $path,
                    'wp_version' => $this->getWordPressVersion($domain, $path),
                    'status' => $site['status'] ?? 'active',
                    'admin_url' => "http://{$domain}/wp-admin",
                    'site_url' => "http://{$domain}",
                    'database' => 'N/A',
                    'db_user' => 'N/A'
                ];
            }
        }
        
        return $wpSites;
    }
    
    /**
     * Check if WordPress is installed
     */
    private function isWordPressInstalled($domain, $path) {
        // This would require server-side file checking
        // For API implementation, we'll assume true if domain exists
        return !empty($domain);
    }
    
    /**
     * Get WordPress version
     */
    private function getWordPressVersion($domain, $path) {
        // This would require reading wp-includes/version.php
        // For now, return 'Unknown'
        return 'Unknown';
    }
    
    /**
     * Build admin URL
     */
    private function buildAdminUrl($site) {
        $domain = $site['domain'] ?? $site['name'] ?? '';
        $protocol = (isset($site['ssl']) && $site['ssl']) ? 'https' : 'http';
        return $domain ? "{$protocol}://{$domain}/wp-admin" : '';
    }
    
    /**
     * Build site URL
     */
    private function buildSiteUrl($site) {
        $domain = $site['domain'] ?? $site['name'] ?? '';
        $protocol = (isset($site['ssl']) && $site['ssl']) ? 'https' : 'http';
        return $domain ? "{$protocol}://{$domain}" : '';
    }
    
    /**
     * Extract CSRF token from HTML
     */
    private function extractCSRFToken($html) {
        if (preg_match('/name=["\']csrf_token["\'][^>]*value=["\']([^"\']+)["\']/', $html, $matches)) {
            return $matches[1];
        }
        
        if (preg_match('/csrf_token["\'][^>]*:["\']([^"\']+)["\']/', $html, $matches)) {
            return $matches[1];
        }
        
        return null;
    }
    
    /**
     * Make HTTP request
     */
    public function makeRequest($endpoint, $method = 'GET', $data = []) {
        $url = $this->panelUrl . $endpoint;
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_COOKIEJAR => $this->cookieJar,
            CURLOPT_COOKIEFILE => $this->cookieJar,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
            CURLOPT_TIMEOUT => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_REFERER => $this->panelUrl,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json, text/html, */*',
                'Accept-Language: en-US,en;q=0.9,vi;q=0.8',
                'X-Requested-With: XMLHttpRequest'
            ]
        ]);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if (!empty($data)) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            }
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        curl_close($ch);
        
        return $response;
    }
    
    /**
     * Test connection to panel
     */
    public function testConnection() {
        $start = microtime(true);
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->panelUrl,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_NOBODY => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_USERAGENT => 'aaPanel-Checker/1.0'
        ]);
        
        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $totalTime = round((microtime(true) - $start) * 1000, 2);
        
        curl_close($ch);
        
        return [
            'success' => ($httpCode >= 200 && $httpCode < 400),
            'http_code' => $httpCode,
            'response_time' => $totalTime
        ];
    }
}

/**
 * Test function
 */
function testAaPanelConnection($panelUrl, $username, $password) {
    try {
        $api = new AaPanelAPI($panelUrl, $username, $password);
        
        // Test connection
        $connectionTest = $api->testConnection();
        if (!$connectionTest['success']) {
            return [
                'success' => false,
                'error' => "Không thể kết nối đến panel (HTTP {$connectionTest['http_code']})"
            ];
        }
        
        // Test login
        if (!$api->login()) {
            return [
                'success' => false,
                'error' => 'Không thể đăng nhập vào aaPanel'
            ];
        }
        
        // Get WordPress sites
        $sites = $api->getWordPressSites();
        
        return [
            'success' => true,
            'sites' => $sites,
            'connection_time' => $connectionTest['response_time']
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}
?>