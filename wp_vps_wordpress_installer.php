<?php
/**
 * wp_vps_wordpress_installer.php
 * Web UI to trigger WordPress auto-install script on selected VPS via SSH.
 */

$autoloadFile = __DIR__ . '/vendor/autoload.php';
$hasSshLibrary = file_exists($autoloadFile);
if ($hasSshLibrary) {
    require_once $autoloadFile;
}
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/CloudflareAPI.php';

use phpseclib3\Net\SSH2;

@set_time_limit(0);
@ini_set('max_execution_time', '0');

const SSH_PORT = 22;
const SSH_CONNECT_TIMEOUT = 15;
const SSH_COMMAND_TIMEOUT = 1800;

const AAPANEL_WP_API_ENABLED = true;
const AAPANEL_WP_API_URL = 'https://142.91.101.144:40188/apsess_85R3ROjCsDtSGZ7MXEXmDsazn937sa0j/v2/site?action=AddWPSite';
const AAPANEL_WP_API_HTTP_TOKEN = 'rXW51sDvFkrj1qqYS5ajBgPxebJpZG8SPac2WT3M4DtxkCM6';
const AAPANEL_WP_API_COOKIE = 'Path=; __stripe_mid=03aae26f-1884-4401-b6a0-78b007b281b0c55cf7; fbf0e552d36745b8fce5bcc7265fbae1=960354ee-f677-4e4b-abb6-9b9dbea9eca7.0_XIStB1VKLJQ6TejTAkQ4Z6MS0; __stripe_sid=09ee6ecc-fe01-4b0f-ada8-1ba2ae3449772d47ba';

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function loadVpsList(string $file = 'vps.json'): array
{
    $path = __DIR__ . '/' . $file;
    if (!file_exists($path)) {
        return [];
    }

    $raw = file_get_contents($path);
    if ($raw === false) {
        return [];
    }

    $rows = json_decode($raw, true);
    if (!is_array($rows)) {
        return [];
    }

    $result = [];
    foreach ($rows as $idx => $row) {
        if (!is_array($row) || empty($row['ip'])) {
            continue;
        }

        $ip = trim((string) $row['ip']);
        if ($ip === '') {
            continue;
        }

        $result[] = [
            'id' => (string) $idx,
            'label' => !empty($row['name'])
                ? ((string) $row['name'] . ' (' . $ip . ')')
                : ('VPS ' . $ip),
            'ip' => $ip,
            'username' => !empty($row['username']) ? (string) $row['username'] : 'root',
            'password' => (string) ($row['password'] ?? ''),
            'team' => (string) ($row['team'] ?? ''),
        ];
    }

    return $result;
}

function normalizeDomain(string $domain): string
{
    $domain = trim(strtolower($domain));
    $domain = preg_replace('#^https?://#', '', $domain);
    $domain = preg_replace('#^www\.#', '', $domain);
    $domain = explode('/', $domain)[0];
    return trim($domain);
}

function isValidDomain(string $domain): bool
{
    if ($domain === '' || strlen($domain) > 253) {
        return false;
    }

    if (!preg_match('/^(?=.{1,253}$)(?!-)(?:[a-z0-9-]{1,63}\.)+[a-z]{2,63}$/i', $domain)) {
        return false;
    }

    return true;
}

function parseDomains(string $input): array
{
    $parts = preg_split('/[\r\n,;\s]+/', $input);
    $valid = [];
    $invalid = [];
    $seen = [];

    foreach ($parts as $part) {
        $raw = trim((string) $part);
        if ($raw === '') {
            continue;
        }

        $domain = normalizeDomain($raw);
        if ($domain === '' || isset($seen[$domain])) {
            continue;
        }

        $seen[$domain] = true;
        if (isValidDomain($domain)) {
            $valid[] = $domain;
        } else {
            $invalid[] = $raw;
        }
    }

    return [$valid, $invalid];
}

function getZoneCandidatesForDomain(string $domain): array
{
    $parts = explode('.', strtolower(trim($domain)));
    $parts = array_values(array_filter($parts, static function ($item) {
        return $item !== '';
    }));

    $candidates = [];
    $count = count($parts);
    if ($count < 2) {
        return [];
    }

    for ($i = 0; $i <= $count - 2; $i++) {
        $candidates[] = implode('.', array_slice($parts, $i));
    }

    return array_values(array_unique($candidates));
}

function findZoneForDomainUsingCloudflare(CloudflareAPI $api, string $domain): ?array
{
    $candidates = getZoneCandidatesForDomain($domain);
    foreach ($candidates as $candidate) {
        $result = $api->searchZones($candidate, 1, 50, null, null, false);
        if (!empty($result['result']) && is_array($result['result'])) {
            foreach ($result['result'] as $zone) {
                if (strtolower((string) ($zone['name'] ?? '')) === strtolower($candidate)) {
                    return [
                        'id' => (string) ($zone['id'] ?? ''),
                        'name' => (string) ($zone['name'] ?? ''),
                    ];
                }
            }
        }
    }
    return null;
}

function isCloudflareConflictError(Throwable $e): bool
{
    $msg = (string) $e->getMessage();
    return strpos($msg, '81053') !== false || stripos($msg, 'record with that host already exists') !== false;
}

function deleteConflictingRecordsForName(CloudflareAPI $api, string $zoneId, string $name, string $targetType): array
{
    $deleted = [];

    try {
        $all = $api->listDNSRecords($zoneId, null, $name, false);
    } catch (Throwable $e) {
        return ['deleted' => $deleted, 'error' => $e->getMessage()];
    }

    foreach (($all['result'] ?? []) as $record) {
        $rType = strtoupper((string) ($record['type'] ?? ''));
        $rName = strtolower((string) ($record['name'] ?? ''));
        $id = (string) ($record['id'] ?? '');

        if ($rName !== strtolower($name) || $id === '') {
            continue;
        }

        $shouldDelete = false;
        if ($targetType === 'CNAME' && in_array($rType, ['A', 'AAAA', 'CNAME'], true)) {
            $shouldDelete = true;
        }
        if (in_array($targetType, ['A', 'AAAA'], true) && $rType === 'CNAME') {
            $shouldDelete = true;
        }

        if (!$shouldDelete) {
            continue;
        }

        try {
            $del = $api->deleteDNSRecord($zoneId, $id);
            if (!empty($del['success'])) {
                $deleted[] = $rType . ':' . $rName;
            }
        } catch (Throwable $e) {
            return ['deleted' => $deleted, 'error' => $e->getMessage()];
        }
    }

    return ['deleted' => $deleted, 'error' => ''];
}

function upsertDnsRecord(CloudflareAPI $api, string $zoneId, array $data): array
{
    $type = strtoupper((string) ($data['type'] ?? ''));
    $name = (string) ($data['name'] ?? '');
    $records = [];
    try {
        $records = $api->listDNSRecords($zoneId, $type, $name, false);
    } catch (Throwable $e) {
        return [
            'success' => false,
            'action' => 'failed',
            'type' => $type,
            'name' => $name,
            'content' => (string) ($data['content'] ?? ''),
            'error' => $e->getMessage(),
        ];
    }
    $existing = null;

    foreach (($records['result'] ?? []) as $record) {
        if (strtoupper((string) ($record['type'] ?? '')) === $type && strtolower((string) ($record['name'] ?? '')) === strtolower($name)) {
            $existing = $record;
            break;
        }
    }

    $payload = [
        'type' => $type,
        'name' => $name,
        'content' => (string) ($data['content'] ?? ''),
        'ttl' => (int) ($data['ttl'] ?? 1),
        'proxied' => (bool) ($data['proxied'] ?? false),
    ];

    if ($existing) {
        try {
            $resp = $api->updateDNSRecordAdvanced($zoneId, (string) $existing['id'], $payload);
        } catch (Throwable $e) {
            return [
                'success' => false,
                'action' => 'failed',
                'type' => $type,
                'name' => $name,
                'content' => (string) ($data['content'] ?? ''),
                'error' => $e->getMessage(),
            ];
        }
        return [
            'success' => (bool) ($resp['success'] ?? false),
            'action' => 'updated',
            'type' => $type,
            'name' => $name,
            'content' => (string) ($data['content'] ?? ''),
            'error' => $resp['errors'][0]['message'] ?? '',
        ];
    }

    try {
        $resp = $api->createDNSRecordAdvanced($zoneId, $payload);
    } catch (Throwable $e) {
        if (isCloudflareConflictError($e)) {
            $cleanup = deleteConflictingRecordsForName($api, $zoneId, $name, $type);
            if ($cleanup['error'] !== '') {
                return [
                    'success' => false,
                    'action' => 'failed',
                    'type' => $type,
                    'name' => $name,
                    'content' => (string) ($data['content'] ?? ''),
                    'error' => 'Conflict cleanup failed: ' . $cleanup['error'],
                ];
            }

            try {
                $resp = $api->createDNSRecordAdvanced($zoneId, $payload);
            } catch (Throwable $e2) {
                return [
                    'success' => false,
                    'action' => 'failed',
                    'type' => $type,
                    'name' => $name,
                    'content' => (string) ($data['content'] ?? ''),
                    'error' => 'Retry after conflict cleanup failed: ' . $e2->getMessage(),
                ];
            }
        } else {
            return [
                'success' => false,
                'action' => 'failed',
                'type' => $type,
                'name' => $name,
                'content' => (string) ($data['content'] ?? ''),
                'error' => $e->getMessage(),
            ];
        }
    }

    return [
        'success' => (bool) ($resp['success'] ?? false),
        'action' => 'created',
        'type' => $type,
        'name' => $name,
        'content' => (string) ($data['content'] ?? ''),
        'error' => $resp['errors'][0]['message'] ?? '',
    ];
}

