<?php
/**
 * VPS Status & PHP File Change Monitor (HTML Simple)
 * Sử dụng thông tin VPS từ file vps.json
 * Author: Copilot (GPT-4.1)
 */

// Đọc danh sách VPS từ vps.json
function getVpsListFromJson($file = 'vps.json') {
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (!is_array($data)) return [];
    return $data;
}

// Hàm kiểm tra tình trạng VPS (dùng KeyAPI aaPanel lấy thông tin thật)
function check_vps_status_html_json() {
    $servers = getVpsListFromJson();
    echo '<h3>Kiểm tra tình trạng VPS (vps.json, dùng KeyAPI)</h3>';
    echo '<table class="table table-bordered align-middle" style="min-width:400px;">';
    echo '<thead class="table-light"><tr><th>STT</th><th>Tên VPS</th><th>IP/URL</th><th>KeyAPI</th><th>Trạng thái</th><th>Thông tin</th></tr></thead><tbody>';
    foreach ($servers as $i => $server) {
        $url = rtrim($server['panel_url'] ?? $server['ip'] ?? '', '/');
        $api_key = $server['aapanel_keyapi'] ?? ($server['api_key'] ?? '');
        $api_secret = $server['api_secret'] ?? '';
        $status = '<span class="status-unknown">Unknown</span>';
        $infoStr = '';
        // Luôn chuẩn bị requestInfo để hiển thị
        $endpoint = rtrim($url, '/') . '/api/system/info';
        $data = [
            'api_key' => $api_key,
            'timestamp' => time()
        ];
        ksort($data);
        $string = '';
        foreach ($data as $k => $v) $string .= $k . '=' . $v . '&';
        $string = rtrim($string, '&');
        $data['signature'] = md5($string . $api_secret);
        $payload = json_encode($data, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
        $headers = [
            'Content-Type: application/json',
            'User-Agent: aapanel-vps-status-json/1.0'
        ];
        $requestInfo = '<details><summary class="small text-primary">Xem request</summary>';
        $requestInfo .= '<div class="small"><b>Endpoint:</b> <code>' . htmlspecialchars($endpoint) . '</code><br>';
        $requestInfo .= '<b>Payload:</b><pre style="font-size:11px;background:#f8f9fa;">' . htmlspecialchars($payload) . '</pre>';
        $requestInfo .= '<b>Headers:</b><pre style="font-size:11px;background:#f8f9fa;">' . htmlspecialchars(implode("\n", $headers)) . '</pre></div></details>';

        // Lấy thông tin mạng qua API /v2/system?action=GetNetWork
        $networkInfo = '';
        if ($url && $api_key && $api_secret) {
            try {
                $info = aapanel_api_get_system_info($url, $api_key, $api_secret);
                $status = '<span class="status-online">ONLINE</span>';
                $infoStr = htmlspecialchars(($info['hostname'] ?? '') . ' | ' . ($info['os'] ?? '') . ' | Uptime: ' . ($info['uptime'] ?? ''));
                // Lấy network info
                $net = aapanel_api_get_network_info($url, $api_key, $api_secret);
                if (is_array($net) && isset($net['message'])) {
                    $msg = $net['message'];
                    $hostname = htmlspecialchars($msg['hostname'] ?? '');
                    $uptime = htmlspecialchars($msg['time'] ?? '');
                    $version = htmlspecialchars($msg['version'] ?? '');
                    $system = htmlspecialchars($msg['system'] ?? '');
                    $cpu = is_array($msg['cpu'] ?? null) ? $msg['cpu'][3] ?? '' : '';
                    $load = isset($msg['load']) ?
                        '1m: ' . ($msg['load']['one'] ?? '') . ', 5m: ' . ($msg['load']['five'] ?? '') . ', 15m: ' . ($msg['load']['fifteen'] ?? '') : '';
                    $ram = isset($msg['mem']) ?
                        'Used: ' . ($msg['mem']['memRealUsed'] ?? '') . 'MB / Total: ' . ($msg['mem']['memTotal'] ?? '') . 'MB' : '';
                    $site_total = $msg['site_total'] ?? '';
                    $db_total = $msg['database_total'] ?? '';
                    $mainIf = 'eth0';
                    $ifaces = $msg['network'] ?? [];
                    $iface = $ifaces[$mainIf] ?? reset($ifaces);
                    $ifaceName = isset($ifaces[$mainIf]) ? $mainIf : key($ifaces);
                    $ifaceInfo = '';
                    if (is_array($iface)) {
                        $ifaceInfo =
                            'Interface: <b>' . htmlspecialchars($ifaceName) . '</b> | '
                            . 'Up: <b>' . ($iface['up'] ?? 0) . ' KB/s</b> | Down: <b>' . ($iface['down'] ?? 0) . ' KB/s</b><br>'
                            . 'Total Up: <b>' . ($iface['upTotal'] ?? 0) . '</b> | Total Down: <b>' . ($iface['downTotal'] ?? 0) . '</b>';
                    }
                    $networkInfo = '<div style="font-size:12px;line-height:1.5;margin-top:4px">'
                        . '<b>Host:</b> ' . $hostname
                        . ' | <b>Uptime:</b> ' . $uptime
                        . ' | <b>Version:</b> ' . $version
                        . '<br><b>System:</b> ' . $system
                        . '<br><b>CPU:</b> ' . htmlspecialchars($cpu)
                        . ' | <b>Load:</b> ' . $load
                        . '<br><b>RAM:</b> ' . $ram
                        . ' | <b>Sites:</b> ' . $site_total . ' | <b>DB:</b> ' . $db_total
                        . '<br>' . $ifaceInfo
                        . '</div>';
                }
            } catch (Exception $e) {
                $status = '<span class="status-offline">OFFLINE</span>';
                $infoStr = htmlspecialchars($e->getMessage());
            }
        } else {
            $infoStr = 'Thiếu API key/secret';
        }
        $api_key_display = htmlspecialchars($api_key);
        $rowId = 'vpsrow_' . $i;
        echo '<tr id="' . $rowId . '"><td>' . ($i+1) . '</td><td>' . htmlspecialchars($server['name'] ?? 'VPS '.($i+1)) . '</td><td>' . htmlspecialchars($url) . '</td><td><code>' . $api_key_display . '</code></td><td>' . $status . '</td><td>' . $infoStr . '<br><button type="button" class="btn btn-sm btn-outline-info mt-1" onclick="loadNetInfo(this,\'' . addslashes($url) . '\',\'' . addslashes($api_key) . '\',\'' . addslashes($api_secret) . '\',' . $i . ')">Xem chi tiết mạng</button><div id="netinfo_' . $i . '"></div>' . $requestInfo . '</td></tr>';
    }
    echo '</tbody></table>';
    // Script đã chuyển sang file ngoài
}

// Hàm lấy thông tin mạng qua API /v2/system?action=GetNetWork
function aapanel_api_get_network_info($panel_url, $api_key, $api_secret) {
    $endpoint = rtrim($panel_url, '/') . '/v2/system?action=GetNetWork';
    $data = [
        'api_key' => $api_key,
        'timestamp' => time()
    ];
    ksort($data);
    $string = '';
    foreach ($data as $k => $v) $string .= $k . '=' . $v . '&';
    $string = rtrim($string, '&');
    $data['signature'] = md5($string . $api_secret);
    $payload = json_encode($data);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: aapanel-vps-status-json/1.0'
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) throw new Exception('cURL: ' . $err);
    if ($httpCode !== 200) throw new Exception('HTTP: ' . $httpCode);
    $json = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('JSON: ' . json_last_error_msg());
    if (isset($json['status']) && $json['status'] === false && isset($json['msg'])) throw new Exception($json['msg']);
    return $json;
}

