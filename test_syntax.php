<?php
// Simple PHP syntax test for search.php
echo "Testing PHP syntax...\n";

// Include the search.php file to check for any syntax errors
ob_start();
try {
    include('search.php');
    echo "✅ PHP syntax check passed!\n";
} catch (ParseError $e) {
    echo "❌ PHP Syntax Error: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} catch (Error $e) {
    echo "❌ PHP Error: " . $e->getMessage() . "\n";
    echo "Line: " . $e->getLine() . "\n";
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
}
ob_end_clean();

echo "Syntax test completed.\n";
?>