<?php
/**
 * aapanel_bulk_rewrite_wordpress.php
 * Cập nhật Rewrite rule "wordpress" trên aaPanel theo danh sách domain.
 */

$rewriteComposerAutoload = __DIR__ . '/vendor/autoload.php';
if (is_file($rewriteComposerAutoload)) {
    require_once $rewriteComposerAutoload;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/CloudflareAPI.php';

$rewriteIsAjax = ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action']));
if ($rewriteIsAjax) {
    // Tránh warning HTML chen vào response JSON của AJAX.
    ini_set('display_errors', '0');
    ini_set('html_errors', '0');
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    ob_start();
}

function rewriteNormalizeDomain($domain) {
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

    // Chuẩn hóa IDN về punycode nếu môi trường có ext-intl.
    if (function_exists('idn_to_ascii')) {
        $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if (is_string($ascii) && $ascii !== '') {
            $host = strtolower($ascii);
        }
    }

    return $host;
}

function rewriteBuildDomainAliases($domain) {
    $aliases = [];
    $normalized = rewriteNormalizeDomain($domain);
    if ($normalized !== '') {
        $aliases[] = $normalized;
    }

    $raw = strtolower(trim((string)$domain));
    if ($raw !== '' && !in_array($raw, $aliases, true)) {
        $aliases[] = $raw;
    }

    if (function_exists('idn_to_utf8')) {
        foreach ($aliases as $item) {
            $utf8 = idn_to_utf8($item, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
            if (is_string($utf8) && $utf8 !== '' && !in_array($utf8, $aliases, true)) {
                $aliases[] = strtolower($utf8);
            }
        }
    }

    return array_values(array_unique(array_filter($aliases, static function ($item) {
        return trim((string)$item) !== '';
    })));
}

function rewriteParseDomainList($input) {
    $parts = preg_split('/[\r\n,;\s]+/', (string)$input);
    $domains = [];
    $seen = [];

    foreach ($parts as $part) {
        $normalized = rewriteNormalizeDomain($part);
        if ($normalized !== '' && !isset($seen[$normalized])) {
            $domains[] = $normalized;
            $seen[$normalized] = true;
        }
    }

    return $domains;
}

function rewriteLoadVpsList($vpsFile = null) {
    if ($vpsFile === null) {
        $vpsFile = __DIR__ . '/vps.json';
    }
    if (!file_exists($vpsFile)) {
        return [];
    }

    $rows = json_decode((string)file_get_contents($vpsFile), true);
    return is_array($rows) ? $rows : [];
}

function rewriteFindVpsByIp($ip, array $vpsList) {
    $ip = trim((string)$ip);
    if ($ip === '') {
        return null;
    }

    foreach ($vpsList as $vps) {
        if (!is_array($vps)) {
            continue;
        }
        if (trim((string)($vps['ip'] ?? '')) === $ip) {
            return $vps;
        }
    }

    return null;
}

function rewriteResolveApiFromVps(array $vps) {
    $apiKey = trim((string)($vps['aapanel_keyapi'] ?? ($vps['api_key'] ?? ($vps['x_http_token'] ?? ($vps['api_token'] ?? '')))));
    $panelUrl = trim((string)($vps['info'] ?? ''));

    if ($panelUrl === '') {
        $ip = trim((string)($vps['ip'] ?? ''));
        if ($ip !== '') {
            $panelUrl = 'http://' . $ip . ':8888';
        }
    }

    if ($panelUrl !== '' && stripos($panelUrl, 'http') !== 0) {
        $panelUrl = 'http://' . $panelUrl;
    }

    $parsed = parse_url($panelUrl);
    if (is_array($parsed) && !empty($parsed['host'])) {
        $scheme = $parsed['scheme'] ?? 'http';
        $portPart = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $panelHost = (string)$parsed['host'];
        $vpsIp = trim((string)($vps['ip'] ?? ''));
        if (filter_var($panelHost, FILTER_VALIDATE_IP) !== false
            && filter_var($vpsIp, FILTER_VALIDATE_IP) !== false
            && strcasecmp($panelHost, $vpsIp) !== 0) {
            $panelHost = $vpsIp;
        }
        if (strpos($panelHost, ':') !== false && $panelHost[0] !== '[') {
            $panelHost = '[' . $panelHost . ']';
        }
        $origin = $scheme . '://' . $panelHost . $portPart;
        $sessionPath = trim((string)($vps['aapanel_session_path'] ?? ''), '/');
        if ($sessionPath === '') {
            $configuredPath = trim((string)($parsed['path'] ?? ''), '/');
            if (preg_match('/^apsess_[a-z0-9]+$/i', $configuredPath)) {
                $sessionPath = $configuredPath;
            }
        }
        if ($sessionPath !== '' && preg_match('/^apsess_[a-z0-9]+$/i', $sessionPath)) {
            $panelUrl = $origin . '/' . $sessionPath;
        } else {
            $panelUrl = $origin;
        }
    }

    return [
        'api_url' => $panelUrl,
        'api_key' => $apiKey,
    ];
}

function rewriteHasVpsSshCredentials(array $vps) {
    return trim((string)($vps['ip'] ?? '')) !== ''
        && trim((string)($vps['password'] ?? '')) !== '';
}

function rewriteShouldPreferSsh($apiUrl, array $vps) {
    if (!rewriteHasVpsSshCredentials($vps)) {
        return false;
    }

    $parsed = parse_url((string)$apiUrl);
    $panelHost = strtolower(trim((string)($parsed['host'] ?? ''), '[]'));
    $vpsIp = strtolower(trim((string)($vps['ip'] ?? ''), '[]'));
    return $panelHost !== '' && $vpsIp !== '' && $panelHost !== $vpsIp;
}

function rewriteOpenVpsSsh(array $vps) {
    if (!rewriteHasVpsSshCredentials($vps)) {
        return ['ssh' => null, 'error' => 'Thiếu thông tin SSH của VPS trong vps.json.'];
    }

    $autoloadFile = __DIR__ . '/vendor/autoload.php';
    if (!is_file($autoloadFile)) {
        return ['ssh' => null, 'error' => 'Không tìm thấy vendor/autoload.php để sử dụng SSH.'];
    }
    require_once $autoloadFile;

    if (!class_exists(\phpseclib3\Net\SSH2::class)) {
        return ['ssh' => null, 'error' => 'Thư viện phpseclib3 chưa được cài đặt.'];
    }

    try {
        $ssh = new \phpseclib3\Net\SSH2(
            trim((string)$vps['ip']),
            22,
            12
        );
        $ssh->setTimeout(30);
        $username = trim((string)($vps['username'] ?? '')) ?: 'root';
        if (!$ssh->login($username, (string)$vps['password'])) {
            return ['ssh' => null, 'error' => 'Đăng nhập SSH VPS thất bại.'];
        }

        return ['ssh' => $ssh, 'error' => ''];
    } catch (Throwable $e) {
        return ['ssh' => null, 'error' => 'Không kết nối được SSH VPS: ' . $e->getMessage()];
    }
}

function rewriteVpsSshCommand($ssh, $command) {
    $output = $ssh->exec($command . '; printf "\\n__REWRITE_EXIT:%s\\n" "$?"');
    if (!is_string($output)
        || !preg_match('/(?:^|\n)__REWRITE_EXIT:(\d+)\s*$/', $output, $matches)) {
        return [
            'success' => false,
            'output' => trim((string)$output),
            'error' => 'Không nhận được trạng thái lệnh từ SSH VPS.',
        ];
    }

    $output = preg_replace('/(?:^|\n)__REWRITE_EXIT:\d+\s*$/', '', $output);
    $exitCode = (int)$matches[1];
    return [
        'success' => $exitCode === 0,
        'output' => trim((string)$output),
        'error' => $exitCode === 0 ? '' : (trim((string)$output) ?: 'Lệnh SSH thất bại, exit code ' . $exitCode),
    ];
}

function rewriteShellQuote($value) {
    return "'" . str_replace("'", "'\\''", (string)$value) . "'";
}

function rewriteBuildAaPanelApiConfigFromVps(array $vps) {
    $resolved = rewriteResolveApiFromVps($vps);
    $baseUrl = trim((string)($resolved['api_url'] ?? ''));
    $apiKey = trim((string)($resolved['api_key'] ?? ''));

    return [
        'panel_url' => $baseUrl,
        'site_endpoint' => rewriteBuildApiUrl($baseUrl, '/site?action=SetRewriteConfig'),
        'save_file_endpoint' => rewriteBuildApiUrl($baseUrl, '/v2/files?action=SaveFileBody'),
        'api_key' => $apiKey,
        'api_key_masked' => $apiKey !== '' ? (substr($apiKey, 0, 4) . str_repeat('*', max(strlen($apiKey) - 8, 0)) . substr($apiKey, -4)) : '',
    ];
}

function rewriteGetZoneCandidates($domain) {
    $candidates = [];

    $aliases = rewriteBuildDomainAliases($domain);
    foreach ($aliases as $aliasDomain) {
        $parts = explode('.', strtolower(trim((string)$aliasDomain)));
        $parts = array_values(array_filter($parts, static function ($item) {
            return $item !== '';
        }));

        $count = count($parts);
        if ($count < 2) {
            continue;
        }

        for ($i = 0; $i <= $count - 2; $i++) {
            $candidates[] = implode('.', array_slice($parts, $i));
        }
    }

    return array_values(array_unique($candidates));
}

function rewriteGetCloudflareClient() {
    try {
        return new CloudflareAPI();
    } catch (Throwable $e) {
        return null;
    }
}

function rewriteFindZoneIdForDomain($cf, $domain, &$zoneName = '') {
    $zoneName = '';
    if (!$cf) {
        return null;
    }

    $candidates = rewriteGetZoneCandidates($domain);
    foreach ($candidates as $candidate) {
        $zoneId = $cf->getZoneIdByDomain($candidate);
        if ($zoneId) {
            $zoneName = $candidate;
            return $zoneId;
        }
    }

    // Fallback: tải danh sách zones (1 trang lớn) để đối chiếu chính xác theo name.
    $zoneListResp = $cf->listZones(1, 100, false);
    if (isset($zoneListResp['success']) && $zoneListResp['success'] === true && !empty($zoneListResp['result']) && is_array($zoneListResp['result'])) {
        $zoneMap = [];
        foreach ($zoneListResp['result'] as $zone) {
            $name = strtolower(trim((string)($zone['name'] ?? '')));
            $id = trim((string)($zone['id'] ?? ''));
            if ($name !== '' && $id !== '') {
                $zoneMap[$name] = $id;
            }
        }

        foreach ($candidates as $candidate) {
            $candidate = strtolower(trim((string)$candidate));
            if (isset($zoneMap[$candidate])) {
                $zoneName = $candidate;
                return $zoneMap[$candidate];
            }
        }
    }

    return null;
}

function rewriteExtractRecordIp(array $records, $exactName) {
    $exactName = strtolower(trim((string)$exactName));
    foreach ($records as $record) {
        $type = strtoupper((string)($record['type'] ?? ''));
        $name = strtolower(trim((string)($record['name'] ?? '')));
        $content = trim((string)($record['content'] ?? ''));
        if ($name !== $exactName) {
            continue;
        }
        if (($type === 'A' || $type === 'AAAA') && filter_var($content, FILTER_VALIDATE_IP)) {
            return $content;
        }
    }
    return '';
}

function rewriteFindOriginIpByDomain($cf, $zoneId, $domain) {
    if (!$cf || !$zoneId) {
        return '';
    }

    try {
        $resp = $cf->listDNSRecords($zoneId, null, $domain, false);
        $records = $resp['result'] ?? [];
        $ip = rewriteExtractRecordIp($records, $domain);
        if ($ip !== '') {
            return $ip;
        }
    } catch (Throwable $e) {
    }

    try {
        $resp = $cf->listDNSRecords($zoneId, 'A', $domain, false);
        $records = $resp['result'] ?? [];
        foreach ($records as $record) {
            $content = trim((string)($record['content'] ?? ''));
            if (filter_var($content, FILTER_VALIDATE_IP)) {
                return $content;
            }
        }
    } catch (Throwable $e) {
    }

    return '';
}

function rewriteResolveVpsByDomain($cf, $domain, array $vpsList) {
    $zoneName = '';
    $zoneCandidates = rewriteGetZoneCandidates($domain);
    $zoneId = rewriteFindZoneIdForDomain($cf, $domain, $zoneName);
    if (!$zoneId) {
        return [
            'success' => false,
            'error' => 'Không tìm thấy zone trên Cloudflare. Candidates đã thử: ' . implode(', ', $zoneCandidates),
            'zone_candidates' => $zoneCandidates,
        ];
    }

    function rewriteResolveSelectedVps($domain, $vpsIp, array $vpsList) {
        $vps = rewriteFindVpsByIp($vpsIp, $vpsList);
        if (!$vps) {
            return [
                'success' => false,
                'error' => 'VPS được chọn không tồn tại trong vps.json: ' . $vpsIp,
            ];
        }

        $apiConfig = rewriteBuildAaPanelApiConfigFromVps($vps);
        $hasApi = !empty($apiConfig['panel_url']) && !empty($apiConfig['api_key']);
        if (!$hasApi && !rewriteHasVpsSshCredentials($vps)) {
            return [
                'success' => false,
                'error' => 'VPS được chọn thiếu API URL/API Key và thông tin SSH trong vps.json',
            ];
        }

        return [
            'success' => true,
            'origin_ip' => $vpsIp,
            'vps' => $vps,
            'api_url' => $apiConfig['panel_url'],
            'api_key' => $apiConfig['api_key'],
            'api_config' => $apiConfig,
            'api_available' => $hasApi,
        ];
    }

    $originIp = rewriteFindOriginIpByDomain($cf, $zoneId, $domain);
    if ($originIp === '') {
        return [
            'success' => false,
            'error' => 'Không tìm thấy bản ghi A/AAAA cho domain trên Cloudflare',
            'zone_id' => $zoneId,
            'zone_name' => $zoneName,
        ];
    }

    $vps = rewriteFindVpsByIp($originIp, $vpsList);
    if (!$vps) {
        return [
            'success' => false,
            'error' => 'IP từ Cloudflare không khớp VPS trong vps.json: ' . $originIp,
            'zone_id' => $zoneId,
            'zone_name' => $zoneName,
            'origin_ip' => $originIp,
        ];
    }

    $apiConfig = rewriteBuildAaPanelApiConfigFromVps($vps);
    $api = [
        'api_url' => $apiConfig['panel_url'] ?? '',
        'api_key' => $apiConfig['api_key'] ?? '',
    ];
    $hasApi = !empty($api['api_url']) && !empty($api['api_key']);
    if (!$hasApi && !rewriteHasVpsSshCredentials($vps)) {
        return [
            'success' => false,
            'error' => 'VPS khớp nhưng thiếu API URL/API Key và thông tin SSH trong vps.json',
            'zone_id' => $zoneId,
            'zone_name' => $zoneName,
            'origin_ip' => $originIp,
            'vps_ip' => $vps['ip'] ?? '',
        ];
    }

    return [
        'success' => true,
        'zone_id' => $zoneId,
        'zone_name' => $zoneName,
        'origin_ip' => $originIp,
        'vps' => $vps,
        'api_url' => $api['api_url'],
        'api_key' => $api['api_key'],
        'api_config' => $apiConfig,
        'api_available' => $hasApi,
    ];
}

function rewriteBuildAaPanelHeaders($apiUrl, $apiKey, array $vps = []) {
    $headers = [
        'x-http-token: ' . $apiKey,
        'Content-Type: application/x-www-form-urlencoded',
        'Accept: application/json, text/plain, */*',
    ];
    $parsed = parse_url((string)$apiUrl);
    if (is_array($parsed) && !empty($parsed['host'])) {
        $scheme = $parsed['scheme'] ?? 'https';
        $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $origin = $scheme . '://' . $parsed['host'] . $port;
        $headers[] = 'Origin: ' . $origin;
        $headers[] = 'Referer: ' . rtrim((string)$apiUrl, '/') . '/wp/toolkit';
    }

    return $headers;
}

function rewriteAaPanelRequest($apiUrl, $apiKey, $endpoint, array $data = [], array $vps = []) {
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
        CURLOPT_HTTPHEADER => rewriteBuildAaPanelHeaders($apiUrl, $apiKey, $vps),
        CURLOPT_COOKIE => trim((string)($vps['aapanel_cookie'] ?? '')),
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
        return ['status' => false, 'msg' => 'Invalid JSON: ' . substr((string)$resp, 0, 200), 'raw' => (string)$resp];
    }

    return $decoded;
}

function rewriteBuildApiUrl($apiUrl, $endpoint) {
    $base = rtrim((string)$apiUrl, '/');
    $ep = ltrim((string)$endpoint, '/');

    // Chuẩn hóa URL: giữ apsess_* path, bỏ các path login/hash khác.
    $parsed = parse_url($base);
    if (is_array($parsed) && !empty($parsed['host'])) {
        $scheme = $parsed['scheme'] ?? 'http';
        $portPart = isset($parsed['port']) ? ':' . $parsed['port'] : '';
        $origin = $scheme . '://' . $parsed['host'] . $portPart;
        $path = trim((string)($parsed['path'] ?? ''), '/');
        if ($path !== '' && preg_match('/^apsess_/i', $path)) {
            $base = $origin . '/' . $path;
        } else {
            $base = $origin;
        }
    }

    // Hỗ trợ cả khi người dùng nhập base URL hoặc URL endpoint đầy đủ.
    if (preg_match('#/v2/files\?action=SaveFileBody$#i', $base)) {
        if (stripos($endpoint, '/v2/files?action=SaveFileBody') !== false) {
            return $base;
        }
        $base = preg_replace('#/v2/files\?action=SaveFileBody$#i', '', $base);
    }

    if (preg_match('#/site\?action=.*$#i', $base)) {
        $base = preg_replace('#/site\?action=.*$#i', '', $base);
    }

    if (preg_match('#/v2/site\?action=.*$#i', $base)) {
        $base = preg_replace('#/v2/site\?action=.*$#i', '', $base);
    }

    return $base . '/' . $ep;
}

function rewriteAaPanelRequestRaw($apiUrl, $apiKey, $endpoint, array $data = [], array $vps = []) {
    $isSaveFileBody = stripos($endpoint, 'SaveFileBody') !== false;
    $hasApsessPath = preg_match('#/apsess_[^/]+#i', (string)$apiUrl) === 1;

    // Với endpoint SaveFileBody kiểu apsess, bám sát request thực tế từ panel: không ép request_time/request_token.
    if (!($isSaveFileBody && $hasApsessPath)) {
        $now = time();
        $data['request_time'] = $now;
        $data['request_token'] = md5($now . md5($apiKey));
    }

    $url = rewriteBuildApiUrl($apiUrl, $endpoint);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false,
        CURLOPT_TIMEOUT => 90,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_HTTPHEADER => rewriteBuildAaPanelHeaders($apiUrl, $apiKey, $vps),
        CURLOPT_COOKIE => trim((string)($vps['aapanel_cookie'] ?? '')),
    ]);

    $resp = curl_exec($ch);
    $errno = curl_errno($ch);
    $emsg = curl_error($ch);
    curl_close($ch);

    if ($errno) {
        return ['status' => false, 'msg' => 'cURL #' . $errno . ': ' . $emsg, '_raw_url' => $url];
    }

    $decoded = json_decode((string)$resp, true);
    if ($decoded === null) {
        return ['status' => false, 'msg' => 'Invalid JSON: ' . substr((string)$resp, 0, 200), 'raw' => (string)$resp, '_raw_url' => $url];
    }

    $decoded['_raw_url'] = $url;
    return $decoded;
}

