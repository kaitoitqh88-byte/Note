<?php
/**
 * Domain Status Checker - Check trạng thái của danh sách domain
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/CloudflareAPI.php';

class DomainStatusChecker {
    private $cloudflareAPI;
    private $results;
    
    public function __construct() {
        $this->cloudflareAPI = new CloudflareAPI();
        $this->results = [];
    }
    
    /**
     * Clean domain name by removing http/https and path
     */
    private function cleanDomainName($domain) {
        // Remove extra whitespace
        $domain = trim($domain);
        
        // Remove protocol (http:// or https://, ftp://, etc.)
        $domain = preg_replace('/^[a-zA-Z]+:\/\//', '', $domain);
        
        // Remove www prefix (optional - user can decide to keep or remove)
        $domain = preg_replace('/^www\./', '', $domain);
        
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
     * Check status của danh sách domain
     */
    public function checkDomainList($domains) {
        $results = [];
        $totalDomains = count($domains);
        
        foreach ($domains as $index => $domain) {
            $originalDomain = trim($domain);
            if (empty($originalDomain)) continue;
            
            // Clean domain name (remove http/https, www, path, etc.)
            $cleanedDomain = $this->cleanDomainName($originalDomain);
            
            if (empty($cleanedDomain)) {
                $results[] = [
                    'domain' => $originalDomain,
                    'cleaned_domain' => $cleanedDomain,
                    'status' => 'error',
                    'details' => ['error' => 'Invalid domain format'],
                    'index' => $index + 1,
                    'success' => false
                ];
                continue;
            }
            
            try {
                $status = $this->checkSingleDomain($cleanedDomain);
                $results[] = [
                    'domain' => $originalDomain,
                    'cleaned_domain' => $cleanedDomain,
                    'status' => $status['status'],
                    'details' => $status,
                    'index' => $index + 1,
                    'success' => true
                ];
            } catch (Exception $e) {
                $results[] = [
                    'domain' => $originalDomain,
                    'cleaned_domain' => $cleanedDomain,
                    'status' => 'error',
                    'details' => ['error' => $e->getMessage()],
                    'index' => $index + 1,
                    'success' => false
                ];
            }
        }
        
        return [
            'success' => true,
            'total_domains' => $totalDomains,
            'checked_domains' => count($results),
            'results' => $results,
            'summary' => $this->generateSummary($results)
        ];
    }
    
    /**
     * Check status của một domain
     */
    private function checkSingleDomain($domain) {
        // Get basic zone info
        $zoneInfo = $this->getZoneInfo($domain);
        
        if (!$zoneInfo) {
            return [
                'status' => 'not_found',
                'message' => 'Domain không được tìm thấy trong Cloudflare',
                'dns_status' => null,
                'ssl_status' => null,
                'security_status' => null
            ];
        }
        
        // Check DNS status
        $dnsStatus = $this->checkDNSStatus($zoneInfo);
        
        // Check SSL status
        $sslStatus = $this->checkSSLStatus($zoneInfo);
        
        // Check security status
        $securityStatus = $this->checkSecurityStatus($zoneInfo);
        
        return [
            'status' => $zoneInfo['status'],
            'zone_id' => $zoneInfo['id'],
            'name' => $zoneInfo['name'],
            'created_on' => $zoneInfo['created_on'],
            'modified_on' => $zoneInfo['modified_on'],
            'plan' => $zoneInfo['plan']['name'] ?? 'Unknown',
            'dns_status' => $dnsStatus,
            'ssl_status' => $sslStatus,
            'security_status' => $securityStatus,
            'nameservers' => $zoneInfo['name_servers'] ?? []
        ];
    }
    
    /**
     * Lấy thông tin zone từ domain
     */
    private function getZoneInfo($domain) {
        try {
            $zones = $this->cloudflareAPI->searchZones($domain, 1, 1);
            
            if (!empty($zones['result']) && count($zones['result']) > 0) {
                // Check if exact match
                foreach ($zones['result'] as $zone) {
                    if ($zone['name'] === $domain) {
                        return $zone;
                    }
                }
                // If no exact match, return first result
                return $zones['result'][0];
            }
            
            return null;
        } catch (Exception $e) {
            throw new Exception("Lỗi khi lấy thông tin zone: " . $e->getMessage());
        }
    }
    
    /**
     * Check DNS status và lấy toàn bộ DNS records
     */
    private function checkDNSStatus($zoneInfo) {
        try {
            // Get ALL DNS records for the zone (increase per_page)
            $dnsRecords = $this->cloudflareAPI->getDNSRecords($zoneInfo['id'], ['per_page' => 100]);
            
            $records = $dnsRecords['result'] ?? [];
            $recordCount = count($records);
            $hasARecord = false;
            $hasAAAARecord = false;
            $hasCNAME = false;
            $hasMX = false;
            $hasTXT = false;
            $hasNS = false;
            
            // Organize records by type
            $recordsByType = [];
            $formattedRecords = [];
            
            foreach ($records as $record) {
                $type = $record['type'];
                
                // Count record types
                switch ($type) {
                    case 'A':
                        $hasARecord = true;
                        break;
                    case 'AAAA':
                        $hasAAAARecord = true;
                        break;
                    case 'CNAME':
                        $hasCNAME = true;
                        break;
                    case 'MX':
                        $hasMX = true;
                        break;
                    case 'TXT':
                        $hasTXT = true;
                        break;
                    case 'NS':
                        $hasNS = true;
                        break;
                }
                
                // Group by type
                if (!isset($recordsByType[$type])) {
                    $recordsByType[$type] = [];
                }
                $recordsByType[$type][] = $record;
                
                // Format record for display
                $formattedRecord = [
                    'id' => $record['id'] ?? '',
                    'type' => $type,
                    'name' => $record['name'] ?? '',
                    'content' => $record['content'] ?? '',
                    'ttl' => $record['ttl'] ?? 1,
                    'proxied' => $record['proxied'] ?? false,
                    'created_on' => $record['created_on'] ?? '',
                    'modified_on' => $record['modified_on'] ?? '',
                    'zone_id' => $record['zone_id'] ?? ''
                ];
                
                // Add type-specific data
                if ($type === 'MX') {
                    $formattedRecord['priority'] = $record['priority'] ?? 0;
                }
                
                if ($type === 'SRV') {
                    $formattedRecord['priority'] = $record['priority'] ?? 0;
                    $formattedRecord['weight'] = $record['data']['weight'] ?? 0;
                    $formattedRecord['port'] = $record['data']['port'] ?? 0;
                    $formattedRecord['target'] = $record['data']['target'] ?? '';
                }
                
                $formattedRecords[] = $formattedRecord;
            }
            
            return [
                'status' => $recordCount > 0 ? 'configured' : 'not_configured',
                'record_count' => $recordCount,
                'has_a_record' => $hasARecord,
                'has_aaaa_record' => $hasAAAARecord,
                'has_cname' => $hasCNAME,
                'has_mx_record' => $hasMX,
                'has_txt_record' => $hasTXT,
                'has_ns_record' => $hasNS,
                'records' => $formattedRecords,
                'records_by_type' => $recordsByType,
                'type_counts' => [
                    'A' => count($recordsByType['A'] ?? []),
                    'AAAA' => count($recordsByType['AAAA'] ?? []),
                    'CNAME' => count($recordsByType['CNAME'] ?? []),
                    'MX' => count($recordsByType['MX'] ?? []),
                    'TXT' => count($recordsByType['TXT'] ?? []),
                    'NS' => count($recordsByType['NS'] ?? []),
                    'SRV' => count($recordsByType['SRV'] ?? []),
                    'CAA' => count($recordsByType['CAA'] ?? []),
                    'PTR' => count($recordsByType['PTR'] ?? [])
                ]
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage(),
                'records' => [],
                'records_by_type' => [],
                'type_counts' => []
            ];
        }
    }
    
    /**
     * Check SSL status
     */
    private function checkSSLStatus($zoneInfo) {
        try {
            // Get SSL settings
            $response = $this->cloudflareAPI->getSSLSettings($zoneInfo['id']);
            
            // Find SSL-related settings
            $sslMode = 'unknown';
            $alwaysHTTPS = false;
            
            if (isset($response['result']) && is_array($response['result'])) {
                foreach ($response['result'] as $setting) {
                    if ($setting['id'] === 'ssl') {
                        $sslMode = $setting['value'] ?? 'unknown';
                    }
                    if ($setting['id'] === 'always_use_https') {
                        $alwaysHTTPS = $setting['value'] === 'on';
                    }
                }
            }
            
            return [
                'status' => $sslMode,
                'always_https' => $alwaysHTTPS,
                'certificate_status' => $sslMode !== 'off' ? 'active' : 'off'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Check security status
     */
    private function checkSecurityStatus($zoneInfo) {
        try {
            // Get basic security settings
            return [
                'status' => 'active',
                'zone_status' => $zoneInfo['status'],
                'paused' => $zoneInfo['paused'] ?? false,
                'development_mode' => false // Would need separate API call
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Tạo summary của kết quả check
     */
    private function generateSummary($results) {
        $summary = [
            'total' => count($results),
            'active' => 0,
            'pending' => 0,
            'error' => 0,
            'not_found' => 0,
            'dns_configured' => 0,
            'ssl_enabled' => 0
        ];
        
        foreach ($results as $result) {
            if (!$result['success']) {
                $summary['error']++;
                continue;
            }
            
            $status = $result['details']['status'] ?? 'unknown';
            
            switch ($status) {
                case 'active':
                    $summary['active']++;
                    break;
                case 'pending':
                    $summary['pending']++;
                    break;
                case 'not_found':
                    $summary['not_found']++;
                    break;
            }
            
            // DNS check
            if (isset($result['details']['dns_status']['status']) && 
                $result['details']['dns_status']['status'] === 'configured') {
                $summary['dns_configured']++;
            }
            
            // SSL check
            if (isset($result['details']['ssl_status']['status']) && 
                $result['details']['ssl_status']['status'] !== 'off') {
                $summary['ssl_enabled']++;
            }
        }
        
        return $summary;
    }
}

// API endpoint for AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Set JSON header early
    ini_set('display_errors', '0');
    ini_set('html_errors', '0');
    header('Content-Type: application/json');
    
    // Disable any output buffering that might cause HTML to be sent
    if (ob_get_level()) {
        ob_clean();
    }
    
    try {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'check_domains') {
            $domains = $_POST['domains'] ?? '';
            
            if (empty($domains)) {
                throw new Exception('Vui lòng nhập danh sách domain');
            }
            
            // Parse domains from string and clean them
            $rawDomainList = array_filter(array_map('trim', preg_split('/[\r\n,;]+/', $domains)));
            $domainList = [];
            
            // Clean each domain
            foreach ($rawDomainList as $domain) {
                if (!empty($domain)) {
                    $domainList[] = $domain; // Keep original for processing in DomainStatusChecker
                }
            }
            
            $checker = new DomainStatusChecker();
            $result = $checker->checkDomainList($domainList);
            
            echo json_encode($result);
        } elseif ($action === 'check_domain') {
            $domain = trim($_POST['domain'] ?? '');
            $index = (int)($_POST['index'] ?? 1);

            if (empty($domain)) {
                throw new Exception('Thiếu domain cần kiểm tra');
            }

            $checker = new DomainStatusChecker();
            $status = $checker->checkDomainList([$domain]);
            $result = $status['results'][0] ?? [
                'domain' => $domain,
                'cleaned_domain' => '',
                'status' => 'error',
                'details' => ['error' => 'Invalid domain format'],
                'index' => $index,
                'success' => false
            ];
            $result['index'] = $index;

            echo json_encode([
                'success' => true,
                'result' => $result
            ]);
            
        } elseif ($action === 'get_ssl_settings') {
            $zoneId = $_POST['zone_id'] ?? '';
            
            if (empty($zoneId)) {
                throw new Exception('Zone ID is required');
            }
            
            $cloudflareAPI = new CloudflareAPI();
            $result = $cloudflareAPI->getSSLSettingsDetailed($zoneId);
            
            echo json_encode($result);
            
        } elseif ($action === 'configure_ssl') {
            $zoneId = $_POST['zone_id'] ?? '';
            $settingsJson = $_POST['settings'] ?? '';
            
            if (empty($zoneId)) {
                throw new Exception('Zone ID is required');
            }
            
            if (empty($settingsJson)) {
                throw new Exception('No SSL settings provided');
            }
            
            $settings = json_decode($settingsJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new Exception('Invalid settings format');
            }
            
            $cloudflareAPI = new CloudflareAPI();
            $result = $cloudflareAPI->configureSSLBulk($zoneId, $settings);
            
            echo json_encode($result);
        } else {
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
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>[TACTICAL-CF] Domain Status Checker - Reconnaissance System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&family=Roboto+Mono:wght@300;400;500;700&display=swap" rel="stylesheet">
    <link href="assets/css/rpg-shooter-theme.css" rel="stylesheet">
    <link href="assets/css/sidebar-navigation.css" rel="stylesheet">
    <style>
        /* Force sidebar-content padding to 0 */
        .sidebar-content {
            padding: 0 !important;
        }
        /* Bootstrap CSS Variable Override */
        :root {
            --bs-table-bg: #000;
        }
        
        /* Matrix Theme with Roboto Font */
        * {
            font-family: 'Roboto', sans-serif !important;
        }
        
        body {
            background: #000;
            color: #00ff00;
            font-family: 'Roboto', sans-serif;
            text-shadow: 0 0 5px rgba(0, 255, 0, 0.5);
            overflow-x: hidden;
        }
        
        /* Matrix Animation Background */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: repeating-linear-gradient(
                90deg,
                transparent,
                transparent 98px,
                rgba(0, 255, 0, 0.03) 100px
            );
            pointer-events: none;
            z-index: -1;
            animation: matrixLines 10s linear infinite;
        }
        
        @keyframes matrixLines {
            0% { transform: translateX(0); }
            100% { transform: translateX(100px); }
        }
        
        /* Body with sidebar open */
        body {
            padding-left: 280px;
            transition: padding-left 0.3s ease;
        }
        
        body.sidebar-collapsed {
            padding-left: 0;
        }
        
        body.sidebar-collapsed .sidebar-nav {
            left: -280px !important;
        }
        
        /* HUD Container */
        .hud-container {
            min-height: calc(100vh - 200px);
            padding: 1.5rem;
            background: rgba(0, 0, 0, 0.8);
        }
        
        /* Matrix Text Glow */
        h1, h2, h3, h4, h5, h6 {
            color: #00ff00 !important;
            text-shadow: 0 0 10px rgba(0, 255, 0, 0.8), 0 0 20px rgba(0, 255, 0, 0.4);
            font-family: 'Roboto', sans-serif !important;
            font-weight: 500;
        }
        /* HUD Container */
        .hud-container {
            min-height: calc(100vh - 200px);
            padding: 1.5rem;
            background: rgba(0, 0, 0, 0.8);
        }
        
        .sidebar-section {
            background: rgba(0, 255, 127, 0.05);
            border: 1px solid rgba(0, 255, 127, 0.2);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .sidebar-title {
            color: var(--tactical-primary);
            font-family: 'Roboto', sans-serif;
            font-weight: 700;
            font-size: 1rem;
            margin-bottom: 1rem;
            text-transform: uppercase;
            border-bottom: 1px solid var(--tactical-primary);
            padding-bottom: 0.5rem;
        }
        
        .sidebar-btn, .sidebar-link {
            display: block;
            color: var(--tactical-text);
            text-decoration: none;
            padding: 0.75rem;
            margin-bottom: 0.5rem;
            font-family: 'Roboto', sans-serif;
            font-size: 0.85rem;
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 255, 127, 0.2);
            background: rgba(0, 255, 127, 0.05);
            border-radius: 4px;
            text-align: center;
            cursor: pointer;
        }
        
        .sidebar-btn:hover, .sidebar-link:hover {
            color: #000;
            background: var(--tactical-primary);
            border-color: var(--tactical-primary);
            text-decoration: none;
            transform: translateY(-2px);
        }
        
        /* Main Title Styling */
        h1 {
            font-size: 2em;
            font-family: 'Roboto', sans-serif;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 2px;
            color: var(--tactical-primary);
            text-shadow: 0 0 10px rgba(0, 255, 102, 0.5);
            margin-bottom: 1rem;
        }
        
        /* Ghi chú styling - improved readability */
        .ghi-chu {
            background: rgba(0, 50, 100, 0.8);
            border: 1px solid rgba(0, 255, 127, 0.3);
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
            color: #ffffff;
            text-shadow: none;
            font-family: 'Roboto', sans-serif;
            font-size: 0.9rem;
            line-height: 1.5;
        }
        
        .ghi-chu i {
            color: #00ff7f;
            margin-right: 0.5rem;
        }
        
        .ghi-chu strong {
            color: #00ff7f;
            font-weight: 600;
        }
        
        .ghi-chu code {
            background: rgba(0, 255, 127, 0.15);
            border: 1px solid rgba(0, 255, 127, 0.3);
            color: #ffffff;
            padding: 0.2rem 0.4rem;
            border-radius: 4px;
            font-family: 'Roboto Mono', monospace;
            font-size: 0.85em;
        }
    </style>
    <style>
        .loading-card {
            background: rgba(0, 20, 40, 0.98);
            border: 2px solid var(--tactical-primary);
            border-radius: 20px;
            box-shadow: 
                0 0 30px rgba(0, 255, 102, 0.4),
                0 0 60px rgba(0, 255, 102, 0.2),
                inset 0 0 30px rgba(0, 255, 102, 0.15);
            backdrop-filter: blur(15px);
            position: relative;
            overflow: hidden;
        }
        
        .loading-card::before {
            content: '';
            position: absolute;
            top: -2px;
            left: -2px;
            right: -2px;
            bottom: -2px;
            background: linear-gradient(45deg, var(--tactical-primary), var(--tactical-success), var(--tactical-warning), var(--tactical-primary));
            background-size: 300% 300%;
            border-radius: 20px;
            z-index: -1;
            animation: tactical-border-glow 3s ease-in-out infinite;
        }
        
        @keyframes tactical-border-glow {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
        }
        
        .tactical-spinner {
            width: 4.5rem;
            height: 4.5rem;
            border: 0.3em solid transparent;
            border-top: 0.3em solid var(--tactical-primary);
            border-right: 0.3em solid var(--tactical-success);
            border-radius: 50%;
            animation: tactical-spin 1.2s linear infinite;
            position: relative;
        }
        
        .tactical-spinner::before {
            content: '';
            position: absolute;
            top: -5px;
            left: -5px;
            right: -5px;
            bottom: -5px;
            border: 2px solid rgba(0, 255, 102, 0.2);
            border-radius: 50%;
            animation: tactical-pulse 2s ease-in-out infinite;
        }
        
        @keyframes tactical-spin {
            0% { 
                transform: rotate(0deg);
                border-top-color: var(--tactical-primary);
                border-right-color: var(--tactical-success);
            }
            25% { 
                border-top-color: var(--tactical-success);
                border-right-color: var(--tactical-warning);
            }
            50% { 
                transform: rotate(180deg);
                border-top-color: var(--tactical-warning);
                border-right-color: var(--tactical-danger);
            }
            75% { 
                border-top-color: var(--tactical-danger);
                border-right-color: var(--tactical-primary);
            }
            100% { 
                transform: rotate(360deg);
                border-top-color: var(--tactical-primary);
                border-right-color: var(--tactical-success);
            }
        }
        
        @keyframes tactical-pulse {
            0%, 100% {
                border-color: rgba(0, 255, 102, 0.2);
                transform: scale(1);
            }
            50% {
                border-color: rgba(0, 255, 102, 0.6);
                transform: scale(1.05);
            }
        }
        
        .loading-text {
            color: var(--tactical-primary);
            font-family: 'Roboto', sans-serif;
            font-weight: 700;
            text-shadow: 
                0 0 10px rgba(0, 255, 102, 0.8),
                0 0 20px rgba(0, 255, 102, 0.4);
            margin-top: 1.5rem;
            font-size: 1.2rem;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        
        .loading-dots::after {
            content: '';
            animation: loading-dots 1.2s steps(4, end) infinite;
        }
        
        @keyframes loading-dots {
            0%, 20% { content: ''; }
            40% { content: '.'; }
            60% { content: '..'; }
            80%, 100% { content: '...'; }
        }
        
        .loading-progress {
            width: 100%;
            height: 8px;
            background: rgba(0, 255, 127, 0.1);
            border-radius: 10px;
            margin-top: 1rem;
            overflow: hidden;
            position: relative;
        }
        
        .loading-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--tactical-primary), var(--tactical-success), var(--tactical-warning));
            border-radius: 10px;
            width: 0%;
            transition: width 0.5s ease-out;
            position: relative;
            overflow: hidden;
        }
        
        .loading-progress-bar::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            animation: tactical-shimmer 2s infinite;
        }
        
        @keyframes tactical-shimmer {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        .loading-status {
            margin-top: 1rem;
            font-family: 'Roboto', sans-serif;
            font-size: 0.9rem;
            color: var(--tactical-success);
            text-shadow: 0 0 5px rgba(0, 255, 102, 0.5);
        }
        
        .loading-stage {
            opacity: 0.6;
            transition: opacity 0.3s ease;
        }
        
        .loading-stage.active {
            opacity: 1;
            animation: tactical-pulse 1.5s infinite;
        }
        
        @keyframes tactical-pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .radar-container {
            position: relative;
            width: 100px;
            height: 100px;
            margin: 0 auto 1rem;
        }
        
        .radar-sweep {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: 2px solid var(--tactical-primary);
            border-radius: 50%;
            opacity: 0;
            animation: radar-pulse 2s ease-out infinite;
        }
        
        .radar-sweep:nth-child(2) {
            animation-delay: 0.5s;
        }
        
        .radar-sweep:nth-child(3) {
            animation-delay: 1s;
        }
        
        .radar-sweep:nth-child(4) {
            animation-delay: 1.5s;
        }
        
        @keyframes radar-pulse {
            0% {
                transform: scale(0.1);
                opacity: 1;
            }
            50% {
                opacity: 0.8;
            }
            100% {
                transform: scale(1);
                opacity: 0;
            }
        }
        
        /* Table with black background and white text */
        .table {
            background: #000000 !important;
            background-color: #000000 !important;
        }
        
        .table th,
        .table td {
            color: #ffffff;
            border-color: #333333;
            background-color: #000000 !important;
        }
        
        .table thead th {
            color: #ffffff;
            background: #111111 !important;
            background-color: #111111 !important;
        }
        
        .simple-card {
            background: #000000;
            border: 1px solid #333333;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        .simple-header {
            background: #000000;
            color: #ffffff;
            padding: 1rem 1.5rem;
            font-family: inherit;
            font-weight: 600;
            border-bottom: 1px solid #333333;
        }
        
        /* Simple Badge Styling - no special effects */
        .simple-status {
            padding: 0.35rem 0.65rem;
            border-radius: 4px;
            font-weight: 500;
            font-size: 0.8rem;
        }
        
        .status-active {
            background: #28a745;
            color: white;
        }
        
        .status-pending {
            background: #ffc107;
            color: #212529;
        }
        
        .status-error, .status-not-found {
            background: #dc3545;
            color: white;
        }
        
        /* Simple Index Styling */
        .simple-index {
            background: #6c757d;
            color: white;
            padding: 0.3rem 0.6rem;
            border-radius: 3px;
            font-weight: 600;
            font-family: 'Roboto', sans-serif;
        }
        
        .domain-info .domain-name {
            color: #ffffff;
            font-family: 'Roboto', sans-serif;
            font-weight: 600;
            text-shadow: none;
        }
        
        .cleaned-domain-info {
            margin-top: 0.3rem;
            font-size: 0.8rem;
            color: #cccccc;
            text-shadow: none;
        }
        
        .cleaned-domain-text {
            color: #ffffff;
            font-weight: 500;
        }
        
        .dns-records-cell {
            font-family: 'Roboto Mono', monospace;
            font-size: 0.85rem;
            max-width: 300px;
            overflow-wrap: break-word;
        }
        
        .nameservers-cell {
            font-family: 'Roboto Mono', monospace;
            font-size: 0.8rem;
        }
        
        code {
            padding: 5px;
            font-size: 16px;
            background: rgba(0, 0, 0, 0.7);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            color: #ffffff;
            font-family: 'Roboto Mono', monospace;
        }
        
        /* Nameserver display improvement */
        .nameserver-display small {
            color: #ffffff !important;
            font-weight: 500;
            text-shadow: none;
        }
        
        /* Form text improvement */
        .form-text {
            color: #cccccc !important;
            font-size: 0.85rem;
            text-shadow: none;
            margin-top: 0.5rem;
        }
        
        .form-text i {
            color: #00ff7f;
        }
        
        /* Label và text improvement */
        .form-label {
            color: #ffffff !important;
            font-weight: 600;
            text-shadow: none;
        }
        
        .card-title {
            color: #ffffff !important;
            text-shadow: none;
        }
        
        .tactical-header h1 {
            color: #00ff7f !important;
            text-shadow: 0 0 10px rgba(0, 255, 127, 0.5);
        }
        
        /* Table header improvement */ 
        .table thead th {
            color: #ffffff !important;
            text-shadow: none;
            font-weight: 600;
        }
        
        .domain-details {
            background: rgba(0, 20, 40, 0.9);
            border: 1px solid var(--tactical-primary);
            border-radius: 8px;
            padding: 0;
            margin-top: 0.5rem;
        }
        
        /* DNS Record Type Styling */
        .dns-record-type {
            display: inline-block;
            padding: 0;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
            font-family: 'Roboto', sans-serif;
            letter-spacing: 0.5px;
            margin: 0.1rem;
            text-transform: uppercase;
        }
        
        .dns-type-A {
            background: linear-gradient(135deg, #00ff66, #00cc52);
            color: #000;
        }
        
        .dns-type-AAAA {
            background: linear-gradient(135deg, #0099ff, #0077cc);
            color: #fff;
        }
        
        .dns-type-CNAME {
            background: linear-gradient(135deg, #ff9900, #cc7700);
            color: #fff;
        }
        
        .dns-type-MX {
            background: linear-gradient(135deg, #9966ff, #7744cc);
            color: #fff;
        }
        
        .dns-type-TXT {
            background: linear-gradient(135deg, #ff6699, #cc4477);
            color: #fff;
        }
        
        .dns-type-NS {
            background: linear-gradient(135deg, #66ffff, #44cccc);
            color: #000;
        }
        
        .dns-type-SRV, .dns-type-CAA, .dns-type-PTR {
            background: linear-gradient(135deg, #999999, #666666);
            color: #fff;
        }
        
        .dns-summary-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }
        
        /* Enhanced Loading Animations */
        .loading-card {
            animation: loading-card-float 4s ease-in-out infinite;
        }
        
        @keyframes loading-card-float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }
        
        .tactical-spinner {
            position: relative;
            z-index: 10;
        }
        
        .tactical-spinner:after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 8px;
            height: 8px;
            background: var(--tactical-primary);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            box-shadow: 0 0 15px var(--tactical-primary);
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'domain_status_checker'; // Set active page for navigation
    include 'includes/main_navigation.php'; 
    ?>

    <!-- Tactical Command Header -->
    <div class="container-fluid mt-4 hud-container p-0">
        <div class="tactical-header">
            <h1 class="mb-3">
                <i class="fas fa-radar me-3"></i>
                DOMAIN RECONNAISSANCE SYSTEM
            </h1>
            <p class="lead mb-0" style="color: #ffffff; text-shadow: none; font-size: 1.1rem;">
                Advanced domain status monitoring and analysis via Cloudflare intelligence network
            </p>
        </div>

        <!-- Input Form -->
        <div class="card tactical-border">
            <div class="card-body p-4">
                <h4 class="card-title mb-4">
                    <i class="fas fa-crosshairs me-2"></i>
                    TARGET DOMAINS INPUT
                </h4>
                
                <div class="ghi-chu">
                    <i class="fas fa-info-circle"></i>
                    <strong>Intel:</strong> System auto-processes domains from full URLs. You can input:
                    <code>https://target.com/path</code> → <code>target.com</code>
                </div>
                
                <form id="domainCheckerForm">
                    <div class="mb-3">
                        <label for="domainList" class="form-label">
                            <strong>Danh sách domain:</strong>
                        </label>
                        <textarea id="domainList" 
                                  name="domains"
                                  class="form-control domain-input"
                                  rows="10"
                                  placeholder="Nhập danh sách domain, hỗ trợ nhiều định dạng:&#10;&#10;example.com&#10;https://mydomain.org&#10;http://test.net&#10;www.sample.io&#10;https://demo.com/path"
                                  required></textarea>
                        <div class="form-text">
                            <i class="fas fa-info-circle me-1"></i>
                            Không giới hạn số lượng domain. Tự động loại bỏ http/https, www, đường dẫn. Mỗi domain trên một dòng hoặc cách nhau bằng dấu phẩy.
                        </div>
                    </div>
                    
                    <div class="text-center">
                        <button type="submit" class="btn btn-primary btn-sm" id="checkDomainsBtn">
                            <i class="fas fa-search me-2"></i>
                            Check Tình Trạng Domain
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div class="card loading-card mb-4" id="progressCard" style="display:none;">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                    <div>
                        <div class="loading-text mb-1" id="loadingMainText">Đang khởi tạo...</div>
                        <div class="loading-status" id="loadingStatusText">Chưa có domain nào được xử lý</div>
                    </div>
                    <div class="text-end">
                        <div class="fw-bold text-white" id="domainCountText">0 domain(s)</div>
                        <small class="text-muted" id="estimatedTime">--</small>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <small class="text-muted" id="loadingProgressMeta">0/0 domain đã xử lý</small>
                    <strong class="text-success" id="loadingProgressPercent">0%</strong>
                </div>
                <div class="loading-progress">
                    <div class="loading-progress-bar" id="loadingProgressBar"></div>
                </div>
            </div>
        </div>

        <!-- Results Container -->
        <div class="results-container">
            <!-- Results Table -->
            <div class="simple-card">
                <div class="simple-header">
                    <h5 class="mb-0">
                        <i class="fas fa-database me-2"></i>
                        DOMAIN ANALYSIS REPORT
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th class="text-center" width="50px">
                                        <i class="fas fa-hashtag"></i>
                                    </th>
                                    <th width="200px">
                                        <i class="fas fa-globe me-1"></i>DOMAIN
                                    </th>
                                    <th class="text-center" width="120px">
                                        <i class="fas fa-signal me-1"></i>STATUS
                                    </th>
                                    <th width="300px">
                                        <i class="fas fa-server me-1"></i>DNS RECORDS
                                    </th>
                                    <th width="160px">
                                        <i class="fas fa-dns me-1"></i>NAMESERVERS
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="resultsTableBody">
                                <!-- Results will be populated here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- SSL Configuration Modal -->
        <div class="modal fade" id="sslConfigModal" tabindex="-1" aria-labelledby="sslConfigModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header bg-primary text-white">
                        <h5 class="modal-title" id="sslConfigModalLabel">
                            <i class="fas fa-shield-alt me-2"></i>
                            Cấu Hình SSL/TLS
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <form id="sslConfigForm">
                            <input type="hidden" id="sslZoneId" name="zone_id">
                            <input type="hidden" id="sslDomainName" name="domain_name">
                            
                            <!-- Domain Info -->
                            <div class="alert alert-info d-flex align-items-center mb-4">
                                <i class="fas fa-info-circle me-2"></i>
                                <div>
                                    <strong>Domain:</strong> <span id="sslConfigDomainDisplay">-</span><br>
                                    <small class="text-muted">Zone ID: <span id="sslConfigZoneDisplay">-</span></small>
                                </div>
                            </div>
                            
                            <!-- SSL Mode -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <label for="sslMode" class="form-label">
                                        <strong><i class="fas fa-lock me-1"></i>SSL Mode</strong>
                                    </label>
                                    <select class="form-select" id="sslMode" name="ssl_mode">
                                        <option value="off">Off - Không SSL</option>
                                        <option value="flexible">Flexible - Client to Cloudflare</option>
                                        <option value="full">Full - End to End, Self-signed cert OK</option>
                                        <option value="strict">Full (Strict) - End to End, Valid cert required</option>
                                    </select>
                                    <div class="form-text">Chế độ mã hóa SSL/TLS giữa client, Cloudflare và server gốc</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <label for="minTlsVersion" class="form-label">
                                        <strong><i class="fas fa-certificate me-1"></i>Minimum TLS Version</strong>
                                    </label>
                                    <select class="form-select" id="minTlsVersion" name="min_tls_version">
                                        <option value="1.0">TLS 1.0</option>
                                        <option value="1.1">TLS 1.1</option>
                                        <option value="1.2">TLS 1.2 (Khuyến nghị)</option>
                                        <option value="1.3">TLS 1.3 (Mới nhất)</option>
                                    </select>
                                    <div class="form-text">Phiên bản TLS tối thiểu được chấp nhận</div>
                                </div>
                            </div>
                            
                            <!-- SSL Features Row 1 -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="alwaysUseHttps" name="always_use_https">
                                        <label class="form-check-label" for="alwaysUseHttps">
                                            <strong><i class="fas fa-redirect me-1"></i>Always Use HTTPS</strong>
                                        </label>
                                    </div>
                                    <div class="form-text">Tự động chuyển hướng HTTP về HTTPS</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="tls13" name="tls_1_3">
                                        <label class="form-check-label" for="tls13">
                                            <strong><i class="fas fa-shield-alt me-1"></i>TLS 1.3</strong>
                                        </label>
                                    </div>
                                    <div class="form-text">Bật hỗ trợ TLS 1.3 (hiệu suất cao hơn)</div>
                                </div>
                            </div>
                            
                            <!-- SSL Features Row 2 -->
                            <div class="row mb-3">
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="automaticHttpsRewrites" name="automatic_https_rewrites">
                                        <label class="form-check-label" for="automaticHttpsRewrites">
                                            <strong><i class="fas fa-sync-alt me-1"></i>Automatic HTTPS Rewrites</strong>
                                        </label>
                                    </div>
                                    <div class="form-text">Tự động viết lại HTTP links thành HTTPS</div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" id="opportunisticEncryption" name="opportunistic_encryption">
                                        <label class="form-check-label" for="opportunisticEncryption">
                                            <strong><i class="fas fa-key me-1"></i>Opportunistic Encryption</strong>
                                        </label>
                                    </div>
                                    <div class="form-text">Bật mã hóa cơ hội cho HTTP/2</div>
                                </div>
                            </div>
                            
                            <!-- Current Settings Display -->
                            <div class="alert alert-light" id="currentSSLStatus">
                                <i class="fas fa-spinner fa-spin me-2"></i>
                                Đang tải thông tin SSL hiện tại...
                            </div>
                        </form>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Hủy
                        </button>
                        <button type="button" class="btn btn-success btn-sm" id="saveSSLConfig">
                            <i class="fas fa-save me-2"></i>Lưu Cấu Hình
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- DNS Records Modal -->
        <div class="modal fade" id="dnsRecordsModal" tabindex="-1" aria-labelledby="dnsRecordsModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header bg-success text-white">
                        <h5 class="modal-title" id="dnsRecordsModalLabel">
                            <i class="fas fa-list me-2"></i>
                            DNS Records
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-info d-flex align-items-center mb-4" id="dnsRecordsDomainInfo">
                            <i class="fas fa-info-circle me-2"></i>
                            <div>
                                <strong>Domain:</strong> <span id="dnsRecordsDomainDisplay">-</span><br>
                                <small class="text-muted">Zone ID: <span id="dnsRecordsZoneDisplay">-</span></small>
                            </div>
                        </div>
                        
                        <div id="dnsRecordsContent">
                            <div class="text-center">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Đang tải...</span>
                                </div>
                                <p class="mt-3 text-muted">Đang tải DNS records...</p>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">
                            <i class="fas fa-times me-2"></i>Đóng
                        </button>
                        <button type="button" class="btn btn-success btn-sm" id="exportDNSRecords">
                            <i class="fas fa-download me-2"></i>Xuất CSV
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Navigation -->
        <div class="text-center mt-4 d-flex justify-content-center gap-2 flex-wrap">
            <a href="search.php" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-search me-2"></i>
                Tìm Kiếm Domain
            </a>

            <a href="index.php" class="btn btn-outline-secondary btn-sm">
                <i class="fas fa-home me-2"></i>
                Trang Chủ
            </a>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        class DomainStatusChecker {
            constructor() {
                this.form = document.getElementById('domainCheckerForm');
                this.resultsContainer = document.querySelector('.results-container');
                this.progressCard = document.getElementById('progressCard');
                this.submitButton = document.getElementById('checkDomainsBtn');
                this.resultBody = document.getElementById('resultsTableBody');
                this.initializeEvents();
            }

            initializeEvents() {
                this.form.addEventListener('submit', (e) => {
                    e.preventDefault();
                    this.checkDomains();
                });
            }

            async checkDomains() {
                const domains = this.parseDomainList();
                if (domains.length === 0) {
                    this.showError('Vui lòng nhập danh sách domain');
                    return;
                }

                this.resetResults();
                this.showLoading(domains.length);

                try {
                    const results = [];

                    for (let index = 0; index < domains.length; index++) {
                        const domain = domains[index];
                        this.updateLoadingState(index, domains.length, domain);

                        const formData = new FormData();
                        formData.append('action', 'check_domain');
                        formData.append('domain', domain);
                        formData.append('index', String(index + 1));

                        const response = await fetch(window.location.href, {
                            method: 'POST',
                            body: formData
                        });

                        if (!response.ok) {
                            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                        }

                        const data = await this.parseJsonResponse(response);
                        if (!data.success || !data.result) {
                            throw new Error(data.error || `Không thể check domain ${domain}`);
                        }

                        results.push(data.result);
                        window._domainResults = results.slice();
                        this.appendResultRow(data.result);
                        this.updateProgressBar(((index + 1) / domains.length) * 100);
                    }

                    this.showCompleted(domains.length);
                    this.displayResults(results);
                } catch (error) {
                    console.error('Check domains error:', error);
                    this.showError('Lỗi kết nối: ' + error.message);
                } finally {
                    this.hideLoading();
                }
            }

            async parseJsonResponse(response) {
                const responseText = await response.text();
                try {
                    return JSON.parse(responseText);
                } catch (error) {
                    console.error('Response is not valid JSON:', responseText);
                    throw new Error('Server returned invalid response. Check browser console for details.');
                }
            }

            parseDomainList() {
                const domainText = document.getElementById('domainList').value || '';
                return Array.from(new Set(
                    domainText
                        .split(/[\r\n,;]+/)
                        .map(domain => domain.trim())
                        .filter(domain => domain.length > 0)
                ));
            }

            resetResults() {
                if (this.resultBody) {
                    this.resultBody.innerHTML = '';
                }
                window._domainResults = [];
                if (this.resultsContainer) {
                    this.resultsContainer.style.display = 'none';
                }
            }

            displayResults(results) {
                if (this.resultBody && this.resultBody.children.length === 0) {
                    this.displayResultsTable(results);
                }
                this.resultsContainer.style.display = 'block';
                
                // Scroll to results
                this.resultsContainer.scrollIntoView({ behavior: 'smooth' });
            }

            displayResultsTable(results) {
                const tableBody = document.getElementById('resultsTableBody');
                
                tableBody.innerHTML = results.map(result => this.renderResultRow(result)).join('');
            }

            appendResultRow(result) {
                if (!this.resultBody) return;
                this.resultBody.insertAdjacentHTML('beforeend', this.renderResultRow(result));
            }

            renderResultRow(result) {
                const statusBadge = this.getStatusBadge(result);
                const dnsStatus = this.getDNSStatus(result.details?.dns_status);
                const nameservers = this.formatNameservers(result.details?.nameservers);

                return `
                    <tr>
                        <td class="text-center">
                            <span class="simple-index">${result.index || ''}</span>
                        </td>
                        <td>
                            <div class="domain-info">
                                <strong class="domain-name">${result.domain || ''}</strong>
                                ${result.cleaned_domain && result.cleaned_domain !== result.domain ? 
                                    `<div class="cleaned-domain-info">
                                        <i class="fas fa-arrow-right text-success"></i>
                                        <span class="cleaned-domain-text">${result.cleaned_domain}</span>
                                    </div>` : ''
                                }
                            </div>
                        </td>
                        <td class="text-center">${statusBadge}</td>
                        <td class="dns-records-cell">${dnsStatus}</td>
                        <td class="nameservers-cell">${nameservers}</td>
                    </tr>
                `;
            }

            getStatusBadge(result) {
                if (!result.success) {
                    return '<span class="simple-status status-error">Error</span>';
                }

                const status = result.details.status || 'unknown';
                
                switch (status) {
                    case 'active':
                        return '<span class="simple-status status-active">Active</span>';
                    case 'pending':
                        return '<span class="simple-status status-pending">Pending</span>';
                    case 'not_found':
                        return '<span class="simple-status status-not-found">Not Found</span>';
                    default:
                        return '<span class="simple-status status-error">Unknown</span>';
                }
            }

            getDNSStatus(dnsStatus) {
                if (!dnsStatus || dnsStatus.status === 'error') {
                    return '<div class="alert alert-danger py-2 px-3 mb-0"><i class="fas fa-times me-2"></i>DNS Error</div>';
                }

                if (dnsStatus.status === 'configured') {
                    const records = dnsStatus.records || [];
                    const recordCount = dnsStatus.record_count || 0;
                    const typeCounts = dnsStatus.type_counts || {};
                    
                    if (records.length === 0) {
                        return '<div class="alert alert-warning py-2 px-3 mb-0"><i class="fas fa-exclamation-triangle me-2"></i>No DNS records found</div>';
                    }
                    
                    // Create comprehensive DNS records table
                    return `
                        <div class="dns-records-full-display">
                            <div class="table-responsive">
                                <table class="table table-sm mb-0">
                                    <thead>
                                        <tr>
                                            <th style="min-width: 60px;">Type</th>
                                            <th style="min-width: 200px;">Name</th>
                                            <th style="min-width: 250px;">Content</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${records.map(record => {
                                            let content = record.content || '-';
                                            if (record.type === 'SRV' && record.target) {
                                                content = `${record.weight || 0} ${record.port || 0} ${record.target}`;
                                            }
                                            if (record.type === 'MX' && record.priority !== undefined) {
                                                content = `${record.priority} ${record.content}`;
                                            }
                                            
                                            // Truncate very long content for readability
                                            if (content.length > 80) {
                                                content = content.substring(0, 80) + '...';
                                            }
                                            
                                            return `
                                                <tr>
                                                    <td><span class="dns-record-type dns-type-${record.type}">${record.type}</span></td>
                                                    <td><code style="font-size: 0.8em;">${record.name || '-'}</code></td>
                                                    <td><code style="font-size: 0.8em;" title="${record.content || ''}">${content}</code></td>
                                                </tr>
                                            `;
                                        }).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    `;
                }

                return '<div class="alert alert-secondary py-2 px-3 mb-0"><i class="fas fa-minus me-2"></i>Not configured</div>';
            }

            getSSLStatus(sslStatus) {
                if (!sslStatus || sslStatus.status === 'error') {
                    return '<i class="fas fa-times text-danger" title="SSL Error"></i>';
                }

                if (sslStatus.status === 'off') {
                    return '<i class="fas fa-unlock text-danger" title="SSL Off"></i>';
                }

                return '<i class="fas fa-lock text-success" title="SSL On"></i>';
            }

            formatNameservers(nameservers) {
                if (!nameservers || !Array.isArray(nameservers) || nameservers.length === 0) {
                    return '<small class="text-muted">N/A</small>';
                }

                // Show all nameservers
                return `
                    <div class="nameserver-display">
                        ${nameservers.map(ns => `<small class="d-block text-primary fw-bold">${ns}</small>`).join('')}
                    </div>
                `;
            }

            formatDomainDetails(details) {
                const nameserversList = details.nameservers && details.nameservers.length > 0 
                    ? details.nameservers.map(ns => `<small class="d-block text-primary">${ns}</small>`).join('')
                    : '<small class="text-muted">N/A</small>';
                
                // Format DNS Records display
                let dnsRecordsHtml = '';
                if (details.dns_status && details.dns_status.records && details.dns_status.records.length > 0) {
                    dnsRecordsHtml = `
                        <div class="dns-records-container">
                            ${details.dns_status.records.map(record => {
                                const proxiedBadge = record.proxied ? '<span class="proxied-badge">Proxied</span>' : '';
                                let content = record.content;
                                
                                // Handle different record types
                                if (record.type === 'MX') {
                                    content = `${record.priority || 0} ${record.content}`;
                                } else if (record.type === 'SRV') {
                                    content = `${record.priority || 0} ${record.weight || 0} ${record.port || 0} ${record.target || record.content}`;
                                }
                                
                                // Truncate long content
                                if (content && content.length > 40) {
                                    content = content.substring(0, 40) + '...';
                                }
                                
                                return `
                                    <div class="dns-record-item">
                                        <span class="dns-record-type dns-type-${record.type}">${record.type}</span>
                                        <strong>${record.name}</strong> 
                                        <span class="text-muted">→</span> 
                                        ${content}
                                        ${proxiedBadge}
                                        <small class="text-muted d-block">TTL: ${record.ttl || 'Auto'}</small>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                    `;
                } else {
                    dnsRecordsHtml = '<small class="text-muted">Không có DNS records hoặc lỗi khi tải</small>';
                }
                    
                return `
                    <div class="row">
                        <div class="col-md-4">
                            <strong>Thông tin Domain:</strong><br>
                            <small><i class="fas fa-calendar-plus"></i> Tạo: ${this.formatDate(details.created_on)}</small><br>
                            <small><i class="fas fa-edit"></i> Cập nhật: ${this.formatDate(details.modified_on)}</small><br>
                            <small><i class="fas fa-tag"></i> Gói: ${details.plan || 'N/A'}</small><br>
                            <small><i class="fas fa-key"></i> Zone ID: ${details.zone_id || 'N/A'}</small>
                        </div>
                        <div class="col-md-4">
                            <strong><i class="fas fa-server"></i> Cloudflare Nameservers:</strong><br>
                            ${nameserversList}
                        </div>
                        <div class="col-md-4">
                            <strong><i class="fas fa-shield-alt"></i> SSL Status:</strong><br>
                            <small>Mode: ${details.ssl_status?.status || 'N/A'}</small><br>
                            <small>HTTPS: ${details.ssl_status?.always_https ? '✓ Enabled' : '✗ Disabled'}</small><br>
                            <small>Certificate: ${details.ssl_status?.certificate_status || 'N/A'}</small>
                        </div>
                    </div>
                    <hr>
                    <div class="row">
                        <div class="col-12">
                            ${dnsRecordsHtml}
                        </div>
                    </div>
                `;
            }
            
            formatDate(dateString) {
                if (!dateString) return 'N/A';
                try {
                    return new Date(dateString).toLocaleDateString('vi-VN');
                } catch (e) {
                    return dateString;
                }
            }

            sanitizeId(str) {
                return str.replace(/[^a-zA-Z0-9]/g, '');
            }

            showLoading(totalDomains) {
                if (this.progressCard) {
                    this.progressCard.style.display = 'block';
                }
                if (this.resultsContainer) {
                    this.resultsContainer.style.display = 'none';
                }
                if (this.submitButton) {
                    this.submitButton.disabled = true;
                    this.submitButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang kiểm tra...';
                }
                this.updateProgressBar(0);
                const progressMetaElement = document.getElementById('loadingProgressMeta');
                if (progressMetaElement) {
                    progressMetaElement.textContent = `0/${totalDomains} domain đã xử lý`;
                }
                const domainCountElement = document.getElementById('domainCountText');
                if (domainCountElement) {
                    domainCountElement.textContent = `${totalDomains} domain(s)`;
                }
                const estimatedTimeElement = document.getElementById('estimatedTime');
                if (estimatedTimeElement) {
                    estimatedTimeElement.textContent = `${Math.max(5, totalDomains * 2)}- ${Math.max(10, totalDomains * 3)} giây`;
                }
                const mainTextElement = document.getElementById('loadingMainText');
                if (mainTextElement) {
                    mainTextElement.textContent = 'Khởi tạo kiểm tra...';
                }
                const statusTextElement = document.getElementById('loadingStatusText');
                if (statusTextElement) {
                    statusTextElement.textContent = 'Đang chuẩn bị danh sách domain';
                }
            }

            hideLoading() {
                if (this.submitButton) {
                    this.submitButton.disabled = false;
                    this.submitButton.innerHTML = '<i class="fas fa-search me-2"></i>Check Tình Trạng Domain';
                }
            }

            updateLoadingState(index, totalDomains, domain) {
                const processed = index + 1;
                const mainTextElement = document.getElementById('loadingMainText');
                if (mainTextElement) {
                    mainTextElement.textContent = `Đang kiểm tra ${processed}/${totalDomains}`;
                }

                const statusTextElement = document.getElementById('loadingStatusText');
                if (statusTextElement) {
                    statusTextElement.textContent = domain;
                }

                const domainCountElement = document.getElementById('domainCountText');
                if (domainCountElement) {
                    domainCountElement.textContent = `${processed}/${totalDomains} domain(s)`;
                }

                const progressMetaElement = document.getElementById('loadingProgressMeta');
                if (progressMetaElement) {
                    progressMetaElement.textContent = `${processed}/${totalDomains} domain đã xử lý`;
                }
            }

            showCompleted(totalDomains) {
                const mainTextElement = document.getElementById('loadingMainText');
                if (mainTextElement) {
                    mainTextElement.textContent = `Hoàn tất kiểm tra ${totalDomains} domain(s)`;
                }

                const statusTextElement = document.getElementById('loadingStatusText');
                if (statusTextElement) {
                    statusTextElement.textContent = 'Đang tổng hợp kết quả';
                }

                const progressMetaElement = document.getElementById('loadingProgressMeta');
                if (progressMetaElement) {
                    progressMetaElement.textContent = `${totalDomains}/${totalDomains} domain đã xử lý`;
                }

                this.updateProgressBar(100);
            }
            
            updateProgressBar(percent) {
                const safePercent = Math.min(Math.max(percent, 0), 100);
                const progressBar = document.getElementById('loadingProgressBar');
                if (progressBar) {
                    progressBar.style.width = `${safePercent}%`;
                }

                const progressPercentElement = document.getElementById('loadingProgressPercent');
                if (progressPercentElement) {
                    progressPercentElement.textContent = `${Math.round(safePercent)}%`;
                }

                this.progressPercent = safePercent;
            }

            showError(message) {
                alert('Error: ' + message);
            }
        }

        // Global function for detail toggle
        function toggleDetails(domain) {
            const detailsId = 'details-' + domain.replace(/[^a-zA-Z0-9]/g, '');
            const details = document.getElementById(detailsId);
            
            if (details) {
                details.style.display = details.style.display === 'block' ? 'none' : 'block';
            }
        }

        // Global variable to store current zone config
        window._sslCurrentZoneId = '';
        window._sslCurrentDomain = '';
        window._currentDNSRecords = null;
        
        // Global function for DNS Records viewing
        function showDNSRecords(domainName, zoneId) {
            if (!domainName) {
                alert('Thiếu thông tin domain name');
                return;
            }
            
            // Store current domain records for export
            const domainResult = window._domainResults?.find(r => r.domain === domainName);
            window._currentDNSRecords = domainResult?.details?.dns_status?.records || [];
            
            // Set modal data
            document.getElementById('dnsRecordsDomainDisplay').textContent = domainName;
            document.getElementById('dnsRecordsZoneDisplay').textContent = zoneId || 'N/A';
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('dnsRecordsModal'));
            modal.show();
            
            // Display DNS records
            displayDNSRecordsInModal(window._currentDNSRecords, domainName);
        }
        
        function displayDNSRecordsInModal(records, domainName) {
            const container = document.getElementById('dnsRecordsContent');
            
            if (!records || records.length === 0) {
                container.innerHTML = `
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        Không tìm thấy DNS records cho domain ${domainName}
                    </div>
                `;
                return;
            }
            
            // Group records by type
            const groupedRecords = records.reduce((acc, record) => {
                if (!acc[record.type]) acc[record.type] = [];
                acc[record.type].push(record);
                return acc;
            }, {});
            
            const recordTypes = Object.keys(groupedRecords).sort();
            
            container.innerHTML = `
                <div class="row mb-3">
                    <div class="col-12">
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-sm">
                        <thead>
                            <tr>
                                <th>Type</th>
                                <th>Name</th>
                                <th>Content</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${records.map(record => {
                                let content = record.content || '-';
                                if (record.type === 'SRV' && record.target) {
                                    content = record.target;
                                }
                                if (record.type === 'MX' && record.priority !== undefined) {
                                    content = `${record.priority} ${record.content}`;
                                }
                                
                                return `
                                    <tr>
                                        <td><span class="dns-record-type dns-type-${record.type}">${record.type}</span></td>
                                        <td>${record.name || '-'}</td>
                                        <td>${content}</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                    </table>
                </div>
            `;
        }
        
        // Export DNS Records to CSV
        function exportDNSRecordsCSV() {
            if (!window._currentDNSRecords || window._currentDNSRecords.length === 0) {
                alert('Không có dữ liệu DNS records để xuất');
                return;
            }
            
            const headers = ['Type', 'Name', 'Content'];
            let csvContent = headers.join(',') + '\n';
            
            window._currentDNSRecords.forEach(record => {
                let content = record.content || '';
                if (record.type === 'SRV' && record.target) {
                    content = record.target;
                }
                if (record.type === 'MX' && record.priority !== undefined) {
                    content = `${record.priority} ${record.content}`;
                }
                
                const row = [
                    record.type || '',
                    `"${(record.name || '').replace(/"/g, '""')}"`,
                    `"${content.replace(/"/g, '""')}"`
                ];
                csvContent += row.join(',') + '\n';
            });
            
            // Create and download file
            const domain = document.getElementById('dnsRecordsDomainDisplay').textContent;
            const filename = `dns-records-${domain}-${new Date().toISOString().substr(0, 10)}.csv`;
            
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            
            if (link.download !== undefined) {
                const url = URL.createObjectURL(blob);
                link.setAttribute('href', url);
                link.setAttribute('download', filename);
                link.style.visibility = 'hidden';
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);
            }
        }
        
        // Global function for SSL configuration
        function openSSLConfig(zoneId, domainName) {
            if (!zoneId || !domainName || zoneId === 'undefined' || zoneId === 'null') {
                alert('Thiếu thông tin zone ID hoặc domain name');
                return;
            }
            
            // Store in global variables
            window._sslCurrentZoneId = zoneId;
            window._sslCurrentDomain = domainName;
            
            // Set modal data
            document.getElementById('sslZoneId').value = zoneId;
            document.getElementById('sslDomainName').value = domainName;
            document.getElementById('sslConfigDomainDisplay').textContent = domainName;
            document.getElementById('sslConfigZoneDisplay').textContent = zoneId;
            
            // Show loading state
            document.getElementById('currentSSLStatus').innerHTML = `
                <i class="fas fa-spinner fa-spin me-2"></i>
                Đang tải thông tin SSL hiện tại...
            `;
            
            // Open modal
            const modal = new bootstrap.Modal(document.getElementById('sslConfigModal'));
            modal.show();
            
            // Load current SSL settings
            loadCurrentSSLSettings(zoneId);
        }
        
        // Load current SSL settings
        async function loadCurrentSSLSettings(zoneId) {
            // Use passed parameter, fallback to global
            const resolvedZoneId = zoneId || window._sslCurrentZoneId;
            
            if (!resolvedZoneId) {
                document.getElementById('currentSSLStatus').innerHTML = `
                    <i class="fas fa-times text-danger me-2"></i>
                    Không tìm thấy Zone ID
                `;
                return;
            }
            
            try {
                const formData = new FormData();
                formData.append('action', 'get_ssl_settings');
                formData.append('zone_id', resolvedZoneId);
                
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                
                if (data.success && data.ssl_settings) {
                    populateSSLForm(data.ssl_settings);
                    showCurrentSSLStatus(data.ssl_settings);
                } else {
                    document.getElementById('currentSSLStatus').innerHTML = `
                        <i class="fas fa-exclamation-triangle text-warning me-2"></i>
                        Không thể tải thông tin SSL: ${data.error || 'Unknown error'}
                    `;
                }
            } catch (error) {
                console.error('Error loading SSL settings:', error);
                document.getElementById('currentSSLStatus').innerHTML = `
                    <i class="fas fa-times text-danger me-2"></i>
                    Lỗi khi tải thông tin SSL: ${error.message}
                `;
            }
        }
        
        // Populate SSL form with current settings
        function populateSSLForm(settings) {
            // SSL Mode
            if (settings.ssl_mode) {
                document.getElementById('sslMode').value = settings.ssl_mode;
            }
            
            // Min TLS Version
            if (settings.min_tls_version) {
                document.getElementById('minTlsVersion').value = settings.min_tls_version;
            }
            
            // Boolean settings
            document.getElementById('alwaysUseHttps').checked = settings.always_use_https || false;
            document.getElementById('tls13').checked = settings.tls_1_3 || false;
            document.getElementById('automaticHttpsRewrites').checked = settings.automatic_https_rewrites || false;
            document.getElementById('opportunisticEncryption').checked = settings.opportunistic_encryption || false;
        }
        
        // Show current SSL status
        function showCurrentSSLStatus(settings) {
            const statusItems = [
                {
                    label: 'SSL Mode',
                    value: settings.ssl_mode || 'Unknown',
                    icon: 'fas fa-lock'
                },
                {
                    label: 'Always HTTPS',
                    value: settings.always_use_https ? 'Enabled' : 'Disabled',
                    icon: 'fas fa-redirect'
                },
                {
                    label: 'TLS 1.3',
                    value: settings.tls_1_3 ? 'Enabled' : 'Disabled',
                    icon: 'fas fa-shield-alt'
                },
                {
                    label: 'Min TLS',
                    value: settings.min_tls_version || 'Unknown',
                    icon: 'fas fa-certificate'
                }
            ];
            
            const statusHtml = statusItems.map(item => `
                <div class="col-md-3">
                    <small class="text-muted"><i class="${item.icon} me-1"></i>${item.label}:</small><br>
                    <strong>${item.value}</strong>
                </div>
            `).join('');
            
            document.getElementById('currentSSLStatus').innerHTML = `
                <div><strong><i class="fas fa-cog me-2"></i>Cấu Hình SSL Hiện Tại:</strong></div>
                <div class="row mt-2">
                    ${statusHtml}
                </div>
            `;
        }
        
        // SSL Configuration save handler
        class SSLConfigHandler {
            constructor() {
                this.saveButton = document.getElementById('saveSSLConfig');
                this.initializeEvents();
            }
            
            initializeEvents() {
                this.saveButton.addEventListener('click', () => {
                    this.saveSSLConfiguration();
                });
            }
            
            async saveSSLConfiguration() {
                const form = document.getElementById('sslConfigForm');
                const formData = new FormData(form);
                
                // Parse form data into settings object
                const settings = {};
                
                // Get select values
                const sslMode = document.getElementById('sslMode').value;
                const minTlsVersion = document.getElementById('minTlsVersion').value;
                
                if (sslMode) settings.ssl_mode = sslMode;
                if (minTlsVersion) settings.min_tls_version = minTlsVersion;
                
                // Get checkbox values
                settings.always_use_https = document.getElementById('alwaysUseHttps').checked;
                settings.tls_1_3 = document.getElementById('tls13').checked;
                settings.automatic_https_rewrites = document.getElementById('automaticHttpsRewrites').checked;
                settings.opportunistic_encryption = document.getElementById('opportunisticEncryption').checked;
                
                // Show loading state
                this.saveButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Đang lưu...';
                this.saveButton.disabled = true;
                
                try {
                    // Use global variable as fallback for zone_id
                    const zoneId = window._sslCurrentZoneId || document.getElementById('sslZoneId').value;
                    
                    if (!zoneId) {
                        throw new Error('Không tìm thấy Zone ID. Vui lòng đóng và mở lại modal.');
                    }
                    
                    const requestData = new FormData();
                    requestData.append('action', 'configure_ssl');
                    requestData.append('zone_id', zoneId);
                    requestData.append('settings', JSON.stringify(settings));
                    
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: requestData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        this.showSuccessMessage(data);
                        
                        // Close modal after delay
                        setTimeout(() => {
                            const modal = bootstrap.Modal.getInstance(document.getElementById('sslConfigModal'));
                            modal.hide();
                        }, 2000);
                        
                    } else {
                        this.showErrorMessage(data.error || 'Có lỗi xảy ra khi lưu cấu hình SSL');
                    }
                } catch (error) {
                    console.error('SSL save error:', error);
                    this.showErrorMessage('Lỗi kết nối: ' + error.message);
                } finally {
                    this.saveButton.innerHTML = '<i class="fas fa-save me-2"></i>Lưu Cấu Hình';
                    this.saveButton.disabled = false;
                }
            }
            
            showSuccessMessage(data) {
                const summary = data.summary;
                const alertHtml = `
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Cấu hình SSL đã được lưu thành công!</strong><br>
                        <small>Đã cập nhật ${summary.successful}/${summary.total_settings} cài đặt</small>
                    </div>
                `;
                
                // Update current status area
                document.getElementById('currentSSLStatus').innerHTML = alertHtml;
            }
            
            showErrorMessage(error) {
                const alertHtml = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <strong>Lỗi khi lưu cấu hình SSL:</strong><br>
                        <small>${error}</small>
                    </div>
                `;
                
                // Update current status area
                document.getElementById('currentSSLStatus').innerHTML = alertHtml;
            }
        }

        // Initialize when DOM is ready
        document.addEventListener('DOMContentLoaded', () => {
            window.domainChecker = new DomainStatusChecker();
            window.sslConfigHandler = new SSLConfigHandler();
            
            // Export DNS Records event handler
            document.getElementById('exportDNSRecords').addEventListener('click', exportDNSRecordsCSV);
        });
    </script>
    
    <!-- Tactical Gaming Interface -->
    <script src="assets/js/tactical-interface.js"></script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        // Initialize Menu Toggle
        function initializeMenuToggle() {
            const sidebarToggle = document.getElementById('sidebarToggle');
            
            if (sidebarToggle) {
                sidebarToggle.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    document.body.classList.toggle('sidebar-collapsed');
                    
                    const isCollapsed = document.body.classList.contains('sidebar-collapsed');
                    this.innerHTML = isCollapsed ? '☰' : '✖';
                    this.setAttribute('title', isCollapsed ? 'Hiện Menu' : 'Ẩn Menu');
                    
                    this.style.boxShadow = '0 0 20px rgba(0, 255, 0, 0.8)';
                    this.style.background = 'rgba(0, 255, 0, 0.3)';
                    setTimeout(() => {
                        this.style.boxShadow = '0 0 15px rgba(0, 255, 0, 0.6)';
                        this.style.background = 'rgba(0, 255, 0, 0.1)';
                    }, 300);
                });
                
                // Keyboard shortcuts
                document.addEventListener('keydown', function(e) {
                    if ((e.ctrlKey && e.key === 'b') || (e.ctrlKey && e.key === 'm')) {
                        e.preventDefault();
                        sidebarToggle.click();
                    }
                });
                
                sidebarToggle.innerHTML = '✖';
                sidebarToggle.setAttribute('title', 'Ẩn Menu (Ctrl+B)');
            }
        }
        
        document.addEventListener('DOMContentLoaded', () => {
            setTimeout(() => {
                initializeMenuToggle();
            }, 100);
        });
    </script>
    
</body>
</html>