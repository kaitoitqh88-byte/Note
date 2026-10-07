<?php
/**
 * Example sử dụng SSL/HTTPS API
 * Test Always Use HTTPS functionality
 */

require_once 'config.php';
require_once 'CloudflareAPI.php';

echo "=== CLOUDFLARE SSL/HTTPS API EXAMPLES ===\n\n";

try {
    $cloudflare = new CloudflareAPI();
    
    // 1. Lấy danh sách zones
    echo "1. GETTING ZONES FOR SSL TESTING:\n";
    $zones = $cloudflare->listZones(1, 5);
    
    if ($zones && isset($zones['result']) && !empty($zones['result'])) {
        $zone = $zones['result'][0];
        $zoneId = $zone['id'];
        $zoneName = $zone['name'];
        
        echo "Testing with zone: {$zoneName} (ID: {$zoneId})\n\n";
        
        // 2. Lấy SSL settings hiện tại
        echo "2. CURRENT SSL SETTINGS:\n";
        try {
            $sslSettings = $cloudflare->getSSLSettings($zoneId);
            
            if ($sslSettings && isset($sslSettings['result'])) {
                $settings = $sslSettings['result'];
                foreach ($settings as $setting) {
                    if (in_array($setting['id'], ['ssl', 'always_use_https', 'automatic_https_rewrites'])) {
                        echo "  - {$setting['id']}: {$setting['value']}\n";
                    }
                }
            } else {
                echo "  Could not retrieve SSL settings.\n";
            }
        } catch (Exception $e) {
            echo "  Error getting SSL settings: " . $e->getMessage() . "\n";
        }
        
        echo "\n";
        
        // 3. Test API endpoints trực tiếp
        echo "3. TEST SSL API ENDPOINTS:\n";
        
        // Test Always Use HTTPS
        echo "Testing Always Use HTTPS API...\n";
        $apiUrl = "http://localhost:8000/?action=ssl";
        echo "URL: POST {$apiUrl}\n";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $apiUrl,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => http_build_query([
                'zone_id' => $zoneId,
                'action_type' => 'always_use_https',
                'enabled' => 'true'
            ]),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($response && $httpCode == 200) {
            $data = json_decode($response, true);
            if ($data && $data['success']) {
                echo "✅ Always Use HTTPS API working!\n";
                echo "Response: " . json_encode($data, JSON_PRETTY_PRINT) . "\n";
            } else {
                echo "❌ API returned error: " . ($data['error'] ?? 'Unknown error') . "\n";
            }
        } else {
            echo "❌ API call failed (HTTP: {$httpCode})\n";
            echo "Response: {$response}\n";
        }
        
    } else {
        echo "No zones found for testing.\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

echo "\n=== SSL/HTTPS API USAGE ===\n";
echo "Enable Always Use HTTPS:\n";
echo "POST /?action=ssl\n";
echo "  zone_id=YOUR_ZONE_ID\n";
echo "  action_type=always_use_https\n";
echo "  enabled=true\n\n";

echo "Set SSL Mode:\n";  
echo "POST /?action=ssl\n";
echo "  zone_id=YOUR_ZONE_ID\n";
echo "  action_type=ssl_mode\n";
echo "  mode=flexible|full|strict\n\n";

echo "Get SSL Settings:\n";
echo "GET /?action=ssl&zone_id=YOUR_ZONE_ID\n\n";

echo "=== JAVASCRIPT EXAMPLE ===\n";
echo "// Enable Always Use HTTPS\n";
echo "const formData = new FormData();\n";
echo "formData.append('zone_id', 'your_zone_id');\n";
echo "formData.append('action_type', 'always_use_https');\n";
echo "formData.append('enabled', 'true');\n\n";
echo "fetch('/?action=ssl', {\n";
echo "  method: 'POST',\n";
echo "  body: formData\n";
echo "})\n";
echo ".then(response => response.json())\n";
echo ".then(data => {\n";
echo "  if (data.success) {\n";
echo "    console.log('Always Use HTTPS enabled!');\n";
echo "  }\n";
echo "});\n";

echo "\n=== TEST COMPLETED ===\n";