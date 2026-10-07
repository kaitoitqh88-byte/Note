<?php
// Simple test to check CloudflareAPI instantiation
try {
    echo "Loading config...\n";
    require_once __DIR__ . '/config.php';
    echo "Config loaded!\n";
    
    echo "Loading CloudflareAPI...\n";
    require_once __DIR__ . '/CloudflareAPI.php';
    echo "CloudflareAPI class loaded!\n";
    
    echo "Creating CloudflareAPI instance...\n";
    $api = new CloudflareAPI();
    echo "CloudflareAPI instance created successfully!\n";
    
    echo "Constants check:\n";
    echo "CLOUDFLARE_EMAIL: " . CLOUDFLARE_EMAIL . "\n";
    echo "CLOUDFLARE_API_URL: " . CLOUDFLARE_API_URL . "\n";
    echo "Token length: " . strlen(CLOUDFLARE_API_TOKEN) . " chars\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
} catch (Error $e) {
    echo "FATAL ERROR: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}
?>