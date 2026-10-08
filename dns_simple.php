<?php
/**
 * DNS Records Manager — Tạo hàng loạt A & CNAME Records
 */

require_once 'config.php';
require_once 'CloudflareAPI.php';

// ─── Helpers ──────────────────────────────────────────────────────────────────

/**
 * Tìm zone ID cho một domain trong Cloudflare account.
 * Dùng CF name= filter trước (không giới hạn 100 zones),
 * sau đó thử parent zones nếu cần.
 */
function findZoneForDomain(CloudflareAPI $api, string $domain): ?array {
    $domain = strtolower(trim($domain));

    // Tập hợp các zone name cần tìm: domain gốc + các parent
    $candidates = [$domain];
    $parts = explode('.', $domain);
    for ($i = 1; $i < count($parts) - 1; $i++) {
        $candidates[] = implode('.', array_slice($parts, $i));
    }

    foreach ($candidates as $candidate) {
        // Gọi CF API với name= filter — chính xác, không bị giới hạn trang
        $res = $api->searchZones($candidate, 1, 100, null, null, false);
        if (!empty($res['result'])) {
            foreach ($res['result'] as $zone) {
                if (strtolower($zone['name']) === $candidate) {
                    return ['id' => $zone['id'], 'name' => $zone['name']];
                }
            }
        }
    }

    return null;
}

/**
 * Upsert một DNS record (create nếu chưa có, update nếu đã có).
 */
function upsertRecord(CloudflareAPI $api, string $zoneId, array $data): array {
    $existing = $api->listDNSRecords($zoneId);
    foreach ($existing['result'] ?? [] as $rec) {
        if ($rec['type'] === $data['type'] && $rec['name'] === $data['name']) {
            $r = $api->updateDNSRecordAdvanced($zoneId, $rec['id'], $data);
            return ['action' => 'updated', 'success' => (bool)($r['success'] ?? false), 'id' => $rec['id']];
        }
    }
    $r = $api->createDNSRecordAdvanced($zoneId, $data);
    return ['action' => 'created', 'success' => (bool)($r['success'] ?? false), 'id' => $r['result']['id'] ?? null];
}

/**
 * Tách & làm sạch danh sách domain từ textarea.
 */
function parseDomainList(string $raw): array {
    $lines = preg_split('/[\r\n,;]+/', $raw);
    $out   = [];
    foreach ($lines as $line) {
        // strip protocol, www prefix, paths
        $d = trim($line);
        $d = preg_replace('#^https?://#i', '', $d);
        $d = preg_replace('#/.*$#', '', $d);
        $d = strtolower($d);
        if ($d !== '' && strpos($d, '.') !== false) {
            $out[] = $d;
        }
    }
    return array_unique($out);
}

/**
 * Xử lý deploy cho 1 domain.
 */
function deploySingleDomain(CloudflareAPI $api, string $domain, string $ip, bool $proxiedA, bool $proxiedCNAME, bool $alwaysHttps): array {
    $domain = strtolower(trim($domain));
    if ($domain === '') {
        throw new Exception('Domain rỗng');
    }

    $item = ['domain' => $domain, 'success' => false, 'zone' => null, 'zone_id' => null, 'records' => [], 'error' => null];

    $zone = findZoneForDomain($api, $domain);
    if (!$zone) {
        $item['error'] = 'Domain không tìm thấy trong Cloudflare account';
        return $item;
    }

    $item['zone'] = $zone['name'];
    $item['zone_id'] = $zone['id'];
    $zoneId   = $zone['id'];
    $zoneName = $zone['name'];

    // A record: @ (root)
    $aName   = $zoneName;
    $aResult = upsertRecord($api, $zoneId, [
        'type'    => 'A',
        'name'    => $aName,
        'content' => $ip,
        'ttl'     => 1,
        'proxied' => $proxiedA,
    ]);
    $item['records'][] = array_merge(['type' => 'A', 'name' => '@', 'content' => $ip, 'proxied' => $proxiedA], $aResult);

    // CNAME record: www → zone
    $wwwName   = 'www.' . $zoneName;
    $wwwResult = upsertRecord($api, $zoneId, [
        'type'    => 'CNAME',
        'name'    => $wwwName,
        'content' => $zoneName,
        'ttl'     => 1,
        'proxied' => $proxiedCNAME,
    ]);
    $item['records'][] = array_merge(['type' => 'CNAME', 'name' => 'www', 'content' => $zoneName, 'proxied' => $proxiedCNAME], $wwwResult);

    // Always Use HTTPS
    if ($alwaysHttps) {
        $hr = $api->setAlwaysUseHTTPS($zoneId, true);
        $item['https'] = ['enabled' => true, 'success' => (bool)($hr['success'] ?? false)];
    } else {
        $item['https'] = ['enabled' => false, 'success' => true];
    }

    $item['success'] = (bool)($aResult['success'] ?? false) || (bool)($wwwResult['success'] ?? false);
    if (!$item['success']) {
        $item['error'] = 'Không tạo/cập nhật được A hoặc CNAME record';
    }

    return $item;
}