function ensureCloudflareDnsForInstall(string $domain, string $ip): array
{
    try {
        $api = new CloudflareAPI();
    } catch (Throwable $e) {
        return [
            'success' => false,
            'message' => 'Cannot initialize Cloudflare API: ' . $e->getMessage(),
            'records' => [],
        ];
    }

    $zone = findZoneForDomainUsingCloudflare($api, $domain);
    if (!$zone || empty($zone['id']) || empty($zone['name'])) {
        return [
            'success' => false,
            'message' => 'Zone not found in Cloudflare account for domain ' . $domain,
            'records' => [],
        ];
    }

    $rootFqdn = $zone['name'];
    $wwwFqdn = 'www.' . $zone['name'];

    // Skip DNS update if records already point correctly to target VPS IP.
    try {
        $existingRoot = $api->listDNSRecords($zone['id'], 'A', $rootFqdn, false);
        $existingWwwA = $api->listDNSRecords($zone['id'], 'A', $wwwFqdn, false);
    } catch (Throwable $e) {
        $existingRoot = ['result' => []];
        $existingWwwA = ['result' => []];
    }

    $rootMatchesIp = false;
    foreach (($existingRoot['result'] ?? []) as $record) {
        if (strtoupper((string) ($record['type'] ?? '')) === 'A'
            && strtolower((string) ($record['name'] ?? '')) === strtolower($rootFqdn)
            && trim((string) ($record['content'] ?? '')) === $ip) {
            $rootMatchesIp = true;
            break;
        }
    }

    $wwwMatchesIp = false;
    foreach (($existingWwwA['result'] ?? []) as $record) {
        if (strtoupper((string) ($record['type'] ?? '')) === 'A'
            && strtolower((string) ($record['name'] ?? '')) === strtolower($wwwFqdn)
            && trim((string) ($record['content'] ?? '')) === $ip) {
            $wwwMatchesIp = true;
            break;
        }
    }

    if ($rootMatchesIp && $wwwMatchesIp) {
        return [
            'success' => true,
            'skipped' => true,
            'message' => 'DNS already points to VPS IP. Skipped DNS update step.',
            'zone' => $zone['name'],
            'records' => [],
            'https' => [
                'enabled' => false,
                'success' => true,
                'error' => '',
            ],
        ];
    }

    // Same behavior as dns_simple.php: root=A(IP), www=CNAME(root).
    $root = upsertDnsRecord($api, $zone['id'], [
        'type' => 'A',
        'name' => $rootFqdn,
        'content' => $ip,
        'ttl' => 1,
        'proxied' => true,
    ]);

    $www = upsertDnsRecord($api, $zone['id'], [
        'type' => 'CNAME',
        'name' => $wwwFqdn,
        'content' => $zone['name'],
        'ttl' => 1,
        'proxied' => true,
    ]);

    try {
        $httpsResult = $api->setAlwaysUseHTTPS($zone['id'], true);
        $httpsOk = (bool) ($httpsResult['success'] ?? false);
    } catch (Throwable $e) {
        $httpsResult = ['success' => false, 'errors' => [['message' => $e->getMessage()]]];
        $httpsOk = false;
    }

    $ok = $root['success'] && $www['success'];
    $msg = $ok
        ? 'DNS synced: ' . $root['action'] . ' ' . $root['name'] . ' and ' . $www['action'] . ' ' . $www['name']
        : 'DNS sync failed: ' . trim(($root['error'] ?? '') . ' ' . ($www['error'] ?? ''));
    if ($ok) {
        $msg .= $httpsOk ? ' | Always HTTPS: enabled' : ' | Always HTTPS: failed';
    }

    return [
        'success' => $ok,
        'skipped' => false,
        'message' => $msg,
        'zone' => $zone['name'],
        'records' => [$root, $www],
        'https' => [
            'enabled' => true,
            'success' => $httpsOk,
            'error' => $httpsResult['errors'][0]['message'] ?? '',
        ],
    ];
}

function parseInstallOutput(string $output): array
{
    $parsed = [];
    $lines = preg_split('/\r?\n/', $output);

    foreach ($lines as $line) {
        $line = trim((string) $line);
        if (preg_match('/^([A-Z0-9_]+)=(.*)$/', $line, $m)) {
            $parsed[$m[1]] = trim($m[2]);
        }
    }

    return $parsed;
}

function parseInstallSteps(string $output): array
{
    $steps = [];
    $currentIndex = -1;
    $lines = preg_split('/\r?\n/', $output);

    foreach ($lines as $line) {
        $rawLine = rtrim((string) $line, "\r\n");
        $trimLine = trim($rawLine);

        if (preg_match('/^__STEP_START__=(.+)$/', $trimLine, $m)) {
            $payload = trim($m[1]);
            $parts = explode('|', $payload, 2);
            $stepId = trim($parts[0]);
            $stepTitle = isset($parts[1]) ? trim($parts[1]) : ('Step ' . $stepId);

            $steps[] = [
                'id' => $stepId,
                'title' => $stepTitle,
                'status' => 'running',
                'logs' => [],
            ];
            $currentIndex = count($steps) - 1;
            continue;
        }

        if (preg_match('/^__STEP_OK__=(.+)$/', $trimLine, $m)) {
            $okId = trim($m[1]);
            for ($i = count($steps) - 1; $i >= 0; $i--) {
                if ($steps[$i]['id'] === $okId) {
                    $steps[$i]['status'] = 'success';
                    break;
                }
            }
            continue;
        }

        if (preg_match('/^__STEP_FAIL__=(.+)$/', $trimLine, $m)) {
            $payload = trim($m[1]);
            $parts = explode('|', $payload, 2);
            $failId = trim($parts[0]);
            $failMsg = isset($parts[1]) ? trim($parts[1]) : 'Failed';

            for ($i = count($steps) - 1; $i >= 0; $i--) {
                if ($steps[$i]['id'] === $failId) {
                    $steps[$i]['status'] = 'failed';
                    $steps[$i]['logs'][] = 'ERROR: ' . $failMsg;
                    break;
                }
            }
            continue;
        }

        if ($currentIndex >= 0 && preg_match('/^RESULT_STATUS=FAILED$/', $trimLine)) {
            $steps[$currentIndex]['status'] = 'failed';
            $steps[$currentIndex]['logs'][] = $trimLine;
            continue;
        }

        if ($currentIndex >= 0 && preg_match('/^ERROR=(.+)$/', $trimLine, $m)) {
            $steps[$currentIndex]['status'] = 'failed';
            $steps[$currentIndex]['logs'][] = 'ERROR: ' . trim($m[1]);
            continue;
        }

        if ($currentIndex >= 0 && $trimLine !== '') {
            $steps[$currentIndex]['logs'][] = $rawLine;
        }
    }

    foreach ($steps as &$step) {
        if ($step['status'] === 'running') {
            $step['status'] = 'unknown';
        }
    }
    unset($step);

    return $steps;
}

