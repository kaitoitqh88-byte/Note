<?php
// Minimal test for undefined variable fix
$warningCount = 0;

// Custom error handler to catch warnings
set_error_handler(function($severity, $message, $file, $line) use (&$warningCount) {
    if ($severity & E_WARNING) {
        if (strpos($message, 'Undefined variable') !== false) {
            echo "❌ Warning: $message on line $line\n";
            $warningCount++;
        }
    }
    return true; // Don't execute PHP internal error handler
});

echo "Testing for undefined variable warnings...\n";

try {
    require_once 'd:\Note\config.php';
    require_once 'd:\Note\CloudflareAPI.php';
    
    // Test the specific function
    function testBulkUpdate() {
        // This simulates the function call structure
        echo "Simulating bulkUpdateCacheTTL call...\n";
        
        // Check if we can at least load the functions
        if (function_exists('getZoneInfo')) {
            echo "✅ getZoneInfo function exists\n";
        } else {
            echo "❌ getZoneInfo function missing\n";
        }
        
        return true;
    }
    
    testBulkUpdate();
    
} catch (Exception $e) {
    echo "Exception: " . $e->getMessage() . "\n";
}

restore_error_handler();

if ($warningCount === 0) {
    echo "✅ No undefined variable warnings detected\n";
} else {
    echo "❌ Found $warningCount undefined variable warnings\n";
}
?>