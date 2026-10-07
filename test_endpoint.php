<?php
/**
 * Test real cache_ttl_bulk_update.php endpoint
 */

// Simple curl to test actual endpoint
$url = 'http://localhost/cache_ttl_bulk_update.php';
$data = [
    'action' => 'check_domains',
    'domain_list' => "api.11bet.de.com\ntest.123win.name\nmb66ee.com\n11bet.de.com"
];

echo "Testing real cache_ttl_bulk_update.php endpoint:\n";
echo str_repeat('=', 50) . "\n";
echo "URL: $url\n";
echo "Domains to test:\n";
echo "  - api.11bet.de.com (should find parent 11bet.de.com)\n";
echo "  - test.123win.name (should find parent 123win.name)\n";
echo "  - mb66ee.com (should find exact)\n";
echo "  - 11bet.de.com (should find exact)\n\n";

// Create POST data
$postData = http_build_query($data);

// Create context
$context = stream_context_create([
    'http' => [
        'method' => 'POST',
        'header' => 'Content-Type: application/x-www-form-urlencoded',
        'content' => $postData
    ]
]);

try {
    $response = file_get_contents($url, false, $context);
    
    if ($response === false) {
        echo "❌ Failed to connect to endpoint\n";
        echo "Make sure web server is running and file is accessible\n";
    } else {
        echo "✅ Response received:\n";
        echo str_repeat('-', 30) . "\n";
        
        // Try to decode JSON
        $decoded = json_decode($response, true);
        if ($decoded === null) {
            echo "Raw response (not JSON):\n";
            echo substr($response, 0, 500) . "...\n";
        } else {
            echo "JSON Response:\n";
            print_r($decoded);
        }
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>