function extractErrorSummary(string $output, array $parsed, array $steps): string
{
    if (!empty($parsed['ERROR'])) {
        return (string) $parsed['ERROR'];
    }

    foreach ($steps as $step) {
        if (($step['status'] ?? '') === 'failed' && !empty($step['logs']) && is_array($step['logs'])) {
            foreach ($step['logs'] as $logLine) {
                $line = trim((string) $logLine);
                if ($line !== '') {
                    return $line;
                }
            }
        }
    }

    $candidates = [];
    $lines = preg_split('/\r?\n/', $output);
    foreach ($lines as $line) {
        $trimLine = trim((string) $line);
        if ($trimLine === '') {
            continue;
        }

        if (preg_match('/^ERROR=(.+)$/', $trimLine, $m)) {
            $candidates[] = trim($m[1]);
            continue;
        }

        if (preg_match('/^__STEP_FAIL__=([^|]+)\|(.+)$/', $trimLine, $m)) {
            $candidates[] = trim($m[2]);
            continue;
        }

        if (stripos($trimLine, 'error') !== false || stripos($trimLine, 'failed') !== false) {
            $candidates[] = $trimLine;
        }
    }

    if (!empty($candidates)) {
        return $candidates[0];
    }

    return '';
}

function formatStepStatusLabel(string $status): string
{
    $status = strtolower(trim($status));
    if ($status === 'success') {
        return 'SUCCESS';
    }
    if ($status === 'failed') {
        return 'FAILED';
    }
    if ($status === 'running') {
        return 'RUNNING';
    }
    return 'UNKNOWN';
}

function randomLowerHex(int $len): string
{
    return substr(bin2hex(random_bytes(max(1, (int) ceil($len / 2)))), 0, $len);
}

function randomLowerAlnum(int $len): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $out = '';
    for ($i = 0; $i < $len; $i++) {
        $out .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return $out;
}

function buildAaPanelWpPayload(string $domain): array
{
    $slug = preg_replace('/[^a-z0-9_]+/i', '_', strtolower($domain));
    $slug = trim((string) $slug, '_');
    if ($slug === '') {
        $slug = 'site_' . randomLowerAlnum(5);
    }

    $dbUser = substr('sql_' . $slug, 0, 16);
    $dbPass = randomLowerHex(12);
    $wpPrefix = 'wp_' . randomLowerHex(6) . '_';
    $wpUser = randomLowerHex(6);

    return [
        'webname' => json_encode([
            'domain' => $domain,
            'domainlist' => [],
            'count' => 0,
        ], JSON_UNESCAPED_SLASHES),
        'type' => 'PHP',
        'port' => '80',
        'type_id' => '0',
        'ftp' => 'false',
        'sql' => 'MySQL',
        'codeing' => 'utf8',
        'set_ssl' => '0',
        'force_ssl' => '0',
        'project_type' => 'WP2',
        'path' => '/www/wwwroot/' . $domain,
        'ps' => $slug,
        'version' => '84',
        'datauser' => $dbUser,
        'datapassword' => $dbPass,
        'password' => 'KaitoIT@@@123zaq',
        'pw_weak' => 'off',
        'email' => 'kaitoit.qh88@gmail.com',
        'weblog_title' => $domain,
        'language' => 'en',
        'user_name' => $wpUser,
        'prefix' => $wpPrefix,
        'enable_cache' => '1',
        'enable_whl' => '0',
        'whl_page' => 'wp-admin',
        'whl_redirect_admin' => '404',
        'package_version' => '7.0.1',
        'is_create_default_file' => 'true',
        'ssl_auto' => '1',
        'sub_dir' => '',
    ];
}

function createWordPressSiteViaAaPanelApi(string $domain): array
{
    if (!AAPANEL_WP_API_ENABLED) {
        return [
            'success' => false,
            'error' => 'aaPanel AddWPSite API is disabled',
            'response' => '',
            'status' => null,
            'payload' => [],
        ];
    }

    if (!function_exists('curl_init')) {
        return [
            'success' => false,
            'error' => 'PHP cURL extension is required for aaPanel API mode',
            'response' => '',
            'status' => null,
            'payload' => [],
        ];
    }

    $payload = buildAaPanelWpPayload($domain);
    $ch = curl_init(AAPANEL_WP_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => http_build_query($payload),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 15,
        CURLOPT_TIMEOUT => 120,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_HTTPHEADER => [
            'accept: application/json, text/plain, */*',
            'content-type: application/x-www-form-urlencoded',
            'origin: https://142.91.101.144:40188',
            'referer: https://142.91.101.144:40188/apsess_85R3ROjCsDtSGZ7MXEXmDsazn937sa0j/wp/toolkit',
            'x-http-token: ' . AAPANEL_WP_API_HTTP_TOKEN,
        ],
        CURLOPT_COOKIE => AAPANEL_WP_API_COOKIE,
    ]);

    $raw = (string) curl_exec($ch);
    $errno = curl_errno($ch);
    $err = curl_error($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($errno !== 0) {
        return [
            'success' => false,
            'error' => 'aaPanel API curl error: ' . $err,
            'response' => $raw,
            'status' => $status,
            'payload' => $payload,
        ];
    }

    $json = json_decode($raw, true);
    $ok = false;
    if (is_array($json)) {
        if (($json['status'] ?? null) === true || ($json['success'] ?? null) === true) {
            $ok = true;
        }
        if (($json['status'] ?? null) === 1 || ($json['success'] ?? null) === 1) {
            $ok = true;
        }
    }

    if (!$ok && $status >= 200 && $status < 300 && is_array($json) && empty($json['msg'])) {
        $ok = true;
    }

    if ($ok) {
        return [
            'success' => true,
            'error' => '',
            'response' => $raw,
            'status' => $status,
            'payload' => $payload,
        ];
    }

    $msg = 'aaPanel AddWPSite failed';
    if (is_array($json) && !empty($json['msg'])) {
        $msg .= ': ' . (string) $json['msg'];
    } elseif ($status > 0) {
        $msg .= ' (HTTP ' . $status . ')';
    }

    return [
        'success' => false,
        'error' => $msg,
        'response' => $raw,
        'status' => $status,
        'payload' => $payload,
    ];
}

