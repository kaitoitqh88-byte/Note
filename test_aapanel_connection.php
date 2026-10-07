<?php
/**
 * Test aaPanel WordPress Sites Manager
 * Kiểm tra kết nối và API call
 */

require_once 'AaPanelAPIHelper.php';

// Test với thông tin VPS từ file VPS_TK.txt
$vpsFile = 'Data_Config/VPS_TK.txt';

if (!file_exists($vpsFile)) {
    die("❌ Không tìm thấy file VPS_TK.txt");
}

$lines = file($vpsFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
if (empty($lines)) {
    die("❌ File VPS_TK.txt trống");
}

echo "🔧 aaPanel WordPress Sites Manager - Test Connection\n";
echo "=" . str_repeat("=", 50) . "\n\n";

foreach ($lines as $lineNum => $line) {
    $parts = explode("\t", trim($line));
    if (count($parts) < 4) {
        echo "⚠️  Dòng " . ($lineNum + 1) . ": Không đủ thông tin\n";
        continue;
    }
    
    $vpsInfo = [
        'ip' => $parts[0],
        'panel_url' => $parts[1], 
        'panel_user' => $parts[2],
        'panel_pass' => $parts[3]
    ];
    
    echo "🌐 VPS: " . $vpsInfo['ip'] . "\n";
    echo "   Panel: " . $vpsInfo['panel_url'] . "\n";
    echo "   User: " . $vpsInfo['panel_user'] . "\n";
    
    try {
        $api = new AaPanelAPI($vpsInfo['panel_url'], $vpsInfo['panel_user'], $vpsInfo['panel_pass']);
        
        // Test connection
        echo "   🔌 Testing connection... ";
        $connectionTest = $api->testConnection();
        
        if ($connectionTest['success']) {
            echo "✅ OK (" . $connectionTest['response_time'] . "ms)\n";
            
            // Test login
            echo "   🔑 Testing login... ";
            if ($api->login()) {
                echo "✅ Login successful\n";
                
                // Test getting WordPress sites 
                echo "   📦 Getting WordPress sites... ";
                $sites = $api->getWordPressSites();
                
                if (isset($sites['error'])) {
                    echo "❌ Error: " . $sites['error'] . "\n";
                } else {
                    echo "✅ Found " . count($sites) . " WordPress sites\n";
                    
                    if (!empty($sites)) {
                        foreach ($sites as $i => $site) {
                            echo "      " . ($i + 1) . ". " . ($site['domain'] ?? 'N/A') . " - " . ($site['wp_version'] ?? 'Unknown') . "\n";
                        }
                    }
                }
            } else {
                echo "❌ Login failed\n";
            }
            
        } else {
            echo "❌ Connection failed (HTTP " . $connectionTest['http_code'] . ")\n";
        }
        
    } catch (Exception $e) {
        echo "❌ Exception: " . $e->getMessage() . "\n";
    }
    
    echo "\n";
}

echo "🎯 Test complete!\n";
echo "\n📌 Để sử dụng aaPanel WordPress Sites Manager:\n";
echo "   2. Hoặc từ menu: Tools → aaPanel WP Sites\n";
echo "   3. Hoặc từ homepage: nút 'aaPanel WP Sites'\n";
?>