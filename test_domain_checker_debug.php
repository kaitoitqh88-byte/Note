<?php
echo "Testing domain status checker...\n";
require_once __DIR__ . '/config.php';
echo "Config loaded successfully\n";
require_once __DIR__ . '/CloudflareAPI.php';
echo "CloudflareAPI loaded successfully\n";

// Test basic functionality
try {
    $api = new CloudflareAPI();
    echo "CloudflareAPI instance created successfully\n";
} catch (Exception $e) {
    echo "Error creating CloudflareAPI: " . $e->getMessage() . "\n";
}

echo "Test completed\n";
?>