<?php
/**
 * Domain Actions - Cập nhật cấu hình domain trên Cloudflare
 * - Cấu hình SSL/TLS Encryption Mode → Full
 * - Xóa toàn bộ Cache (Purge Everything)
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/CloudflareAPI.php';

$logFilePath = __DIR__ . '/logs/domain_actions.log';
$logCleared = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clear_log') {
    if (file_exists($logFilePath)) {
        unlink($logFilePath);
    }
    $logCleared = true;
}

function loadVpsListFromJson($filePath) {
    if (!file_exists($filePath)) {
        return [];
    }

    $data = json_decode(file_get_contents($filePath), true);
    return is_array($data) ? $data : [];
}

function appendDomainActionLog($message, $context = []) {
    global $logFilePath;
    $logDir = dirname($logFilePath);
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    $entry = [
        'time' => date('Y-m-d H:i:s'),
        'message' => $message,
        'context' => $context
    ];

    file_put_contents($logFilePath, json_encode($entry, JSON_UNESCAPED_SLASHES) . PHP_EOL, FILE_APPEND);
}

function buildAapanelV2Url($panelUrl, $action, $apiSecret) {
    $requestTime = time();
    $requestToken = md5($requestTime . md5($apiSecret));

    $actionPath = ltrim($action, '/');
    $separator = (strpos($actionPath, '?') === false) ? '?' : '&';

    return [
        'url' => rtrim($panelUrl, '/') . '/v2/' . $actionPath . $separator . 'request_time=' . $requestTime . '&request_token=' . $requestToken,
        'request_time' => $requestTime,
        'request_token' => $requestToken
    ];
}

function isIpHost($url) {
    $host = parse_url($url, PHP_URL_HOST);
    if (!is_string($host) || $host === '') {
        return false;
    }

    return filter_var($host, FILTER_VALIDATE_IP) !== false;
}

function aapanelV2Request($panelUrl, $apiSecret, $action, $postData) {
    $request = buildAapanelV2Url($panelUrl, $action, $apiSecret);
    $isIpPanelHost = isIpHost($panelUrl);

    $ch = curl_init($request['url']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/x-www-form-urlencoded',
        'User-Agent: aaPanel-API/1.0'
    ]);
    // aaPanel thường dùng cert tự ký; nếu panel URL là IP thì cert SAN không khớp.
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $isIpPanelHost ? 0 : 2);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        return ['success' => false, 'error' => $err];
    }

    $json = json_decode($response, true);
    if (!is_array($json)) {
        return ['success' => false, 'error' => 'Invalid response', 'raw' => $response];
    }

    return ['success' => true, 'data' => $json];
}

function extractAapanelErrorMessage($payload) {
    if (!is_array($payload)) {
        return 'API error';
    }

    $candidates = [];

    if (isset($payload['msg']) && $payload['msg'] !== '') {
        $candidates[] = $payload['msg'];
    }

    if (isset($payload['message'])) {
        if (is_string($payload['message']) && $payload['message'] !== '') {
            $candidates[] = $payload['message'];
        } elseif (is_array($payload['message'])) {
            foreach (['msg', 'error', 'result'] as $key) {
                if (!empty($payload['message'][$key]) && is_string($payload['message'][$key])) {
                    $candidates[] = $payload['message'][$key];
                }
            }

            if (empty($candidates)) {
                $json = json_encode($payload['message'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if ($json && $json !== '[]' && $json !== '{}') {
                    $candidates[] = $json;
                }
            }
        }
    }

    if (isset($payload['error']) && $payload['error'] !== '') {
        $candidates[] = $payload['error'];
    }

    foreach ($candidates as $candidate) {
        if (is_string($candidate)) {
            $candidate = trim($candidate);
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }

    $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return $json ?: 'API error';
}

function isNginxHelperPurgeIssue($message) {
    if (!is_string($message) || $message === '') {
        return false;
    }

    return stripos($message, 'Nginx Helper') !== false
        || stripos($message, 'Cache clearing failed') !== false;
}

function extractPanelUrl($infoUrl) {
    if (!$infoUrl) {
        return null;
    }

    $parts = parse_url($infoUrl);
    if (!$parts || empty($parts['host'])) {
        return preg_replace('#/(login|apsess_).*#', '', $infoUrl);
    }

    $scheme = $parts['scheme'] ?? 'https';
    $host = $parts['host'];
    $port = isset($parts['port']) ? ':' . $parts['port'] : '';

    return $scheme . '://' . $host . $port;
}

function normalizeDomainForMatch($domain) {
    $domain = strtolower(trim($domain));
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = preg_replace('#^www\.#', '', $domain);
    $domain = preg_replace('#/.*$#', '', $domain);
    $domain = preg_replace('#:.*$#', '', $domain);
    return trim($domain, '.');
}

function resolveDomainIp($domain) {
    $domain = trim($domain);
    if ($domain === '') {
        return null;
    }

    $dohIp = resolveDomainIpDoH($domain);
    if ($dohIp) {
        return $dohIp;
    }

    $records = dns_get_record($domain, DNS_A);
    if (!empty($records)) {
        return $records[0]['ip'] ?? null;
    }

    $ip = gethostbyname($domain);
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : null;
}

function resolveDomainIpFromCloudflare($cloudflareApi, $domain) {
    try {
        $zones = $cloudflareApi->searchZones($domain, 1, 1);
        if (empty($zones['result'])) {
            return null;
        }

        $zone = null;
        foreach ($zones['result'] as $item) {
            if (($item['name'] ?? '') === $domain) {
                $zone = $item;
                break;
            }
        }
        if (!$zone) {
            $zone = $zones['result'][0];
        }

        $zoneId = $zone['id'] ?? null;
        if (!$zoneId) {
            return null;
        }

        $dnsRecords = $cloudflareApi->getDNSRecords($zoneId, ['per_page' => 100]);
        $records = $dnsRecords['result'] ?? [];
        foreach ($records as $record) {
            if (($record['type'] ?? '') === 'A' && ($record['name'] ?? '') === $domain) {
                return $record['content'] ?? null;
            }
        }
    } catch (Throwable $e) {
        appendDomainActionLog('Cloudflare DNS resolve failed', [
            'domain' => $domain,
            'error' => $e->getMessage()
        ]);
    }

    return null;
}

function resolveDomainIpDoH($domain) {
    $url = 'https://cloudflare-dns.com/dns-query?name=' . urlencode($domain) . '&type=A';
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Accept: application/dns-json',
        'User-Agent: domain-actions/1.0'
    ]);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $response = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($err) {
        appendDomainActionLog('DoH resolve failed', [
            'domain' => $domain,
            'error' => $err
        ]);
        return null;
    }

    $json = json_decode($response, true);
    if (!is_array($json) || empty($json['Answer'])) {
        return null;
    }

    foreach ($json['Answer'] as $answer) {
        if (($answer['type'] ?? null) === 1 && !empty($answer['data'])) {
            return $answer['data'];
        }
    }

    return null;
}

function findVpsByIp($vpsList, $ip) {
    foreach ($vpsList as $vps) {
        if (!empty($vps['ip']) && $vps['ip'] === $ip) {
            return $vps;
        }
    }

    return null;
}

function findSiteIdByDomain($panelUrl, $apiSecret, $domain) {
    $normalizedTarget = normalizeDomainForMatch($domain);
    $response = aapanelV2Request($panelUrl, $apiSecret, 'data?action=getData', [
        'p' => 1,
        'limit' => 200,
        'table' => 'sites',
        'search' => $domain,
        'order' => '',
        'type' => -1
    ]);

    if (empty($response['success'])) {
        return ['success' => false, 'error' => $response['error'] ?? 'Request failed'];
    }

    $payload = $response['data'] ?? [];
    $status = $payload['status'] ?? null;
    if ($status !== 0 && $status !== true) {
        return ['success' => false, 'error' => $payload['msg'] ?? 'API error'];
    }

    $items = $payload['message']['data'] ?? [];
    foreach ($items as $item) {
        $itemName = normalizeDomainForMatch($item['name'] ?? '');
        if ($itemName !== '' && $itemName === $normalizedTarget) {
            return ['success' => true, 'site_id' => $item['id'] ?? null];
        }
    }

    $page = 1;
    $limit = 200;
    while ($page <= 20) {
        $response = aapanelV2Request($panelUrl, $apiSecret, 'data?action=getData', [
            'p' => $page,
            'limit' => $limit,
            'table' => 'sites',
            'search' => '',
            'order' => '',
            'type' => -1
        ]);

        if (empty($response['success'])) {
            return ['success' => false, 'error' => $response['error'] ?? 'Request failed'];
        }

        $payload = $response['data'] ?? [];
        $status = $payload['status'] ?? null;
        if ($status !== 0 && $status !== true) {
            return ['success' => false, 'error' => $payload['msg'] ?? 'API error'];
        }

        $items = $payload['message']['data'] ?? [];
        if (empty($items)) {
            break;
        }

        foreach ($items as $item) {
            $siteId = $item['id'] ?? null;
            if (!$siteId) {
                continue;
            }

            $domainsResponse = aapanelV2Request($panelUrl, $apiSecret, 'data?action=getData&table=domain', [
                'list' => true,
                'search' => $siteId
            ]);

            if (empty($domainsResponse['success'])) {
                continue;
            }

            $domainPayload = $domainsResponse['data'] ?? [];
            $domainStatus = $domainPayload['status'] ?? null;
            if ($domainStatus !== 0 && $domainStatus !== true) {
                continue;
            }

            $domainItems = $domainPayload['message'] ?? [];
            foreach ($domainItems as $domainItem) {
                $itemName = normalizeDomainForMatch($domainItem['name'] ?? '');
                if ($itemName !== '' && $itemName === $normalizedTarget) {
                    return ['success' => true, 'site_id' => $siteId];
                }
            }
        }

        if (count($items) < $limit) {
            break;
        }
        $page++;
    }

    $domainSearch = aapanelV2Request($panelUrl, $apiSecret, 'data?action=getData&table=domain', [
        'list' => true,
        'search' => $domain
    ]);

    if (!empty($domainSearch['success'])) {
        $domainPayload = $domainSearch['data'] ?? [];
        $domainStatus = $domainPayload['status'] ?? null;
        if ($domainStatus === 0 || $domainStatus === true) {
            $domainItems = $domainPayload['message'] ?? [];
            foreach ($domainItems as $domainItem) {
                $itemName = normalizeDomainForMatch($domainItem['name'] ?? '');
                if ($itemName !== '' && $itemName === $normalizedTarget) {
                    $siteId = $domainItem['pid'] ?? ($domainItem['site_id'] ?? null);
                    if ($siteId) {
                        return ['success' => true, 'site_id' => $siteId];
                    }
                }
            }
        }
    }

    return ['success' => false, 'error' => 'Không tìm thấy site trong VPS'];
}

function purgeVpsCacheBySiteId($panelUrl, $apiSecret, $siteId) {
    $response = aapanelV2Request($panelUrl, $apiSecret, 'site?action=purge_all_cache', [
        's_id' => $siteId
    ]);

    if (empty($response['success'])) {
        return ['success' => false, 'error' => $response['error'] ?? 'Request failed'];
    }

    $payload = $response['data'] ?? [];
    $status = $payload['status'] ?? null;
    if ($status === 0 || $status === true) {
        $message = 'OK';
        if (isset($payload['message']['result']) && is_string($payload['message']['result'])) {
            $message = $payload['message']['result'];
        } elseif (isset($payload['message']) && is_string($payload['message'])) {
            $message = $payload['message'];
        }
        return ['success' => true, 'message' => $message];
    }

    return [
        'success' => false,
        'error' => extractAapanelErrorMessage($payload),
        'raw_payload' => $payload
    ];
}

$result = null;
$error  = null;
$api    = null;

try {
    $api = new CloudflareAPI();
} catch (Throwable $e) {
    $error = 'Không thể khởi tạo CloudflareAPI: ' . htmlspecialchars($e->getMessage());
}

// ─── Xử lý POST ────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $api) {
    $rawInput    = trim($_POST['domains'] ?? '');
    $doSetSSL    = isset($_POST['do_ssl']);
    $doClearCache = isset($_POST['do_cache']);
    $doVpsCache  = isset($_POST['do_vps_cache']);

    // Parse & validate danh sách domain
    $domains = [];
    $lines   = preg_split('/[\r\n,]+/', $rawInput);
    foreach ($lines as $line) {
        $d = strtolower(trim($line));
        $d = preg_replace('#^https?://#', '', $d);
        $d = preg_replace('#^www\.#', '', $d);
        $d = explode('/', $d)[0];
        if (
            $d !== '' &&
            preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)+$/', $d)
        ) {
            $domains[] = $d;
        }
    }
    $domains = array_unique($domains);

    if (empty($domains)) {
        $error = 'Không có domain hợp lệ nào được nhập.';
    } elseif (!$doSetSSL && !$doClearCache && !$doVpsCache) {
        $error = 'Vui lòng chọn ít nhất một tác vụ cần thực hiện.';
    } else {
        $result = [];
        $vpsList = $doVpsCache ? loadVpsListFromJson(__DIR__ . '/vps.json') : [];
        foreach ($domains as $domain) {
            $row = [
                'domain'  => $domain,
                'zone_id' => null,
                'ssl'     => null,
                'cache'   => null,
                'vps_cache' => null,
                'error'   => null,
            ];

            // Tìm Zone ID theo domain name
            $zoneId = $api->getZoneIdByDomain($domain);

            if (!$zoneId) {
                $row['error'] = 'Không tìm thấy zone trong tài khoản Cloudflare';
                $result[] = $row;
                continue;
            }

            $row['zone_id'] = $zoneId;

            // SSL/TLS Encryption Mode → full
            if ($doSetSSL) {
                try {
                    $sslResp     = $api->setSSLMode($zoneId, 'full');
                    $row['ssl']  = (!empty($sslResp['success'])) ? 'OK' : 'Lỗi: ' . ($sslResp['errors'][0]['message'] ?? 'unknown');
                } catch (Throwable $e) {
                    $row['ssl'] = 'Lỗi: ' . $e->getMessage();
                }
            }

            // Purge Everything
            if ($doClearCache) {
                try {
                    $cacheResp    = $api->purgeCache($zoneId);
                    $row['cache'] = (!empty($cacheResp['success'])) ? 'OK' : 'Lỗi: ' . ($cacheResp['errors'][0]['message'] ?? 'unknown');
                } catch (Throwable $e) {
                    $row['cache'] = 'Lỗi: ' . $e->getMessage();
                }
            }

            if ($doVpsCache) {
                $vpsMessage = null;
                $vpsError = null;

                $resolvedIp = resolveDomainIpFromCloudflare($api, $domain);
                $resolveSource = 'cloudflare';
                if (!$resolvedIp) {
                    $resolvedIp = resolveDomainIp($domain);
                    $resolveSource = $resolvedIp ? 'fallback' : 'none';
                }
                if (!$resolvedIp) {
                    $vpsError = 'Không lấy được IP từ domain';
                    appendDomainActionLog('VPS purge failed: resolve IP', [
                        'domain' => $domain,
                        'source' => $resolveSource
                    ]);
                } else {
                    $vps = findVpsByIp($vpsList, $resolvedIp);
                    if (!$vps) {
                        $vpsError = 'Không tìm thấy VPS theo IP domain';
                        appendDomainActionLog('VPS purge failed: VPS not found', [
                            'domain' => $domain,
                            'ip' => $resolvedIp,
                            'source' => $resolveSource
                        ]);
                    } else {
                        $vpsIp = $vps['ip'] ?? null;
                        $apiSecret = $vps['aapanel_keyapi'] ?? '';
                        $panelUrl = extractPanelUrl($vps['info'] ?? '');
                        if (!$apiSecret || !$panelUrl) {
                            $vpsError = 'Thiếu API secret hoặc panel URL';
                            appendDomainActionLog('VPS purge failed: missing credentials', [
                                'domain' => $domain,
                                'ip' => $resolvedIp,
                                'vps_ip' => $vpsIp,
                                'panel_url' => $panelUrl,
                                'has_secret' => $apiSecret !== ''
                            ]);
                        } else {
                            $siteLookup = findSiteIdByDomain($panelUrl, $apiSecret, $domain);
                            if (!$siteLookup['success']) {
                                $vpsError = $siteLookup['error'] ?? 'Không tìm thấy site';
                                appendDomainActionLog('VPS purge failed: site lookup', [
                                    'domain' => $domain,
                                    'ip' => $resolvedIp,
                                    'vps_ip' => $vpsIp,
                                    'panel_url' => $panelUrl,
                                    'error' => $vpsError
                                ]);
                            } else {
                                $siteId = $siteLookup['site_id'] ?? null;
                                if (!$siteId) {
                                    $vpsError = 'Không tìm thấy site ID';
                                    appendDomainActionLog('VPS purge failed: missing site ID', [
                                        'domain' => $domain,
                                        'ip' => $resolvedIp,
                                        'vps_ip' => $vpsIp,
                                        'panel_url' => $panelUrl
                                    ]);
                                } else {
                                    $purgeResult = purgeVpsCacheBySiteId($panelUrl, $apiSecret, $siteId);
                                    if (!empty($purgeResult['success'])) {
                                        $vpsMessage = 'OK (' . ($vps['ip'] ?? 'VPS') . ')';
                                    } else {
                                        $vpsError = $purgeResult['error'] ?? 'Lỗi purge';
                                        if (isNginxHelperPurgeIssue($vpsError)) {
                                            $vpsMessage = 'Cảnh báo: Nginx Helper chưa sẵn sàng';
                                            appendDomainActionLog('VPS purge warning: Nginx Helper issue', [
                                                'domain' => $domain,
                                                'ip' => $resolvedIp,
                                                'vps_ip' => $vpsIp,
                                                'panel_url' => $panelUrl,
                                                'site_id' => $siteId,
                                                'warning' => $vpsError,
                                                'raw_payload' => $purgeResult['raw_payload'] ?? null
                                            ]);
                                        } else {
                                            appendDomainActionLog('VPS purge failed: purge error', [
                                                'domain' => $domain,
                                                'ip' => $resolvedIp,
                                                'vps_ip' => $vpsIp,
                                                'panel_url' => $panelUrl,
                                                'site_id' => $siteId,
                                                'error' => $vpsError,
                                                'raw_payload' => $purgeResult['raw_payload'] ?? null
                                            ]);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                if ($vpsMessage) {
                    $row['vps_cache'] = $vpsMessage;
                } else {
                    $row['vps_cache'] = 'Lỗi: ' . ($vpsError ?? 'Không tìm thấy VPS có domain');
                }
            }

            $result[] = $row;
        }
    }
}

// ─── Thống kê ───────────────────────────────────────────────────────────────
$totalCount   = $result ? count($result) : 0;
$successCount = $result ? count(array_filter($result, fn($r) => $r['error'] === null)) : 0;
$failCount    = $totalCount - $successCount;

$logLines = file_exists($logFilePath)
    ? array_slice(file($logFilePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES), -200)
    : [];
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domain Actions – Cloudflare</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #0f172a; color: #ffffff; font-family: 'Segoe UI', sans-serif; }
        .card { background: #1e293b; border: 1px solid #334155; border-radius: 12px; }
        .card-header { background: #0f172a; border-bottom: 1px solid #334155; border-radius: 12px 12px 0 0 !important; }
        textarea { background: #0f172a !important; color: #ffffff !important; border-color: #475569 !important; resize: vertical; }
        textarea:focus { border-color: #f6821f !important; box-shadow: 0 0 0 3px rgba(246,130,31,.2) !important; }
        .btn-cf { background: #f6821f; border: none; color: #fff; font-weight: 600; }
        .btn-cf:hover { background: #e07010; color: #fff; }
        .table { color: #ffffff; }
        .table-dark { --bs-table-bg: #1e293b; --bs-table-border-color: #334155; }
        .zone-id { font-size: .72rem; color: #fff; font-family: monospace; }
        .form-text, .text-muted { color: #fff !important; }
        .form-check-input:checked { background-color: #f6821f; border-color: #f6821f; }
        #domainPreview { max-height: 130px; overflow-y: auto; }
        .domain-count { font-size: .8rem; color: #fff; }
        #processProgressWrap { display: none; }
        #processProgressBar { transition: width .35s ease; }
    </style>
</head>
<body>
<div class="container py-4">

    <?php if (file_exists(__DIR__ . '/includes/main_navigation.php')): ?>
    <?php $currentPage = 'domain_actions'; include __DIR__ . '/includes/main_navigation.php'; ?>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-9">

            <ul class="nav nav-tabs mb-4" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="actions-tab" data-bs-toggle="tab" data-bs-target="#actions-pane" type="button" role="tab">Thao tác</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="log-tab" data-bs-toggle="tab" data-bs-target="#log-pane" type="button" role="tab">Log</button>
                </li>
            </ul>

            <div class="tab-content">
            <div class="tab-pane fade show active" id="actions-pane" role="tabpanel" aria-labelledby="actions-tab">

            <!-- Header -->
            <div class="d-flex align-items-center gap-3 mb-4">
                <div style="background:#f6821f;width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-gear-fill fs-4 text-white"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold">Domain Actions</h4>
                    <small class="text-muted">Cập nhật cấu hình domain hàng loạt trên Cloudflare</small>
                </div>
            </div>

            <!-- Lỗi toàn cục -->
            <?php if ($error): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <!-- Kết quả -->
            <?php if ($result): ?>
            <div class="card mb-4">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <span class="fw-semibold">
                        <i class="bi bi-list-check me-2 text-warning"></i>Kết quả
                    </span>
                    <div class="d-flex gap-2">
                        <span class="badge bg-success"><?= $successCount ?> thành công</span>
                        <?php if ($failCount): ?>
                        <span class="badge bg-danger"><?= $failCount ?> lỗi</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-dark table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Domain</th>
                                    <th>SSL/TLS Full</th>
                                    <th>Clear Cache</th>
                                    <th>Purge VPS Cache</th>
                                    <th>Trạng thái</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($result as $i => $row): ?>
                                <tr>
                                    <td class="text-muted"><?= $i + 1 ?></td>
                                    <td>
                                        <strong><?= htmlspecialchars($row['domain']) ?></strong>
                                        <?php if ($row['zone_id']): ?>
                                        <div class="zone-id"><?= htmlspecialchars($row['zone_id']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['ssl'] === null): ?>
                                            <span class="text-muted">—</span>
                                        <?php elseif ($row['ssl'] === 'OK'): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Full</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger" title="<?= htmlspecialchars($row['ssl']) ?>">
                                                <i class="bi bi-x-lg me-1"></i>Lỗi
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['cache'] === null): ?>
                                            <span class="text-muted">—</span>
                                        <?php elseif ($row['cache'] === 'OK'): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Đã xóa</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger" title="<?= htmlspecialchars($row['cache']) ?>">
                                                <i class="bi bi-x-lg me-1"></i>Lỗi
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['vps_cache'] === null): ?>
                                            <span class="text-muted">—</span>
                                        <?php elseif ($row['vps_cache'] === 'OK'): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i>Đã xóa</span>
                                        <?php elseif (strpos($row['vps_cache'], 'OK') === 0): ?>
                                            <span class="badge bg-success"><i class="bi bi-check-lg me-1"></i><?= htmlspecialchars($row['vps_cache']) ?></span>
                                        <?php elseif (strpos($row['vps_cache'], 'Cảnh báo:') === 0): ?>
                                            <span class="badge bg-warning text-dark" title="<?= htmlspecialchars($row['vps_cache']) ?>">
                                                <i class="bi bi-exclamation-circle me-1"></i><?= htmlspecialchars($row['vps_cache']) ?>
                                            </span>
                                        <?php elseif (stripos($row['vps_cache'], 'Nginx Helper') !== false): ?>
                                            <span class="badge bg-warning text-dark" title="<?= htmlspecialchars($row['vps_cache']) ?>">
                                                <i class="bi bi-exclamation-circle me-1"></i>Thiếu Nginx Helper
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-danger" title="<?= htmlspecialchars($row['vps_cache']) ?>">
                                                <i class="bi bi-x-lg me-1"></i>Lỗi
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($row['error']): ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($row['error']) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Hoàn thành</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Form -->
            <div class="card">
                <div class="card-header py-3">
                    <span class="fw-semibold">
                        <i class="bi bi-list-ul me-2" style="color:#f6821f;"></i>Nhập danh sách domain
                    </span>
                </div>
                <div class="card-body">
                    <form method="POST" id="actionsForm">

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Danh sách domain
                                <span class="domain-count ms-2" id="domainCount"></span>
                            </label>
                            <textarea
                                name="domains"
                                id="domainsInput"
                                class="form-control"
                                rows="9"
                                placeholder="Mỗi domain một dòng hoặc phân cách bằng dấu phẩy:&#10;example.com&#10;mysite.net&#10;another-domain.org"><?= htmlspecialchars($_POST['domains'] ?? '') ?></textarea>
                            <div class="form-text">Hỗ trợ mọi định dạng: có/không có http://, www., đường dẫn — sẽ tự động làm sạch.</div>
                        </div>

                        <!-- Preview -->
                        <div id="domainPreview" class="mb-3 d-none">
                            <div class="form-label fw-semibold text-success">
                                <i class="bi bi-check2-all me-1"></i>Domain hợp lệ:
                            </div>
                            <div id="previewList" class="d-flex flex-wrap gap-1"></div>
                        </div>

                        <!-- Tác vụ cần thực hiện -->
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Tác vụ thực hiện</label>
                            <div class="d-flex flex-column gap-2 ps-1">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="do_ssl" id="doSSL"
                                           <?= (!isset($_POST['do_ssl']) || isset($_POST['do_ssl'])) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="doSSL">
                                        <strong>SSL/TLS Encryption Mode → Full</strong>
                                        <span class="text-muted ms-1">— Đặt chế độ mã hoá thành <span class="badge bg-success">full</span> cho từng zone</span>
                                    </label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="do_cache" id="doCache"
                                           <?= (!isset($_POST['do_cache']) || isset($_POST['do_cache'])) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="doCache">
                                        <strong>Clear Cache (Purge Everything)</strong>
                                        <span class="text-muted ms-1">— Xóa toàn bộ cache của zone trên Cloudflare</span>
                                    </label>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="do_vps_cache" id="doVpsCache"
                                           <?= isset($_POST['do_vps_cache']) ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="doVpsCache">
                                        <strong>Purge Cache trên VPS (aaPanel)</strong>
                                        <span class="text-muted ms-1">— Xóa cache site trên VPS theo aaPanel API v2</span>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex gap-2 align-items-center">
                            <button type="submit" class="btn btn-cf px-4" id="submitBtn">
                                <i class="bi bi-lightning-charge me-2"></i>Thực hiện
                            </button>
                            <button type="button" class="btn btn-outline-secondary"
                                    onclick="document.getElementById('domainsInput').value=''; updatePreview();">
                                <i class="bi bi-trash me-1"></i>Xóa
                            </button>
                        </div>

                        <div class="mt-3" id="processProgressWrap">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <small class="text-muted">Tiến trình xử lý</small>
                                <small class="text-muted" id="processProgressText">0%</small>
                            </div>
                            <div class="progress" role="progressbar" aria-label="Tiến trình xử lý domain" aria-valuemin="0" aria-valuemax="100" style="height: 10px; background:#0b1220;">
                                <div id="processProgressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%; background:#f6821f;"></div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

        </div>
            <div class="tab-pane fade" id="log-pane" role="tabpanel" aria-labelledby="log-tab">
                <div class="card">
                    <div class="card-header py-3 d-flex align-items-center justify-content-between">
                        <span class="fw-semibold">
                            <i class="bi bi-journal-text me-2" style="color:#f6821f;"></i>Log gần đây
                        </span>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge bg-secondary"><?= count($logLines) ?> dòng</span>
                            <form method="POST" class="mb-0">
                                <input type="hidden" name="action" value="clear_log">
                                <button type="submit" class="btn btn-outline-danger btn-sm">
                                    <i class="bi bi-trash me-1"></i>Xóa log
                                </button>
                            </form>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if ($logCleared): ?>
                            <div class="alert alert-success">Đã xóa log.</div>
                        <?php endif; ?>
                        <?php if (empty($logLines)): ?>
                            <div class="text-muted">Chưa có log.</div>
                        <?php else: ?>
                            <pre class="mb-0" style="white-space: pre-wrap; word-break: break-word; background:#0f172a; color:#e2e8f0; padding:12px; border-radius:8px; border:1px solid #334155; max-height:360px; overflow:auto;"><?php
                                echo htmlspecialchars(implode("\n", $logLines));
                            ?></pre>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            </div>
    </div>
</div>

<script>
function parseDomains(raw) {
    const re = /^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)+$/;
    const seen = new Set();
    const valid = [];
    for (const line of raw.split(/[\r\n,]+/)) {
        let d = line.trim().toLowerCase()
            .replace(/^https?:\/\//, '')
            .replace(/^www\./, '')
            .split('/')[0];
        if (d && re.test(d) && !seen.has(d)) {
            seen.add(d);
            valid.push(d);
        }
    }
    return valid;
}

function updatePreview() {
    const raw     = document.getElementById('domainsInput').value;
    const domains = parseDomains(raw);
    const countEl = document.getElementById('domainCount');
    const preview = document.getElementById('domainPreview');
    const list    = document.getElementById('previewList');

    if (!domains.length) {
        countEl.textContent = '';
        preview.classList.add('d-none');
        return;
    }
    countEl.textContent = '(' + domains.length + ' domain hợp lệ)';
    list.innerHTML = domains.map(d =>
        `<span class="badge" style="background:#1e3a5f;color:#fff;font-size:.78rem;">${d}</span>`
    ).join('');
    preview.classList.remove('d-none');
}

document.getElementById('domainsInput').addEventListener('input', updatePreview);

let progressTimer = null;

function setProcessingProgress(percent) {
    const value = Math.max(0, Math.min(100, Number(percent) || 0));
    const bar = document.getElementById('processProgressBar');
    const text = document.getElementById('processProgressText');
    bar.style.width = value + '%';
    text.textContent = value + '%';
    bar.setAttribute('aria-valuenow', String(value));
}

function startProcessingProgress(estimatedDomains) {
    const wrap = document.getElementById('processProgressWrap');
    wrap.style.display = 'block';
    setProcessingProgress(2);

    const steps = Math.max(estimatedDomains || 1, 1);
    const targetMax = 95;
    const stepValue = Math.max(1, Math.floor(targetMax / (steps * 2)));

    if (progressTimer) {
        clearInterval(progressTimer);
    }

    progressTimer = setInterval(() => {
        const current = parseInt(document.getElementById('processProgressBar').style.width, 10) || 0;
        if (current >= targetMax) {
            clearInterval(progressTimer);
            progressTimer = null;
            return;
        }
        setProcessingProgress(Math.min(targetMax, current + stepValue));
    }, 450);
}

document.getElementById('actionsForm').addEventListener('submit', function () {
    const btn = document.getElementById('submitBtn');
    const domains = parseDomains(document.getElementById('domainsInput').value || '');

    startProcessingProgress(domains.length);

    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang xử lý...';
});

updatePreview();
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