function buildInlineInstallerCommand(string $domain, string $expectedIp): string
{
    $domainArg = escapeshellarg($domain);
    $expectedIpArg = escapeshellarg($expectedIp);

    $inlineScript = <<<'BASH'
set -Eeuo pipefail

DOMAIN="${1:-}"
EXPECTED_IP="${2:-}"
if [[ -z "$DOMAIN" ]]; then
    echo "RESULT_STATUS=FAILED"
    echo "ERROR=Missing domain argument"
    exit 1
fi

DOMAIN="${DOMAIN#http://}"
DOMAIN="${DOMAIN#https://}"
DOMAIN="${DOMAIN#www.}"
DOMAIN="${DOMAIN%%/*}"
DOMAIN="${DOMAIN,,}"

if ! [[ "$DOMAIN" =~ ^([a-z0-9-]+\.)+[a-z]{2,63}$ ]]; then
    echo "RESULT_STATUS=FAILED"
    echo "ERROR=Invalid domain format: $DOMAIN"
    exit 1
fi

if [[ "$EUID" -ne 0 ]]; then
    echo "RESULT_STATUS=FAILED"
    echo "ERROR=This installer must run as root"
    exit 1
fi

log() {
    printf '[%s] %s\n' "$(date '+%Y-%m-%d %H:%M:%S')" "$*"
}

CURRENT_STEP_ID=""

step_start() {
    CURRENT_STEP_ID="$1"
    echo "__STEP_START__=$1|$2"
}

step_ok() {
    echo "__STEP_OK__=$1"
}

step_fail() {
    echo "__STEP_FAIL__=$1|$2"
}

trap 'if [[ -n "$CURRENT_STEP_ID" ]]; then step_fail "$CURRENT_STEP_ID" "Command failed at line $LINENO"; fi' ERR

rand_alnum() {
    local len="${1:-16}"
    # `head` closes the pipe early; with `pipefail` this may return 141 (SIGPIPE).
    # Keep output deterministic for caller while preventing premature script exit.
    tr -dc 'A-Za-z0-9' < /dev/urandom | head -c "$len" || true
}

require_cmd() {
    local cmd="$1"
    if ! command -v "$cmd" >/dev/null 2>&1; then
        echo "RESULT_STATUS=FAILED"
        echo "ERROR=Missing required command: $cmd"
        exit 1
    fi
}

ensure_certbot() {
    if command -v certbot >/dev/null 2>&1; then
        return 0
    fi

    log "certbot not found, trying to install automatically"
    if command -v apt-get >/dev/null 2>&1; then
        export DEBIAN_FRONTEND=noninteractive
        apt-get update -y
        apt-get install -y certbot python3-certbot-nginx
        return 0
    fi

    if command -v dnf >/dev/null 2>&1; then
        dnf install -y certbot python3-certbot-nginx
        return 0
    fi

    if command -v yum >/dev/null 2>&1; then
        yum install -y certbot python3-certbot-nginx
        return 0
    fi

    echo "RESULT_STATUS=FAILED"
    echo "ERROR=Cannot install certbot automatically (unsupported package manager)"
    exit 1
}

resolve_ipv4() {
    local host="$1"
    local out=""

    if command -v getent >/dev/null 2>&1; then
        out="$(getent ahostsv4 "$host" | awk '{print $1}' | sort -u | tr '\n' ' ' | xargs || true)"
    fi

    if [[ -z "$out" ]] && command -v dig >/dev/null 2>&1; then
        out="$(dig +short A "$host" | grep -E '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$' | sort -u | tr '\n' ' ' | xargs || true)"
    fi

    if [[ -z "$out" ]] && command -v nslookup >/dev/null 2>&1; then
        out="$(nslookup -type=A "$host" 2>/dev/null | awk '/^Address: /{print $2}' | grep -E '^[0-9]+\.[0-9]+\.[0-9]+\.[0-9]+$' | sort -u | tr '\n' ' ' | xargs || true)"
    fi

    echo "$out"
}

dns_contains_ip() {
    local ips="$1"
    local expected="$2"
    for ip in $ips; do
        if [[ "$ip" == "$expected" ]]; then
            return 0
        fi
    done
    return 1
}

check_dns_before_ssl() {
    local root_ips www_ips
    DNS_READY="1"
    DNS_CHECK_MESSAGE=""

    root_ips="$(resolve_ipv4 "$DOMAIN")"
    www_ips="$(resolve_ipv4 "www.$DOMAIN")"

    if [[ -z "$root_ips" ]]; then
        DNS_READY="0"
        DNS_CHECK_MESSAGE="Domain has no resolvable A record: $DOMAIN"
        echo "DNS_PRECHECK_WARN=$DNS_CHECK_MESSAGE"
        return 0
    fi

    if [[ -z "$www_ips" ]]; then
        DNS_READY="0"
        DNS_CHECK_MESSAGE="Domain has no resolvable A record: www.$DOMAIN"
        echo "DNS_PRECHECK_WARN=$DNS_CHECK_MESSAGE"
        return 0
    fi

    if [[ -n "$EXPECTED_IP" ]]; then
        if ! dns_contains_ip "$root_ips" "$EXPECTED_IP"; then
            DNS_READY="0"
            DNS_CHECK_MESSAGE="DNS mismatch for $DOMAIN | expected=$EXPECTED_IP | got=$root_ips"
            echo "DNS_PRECHECK_WARN=$DNS_CHECK_MESSAGE"
            return 0
        fi

        if ! dns_contains_ip "$www_ips" "$EXPECTED_IP"; then
            DNS_READY="0"
            DNS_CHECK_MESSAGE="DNS mismatch for www.$DOMAIN | expected=$EXPECTED_IP | got=$www_ips"
            echo "DNS_PRECHECK_WARN=$DNS_CHECK_MESSAGE"
            return 0
        fi
    fi

    echo "DNS_PRECHECK_OK=Domain and www resolve to expected IP"
    return 0
}

detect_fastcgi_pass() {
    local sock
    sock="$(ls /run/php/php*-fpm.sock 2>/dev/null | head -n1 || true)"
    if [[ -n "$sock" ]]; then
        echo "unix:$sock"
        return
    fi

    sock="$(ls /var/run/php/php*-fpm.sock 2>/dev/null | head -n1 || true)"
    if [[ -n "$sock" ]]; then
        echo "unix:$sock"
        return
    fi

    echo "127.0.0.1:9000"
}

mysql_exec() {
    local sql="$1"
    local mysql_bin="mysql"

    if ! command -v "$mysql_bin" >/dev/null 2>&1 && command -v mariadb >/dev/null 2>&1; then
        mysql_bin="mariadb"
    fi

    # Build a broad socket candidate list because aaPanel/MariaDB paths vary by distro/install mode.
    local -a socket_candidates
    socket_candidates=(
        "/tmp/mysql.sock"
        "/run/mysqld/mysqld.sock"
        "/var/run/mysqld/mysqld.sock"
        "/www/server/mysql/mysql.sock"
        "/www/server/mysql/data/mysql.sock"
    )

    while IFS= read -r s; do
        [[ -S "$s" ]] && socket_candidates+=("$s")
    done < <(ls /run/mysql*.sock /var/run/mysql*.sock /www/server/mysql/*.sock 2>/dev/null || true)

    mysql_try_and_exec() {
        if "$mysql_bin" "$@" -e "SELECT 1" >/dev/null 2>&1; then
            "$mysql_bin" "$@" -e "$sql"
            return 0
        fi
        return 1
    }

    extract_mysql_users() {
        local candidates="root\nadmin\nmysql\n"
        local raw=""

        if [[ -f /www/server/panel/config/config.json ]]; then
            raw="$(grep -Eo '"mysql_(user|username)"\s*:\s*"[^"]+"' /www/server/panel/config/config.json | sed -E 's/.*:\s*"([^"]+)".*/\1/' || true)"
            candidates+="$raw\n"
        fi

        if [[ -f /root/.my.cnf ]]; then
            raw="$(grep -E '^user\s*=' /root/.my.cnf | head -n1 | sed -E 's/^user\s*=\s*//' || true)"
            candidates+="$raw\n"
        fi

        echo -e "$candidates" | sed '/^\s*$/d' | awk '!seen[$0]++'
    }

    extract_password_candidates() {
        local candidates=""
        local raw=""

        if [[ -f /www/server/data/mysqlRootPassword ]]; then
            raw="$(tr -d '\r' < /www/server/data/mysqlRootPassword | head -n 5)"
            # Keep raw line and also token after colon/space to support different aaPanel formats.
            candidates+="$raw\n"
            candidates+="$(echo "$raw" | awk -F':' '{print $NF}' | awk '{print $NF}')\n"
        fi

        if [[ -f /www/server/panel/config/config.json ]]; then
            raw="$(grep -Eo '"mysql_root"\s*:\s*"[^"]+"' /www/server/panel/config/config.json | head -n1 | sed -E 's/.*"mysql_root"\s*:\s*"([^"]+)".*/\1/')"
            candidates+="$raw\n"
        fi

        if [[ -f /root/.my.cnf ]]; then
            raw="$(grep -E '^password\s*=' /root/.my.cnf | head -n1 | sed -E 's/^password\s*=\s*//')"
            candidates+="$raw\n"
        fi

        echo -e "$candidates" | sed '/^\s*$/d' | awk '!seen[$0]++'
    }

    # Common Ubuntu setup: root via unix socket without password
    if mysql_try_and_exec --protocol=socket -uroot; then
        return
    fi

    # Try explicit sockets for root without password.
    local sock
    for sock in "${socket_candidates[@]}"; do
        if [[ -S "$sock" ]] && mysql_try_and_exec --protocol=socket --socket="$sock" -uroot; then
            return
        fi
    done

    # Some systems allow root TCP without password.
    if mysql_try_and_exec -h127.0.0.1 -P3306 -uroot; then
        return
    fi

    # Try common admin users without password.
    local mysql_user
    while IFS= read -r mysql_user; do
        [[ -z "$mysql_user" ]] && continue

        if mysql_try_and_exec --protocol=socket -u"$mysql_user"; then
            return
        fi

        for sock in "${socket_candidates[@]}"; do
            if [[ -S "$sock" ]] && mysql_try_and_exec --protocol=socket --socket="$sock" -u"$mysql_user"; then
                return
            fi
        done

        if mysql_try_and_exec -h127.0.0.1 -P3306 -u"$mysql_user"; then
            return
        fi
    done < <(extract_mysql_users)

    # Some installations allow mysql system user without password.
    if command -v sudo >/dev/null 2>&1 && id mysql >/dev/null 2>&1; then
        if sudo -u mysql "$mysql_bin" -e "SELECT 1" >/dev/null 2>&1; then
            sudo -u mysql "$mysql_bin" -e "$sql"
            return
        fi
    fi

    # aaPanel default password files fallback
    if [[ -f /www/server/panel/default.pl ]]; then
        local panel_default_pass
        panel_default_pass="$(tr -d '\r\n' < /www/server/panel/default.pl | awk '{print $NF}')"
        if [[ -n "$panel_default_pass" ]]; then
            if mysql_try_and_exec --protocol=socket -uroot --password="$panel_default_pass"; then
                return
            fi

            for sock in "${socket_candidates[@]}"; do
                if [[ -S "$sock" ]] && mysql_try_and_exec --protocol=socket --socket="$sock" -uroot --password="$panel_default_pass"; then
                    return
                fi
            done

            if mysql_try_and_exec -h127.0.0.1 -P3306 -uroot --password="$panel_default_pass"; then
                return
            fi
        fi
    fi

    # Standard root client config
    if [[ -f /root/.my.cnf ]]; then
        if "$mysql_bin" --defaults-extra-file=/root/.my.cnf -e "SELECT 1" >/dev/null 2>&1; then
            "$mysql_bin" --defaults-extra-file=/root/.my.cnf -e "$sql"
            return
        fi
    fi

    # Try discovered root password candidates from aaPanel/config files.
    local root_pass
    while IFS= read -r root_pass; do
        if [[ -z "$root_pass" ]]; then
            continue
        fi

        if mysql_try_and_exec --protocol=socket -uroot --password="$root_pass"; then
            return
        fi

        for sock in "${socket_candidates[@]}"; do
            if [[ -S "$sock" ]] && mysql_try_and_exec --protocol=socket --socket="$sock" -uroot --password="$root_pass"; then
                return
            fi
        done

        if mysql_try_and_exec -h127.0.0.1 -P3306 -uroot --password="$root_pass"; then
            return
        fi
    done < <(extract_password_candidates)

    # Try discovered passwords with all candidate users.
    local cand_user cand_pass
    while IFS= read -r cand_user; do
        [[ -z "$cand_user" ]] && continue

        while IFS= read -r cand_pass; do
            [[ -z "$cand_pass" ]] && continue

            if mysql_try_and_exec --protocol=socket -u"$cand_user" --password="$cand_pass"; then
                return
            fi

            for sock in "${socket_candidates[@]}"; do
                if [[ -S "$sock" ]] && mysql_try_and_exec --protocol=socket --socket="$sock" -u"$cand_user" --password="$cand_pass"; then
                    return
                fi
            done

            if mysql_try_and_exec -h127.0.0.1 -P3306 -u"$cand_user" --password="$cand_pass"; then
                return
            fi
        done < <(extract_password_candidates)
    done < <(extract_mysql_users)

    # Debian/MariaDB maintenance account fallback
    if [[ -f /etc/mysql/debian.cnf ]]; then
        if "$mysql_bin" --defaults-extra-file=/etc/mysql/debian.cnf -e "SELECT 1" >/dev/null 2>&1; then
            "$mysql_bin" --defaults-extra-file=/etc/mysql/debian.cnf -e "$sql"
            return
        fi
    fi

    echo "RESULT_STATUS=FAILED"
    echo "ERROR=Cannot authenticate MySQL automatically. Tried multiple users/passwords, socket and TCP, /root/.my.cnf, aaPanel files, and /etc/mysql/debian.cnf"
    echo "MYSQL_DEBUG_SOCKETS=$(printf '%s ' \"${socket_candidates[@]}\")"
    exit 1
}

