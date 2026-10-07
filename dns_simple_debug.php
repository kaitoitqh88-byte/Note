<?php
/**
 * Debug version to test DNS Simple functionality
 */

require_once 'config.php';

echo "<h1>DNS Simple Debug</h1>";
echo "<h2>URL Parameters:</h2>";
echo "<pre>";
print_r($_GET);
echo "</pre>";

echo "<h2>Available CloudflareAPI:</h2>";
if (file_exists('CloudflareAPI.php')) {
    echo "CloudflareAPI.php exists<br>";
    require_once 'CloudflareAPI.php';
    
    try {
        $api = new CloudflareAPI();
        echo "CloudflareAPI instantiated successfully<br>";
        
        // Test API connection
        $zones = $api->listZones(1, 1);
        if ($zones['success']) {
            echo "API connection working - found " . count($zones['result']) . " zone(s)<br>";
        } else {
            echo "API connection failed: " . ($zones['error'] ?? 'Unknown error') . "<br>";
        }
    } catch (Exception $e) {
        echo "Error creating CloudflareAPI: " . $e->getMessage() . "<br>";
    }
} else {
    echo "CloudflareAPI.php NOT FOUND<br>";
}

echo "<h2>Test Domain Processing:</h2>";
if (isset($_GET['domains']) && isset($_GET['ip_address'])) {
    $domains = $_GET['domains'];
    $ip = $_GET['ip_address'];
    
    echo "Processing domain: $domains<br>";
    echo "With IP: $ip<br>";
    
    if (isset($api)) {
        // Test finding zone for domain
        $allZones = $api->listZones(1, 100);
        if ($allZones['success'] && isset($allZones['result'])) {
            echo "Found " . count($allZones['result']) . " total zones<br>";
            
            foreach ($allZones['result'] as $zone) {
                if (strtolower($zone['name']) === strtolower($domains)) {
                    echo "Zone found for $domains: " . $zone['name'] . " (ID: " . $zone['id'] . ")<br>";
                    break;
                }
            }
        }
    }
} else {
    echo "No domain/IP parameters provided<br>";
    echo "Expected: ?domains=domain.com&ip_address=1.2.3.4<br>";
}
?>