<?php
/**
 * Simple connectivity test to Cloudflare API
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "Testing connectivity to Cloudflare API...\n\n";

// Test 1: Basic connectivity
echo "1. Testing basic connectivity to api.cloudflare.com...\n";
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.cloudflare.com/client/v4/',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => ['User-Agent: TestScript/1.0']
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "✗ Connection failed: $error\n";
} else {
    echo "✓ Connection successful. HTTP Code: $httpCode\n";
    $json = json_decode($response, true);
    if (isset($json['success'])) {
        echo "✓ API responding. Success: " . ($json['success'] ? 'true' : 'false') . "\n";
    }
}

// Test 2: Test with auth headers  
echo "\n2. Testing with authentication...\n";
require_once 'config.php';

$headers = [
    'Authorization: Bearer ' . CLOUDFLARE_API_TOKEN,
    'Content-Type: application/json',
    'X-Auth-Email: ' . CLOUDFLARE_EMAIL
];

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.cloudflare.com/client/v4/user/tokens/verify',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => $headers
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "✗ Auth test failed: $error\n";
} else {
    echo "✓ Auth test successful. HTTP Code: $httpCode\n";
    $json = json_decode($response, true);
    if (isset($json['success'])) {
        echo "Auth result: " . ($json['success'] ? 'Valid' : 'Invalid') . "\n";
        if (!$json['success'] && isset($json['errors'])) {
            echo "Errors: " . json_encode($json['errors']) . "\n";
        }
    }
}

echo "\nTest completed.\n";
?>