<?php
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <meta charset="UTF-8">
    <title>🚀 Cloudflare DNS API Demo Results</title>
    <style>
        body { font-family: 'Roboto', sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .header { text-align: center; color: #f48120; margin-bottom: 30px; }
        .success { background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .error { background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .info { background: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 10px 0; }
        .record-table { width: 100%; border-collapse: collapse; margin: 15px 0; }
        .record-table th, .record-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .record-table th { background-color: #f2f2f2; font-weight: bold; }
        .proxied { background: #ff6b35; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
        .dns-only { background: #6c757d; color: white; padding: 2px 8px; border-radius: 12px; font-size: 12px; }
        .stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; }
        .stat-box { background: #f8f9fa; border: 1px solid #dee2e6; padding: 15px; text-align: center; border-radius: 8px; }
        .stat-number { font-size: 2em; font-weight: bold; color: #007bff; }
        .code { background: #f8f9fa; border: 1px solid #e9ecef; padding: 10px; border-radius: 4px; font-family: monospace; margin: 10px 0; }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <h1>🚀 Cloudflare DNS API Demo</h1>
        <p>Live test với token: <code><?= substr(trim(file_get_contents('token.txt')), 0, 10) ?>...</code></p>
    </div>

<?php
require_once 'config.php';
require_once 'CacheHandler.php';
require_once 'CloudflareAPI.php';

echo "<div class='info'>📡 <strong>Đang test Cloudflare API connection...</strong></div>";

try {
    $cloudflare = new CloudflareAPI();
    
    // Test API connection by listing zones
    $zones = $cloudflare->listZones(1, 10);
    
    if ($zones && isset($zones['result'])) {
        $zoneCount = count($zones['result']);
        echo "<div class='success'>✅ <strong>API Connection thành công!</strong> Tìm thấy {$zoneCount} domains trong account.</div>";
        
        if ($zoneCount > 0) {
            echo "<h3>📋 Domains có sẵn trong Cloudflare account:</h3>";
            echo "<table class='record-table'>";
            echo "<tr><th>Domain</th><th>Status</th><th>Plan</th><th>Action</th></tr>";
            
            foreach ($zones['result'] as $zone) {
                $testLink = "?demo_domain=" . urlencode($zone['name']);
                echo "<tr>";
                echo "<td><strong>{$zone['name']}</strong></td>";
                echo "<td>" . ucfirst($zone['status']) . "</td>";
                echo "<td>" . ($zone['plan']['name'] ?? 'Free') . "</td>";
                echo "<td><a href='{$testLink}' style='background: #007bff; color: white; padding: 5px 10px; text-decoration: none; border-radius: 4px;'>🧪 Test DNS</a></td>";
                echo "</tr>";
            }
            echo "</table>";
            
            // If demo_domain is specified, test it
            if (isset($_GET['demo_domain'])) {
                $testDomain = $_GET['demo_domain'];
                echo "<hr><h2>🔍 Testing DNS cho domain: <code>{$testDomain}</code></h2>";
                
                // Simulate POST data for the function
                $_POST['domain'] = $testDomain;
                $_POST['include_proxied'] = 'true';
                $_POST['show_only_active'] = 'true';
                $_POST['compare_dns'] = 'true';
                
                // Capture the JSON output
                ob_start();
                getCloudflaresDNSRecords();
                $jsonOutput = ob_get_clean();
                
                // Clean POST data
                unset($_POST['domain'], $_POST['include_proxied'], $_POST['show_only_active'], $_POST['compare_dns']);
                
                $result = json_decode($jsonOutput, true);
                
                if ($result && $result['success']) {
                    $data = $result['data'];
                    $stats = $data['statistics'];
                    
                    echo "<div class='success'>✅ <strong>DNS Lookup thành công!</strong></div>";
                    
                    // Statistics
                    echo "<div class='stats'>";
                    echo "<div class='stat-box'><div class='stat-number'>{$stats['total_records']}</div><div>Total Records</div></div>";
                    echo "<div class='stat-box'><div class='stat-number'>{$stats['proxied_records']}</div><div>🟠 Proxied</div></div>";
                    echo "<div class='stat-box'><div class='stat-number'>{$stats['dns_only_records']}</div><div>⚫ DNS Only</div></div>";
                    echo "<div class='stat-box'><div class='stat-number'>" . count($stats['record_types'] ?? []) . "</div><div>Record Types</div></div>";
                    echo "</div>";
                    
                    echo "<div class='info'>";
                    echo "<strong>Zone ID:</strong> <code>{$data['zone_id']}</code><br>";
                    echo "<strong>Domain:</strong> {$data['domain']}<br>";
                    echo "<strong>Timestamp:</strong> " . date('Y-m-d H:i:s');
                    echo "</div>";
                    
                    // Display records by type
                    foreach ($data['cloudflare_records'] as $type => $records) {
                        echo "<h4>📡 {$type} Records (" . count($records) . ")</h4>";
                        echo "<table class='record-table'>";
                        echo "<tr><th>Name</th><th>Content</th><th>Proxy Status</th><th>TTL</th></tr>";
                        
                        foreach ($records as $record) {
                            $proxyBadge = $record['proxied'] ? 
                                "<span class='proxied'>🟠 Proxied</span>" : 
                                "<span class='dns-only'>⚫ DNS Only</span>";
                            
                            echo "<tr>";
                            echo "<td>{$record['name']}</td>";
                            echo "<td><code>{$record['content']}</code></td>";
                            echo "<td>{$proxyBadge}</td>";
                            echo "<td>" . ($record['ttl'] == 1 ? 'Auto' : $record['ttl']) . "</td>";
                            echo "</tr>";
                        }
                        echo "</table>";
                    }
                    
                    // Comparison results
                    if (isset($data['comparison'])) {
                        $comp = $data['comparison']['summary'];
                        echo "<h4>⚖️ DNS Comparison với Public DNS</h4>";
                        echo "<div class='stats'>";
                        echo "<div class='stat-box'><div class='stat-number' style='color: #28a745;'>{$comp['total_matches']}</div><div>Matches</div></div>";
                        echo "<div class='stat-box'><div class='stat-number' style='color: #ffc107;'>{$comp['total_mismatches']}</div><div>Mismatches</div></div>";
                        echo "<div class='stat-box'><div class='stat-number' style='color: #17a2b8;'>{$comp['cloudflare_only_count']}</div><div>CF Only</div></div>";
                        echo "<div class='stat-box'><div class='stat-number' style='color: #6c757d;'>{$comp['public_only_count']}</div><div>Public Only</div></div>";
                        echo "</div>";
                        
                        if ($comp['total_mismatches'] > 0) {
                            echo "<div class='info'>⚠️ <strong>Note:</strong> Mismatches thường xảy ra với proxied records vì Cloudflare sẽ return CF IPs thay vì origin IPs.</div>";
                        }
                    }
                    
                    // Show raw JSON
                    echo "<h4>📝 Raw API Response:</h4>";
                    echo "<div class='code'><pre>" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre></div>";
                    
                } else {
                    echo "<div class='error'>❌ <strong>DNS Lookup failed:</strong> " . ($result['error'] ?? 'Unknown error') . "</div>";
                }
            }
            
            if (!isset($_GET['demo_domain'])) {
                echo "<div class='info'>💡 <strong>Tip:</strong> Click vào nút '🧪 Test DNS' để test DNS lookup cho domain cụ thể.</div>";
            }
        }
    } else {
        echo "<div class='error'>❌ <strong>API Connection failed!</strong> Kiểm tra API token và email trong config.php</div>";
    }
    
} catch (Exception $e) {
    echo "<div class='error'>❌ <strong>Exception:</strong> " . htmlspecialchars($e->getMessage()) . "</div>";
    echo "<div class='info'>💡 <strong>Troubleshooting:</strong><br>";
    echo "- Kiểm tra CLOUDFLARE_API_TOKEN trong config.php<br>";
    echo "- Verify rằng API token có quyền Zone:Read<br>";
    echo "- Đảm bảo CLOUDFLARE_EMAIL là đúng account<br>";
    echo "- Check internet connection</div>";
}
?>

<hr>
<div style="text-align: center; margin: 30px 0;">
    <a href="demo_live.html" style="background: #28a745; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 0 10px;">
        🎮 Interactive Demo
    </a>
    <a href="search.php" style="background: #007bff; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 0 10px;">
        🛠️ Full DNS Tool
    </a>
    <a href="cloudflare_api_examples.html" style="background: #6f42c1; color: white; padding: 12px 24px; text-decoration: none; border-radius: 6px; margin: 0 10px;">
        📚 API Examples
    </a>
</div>

</div>

</body>
</html>