function rewriteExtractAaPanelSiteRows($value, array &$rows) {
    if (!is_array($value)) {
        return;
    }

    foreach (['domain', 'domains', 'domain_list', 'name', 'webname'] as $field) {
        if (array_key_exists($field, $value)) {
            $rows[] = $value;
            break;
        }
    }

    foreach ($value as $child) {
        if (is_array($child)) {
            rewriteExtractAaPanelSiteRows($child, $rows);
        }
    }
}

function rewriteSiteRowsToTable(array $rows) {
    $table = [];
    $seen = [];
    foreach ($rows as $site) {
        $domain = '';
        foreach (['domain', 'domains', 'domain_list', 'name', 'webname'] as $domainField) {
            if (!array_key_exists($domainField, $site)) {
                continue;
            }
            $candidate = is_array($site[$domainField])
                ? implode("\n", array_map('strval', $site[$domainField]))
                : trim((string)$site[$domainField]);
            if ($candidate !== '') {
                $domain = $candidate;
                break;
            }
        }
        $sId = $site['id'] ?? $site['s_id'] ?? '';
        $key = $domain . '|' . $sId;

        if ($domain !== '' && !isset($seen[$key])) {
            $siteDomains = $site['domain'] ?? ($site['domains'] ?? ($site['domain_list'] ?? $domain));
            if (is_array($siteDomains)) {
                $siteDomains = implode("\n", array_map('strval', $siteDomains));
            }
            $table[] = [
                'domain' => $domain,
                'site_domains' => (string)$siteDomains,
                'webname' => $site['name'] ?? ($site['webname'] ?? $domain),
                's_id' => $sId,
                'path' => $site['path'] ?? '',
            ];
            $seen[$key] = true;
        }
    }

    return $table;
}

function rewriteFetchSiteRowsViaSsh(array $vps) {
    $connection = rewriteOpenVpsSsh($vps);
    if (!$connection['ssh']) {
        return [
            'rows' => [],
            'complete' => false,
            'error' => $connection['error'],
        ];
    }

    $ssh = $connection['ssh'];
    $queries = [
        'SELECT s.id, s.name, s.path, COALESCE(d.name, \'\') FROM sites AS s LEFT JOIN domain AS d ON d.pid = s.id ORDER BY s.id;',
        'SELECT id, name, path, name FROM sites ORDER BY id;',
        'SELECT name FROM sites;',
    ];
    $errors = [];

    foreach ($queries as $query) {
        $command = 'sqlite3 -noheader -separator ' . rewriteShellQuote('|')
            . ' /www/server/panel/data/default.db ' . rewriteShellQuote($query) . ' 2>&1';
        $result = rewriteVpsSshCommand($ssh, $command);
        if (!$result['success']) {
            $errors[] = $result['error'];
            continue;
        }

        return [
            'rows' => rewriteParseSshSiteRows($result['output']),
            'complete' => true,
            'error' => '',
        ];
    }

    return [
        'rows' => [],
        'complete' => false,
        'error' => implode(' | ', array_values(array_unique($errors))),
    ];
}

function rewriteParseSshSiteRows($output) {
    $rows = [];
    foreach (preg_split('/\r\n|\r|\n/', (string)$output) as $line) {
        if (trim($line) === '') {
            continue;
        }

        $fields = explode('|', $line, 4);
        if (count($fields) >= 4) {
            [$id, $name, $path, $domain] = $fields;
        } elseif (count($fields) >= 3) {
            [$id, $name, $path] = $fields;
            $domain = $name;
        } else {
            $name = trim((string)$fields[0]);
            $id = '';
            $path = '';
            $domain = $name;
        }

        $name = trim((string)$name);
        if ($name === '') {
            continue;
        }
        $domains = array_values(array_unique(array_filter([
            $name,
            trim((string)$domain),
        ])));
        $rows[] = [
            'id' => trim((string)$id),
            's_id' => trim((string)$id),
            'name' => $name,
            'webname' => $name,
            'path' => trim((string)$path),
            'domain' => implode("\n", $domains),
        ];
    }

    return $rows;
}