// ─── AJAX Handler ─────────────────────────────────────────────────────────────

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    ini_set('display_errors', '0');
    ini_set('html_errors', '0');
    if (ob_get_level()) ob_clean();
    header('Content-Type: application/json');
    if (ob_get_level()) ob_clean();

    try {
        $action = $_POST['action'] ?? '';
        $api    = new CloudflareAPI();

        switch ($action) {

            // ── Tạo/Cập nhật 1 domain ─────────────────────────────────────────
            case 'deploy_domain': {
                $domain        = trim($_POST['domain'] ?? '');
                $ip            = trim($_POST['ip_address'] ?? '');
                $proxiedA      = ($_POST['proxied_a'] ?? '0') === '1';
                $proxiedCNAME  = ($_POST['proxied_cname'] ?? '0') === '1';
                $alwaysHttps   = ($_POST['always_https'] ?? '1') === '1';

                if ($domain === '') throw new Exception('Thiếu domain');
                if ($ip === '') throw new Exception('Vui lòng nhập IP address');
                if (!filter_var($ip, FILTER_VALIDATE_IP)) throw new Exception('IP address không hợp lệ: ' . htmlspecialchars($ip));

                $item = deploySingleDomain($api, $domain, $ip, $proxiedA, $proxiedCNAME, $alwaysHttps);
                echo json_encode(['success' => true, 'result' => $item]);
                break;
            }

            // ── Tạo A + CNAME cho danh sách domain ───────────────────────────
            case 'bulk_deploy': {
                $rawDomains = trim($_POST['domains'] ?? '');
                $ip            = trim($_POST['ip_address'] ?? '');
                $proxiedA      = ($_POST['proxied_a']     ?? '0') === '1';
                $proxiedCNAME  = ($_POST['proxied_cname'] ?? '0') === '1';
                $alwaysHttps   = ($_POST['always_https']  ?? '1') === '1';

                if ($rawDomains === '') throw new Exception('Vui lòng nhập danh sách domain');
                if ($ip === '')         throw new Exception('Vui lòng nhập IP address');
                if (!filter_var($ip, FILTER_VALIDATE_IP)) throw new Exception('IP address không hợp lệ: ' . htmlspecialchars($ip));

                $domains    = parseDomainList($rawDomains);
                if (empty($domains)) throw new Exception('Không có domain hợp lệ nào');

                $results      = [];
                $totalSuccess = 0;
                $totalError   = 0;

                foreach ($domains as $domain) {
                    $item = deploySingleDomain($api, $domain, $ip, $proxiedA, $proxiedCNAME, $alwaysHttps);
                    $results[] = $item;
                    if ($item['success']) $totalSuccess++; else $totalError++;
                }

                echo json_encode([
                    'success' => true,
                    'results' => $results,
                    'stats'   => [
                        'total'   => count($domains),
                        'success' => $totalSuccess,
                        'error'   => $totalError,
                    ],
                ]);
                break;
            }

            // ── Bật Always Use HTTPS cho một domain ──────────────────────────
            case 'enable_always_https': {
                $domain = strtolower(trim($_POST['domain'] ?? ''));
                if ($domain === '') throw new Exception('Thiếu domain');

                $item = [
                    'domain' => $domain,
                    'success' => false,
                    'zone' => null,
                    'https' => ['enabled' => true, 'success' => false],
                    'error' => null,
                ];
                $zone = findZoneForDomain($api, $domain);
                if (!$zone) {
                    $item['error'] = 'Domain không tìm thấy trong Cloudflare account';
                } else {
                    $item['zone'] = $zone['name'];
                    $item['zone_id'] = $zone['id'];
                    $httpsResult = $api->setAlwaysUseHTTPS($zone['id'], true);
                    $item['https']['success'] = (bool)($httpsResult['success'] ?? false);
                    $item['success'] = $item['https']['success'];
                    if (!$item['success']) {
                        $item['error'] = $httpsResult['errors'][0]['message'] ?? 'Không thể bật Always Use HTTPS';
                    }
                }

                echo json_encode(['success' => true, 'result' => $item]);
                break;
            }

            // ── Xóa A/CNAME theo danh sách domain ───────────────────────────
            case 'bulk_delete_by_domains': {
                $rawDomains = trim($_POST['domains'] ?? '');
                if ($rawDomains === '') throw new Exception('Vui lòng nhập danh sách domain');

                $domains = parseDomainList($rawDomains);
                if (empty($domains)) throw new Exception('Không có domain hợp lệ nào');

                $results = [];
                $totalDeleted = 0;
                $totalSuccess = 0;
                $totalError = 0;

                foreach ($domains as $domain) {
                    $item = [
                        'domain' => $domain,
                        'success' => false,
                        'zone' => null,
                        'zone_id' => null,
                        'deleted' => 0,
                        'targets' => [],
                        'error' => null,
                    ];

                    $zone = findZoneForDomain($api, $domain);
                    if (!$zone) {
                        $item['error'] = 'Domain không tìm thấy trong Cloudflare account';
                        $results[] = $item;
                        $totalError++;
                        continue;
                    }

                    $zoneId = $zone['id'];
                    $zoneName = $zone['name'];
                    $item['zone'] = $zoneName;
                    $item['zone_id'] = $zoneId;

                    $listRes = $api->listDNSRecords($zoneId);
                    $records = $listRes['result'] ?? [];

                    foreach ($records as $record) {
                        $type = strtoupper((string)($record['type'] ?? ''));
                        $name = strtolower((string)($record['name'] ?? ''));
                        $isTarget =
                            ($type === 'A' && $name === strtolower($zoneName)) ||
                            ($type === 'CNAME' && $name === strtolower('www.' . $zoneName));

                        if (!$isTarget) continue;

                        $delRes = $api->deleteDNSRecord($zoneId, $record['id']);
                        $ok = (bool)($delRes['success'] ?? false);
                        $item['targets'][] = [
                            'id' => $record['id'],
                            'type' => $type,
                            'name' => $record['name'] ?? '',
                            'success' => $ok,
                            'error' => $ok ? null : ($delRes['errors'][0]['message'] ?? 'Delete failed'),
                        ];

                        if ($ok) {
                            $item['deleted']++;
                            $totalDeleted++;
                        }
                    }

                    if ($item['deleted'] > 0) {
                        $item['success'] = true;
                        $totalSuccess++;
                    } else {
                        // Không có target để xóa cũng coi là success mềm, tránh fail cả batch.
                        $item['success'] = true;
                        $item['error'] = 'Không có A/CNAME (@, www) để xóa';
                        $totalSuccess++;
                    }

                    $results[] = $item;
                }

                echo json_encode([
                    'success' => true,
                    'results' => $results,
                    'stats' => [
                        'total' => count($domains),
                        'success' => $totalSuccess,
                        'error' => $totalError,
                        'deleted' => $totalDeleted,
                    ],
                ]);
                break;
            }

            // ── Check domain status ───────────────────────────────────────────
            case 'check_domain': {
                $domain = strtolower(trim($_POST['domain'] ?? ''));
                if ($domain === '') throw new Exception('Vui lòng nhập domain');

                $zone = findZoneForDomain($api, $domain);
                if (!$zone) {
                    echo json_encode(['success' => true, 'found' => false, 'domain' => $domain]);
                    break;
                }

                $recs = $api->listDNSRecords($zone['id']);
                echo json_encode([
                    'success' => true,
                    'found'   => true,
                    'domain'  => $domain,
                    'zone'    => $zone,
                    'records' => array_values(array_filter(
                        $recs['result'] ?? [],
                        fn($r) => in_array($r['type'], ['A','CNAME','MX','TXT','NS'])
                    )),
                ]);
                break;
            }

            // ── Xóa 1 DNS record ─────────────────────────────────────────────
            case 'delete_dns_record': {
                $zoneId = trim($_POST['zone_id'] ?? '');
                $recordId = trim($_POST['record_id'] ?? '');

                if ($zoneId === '' || $recordId === '') {
                    throw new Exception('Thiếu zone_id hoặc record_id');
                }

                // Chỉ cho phép xóa A/CNAME để tránh xóa nhầm NS/MX/TXT quan trọng.
                $allRecords = $api->listDNSRecords($zoneId);
                $targetRecord = null;
                foreach (($allRecords['result'] ?? []) as $record) {
                    if (($record['id'] ?? '') === $recordId) {
                        $targetRecord = $record;
                        break;
                    }
                }

                if (!$targetRecord) {
                    throw new Exception('Không tìm thấy DNS record cần xóa trong zone');
                }

                $recordType = strtoupper((string)($targetRecord['type'] ?? ''));
                if (!in_array($recordType, ['A', 'CNAME'], true)) {
                    throw new Exception('Chỉ được phép xóa record loại A/CNAME. Record hiện tại: ' . $recordType);
                }

                $r = $api->deleteDNSRecord($zoneId, $recordId);
                if (!($r['success'] ?? false)) {
                    $err = $r['errors'][0]['message'] ?? 'Không thể xóa DNS record';
                    throw new Exception($err);
                }

                echo json_encode([
                    'success' => true,
                    'message' => 'Đã xóa DNS record thành công',
                    'record_id' => $recordId,
                ]);
                break;
            }

            default:
                throw new Exception('Unknown action: ' . htmlspecialchars($action));
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DNS Records Manager — Bulk Deploy</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/sidebar-navigation.css" rel="stylesheet">
    <style>
        :root {
            --cf-orange : #F48120;
            --cf-blue   : #0051C3;
            --dark-bg   : #0d1117;
            --card-bg   : #161b22;
            --border    : #30363d;
            --text       : #c9d1d9;
            --text-muted : #8b949e;
            --green      : #3fb950;
            --red        : #f85149;
            --yellow     : #e3b341;
        }
        body          { background: var(--dark-bg); color: var(--text); }
        .page-header  { border-bottom: 2px solid var(--cf-orange); margin-bottom: 2rem; padding-bottom: 1rem; }
        .page-header h1 { color: var(--cf-orange); font-weight: 700; }
        .card-dark    { background: var(--card-bg); border: 1px solid var(--border); border-radius: 8px; }
        .form-control, .form-select {
            background: #0d1117; color: var(--text); border-color: var(--border);
        }
        .form-control:focus, .form-select:focus {
            background: #0d1117; color: var(--text); border-color: var(--cf-orange);
            box-shadow: 0 0 0 .2rem rgba(244,129,32,.25);
        }
        .form-control::placeholder { color: var(--text-muted); }
        .form-label   { color: var(--text); font-weight: 600; }
        .form-text    { color: var(--text-muted); }
        .badge-a      { background: #1a472a; color: #3fb950; border: 1px solid #3fb950; }
        .badge-cname  { background: #0c2a4a; color: #58a6ff; border: 1px solid #58a6ff; }
        .nav-tabs .nav-link { color: var(--text-muted); border-color: transparent; }
        .nav-tabs .nav-link.active {
            background: var(--card-bg); color: var(--cf-orange);
            border-color: var(--border) var(--border) var(--card-bg);
        }
        .nav-tabs { border-color: var(--border); }
        .domain-badge { display: inline-block; margin: 2px 4px 2px 0; padding: 2px 8px;
                        border-radius: 4px; font-size: .8rem; }
        #deployResult { max-height: 520px; overflow-y: auto; }
        .rec-row-ok   { background: rgba(63,185,80,.06); }
        .rec-row-fail { background: rgba(248,81,73,.06); }
        #domainCount  { font-size: .85rem; color: var(--text-muted); margin-top: .3rem; }
        .info-panel {
            background: rgba(244,129,32,.08);
            border: 1px solid rgba(244,129,32,.3);
            border-radius: 8px; padding: 1rem 1.25rem;
        }
        .info-panel .label { font-size: .8rem; color: var(--text-muted); text-transform: uppercase; letter-spacing: .05em; }
        .info-panel .value { font-size: 1.1rem; font-weight: 700; color: var(--cf-orange); font-family: monospace; }
    </style>
</head>
<body>
<?php
$currentPage = 'dns-simple';
include 'includes/main_navigation.php';
?>

<div class="main-wrapper">
<div class="container-fluid mt-4" style="max-width:1200px;">

    <!-- Header -->
    <div class="page-header">
        <h1><i class="fas fa-satellite-dish me-2"></i>DNS Records Manager</h1>
        <p class="text-muted mb-0">Tạo hàng loạt A Record (@) và CNAME Record (www) cho các zone Cloudflare</p>
    </div>

    <!-- Tabs -->
    <ul class="nav nav-tabs mb-0" id="mainTabs">
        <li class="nav-item">
            <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-deploy" type="button">
                <i class="fas fa-rocket me-1"></i>Tạo DNS Records (Bulk)
            </button>
        </li>
        <li class="nav-item">
            <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-check" type="button">
                <i class="fas fa-search me-1"></i>Kiểm tra Domain
            </button>
        </li>
    </ul>

    <div class="tab-content card-dark p-4" style="border-top: none; border-radius: 0 0 8px 8px;">

        <!-- ═══ TAB: BULK DEPLOY ═══════════════════════════════════════════════ -->
        <div class="tab-pane fade show active" id="tab-deploy">

            <!-- Deployment Parameters info -->
            <div class="info-panel mb-4">
                <div class="row g-3">
                    <div class="col-sm-6 col-md-3">
                        <div class="label">Record 1</div>
                        <div class="value"><span class="badge badge-a me-1">A</span> @ → IP</div>
                        <small class="form-text">Root domain → Target IP</small>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="label">Record 2</div>
                        <div class="value"><span class="badge badge-cname me-1">CNAME</span> www → domain</div>
                        <small class="form-text">www subdomain → primary domain</small>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="label">Hành động</div>
                        <div class="value" style="font-size:.95rem;">Create / Update</div>
                        <small class="form-text">Upsert — tự động phát hiện</small>
                    </div>
                    <div class="col-sm-6 col-md-3">
                        <div class="label">Phạm vi</div>
                        <div class="value" style="font-size:.95rem;">Nhiều domain</div>
                        <small class="form-text">Xử lý tuần tự từng domain</small>
                    </div>
                </div>
            </div>

            <form id="deployForm">
                <div class="row g-4">

                    <!-- Domain list -->
                    <div class="col-lg-7">
                        <label class="form-label" for="domainInput">
                            <i class="fas fa-list me-1"></i>Danh sách Domain
                            <span class="badge bg-secondary ms-1" id="domainCountBadge">0</span>
                        </label>
                        <textarea class="form-control font-monospace" id="domainInput" rows="12"
                            placeholder="example.com&#10;shopsite.net&#10;mybrand.vn&#10;&#10;(mỗi domain một dòng)"></textarea>
                        <div id="domainCount" class="mt-1"></div>
                        <div class="mt-2 d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="cleanDomains()">
                                <i class="fas fa-broom me-1"></i>Làm sạch
                            </button>
                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="clearDomains()">
                                <i class="fas fa-times me-1"></i>Xóa hết
                            </button>
                        </div>
                    </div>

                    <!-- Settings -->
                    <div class="col-lg-5">
                        <label class="form-label" for="ipInput">
                            <i class="fas fa-map-marker-alt me-1"></i>Target IP Address
                            <span class="text-danger">*</span>
                        </label>
                        <input type="text" class="form-control font-monospace mb-1"
                               id="ipInput" placeholder="103.213.216.170" required>
                        <small class="form-text">IP cho tất cả A records (@)</small>

                        <hr style="border-color:var(--border); margin: 1.25rem 0;">

                        <!-- Proxy status per record -->
                        <p class="mb-2 fw-bold" style="font-size:.82rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.04em;">
                            <i class="fas fa-cloud me-1" style="color:var(--cf-orange);"></i>Proxy Status
                        </p>

                        <div class="p-3 rounded mb-3" style="background:rgba(255,255,255,.04); border:1px solid var(--border);">
                            <!-- A Record proxy -->
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <div>
                                    <span class="badge badge-a me-1">A</span>
                                    <code style="font-size:.82rem;">@</code>
                                    <small class="text-muted ms-1">Root domain</small>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="proxiedA" checked>
                                    <label class="form-check-label" for="proxiedA" id="proxiedALabel"
                                           style="font-size:.8rem; color:var(--cf-orange); font-weight:600;">
                                        Proxied
                                    </label>
                                </div>
                            </div>
                            <hr style="border-color:var(--border); margin:.5rem 0;">
                            <!-- CNAME Record proxy -->
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <span class="badge badge-cname me-1">CNAME</span>
                                    <code style="font-size:.82rem;">www</code>
                                    <small class="text-muted ms-1">Subdomain</small>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="proxiedCNAME" checked>
                                    <label class="form-check-label" for="proxiedCNAME" id="proxiedCNAMELabel"
                                           style="font-size:.8rem; color:var(--cf-orange); font-weight:600;">
                                        Proxied
                                    </label>
                                </div>
                            </div>
                        </div>

                        <hr style="border-color:var(--border); margin: 1rem 0;">

                        <!-- Always Use HTTPS -->
                        <p class="mb-2 fw-bold" style="font-size:.82rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.04em;">
                            <i class="fas fa-lock me-1" style="color:#3fb950;"></i>HTTPS Settings
                        </p>

                        <div class="p-3 rounded mb-3" style="background:rgba(63,185,80,.06); border:1px solid rgba(63,185,80,.3);">
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <i class="fas fa-shield-alt me-1" style="color:#3fb950;"></i>
                                    <strong style="font-size:.88rem;">Always Use HTTPS</strong><br>
                                    <small class="text-muted">Chuyển hướng HTTP → HTTPS</small>
                                </div>
                                <div class="form-check form-switch mb-0">
                                    <input class="form-check-input" type="checkbox" id="alwaysHttpsSwitch" checked>
                                    <label class="form-check-label" for="alwaysHttpsSwitch" id="alwaysHttpsLabel"
                                           style="font-size:.8rem; color:#3fb950; font-weight:600;">
                                        ON
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="p-3 rounded" style="background:rgba(255,255,255,.04); border:1px solid var(--border);">
                            <p class="mb-2 fw-bold" style="font-size:.85rem;">Mỗi domain sẽ tạo:</p>
                            <ul class="mb-0 small" style="color:var(--text-muted);">
                                <li><span class="badge badge-a">A</span> <code>@</code> → IP bạn nhập</li>
                                <li class="mt-1"><span class="badge badge-cname">CNAME</span> <code>www</code> → tên zone</li>
                                <li class="mt-1"><i class="fas fa-sync-alt me-1"></i>Nếu record đã tồn tại → cập nhật</li>
                            </ul>
                        </div>

                        <div class="d-grid mt-4">
                            <button type="submit" class="btn btn-warning btn-lg fw-bold" id="deployBtn">
                                <i class="fas fa-rocket me-2"></i>Triển khai DNS Records
                            </button>
                        </div>

                        <div class="d-grid mt-2">
                            <button type="button" class="btn btn-outline-success btn-lg fw-bold" id="enableAlwaysHttpsBtn" onclick="bulkEnableAlwaysHttps()">
                                <i class="fas fa-lock me-2"></i>Bật Always Use HTTPS cho danh sách domain
                            </button>
                        </div>

                        <div class="d-grid mt-2">
                            <button type="button" class="btn btn-outline-danger btn-lg fw-bold" id="bulkDeleteDomainsBtn" onclick="bulkDeleteByDomainList()">
                                <i class="fas fa-trash-alt me-2"></i>Xóa DNS Records theo danh sách domain
                            </button>
                        </div>
                        <small class="form-text">Chỉ xóa record loại A (@) và CNAME (www) cho mỗi domain trong danh sách.</small>

                        <div class="mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="fw-bold" style="font-size:.9rem; color:var(--text-muted); text-transform:uppercase; letter-spacing:.04em;">
                                    <i class="fas fa-chart-bar me-1" style="color:var(--cf-orange);"></i>Tiến trình
                                </div>
                                <div id="progressText" style="font-size:.85rem;color:var(--text-muted);">0 / 0 domain</div>
                            </div>
                            <div class="progress" style="height: 18px; background:#0d1117; border:1px solid var(--border);">
                                <div id="deployProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-warning" role="progressbar" style="width:0%">0%</div>
                            </div>
                            <div class="mt-2 small text-muted" id="progressDomainName">Chưa bắt đầu.</div>
                        </div>
                    </div>
                </div>
            </form>

            <!-- Result -->
            <div id="deployResult" class="mt-4" style="display:none;"></div>
        </div>

        <!-- ═══ TAB: CHECK DOMAIN ══════════════════════════════════════════════ -->
        <div class="tab-pane fade" id="tab-check">
            <div class="row justify-content-center">
                <div class="col-lg-6">
                    <label class="form-label" for="checkInput">
                        <i class="fas fa-globe me-1"></i>Domain cần kiểm tra
                    </label>
                    <div class="input-group">
                        <input type="text" class="form-control" id="checkInput"
                               placeholder="example.com" autocomplete="off">
                        <button class="btn btn-outline-warning" id="checkBtn" onclick="checkDomain()">
                            <i class="fas fa-search me-1"></i>Check
                        </button>
                    </div>
                    <div class="d-flex justify-content-end mt-2">
                        <button class="btn btn-danger btn-sm" id="deleteDnsRecordsBtn" onclick="deleteAllVisibleRecords()" disabled>
                            <i class="fas fa-trash me-1"></i>Xóa DNS Records (A/CNAME)
                        </button>
                    </div>
                    <small class="form-text d-block mt-1">Nút này chỉ xóa các record loại A/CNAME của domain vừa kiểm tra.</small>
                </div>
            </div>
            <div id="checkResult" class="mt-4"></div>
        </div>

    </div><!-- /tab-content -->
</div>
</div><!-- /main-wrapper -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
let lastCheckedRecords = [];
let lastCheckedZoneId = '';

// ── Domain counter ───────────────────────────────────────────────────────────
const domainInput = document.getElementById('domainInput');

function getDomains() {
    const raw = domainInput.value.trim();
    if (!raw) return [];
    return [...new Set(
        raw.split(/[\r\n,;]+/)
           .map(d => d.replace(/^https?:\/\//i, '').replace(/\/.*$/, '').toLowerCase().trim())
           .filter(d => d && d.includes('.'))
    )];
}

function updateCount() {
    const list  = getDomains();
    const badge = document.getElementById('domainCountBadge');
    const info  = document.getElementById('domainCount');
    badge.textContent = list.length;
    badge.className   = list.length > 0 ? 'badge bg-success ms-1' : 'badge bg-secondary ms-1';
    info.textContent  = list.length > 0
        ? list.length + ' domain' + (list.length > 1 ? 's' : '') + ' sẽ được xử lý'
        : '';
}

domainInput.addEventListener('input', updateCount);
updateCount();

// ── Proxy label sync ─────────────────────────────────────────────────────────
// ── Always HTTPS label sync ─────────────────────────────────────────────────
(function() {
    const el    = document.getElementById('alwaysHttpsSwitch');
    const label = document.getElementById('alwaysHttpsLabel');
    function sync() {
        label.textContent = el.checked ? 'ON' : 'OFF';
        label.style.color = el.checked ? '#3fb950' : 'var(--text-muted)';
    }
    el.addEventListener('change', sync);
    sync();
})();

['proxiedA', 'proxiedCNAME'].forEach(function(id) {
    const el    = document.getElementById(id);
    const label = document.getElementById(id + 'Label');
    if (!el || !label) return;
    function syncLabel() {
        label.textContent = el.checked ? 'Proxied' : 'DNS only';
        label.style.color = el.checked ? 'var(--cf-orange)' : 'var(--text-muted)';
    }
    el.addEventListener('change', syncLabel);
    syncLabel();
});

function cleanDomains() {
    const list = getDomains();
    domainInput.value = list.join('\n');
    updateCount();
}

function clearDomains() {
    domainInput.value = '';
    updateCount();
}

// ── Auto-fill from URL params ────────────────────────────────────────────────
(function () {
    const p = new URLSearchParams(location.search);
    const d = p.get('domain') || p.get('domains');
    const ip = p.get('ip') || p.get('ip_address');
    if (d) { domainInput.value = d; updateCount(); }
    if (ip) document.getElementById('ipInput').value = ip;
})();

// ── Bulk Deploy ──────────────────────────────────────────────────────────────
document.getElementById('deployForm').addEventListener('submit', function (e) {
    e.preventDefault();
    bulkDeploy();
});

function bulkDeploy() {
    const domains      = getDomains();
    const ip           = document.getElementById('ipInput').value.trim();
    const proxiedA     = document.getElementById('proxiedA').checked;
    const proxiedCNAME = document.getElementById('proxiedCNAME').checked;
    const alwaysHttps  = document.getElementById('alwaysHttpsSwitch').checked;

    if (!domains.length) { alert('Vui lòng nhập ít nhất một domain'); return; }
    if (!ip)              { alert('Vui lòng nhập IP address'); return; }

    const btn = document.getElementById('deployBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang triển khai ' + domains.length + ' domain…';

    const result = document.getElementById('deployResult');
    result.style.display = 'none';
    result.dataset.initialized = '';
    result.innerHTML = '';

    const progressBar = document.getElementById('deployProgressBar');
    const progressText = document.getElementById('progressText');
    const progressDomainName = document.getElementById('progressDomainName');
    progressBar.style.width = '0%';
    progressBar.textContent = '0%';
    progressText.textContent = '0 / ' + domains.length + ' domain';
    progressDomainName.textContent = 'Đang chờ...';

    const rows = [];

    const total = domains.length;

    function updateProgress(current, domainName) {
        const percent = total > 0 ? Math.round((current / total) * 100) : 0;
        progressBar.style.width = percent + '%';
        progressBar.textContent = percent + '%';
        progressText.textContent = current + ' / ' + total + ' domain';
        progressDomainName.textContent = domainName ? 'Đang xử lý: ' + domainName : 'Đang xử lý...';
    }

    function deployOne(index) {
        if (index >= total) {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-rocket me-2"></i>Triển khai DNS Records';

            const successCount = rows.filter(r => r.success).length;
            const errorCount = rows.filter(r => !r.success).length;
            renderDeployResult({
                success: true,
                results: rows,
                stats: {
                    total: total,
                    success: successCount,
                    error: errorCount,
                }
            });
            progressDomainName.textContent = 'Hoàn tất.';
            return;
        }

        const domain = domains[index];
        updateProgress(index, domain);

        const fd = new FormData();
        fd.append('action', 'deploy_domain');
        fd.append('domain', domain);
        fd.append('ip_address', ip);
        fd.append('proxied_a', proxiedA ? '1' : '0');
        fd.append('proxied_cname', proxiedCNAME ? '1' : '0');
        fd.append('always_https', alwaysHttps ? '1' : '0');

        fetch('', { method: 'POST', body: fd })
            .then(r => r.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Phản hồi không phải JSON: ' + text.slice(0, 180));
                }
            }))
            .then(data => {
                const item = data.result || { domain: domain, success: false, error: 'Invalid response' };
                rows.push(item);
                appendDeployRow(item, index + 1, total);
                updateProgress(index + 1, domain);
                deployOne(index + 1);
            })
            .catch(err => {
                const item = { domain: domain, success: false, error: err.message };
                rows.push(item);
                appendDeployRow(item, index + 1, total);
                updateProgress(index + 1, domain);
                deployOne(index + 1);
            });
    }

    deployOne(0);
}

function bulkEnableAlwaysHttps() {
    const domains = getDomains();
    if (!domains.length) {
        alert('Vui lòng nhập ít nhất một domain');
        return;
    }
    if (!confirm('Xác nhận bật Always Use HTTPS cho ' + domains.length + ' domain?')) return;

    const btn = document.getElementById('enableAlwaysHttpsBtn');
    const oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang bật Always Use HTTPS...';

    const result = document.getElementById('deployResult');
    result.style.display = 'none';
    result.innerHTML = '';

    const progressBar = document.getElementById('deployProgressBar');
    const progressText = document.getElementById('progressText');
    const progressDomainName = document.getElementById('progressDomainName');
    const rows = [];
    const total = domains.length;
    progressBar.style.width = '0%';
    progressBar.textContent = '0%';
    progressText.textContent = '0 / ' + total + ' domain';
    progressDomainName.textContent = 'Đang chờ...';

    function processNext(index) {
        if (index >= total) {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            progressBar.style.width = '100%';
            progressBar.textContent = '100%';
            progressText.textContent = total + ' / ' + total + ' domain';
            progressDomainName.textContent = 'Hoàn tất.';
            renderAlwaysHttpsResult(rows);
            return;
        }

        const domain = domains[index];
        const percent = Math.round((index / total) * 100);
        progressBar.style.width = percent + '%';
        progressBar.textContent = percent + '%';
        progressText.textContent = index + ' / ' + total + ' domain';
        progressDomainName.textContent = 'Đang xử lý: ' + domain;

        const fd = new FormData();
        fd.append('action', 'enable_always_https');
        fd.append('domain', domain);

        fetch('', { method: 'POST', body: fd })
            .then(r => r.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Phản hồi không phải JSON: ' + text.slice(0, 180));
                }
            }))
            .then(data => {
                if (!data.success) throw new Error(data.error || 'Không thể bật Always Use HTTPS');
                rows.push(data.result || {
                    domain: domain,
                    success: false,
                    https: { enabled: true, success: false },
                    error: 'Phản hồi không hợp lệ',
                });
                processNext(index + 1);
            })
            .catch(err => {
                rows.push({
                    domain: domain,
                    success: false,
                    https: { enabled: true, success: false },
                    error: err.message,
                });
                processNext(index + 1);
            });
    }

    processNext(0);
}

function renderAlwaysHttpsResult(results) {
    const successCount = results.filter(item => item.success).length;
    const errorCount = results.length - successCount;
    const result = document.getElementById('deployResult');
    result.style.display = 'block';

    let html = `
        <div class="d-flex gap-3 mb-3 flex-wrap">
            <div class="p-3 rounded text-center" style="background:rgba(255,255,255,.05);min-width:110px;">
                <div style="font-size:1.6rem;font-weight:700;">${results.length}</div>
                <small class="text-muted">Tổng domain</small>
            </div>
            <div class="p-3 rounded text-center" style="background:rgba(63,185,80,.12);min-width:110px;">
                <div style="font-size:1.6rem;font-weight:700;color:#3fb950;">${successCount}</div>
                <small class="text-muted">Thành công</small>
            </div>
            <div class="p-3 rounded text-center" style="background:rgba(248,81,73,.12);min-width:110px;">
                <div style="font-size:1.6rem;font-weight:700;color:#f85149;">${errorCount}</div>
                <small class="text-muted">Lỗi</small>
            </div>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" style="color:var(--text);">
                <thead style="background:rgba(255,255,255,.06);">
                    <tr><th>Domain</th><th>Zone</th><th>Always Use HTTPS</th><th>Trạng thái</th></tr>
                </thead>
                <tbody>`;

    for (const item of results) {
        html += `<tr class="${item.success ? 'rec-row-ok' : 'rec-row-fail'}">
            <td><code>${escHtml(item.domain)}</code></td>
            <td><small class="text-muted">${escHtml(item.zone || '—')}</small></td>
            <td>${item.https?.success
                ? '<span class="badge" style="background:#3fb950;color:#fff;"><i class="fas fa-lock me-1"></i>ON ✓</span>'
                : '<span class="badge bg-danger"><i class="fas fa-lock me-1"></i>ON ✗</span>'
            }</td>
            <td>${item.success
                ? '<span class="badge bg-success">✓ OK</span>'
                : `<span class="badge bg-danger">✗ Lỗi</span><br><small class="text-danger">${escHtml(item.error || 'Không thể bật Always Use HTTPS')}</small>`
            }</td>
        </tr>`;
    }

    html += '</tbody></table></div>';
    result.innerHTML = html;
    result.scrollIntoView({ behavior: 'smooth' });
}

function bulkDeleteByDomainList() {
    const domains = getDomains();
    if (!domains.length) {
        alert('Vui lòng nhập ít nhất một domain');
        return;
    }

    if (!confirm('Xác nhận xóa A/CNAME (@, www) cho ' + domains.length + ' domain?')) return;

    const btn = document.getElementById('bulkDeleteDomainsBtn');
    const oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang xóa DNS Records...';

    const result = document.getElementById('deployResult');
    result.style.display = 'none';
    result.innerHTML = '';

    const fd = new FormData();
    fd.append('action', 'bulk_delete_by_domains');
    fd.append('domains', document.getElementById('domainInput').value);

    fetch('', { method: 'POST', body: fd })
        .then(r => r.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Phản hồi không phải JSON: ' + text.slice(0, 180));
            }
        }))
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            renderBulkDeleteResult(data);
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            result.style.display = 'block';
            result.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i>${escHtml(err.message)}</div>`;
        });
}

function renderBulkDeleteResult(data) {
    const el = document.getElementById('deployResult');
    el.style.display = 'block';

    if (!data.success) {
        el.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i><strong>Lỗi:</strong> ${escHtml(data.error || 'Unknown error')}</div>`;
        el.scrollIntoView({ behavior: 'smooth' });
        return;
    }

    const s = data.stats || { total: 0, success: 0, error: 0, deleted: 0 };
    let html = `
        <div class="d-flex gap-3 mb-3 flex-wrap">
            <div class="p-3 rounded text-center" style="background:rgba(255,255,255,.05);min-width:110px;">
                <div style="font-size:1.6rem;font-weight:700;">${s.total}</div>
                <small class="text-muted">Tổng domain</small>
            </div>
            <div class="p-3 rounded text-center" style="background:rgba(63,185,80,.12);min-width:110px;">
                <div style="font-size:1.6rem;font-weight:700;color:#3fb950;">${s.success}</div>
                <small class="text-muted">Xử lý được</small>
            </div>
            <div class="p-3 rounded text-center" style="background:rgba(248,81,73,.12);min-width:110px;">
                <div style="font-size:1.6rem;font-weight:700;color:#f85149;">${s.error}</div>
                <small class="text-muted">Lỗi</small>
            </div>
            <div class="p-3 rounded text-center" style="background:rgba(227,179,65,.12);min-width:110px;">
                <div style="font-size:1.6rem;font-weight:700;color:#e3b341;">${s.deleted}</div>
                <small class="text-muted">Records đã xóa</small>
            </div>
        </div>
        <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" style="color:var(--text);">
            <thead style="background:rgba(255,255,255,.06);">
                <tr>
                    <th>Domain</th>
                    <th>Zone</th>
                    <th>Records đã xóa</th>
                    <th>Trạng thái</th>
                    <th>Chi tiết</th>
                </tr>
            </thead>
            <tbody>`;

    for (const item of (data.results || [])) {
        const rowCls = item.success ? 'rec-row-ok' : 'rec-row-fail';
        const details = (item.targets || []).length
            ? item.targets.map(t => `${escHtml(t.type)} ${escHtml(t.name)} ${t.success ? '✓' : '✗'}`).join('<br>')
            : '<span class="text-muted">—</span>';

        html += `<tr class="${rowCls}">
            <td><code>${escHtml(item.domain)}</code></td>
            <td><small class="text-muted">${escHtml(item.zone || '—')}</small></td>
            <td><span class="badge bg-warning text-dark">${Number(item.deleted || 0)}</span></td>
            <td>${item.success
                ? '<span class="badge bg-success">✓ OK</span>'
                : `<span class="badge bg-danger">✗ Lỗi</span><br><small class="text-danger">${escHtml(item.error || '')}</small>`
            }</td>
            <td><small>${details}</small></td>
        </tr>`;
    }

    html += '</tbody></table></div>';
    el.innerHTML = html;
    el.scrollIntoView({ behavior: 'smooth' });
}