init_install_context() {
    slug="$(echo "$DOMAIN" | tr '.' '_' | tr -cd 'a-z0-9_' | cut -c1-18)"
    SITE_ROOT="/var/www/$DOMAIN"
    WEB_ROOT="$SITE_ROOT"
    NGINX_AVAIL="/etc/nginx/sites-available/$DOMAIN.conf"
    NGINX_ENABLED="/etc/nginx/sites-enabled/$DOMAIN.conf"
    DB_NAME="wp_${slug}_$(rand_alnum 5 | tr 'A-Z' 'a-z')"
    DB_USER="u_${slug}_$(rand_alnum 4 | tr 'A-Z' 'a-z')"
    DB_USER="${DB_USER:0:31}"
    DB_PASS="$(rand_alnum 24)"
    WP_ADMIN_USER="admin"
    WP_ADMIN_PASS="KaitoIT@@@123zaq"
    WP_ADMIN_EMAIL="kaitoit.qh88@gmail.com"
    SITE_TITLE="${DOMAIN%%.*}"
    LE_EMAIL="$WP_ADMIN_EMAIL"
    FASTCGI_PASS="$(detect_fastcgi_pass)"
    SSL_STATUS="UNKNOWN"
}

section_precheck_dns() {
    require_cmd nginx
    require_cmd mysql
    require_cmd wp
    ensure_certbot
    check_dns_before_ssl
}

section_site_dirs() {
    log "Creating website directory: $WEB_ROOT"
    mkdir -p "$WEB_ROOT"
    chown -R www-data:www-data "$SITE_ROOT"
    find "$SITE_ROOT" -type d -exec chmod 755 {} \;
    find "$SITE_ROOT" -type f -exec chmod 644 {} \;
}

section_database() {
    log "Creating MySQL database and user"
    mysql_exec "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    mysql_exec "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
    mysql_exec "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost'; FLUSH PRIVILEGES;"
}

section_wordpress() {
    log "Downloading and configuring WordPress via WP-CLI"
    wp core download --path="$WEB_ROOT" --allow-root --force
    wp config create --path="$WEB_ROOT" --dbname="$DB_NAME" --dbuser="$DB_USER" --dbpass="$DB_PASS" --dbhost="localhost" --dbprefix="wp_" --skip-check --allow-root --force

    if ! wp core is-installed --path="$WEB_ROOT" --allow-root >/dev/null 2>&1; then
        wp core install \
            --path="$WEB_ROOT" \
            --url="https://$DOMAIN" \
            --title="$SITE_TITLE" \
            --admin_user="$WP_ADMIN_USER" \
            --admin_password="$WP_ADMIN_PASS" \
            --admin_email="$WP_ADMIN_EMAIL" \
            --skip-email \
            --allow-root
    else
        log "WordPress already installed, skipping core install"
    fi
}

section_site_and_wordpress() {
    section_site_dirs
    section_database
    section_wordpress
}

section_nginx() {
    log "Writing Nginx vhost config with www + non-www and try_files"
    cat > "$NGINX_AVAIL" <<EOF
server {
        listen 80;
        listen [::]:80;
        server_name $DOMAIN www.$DOMAIN;

        root $WEB_ROOT;
        index index.php index.html;

        access_log /var/log/nginx/${DOMAIN}_access.log;
        error_log /var/log/nginx/${DOMAIN}_error.log;

        location / {
                try_files \$uri \$uri/ /index.php?\$args;
        }

        location ~ \.php$ {
                include snippets/fastcgi-php.conf;
                fastcgi_pass $FASTCGI_PASS;
                fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        }

        location ~ /\.(?!well-known).* {
                deny all;
        }
}
EOF

    ln -sf "$NGINX_AVAIL" "$NGINX_ENABLED"
    nginx -t
    systemctl reload nginx
}

section_permalink() {
    log "Configuring permalink structure"
    wp rewrite structure '/%postname%/' --hard --path="$WEB_ROOT" --allow-root
    wp rewrite flush --hard --path="$WEB_ROOT" --allow-root
}

