<?php
/**
 * Debug test for cache_ttl_bulk_update.php
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/debug.log');

echo "Starting debug test...\n";

try {
    // Test 1: Config file
    echo "1. Testing config.php...\n";
    require_once 'config.php';
    echo "✓ Config loaded successfully\n";
    echo "CLOUDFLARE_EMAIL: " . (defined('CLOUDFLARE_EMAIL') ? CLOUDFLARE_EMAIL : 'NOT DEFINED') . "\n";
    echo "CLOUDFLARE_API_TOKEN length: " . (defined('CLOUDFLARE_API_TOKEN') ? strlen(CLOUDFLARE_API_TOKEN) : 'NOT DEFINED') . "\n";
    
    // Test 2: CloudflareAPI class
    echo "\n2. Testing CloudflareAPI.php...\n";
    require_once 'CloudflareAPI.php';
    echo "✓ CloudflareAPI class loaded\n";
    
    // Test 3: Initialize CloudflareAPI
    echo "\n3. Testing CloudflareAPI initialization...\n";
    $api = new CloudflareAPI();
    echo "✓ CloudflareAPI initialized successfully\n";
    
    // Test 4: Test simple API call
    echo "\n4. Testing API call...\n";
    $zones = $api->listZones(1, 1);
    echo "✓ API call successful\n";
    echo "Response success: " . ($zones['success'] ? 'true' : 'false') . "\n";
    
    if (!$zones['success']) {
        echo "API Error: " . json_encode($zones['errors'] ?? []) . "\n";
    }
    
    // Test 5: Test domain parsing function
    echo "\n5. Testing domain parsing...\n";
    if (function_exists('parseAndValidateDomains')) {
        $domains = parseAndValidateDomains("xiaofeng.cn.com\nexample.com");
        echo "✓ Domain parsing successful\n";
        echo "Parsed domains: " . json_encode($domains) . "\n";
    } else {
        echo "✗ parseAndValidateDomains function not found\n";
    }
    
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "✗ FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}

echo "\nDebug test completed.\n";
?>