function appendDeployRow(item, idx, total) {
    const result = document.getElementById('deployResult');
    result.style.display = 'block';
    if (!result.dataset.initialized) {
        result.dataset.initialized = '1';
        result.innerHTML = `
            <div class="d-flex gap-3 mb-3 flex-wrap" id="summaryCards"></div>
            <div class="table-responsive">
            <table class="table table-sm align-middle mb-0" style="color:var(--text);">
                <thead style="background:rgba(255,255,255,.06);">
                    <tr>
                        <th>#</th><th>Domain</th><th>Zone</th><th>A</th><th>CNAME</th><th>HTTPS</th><th>Thao tác</th><th>Trạng thái</th>
                    </tr>
                </thead>
                <tbody id="deployResultBody"></tbody>
            </table></div>`;
    }

    const body = document.getElementById('deployResultBody');
    if (!body) return;

    const rowCls = item.success ? 'rec-row-ok' : 'rec-row-fail';
    const aRec = item.records?.find(r => r.type === 'A');
    const cRec = item.records?.find(r => r.type === 'CNAME');
    const zoneId = item.zone_id || '';
    const httpsCell = item.https?.enabled ? (item.https.success ? '<span class="badge" style="background:#3fb950;color:#fff;">ON ✓</span>' : '<span class="badge bg-danger">ON ✗</span>') : '<span class="badge bg-secondary">OFF</span>';

    function buildDeleteBtn(rec, recLabel) {
        if (!rec || !rec.id || !zoneId || !rec.success) return '';
        const recType = String(rec.type || '').toUpperCase();
        if (!['A', 'CNAME'].includes(recType)) return '';
        return `<button type="button" class="btn btn-sm btn-outline-danger delete-record-btn me-1 mb-1"
            data-zone-id="${escHtml(zoneId)}"
            data-record-id="${escHtml(rec.id)}"
            data-record-name="${escHtml(recLabel)}"
            data-record-type="${escHtml(recType)}">
            <i class="fas fa-trash-alt me-1"></i>Xóa ${escHtml(recType)}
        </button>`;
    }

    const actionCell = `${buildDeleteBtn(aRec, '@')}${buildDeleteBtn(cRec, 'www')}` || '<span class="text-muted">—</span>';

    const tr = document.createElement('tr');
    tr.className = rowCls;
    tr.innerHTML = `
        <td>${idx}</td>
        <td><code>${escHtml(item.domain)}</code></td>
        <td><small class="text-muted">${escHtml(item.zone ?? '—')}</small></td>
        <td>${aRec ? '<span class="badge badge-a me-1">A</span>' + (aRec.success ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">FAIL</span>') : '<span class="text-muted">—</span>'}</td>
        <td>${cRec ? '<span class="badge badge-cname me-1">CNAME</span>' + (cRec.success ? '<span class="badge bg-success">OK</span>' : '<span class="badge bg-danger">FAIL</span>') : '<span class="text-muted">—</span>'}</td>
        <td>${httpsCell}</td>
        <td>${actionCell}</td>
        <td>${item.success ? '<span class="badge bg-success">✓ OK</span>' : '<span class="badge bg-danger">✗ Lỗi</span><br><small class="text-danger">' + escHtml(item.error ?? '') + '</small>'}</td>`;
    body.appendChild(tr);
}

