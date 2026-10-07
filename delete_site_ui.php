<?php
/**
 * delete_site_ui.php
 * Form xóa website trên aaPanel theo danh sách domain.
 */

function deleteSiteNormalizeDomain($domain) {
    $domain = trim((string)$domain);
    if ($domain === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $domain)) {
        $domain = 'http://' . $domain;
    }
    $host = parse_url($domain, PHP_URL_HOST);
    if (!$host) {
        return '';
    }
    $host = strtolower(trim($host));
    $host = preg_replace('/\.+$/', '', $host);
    if (strpos($host, 'www.') === 0) {
        $host = substr($host, 4);
    }
    return $host;
}

function deleteSiteParseDomainList($input) {
    $parts = preg_split('/[\r\n,;\s]+/', (string)$input);
    $domains = [];
    $seen = [];
    foreach ($parts as $part) {
        $normalized = deleteSiteNormalizeDomain($part);
        if ($normalized !== '' && !isset($seen[$normalized])) {
            $domains[] = $normalized;
            $seen[$normalized] = true;
        }
    }
    return $domains;
}

function deleteSiteAaPanelRequest($apiUrl, $apiKey, $endpoint, array $data = []) {
    $now = time();
    $data['request_time'] = $now;
    $data['request_token'] = md5($now . md5($apiKey));

    $ch = curl_init(rtrim($apiUrl, '/') . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER => [
            'x-http-token: ' . $apiKey,
            'Content-Type: application/x-www-form-urlencoded',
        ],
    ]);
    $resp = curl_exec($ch);
    $errno = curl_errno($ch);
    $emsg = curl_error($ch);
    curl_close($ch);

    if ($errno) {
        return ['status' => false, 'msg' => 'cURL #' . $errno . ': ' . $emsg];
    }
    $decoded = json_decode((string)$resp, true);
    if ($decoded === null) {
        return ['status' => false, 'msg' => 'Invalid JSON: ' . substr((string)$resp, 0, 200)];
    }
    return $decoded;
}

function deleteSiteFetchSiteTable($apiUrl, $apiKey) {
    $data = deleteSiteAaPanelRequest($apiUrl, $apiKey, '/v2/site?action=get_site_list');
    $table = [];
    $seen = [];
    $sources = [];
    if (isset($data['data']) && is_array($data['data'])) {
        $sources[] = $data['data'];
    }
    if (isset($data['message']) && is_array($data['message'])) {
        $sources[] = $data['message'];
    }
    foreach ($sources as $source) {
        foreach ($source as $site) {
            $domain = $site['domain'] ?? $site['name'] ?? '';
            $sId = $site['id'] ?? $site['s_id'] ?? '';
            $key = $domain . '|' . $sId;
            if ($domain && $sId && !isset($seen[$key])) {
                $table[] = [
                    'domain' => $domain,
                    's_id' => $sId,
                ];
                $seen[$key] = true;
            }
        }
    }
    return ['site_table' => $table, 'raw' => $data];
}

function deleteSiteBuildDomainMap(array $siteTable) {
    $map = [];
    foreach ($siteTable as $site) {
        $rawDomains = preg_split('/[\r\n,;\s]+/', (string)($site['domain'] ?? ''));
        foreach ($rawDomains as $singleDomain) {
            $normalized = deleteSiteNormalizeDomain($singleDomain);
            if ($normalized !== '' && !isset($map[$normalized])) {
                $map[$normalized] = [
                    'webname' => trim($singleDomain),
                    's_id' => $site['s_id'],
                ];
            }
        }
    }
    return $map;
}

function deleteSiteLoadVpsList($vpsFile = null) {
    if ($vpsFile === null) {
        $vpsFile = __DIR__ . '/vps.json';
    }
    if (!file_exists($vpsFile)) {
        return [];
    }

    $data = json_decode((string)file_get_contents($vpsFile), true);
    return is_array($data) ? $data : [];
}

function deleteSiteBuildVpsApiConfig(array $vps) {
    $panelUrl = trim((string)($vps['info'] ?? ''));
    if ($panelUrl === '') {
        $ip = trim((string)($vps['ip'] ?? ''));
        $panelUrl = $ip !== '' ? 'http://' . $ip . ':8888' : '';
    }
    if ($panelUrl !== '' && !preg_match('#^https?://#i', $panelUrl)) {
        $panelUrl = 'http://' . $panelUrl;
    }

    $apiKey = trim((string)($vps['aapanel_keyapi'] ?? ($vps['api_key'] ?? '')));

    return [
        'ip' => trim((string)($vps['ip'] ?? '')),
        'api_url' => rtrim($panelUrl, '/'),
        'api_key' => $apiKey,
    ];
}