function rewriteFetchSiteRowsFromEndpoint($apiUrl, $apiKey, $endpoint, $searchDomain = '', array $vps = []) {
    $pageSize = 500;
    $maxPages = 100;
    $rows = [];
    $seenRows = [];
    $previousPageSignature = '';

    for ($page = 1; $page <= $maxPages; $page++) {
        $payload = [
            'p' => $page,
            'page' => $page,
            'limit' => $pageSize,
        ];
        if ($searchDomain !== '') {
            $payload['search'] = $searchDomain;
        }

        $response = rewriteAaPanelRequest($apiUrl, $apiKey, $endpoint, $payload, $vps);
        if (!is_array($response)
            || (isset($response['status']) && $response['status'] === false)
            || (isset($response['success']) && $response['success'] === false)
            || (isset($response['code']) && (int)$response['code'] !== 0)) {
            return [
                'rows' => $rows,
                'complete' => false,
                'error' => rewriteExtractResponseMessage(is_array($response) ? $response : []),
            ];
        }

        $pageRows = [];
        rewriteExtractAaPanelSiteRows($response, $pageRows);
        if (empty($pageRows)
            && !is_array($response['data'] ?? null)
            && !is_array($response['message'] ?? null)) {
            return [
                'rows' => $rows,
                'complete' => false,
                'error' => rewriteExtractResponseMessage($response),
            ];
        }

        $totalRows = rewriteFindAaPanelSiteTotal($response);
        if (empty($pageRows) && $searchDomain !== '') {
            return ['rows' => $rows, 'complete' => true, 'error' => ''];
        }
        if (empty($pageRows) && $totalRows !== null && $totalRows > count($rows)) {
            return [
                'rows' => $rows,
                'complete' => false,
                'error' => 'aaPanel báo còn site nhưng trang hiện tại không trả về dữ liệu.',
            ];
        }

        $signature = md5((string)json_encode($pageRows));
        if ($page > 1 && $signature === $previousPageSignature && !empty($pageRows)) {
            return [
                'rows' => $rows,
                'complete' => false,
                'error' => 'aaPanel trả lặp lại cùng một trang site; dừng phân trang để tránh kết quả thiếu.',
            ];
        }
        $previousPageSignature = $signature;

        foreach ($pageRows as $row) {
            $rowKey = (string)json_encode($row);
            if (!isset($seenRows[$rowKey])) {
                $rows[] = $row;
                $seenRows[$rowKey] = true;
            }
        }

        $hasMoreByTotal = $totalRows !== null
            && $totalRows > (($page - 1) * count($pageRows) + count($pageRows));
        if (!$hasMoreByTotal && count($pageRows) < $pageSize) {
            return ['rows' => $rows, 'complete' => true, 'error' => ''];
        }
    }

    return [
        'rows' => $rows,
        'complete' => false,
        'error' => 'Đã đạt giới hạn ' . $maxPages . ' trang khi lấy danh sách site aaPanel.',
    ];
}

function rewriteFindAaPanelSiteTotal($value) {
    if (!is_array($value)) {
        return null;
    }

    foreach (['total', 'total_count', 'totalCount', 'recordsTotal', 'records_total'] as $key) {
        if (isset($value[$key]) && is_numeric($value[$key])) {
            return max(0, (int)$value[$key]);
        }
    }

    foreach ($value as $child) {
        if (is_array($child)) {
            $total = rewriteFindAaPanelSiteTotal($child);
            if ($total !== null) {
                return $total;
            }
        }
    }

    return null;
}

function rewriteFetchSiteTable($apiUrl, $apiKey, $searchDomain = '', array $vps = []) {
    $normalizedSearch = rewriteNormalizeDomainForVpsLookup($searchDomain);
    $hasApi = trim((string)$apiUrl) !== '' && trim((string)$apiKey) !== '';
    $endpoints = $hasApi ? [
        '/v2/site?action=get_site_list',
        '/site?action=GetSiteList',
    ] : [];
    $allRows = [];
    $seenRows = [];
    $errors = [];
    $lookupComplete = false;
    if (!$hasApi) {
        $errors[] = 'Thiếu API URL/API Key aaPanel.';
    }

    if (rewriteHasVpsSshCredentials($vps)) {
        $sshResult = rewriteFetchSiteRowsViaSsh($vps);
        if ($sshResult['complete']) {
            $sshMap = rewriteBuildDomainMap(rewriteSiteRowsToTable($sshResult['rows']));
            if ($normalizedSearch === '' || isset($sshMap[$normalizedSearch])) {
                return [
                    'site_table' => rewriteSiteRowsToTable($sshResult['rows']),
                    'raw' => $sshResult['rows'],
                    'lookup_complete' => true,
                    'lookup_errors' => [],
                    'lookup_method' => 'SSH SQLite',
                ];
            }
            $errors[] = 'SSH SQLite: đã đọc danh sách site nhưng không tìm thấy domain.';
        } else {
            $errors[] = 'SSH SQLite: ' . $sshResult['error'];
        }
    }

    foreach ($endpoints as $endpoint) {
        if ($normalizedSearch !== '') {
            $searchResult = rewriteFetchSiteRowsFromEndpoint(
                $apiUrl,
                $apiKey,
                $endpoint,
                $normalizedSearch,
                $vps
            );
            foreach ($searchResult['rows'] as $row) {
                $rowKey = (string)json_encode($row);
                if (!isset($seenRows[$rowKey])) {
                    $allRows[] = $row;
                    $seenRows[$rowKey] = true;
                }
            }

            $searchMap = rewriteBuildDomainMap(rewriteSiteRowsToTable($searchResult['rows']));
            if (isset($searchMap[$normalizedSearch])) {
                return [
                    'site_table' => rewriteSiteRowsToTable($allRows),
                    'raw' => $searchResult['rows'],
                    'lookup_complete' => true,
                    'lookup_errors' => [],
                    'lookup_method' => 'aaPanel API domain search',
                ];
            }
            if ($searchResult['error'] !== '') {
                $errors[] = $endpoint . ': ' . $searchResult['error'];
            }
        }

        $fullResult = rewriteFetchSiteRowsFromEndpoint($apiUrl, $apiKey, $endpoint, '', $vps);
        foreach ($fullResult['rows'] as $row) {
            $rowKey = (string)json_encode($row);
            if (!isset($seenRows[$rowKey])) {
                $allRows[] = $row;
                $seenRows[$rowKey] = true;
            }
        }
        $lookupComplete = $lookupComplete || $fullResult['complete'];
        if ($fullResult['error'] !== '') {
            $errors[] = $endpoint . ': ' . $fullResult['error'];
        }

        $siteMap = rewriteBuildDomainMap(rewriteSiteRowsToTable($allRows));
        if ($normalizedSearch !== '' && isset($siteMap[$normalizedSearch])) {
            return [
                'site_table' => rewriteSiteRowsToTable($allRows),
                'raw' => $allRows,
                'lookup_complete' => true,
                'lookup_errors' => [],
                'lookup_method' => 'aaPanel API site list',
            ];
        }
    }

    return [
        'site_table' => rewriteSiteRowsToTable($allRows),
        'raw' => $allRows,
        'lookup_complete' => $lookupComplete,
        'lookup_errors' => $lookupComplete ? [] : array_values(array_unique($errors)),
        'lookup_method' => 'aaPanel API fallback',
    ];
}

function rewriteFindSiteInVps($apiUrl, $apiKey, $domain, array $vps = []) {
    $normalized = rewriteNormalizeDomainForVpsLookup($domain);
    if ($normalized === '') {
        return [
            'site' => null,
            'lookup_complete' => false,
            'lookup_errors' => ['Domain không hợp lệ để tìm trên VPS.'],
        ];
    }

    $siteResult = rewriteFetchSiteTable($apiUrl, $apiKey, $normalized, $vps);
    $domainMap = rewriteBuildDomainMap($siteResult['site_table'] ?? []);
    return [
        'site' => $domainMap[$normalized] ?? null,
        'lookup_complete' => !empty($siteResult['lookup_complete']),
        'lookup_errors' => $siteResult['lookup_errors'] ?? [],
    ];
}

function rewriteBuildDomainMap(array $siteTable) {
    $map = [];

    foreach ($siteTable as $site) {
        $rawDomainText = (string)($site['site_domains'] ?? ($site['domain'] ?? ''));
        $decodedDomainList = json_decode($rawDomainText, true);
        if (is_array($decodedDomainList)) {
            $rawDomainText = implode("\n", array_map('strval', $decodedDomainList));
        } else {
            // aaPanel có thể lưu domain dưới dạng chuỗi với dấu [] hoặc dấu nháy.
            $rawDomainText = str_replace(['[', ']', '"', "'"], ' ', $rawDomainText);
        }

        $rawDomains = preg_split('/[\r\n,;\s]+/', $rawDomainText);
        foreach ($rawDomains as $singleDomain) {
            $singleDomain = trim((string)$singleDomain);
            if ($singleDomain === '') {
                continue;
            }

            $normalized = rewriteNormalizeDomain($singleDomain);
            if ($normalized !== '' && !isset($map[$normalized])) {
                $map[$normalized] = [
                    'webname' => trim((string)($site['webname'] ?? $singleDomain)),
                    's_id' => $site['s_id'] ?? '',
                    'domain' => trim((string)$singleDomain),
                    'site_domains' => $rawDomainText,
                    'path' => trim((string)($site['path'] ?? '')),
                ];
            }
        }

        // Fallback: một số server dùng name/webname như domain chính.
        $webname = trim((string)($site['webname'] ?? ''));
        $webnameDomain = rewriteNormalizeDomain($webname);
        if ($webnameDomain !== '' && !isset($map[$webnameDomain])) {
            $map[$webnameDomain] = [
                'webname' => $webname,
                's_id' => $site['s_id'] ?? '',
                'domain' => $webname,
                'site_domains' => $rawDomainText,
                'path' => trim((string)($site['path'] ?? '')),
            ];
        }
    }

    return $map;
}

function rewriteNormalizeDomainWithWww($domain) {
    $domain = trim((string)$domain);
    if ($domain === '') {
        return '';
    }
    if (!preg_match('#^https?://#i', $domain)) {
        $domain = 'http://' . $domain;
    }
    $host = parse_url($domain, PHP_URL_HOST);
    return $host ? strtolower(rtrim(trim($host), '.')) : '';
}

function rewriteNormalizeDomainForVpsLookup($domain) {
    $host = rewriteNormalizeDomainWithWww($domain);
    return preg_replace('/^www\./i', '', $host);
}

function rewriteSiteHasDomain(array $site, $targetDomain) {
    $target = rewriteNormalizeDomainWithWww($targetDomain);
    if ($target === '') {
        return false;
    }

    $rawDomainText = (string)($site['site_domains'] ?? ($site['domain'] ?? ''));
    $decodedDomainList = json_decode($rawDomainText, true);
    if (is_array($decodedDomainList)) {
        $rawDomainText = implode("\n", array_map('strval', $decodedDomainList));
    } else {
        $rawDomainText = str_replace(['[', ']', '"', "'"], ' ', $rawDomainText);
    }

    foreach (preg_split('/[\r\n,;\s]+/', $rawDomainText) as $domain) {
        if (rewriteNormalizeDomainWithWww($domain) === $target) {
            return true;
        }
    }

    return rewriteNormalizeDomainWithWww($site['webname'] ?? '') === $target;
}

function rewriteBuildAddWwwRequests(array $site, $wwwDomain) {
    $sId = (string)($site['s_id'] ?? '');
    $webname = (string)($site['webname'] ?? '');
    $wwwDomain = (string)$wwwDomain;

    return [
        [
            'endpoint' => '/site?action=AddDomain',
            'label' => 'DomainManager AddDomain(id,webname,domain)',
            'payload' => [
                'id' => $sId,
                'webname' => $webname,
                'domain' => $wwwDomain,
                'port' => '80',
                'type' => '0',
            ],
        ],
        [
            'endpoint' => '/site?action=AddDomain',
            'label' => 'DomainManager AddDomain(id,domain,port)',
            'payload' => [
                'id' => $sId,
                'domain' => $wwwDomain,
                'port' => '80',
            ],
        ],
        [
            'endpoint' => '/site?action=AddDomain',
            'label' => 'DomainManager AddDomain(s_id,domain)',
            'payload' => [
                's_id' => $sId,
                'domain' => $wwwDomain,
            ],
        ],
    ];
}

