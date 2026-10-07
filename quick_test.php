<?php
// Quick test - just check if functions can be loaded without errors
echo "Testing file loading...\n";

try {
    require_once 'd:\Note\config.php';
    echo "Config loaded OK\n";
    
    require_once 'd:\Note\CloudflareAPI.php';
    echo "CloudflareAPI loaded OK\n";
    
    require_once 'd:\Note\cache_ttl_bulk_update.php';
    echo "Cache TTL functions loaded OK\n";
    
    echo "✅ All files loaded without parse errors\n";
    
} catch (ParseError $e) {
    echo "❌ Parse error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "❌ Runtime error: " . $e->getMessage() . "\n";
}
?>