function deleteSiteResolveDomainOnVps($domain, array $vpsList) {
    $normalized = deleteSiteNormalizeDomain($domain);
    if ($normalized === '') {
        return ['success' => false, 'msg' => 'Domain không hợp lệ'];
    }

    foreach ($vpsList as $vps) {
        if (!is_array($vps)) {
            continue;
        }

        $config = deleteSiteBuildVpsApiConfig($vps);
        if ($config['api_url'] === '' || $config['api_key'] === '') {
            continue;
        }

        $siteResult = deleteSiteFetchSiteTable($config['api_url'], $config['api_key']);
        $siteTable = $siteResult['site_table'] ?? [];
        if (empty($siteTable)) {
            continue;
        }

        $siteMap = deleteSiteBuildDomainMap($siteTable);
        if (!isset($siteMap[$normalized])) {
            continue;
        }

        return [
            'success' => true,
            'api_url' => $config['api_url'],
            'api_key' => $config['api_key'],
            'site' => $siteMap[$normalized],
            'vps' => $vps,
            'vps_ip' => $config['ip'],
        ];
    }

    return ['success' => false, 'msg' => 'Không tìm thấy domain trên bất kỳ VPS nào trong vps.json'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $apiUrl = trim($_POST['api_url'] ?? '');
    $apiKey = trim($_POST['api_key'] ?? '');
    $action = $_POST['ajax_action'];

    $vpsList = deleteSiteLoadVpsList();
    if (!$apiUrl || !$apiKey) {
        if ($action === 'test_connection' || $action === 'get_site_list' || $action === 'delete_sites') {
            if (empty($vpsList)) {
                echo json_encode(['success' => false, 'msg' => 'Thiếu API URL/API Key và không tìm thấy file vps.json']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'msg' => 'Thiếu API URL hoặc API Key']);
            exit;
        }
    }

    if ($action === 'test_connection') {
        if ($apiUrl && $apiKey) {
            $r = deleteSiteAaPanelRequest($apiUrl, $apiKey, '/system?action=GetSystemTotal');
            $ok = isset($r['memTotal']) || (isset($r['status']) && $r['status'] === true);
            echo json_encode([
                'success' => $ok,
                'msg' => $ok ? 'Kết nối thành công!' : ($r['msg'] ?? 'Kết nối thất bại'),
            ]);
            exit;
        }

        $detected = [];
        $detectedIps = [];
        foreach ($vpsList as $vps) {
            if (!is_array($vps)) {
                continue;
            }
            $config = deleteSiteBuildVpsApiConfig($vps);
            if ($config['api_url'] === '' || $config['api_key'] === '') {
                continue;
            }
            $r = deleteSiteAaPanelRequest($config['api_url'], $config['api_key'], '/system?action=GetSystemTotal');
            $ok = isset($r['memTotal']) || (isset($r['status']) && $r['status'] === true);
            if ($ok) {
                $detectedIps[] = $config['ip'];
            }
        }

        if (!empty($detectedIps)) {
            echo json_encode([
                'success' => true,
                'msg' => 'Kết nối thành công qua vps.json, VPS: ' . implode(', ', $detectedIps),
            ]);
            exit;
        }

        echo json_encode(['success' => false, 'msg' => 'Không kết nối được VPS nào từ vps.json']);
        exit;
    }

    if ($action === 'get_site_list') {
        if ($apiUrl && $apiKey) {
            $result = deleteSiteFetchSiteTable($apiUrl, $apiKey);
            echo json_encode([
                'success' => !empty($result['site_table']),
                'site_table' => $result['site_table'],
                'msg' => empty($result['site_table']) ? 'Không lấy được danh sách site' : '',
            ]);
            exit;
        }

        $siteTable = [];
        $seen = [];
        foreach ($vpsList as $vps) {
            if (!is_array($vps)) {
                continue;
            }
            $config = deleteSiteBuildVpsApiConfig($vps);
            if ($config['api_url'] === '' || $config['api_key'] === '') {
                continue;
            }
            $result = deleteSiteFetchSiteTable($config['api_url'], $config['api_key']);
            foreach (($result['site_table'] ?? []) as $row) {
                $key = ($row['s_id'] ?? '') . '|' . ($row['domain'] ?? '');
                if ($key !== '|' && !isset($seen[$key])) {
                    $siteTable[] = $row;
                    $seen[$key] = true;
                }
            }
        }

        echo json_encode([
            'success' => !empty($siteTable),
            'site_table' => $siteTable,
            'msg' => empty($siteTable) ? 'Không lấy được danh sách site từ vps.json' : '',
        ]);
        exit;
    }

    if ($action === 'delete_sites') {
        $domainInput = trim($_POST['domains'] ?? '');
        $deleteFtp = !empty($_POST['delete_ftp']);
        $deleteDatabase = !empty($_POST['delete_database']);
        $deletePath = !empty($_POST['delete_path']);

        $domains = deleteSiteParseDomainList($domainInput);
        if (empty($domains)) {
            echo json_encode(['success' => false, 'msg' => 'Không có domain hợp lệ trong danh sách']);
            exit;
        }

        $results = [];
        $successCount = 0;
        $errorCount = 0;
        $notFoundCount = 0;

        foreach ($domains as $domain) {
            $resolved = ['success' => false];
            if ($apiUrl && $apiKey) {
                $siteResult = deleteSiteFetchSiteTable($apiUrl, $apiKey);
                $siteTable = $siteResult['site_table'] ?? [];
                if (!empty($siteTable)) {
                    $domainMap = deleteSiteBuildDomainMap($siteTable);
                    if (isset($domainMap[$domain])) {
                        $resolved = [
                            'success' => true,
                            'api_url' => $apiUrl,
                            'api_key' => $apiKey,
                            'site' => $domainMap[$domain],
                        ];
                    }
                }
            }

            if (!$resolved['success'] && !empty($vpsList)) {
                $resolved = deleteSiteResolveDomainOnVps($domain, $vpsList);
            }

            if (!$resolved['success']) {
                $notFoundCount++;
                $results[] = [
                    'domain' => $domain,
                    'status' => 'not_found',
                    'msg' => $resolved['msg'] ?? 'Không tìm thấy site trên aaPanel',
                ];
                continue;
            }

            $site = $resolved['site'];
            $payload = [
                'id' => $site['s_id'],
                'webname' => $site['webname'],
            ];
            if ($deleteFtp) {
                $payload['ftp'] = 1;
            }
            if ($deleteDatabase) {
                $payload['database'] = 1;
            }
            if ($deletePath) {
                $payload['path'] = 1;
            }

            $r = deleteSiteAaPanelRequest($resolved['api_url'], $resolved['api_key'], '/site?action=DeleteSite', $payload);
            $ok = (isset($r['status']) && $r['status'] === true)
                || (isset($r['code']) && (int)$r['code'] === 0);

            if ($ok) {
                $successCount++;
                $results[] = [
                    'domain' => $domain,
                    'status' => 'success',
                    'msg' => $r['msg'] ?? 'Đã xóa',
                    'site_id' => $site['s_id'],
                    'vps_ip' => $resolved['vps_ip'] ?? '',
                ];
            } else {
                $errorCount++;
                $results[] = [
                    'domain' => $domain,
                    'status' => 'error',
                    'msg' => $r['msg'] ?? ($r['error'] ?? 'Xóa thất bại'),
                    'site_id' => $site['s_id'],
                    'vps_ip' => $resolved['vps_ip'] ?? '',
                ];
            }
        }

        echo json_encode([
            'success' => $successCount > 0,
            'summary' => [
                'total' => count($domains),
                'success' => $successCount,
                'error' => $errorCount,
                'not_found' => $notFoundCount,
            ],
            'results' => $results,
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'msg' => 'Invalid ajax_action']);
    exit;
}

$currentPage = 'delete_site_ui';
include __DIR__ . '/includes/main_navigation.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Xóa Site aaPanel theo Domain</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&display=swap" rel="stylesheet">
    <link href="assets/css/sidebar-navigation.css" rel="stylesheet">
    <style>
        :root {
            --tactical-border: #00ff66;
            --tactical-text: #eaffea;
        }
        html, body {
            background: #000 !important;
            color: var(--tactical-text) !important;
            font-family: 'Roboto', Arial, sans-serif !important;
        }
        body { padding-left: 280px; }
        .card, .card-header, .card-body, .form-control, .btn, .form-check-label {
            background: #000 !important;
            color: var(--tactical-text) !important;
            border-color: var(--tactical-border) !important;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 0 24px rgba(0, 255, 0, 0.08);
            border: 1.5px solid var(--tactical-border);
        }
        .form-label, .form-control::placeholder { color: #00ff66 !important; }
        .btn-danger {
            background: #ff3333 !important;
            border-color: #ff3333 !important;
            color: #fff !important;
            font-weight: 700;
        }
        .btn-outline-secondary, .btn-outline-primary {
            color: #00ff66 !important;
            border-color: #00ff66 !important;
        }
        .btn-outline-secondary:hover, .btn-outline-primary:hover {
            background: #00ff66 !important;
            color: #000 !important;
        }
        .table, .table th, .table td {
            background: #000 !important;
            color: #00ff66 !important;
            border-color: #00ff66 !important;
        }
        .result-success { color: #00ff66; }
        .result-error { color: #ff6666; }
        .result-warn { color: #ffcc00; }
    </style>
</head>
<body>
<div class="container-fluid py-3">
    <div class="row">
        <div class="col-lg-12 mb-4">
            <div class="card h-100">
                <div class="card-header">
                    <h4 class="mb-0">Xóa website trên aaPanel theo domain</h4>
                </div>
                <div class="card-body">
                    <form id="deleteSiteForm">
                        <div class="mb-3">
                            <label class="form-label">Danh sách domain cần xóa (mỗi dòng 1 domain)</label>
                            <textarea class="form-control" name="domains" rows="8" required
                                placeholder="example.com&#10;site2.net&#10;www.site3.vn"></textarea>
                            <div class="small mt-1" style="color:#ffcc00;">
                                Hỗ trợ xuống dòng, dấu phẩy hoặc khoảng trắng. Tự động bỏ http/https và www. Chỉ cần nhập domain, hệ thống sẽ tìm VPS tương ứng trong <strong>vps.json</strong> và xóa website trên VPS đó.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label d-block">Tùy chọn xóa kèm theo</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="delete_ftp" id="deleteFtp" value="1">
                                <label class="form-check-label" for="deleteFtp">Xóa FTP liên quan</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="delete_database" id="deleteDatabase" value="1" checked>
                                <label class="form-check-label" for="deleteDatabase">Xóa database liên quan</label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="delete_path" id="deletePath" value="1" checked>
                                <label class="form-check-label" for="deletePath">Xóa thư mục website</label>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-danger w-100">Xóa các domain đã nhập</button>
                    </form>
                    <div id="resultBox" class="mt-3"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function getFormValues() {
    const form = document.getElementById('deleteSiteForm');
    return {
        domains: form.domains.value.trim(),
        delete_ftp: form.delete_ftp.checked ? '1' : '',
        delete_database: form.delete_database.checked ? '1' : '',
        delete_path: form.delete_path.checked ? '1' : '',
    };
}

async function postAction(action, extra = {}) {
    const values = getFormValues();
    const body = new URLSearchParams({ ajax_action: action, ...values, ...extra });
    const res = await fetch('delete_site_ui.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body,
    });
    return res.json();
}

document.getElementById('deleteSiteForm').onsubmit = async function(e) {
    e.preventDefault();
    const values = getFormValues();
    const lines = values.domains.split(/\r?\n/).map(s => s.trim()).filter(Boolean);
    if (!lines.length) return;

    const confirmMsg = 'Bạn chắc chắn muốn XÓA ' + lines.length + ' domain trên aaPanel?\n\n'
        + lines.slice(0, 10).join('\n')
        + (lines.length > 10 ? '\n... và ' + (lines.length - 10) + ' domain khác' : '')
        + '\n\nHành động này không thể hoàn tác!';
    if (!confirm(confirmMsg)) return;

    const box = document.getElementById('resultBox');
    box.innerHTML = '<span class="result-warn">Đang xóa ' + lines.length + ' domain...</span>';

    try {
        const data = await postAction('delete_sites');
        if (!data.results) {
            box.innerHTML = '<span class="result-error">' + (data.msg || 'Lỗi không xác định') + '</span>';
            return;
        }

        const s = data.summary || {};
        let html = '<div class="mb-2"><strong>Kết quả:</strong> '
            + 'Thành công: ' + (s.success || 0)
            + ' | Lỗi: ' + (s.error || 0)
            + ' | Không tìm thấy: ' + (s.not_found || 0)
            + '</div>';
        html += '<table class="table table-sm table-bordered"><thead><tr><th>Domain</th><th>Trạng thái</th><th>Chi tiết</th></tr></thead><tbody>';
        data.results.forEach(function(row) {
            const cls = row.status === 'success' ? 'result-success'
                : (row.status === 'not_found' ? 'result-warn' : 'result-error');
            html += '<tr><td>' + row.domain + '</td><td class="' + cls + '">' + row.status + '</td><td>' + (row.msg || '') + '</td></tr>';
        });
        html += '</tbody></table>';
        box.innerHTML = html;
    } catch (err) {
        box.innerHTML = '<span class="result-error">Lỗi khi gửi yêu cầu xóa</span>';
    }
};
</script>
</body>
</html>