function rewriteAddWwwDomain($apiUrl, $apiKey, array $site, $wwwDomain, array $vps = []) {
    $attempts = [];
    foreach (rewriteBuildAddWwwRequests($site, $wwwDomain) as $request) {
        $response = rewriteAaPanelRequest($apiUrl, $apiKey, $request['endpoint'], $request['payload'], $vps);
        $rawResponseDomain = rewriteNormalizeDomainWithWww($response['raw'] ?? '');
        $expectedDomain = rewriteNormalizeDomainWithWww($wwwDomain);
        $plainDomainSuccess = $rawResponseDomain !== ''
            && ($rawResponseDomain === $expectedDomain
                || $rawResponseDomain === rewriteNormalizeDomainForVpsLookup($expectedDomain));
        $attempts[] = [
            'label' => $request['label'],
            'endpoint' => $request['endpoint'],
            'response' => $response,
        ];

        if (rewriteLooksSuccess($response) || $plainDomainSuccess) {
            if ($plainDomainSuccess && !rewriteLooksSuccess($response)) {
                $response = [
                    'status' => true,
                    'msg' => 'aaPanel đã thêm domain: ' . $rawResponseDomain,
                    'raw' => $response['raw'],
                    '_raw_url' => $response['_raw_url'] ?? '',
                ];
                $attempts[count($attempts) - 1]['response'] = $response;
            }
            return [
                'success' => true,
                'message' => $response['msg'] ?? 'Đã thêm www vào domain trên VPS',
                'attempt' => $request['label'],
                'response' => $response,
                'attempts' => $attempts,
            ];
        }
    }

    $last = end($attempts);
    return [
        'success' => false,
        'message' => rewriteExtractResponseMessage($last['response'] ?? []),
        'attempt' => $last['label'] ?? 'AddDomain',
        'response' => $last['response'] ?? [],
        'attempts' => $attempts,
    ];
}

function rewriteLooksSuccess(array $response) {
    if ((isset($response['status']) && $response['status'] === true)
        || (isset($response['code']) && (int)$response['code'] === 0)
        || (isset($response['success']) && $response['success'] === true)) {
        return true;
    }

    $msg = strtolower((string)($response['msg'] ?? $response['message'] ?? ''));
    if ($msg !== '' && preg_match('/success|succeed|done|ok|thanh cong|saved|complete|setting complete/', $msg)) {
        return true;
    }

    return false;
}

function rewriteBuildWordPressRequests(array $site, $ruleName = 'wordpress') {
    $webname = (string)($site['webname'] ?? '');
    $domain = (string)($site['domain'] ?? '');

    // Thử nhiều payload để tương thích nhiều phiên bản aaPanel.
    return [
        [
            'endpoint' => '/site?action=SetRewriteConfig',
            'label' => 'SetRewriteConfig(siteName,data)',
            'payload' => [
                'siteName' => $webname,
                'data' => $ruleName,
            ],
        ],
        [
            'endpoint' => '/site?action=SetRewriteConfig',
            'label' => 'SetRewriteConfig(vhostname,data)',
            'payload' => [
                'vhostname' => $webname,
                'data' => $ruleName,
            ],
        ],
        [
            'endpoint' => '/site?action=SetRewriteConfig',
            'label' => 'SetRewriteConfig(siteName,rewrite)',
            'payload' => [
                'siteName' => $webname,
                'rewrite' => $ruleName,
            ],
        ],
        [
            'endpoint' => '/site?action=SetRewrite',
            'label' => 'SetRewrite(siteName,rewrite)',
            'payload' => [
                'siteName' => $webname,
                'rewrite' => $ruleName,
            ],
        ],
        [
            'endpoint' => '/site?action=SetSiteRewrite',
            'label' => 'SetSiteRewrite(siteName,rewrite)',
            'payload' => [
                'siteName' => $webname,
                'rewrite' => $ruleName,
            ],
        ],
        [
            'endpoint' => '/site?action=SetRewriteConfig',
            'label' => 'SetRewriteConfig(vhostname,domain,data)',
            'payload' => [
                'vhostname' => $webname,
                'domain' => $domain,
                'data' => $ruleName,
            ],
        ],
    ];
}

function rewriteGetNginxWordPressContent() {
    return "location /\n{\n\t try_files \$uri \$uri/ /index.php?\$args;\n}\n\nrewrite /wp-admin\$ \$scheme://\$host\$uri/ permanent;\n";
}

function rewriteGetApacheWordPressContent() {
    return "<IfModule mod_rewrite.c>\nRewriteEngine On\nRewriteBase /\nRewriteRule ^index\\.php$ - [L]\nRewriteCond %{REQUEST_FILENAME} !-f\nRewriteCond %{REQUEST_FILENAME} !-d\nRewriteRule . /index.php [L]\n</IfModule>\n";
}

function rewriteBuildSaveFileRequests(array $site) {
    $domain = trim((string)($site['domain'] ?? ''));
    $path = trim((string)($site['path'] ?? ''));

    $targets = [];
    if ($domain !== '') {
        $targets[] = [
            'file_path' => '/www/server/panel/vhost/rewrite/' . $domain . '.conf',
            'content' => rewriteGetNginxWordPressContent(),
            'type' => 'nginx-rewrite-conf',
        ];
    }

    if ($path !== '') {
        $targets[] = [
            'file_path' => rtrim($path, '/') . '/.htaccess',
            'content' => rewriteGetApacheWordPressContent(),
            'type' => 'apache-htaccess',
        ];
    }

    $requests = [];
    $saveFileEndpoints = [
        '/v2/files?action=SaveFileBody',
        '/files?action=SaveFileBody',
    ];

    $buildPayloadVariants = static function ($filePath, $content) {
        $filePath = (string)$filePath;
        $content = (string)$content;

        return [
            [
                'suffix' => 'path,data',
                'payload' => [
                    'path' => $filePath,
                    'data' => $content,
                ],
            ],
            [
                'suffix' => 'path,data,encoding',
                'payload' => [
                    'path' => $filePath,
                    'data' => $content,
                    'encoding' => 'utf-8',
                ],
            ],
            [
                'suffix' => 'path,content,encoding',
                'payload' => [
                    'path' => $filePath,
                    'content' => $content,
                    'encoding' => 'utf-8',
                ],
            ],
            [
                'suffix' => 'file,data,encoding',
                'payload' => [
                    'file' => $filePath,
                    'data' => $content,
                    'encoding' => 'utf-8',
                ],
            ],
        ];
    };

    foreach ($targets as $target) {
        foreach ($saveFileEndpoints as $endpoint) {
            $variants = $buildPayloadVariants($target['file_path'], $target['content']);
            foreach ($variants as $variant) {
                $requests[] = [
                    'endpoint' => $endpoint,
                    'label' => 'SaveFileBody(' . $variant['suffix'] . ') [' . $target['type'] . '] [' . $endpoint . ']',
                    'payload' => $variant['payload'],
                ];
            }
        }
    }

    return $requests;
}

function rewriteExtractResponseMessage($response) {
    if (!is_array($response)) {
        return 'Phản hồi không hợp lệ từ aaPanel API';
    }

    $parts = [];
    $msg = trim((string)($response['msg'] ?? $response['message'] ?? ''));
    if ($msg !== '') {
        $parts[] = $msg;
    }

    if (isset($response['code'])) {
        $parts[] = 'code=' . (string)$response['code'];
    }

    if (isset($response['status']) && is_bool($response['status'])) {
        $parts[] = 'status=' . ($response['status'] ? 'true' : 'false');
    }

    if (!empty($response['_raw_url'])) {
        $parts[] = 'url=' . (string)$response['_raw_url'];
    }

    if (isset($response['errors']) && is_array($response['errors']) && !empty($response['errors'])) {
        $firstError = $response['errors'][0];
        if (is_array($firstError)) {
            $errText = trim((string)($firstError['message'] ?? json_encode($firstError, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)));
            if ($errText !== '') {
                $parts[] = 'error=' . $errText;
            }
        } else {
            $errText = trim((string)$firstError);
            if ($errText !== '') {
                $parts[] = 'error=' . $errText;
            }
        }
    }

    if (isset($response['raw']) && is_string($response['raw'])) {
        $rawPreview = trim(substr($response['raw'], 0, 160));
        if ($rawPreview !== '') {
            $parts[] = 'raw=' . $rawPreview;
        }
    }

    if (empty($parts)) {
        return 'Cập nhật rewrite thất bại (không có thông tin lỗi chi tiết từ aaPanel).';
    }

    return implode(' | ', $parts);
}