// Hàm gọi API aaPanel lấy thông tin hệ thống (dùng key)
function aapanel_api_get_system_info($panel_url, $api_key, $api_secret) {
    $endpoint = rtrim($panel_url, '/') . '/api/system/info';
    $data = [
        'api_key' => $api_key,
        'timestamp' => time()
    ];
    ksort($data);
    $string = '';
    foreach ($data as $k => $v) $string .= $k . '=' . $v . '&';
    $string = rtrim($string, '&');
    $data['signature'] = md5($string . $api_secret);
    $payload = json_encode($data);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $endpoint);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 8);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: aapanel-vps-status-json/1.0'
    ]);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($err) throw new Exception('cURL: ' . $err);
    if ($httpCode !== 200) throw new Exception('HTTP: ' . $httpCode);
    $json = json_decode($response, true);
    if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('JSON: ' . json_last_error_msg());
    if (isset($json['status']) && $json['status'] === false && isset($json['msg'])) throw new Exception($json['msg']);
    return $json;
}

// Hàm kiểm tra biến động file PHP (giả lập, chỉ demo)
function check_php_file_changes_html_json() {
    $servers = getVpsListFromJson();
    echo '<h3>Kiểm tra biến động file PHP (vps.json)</h3>';
    foreach ($servers as $server) {
        echo '<h4>' . htmlspecialchars($server['name'] ?? $server['ip'] ?? 'VPS') . '</h4>';
        // Demo: Không có API thực, chỉ hiển thị thông báo mẫu
        echo '<span style="color:gray;">Chức năng này cần tích hợp API thực tế từ aaPanel hoặc SSH để kiểm tra log/file PHP.</span>';
    }
}