function renderDeployResult(data) {
    const el = document.getElementById('deployResult');
    el.style.display = 'block';

    if (!data.success) {
        el.innerHTML = `<div class="alert alert-danger"><i class="fas fa-times-circle me-2"></i><strong>Lỗi:</strong> ${escHtml(data.error || 'Unknown error')}</div>`;
        el.scrollIntoView({ behavior: 'smooth' });
        return;
    }

    const s = data.stats;
    let html = `
        <div class="d-flex gap-3 mb-3 flex-wrap">
            <div class="p-3 rounded text-center" style="background:rgba(255,255,255,.05);min-width:110px;">
                <div style="font-size:1.6rem;font-weight:700;">${s.total}</div>
                <small class="text-muted">Tổng</small>
            </div>
            <div class="p-3 rounded text-center" style="background:rgba(63,185,80,.12);min-width:110px;">
                <div style="font-size:1.6rem;font-weight:700;color:#3fb950;">${s.success}</div>
                <small class="text-muted">Thành công</small>
            </div>
            <div class="p-3 rounded text-center" style="background:rgba(248,81,73,.12);min-width:110px;">
                <div style="font-size:1.6rem;font-weight:700;color:#f85149;">${s.error}</div>
                <small class="text-muted">Lỗi</small>
            </div>
        </div>
        <div class="table-responsive">
        <table class="table table-sm align-middle mb-0" style="color:var(--text);">
            <thead style="background:rgba(255,255,255,.06);">
                <tr>
                    <th>Domain</th>
                    <th>Zone</th>
                    <th>A Record (@)</th>
                    <th>Proxy A</th>
                    <th>CNAME (www)</th>
                    <th>Proxy CNAME</th>
                    <th>Always HTTPS</th>
                    <th>Thao tác</th>
                    <th>Trạng thái</th>
                </tr>
            </thead>
            <tbody>`;

    for (const item of data.results) {
        const aRec    = item.records?.find(r => r.type === 'A');
        const cRec    = item.records?.find(r => r.type === 'CNAME');
        const zoneId  = item.zone_id || '';
        const rowCls  = item.success ? 'rec-row-ok' : 'rec-row-fail';

        const proxyBadge = (val) => val
            ? `<span class="badge" style="background:#F48120;color:#fff;"><i class="fas fa-cloud me-1"></i>Proxied</span>`
            : `<span class="badge bg-secondary"><i class="fas fa-cloud-slash me-1"></i>DNS only</span>`;

        const aCell   = aRec
            ? `<span class="badge badge-a me-1">A</span>
               <span class="badge ${aRec.action === 'created' ? 'bg-success' : 'bg-primary'} me-1">${aRec.action}</span>
               ${aRec.success ? '✓' : '<span class="text-danger">✗</span>'}`
            : '<span class="text-muted">—</span>';
        const cCell   = cRec
            ? `<span class="badge badge-cname me-1">CNAME</span>
               <span class="badge ${cRec.action === 'created' ? 'bg-success' : 'bg-primary'} me-1">${cRec.action}</span>
               ${cRec.success ? '✓' : '<span class="text-danger">✗</span>'}`
            : '<span class="text-muted">—</span>';

        const httpsCell = (() => {
            const h = item.https;
            if (!h) return '<span class="text-muted">—</span>';
            if (!h.enabled) return '<span class="badge bg-secondary">OFF</span>';
            return h.success
                ? '<span class="badge" style="background:#3fb950;color:#fff;"><i class="fas fa-lock me-1"></i>ON ✓</span>'
                : '<span class="badge bg-danger"><i class="fas fa-lock me-1"></i>ON ✗</span>';
        })();

        const buildDeleteBtn = (rec, recLabel) => {
            if (!rec || !rec.id || !zoneId || !rec.success) return '';
            const recType = String(rec.type || '').toUpperCase();
            if (!['A', 'CNAME'].includes(recType)) return '';
            return `<button type="button" class="btn btn-sm btn-outline-danger delete-record-btn me-1 mb-1"
                data-zone-id="${escHtml(zoneId)}"
                data-record-id="${escHtml(rec.id)}"
                data-record-name="${escHtml(recLabel)}"
                data-record-type="${escHtml(recType)}">
                <i class="fas fa-trash-alt me-1"></i>Xóa ${escHtml(recType)}
            </button>`;
        };

        const actionCell = `${buildDeleteBtn(aRec, '@')}${buildDeleteBtn(cRec, 'www')}` || '<span class="text-muted">—</span>';

        html += `<tr class="${rowCls}">
            <td><code>${escHtml(item.domain)}</code></td>
            <td><small class="text-muted">${escHtml(item.zone ?? '—')}</small></td>
            <td>${aCell}</td>
            <td>${aRec ? proxyBadge(aRec.proxied) : '<span class="text-muted">—</span>'}</td>
            <td>${cCell}</td>
            <td>${cRec ? proxyBadge(cRec.proxied) : '<span class="text-muted">—</span>'}</td>
            <td>${httpsCell}</td>
            <td>${actionCell}</td>
            <td>${item.success
                ? '<span class="badge bg-success">✓ OK</span>'
                : `<span class="badge bg-danger">✗ Lỗi</span><br><small class="text-danger">${escHtml(item.error ?? '')}</small>`
            }</td>
        </tr>`;
    }

    html += `</tbody></table></div>`;
    el.innerHTML = html;
    el.scrollIntoView({ behavior: 'smooth' });
}