function rewriteCompactResponseForUi($response) {
    if (!is_array($response) || empty($response)) {
        return '';
    }

    $compact = [];
    if (array_key_exists('status', $response)) {
        $compact['status'] = $response['status'];
    }
    if (array_key_exists('code', $response)) {
        $compact['code'] = $response['code'];
    }
    if (isset($response['msg'])) {
        $compact['msg'] = $response['msg'];
    } elseif (isset($response['message'])) {
        $compact['message'] = $response['message'];
    }
    if (!empty($response['_raw_url'])) {
        $compact['url'] = $response['_raw_url'];
    }

    if (empty($compact)) {
        return '';
    }

    return json_encode($compact, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function rewriteApplyWordPressRuleViaSsh(array $vps, array $site) {
    $siteName = rewriteNormalizeDomainWithWww($site['webname'] ?? ($site['domain'] ?? ''));
    if ($siteName === '' || !preg_match('/^[a-z0-9.-]+$/i', $siteName) || strpos($siteName, '..') !== false) {
        return [
            'success' => false,
            'message' => 'Tên site aaPanel không an toàn để tạo đường dẫn rewrite.',
            'written_files' => [],
        ];
    }

    $targets = [[
        'path' => '/www/server/panel/vhost/rewrite/' . $siteName . '.conf',
        'content' => rewriteGetNginxWordPressContent(),
    ]];
    $sitePath = trim((string)($site['path'] ?? ''));
    if ($sitePath !== ''
        && strpos($sitePath, '/www/wwwroot/') === 0
        && !preg_match('#(?:^|/)\.\.(?:/|$)|[\r\n\0]#', $sitePath)) {
        $targets[] = [
            'path' => rtrim($sitePath, '/') . '/.htaccess',
            'content' => rewriteGetApacheWordPressContent(),
        ];
    }

    $connection = rewriteOpenVpsSsh($vps);
    if (!$connection['ssh']) {
        return [
            'success' => false,
            'message' => $connection['error'],
            'written_files' => [],
        ];
    }

    $writtenFiles = [];
    $errors = [];
    foreach ($targets as $target) {
        $temporaryPath = $target['path'] . '.tmp-' . bin2hex(random_bytes(6));
        $directory = dirname($target['path']);
        $command = 'test -d ' . rewriteShellQuote($directory)
            . ' && printf \'%s\' ' . rewriteShellQuote(base64_encode($target['content']))
            . ' | base64 -d > ' . rewriteShellQuote($temporaryPath)
            . ' && chmod 0644 ' . rewriteShellQuote($temporaryPath)
            . ' && mv -f ' . rewriteShellQuote($temporaryPath) . ' ' . rewriteShellQuote($target['path']);
        $result = rewriteVpsSshCommand($connection['ssh'], $command);
        if ($result['success']) {
            $writtenFiles[] = $target['path'];
        } else {
            $errors[] = $target['path'] . ': ' . $result['error'];
        }
    }

    return [
        'success' => !empty($writtenFiles),
        'message' => !empty($writtenFiles)
            ? 'Đã ghi rewrite WordPress qua SSH: ' . implode(', ', $writtenFiles)
            : implode(' | ', $errors),
        'written_files' => $writtenFiles,
        'errors' => $errors,
    ];
}

function rewriteGetAaPanelFileBody($apiUrl, $apiKey, $filePath, array $vps = []) {
    $response = rewriteAaPanelRequestRaw(
        $apiUrl,
        $apiKey,
        '/v2/files?action=GetFileBody',
        ['path' => (string)$filePath],
        $vps
    );
    $message = $response['message'] ?? null;
    if (is_array($message) && array_key_exists('data', $message) && is_string($message['data'])) {
        return ['success' => true, 'content' => $message['data'], 'response' => $response];
    }
    if (isset($response['data']) && is_string($response['data'])) {
        return ['success' => true, 'content' => $response['data'], 'response' => $response];
    }

    return [
        'success' => false,
        'content' => '',
        'response' => $response,
        'error' => rewriteExtractResponseMessage($response),
    ];
}

function rewriteApplyWordPressRule($apiUrl, $apiKey, array $site, array $vps = []) {
    $attempts = [];
    $requests = rewriteBuildWordPressRequests($site, 'wordpress');
    $requests = array_merge($requests, rewriteBuildSaveFileRequests($site));
    $preferSsh = rewriteShouldPreferSsh($apiUrl, $vps);
    if ($preferSsh) {
        $attempts[] = [
            'label' => 'aaPanel API skipped: panel host differs from resolved VPS',
            'response' => [
                'status' => false,
                'msg' => 'Bỏ qua API endpoint trên host khác VPS; ưu tiên SSH vào đúng VPS đã resolve.',
            ],
        ];
    }

    if (!$preferSsh && trim((string)$apiUrl) !== '' && trim((string)$apiKey) !== '') {
        foreach ($requests as $request) {
            $useRawRequest = stripos($request['endpoint'], '/v2/files?action=SaveFileBody') !== false
                || stripos($request['endpoint'], '/files?action=SaveFileBody') !== false;

            $response = $useRawRequest
                ? rewriteAaPanelRequestRaw($apiUrl, $apiKey, $request['endpoint'], $request['payload'], $vps)
                : rewriteAaPanelRequest($apiUrl, $apiKey, $request['endpoint'], $request['payload'], $vps);

            $attempts[] = [
                'label' => $request['label'],
                'endpoint' => $request['endpoint'],
                'payload' => $request['payload'],
                'response' => $response,
            ];

            if ($useRawRequest) {
                $filePath = (string)($request['payload']['path'] ?? ($request['payload']['file'] ?? ''));
                $expectedContent = $request['payload']['data'] ?? ($request['payload']['content'] ?? null);
                if ($filePath !== '' && is_string($expectedContent)) {
                    $readback = rewriteGetAaPanelFileBody($apiUrl, $apiKey, $filePath, $vps);
                    if ($readback['success'] && hash_equals($expectedContent, $readback['content'])) {
                        $verifiedResponse = [
                            'status' => true,
                            'msg' => 'SaveFileBody đã được xác minh bằng GetFileBody.',
                            'verified' => true,
                            '_raw_url' => $response['_raw_url'] ?? '',
                        ];
                        $attempts[count($attempts) - 1]['response'] = $verifiedResponse;
                        return [
                            'success' => true,
                            'message' => 'Đã lưu và xác minh rewrite file qua aaPanel API.',
                            'attempt' => $request['label'] . ' + GetFileBody verify',
                            'response' => $verifiedResponse,
                            'attempts' => $attempts,
                            'attempt_labels' => array_map(static function ($item) {
                                return (string)($item['label'] ?? 'unknown');
                            }, $attempts),
                        ];
                    }
                    $attempts[count($attempts) - 1]['verification'] = $readback['success']
                        ? 'GetFileBody trả về nội dung khác'
                        : ($readback['error'] ?? 'GetFileBody xác minh thất bại');
                }
                continue;
            }

            if (rewriteLooksSuccess($response)) {
                return [
                    'success' => true,
                    'message' => $response['msg'] ?? 'Đã cập nhật rewrite wordpress',
                    'attempt' => $request['label'],
                    'response' => $response,
                    'attempts' => $attempts,
                ];
            }

            if (strpos((string)($response['msg'] ?? ''), 'cURL #') === 0) {
                break;
            }
            $responseMessage = strtolower((string)($response['msg'] ?? $response['message'] ?? ''));
            if (strpos($responseMessage, 'invalid json:') === 0
                || preg_match('/specific parameters are invalid|invalid request parameters|unauthori[sz]ed|forbidden|permission|token|ip.*(match|allow|whitelist)|api.*(disabled|closed)/', $responseMessage)) {
                break;
            }
        }
    }

    $last = end($attempts);
    $lastResponse = $last['response'] ?? [];
    $lastMessage = rewriteExtractResponseMessage(is_array($lastResponse) ? $lastResponse : []);
    if (rewriteHasVpsSshCredentials($vps)) {
        $sshResult = rewriteApplyWordPressRuleViaSsh($vps, $site);
        if ($sshResult['success']) {
            $sshResponse = [
                'status' => true,
                'msg' => $sshResult['message'],
                'method' => 'ssh-direct-file-write',
            ];
            return [
                'success' => true,
                'message' => $sshResult['message'],
                'attempt' => 'SSH direct rewrite file fallback',
                'response' => $sshResponse,
                'attempts' => $attempts,
                'attempt_labels' => array_merge(
                    array_map(static function ($item) {
                        return (string)($item['label'] ?? 'unknown');
                    }, $attempts),
                    ['SSH direct rewrite file fallback']
                ),
            ];
        }
        $lastMessage .= ' | SSH fallback: ' . $sshResult['message'];
    }

    $attemptLabels = array_map(static function ($item) {
        return (string)($item['label'] ?? 'unknown');
    }, $attempts);

    return [
        'success' => false,
        'message' => $lastMessage,
        'attempt' => $preferSsh ? 'SSH direct rewrite failed' : ($last['label'] ?? 'unknown'),
        'response' => $lastResponse,
        'attempt_labels' => rewriteHasVpsSshCredentials($vps)
            ? array_merge($attemptLabels, ['SSH direct rewrite file fallback'])
            : $attemptLabels,
        'attempts' => $attempts,
    ];
}

function rewriteApplyWordPressRuleDirect($apiUrl, $apiKey, $domain, array $vps = []) {
    $domain = rewriteNormalizeDomain((string)$domain);
    if ($domain === '') {
        return [
            'success' => false,
            'message' => 'Domain không hợp lệ để cập nhật trực tiếp',
            'attempt' => 'direct-invalid-domain',
            'response' => [],
            'attempts' => [],
        ];
    }

    // Fallback trực tiếp theo domain, không cần site table (ưu tiên SaveFileBody nginx rewrite).
    return rewriteApplyWordPressRule($apiUrl, $apiKey, [
        'domain' => $domain,
        'webname' => $domain,
        'path' => '',
    ], $vps);
}

function rewriteJsonResponse(array $payload, int $httpCode = 200) {
    if (!headers_sent()) {
        http_response_code($httpCode);
        header('Content-Type: application/json; charset=utf-8');
    }

    if (ob_get_level() > 0) {
        $strayOutput = trim((string)ob_get_clean());
        if ($strayOutput !== '' && !isset($payload['_debug_output'])) {
            $payload['_debug_output'] = substr(strip_tags($strayOutput), 0, 500);
        }
    }

    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function rewriteBuildCheckItem($key, $label, $ok, $detail = '', $hint = '') {
    return [
        'key' => (string)$key,
        'label' => (string)$label,
        'ok' => (bool)$ok,
        'detail' => (string)$detail,
        'hint' => (string)$hint,
    ];
}

function rewriteBuildPreviewChecks($rawDomains, array $domains, array $vpsList, $cf, array $selectedVps = []) {
    $rawDomains = (string)$rawDomains;
    $hasRawInput = trim($rawDomains) !== '';
    $checks = [];

    $checks[] = rewriteBuildCheckItem(
        'domains_input',
        'Đã nhập danh sách domain',
        $hasRawInput,
        $hasRawInput ? 'Có dữ liệu đầu vào.' : 'Textarea domain đang trống.',
        'Nhập mỗi dòng 1 domain, ví dụ: example.com'
    );

    $checks[] = rewriteBuildCheckItem(
        'valid_domains',
        'Có domain hợp lệ sau khi chuẩn hóa',
        !empty($domains),
        !empty($domains) ? ('Số domain hợp lệ: ' . count($domains)) : 'Không parse được domain hợp lệ.',
        'Xóa protocol/path (https://, /path), kiểm tra định dạng domain.'
    );

    $checks[] = rewriteBuildCheckItem(
        'vps_json',
        'Có dữ liệu VPS trong vps.json',
        !empty($vpsList),
        !empty($vpsList) ? ('Số VPS: ' . count($vpsList)) : 'vps.json trống hoặc không đọc được.',
        'Kiểm tra file vps.json và đảm bảo có danh sách VPS hợp lệ.'
    );

    $checks[] = rewriteBuildCheckItem(
        'cloudflare_client',
        'Khởi tạo Cloudflare API thành công',
        (bool)$cf || count(array_filter($domains, static function ($domain) use ($selectedVps) {
            return rewriteGetSelectedVpsIp($selectedVps, $domain) !== '';
        })) === count($domains),
        $cf ? 'Cloudflare client sẵn sàng.' : 'Tất cả domain đã chọn VPS thủ công.',
        'Kiểm tra config.php (CLOUDFLARE_EMAIL, CLOUDFLARE_API_TOKEN) hoặc token.txt.'
    );

    $missing = [];
    foreach ($checks as $check) {
        if (!$check['ok']) {
            $missing[] = $check;
        }
    }

    return [
        'checks' => $checks,
        'missing' => $missing,
    ];
}

function rewriteGetSelectedVpsIp(array $selectedVps, $domain) {
    $key = rewriteNormalizeDomainForVpsLookup($domain);
    $value = $selectedVps[$key] ?? '';
    return trim((string)$value);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    if (ob_get_level() > 0) {
        ob_clean();
    }

    $vpsListForApi = rewriteLoadVpsList();
    $cf = rewriteGetCloudflareClient();
    $action = $_POST['ajax_action'];
    $selectedVps = json_decode((string)($_POST['vps_assignments'] ?? ''), true);
    $selectedVps = is_array($selectedVps) ? $selectedVps : [];

    if ($action === 'test_connection') {
        if (!$cf) {
            rewriteJsonResponse([
                'success' => false,
                'msg' => 'Không khởi tạo được Cloudflare API. Kiểm tra token.txt và config.php',
                'missing_conditions' => [
                    rewriteBuildCheckItem(
                        'cloudflare_client',
                        'Khởi tạo Cloudflare API thành công',
                        false,
                        'Không tạo được Cloudflare client.',
                        'Kiểm tra config.php (CLOUDFLARE_EMAIL, CLOUDFLARE_API_TOKEN) hoặc token.txt.'
                    ),
                ],
            ]);
        }

        $zones = $cf->listZones(1, 1, false);
        $ok = isset($zones['success']) && $zones['success'] === true;
        rewriteJsonResponse([
            'success' => $ok,
            'msg' => $ok ? 'Kết nối Cloudflare thành công!' : (($zones['errors'][0]['message'] ?? 'Kết nối Cloudflare thất bại')),
        ]);
    }

    if ($action === 'preview_domains') {
        $rawDomains = $_POST['domains'] ?? '';
        $domains = rewriteParseDomainList($rawDomains);
        $checks = rewriteBuildPreviewChecks($rawDomains, $domains, $vpsListForApi, $cf, $selectedVps);
        if (!empty($checks['missing'])) {
            rewriteJsonResponse([
                'success' => false,
                'msg' => 'Preview thất bại do thiếu điều kiện đầu vào',
                'conditions' => $checks['checks'],
                'missing_conditions' => $checks['missing'],
            ]);
        }

        $preview = [];
        $found = 0;
        $resolved = 0;
        $notFound = 0;
        $lookupErrors = 0;

        foreach ($domains as $domain) {
            $selectedVpsIp = rewriteGetSelectedVpsIp($selectedVps, $domain);
            $resolve = $selectedVpsIp !== ''
                ? rewriteResolveSelectedVps($domain, $selectedVpsIp, $vpsListForApi)
                : rewriteResolveVpsByDomain($cf, $domain, $vpsListForApi);
            if (!$resolve['success']) {
                $notFound++;
                $preview[] = [
                    'domain' => $domain,
                    'status' => 'not_found',
                    'webname' => '',
                    'msg' => $resolve['error'] ?? 'Không resolve được VPS từ Cloudflare',
                    'vps_ip' => $resolve['origin_ip'] ?? '',
                    'api_url' => $resolve['api_config']['panel_url'] ?? '',
                ];
                continue;
            }

            $resolved++;
            $siteResult = rewriteFetchSiteTable(
                $resolve['api_url'],
                $resolve['api_key'],
                $domain,
                $resolve['vps'] ?? []
            );
            $siteTable = $siteResult['site_table'] ?? [];
            $domainMap = rewriteBuildDomainMap($siteTable);

            if (isset($domainMap[$domain])) {
                $found++;
                $preview[] = [
                    'domain' => $domain,
                    'status' => 'found',
                    'webname' => $domainMap[$domain]['webname'],
                    'vps_ip' => $resolve['origin_ip'] ?? '',
                    'api_url' => $resolve['api_config']['panel_url'] ?? '',
                    'api_endpoint' => $resolve['api_config']['save_file_endpoint'] ?? '',
                    'msg' => 'B1: CF => IP ' . ($resolve['origin_ip'] ?? '')
                        . ' | B2: tìm site qua ' . ($siteResult['lookup_method'] ?? 'aaPanel API'),
                ];
            } else {
                $lookupComplete = !empty($siteResult['lookup_complete']);
                if ($lookupComplete) {
                    $notFound++;
                } else {
                    $lookupErrors++;
                }
                $preview[] = [
                    'domain' => $domain,
                    'status' => $lookupComplete ? 'not_found' : 'error',
                    'webname' => '',
                    'vps_ip' => $resolve['origin_ip'] ?? '',
                    'api_url' => $resolve['api_config']['panel_url'] ?? '',
                    'api_endpoint' => $resolve['api_config']['save_file_endpoint'] ?? '',
                    'msg' => $lookupComplete
                        ? 'Đã resolve VPS nhưng không thấy site trên aaPanel tương ứng (đã tìm hết danh sách). Khi Apply sẽ thử cập nhật trực tiếp theo domain.'
                        : 'Chưa thể xác minh site vì không lấy đủ danh sách aaPanel: ' . implode(' | ', $siteResult['lookup_errors'] ?? []),
                ];
            }
        }

        rewriteJsonResponse([
            'success' => true,
            'summary' => [
                'total' => count($domains),
                'resolved_vps' => $resolved,
                'found' => $found,
                'not_found' => $notFound,
                'error' => $lookupErrors,
            ],
            'preview' => $preview,
        ]);
    }

    if ($action === 'add_www_domains') {
        if (empty($vpsListForApi)) {
            rewriteJsonResponse(['success' => false, 'msg' => 'Không có dữ liệu VPS trong vps.json']);
        }
        $domains = rewriteParseDomainList($_POST['domains'] ?? '');
        if (empty($domains)) {
            rewriteJsonResponse(['success' => false, 'msg' => 'Không có domain hợp lệ trong danh sách']);
        }
        $allDomainsHaveSelectedVps = count(array_filter($domains, static function ($domain) use ($selectedVps) {
            return rewriteGetSelectedVpsIp($selectedVps, $domain) !== '';
        })) === count($domains);
        if (!$cf && !$allDomainsHaveSelectedVps) {
            rewriteJsonResponse(['success' => false, 'msg' => 'Chưa có Cloudflare API và chưa chọn VPS cho tất cả domain']);
        }

        $siteMapByVpsIp = [];
        $siteLookupCompleteByVpsIp = [];
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        $notFoundCount = 0;

        foreach ($domains as $domain) {
            $lookupDomain = rewriteNormalizeDomainForVpsLookup($domain);
            $wwwDomain = 'www.' . $lookupDomain;
            $selectedVpsIp = rewriteGetSelectedVpsIp($selectedVps, $lookupDomain);
            $resolve = $selectedVpsIp !== ''
                ? rewriteResolveSelectedVps($lookupDomain, $selectedVpsIp, $vpsListForApi)
                : rewriteResolveVpsByDomain($cf, $lookupDomain, $vpsListForApi);
            if (!$resolve['success']) {
                $notFoundCount++;
                $results[] = [
                    'domain' => $wwwDomain,
                    'status' => 'not_found',
                    'method' => 'resolve-vps',
                    'msg' => $resolve['error'] ?? 'Không resolve được VPS từ Cloudflare',
                ];
                continue;
            }

            $vpsIp = trim((string)($resolve['origin_ip'] ?? ''));
            if (!isset($siteMapByVpsIp[$vpsIp])) {
                $siteResult = rewriteFetchSiteTable(
                    $resolve['api_url'],
                    $resolve['api_key'],
                    '',
                    $resolve['vps'] ?? []
                );
                $siteMapByVpsIp[$vpsIp] = rewriteBuildDomainMap($siteResult['site_table'] ?? []);
                $siteLookupCompleteByVpsIp[$vpsIp] = !empty($siteResult['lookup_complete']);
            }

            $domainMap = $siteMapByVpsIp[$vpsIp];
            if (!isset($domainMap[$lookupDomain])) {
                $lookupResult = rewriteFindSiteInVps(
                    $resolve['api_url'],
                    $resolve['api_key'],
                    $lookupDomain,
                    $resolve['vps'] ?? []
                );
                $siteLookupCompleteByVpsIp[$vpsIp] = $siteLookupCompleteByVpsIp[$vpsIp]
                    || !empty($lookupResult['lookup_complete']);
                if (is_array($lookupResult['site'])) {
                    $domainMap[$lookupDomain] = $lookupResult['site'];
                    $siteMapByVpsIp[$vpsIp] = $domainMap;
                }
            }
            if (!isset($domainMap[$lookupDomain])) {
                $lookupComplete = $siteLookupCompleteByVpsIp[$vpsIp] ?? false;
                if ($lookupComplete) {
                    $notFoundCount++;
                } else {
                    $errorCount++;
                }
                $results[] = [
                    'domain' => $wwwDomain,
                    'status' => $lookupComplete ? 'not_found' : 'error',
                    'method' => 'site-lookup',
                    'msg' => $lookupComplete
                        ? 'Đã tìm hết danh sách site aaPanel nhưng không thấy site gốc.'
                        : 'Không thể xác minh site do API aaPanel không trả đủ danh sách: ' . implode(' | ', $lookupResult['lookup_errors'] ?? []),
                    'vps_ip' => $vpsIp,
                ];
                continue;
            }

            $site = $domainMap[$lookupDomain];
            if (rewriteSiteHasDomain($site, $wwwDomain)) {
                $successCount++;
                $results[] = [
                    'domain' => $wwwDomain,
                    'status' => 'success',
                    'method' => 'already-exists',
                    'msg' => 'Domain www đã tồn tại trên VPS.',
                    'vps_ip' => $vpsIp,
                ];
                continue;
            }

            $addResult = rewriteAddWwwDomain(
                $resolve['api_url'],
                $resolve['api_key'],
                $site,
                $wwwDomain,
                $resolve['vps'] ?? []
            );
            if ($addResult['success']) {
                $successCount++;
                $results[] = [
                    'domain' => $wwwDomain,
                    'status' => 'success',
                    'method' => $addResult['attempt'],
                    'msg' => $addResult['message'],
                    'vps_ip' => $vpsIp,
                    'debug_response' => rewriteCompactResponseForUi($addResult['response'] ?? []),
                ];
            } else {
                $errorCount++;
                $results[] = [
                    'domain' => $wwwDomain,
                    'status' => 'error',
                    'method' => $addResult['attempt'],
                    'msg' => $addResult['message'],
                    'vps_ip' => $vpsIp,
                    'debug_response' => rewriteCompactResponseForUi($addResult['response'] ?? []),
                ];
            }
        }

        rewriteJsonResponse([
            'success' => $successCount > 0 && $errorCount === 0,
            'summary' => [
                'total' => count($domains),
                'success' => $successCount,
                'error' => $errorCount,
                'not_found' => $notFoundCount,
            ],
            'results' => $results,
        ]);
    }

    if ($action === 'apply_bulk_rewrite') {
        if (empty($vpsListForApi)) {
            rewriteJsonResponse(['success' => false, 'msg' => 'Không có dữ liệu VPS trong vps.json']);
        }

        $domains = rewriteParseDomainList($_POST['domains'] ?? '');
        if (empty($domains)) {
            rewriteJsonResponse(['success' => false, 'msg' => 'Không có domain hợp lệ trong danh sách']);
        }
        $allDomainsHaveSelectedVps = count(array_filter($domains, static function ($domain) use ($selectedVps) {
            return rewriteGetSelectedVpsIp($selectedVps, $domain) !== '';
        })) === count($domains);
        if (!$cf && !$allDomainsHaveSelectedVps) {
            rewriteJsonResponse(['success' => false, 'msg' => 'Chưa có Cloudflare API và chưa chọn VPS cho tất cả domain']);
        }

        $domainMapByVpsIp = [];
        $siteLookupCompleteByVpsIp = [];
        $results = [];
        $successCount = 0;
        $errorCount = 0;
        $notFoundCount = 0;

        foreach ($domains as $domain) {
            $selectedVpsIp = rewriteGetSelectedVpsIp($selectedVps, $domain);
            $resolve = $selectedVpsIp !== ''
                ? rewriteResolveSelectedVps($domain, $selectedVpsIp, $vpsListForApi)
                : rewriteResolveVpsByDomain($cf, $domain, $vpsListForApi);
            if (!$resolve['success']) {
                $notFoundCount++;
                $results[] = [
                    'domain' => $domain,
                    'status' => 'not_found',
                    'msg' => $resolve['error'] ?? 'Không resolve được VPS từ Cloudflare',
                    'vps_ip' => $resolve['origin_ip'] ?? '',
                    'api_url' => $resolve['api_config']['panel_url'] ?? '',
                ];
                continue;
            }

            $vpsIp = trim((string)($resolve['origin_ip'] ?? ''));
            if (!isset($domainMapByVpsIp[$vpsIp])) {
                $siteResult = rewriteFetchSiteTable(
                    $resolve['api_url'],
                    $resolve['api_key'],
                    '',
                    $resolve['vps'] ?? []
                );
                $siteTable = $siteResult['site_table'] ?? [];
                $domainMapByVpsIp[$vpsIp] = rewriteBuildDomainMap($siteTable);
                $siteLookupCompleteByVpsIp[$vpsIp] = !empty($siteResult['lookup_complete']);
            }

            $domainMap = $domainMapByVpsIp[$vpsIp];
            $lookupResult = ['lookup_errors' => []];
            if (!isset($domainMap[$domain])) {
                $lookupResult = rewriteFindSiteInVps(
                    $resolve['api_url'],
                    $resolve['api_key'],
                    $domain,
                    $resolve['vps'] ?? []
                );
                $siteLookupCompleteByVpsIp[$vpsIp] = $siteLookupCompleteByVpsIp[$vpsIp]
                    || !empty($lookupResult['lookup_complete']);
                if (is_array($lookupResult['site'])) {
                    $domainMap[$domain] = $lookupResult['site'];
                    $domainMapByVpsIp[$vpsIp] = $domainMap;
                }
            }
            if (!isset($domainMap[$domain])) {
                if (empty($siteLookupCompleteByVpsIp[$vpsIp])) {
                    $errorCount++;
                    $results[] = [
                        'domain' => $domain,
                        'status' => 'error',
                        'msg' => 'Không thể xác minh site vì API aaPanel không trả đủ danh sách; không chạy cập nhật dự phòng để tránh sửa nhầm: ' . implode(' | ', $lookupResult['lookup_errors'] ?? []),
                        'method' => 'site-lookup-incomplete',
                        'vps_ip' => $vpsIp,
                        'api_url' => $resolve['api_config']['panel_url'] ?? '',
                    ];
                    continue;
                }

                $directResult = rewriteApplyWordPressRuleDirect(
                    $resolve['api_url'],
                    $resolve['api_key'],
                    $domain,
                    $resolve['vps'] ?? []
                );
                if ($directResult['success']) {
                    $successCount++;
                    $results[] = [
                        'domain' => $domain,
                        'status' => 'success',
                        'msg' => 'Site không có trong danh sách aaPanel, đã cập nhật trực tiếp theo domain: ' . ($directResult['message'] ?? ''),
                        'method' => 'direct-domain-fallback | ' . ($directResult['attempt'] ?? '-'),
                        'attempt_flow' => implode(' => ', $directResult['attempt_labels'] ?? []),
                        'debug_response' => rewriteCompactResponseForUi($directResult['response'] ?? []),
                        'path' => '',
                        'vps_ip' => $vpsIp,
                        'api_url' => $resolve['api_config']['panel_url'] ?? '',
                    ];
                } else {
                    $notFoundCount++;
                    $results[] = [
                        'domain' => $domain,
                        'status' => 'not_found',
                        'msg' => 'Không tìm thấy site trên aaPanel và fallback trực tiếp cũng thất bại: ' . ($directResult['message'] ?? 'Unknown error'),
                        'method' => 'direct-domain-fallback-failed',
                        'attempt_flow' => implode(' => ', $directResult['attempt_labels'] ?? []),
                        'debug_response' => rewriteCompactResponseForUi($directResult['response'] ?? []),
                        'vps_ip' => $vpsIp,
                        'api_url' => $resolve['api_config']['panel_url'] ?? '',
                    ];
                }
                continue;
            }

            $site = $domainMap[$domain];
            $site['domain'] = $domain;
            $applyResult = rewriteApplyWordPressRule(
                $resolve['api_url'],
                $resolve['api_key'],
                $site,
                $resolve['vps'] ?? []
            );

            if ($applyResult['success']) {
                $successCount++;
                $results[] = [
                    'domain' => $domain,
                    'status' => 'success',
                    'msg' => $applyResult['message'],
                    'method' => $applyResult['attempt'],
                    'attempt_flow' => implode(' => ', $applyResult['attempt_labels'] ?? []),
                    'debug_response' => rewriteCompactResponseForUi($applyResult['response'] ?? []),
                    'path' => $site['path'] ?? '',
                    'vps_ip' => $vpsIp,
                    'api_url' => $resolve['api_config']['panel_url'] ?? '',
                ];
            } else {
                $errorCount++;
                $results[] = [
                    'domain' => $domain,
                    'status' => 'error',
                    'msg' => $applyResult['message'],
                    'method' => $applyResult['attempt'],
                    'attempt_flow' => implode(' => ', $applyResult['attempt_labels'] ?? []),
                    'debug_response' => rewriteCompactResponseForUi($applyResult['response'] ?? []),
                    'path' => $site['path'] ?? '',
                    'vps_ip' => $vpsIp,
                    'api_url' => $resolve['api_config']['panel_url'] ?? '',
                ];
            }
        }

        rewriteJsonResponse([
            'success' => $successCount > 0,
            'summary' => [
                'total' => count($domains),
                'success' => $successCount,
                'error' => $errorCount,
                'not_found' => $notFoundCount,
            ],
            'results' => $results,
        ]);
    }

    rewriteJsonResponse(['success' => false, 'msg' => 'Invalid ajax_action']);
}

$currentPage = 'aapanel_bulk_rewrite_wordpress';
include __DIR__ . '/includes/main_navigation.php';
$vpsOptionsForUi = [];
foreach (rewriteLoadVpsList() as $vps) {
    if (!is_array($vps) || trim((string)($vps['ip'] ?? '')) === '') {
        continue;
    }
    $vpsOptionsForUi[] = [
        'ip' => trim((string)$vps['ip']),
        'label' => trim((string)($vps['team'] ?? '')) !== ''
            ? trim((string)$vps['team']) . ' - ' . trim((string)$vps['ip'])
            : trim((string)$vps['ip']),
    ];
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>aaPanel Bulk Rewrite WordPress</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="assets/css/sidebar-navigation.css" rel="stylesheet">
    <style>
        body {
            background: #000;
            color: #eaffea;
            padding-left: 280px;
        }
        .card, .card-header, .card-body, .form-control, .btn, .table, .table th, .table td {
            background: #000 !important;
            color: #eaffea !important;
            border-color: #00ff66 !important;
        }
        .card {
            border: 1px solid #00ff66;
            box-shadow: 0 0 18px rgba(0, 255, 102, 0.15);
        }
        .vps-row { cursor: pointer; }
        .vps-row:hover { background: #021408 !important; }
        .result-success { color: #00ff66; }
        .result-error { color: #ff6666; }
        .result-warn { color: #ffcc66; }
        .ui-log-box {
            background: #020b04;
            border: 1px solid #00ff66;
            border-radius: 6px;
            max-height: 240px;
            overflow-y: auto;
            padding: 10px;
            font-family: Consolas, 'Courier New', monospace;
            font-size: 12px;
            line-height: 1.4;
        }
        .ui-log-line { margin: 2px 0; }
        .ui-log-time { color: #9ad7a7; }
        .ui-log-info { color: #c5ffd3; }
        .ui-log-success { color: #00ff66; }
        .ui-log-error { color: #ff6b6b; }
        .ui-log-warn { color: #ffd36b; }
        .btn-action {
            position: relative;
            transition: transform 0.15s ease, box-shadow 0.2s ease, opacity 0.2s ease;
        }
        .btn-action:active {
            transform: scale(0.97);
        }
        .btn-action.is-loading {
            pointer-events: none;
            opacity: 0.85;
            box-shadow: 0 0 0 2px rgba(255,255,255,0.15) inset;
        }
        .btn-action.is-loading::after {
            content: '';
            width: 14px;
            height: 14px;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 50%;
            display: inline-block;
            margin-left: 8px;
            vertical-align: -2px;
            animation: spin-btn 0.8s linear infinite;
        }
        .btn-action.is-success {
            box-shadow: 0 0 0 2px rgba(0, 255, 102, 0.55) inset;
            animation: pulse-success 0.6s ease;
        }
        .btn-action.is-error {
            box-shadow: 0 0 0 2px rgba(255, 102, 102, 0.65) inset;
            animation: pulse-error 0.6s ease;
        }
        @keyframes spin-btn {
            to { transform: rotate(360deg); }
        }
        @keyframes pulse-success {
            0% { transform: scale(1); }
            40% { transform: scale(1.03); }
            100% { transform: scale(1); }
        }
        @keyframes pulse-error {
            0% { transform: translateX(0); }
            25% { transform: translateX(-3px); }
            50% { transform: translateX(3px); }
            75% { transform: translateX(-2px); }
            100% { transform: translateX(0); }
        }
        @media (max-width: 991px) {
            body { padding-left: 0; }
        }
    </style>
</head>
<body>
<div class="container-fluid py-3">
    <div class="row g-3">
        <div class="col-lg-12">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Bulk cập nhật Rewrite rule: wordpress</h4>
                </div>
                <div class="card-body">
                    <form id="rewriteForm">
                        <div class="alert alert-info py-2 mb-3">
                            Luồng xử lý tự động:
                            <b>1)</b> lấy IP từ cấu hình Cloudflare,
                            <b>2)</b> dùng VPS map theo IP để tạo API/config aaPanel và cập nhật rewrite.
                        </div>

                        <div class="mt-3">
                            <div class="d-flex justify-content-between align-items-center">
                                <label class="form-label mb-0">Danh sách domain (mỗi dòng 1 domain)</label>
                                <button type="button" class="btn btn-sm btn-outline-info btn-action" id="btnAddWww">Thêm www</button>
                            </div>
                            <textarea id="domains" class="form-control" rows="8" placeholder="domain1.com&#10;domain2.com"></textarea>
                            <div class="form-text text-info">Có thể chọn VPS riêng cho từng domain; domain không chọn sẽ tự động resolve qua Cloudflare.</div>
                            <div id="domainVpsAssignments" class="table-responsive mt-2"></div>
                        </div>

                        <div class="mt-3 d-flex gap-2 flex-wrap">
                            <button type="button" class="btn btn-outline-info btn-action" id="btnTest">Test kết nối</button>
                            <button type="button" class="btn btn-outline-warning btn-action" id="btnPreview">Preview domain map</button>
                            <button type="button" class="btn btn-outline-primary btn-action" id="btnAddWwwVps">Thêm www trên VPS</button>
                            <button type="submit" class="btn btn-success btn-action" id="btnApply">Áp dụng rewrite wordpress</button>
                        </div>
                    </form>

                    <hr>
                    <div id="summary" class="mb-2"></div>
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <strong>Log giao diện</strong>
                        <button type="button" class="btn btn-sm btn-outline-light" id="btnClearLog">Xóa log</button>
                    </div>
                    <div id="uiLog" class="ui-log-box mb-3">
                        <div class="ui-log-line ui-log-info"><span class="ui-log-time">[--:--:--]</span> Sẵn sàng xử lý.</div>
                    </div>
                    <div class="table-responsive" style="max-height: 360px; overflow-y: auto;">
                        <table class="table table-bordered table-sm mb-0">
                            <thead>
                            <tr>
                                <th style="width: 18%">Domain</th>
                                <th style="width: 14%">Status</th>
                                <th style="width: 20%">Method</th>
                                <th>Message</th>
                            </tr>
                            </thead>
                            <tbody id="resultBody">
                            <tr><td colspan="4" class="text-center text-muted">Chưa có dữ liệu</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
const vpsOptions = <?= json_encode($vpsOptionsForUi, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

function esc(text) {
    return String(text ?? '').replace(/[&<>'"]/g, (m) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;'}[m]));
}

function getNowTime() {
    const d = new Date();
    const hh = String(d.getHours()).padStart(2, '0');
    const mm = String(d.getMinutes()).padStart(2, '0');
    const ss = String(d.getSeconds()).padStart(2, '0');
    return `${hh}:${mm}:${ss}`;
}

function pushUiLog(message, level = 'info') {
    const box = document.getElementById('uiLog');
    if (!box) return;
    const cls = {
        info: 'ui-log-info',
        success: 'ui-log-success',
        error: 'ui-log-error',
        warn: 'ui-log-warn'
    }[level] || 'ui-log-info';

    const line = document.createElement('div');
    line.className = `ui-log-line ${cls}`;
    line.innerHTML = `<span class="ui-log-time">[${getNowTime()}]</span> ${esc(message)}`;
    box.appendChild(line);
    box.scrollTop = box.scrollHeight;
}

function getFormData(action) {
    const assignments = {};
    document.querySelectorAll('#domainVpsAssignments select[data-domain]').forEach((select) => {
        if (select.value) {
            assignments[select.dataset.domain] = select.value;
        }
    });

    return new URLSearchParams({
        ajax_action: action,
        domains: document.getElementById('domains').value,
        vps_assignments: JSON.stringify(assignments),
    });
}

function renderDomainVpsAssignments() {
    const box = document.getElementById('domainVpsAssignments');
    const domains = document.getElementById('domains').value.split(/\r?\n/)
        .map((line) => line.trim())
        .filter((line) => line !== '' && !line.startsWith('#'));

    if (!box || domains.length === 0 || vpsOptions.length === 0) {
        if (box) box.innerHTML = '';
        return;
    }

    const previous = {};
    box.querySelectorAll('select[data-domain]').forEach((select) => {
        previous[select.dataset.domain] = select.value;
    });

    box.innerHTML = `<table class="table table-bordered table-sm mb-0">
        <thead><tr><th>Domain</th><th>VPS xử lý</th></tr></thead>
        <tbody>${domains.map((domain) => {
            const key = domain.replace(/^https?:\/\//i, '').replace(/^www\./i, '').split('/')[0].toLowerCase();
            const options = ['<option value="">Tự động theo Cloudflare</option>']
                .concat(vpsOptions.map((vps) => `<option value="${esc(vps.ip)}">${esc(vps.label)}</option>`))
                .join('');
            return `<tr><td>${esc(domain)}</td><td><select class="form-select form-select-sm" data-domain="${esc(key)}">${options}</select></td></tr>`;
        }).join('')}</tbody>
    </table>`;

    box.querySelectorAll('select[data-domain]').forEach((select) => {
        select.value = previous[select.dataset.domain] || '';
    });
}

function addWwwToDomains() {
    const textarea = document.getElementById('domains');
    if (!textarea) return;

    const updatedDomains = textarea.value.split(/\r?\n/).map((line) => {
        const value = line.trim();
        if (value === '' || value.startsWith('#')) {
            return line;
        }

        const match = value.match(/^(https?:\/\/)?([^/]+)(\/.*)?$/i);
        if (!match) {
            return line;
        }

        const protocol = match[1] || '';
        const host = match[2].replace(/^www\./i, '');
        const path = match[3] || '';
        return `${protocol}www.${host}${path}`;
    });

    textarea.value = updatedDomains.join('\n');
    pushUiLog('Đã thêm www vào các domain hợp lệ.', 'success');
}

async function runAction(action) {
    const res = await fetch('aapanel_bulk_rewrite_wordpress.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
        body: getFormData(action).toString(),
    });
    const rawText = await res.text();
    try {
        return JSON.parse(rawText);
    } catch (e) {
        throw new Error('Phản hồi không phải JSON: ' + rawText.slice(0, 220));
    }
}

function renderSummary(summary) {
    const box = document.getElementById('summary');
    if (!summary) {
        box.innerHTML = '';
        return;
    }
    box.innerHTML = `<div class="alert alert-secondary py-2 mb-2">Tổng: <b>${summary.total ?? 0}</b> | Thành công: <b>${summary.success ?? summary.found ?? 0}</b> | Lỗi: <b>${summary.error ?? 0}</b> | Không tìm thấy: <b>${summary.not_found ?? 0}</b></div>`;
}

function renderResults(results) {
    const body = document.getElementById('resultBody');
    if (!Array.isArray(results) || results.length === 0) {
        body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Không có kết quả</td></tr>';
        pushUiLog('Không có kết quả để hiển thị.', 'warn');
        return;
    }

    const successCount = results.filter(r => r.status === 'success' || r.status === 'found').length;
    const errorCount = results.filter(r => r.status === 'error').length;
    const notFoundCount = results.filter(r => r.status === 'not_found').length;
    pushUiLog(`Nhận ${results.length} dòng kết quả | success: ${successCount}, error: ${errorCount}, not_found: ${notFoundCount}.`, errorCount > 0 ? 'warn' : 'success');

    body.innerHTML = results.map(row => {
        let statusClass = 'result-warn';
        let statusText = row.status || 'unknown';

        if (row.status === 'success' || row.status === 'found') {
            statusClass = 'result-success';
        } else if (row.status === 'error' || row.status === 'not_found') {
            statusClass = 'result-error';
        }

        const trackingParts = [];
        if (row.attempt_flow) {
            trackingParts.push(`Attempts: ${row.attempt_flow}`);
        }
        if (row.debug_response) {
            trackingParts.push(`Response: ${row.debug_response}`);
        }
        const trackingText = trackingParts.length > 0 ? `<br><small class="text-warning">${esc(trackingParts.join(' | '))}</small>` : '';

        return `<tr>
            <td>${esc(row.domain)}</td>
            <td class="${statusClass}">${esc(statusText)}</td>
            <td>${esc(row.method || row.webname || '-')}</td>
            <td>${esc(row.msg || '-')} ${row.vps_ip ? `| VPS: ${esc(row.vps_ip)}` : ''} ${row.api_url ? `| API: ${esc(row.api_url)}` : ''}${trackingText}</td>
        </tr>`;
    }).join('');
}

function buildMissingConditionRows(data) {
    const missing = Array.isArray(data?.missing_conditions) ? data.missing_conditions : [];
    if (missing.length === 0) {
        return [{domain: '-', status: 'error', method: '-', msg: data?.msg || 'Preview thất bại'}];
    }

    const rows = [];
    rows.push({
        domain: '-',
        status: 'error',
        method: 'preview-check',
        msg: data?.msg || 'Preview thất bại',
    });

    missing.forEach((item) => {
        const label = item?.label || item?.key || 'Điều kiện chưa đạt';
        const detail = item?.detail ? ` | ${item.detail}` : '';
        const hint = item?.hint ? ` | Gợi ý: ${item.hint}` : '';
        rows.push({
            domain: '-',
            status: 'error',
            method: item?.key || 'missing',
            msg: `Thiếu điều kiện: ${label}${detail}${hint}`,
        });
    });

    return rows;
}

function setButtonState(button, state, text) {
    if (!button) return;

    button.classList.remove('is-loading', 'is-success', 'is-error');

    if (state === 'loading') {
        button.classList.add('is-loading');
    } else if (state === 'success') {
        button.classList.add('is-success');
    } else if (state === 'error') {
        button.classList.add('is-error');
    }

    if (typeof text === 'string') {
        button.textContent = text;
    }
}

function resetButtonTextLater(button, originalText, delayMs = 900) {
    setTimeout(() => {
        setButtonState(button, 'idle', originalText);
    }, delayMs);
}

document.getElementById('btnClearLog').addEventListener('click', () => {
    const box = document.getElementById('uiLog');
    if (!box) return;
    box.innerHTML = '';
    pushUiLog('Đã xóa log giao diện.', 'info');
});

document.getElementById('btnAddWww').addEventListener('click', () => {
    addWwwToDomains();
    renderDomainVpsAssignments();
});

document.getElementById('domains').addEventListener('input', renderDomainVpsAssignments);
renderDomainVpsAssignments();

document.getElementById('btnTest').addEventListener('click', async () => {
    const btn = document.getElementById('btnTest');
    const originalText = 'Test kết nối';
    setButtonState(btn, 'loading', 'Đang kiểm tra');
    pushUiLog('Bắt đầu test kết nối Cloudflare.', 'info');

    try {
        const data = await runAction('test_connection');
        renderSummary();
        renderResults([{domain: '-', status: data.success ? 'success' : 'error', method: '-', msg: data.msg || ''}]);
        setButtonState(btn, data.success ? 'success' : 'error', data.success ? 'Đã kết nối' : 'Kết nối lỗi');
        pushUiLog(data.success ? 'Test kết nối thành công.' : ('Test kết nối lỗi: ' + (data.msg || 'unknown')), data.success ? 'success' : 'error');
    } catch (e) {
        renderSummary();
        renderResults([{domain: '-', status: 'error', method: '-', msg: e?.message || 'Lỗi không xác định'}]);
        setButtonState(btn, 'error', 'Kết nối lỗi');
        pushUiLog('Test kết nối thất bại: ' + (e?.message || 'unknown'), 'error');
    }

    resetButtonTextLater(btn, originalText);
});

document.getElementById('btnPreview').addEventListener('click', async () => {
    const btn = document.getElementById('btnPreview');
    const originalText = 'Preview domain map';
    setButtonState(btn, 'loading', 'Đang preview');
    pushUiLog('Bắt đầu preview domain map.', 'info');

    try {
        const data = await runAction('preview_domains');
        if (!data.success) {
            renderSummary();
            const rows = buildMissingConditionRows(data);
            renderResults(rows);
            setButtonState(btn, 'error', 'Preview lỗi');
            pushUiLog('Preview thất bại: ' + (data.msg || 'unknown'), 'error');
            if (Array.isArray(data?.missing_conditions) && data.missing_conditions.length > 0) {
                data.missing_conditions.forEach((item) => {
                    const label = item?.label || item?.key || 'Điều kiện thiếu';
                    pushUiLog('Thiếu điều kiện: ' + label, 'warn');
                });
            }
            resetButtonTextLater(btn, originalText);
            return;
        }
        renderSummary(data.summary);
        renderResults(data.preview || []);
        setButtonState(btn, 'success', 'Preview xong');
        pushUiLog(`Preview hoàn tất: tổng ${data.summary?.total ?? 0}, found ${data.summary?.found ?? 0}.`, 'success');
        (data.preview || []).forEach((row) => {
            if (row.status !== 'not_found' || !row.vps_ip || !row.api_url) {
                return;
            }

            pushUiLog(
                `${row.msg || 'Đã resolve VPS nhưng không thấy site trên aaPanel tương ứng. Khi Apply sẽ thử cập nhật trực tiếp theo domain.'} | VPS: ${row.vps_ip} | API: ${row.api_url}`,
                'warn'
            );
        });
    } catch (e) {
        renderSummary();
        renderResults([{domain: '-', status: 'error', method: '-', msg: e?.message || 'Preview thất bại'}]);
        setButtonState(btn, 'error', 'Preview lỗi');
        pushUiLog('Preview lỗi: ' + (e?.message || 'unknown'), 'error');
    }

    resetButtonTextLater(btn, originalText);
});

document.getElementById('btnAddWwwVps').addEventListener('click', async () => {
    const btn = document.getElementById('btnAddWwwVps');
    const originalText = 'Thêm www trên VPS';
    setButtonState(btn, 'loading', 'Đang thêm www');
    pushUiLog('Bắt đầu thêm www vào domain trên VPS.', 'info');

    try {
        const data = await runAction('add_www_domains');
        renderSummary(data.summary);
        renderResults(data.results || [{domain: '-', status: 'error', method: '-', msg: data.msg || 'Thêm www thất bại'}]);
        const hasError = (data.summary?.error ?? 0) > 0 || (data.summary?.not_found ?? 0) > 0 || !data.success;
        setButtonState(btn, hasError ? 'error' : 'success', hasError ? 'Thêm www lỗi' : 'Đã thêm www');
        pushUiLog(hasError ? ('Thêm www có lỗi: ' + (data.msg || 'xem chi tiết kết quả')) : 'Đã thêm www trên VPS thành công.', hasError ? 'warn' : 'success');
    } catch (e) {
        renderSummary();
        renderResults([{domain: '-', status: 'error', method: '-', msg: e?.message || 'Thêm www thất bại'}]);
        setButtonState(btn, 'error', 'Thêm www lỗi');
        pushUiLog('Thêm www thất bại: ' + (e?.message || 'unknown'), 'error');
    }

    resetButtonTextLater(btn, originalText, 1200);
});

document.getElementById('rewriteForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    const btn = document.getElementById('btnApply');
    const originalText = 'Áp dụng rewrite wordpress';
    setButtonState(btn, 'loading', 'Đang áp dụng');
    pushUiLog('Bắt đầu áp dụng rewrite wordpress hàng loạt.', 'info');

    try {
        const data = await runAction('apply_bulk_rewrite');
        if (!data.success && !Array.isArray(data.results)) {
            renderSummary();
            renderResults([{domain: '-', status: 'error', method: '-', msg: data.msg || 'Cập nhật thất bại'}]);
            setButtonState(btn, 'error', 'Áp dụng lỗi');
            pushUiLog('Áp dụng thất bại: ' + (data.msg || 'unknown'), 'error');
            resetButtonTextLater(btn, originalText, 1200);
            return;
        }
        renderSummary(data.summary);
        renderResults(data.results || []);
        const hasError = (data.summary?.error ?? 0) > 0 || (data.summary?.not_found ?? 0) > 0;
        setButtonState(btn, hasError ? 'error' : 'success', hasError ? 'Áp dụng xong (có lỗi)' : 'Áp dụng thành công');
        if (hasError) {
            pushUiLog(`Áp dụng xong có lỗi: success ${data.summary?.success ?? 0}, error ${data.summary?.error ?? 0}, not_found ${data.summary?.not_found ?? 0}.`, 'warn');
        } else {
            pushUiLog(`Áp dụng thành công: ${data.summary?.success ?? 0}/${data.summary?.total ?? 0} domain.`, 'success');
        }
    } catch (e) {
        renderSummary();
        renderResults([{domain: '-', status: 'error', method: '-', msg: e?.message || 'Cập nhật thất bại'}]);
        setButtonState(btn, 'error', 'Áp dụng lỗi');
        pushUiLog('Áp dụng lỗi: ' + (e?.message || 'unknown'), 'error');
    }

    resetButtonTextLater(btn, originalText, 1200);
});
</script>
</body>
</html>