// Router đơn giản
// UI hiện đại với Bootstrap, tab chuyển đổi, nút làm mới
if (!isset($_GET['vps_status']) && !isset($_GET['php_changes'])) {
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>VPS Status & PHP File Monitor (vps.json)</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '<style>.status-online{color:#fff;background:#28a745;padding:2px 8px;border-radius:4px}.status-offline{color:#fff;background:#dc3545;padding:2px 8px;border-radius:4px}.status-unknown{color:#fff;background:#6c757d;padding:2px 8px;border-radius:4px}</style>';
    echo '</head><body class="bg-light">';
    echo '<div class="container py-4">';
    echo '<h2 class="mb-4"><i class="bi bi-hdd-network"></i> VPS Status & PHP File Monitor <span class="badge bg-info">vps.json</span></h2>';
    echo '<ul class="nav nav-tabs mb-3" id="tabMenu"><li class="nav-item"><a class="nav-link active" id="vps-tab" data-bs-toggle="tab" href="#vps-status">Tình trạng VPS</a></li><li class="nav-item"><a class="nav-link" id="php-tab" data-bs-toggle="tab" href="#php-changes">Biến động file PHP</a></li></ul>';
    echo '<div class="tab-content">';
    echo '<div class="tab-pane fade show active" id="vps-status">';
    echo '<div class="d-flex justify-content-end mb-2"><button id="refresh-vps" class="btn btn-primary btn-sm"><i class="bi bi-arrow-repeat"></i> Làm mới</button></div>';
    echo '<div class="card"><div class="card-body"><div id="vps-table-area">Đang tải...</div></div></div>';
    echo '</div>';
    echo '<div class="tab-pane fade" id="php-changes">';
    echo '<div class="d-flex justify-content-end mb-2"><a href="?php_changes=1" class="btn btn-warning btn-sm"><i class="bi bi-arrow-repeat"></i> Làm mới</a></div>';
    ob_start(); check_php_file_changes_html_json(); $phpHtml = ob_get_clean();
    echo '<div class="card"><div class="card-body">' . $phpHtml . '</div></div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>';
    echo '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">';
    echo '<link rel="stylesheet" href="aapanel_vps_status_json.css">';
    echo '<script src="aapanel_vps_status_json.js"></script>';
    echo '</body></html>';
    exit;
}
if (isset($_GET['vps_status'])) {
    // AJAX lấy riêng network info cho từng VPS
    if (isset($_GET['ajax']) && isset($_GET['get_network'])) {
        $url = $_GET['url'] ?? '';
        $key = $_GET['key'] ?? '';
        $secret = $_GET['secret'] ?? '';
        try {
            $net = aapanel_api_get_network_info($url, $key, $secret);
            if (is_array($net) && isset($net['message'])) {
                $msg = $net['message'];
                $hostname = htmlspecialchars($msg['hostname'] ?? '');
                $uptime = htmlspecialchars($msg['time'] ?? '');
                $version = htmlspecialchars($msg['version'] ?? '');
                $system = htmlspecialchars($msg['system'] ?? '');
                $cpu = is_array($msg['cpu'] ?? null) ? $msg['cpu'][3] ?? '' : '';
                $load = isset($msg['load']) ?
                    '1m: ' . ($msg['load']['one'] ?? '') . ', 5m: ' . ($msg['load']['five'] ?? '') . ', 15m: ' . ($msg['load']['fifteen'] ?? '') : '';
                $ram = isset($msg['mem']) ?
                    'Used: ' . ($msg['mem']['memRealUsed'] ?? '') . 'MB / Total: ' . ($msg['mem']['memTotal'] ?? '') . 'MB' : '';
                $site_total = $msg['site_total'] ?? '';
                $db_total = $msg['database_total'] ?? '';
                $mainIf = 'eth0';
                $ifaces = $msg['network'] ?? [];
                $iface = $ifaces[$mainIf] ?? reset($ifaces);
                $ifaceName = isset($ifaces[$mainIf]) ? $mainIf : key($ifaces);
                $ifaceInfo = '';
                if (is_array($iface)) {
                    $ifaceInfo =
                        'Interface: <b>' . htmlspecialchars($ifaceName) . '</b> | '
                        . 'Up: <b>' . ($iface['up'] ?? 0) . ' KB/s</b> | Down: <b>' . ($iface['down'] ?? 0) . ' KB/s</b><br>'
                        . 'Total Up: <b>' . ($iface['upTotal'] ?? 0) . '</b> | Total Down: <b>' . ($iface['downTotal'] ?? 0) . '</b>';
                }
                echo '<div style="font-size:12px;line-height:1.5;margin-top:4px">'
                    . '<b>Host:</b> ' . $hostname
                    . ' | <b>Uptime:</b> ' . $uptime
                    . ' | <b>Version:</b> ' . $version
                    . '<br><b>System:</b> ' . $system
                    . '<br><b>CPU:</b> ' . htmlspecialchars($cpu)
                    . ' | <b>Load:</b> ' . $load
                    . '<br><b>RAM:</b> ' . $ram
                    . ' | <b>Sites:</b> ' . $site_total . ' | <b>DB:</b> ' . $db_total
                    . '<br>' . $ifaceInfo
                    . '</div>';
            } else {
                echo '<span class="text-danger">Không lấy được thông tin mạng.</span>';
            }
        } catch (Exception $e) {
            echo '<span class="text-danger">Lỗi: ' . htmlspecialchars($e->getMessage()) . '</span>';
        }
        exit;
    }
    // Nếu là AJAX chỉ trả về bảng, không trả về html đầy đủ
    if (isset($_GET['ajax'])) {
        check_vps_status_html_json();
        exit;
    }
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>VPS Status (vps.json)</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '</head><body class="bg-light py-4">';
    echo '<div class="container">';
    echo '<a href="aapanel_vps_status_json.php" class="btn btn-secondary btn-sm mb-3">&larr; Quay lại</a>';
    check_vps_status_html_json();
    echo '</div></body></html>';
    exit;
}
if (isset($_GET['php_changes'])) {
    echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>PHP File Changes (vps.json)</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '</head><body class="bg-light py-4">';
    echo '<div class="container">';
    echo '<a href="aapanel_vps_status_json.php" class="btn btn-secondary btn-sm mb-3">&larr; Quay lại</a>';
    check_php_file_changes_html_json();
    echo '</div></body></html>';
    exit;
}