// ── Check Domain ─────────────────────────────────────────────────────────────
document.getElementById('checkInput').addEventListener('keydown', e => {
    if (e.key === 'Enter') checkDomain();
});

function checkDomain() {
    const domain = document.getElementById('checkInput').value.trim();
    if (!domain) return;

    const btn = document.getElementById('checkBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const fd = new FormData();
    fd.append('action', 'check_domain');
    fd.append('domain', domain);

    fetch('', { method: 'POST', body: fd })
        .then(r => r.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Phản hồi không phải JSON: ' + text.slice(0, 180));
            }
        }))
        .then(data => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search me-1"></i>Check';
            renderCheckResult(data);
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-search me-1"></i>Check';
            document.getElementById('checkResult').innerHTML =
                `<div class="alert alert-danger">${escHtml(err.message)}</div>`;
        });
}

function renderCheckResult(data) {
    const el = document.getElementById('checkResult');
    if (!data.success) {
        lastCheckedRecords = [];
        lastCheckedZoneId = '';
        updateDeleteToolbarButton();
        el.innerHTML = `<div class="alert alert-danger">${escHtml(data.error)}</div>`;
        return;
    }
    if (!data.found) {
        lastCheckedRecords = [];
        lastCheckedZoneId = '';
        updateDeleteToolbarButton();
        el.innerHTML = `<div class="alert alert-warning"><i class="fas fa-exclamation-triangle me-2"></i>Domain <strong>${escHtml(data.domain)}</strong> không tìm thấy trong Cloudflare account</div>`;
        return;
    }

    lastCheckedRecords = Array.isArray(data.records) ? data.records : [];
    lastCheckedZoneId = data.zone?.id || '';

    const typeColor = { A: 'badge-a', CNAME: 'badge-cname', MX: 'bg-warning text-dark', TXT: 'bg-secondary', NS: 'bg-dark border' };

    let rows = '';
    for (const r of data.records) {
        const bc = typeColor[r.type] ?? 'bg-secondary';
        const canDelete = ['A', 'CNAME'].includes(String(r.type || '').toUpperCase());
        const actionCell = canDelete
            ? `<button type="button" class="btn btn-sm btn-outline-danger delete-record-btn"
                    data-zone-id="${escHtml(data.zone.id)}"
                    data-record-id="${escHtml(r.id)}"
                    data-record-name="${escHtml(r.name)}"
                    data-record-type="${escHtml(r.type)}">
                    <i class="fas fa-trash-alt me-1"></i>Xóa
                </button>`
            : '<span class="badge bg-secondary">Không hỗ trợ</span>';

        rows += `<tr>
            <td><span class="badge ${bc}">${escHtml(r.type)}</span></td>
            <td><code>${escHtml(r.name)}</code></td>
            <td style="word-break:break-all;">${escHtml(r.content)}</td>
            <td>${r.ttl === 1 ? 'Auto' : r.ttl}</td>
            <td>${r.proxied ? '<span class="badge bg-warning text-dark"><i class="fas fa-cloud me-1"></i>Yes</span>' : '<span class="badge bg-secondary">No</span>'}</td>
            <td>${actionCell}</td>
        </tr>`;
    }

    const deletableCount = lastCheckedRecords.filter(r => ['A', 'CNAME'].includes(String(r.type || '').toUpperCase())).length;
    const deleteCountLabel = `Xóa ${deletableCount} DNS Records`;
    updateDeleteToolbarButton();

    el.innerHTML = `
        <div class="alert alert-success py-2"><i class="fas fa-check-circle me-2"></i>
            Zone: <strong>${escHtml(data.zone.name)}</strong>
            <small class="text-muted ms-2">ID: ${escHtml(data.zone.id)}</small>
        </div>
        <div class="table-responsive">
        <table class="table table-sm align-middle" style="color:var(--text);">
            <thead style="background:rgba(255,255,255,.06);">
                <tr><th>Type</th><th>Name</th><th>Content</th><th>TTL</th><th>Proxied</th><th>Thao tác</th></tr>
            </thead>
            <tbody>${rows}</tbody>
        </table></div>
        <div class="mt-3">
            <button class="btn btn-sm btn-outline-warning" onclick="prefillDeploy('${escHtml(data.domain)}')">
                <i class="fas fa-rocket me-1"></i>Tạo/Cập nhật Records cho domain này
            </button>
            <button class="btn btn-sm btn-outline-danger ms-2" id="deleteAllVisibleBtn">
                <i class="fas fa-trash me-1"></i>${deleteCountLabel}
            </button>
        </div>`;

    const deleteAllBtn = document.getElementById('deleteAllVisibleBtn');
    if (deleteAllBtn) {
        deleteAllBtn.disabled = deletableCount === 0;
        deleteAllBtn.addEventListener('click', deleteAllVisibleRecords);
    }
}

