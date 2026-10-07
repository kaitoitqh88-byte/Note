<?php
/**
 * Quick test for cache statistics debugging
 */

echo "Testing Cache Statistics API...<br><br>";

// Test 1: Direct CloudflareCache class
echo "<h3>Test 1: CloudflareCache class</h3>";
try {
    require_once 'CloudflareCache.php';
    $cache = new CloudflareCache();
    
    echo "✅ CloudflareCache class loaded successfully<br>";
    
    $cacheDir = $cache->getCacheDir();
    echo "📁 Cache directory: " . $cacheDir . "<br>";
    
    if (is_dir($cacheDir)) {
        echo "✅ Cache directory exists<br>";
        
        $files = glob($cacheDir . '/*.cache');
        if ($files !== false) {
            echo "📂 Found " . count($files) . " cache files<br>";
        } else {
            echo "⚠️ No cache files found<br>";
        }
    } else {
        echo "❌ Cache directory does not exist<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "<br>";
}

echo "<br>";

// Test 2: Test cache statistics API endpoint
echo "<h3>Test 2: Cache Statistics API</h3>";
try {
    // Simulate the getCacheStatistics function
    require_once 'DashboardHandler.php';
    
    echo "✅ DashboardHandler loaded<br>";
    echo "🔄 Testing getCacheStatistics...<br>";
    
    // Capture output
    ob_start();
    getCacheStatistics();
    $output = ob_get_clean();
    
    echo "<h4>API Response:</h4>";
    echo "<pre>" . htmlspecialchars($output) . "</pre>";
    
    // Check if output is valid JSON
    $decoded = json_decode($output, true);
    if ($decoded !== null) {
        echo "✅ Valid JSON response<br>";
        if (isset($decoded['success']) && $decoded['success']) {
            echo "✅ API call successful<br>";
        } else {
            echo "❌ API returned error: " . ($decoded['message'] ?? 'Unknown') . "<br>";
        }
    } else {
        echo "❌ Invalid JSON response<br>";
        echo "JSON Error: " . json_last_error_msg() . "<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error testing API: " . $e->getMessage() . "<br>";
}

echo "<br>";

// Test 3: Manual cache directory check  
echo "<h3>Test 3: Manual Cache Directory Check</h3>";
$cacheDir = __DIR__ . '/cache';
echo "📁 Expected cache dir: " . $cacheDir . "<br>";

if (is_dir($cacheDir)) {
    echo "✅ Directory exists<br>";
    
    // List all files
    $allFiles = scandir($cacheDir);
    $cacheFiles = array_filter($allFiles, function($file) {
        return strpos($file, '.cache') !== false;
    });
    
    echo "📂 Total files: " . (count($allFiles) - 2) . " (excluding . and ..)<br>";
    echo "📂 Cache files: " . count($cacheFiles) . "<br>";
    
    if (count($cacheFiles) > 0) {
        echo "<h4>Cache Files:</h4>";
        foreach ($cacheFiles as $file) {
            $filePath = $cacheDir . '/' . $file;
            $size = filesize($filePath);
            $modified = date('H:i:s d/m/Y', filemtime($filePath));
            echo "- {$file} ({$size} bytes, modified: {$modified})<br>";
        }
    }
    
    // Check permissions
    if (is_writable($cacheDir)) {
        echo "✅ Directory is writable<br>";
    } else {
        echo "❌ Directory is not writable<br>";
    }
    
} else {
    echo "❌ Cache directory does not exist<br>";
    echo "🔧 Trying to create cache directory...<br>";
    
    if (mkdir($cacheDir, 0755, true)) {
        echo "✅ Cache directory created successfully<br>";
    } else {
        echo "❌ Failed to create cache directory<br>";
    }
}

echo "<br><hr><br>";
echo "Debug completed. Check the results above to identify the issue.";
?>