section_ssl() {
    log "Requesting and installing SSL certificate via Certbot"
    if [[ "${DNS_READY:-0}" == "1" ]]; then
        certbot --nginx \
            -d "$DOMAIN" \
            -d "www.$DOMAIN" \
            --non-interactive \
            --agree-tos \
            -m "$LE_EMAIL" \
            --redirect
        SSL_STATUS="SUCCESS"
    else
        SSL_STATUS="SKIPPED"
        echo "SSL_SKIPPED_REASON=${DNS_CHECK_MESSAGE:-DNS precheck did not pass}"
        log "Skip SSL because DNS not ready yet"
    fi
}

section_finalize_permissions() {
    log "Fixing ownership after install"
    chown -R www-data:www-data "$SITE_ROOT"
    find "$SITE_ROOT" -type d -exec chmod 755 {} \;
    find "$SITE_ROOT" -type f -exec chmod 644 {} \;
}

run_section() {
    local step_id="$1"
    local step_title="$2"
    local step_func="$3"

    step_start "$step_id" "$step_title"
    CURRENT_STEP_ID="$step_id"
    "$step_func"
    step_ok "$step_id"
}

init_install_context
run_section "01" "Precheck dependencies and DNS" section_precheck_dns
run_section "02" "Create site on VPS and install WordPress" section_site_and_wordpress
run_section "03" "Write Nginx vhost and reload service" section_nginx
run_section "04" "Set permalink rewrite structure" section_permalink
run_section "05" "Issue SSL certificate with Certbot" section_ssl
run_section "06" "Finalize ownership and permissions" section_finalize_permissions

echo "RESULT_STATUS=SUCCESS"
echo "DOMAIN=$DOMAIN"
echo "WEB_ROOT=$WEB_ROOT"
echo "DB_NAME=$DB_NAME"
echo "DB_USER=$DB_USER"
echo "DB_PASS=$DB_PASS"
echo "WP_ADMIN_URL=https://$DOMAIN/wp-admin/"
echo "WP_ADMIN_USER=$WP_ADMIN_USER"
echo "WP_ADMIN_PASS=$WP_ADMIN_PASS"
echo "SSL_STATUS=${SSL_STATUS:-UNKNOWN}"
BASH;

    // Normalize line endings to LF so remote bash does not parse options like "pipefail\r".
    $inlineScript = str_replace(["\r\n", "\r"], "\n", $inlineScript);

    return "TMP_SCRIPT=\"/tmp/wp_inline_installer_\$\$.sh\"\n"
        . "cat > \"\$TMP_SCRIPT\" <<'WP_INLINE_INSTALLER'\n{$inlineScript}\nWP_INLINE_INSTALLER\n"
        . "chmod +x \"\$TMP_SCRIPT\"\n"
        . "bash \"\$TMP_SCRIPT\" {$domainArg} {$expectedIpArg} 2>&1\n"
        . "rc=\$?\n"
        . "rm -f \"\$TMP_SCRIPT\"\n"
        . "exit \$rc";
}

function checkDomainExistsInAaPanelDb(array $vps, string $domain): array
{
    try {
        if (($vps['password'] ?? '') === '') {
            return [
                'ok' => false,
                'exists' => false,
                'error' => 'Missing SSH password in vps.json',
            ];
        }

        $ssh = new SSH2((string) $vps['ip'], SSH_PORT, SSH_CONNECT_TIMEOUT);
        $ssh->setTimeout(SSH_COMMAND_TIMEOUT);

        if (!$ssh->login((string) $vps['username'], (string) $vps['password'])) {
            return [
                'ok' => false,
                'exists' => false,
                'error' => 'SSH login failed while checking aaPanel database',
            ];
        }

        $safeDomain = escapeshellarg(strtolower(trim($domain)));
        $command =
            "DB='/www/server/panel/data/default.db'\n"
            . "if [ ! -f \"\$DB\" ]; then echo 'AAPANEL_DB_NOT_FOUND'; exit 2; fi\n"
            . "if command -v sqlite3 >/dev/null 2>&1; then\n"
            . "  count=$(sqlite3 \"\$DB\" \"SELECT COUNT(1) FROM sites WHERE LOWER(name)=LOWER($safeDomain);\" 2>/dev/null || true)\n"
            . "  if ! echo \"\$count\" | grep -Eq '^[0-9]+$'; then\n"
            . "    count=$(sqlite3 \"\$DB\" \"SELECT COUNT(1) FROM domain WHERE LOWER(name)=LOWER($safeDomain) OR LOWER(domain)=LOWER($safeDomain);\" 2>/dev/null || true)\n"
            . "  fi\n"
            . "  if ! echo \"\$count\" | grep -Eq '^[0-9]+$'; then\n"
            . "    count=$(sqlite3 \"\$DB\" \"SELECT COUNT(1) FROM domains WHERE LOWER(name)=LOWER($safeDomain) OR LOWER(domain)=LOWER($safeDomain);\" 2>/dev/null || true)\n"
            . "  fi\n"
            . "else\n"
            . "  echo 'SQLITE3_NOT_FOUND'\n"
            . "  exit 3\n"
            . "fi\n"
            . "count=$(echo \"\$count\" | tr -d '[:space:]')\n"
            . "if ! echo \"\$count\" | grep -Eq '^[0-9]+$'; then\n"
            . "  tbl=$(sqlite3 \"\$DB\" \".tables\" 2>/dev/null | tr '\n' ',' || true)\n"
            . "  echo \"CHECK_FAILED|tables=\$tbl\"\n"
            . "  exit 4\n"
            . "fi\n"
            . "if [ \"\$count\" -gt 0 ] 2>/dev/null; then echo 'EXISTS'; else echo 'NOT_EXISTS'; fi";

        $output = trim((string) $ssh->exec($command));
        $status = (int) ($ssh->getExitStatus() ?? 0);

        if (strpos($output, 'EXISTS') !== false) {
            return [
                'ok' => true,
                'exists' => true,
                'error' => '',
            ];
        }

        if (strpos($output, 'NOT_EXISTS') !== false) {
            return [
                'ok' => true,
                'exists' => false,
                'error' => '',
                'raw_output' => $output,
            ];
        }

        if (strpos($output, 'AAPANEL_DB_NOT_FOUND') !== false) {
            return [
                'ok' => false,
                'exists' => false,
                'error' => 'aaPanel database not found: /www/server/panel/data/default.db',
                'raw_output' => $output,
            ];
        }

        if (strpos($output, 'SQLITE3_NOT_FOUND') !== false) {
            return [
                'ok' => false,
                'exists' => false,
                'error' => 'sqlite3 is not installed on VPS',
                'raw_output' => $output,
            ];
        }

        if (strpos($output, 'CHECK_FAILED') !== false) {
            return [
                'ok' => false,
                'exists' => false,
                'error' => 'Cannot read aaPanel sites table from default.db',
                'raw_output' => $output,
            ];
        }

        return [
            'ok' => false,
            'exists' => false,
            'error' => 'Cannot verify domain in aaPanel default.db (exit=' . $status . ')',
            'raw_output' => $output,
        ];
    } catch (Throwable $e) {
        return [
            'ok' => false,
            'exists' => false,
            'error' => 'Exception while checking aaPanel database: ' . $e->getMessage(),
            'raw_output' => '',
        ];
    }
}

