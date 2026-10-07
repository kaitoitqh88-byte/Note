<?php
/**
 * Domain Redirect Handler
 * Quản lý redirect từ nhiều domain đến domain đích
 */

class RedirectHandler {
    private $configFile = 'redirect_config.json';
    private $config = [];
    
    public function __construct() {
        $this->loadConfig();
    }
    
    /**
     * Load redirect configuration from file
     */
    private function loadConfig() {
        if (file_exists($this->configFile)) {
            $content = file_get_contents($this->configFile);
            $this->config = json_decode($content, true) ?: [];
        } else {
            $this->config = [
                'redirects' => [],
                'settings' => [
                    'redirect_type' => '301', // 301, 302, 307, 308
                    'enable_wildcard' => true,
                    'enable_https_redirect' => true
                ]
            ];
        }
    }
    
    /**
     * Save configuration to file
     */
    private function saveConfig() {
        return file_put_contents($this->configFile, json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }
    
    /**
     * Add new redirect rule
     * @param array $sourceDomains - Array of source domains
     * @param string $targetDomain - Target domain to redirect to
     * @param string $redirectType - Type of redirect (301, 302, etc.)
     * @param bool $includeSubdomain - Include subdomains in redirect
     * @param string $pathHandling - How to handle paths: 'preserve', 'root', 'custom'
     * @param string $customPath - Custom path if pathHandling is 'custom'
     * @return array - Result of operation
     */
    public function addRedirect($sourceDomains, $targetDomain, $redirectType = '301', $includeSubdomain = false, $pathHandling = 'preserve', $customPath = '') {
        try {
            // Validate input
            if (empty($sourceDomains) || empty($targetDomain)) {
                return ['success' => false, 'message' => 'Source domains and target domain are required'];
            }
            
            if (!is_array($sourceDomains)) {
                $sourceDomains = [$sourceDomains];
            }
            
            // Clean and validate domains
            $cleanSourceDomains = [];
            foreach ($sourceDomains as $domain) {
                $cleanDomain = $this->cleanDomain($domain);
                if ($cleanDomain && $this->validateDomain($cleanDomain)) {
                    $cleanSourceDomains[] = $cleanDomain;
                }
            }
            
            $cleanTargetDomain = $this->cleanDomain($targetDomain);
            if (!$this->validateDomain($cleanTargetDomain)) {
                return ['success' => false, 'message' => 'Invalid target domain'];
            }
            
            if (empty($cleanSourceDomains)) {
                return ['success' => false, 'message' => 'No valid source domains provided'];
            }
            
            // Check for existing redirects
            foreach ($cleanSourceDomains as $sourceDomain) {
                if ($this->redirectExists($sourceDomain)) {
                    return ['success' => false, 'message' => "Redirect already exists for domain: $sourceDomain"];
                }
            }
            
            // Create redirect rule
            $redirectId = uniqid('redirect_', true);
            $redirectRule = [
                'id' => $redirectId,
                'source_domains' => $cleanSourceDomains,
                'target_domain' => $cleanTargetDomain,
                'redirect_type' => $redirectType,
                'include_subdomain' => $includeSubdomain,
                'path_handling' => $pathHandling,
                'custom_path' => $customPath,
                'created_at' => date('Y-m-d H:i:s'),
                'enabled' => true,
                'hits' => 0
            ];
            
            $this->config['redirects'][] = $redirectRule;
            
            if ($this->saveConfig()) {
                return ['success' => true, 'message' => 'Redirect rule added successfully', 'redirect_id' => $redirectId];
            } else {
                return ['success' => false, 'message' => 'Failed to save configuration'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }
    
    /**
     * Get all redirect rules
     */
    public function getAllRedirects() {
        return $this->config['redirects'] ?? [];
    }
    
    /**
     * Get redirect rule by ID
     */
    public function getRedirectById($id) {
        foreach ($this->config['redirects'] as $redirect) {
            if ($redirect['id'] === $id) {
                return $redirect;
            }
        }
        return null;
    }
    
    /**
     * Update redirect rule
     */
    public function updateRedirect($id, $data) {
        foreach ($this->config['redirects'] as &$redirect) {
            if ($redirect['id'] === $id) {
                foreach ($data as $key => $value) {
                    if ($key !== 'id' && $key !== 'created_at') {
                        $redirect[$key] = $value;
                    }
                }
                $redirect['updated_at'] = date('Y-m-d H:i:s');
                return $this->saveConfig();
            }
        }
        return false;
    }
    
    /**
     * Delete redirect rule
     */
    public function deleteRedirect($id) {
        foreach ($this->config['redirects'] as $index => $redirect) {
            if ($redirect['id'] === $id) {
                unset($this->config['redirects'][$index]);
                $this->config['redirects'] = array_values($this->config['redirects']);
                return $this->saveConfig();
            }
        }
        return false;
    }
    
    /**
     * Toggle redirect status
     */
    public function toggleRedirect($id) {
        foreach ($this->config['redirects'] as &$redirect) {
            if ($redirect['id'] === $id) {
                $redirect['enabled'] = !$redirect['enabled'];
                return $this->saveConfig();
            }
        }
        return false;
    }
    
    /**
     * Process redirect for incoming request
     */
    public function processRedirect($requestDomain, $requestPath = '', $requestProtocol = 'https') {
        $requestDomain = strtolower($this->cleanDomain($requestDomain));
        
        foreach ($this->config['redirects'] as &$redirect) {
            if (!$redirect['enabled']) {
                continue;
            }
            
            $shouldRedirect = false;
            
            // Check if domain matches
            foreach ($redirect['source_domains'] as $sourceDomain) {
                if ($requestDomain === strtolower($sourceDomain)) {
                    $shouldRedirect = true;
                    break;
                }
                
                // Check subdomain matching if enabled
                if ($redirect['include_subdomain']) {
                    if (str_ends_with($requestDomain, '.' . strtolower($sourceDomain))) {
                        $shouldRedirect = true;
                        break;
                    }
                }
            }
            
            if ($shouldRedirect) {
                // Increment hit counter
                $redirect['hits']++;
                $this->saveConfig();
                
                // Build redirect URL
                $targetUrl = $this->buildRedirectUrl($redirect, $requestPath, $requestProtocol);
                
                // Perform redirect
                $redirectCode = intval($redirect['redirect_type']);
                $this->performRedirect($targetUrl, $redirectCode);
                
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Build redirect URL
     */
    private function buildRedirectUrl($redirect, $requestPath, $requestProtocol) {
        $targetDomain = $redirect['target_domain'];
        
        // Handle protocol
        $protocol = $this->config['settings']['enable_https_redirect'] ? 'https' : $requestProtocol;
        
        // Handle path
        switch ($redirect['path_handling']) {
            case 'root':
                $path = '/';
                break;
            case 'custom':
                $path = '/' . ltrim($redirect['custom_path'], '/');
                break;
            case 'preserve':
            default:
                $path = $requestPath ?: '/';
                break;
        }
        
        return $protocol . '://' . $targetDomain . $path;
    }
    
    /**
     * Perform actual redirect
     */
    private function performRedirect($url, $code = 301) {
        $codes = [
            301 => 'Moved Permanently',
            302 => 'Found',
            307 => 'Temporary Redirect',
            308 => 'Permanent Redirect'
        ];
        
        $message = $codes[$code] ?? $codes[301];
        
        header("HTTP/1.1 $code $message");
        header("Location: $url");
        exit;
    }
    
    /**
     * Check if redirect exists for domain
     */
    private function redirectExists($domain) {
        foreach ($this->config['redirects'] as $redirect) {
            if (in_array(strtolower($domain), array_map('strtolower', $redirect['source_domains']))) {
                return true;
            }
        }
        return false;
    }
    
    /**
     * Clean domain string
     */
    private function cleanDomain($domain) {
        $domain = trim($domain);
        $domain = preg_replace('#^https?://#i', '', $domain);
        $domain = preg_replace('#/$#', '', $domain);
        return strtolower($domain);
    }
    
    /**
     * Validate domain format
     */
    private function validateDomain($domain) {
        return filter_var('http://' . $domain, FILTER_VALIDATE_URL) !== false &&
               preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain);
    }
    
    /**
     * Get redirect statistics
     */
    public function getStats() {
        $total = count($this->config['redirects']);
        $enabled = 0;
        $totalHits = 0;
        
        foreach ($this->config['redirects'] as $redirect) {
            if ($redirect['enabled']) {
                $enabled++;
            }
            $totalHits += intval($redirect['hits'] ?? 0);
        }
        
        return [
            'total_redirects' => $total,
            'enabled_redirects' => $enabled,
            'disabled_redirects' => $total - $enabled,
            'total_hits' => $totalHits
        ];
    }
    
    /**
     * Update settings
     */
    public function updateSettings($settings) {
        foreach ($settings as $key => $value) {
            if (isset($this->config['settings'][$key])) {
                $this->config['settings'][$key] = $value;
            }
        }
        return $this->saveConfig();
    }
    
    /**
     * Get current settings
     */
    public function getSettings() {
        return $this->config['settings'] ?? [];
    }
    
    /**
     * Export configuration
     */
    public function exportConfig() {
        return json_encode($this->config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Import configuration
     */
    public function importConfig($jsonConfig) {
        try {
            $importedConfig = json_decode($jsonConfig, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                return ['success' => false, 'message' => 'Invalid JSON format'];
            }
            
            // Validate structure
            if (!isset($importedConfig['redirects']) || !is_array($importedConfig['redirects'])) {
                return ['success' => false, 'message' => 'Invalid configuration structure'];
            }
            
            $this->config = $importedConfig;
            if ($this->saveConfig()) {
                return ['success' => true, 'message' => 'Configuration imported successfully'];
            } else {
                return ['success' => false, 'message' => 'Failed to save imported configuration'];
            }
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Import error: ' . $e->getMessage()];
        }
    }
}
?>