<?php
/**
 * Debug version of domain checking for troubleshooting
 */

require_once 'config.php';
require_once 'CloudflareAPI.php';

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    try {
        $action = $_POST['action'] ?? '';
        $domainList = $_POST['domain_list'] ?? '';
        
        echo json_encode([
            'debug' => true,
            'received_action' => $action,
            'received_domain_list' => $domainList,
            'parsed_domains' => parseAndValidateDomains($domainList),
            'test_api_connection' => testAPIConnection()
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
    }
    exit;
}

function testAPIConnection() {
    try {
        $api = new CloudflareAPI();
        $zones = $api->listZones(1, 10);
        
        $result = [
            'api_success' => $zones['success'] ?? false,
            'zones_count' => isset($zones['result']) ? count($zones['result']) : 0,
            'sample_zones' => []
        ];
        
        if (isset($zones['result']) && is_array($zones['result'])) {
            foreach (array_slice($zones['result'], 0, 5) as $zone) {
                $result['sample_zones'][] = [
                    'name' => $zone['name'] ?? 'unknown',
                    'status' => $zone['status'] ?? 'unknown',
                    'plan' => $zone['plan']['name'] ?? 'unknown'
                ];
            }
        }
        
        if (!$zones['success'] && isset($zones['errors'])) {
            $result['errors'] = $zones['errors'];
        }
        
        return $result;
        
    } catch (Exception $e) {
        return [
            'error' => $e->getMessage(),
            'api_available' => false
        ];
    }
}

// Include the parseAndValidateDomains function
function parseAndValidateDomains($domainListText) {
    if (empty($domainListText)) {
        return [];
    }
    
    $rawDomains = explode("\n", $domainListText);
    $validDomains = [];
    
    foreach ($rawDomains as $domain) {
        // Clean and normalize domain
        $domain = strtolower(trim($domain));
        
        // Remove protocol if present
        $domain = preg_replace('/^https?:\/\//', '', $domain);
        
        // Remove trailing slash and path
        $domain = preg_replace('/\/.*$/', '', $domain);
        
        // Remove www prefix for consistency
        $domain = preg_replace('/^www\./', '', $domain);
        
        // Skip empty domains
        if (empty($domain)) {
            continue;
        }
        
        // Basic domain validation
        if (isValidDomain($domain)) {
            // Avoid duplicates
            if (!in_array($domain, $validDomains)) {
                $validDomains[] = $domain;
            }
        }
    }
    
    return $validDomains;
}

function isValidDomain($domain) {
    // Check basic domain format
    if (!preg_match('/^[a-z0-9]([a-z0-9\-\.]*[a-z0-9])?\.[a-z]{2,}$/i', $domain)) {
        return false;
    }
    
    // Check if domain has at least one dot
    if (strpos($domain, '.') === false) {
        return false;
    }
    
    // Check domain length
    if (strlen($domain) > 253 || strlen($domain) < 3) {
        return false;
    }
    
    // Check individual label lengths
    $labels = explode('.', $domain);
    foreach ($labels as $label) {
        if (strlen($label) > 63 || strlen($label) < 1) {
            return false;
        }
    }
    
    return true;
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Domain Debug Tool</title>
    <style>
        body { font-family: 'Roboto', sans-serif; margin: 20px; }
        .debug-box { background: #f5f5f5; padding: 15px; margin: 10px 0; border-radius: 5px; }
        pre { background: #fff; padding: 10px; border: 1px solid #ddd; overflow-x: auto; }
        button { padding: 10px 20px; background: #007cba; color: white; border: none; border-radius: 3px; cursor: pointer; }
        button:hover { background: #005a87; }
        input[type="text"] { width: 300px; padding: 8px; margin: 10px 0; }
    </style>
</head>
<body>
    <h1>Domain Debug Tool</h1>
    
    <div class="debug-box">
        <h3>Test Domain Parsing & API Connection</h3>
        <input type="text" id="testDomain" value="xiaofeng.cn.com" placeholder="Enter domain to test">
        <br>
        <button onclick="testDomain()">Test Domain</button>
        <button onclick="testAPI()">Test API Connection Only</button>
    </div>
    
    <div id="results"></div>
    
    <script>
    function testDomain() {
        const domain = document.getElementById('testDomain').value;
        
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=debug&domain_list=${encodeURIComponent(domain)}`
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('results').innerHTML = '<h3>Results:</h3><pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            document.getElementById('results').innerHTML = '<h3>Error:</h3><pre>' + error + '</pre>';
        });
    }
    
    function testAPI() {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=api_test'
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('results').innerHTML = '<h3>API Test Results:</h3><pre>' + JSON.stringify(data, null, 2) + '</pre>';
        })
        .catch(error => {
            document.getElementById('results').innerHTML = '<h3>Error:</h3><pre>' + error + '</pre>';
        });
    }
    </script>
</body>
</html>