function runRemoteInstall(array $vps, string $domain): array
{
    try {
        if ($vps['password'] === '') {
            return [
                'success' => false,
                'status' => null,
                'log' => 'Missing SSH password in vps.json',
                'parsed' => [],
            ];
        }

        $ssh = new SSH2($vps['ip'], SSH_PORT, SSH_CONNECT_TIMEOUT);
        $ssh->setTimeout(SSH_COMMAND_TIMEOUT);

        if (!$ssh->login($vps['username'], $vps['password'])) {
            return [
                'success' => false,
                'status' => null,
                'log' => 'SSH login failed',
                'parsed' => [],
            ];
        }

        // DNS is usually proxied via Cloudflare, so direct-IP equality check is noisy.
        $command = buildInlineInstallerCommand($domain, '');

        $output = (string) $ssh->exec($command);
        $status = $ssh->getExitStatus();
        $parsed = parseInstallOutput($output);
        $steps = parseInstallSteps($output);
        $errorSummary = extractErrorSummary($output, $parsed, $steps);

        if (trim($output) === '') {
            if ((int) $status === 141) {
                $errorSummary = 'Remote installer exited with status 141 (SIGPIPE). This is usually caused by shell pipeline interruption on VPS.';
            } else {
                $errorSummary = 'No output returned from remote installer. Check SSH shell policy and bash availability on VPS.';
            }
        }

        $success = false;
        if (($status === 0 || $status === null) && (($parsed['RESULT_STATUS'] ?? '') === 'SUCCESS')) {
            $success = true;
        }

        return [
            'success' => $success,
            'status' => $status,
            'log' => trim($output),
            'parsed' => $parsed,
            'steps' => $steps,
            'error' => $errorSummary,
        ];
    } catch (Throwable $e) {
        return [
            'success' => false,
            'status' => null,
            'log' => 'Exception: ' . $e->getMessage(),
            'parsed' => [],
            'steps' => [],
            'error' => $e->getMessage(),
        ];
    }
}

