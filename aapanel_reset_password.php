<?php
// aaPanel WordPress Password Reset Script
// Gọi trực tiếp API đổi mật khẩu WP qua aapanel_wp_sites_manager.php

// ==== Cấu hình thông tin cần thiết ====
$panelUrl = 'https://your-aapanel-url'; // URL aaPanel
$panelUser = 'your-username';           // Tài khoản aaPanel
$panelPass = 'your-password';           // Mật khẩu aaPanel
$siteId    = 'domain.com';              // Domain hoặc ID site WordPress
$wpUser    = 'admin';                   // Tài khoản WP cần đổi
$wpPass    = 'newpassword123';          // Mật khẩu mới

// ==== Địa chỉ endpoint backend ====
$apiUrl = 'http://your-server/aapanel_wp_sites_manager.php'; // Đổi thành URL thực tế

// ==== Gửi request ====
$postData = [
    'action'     => 'change_wp_password',
    'panel_url'  => $panelUrl,
    'panel_user' => $panelUser,
    'panel_pass' => $panelPass,
    'site_id'    => $siteId,
    'wp_user'    => $wpUser,
    'wp_pass'    => $wpPass
];

$ch = curl_init($apiUrl);
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
