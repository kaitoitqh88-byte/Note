<?php
/**
 * Simple syntax test
 */

// Just check if the file can be parsed
echo "Testing file syntax...\n";

try {
    require_once 'd:\Note\cache_ttl_bulk_update.php';
    echo "✅ File syntax is OK - no parse errors\n";
} catch (ParseError $e) {
    echo "❌ Parse error: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "✅ File parsed OK (other error: " . $e->getMessage() . ")\n";
}
?>