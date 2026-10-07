<?php
/**
 * Test Search Functionality
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';
require_once 'CloudflareAPI.php';

try {
    echo "=== Testing Search Functionality ===\n";
    
    // Initialize Cloudflare API
    $cloudflare = new CloudflareAPI();
    echo "✓ CloudflareAPI initialized\n";
    
    // Test basic API connectivity
    echo "\n=== Testing API Connectivity ===\n";
    $zones = $cloudflare->listZones(1, 5);
    echo "✓ API connectivity OK\n";
    echo "Found " . (count($zones['result'] ?? [])) . " zones\n";
    
    // Test search functionality
    echo "\n=== Testing Search Function ===\n";
    
    if (!empty($zones['result'])) {
        $firstDomain = $zones['result'][0]['name'] ?? null;
        if ($firstDomain) {
            echo "Testing search for: {$firstDomain}\n";
            $searchResult = $cloudflare->searchZones($firstDomain, 1, 10);
            echo "Search result count: " . count($searchResult['result'] ?? []) . "\n";
        }
    }
    
    // Test the handleSearch function
    echo "\n=== Testing HandleSearch Function ===\n";
    
    // Simulate a GET request
    $_GET['api'] = '1';
    $_GET['q'] = '';
    $_GET['page'] = '1';
    $_GET['per_page'] = '10';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    
    // Load the search handler
    require_once 'SearchHandler.php';
    
    ob_start();
    handleSearch($cloudflare);
    $output = ob_get_clean();
    
    echo "HandleSearch output length: " . strlen($output) . "\n";
    
    if (!empty($output)) {
        $decoded = json_decode($output, true);
        if ($decoded) {
            echo "✓ Valid JSON response\n";
            echo "Success: " . ($decoded['success'] ? 'true' : 'false') . "\n";
            if (isset($decoded['error'])) {
                echo "Error: " . $decoded['error'] . "\n";
            }
            if (isset($decoded['data']['result'])) {
                echo "Results count: " . count($decoded['data']['result']) . "\n";
            }
        } else {
            echo "✗ Invalid JSON response\n";
            echo "Raw output: " . substr($output, 0, 200) . "...\n";
        }
    } else {
        echo "✗ No output from handleSearch\n";
    }
    
} catch (Exception $e) {
    echo "\n✗ Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
}