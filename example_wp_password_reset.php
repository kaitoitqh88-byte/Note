<?php
// Ví dụ gọi API reset mật khẩu WordPress qua aapanel_wp_sites_manager.php

$url = '/aapanel_wp_sites_manager.php'; // Đổi thành URL thực tế

$postData = [
    'action' => 'change_wp_password',
    'panel_url' => 'https://your-aapanel-url',
    'panel_user' => 'your-username',
    'panel_pass' => 'your-password',
    'site_id'   => 'domain.com', // hoặc ID site theo API
    'wp_user'   => 'admin',
    'wp_pass'   => 'newpassword123'
];

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/x-www-form-urlencoded'
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($httpCode === 200) {
    $result = json_decode($response, true);
    if ($result['success']) {
        echo "Đổi mật khẩu thành công!" . PHP_EOL;
        print_r($result['data'] ?? []);
    } else {
        echo "Lỗi: " . ($result['error'] ?? 'Không xác định') . PHP_EOL;
        print_r($result['response'] ?? []);
    }
} else {
    echo "Lỗi HTTP: $httpCode\n";
    echo $response;
}