function updateDeleteToolbarButton() {
    const btn = document.getElementById('deleteDnsRecordsBtn');
    if (!btn) return;

    const deletableCount = lastCheckedRecords.filter(r => ['A', 'CNAME'].includes(String(r.type || '').toUpperCase())).length;
    btn.disabled = !lastCheckedZoneId || deletableCount === 0;
    btn.innerHTML = `<i class="fas fa-trash me-1"></i>Xóa DNS Records (A/CNAME: ${deletableCount})`;
}

document.addEventListener('click', function(event) {
    const btn = event.target.closest('.delete-record-btn');
    if (!btn) return;

    const zoneId = btn.dataset.zoneId || '';
    const recordId = btn.dataset.recordId || '';
    const recordType = btn.dataset.recordType || '';
    const recordName = btn.dataset.recordName || '';

    deleteSingleRecord(btn, zoneId, recordId, recordType + ' ' + recordName);
});

function deleteSingleRecord(btn, zoneId, recordId, label) {
    const recordType = String(btn?.dataset?.recordType || '').toUpperCase();
    if (!['A', 'CNAME'].includes(recordType)) {
        alert('Chỉ được phép xóa record loại A/CNAME.');
        return;
    }

    if (!zoneId || !recordId) {
        alert('Thiếu zone_id hoặc record_id để xóa');
        return;
    }

    if (!confirm('Xác nhận xóa record: ' + label + ' ?')) return;

    const oldHtml = btn.innerHTML;
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xóa';

    const fd = new FormData();
    fd.append('action', 'delete_dns_record');
    fd.append('zone_id', zoneId);
    fd.append('record_id', recordId);

    fetch('', { method: 'POST', body: fd })
        .then(r => r.text().then(text => {
            try {
                return JSON.parse(text);
            } catch (e) {
                throw new Error('Phản hồi không phải JSON: ' + text.slice(0, 180));
            }
        }))
        .then(data => {
            if (!data.success) throw new Error(data.error || 'Không thể xóa record');
            const row = btn.closest('tr');
            if (row) row.remove();

            const checkInputVal = document.getElementById('checkInput')?.value.trim();
            if (checkInputVal) checkDomain();
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = oldHtml;
            alert('Xóa record thất bại: ' + err.message);
        });
}

