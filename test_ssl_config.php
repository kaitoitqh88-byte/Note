<?php
/**
 * Test SSL Configuration Functions
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/CloudflareAPI.php';

echo "<h2>Test SSL Configuration Functions</h2>";

try {
    $cloudflareAPI = new CloudflareAPI();
    
    // Test 1: Get all zones
    echo "<h3>1. Getting zones...</h3>";
    $zones = $cloudflareAPI->getZones();
    
    if ($zones && isset($zones['result']) && count($zones['result']) > 0) {
        $firstZone = $zones['result'][0];
        $zoneId = $firstZone['id'];
        $zoneName = $firstZone['name'];
        
        echo "<p>✅ Found zone: <strong>$zoneName</strong> (ID: $zoneId)</p>";
        
        // Test 2: Get detailed SSL settings
        echo "<h3>2. Getting current SSL settings...</h3>";
        $sslSettings = $cloudflareAPI->getSSLSettingsDetailed($zoneId);
        
        if ($sslSettings['success']) {
            echo "<p>✅ Retrieved SSL settings:</p>";
            echo "<pre>" . json_encode($sslSettings['ssl_settings'], JSON_PRETTY_PRINT) . "</pre>";
            
            // Test 3: Test individual SSL configuration methods
            echo "<h3>3. Testing SSL configuration methods...</h3>";
            
            // Test get current SSL mode
            $currentSettings = $sslSettings['ssl_settings'];
            $currentSSLMode = $currentSettings['ssl_mode'] ?? 'flexible';
            $currentAlwaysHTTPS = $currentSettings['always_use_https'] ?? false;
            
            echo "<p>Current SSL Mode: <strong>$currentSSLMode</strong></p>";
            echo "<p>Current Always HTTPS: <strong>" . ($currentAlwaysHTTPS ? 'Enabled' : 'Disabled') . "</strong></p>";
            
            // Test 4: Bulk SSL configuration (simulate only)
            echo "<h3>4. Testing bulk SSL configuration...</h3>";
            
            $testSettings = [
                'ssl_mode' => $currentSSLMode, // Keep current
                'always_use_https' => $currentAlwaysHTTPS, // Keep current
                'min_tls_version' => '1.2',
                'tls_1_3' => true,
                'automatic_https_rewrites' => true,
                'opportunistic_encryption' => true
            ];
            
            echo "<p>Would apply settings:</p>";
            echo "<pre>" . json_encode($testSettings, JSON_PRETTY_PRINT) . "</pre>";
            
            // Uncomment the line below to actually apply settings (CAUTION!)
            // $result = $cloudflareAPI->configureSSLBulk($zoneId, $testSettings);
            
            echo "<p>⚠️ Actual configuration commented out for safety. Uncomment to test.</p>";
            
        } else {
            echo "<p>❌ Failed to get SSL settings: " . $sslSettings['error'] . "</p>";
        }
        
    } else {
        echo "<p>❌ No zones found or API error</p>";
    }
    
    // Test 5: Check if all required methods exist
    echo "<h3>5. Checking SSL methods availability...</h3>";
    
    $requiredMethods = [
        'setSSLMode',
        'setAlwaysUseHTTPS',
        'setMinTLSVersion',
        'setTLS13',
        'setAutomaticHTTPSRewrites',
        'setOpportunisticEncryption',
        'getSSLSettingsDetailed',
        'configureSSLBulk'
    ];
    
    foreach ($requiredMethods as $method) {
        if (method_exists($cloudflareAPI, $method)) {
            echo "<p>✅ Method <strong>$method</strong> exists</p>";
        } else {
            echo "<p>❌ Method <strong>$method</strong> missing</p>";
        }
    }
    
} catch (Exception $e) {
    echo "<p>❌ Error: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>Test completed!</strong></p>";
echo "<p><a href='domain_status_checker.php'>← Back to Domain Status Checker</a></p>";
?>