$vpsList = loadVpsList();
$selectedVpsId = '';
$domainInput = '';
$globalError = '';
$results = [];
$invalidDomains = [];
$autoDnsSync = true;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selectedVpsId = (string) ($_POST['vps_id'] ?? '');
    $domainInput = trim((string) ($_POST['domains'] ?? ''));
    $autoDnsSync = isset($_POST['auto_dns_sync']) && (string) $_POST['auto_dns_sync'] === '1';

    if (!$hasSshLibrary) {
        $globalError = 'Missing phpseclib library. Run: composer install';
    } elseif (count($vpsList) === 0) {
        $globalError = 'No VPS found in vps.json';
    } else {
        [$domains, $invalidDomains] = parseDomains($domainInput);
        if (count($domains) === 0) {
            $globalError = 'Please enter at least 1 valid domain';
        } else {
            $selectedVps = null;
            foreach ($vpsList as $vps) {
                if ($vps['id'] === $selectedVpsId) {
                    $selectedVps = $vps;
                    break;
                }
            }

            if ($selectedVps === null) {
                $globalError = 'Please select a valid VPS';
            } else {
                foreach ($domains as $domain) {
                    $siteCheck = checkDomainExistsInAaPanelDb($selectedVps, $domain);
                    $siteCheckWarning = '';
                    if (!$siteCheck['ok']) {
                        $siteCheckWarning = 'Domain existence check warning: ' . ($siteCheck['error'] ?? 'Cannot verify domain in aaPanel database');
                        if (!empty($siteCheck['raw_output'])) {
                            $siteCheckWarning .= ' | Raw: ' . (string) $siteCheck['raw_output'];
                        }
                    }

                    if (!empty($siteCheck['exists'])) {
                        $results[] = [
                            'domain' => $domain,
                            'ok' => true,
                            'skipped' => true,
                            'status' => null,
                            'log' => 'Skip install: domain already exists in aaPanel default.db (sites table).',
                            'data' => ['RESULT_STATUS' => 'SKIPPED'],
                            'steps' => [],
                            'error' => '',
                            'dns_sync' => ['success' => null, 'message' => 'Skipped because domain already exists', 'records' => []],
                        ];
                        continue;
                    }

                    $apiCreate = createWordPressSiteViaAaPanelApi($domain);
                    if ($apiCreate['success']) {
                        $apiLog = 'Created by aaPanel AddWPSite API';
                        if ($siteCheckWarning !== '') {
                            $apiLog .= "\n" . $siteCheckWarning;
                        }
                        if (!empty($apiCreate['response'])) {
                            $apiLog .= "\nAPI_RESPONSE=" . $apiCreate['response'];
                        }

                        $results[] = [
                            'domain' => $domain,
                            'ok' => true,
                            'skipped' => false,
                            'status' => $apiCreate['status'] ?? 0,
                            'log' => $apiLog,
                            'data' => [
                                'RESULT_STATUS' => 'SUCCESS',
                                'DOMAIN' => $domain,
                                'WEB_ROOT' => '/www/wwwroot/' . $domain,
                                'WP_ADMIN_USER' => $apiCreate['payload']['user_name'] ?? '',
                                'WP_ADMIN_PASS' => $apiCreate['payload']['password'] ?? '',
                                'DB_USER' => $apiCreate['payload']['datauser'] ?? '',
                                'DB_PASS' => $apiCreate['payload']['datapassword'] ?? '',
                            ],
                            'steps' => [[
                                'id' => '02',
                                'title' => 'Create site on aaPanel API (AddWPSite)',
                                'status' => 'success',
                                'logs' => [
                                    'aaPanel API endpoint accepted request',
                                    'Path: ' . ($apiCreate['payload']['path'] ?? ''),
                                ],
                            ]],
                            'error' => '',
                            'dns_sync' => ['success' => null, 'message' => 'Skipped in API mode', 'records' => []],
                        ];
                        continue;
                    }

                    $dnsSync = ['success' => null, 'message' => 'Skipped', 'records' => []];
                    if ($autoDnsSync) {
                        $dnsSync = ensureCloudflareDnsForInstall($domain, (string) $selectedVps['ip']);
                        if (!$dnsSync['success']) {
                            $results[] = [
                                'domain' => $domain,
                                'ok' => false,
                                'status' => 1,
                                'log' => 'DNS sync failed before installer run.',
                                'data' => [],
                                'steps' => [],
                                'error' => $dnsSync['message'] ?? 'DNS sync failed',
                                'dns_sync' => $dnsSync,
                            ];
                            continue;
                        }
                    }

                    $installResult = runRemoteInstall($selectedVps, $domain);
                    $finalLog = $installResult['log'];
                    if ($siteCheckWarning !== '') {
                        $finalLog = $siteCheckWarning . "\n" . $finalLog;
                    }
                    $finalLog = 'aaPanel API create failed, fallback SSH installer. Reason: ' . ($apiCreate['error'] ?? 'unknown') . "\n" . $finalLog;
                    if (!empty($apiCreate['response'])) {
                        $finalLog .= "\nAPI_RESPONSE=" . (string) $apiCreate['response'];
                    }

                    $results[] = [
                        'domain' => $domain,
                        'ok' => $installResult['success'],
                        'skipped' => false,
                        'status' => $installResult['status'],
                        'log' => $finalLog,
                        'data' => $installResult['parsed'],
                        'steps' => $installResult['steps'],
                        'error' => $installResult['error'] !== '' ? $installResult['error'] : $siteCheckWarning,
                        'dns_sync' => $dnsSync,
                    ];
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WordPress VPS Auto Installer</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg: #f4f7f2;
            --card: #ffffff;
            --primary: #0f766e;
            --accent: #f59e0b;
            --ink: #1f2937;
        }

        body {
            background:
                radial-gradient(circle at 20% 10%, rgba(245, 158, 11, 0.18), transparent 38%),
                radial-gradient(circle at 90% 90%, rgba(15, 118, 110, 0.16), transparent 32%),
                var(--bg);
            color: var(--ink);
            font-family: "Segoe UI", Tahoma, sans-serif;
            min-height: 100vh;
        }

        .app-shell {
            max-width: 1080px;
        }

        .title-card {
            background: linear-gradient(135deg, #0f766e, #115e59);
            color: #fff;
            border: 0;
            border-radius: 18px;
        }

        .title-card .badge {
            background: rgba(255, 255, 255, 0.18);
            border: 1px solid rgba(255, 255, 255, 0.34);
        }

        .main-card {
            border: 0;
            border-radius: 16px;
            background: var(--card);
            box-shadow: 0 14px 36px rgba(15, 23, 42, 0.08);
        }

        .form-control,
        .form-select {
            border-radius: 10px;
            border-color: #cbd5e1;
        }

        .btn-primary {
            background: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: #0b5e58;
            border-color: #0b5e58;
        }

        .btn-delete-dns {
            background: #dc2626;
            border-color: #dc2626;
            color: #fff;
            font-weight: 600;
        }

        .btn-delete-dns:hover {
            background: #b91c1c;
            border-color: #b91c1c;
            color: #fff;
        }

        .result-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
        }

        .result-ok {
            border-left: 5px solid #16a34a;
        }

        .result-fail {
            border-left: 5px solid #dc2626;
        }

        pre {
            background: #0f172a;
            color: #dbeafe;
            border-radius: 10px;
            padding: 14px;
            max-height: 260px;
            overflow: auto;
            margin-bottom: 0;
            font-size: 0.84rem;
        }

        .kv {
            font-size: 0.9rem;
            line-height: 1.6;
        }

        .step-list {
            margin-top: 10px;
        }

        .step-item {
            border: 1px solid #dbe3ef;
            border-radius: 10px;
            padding: 8px 10px;
            margin-bottom: 8px;
            background: #fafcff;
        }

        .step-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
            font-size: 0.9rem;
        }

        .step-log {
            margin-top: 8px;
            background: #0f172a;
            color: #dbeafe;
            border-radius: 8px;
            padding: 8px;
            max-height: 180px;
            overflow: auto;
            font-size: 0.8rem;
            white-space: pre-wrap;
        }

        .step-title {
            font-weight: 600;
        }

        .step-empty {
            margin-top: 8px;
            color: #64748b;
            font-size: 0.82rem;
            font-style: italic;
        }
    </style>
</head>
<body>
<div class="container py-4 app-shell">
    <div class="card title-card mb-4">
        <div class="card-body p-4">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                <h1 class="h4 mb-0">WordPress Auto Install on VPS</h1>
                <span class="badge rounded-pill">SSH + Bash + WP-CLI + Nginx + SSL</span>
                <a href="DeleteDNS.php" class="btn btn-sm btn-delete-dns ms-auto">
                    Xoa DNS Records
                </a>
            </div>
            <p class="mb-0 opacity-75">
                Chon 1 VPS, nhap danh sach domain, he thong se SSH va cai dat inline (khong can file .sh), tu dong cai certbot neu thieu va check DNS truoc khi cap SSL.
            </p>
        </div>
    </div>

    <div class="card main-card mb-4">
        <div class="card-body p-4">
            <?php if ($globalError !== ''): ?>
                <div class="alert alert-danger"><?php echo h($globalError); ?></div>
            <?php endif; ?>

            <?php if (count($invalidDomains) > 0): ?>
                <div class="alert alert-warning">
                    Bo qua domain khong hop le: <?php echo h(implode(', ', $invalidDomains)); ?>
                </div>
            <?php endif; ?>

            <form method="post">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Select VPS</label>
                    <select name="vps_id" class="form-select" required>
                        <option value="">-- Select VPS --</option>
                        <?php foreach ($vpsList as $vps): ?>
                            <option value="<?php echo h($vps['id']); ?>" <?php echo $selectedVpsId === $vps['id'] ? 'selected' : ''; ?>>
                                <?php echo h($vps['label']); ?><?php echo $vps['team'] !== '' ? (' | Team: ' . h($vps['team'])) : ''; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Domain List (1 domain per line)</label>
                    <textarea name="domains" class="form-control" rows="8" placeholder="example.com&#10;myblog.net" required><?php echo h($domainInput); ?></textarea>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" type="checkbox" name="auto_dns_sync" id="auto_dns_sync" value="1" <?php echo $autoDnsSync ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="auto_dns_sync">
                        Auto update Cloudflare A records (@ + www) to selected VPS IP before install
                    </label>
                </div>

                <div class="d-flex flex-wrap gap-2">
                    <button type="submit" class="btn btn-primary px-4">Start Auto Install</button>
                    <a href="DeleteDNS.php" class="btn btn-delete-dns px-4">
                        Xoa DNS Records
                    </a>
                </div>
            </form>
        </div>
    </div>

    <?php if (count($results) > 0): ?>
        <div class="card main-card">
            <div class="card-body p-4">
                <h2 class="h5 mb-3">Execution Results</h2>
                <?php foreach ($results as $item): ?>
                    <div class="result-card mb-3 p-3 <?php echo !empty($item['skipped']) ? '' : ($item['ok'] ? 'result-ok' : 'result-fail'); ?>">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <strong><?php echo h($item['domain']); ?></strong>
                            <span class="badge <?php echo !empty($item['skipped']) ? 'text-bg-secondary' : ($item['ok'] ? 'text-bg-success' : 'text-bg-danger'); ?>">
                                <?php echo !empty($item['skipped']) ? 'SKIPPED' : ($item['ok'] ? 'SUCCESS' : 'FAILED'); ?>
                            </span>
                        </div>

                        <div class="kv mb-2">
                            <div>Exit Status: <?php echo h((string) ($item['status'] === null ? 'null' : $item['status'])); ?></div>
                            <?php if (isset($item['dns_sync'])): ?>
                                <div class="small mt-1">
                                    DNS Sync: <strong><?php echo h(!empty($item['dns_sync']['skipped']) ? 'SKIPPED' : ($item['dns_sync']['success'] === true ? 'SUCCESS' : ($item['dns_sync']['success'] === false ? 'FAILED' : 'SKIPPED'))); ?></strong>
                                    <?php if (!empty($item['dns_sync']['message'])): ?>
                                        - <?php echo h($item['dns_sync']['message']); ?>
                                    <?php endif; ?>
                                    <?php if (!empty($item['dns_sync']['records']) && is_array($item['dns_sync']['records'])): ?>
                                        <?php foreach ($item['dns_sync']['records'] as $record): ?>
                                            <div>
                                                • <?php echo h(($record['action'] ?? '-') . ' ' . ($record['type'] ?? '-') . ' ' . ($record['name'] ?? '-') . ' -> ' . ($record['content'] ?? '-')); ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!$item['ok'] && !empty($item['error'])): ?>
                                <div class="alert alert-danger py-2 mt-2 mb-2 small">
                                    <strong>Error Summary:</strong> <?php echo h($item['error']); ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($item['data']['WP_ADMIN_URL'])): ?>
                                <div>Admin URL: <a href="<?php echo h($item['data']['WP_ADMIN_URL']); ?>" target="_blank" rel="noopener"><?php echo h($item['data']['WP_ADMIN_URL']); ?></a></div>
                            <?php endif; ?>
                            <?php if (!empty($item['data']['WP_ADMIN_USER'])): ?>
                                <div>Admin User: <?php echo h($item['data']['WP_ADMIN_USER']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($item['data']['WP_ADMIN_PASS'])): ?>
                                <div>Admin Pass: <?php echo h($item['data']['WP_ADMIN_PASS']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($item['data']['DB_NAME'])): ?>
                                <div>DB Name: <?php echo h($item['data']['DB_NAME']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($item['data']['DB_USER'])): ?>
                                <div>DB User: <?php echo h($item['data']['DB_USER']); ?></div>
                            <?php endif; ?>
                            <?php if (!empty($item['data']['DB_PASS'])): ?>
                                <div>DB Pass: <?php echo h($item['data']['DB_PASS']); ?></div>
                            <?php endif; ?>
                        </div>

                        <div class="mt-2">
                            <div class="fw-semibold mb-2">Step Execution Log</div>
                            <?php if (!empty($item['steps'])): ?>
                                <div class="step-list">
                                    <?php foreach ($item['steps'] as $step): ?>
                                        <div class="step-item">
                                            <div class="step-header">
                                                <span class="step-title"><?php echo h(($step['id'] !== '' ? ($step['id'] . '. ') : '') . $step['title']); ?></span>
                                                <span class="badge <?php echo $step['status'] === 'success' ? 'text-bg-success' : ($step['status'] === 'failed' ? 'text-bg-danger' : 'text-bg-secondary'); ?>">
                                                    <?php echo h(formatStepStatusLabel((string) $step['status'])); ?>
                                                </span>
                                            </div>
                                            <?php if (!empty($step['logs'])): ?>
                                                <div class="step-log"><?php echo h(implode("\n", $step['logs'])); ?></div>
                                            <?php else: ?>
                                                <div class="step-empty">No command output in this step.</div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="text-muted small mt-2">No step markers found in remote output.</div>
                            <?php endif; ?>
                        </div>

                        <details class="mt-2">
                            <summary>Show full log</summary>
                            <pre><?php echo h($item['log']); ?></pre>
                        </details>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
