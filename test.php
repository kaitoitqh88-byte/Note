<?php
/**
 * Simple Test Script - Kiểm tra kết nối Cloudflare API
 * Chạy file này để test cơ bản mà không cần web server
 */

// Set error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "=== CLOUDFLARE API CONNECTION TEST ===\n\n";

// Check PHP version
echo "PHP Version: " . PHP_VERSION . "\n";

// Check required extensions
$required_extensions = ['curl', 'json'];
$missing_extensions = [];

foreach ($required_extensions as $ext) {
    if (extension_loaded($ext)) {
        echo "[OK] Extension {$ext} is loaded\n";
    } else {
        echo "[ERROR] Extension {$ext} is missing\n";
        $missing_extensions[] = $ext;
    }
}

if (!empty($missing_extensions)) {
    echo "\nPlease install missing extensions:\n";
    foreach ($missing_extensions as $ext) {
        echo "- {$ext}\n";
    }
    exit(1);
}

echo "\n";

// Check config files
if (file_exists('config.php')) {
    echo "[OK] config.php found\n";
} else {
    echo "[ERROR] config.php not found\n";
    exit(1);
}

if (file_exists('CloudflareAPI.php')) {
    echo "[OK] CloudflareAPI.php found\n";
} else {
    echo "[ERROR] CloudflareAPI.php not found\n";
    exit(1);
}

// Check token
if (file_exists('token.txt')) {
    $token = trim(file_get_contents('token.txt'));
    if (strlen($token) > 10) {
        echo "[OK] Token file exists and has content\n";
        echo "Token preview: " . substr($token, 0, 10) . "...\n";
    } else {
        echo "[WARNING] Token file is empty or too short\n";
        echo "Please add your Cloudflare API token to token.txt\n";
    }
} else {
    echo "[ERROR] token.txt not found\n";
    echo "Please create token.txt and add your Cloudflare API token\n";
    exit(1);
}

echo "\n";

// Test API connection
require_once 'config.php';
require_once 'CloudflareAPI.php';

try {
    echo "Testing API connection...\n";
    
    $cloudflare = new CloudflareAPI();
    $result = $cloudflare->listZones(1, 5); // Get first 5 zones
    
    if ($result && isset($result['success']) && $result['success']) {
        echo "[OK] API connection successful!\n";
        echo "Account has " . count($result['result']) . " zones\n";
        
        if (!empty($result['result'])) {
            echo "\nZones found:\n";
            foreach ($result['result'] as $i => $zone) {
                echo sprintf(
                    "%d. %s (ID: %s) - Status: %s\n",
                    $i + 1,
                    $zone['name'],
                    $zone['id'],
                    $zone['status']
                );
            }
        } else {
            echo "No zones found in account.\n";
        }
        
    } else {
        echo "[ERROR] API connection failed\n";
        if (isset($result['errors'])) {
            echo "Errors: " . json_encode($result['errors'], JSON_PRETTY_PRINT) . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "[ERROR] Exception occurred: " . $e->getMessage() . "\n";
    
    // Provide troubleshooting tips
    echo "\nTroubleshooting tips:\n";
    echo "1. Verify your API token in token.txt\n";
    echo "2. Check your email address in config.php\n";
    echo "3. Ensure token has proper permissions (Zone:Read)\n";
    echo "4. Check internet connection\n";
    echo "5. Verify curl extension is working\n";
}

echo "\n=== TEST COMPLETED ===\n";

// Test basic cURL
echo "\nTesting basic cURL functionality...\n";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://httpbin.org/json');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_TIMEOUT, 5);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($response && $httpCode == 200) {
    echo "[OK] cURL is working properly\n";
} else {
    echo "[ERROR] cURL test failed (HTTP: {$httpCode})\n";
}

echo "\nAll tests completed!\n";