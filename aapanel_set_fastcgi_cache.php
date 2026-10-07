<?php
/**
 * Chạy API set_fastcgi_cache trên aaPanel cho danh sách domain và VPS từ vps.json
 * Yêu cầu nhập danh sách domain, tự động lấy thông tin VPS từ vps.json
 */

function loadVPSList($file = 'vps.json') {
    if (!file_exists($file)) return [];
    $json = file_get_contents($file);
    $list = json_decode($json, true);
    return is_array($list) ? $list : [];
}

function parseDomains($input) {
    $lines = preg_split('/\r?\n/', $input);
    $domains = [];
    foreach ($lines as $line) {
        $domain = trim($line);
        if ($domain && filter_var('http://' . $domain, FILTER_VALIDATE_URL)) {
            $domains[] = $domain;
        }
    }
    return array_unique($domains);
}

function setFastcgiCache($panelUrl, $apiKey, $apiSecret, $domain) {
    $endpoint = '/api/site/set_fastcgi_cache';
    $data = [
        'domain' => $domain,
        'enable' => 1,
        'cache_time' => 600 // 10 phút, có thể chỉnh
    ];
    $data['timestamp'] = time();
    $data['api_key'] = $apiKey;
    $data['signature'] = md5('domain=' . $domain . '&enable=1&cache_time=600&timestamp=' . $data['timestamp'] . $apiSecret);

    $url = rtrim($panelUrl, '/') . $endpoint;
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'User-Agent: aaPanel-API/1.0'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);
    if ($err) return ['success' => false, 'error' => $err];
    $json = json_decode($response, true);
    return $json ? $json : ['success' => false, 'error' => 'Invalid response', 'raw' => $response];
}


if (php_sapi_name() === 'cli') {
    echo "Nhập danh sách domain (mỗi dòng 1 domain):\n";
    $stdin = fopen('php://stdin', 'r');
    $input = '';
    while (($line = fgets($stdin)) !== false) {
        if (trim($line) === '') break;
        $input .= $line;
    }
    fclose($stdin);
    $domains = parseDomains($input);
} else {
    echo '<!DOCTYPE html><html lang="vi"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">';
    echo '<title>Set FastCGI Cache - aaPanel</title>';
    echo '<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">';
    echo '</head><body class="bg-light">';
    echo '<div class="container py-5">';
    echo '<div class="row justify-content-center"><div class="col-md-8">';
    echo '<div class="card shadow">';
    echo '<div class="card-header bg-primary text-white"><h4 class="mb-0"><i class="fas fa-bolt me-2"></i>Set FastCGI Cache cho Domain trên aaPanel</h4></div>';
    echo '<div class="card-body">';
    echo '<form method="post">';
    echo '<div class="mb-3">';
    echo '<label class="form-label">Nhập danh sách domain (mỗi dòng 1 domain):</label>';
    echo '<textarea name="domains" rows="8" class="form-control" placeholder="domain1.com\ndomain2.com" required>';
    echo htmlspecialchars($_POST['domains'] ?? '');
    echo '</textarea>';
    echo '</div>';
    echo '<button type="submit" class="btn btn-success"><i class="fas fa-play"></i> Chạy set_fastcgi_cache</button>';
    echo '</form>';

    if (!empty($_POST['domains'])) {
        $domains = parseDomains($_POST['domains']);
        $vpsList = loadVPSList('vps.json');
        echo '<hr><h5>Kết quả:</h5>';
        echo '<div class="table-responsive">';
        echo '<table class="table table-bordered table-striped align-middle" id="result-table">';
        echo '<thead class="table-light"><tr><th>#</th><th>VPS</th><th>Domain</th><th>Trạng thái</th><th>Chi tiết</th></tr></thead><tbody>';
        $i = 1;
        foreach ($vpsList as $vps) {
            if (empty($vps['info']) || empty($vps['aapanel_keyapi'])) continue;
            foreach ($domains as $domain) {
                echo '<tr id="row-' . $i . '">';
                echo '<td>' . $i . '</td>';
                echo '<td>' . htmlspecialchars($vps['ip']) . '</td>';
                echo '<td>' . htmlspecialchars($domain) . '</td>';
                echo '<td id="status-' . $i . '"><span class="badge bg-secondary">Đang xử lý...</span></td>';
                echo '<td id="detail-' . $i . '"><span class="text-muted">Đang gửi yêu cầu...</span></td>';
                echo '</tr>';
                $i++;
            }
        }
        echo '</tbody></table></div>';
        echo '<script>';
        $i = 1;
        foreach ($vpsList as $vps) {
            if (empty($vps['info']) || empty($vps['aapanel_keyapi'])) continue;
            foreach ($domains as $domain) {
                $row = $i;
                $vps_ip = addslashes($vps['ip']);
                $domain_js = addslashes($domain);
                echo "fetch('aapanel_set_fastcgi_cache_ajax.php', {method: 'POST', headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: 'domain=" . urlencode($domain) . "&vps_ip=" . urlencode($vps['ip']) . "'}).then(r=>r.json()).then(data=>{var ok=data.success||data.status;var msg=data.msg||data.error||data.raw||'';document.getElementById('status-$row').innerHTML=ok?'<span class=\'badge bg-success\'>Thành công</span>':'<span class=\'badge bg-danger\'>Lỗi</span>';document.getElementById('detail-$row').innerHTML='<small>'+((typeof msg==='object')?JSON.stringify(msg):msg)+'</small>';}).catch(e=>{document.getElementById('status-$row').innerHTML='<span class=\'badge bg-danger\'>Lỗi</span>';document.getElementById('detail-$row').innerHTML='<small>'+e+'</small>';});\n";
                $i++;
            }
        }
        echo '</script>';
    }
    echo '</div></div></div></div></div>';
    echo '<script src="https://kit.fontawesome.com/4e8e2e6e7b.js" crossorigin="anonymous"></script>';
    echo '</body></html>';
    if (empty($_POST['domains'])) exit;
}

$vpsList = loadVPSList('vps.json');
$results = [];
foreach ($vpsList as $vps) {
    if (empty($vps['info']) || empty($vps['aapanel_keyapi'])) continue;
    $panelUrl = preg_replace('/\\/login.*/', '', $vps['info']);
    $apiKey = $vps['aapanel_keyapi'];
    $apiSecret = $vps['aapanel_keyapi']; // Nếu secret khác thì sửa lại
    foreach ($domains as $domain) {
        $result = setFastcgiCache($panelUrl, $apiKey, $apiSecret, $domain);
        $results[] = [
            'vps' => $vps['ip'],
            'domain' => $domain,
            'result' => $result
        ];
    }
}

if (php_sapi_name() === 'cli') {
    print_r($results);
} else {
    echo '<pre>' . htmlspecialchars(print_r($results, true)) . '</pre>';
}
