<?php
/**
 * Example sử dụng Search API
 * Chạy file này để test search functionality
 */

require_once 'config.php';
require_once 'CloudflareAPI.php';

echo "=== CLOUDFLARE SEARCH API EXAMPLES ===\n\n";

try {
    $cloudflare = new CloudflareAPI();
    
    // 1. Search cơ bản
    echo "1. SEARCH CƠ BẢN:\n";
    echo "Searching for 'jp'...\n";
    $results = $cloudflare->searchZones('jp');
    
    if ($results && isset($results['result'])) {
        echo "Found " . count($results['result']) . " zones:\n";
        foreach ($results['result'] as $zone) {
            echo "  - {$zone['name']} ({$zone['status']})\n";
        }
    } else {
        echo "No results found.\n";
    }
    
    echo "\n";
    
    // 2. Search với filter status
    echo "2. SEARCH VỚI STATUS FILTER:\n"; 
    echo "Searching for active zones...\n";
    $results = $cloudflare->searchZones('', 1, 10, 'active');
    
    if ($results && isset($results['result'])) {
        echo "Found " . count($results['result']) . " active zones:\n";
        foreach ($results['result'] as $zone) {
            echo "  - {$zone['name']} (Plan: {$zone['plan']['name']})\n";
        }
    } else {
        echo "No active zones found.\n";
    }
    
    echo "\n";
    
    // 3. Search với pagination
    echo "3. PAGINATED SEARCH:\n";
    echo "Getting first 5 zones...\n";
    $results = $cloudflare->searchZones('', 1, 5);
    
    if ($results && isset($results['result'])) {
        echo "Page 1 (5 results):\n";
        foreach ($results['result'] as $i => $zone) {
            echo "  " . ($i + 1) . ". {$zone['name']} - Created: " . 
                 date('d/m/Y', strtotime($zone['created_on'])) . "\n";
        }
        
        if (isset($results['result_info'])) {
            $info = $results['result_info'];
            echo "\nPagination Info:\n";
            echo "  - Current page: {$info['page']}\n";
            echo "  - Per page: {$info['per_page']}\n";
            echo "  - Total pages: " . ceil($info['total_count'] / $info['per_page']) . "\n";
            echo "  - Total results: {$info['total_count']}\n";
        }
    }
    
    echo "\n";
    
    // 4. Test API endpoint trực tiếp
    echo "4. TEST API ENDPOINT:\n";
    echo "Testing direct API call...\n";
    
    $apiUrl = "http://localhost:8000/?action=search&q=com&per_page=3";
    echo "URL: {$apiUrl}\n";
    
    // Simulate API call
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($response && $httpCode == 200) {
        $data = json_decode($response, true);
        if ($data && $data['success']) {
            echo "API Response successful!\n";
            echo "Query: " . ($data['search']['query'] ?? 'N/A') . "\n";
            echo "Results: " . ($data['search']['total_results'] ?? 0) . "\n";
            echo "Search fields: " . implode(', ', $data['search']['search_fields'] ?? []) . "\n";
        } else {
            echo "API returned error: " . ($data['error'] ?? 'Unknown error') . "\n";
        }
    } else {
        echo "API call failed (HTTP: {$httpCode})\n";
        echo "Make sure to start the server with: php -S localhost:8000\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting:\n";
    echo "1. Check your API token in token.txt\n";
    echo "2. Verify email in config.php\n";
    echo "3. Ensure internet connection\n";
    echo "4. Start local server: php -S localhost:8000\n";
}

echo "\n=== SEARCH API URLS ===\n";
echo "Basic search:        /?action=search&q=example\n";
echo "With status filter:  /?action=search&q=com&status=active\n";
echo "With plan filter:    /?action=search&q=domain&plan=Free\n";
echo "With pagination:     /?action=search&q=test&page=1&per_page=10\n";

echo "\n=== JAVASCRIPT EXAMPLE ===\n";
echo "// Frontend JavaScript usage:\n";
echo "fetch('/?action=search&q=example')\n";
echo "  .then(response => response.json())\n";
echo "  .then(data => {\n";
echo "    if (data.success) {\n";
echo "      console.log('Found:', data.search.total_results, 'domains');\n";
echo "      data.data.result.forEach(zone => {\n";
echo "        console.log(zone.name, zone.status);\n";
echo "      });\n";
echo "    }\n";
echo "  });\n";

echo "\n=== TEST COMPLETED ===\n";