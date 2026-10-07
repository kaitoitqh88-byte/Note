<?php
/**
 * AJAX API cho set_fastcgi_cache từng domain
 * POST: domain, vps_ip
 */
header('Content-Type: application/json');
require_once __DIR__ . '/aapanel_set_fastcgi_cache.php';

$domain = trim($_POST['domain'] ?? '');
$vps_ip = trim($_POST['vps_ip'] ?? '');

if (!$domain || !$vps_ip) {
    echo json_encode(['success' => false, 'error' => 'Thiếu domain hoặc vps_ip']);
    exit;
}

$vpsList = loadVPSList('vps.json');
$vps = null;
foreach ($vpsList as $item) {
    if ($item['ip'] === $vps_ip && !empty($item['info']) && !empty($item['aapanel_keyapi'])) {
        $vps = $item;
        break;
    }
}
if (!$vps) {
    echo json_encode(['success' => false, 'error' => 'Không tìm thấy VPS hoặc thiếu API key']);
    exit;
}
$panelUrl = preg_replace('/\\/login.*/', '', $vps['info']);
$apiKey = $vps['aapanel_keyapi'];
$apiSecret = $vps['aapanel_keyapi']; // Nếu secret khác thì sửa lại
$result = setFastcgiCache($panelUrl, $apiKey, $apiSecret, $domain);
echo json_encode($result);
