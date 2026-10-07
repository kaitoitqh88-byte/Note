<?php
/**
 * Simple Search Test
 * Test basic search functionality without complex workflow
 */

// Enable all error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

try {
    echo "=== Testing Direct Search Call ===\n";
    
    // Include the basic config
    require_once 'config.php';
    require_once 'CloudflareAPI.php';
    
    // Test CloudflareAPI init
    echo "Initializing CloudflareAPI...\n";
    $cloudflare = new CloudflareAPI();
    echo "CloudflareAPI initialized successfully.\n";
    
    // Test if we can get zones first (basic connectivity)
    echo "\nTesting basic API connectivity...\n";
    $testZones = $cloudflare->listZones(1, 5);
    echo "API connectivity OK - found " . count($testZones['result'] ?? []) . " zones\n";
    
    // Now test search directly 
    echo "\nTesting searchZones method...\n";
    $searchResult = $cloudflare->searchZones('', 1, 10); // Empty search to get all zones
    echo "SearchZones result count: " . count($searchResult['result'] ?? []) . "\n";
    
    // Test with specific search
    if (!empty($testZones['result'])) {
        $firstDomainName = $testZones['result'][0]['name'] ?? '';
        if ($firstDomainName) {
            echo "\nTesting search for specific domain: {$firstDomainName}\n";
            $specificSearch = $cloudflare->searchZones($firstDomainName, 1, 10);
            echo "Specific search result count: " . count($specificSearch['result'] ?? []) . "\n";
        }
    }
    
    // Test the SearchHandler include
    echo "\nTesting SearchHandler include...\n";
    try {
        require_once 'SearchHandler.php';
        echo "SearchHandler.php included successfully\n";
        
        // Test if handleSearch function exists
        if (function_exists('handleSearch')) {
            echo "handleSearch function exists\n";
            
            // Test calling handleSearch directly with minimal setup
            echo "\nTesting handleSearch function...\n";
            
            // Simulate API request
            $_GET['api'] = 'true';
            $_GET['q'] = '';
            $_GET['page'] = '1';
            $_GET['per_page'] = '10';
            $_SERVER['REQUEST_METHOD'] = 'GET';
            
            ob_start();
            try {
                handleSearch($cloudflare);
                $output = ob_get_clean();
                
                echo "HandleSearch output length: " . strlen($output) . " chars\n";
                
                if (!empty($output)) {
                    $decoded = json_decode($output, true);
                    if ($decoded !== null) {
                        echo "Valid JSON response received\n";
                        echo "Response success: " . ($decoded['success'] ? 'true' : 'false') . "\n";
                        if (isset($decoded['error'])) {
                            echo "Error in response: " . $decoded['error'] . "\n";
                        }
                        if (isset($decoded['data']['result'])) {
                            echo "Results in response: " . count($decoded['data']['result']) . "\n";
                        }
                    } else {
                        echo "Invalid JSON response\n";
                        echo "Raw output (first 500 chars):\n";
                        echo substr($output, 0, 500) . "\n";
                    }
                } else {
                    echo "Empty output from handleSearch\n";
                }
            } catch (Exception $e) {
                ob_end_clean();
                echo "Exception in handleSearch: " . $e->getMessage() . "\n";
                echo "File: " . $e->getFile() . "\n";
                echo "Line: " . $e->getLine() . "\n";
            }
        } else {
            echo "handleSearch function NOT found\n";
        }
    } catch (Exception $e) {
        echo "Error including SearchHandler: " . $e->getMessage() . "\n";
    }
    
} catch (Exception $e) {
    echo "Fatal error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n"; 
    echo "Line: " . $e->getLine() . "\n";
    echo "Trace:\n" . $e->getTraceAsString() . "\n";
}