function deleteAllVisibleRecords() {
    if (!lastCheckedZoneId) {
        alert('Chưa có zone để xóa records');
        return;
    }
    const deletableRecords = lastCheckedRecords.filter(r => ['A', 'CNAME'].includes(String(r.type || '').toUpperCase()));
    if (!deletableRecords.length) {
        alert('Không có record A/CNAME nào để xóa');
        updateDeleteToolbarButton();
        return;
    }

    if (!confirm('Xác nhận xóa toàn bộ ' + deletableRecords.length + ' DNS records loại A/CNAME đang hiển thị?')) return;

    const resultBtn = document.getElementById('deleteAllVisibleBtn');
    const toolbarBtn = document.getElementById('deleteDnsRecordsBtn');

    if (resultBtn) resultBtn.disabled = true;
    if (toolbarBtn) toolbarBtn.disabled = true;

    const oldResultText = resultBtn ? resultBtn.innerHTML : '';
    const oldToolbarText = toolbarBtn ? toolbarBtn.innerHTML : '';

    const records = [...deletableRecords];
    let deleted = 0;

    function deleteNext(idx) {
        if (idx >= records.length) {
            if (resultBtn) {
                resultBtn.innerHTML = `<i class="fas fa-trash me-1"></i>Xóa ${Math.max(0, records.length - deleted)} DNS Records`;
                resultBtn.disabled = false;
            }
            if (toolbarBtn) {
                toolbarBtn.innerHTML = oldToolbarText;
                toolbarBtn.disabled = false;
            }
            checkDomain();
            updateDeleteToolbarButton();
            return;
        }

        const rec = records[idx];
        if (resultBtn) {
            resultBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xóa ' + (idx + 1) + '/' + records.length;
        }
        if (toolbarBtn) {
            toolbarBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Đang xóa ' + (idx + 1) + '/' + records.length;
        }

        const fd = new FormData();
        fd.append('action', 'delete_dns_record');
        fd.append('zone_id', lastCheckedZoneId);
        fd.append('record_id', rec.id);

        fetch('', { method: 'POST', body: fd })
            .then(r => r.text().then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    throw new Error('Phản hồi không phải JSON: ' + text.slice(0, 180));
                }
            }))
            .then(data => {
                if (data.success) deleted++;
                deleteNext(idx + 1);
            })
            .catch(() => {
                deleteNext(idx + 1);
            });
    }

    deleteNext(0);
}

function prefillDeploy(domain) {
    document.getElementById('domainInput').value = domain;
    updateCount();
    bootstrap.Tab.getOrCreateInstance(document.querySelector('[data-bs-target="#tab-deploy"]')).show();
    document.getElementById('ipInput').focus();
}

// ── Utility ──────────────────────────────────────────────────────────────────
function escHtml(s) {
    return String(s ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}
</script>
</body>
</html>
