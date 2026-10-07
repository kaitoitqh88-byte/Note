<?php
/**
 * Test with mock data when Cloudflare API is unavailable
 */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_POST['action'] === 'check_domains') {
    header('Content-Type: application/json');
    
    echo json_encode([
        'success' => true,
        'results' => [
            [
                'domain_name' => 'xiaofeng.cn.com',
                'zone_found' => false,
                'zone_info' => null,
                'current_cache_settings' => null,
                'success' => true,
                'error' => 'Domain not found in Cloudflare account'
            ]
        ],
        'note' => 'Mock data - Cloudflare API unavailable'
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Test Mock Response</title>
</head>
<body>
    <h1>Cloudflare API Test (Mock Mode)</h1>
    <button onclick="testAPI()">Test Domain Check</button>
    <div id="result"></div>
    
    <script>
    function testAPI() {
        fetch('', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=check_domains&domain_list=xiaofeng.cn.com'
        })
        .then(response => response.json())
        .then(data => {
            document.getElementById('result').innerHTML = '<pre>' + JSON.stringify(data, null, 2) + '</pre>';
        });
    }
    </script>
</body>
</html>