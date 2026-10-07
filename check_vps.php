<?php
/**
 * Simple VPS health check page.
 *
 * Usage:
 * - Open in browser: check_vps.php
 * - JSON mode: check_vps.php?format=json
 */

declare(strict_types=1);

function formatBytes(float $bytes): string
{
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;

    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }

    return number_format($bytes, 2) . ' ' . $units[$i];
}

function parseLinuxMemInfo(): ?array
{
    $meminfoPath = '/proc/meminfo';
    if (!is_readable($meminfoPath)) {
        return null;
    }

    $content = @file_get_contents($meminfoPath);
    if ($content === false) {
        return null;
    }

    $info = [];
    foreach (explode("\n", $content) as $line) {
        if (preg_match('/^(\w+):\s+(\d+)\s+kB$/', trim($line), $m)) {
            $info[$m[1]] = (int) $m[2] * 1024;
        }
    }

    if (!isset($info['MemTotal'], $info['MemAvailable'])) {
        return null;
    }

    $total = (float) $info['MemTotal'];
    $available = (float) $info['MemAvailable'];
    $used = $total - $available;

    return [
        'total' => $total,
        'used' => $used,
        'free' => $available,
        'usage_percent' => $total > 0 ? round(($used / $total) * 100, 2) : 0.0,
    ];
}

function parseLinuxUptime(): ?array
{
    $uptimePath = '/proc/uptime';
    if (!is_readable($uptimePath)) {
        return null;
    }

    $content = @file_get_contents($uptimePath);
    if ($content === false) {
        return null;
    }

    $parts = explode(' ', trim($content));
    if (!isset($parts[0]) || !is_numeric($parts[0])) {
        return null;
    }

    $seconds = (int) floor((float) $parts[0]);

    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);

    return [
        'seconds' => $seconds,
        'human' => sprintf('%dd %dh %dm', $days, $hours, $minutes),
    ];
}

function getCpuLoadInfo(): array
{
    if (!function_exists('sys_getloadavg')) {
        return [
            'available' => false,
            'message' => 'CPU load not available on this OS/setup',
        ];
    }

    $load = @sys_getloadavg();
    if (!is_array($load)) {
        return [
            'available' => false,
            'message' => 'CPU load not available on this OS/setup',
        ];
    }

    return [
        'available' => true,
        'load_1m' => round((float) $load[0], 2),
        'load_5m' => round((float) $load[1], 2),
        'load_15m' => round((float) $load[2], 2),
    ];
}

function getDiskInfo(string $path = '/'): array
{
    $total = @disk_total_space($path);
    $free = @disk_free_space($path);

    if ($total === false || $free === false) {
        return [
            'available' => false,
            'path' => $path,
            'message' => 'Disk info not available',
        ];
    }

    $used = (float) $total - (float) $free;

    return [
        'available' => true,
        'path' => $path,
        'total' => (float) $total,
        'used' => $used,
        'free' => (float) $free,
        'usage_percent' => $total > 0 ? round(($used / (float) $total) * 100, 2) : 0.0,
    ];
}

function getPublicIp(): ?string
{
    $services = [
        'https://api.ipify.org',
        'https://ifconfig.me/ip',
    ];

    foreach ($services as $url) {
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 2,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
            ],
        ]);

        $ip = @file_get_contents($url, false, $ctx);
        if ($ip !== false) {
            $ip = trim($ip);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return null;
}

function maskSecret(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }

    $len = strlen($value);
    if ($len <= 4) {
        return str_repeat('*', $len);
    }

    return substr($value, 0, 2) . str_repeat('*', max(0, $len - 4)) . substr($value, -2);
}

function loadVpsListFromJson(string $filePath): array
{
    if (!is_readable($filePath)) {
        return [];
    }

    $raw = @file_get_contents($filePath);
    if ($raw === false) {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        return [];
    }

    $list = [];
    foreach ($decoded as $row) {
        if (!is_array($row)) {
            continue;
        }

        $ip = trim((string) ($row['ip'] ?? ''));
        if ($ip === '') {
            continue;
        }

        $list[] = [
            'ip' => $ip,
            'username' => trim((string) ($row['username'] ?? '')),
            'password_masked' => maskSecret((string) ($row['password'] ?? '')),
            'info' => trim((string) ($row['info'] ?? '')),
            'aapanel_keyapi_masked' => maskSecret((string) ($row['aapanel_keyapi'] ?? '')),
            'team' => trim((string) ($row['team'] ?? '')),
        ];
    }

    return $list;
}

function getSelectedVps(array $vpsList, ?string $requestedIp): ?array
{
    if (empty($vpsList)) {
        return null;
    }

    $requestedIp = trim((string) $requestedIp);
    if ($requestedIp !== '') {
        foreach ($vpsList as $vps) {
            if (($vps['ip'] ?? '') === $requestedIp) {
                return $vps;
            }
        }
    }

    return $vpsList[0];
}

function collectVpsStatus(): array
{
    $path = DIRECTORY_SEPARATOR === '\\' ? 'C:/' : '/';
    $memory = parseLinuxMemInfo();
    $vpsFile = __DIR__ . DIRECTORY_SEPARATOR . 'vps.json';
    $vpsList = loadVpsListFromJson($vpsFile);
    $selectedVps = getSelectedVps($vpsList, $_GET['vps_ip'] ?? null);

    $result = [
        'timestamp' => date('Y-m-d H:i:s'),
        'server_name' => gethostname() ?: php_uname('n'),
        'php_version' => PHP_VERSION,
        'os' => php_uname('s') . ' ' . php_uname('r'),
        'local_ip' => $_SERVER['SERVER_ADDR'] ?? gethostbyname(gethostname()),
        'public_ip' => getPublicIp(),
        'cpu' => getCpuLoadInfo(),
        'memory' => $memory,
        'disk' => getDiskInfo($path),
        'uptime' => parseLinuxUptime(),
        'vps_json_file' => $vpsFile,
        'vps_count' => count($vpsList),
        'vps_list' => $vpsList,
        'selected_vps' => $selectedVps,
    ];

    return $result;
}

$status = collectVpsStatus();

if (isset($_GET['format']) && strtolower((string) $_GET['format']) === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($status, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

function safeValue($value, string $fallback = 'N/A'): string
{
    if ($value === null || $value === '') {
        return $fallback;
    }

    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>VPS Health Check</title>
    <style>
        :root {
            --bg: #f4f7fb;
            --card: #ffffff;
            --text: #0f172a;
            --muted: #475569;
            --ok: #059669;
            --warn: #d97706;
            --border: #dbe2ea;
            --accent: #0ea5e9;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            padding: 24px;
            font-family: "Segoe UI", Tahoma, sans-serif;
            color: var(--text);
            background:
                radial-gradient(circle at 5% 5%, #dff5ff 0%, transparent 30%),
                radial-gradient(circle at 95% 5%, #d8fce7 0%, transparent 35%),
                var(--bg);
        }

        .wrap {
            max-width: 980px;
            margin: 0 auto;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 16px;
            gap: 12px;
            flex-wrap: wrap;
        }

        h1 {
            margin: 0;
            font-size: 26px;
        }

        .meta {
            color: var(--muted);
            font-size: 14px;
        }

        .btn {
            border: 1px solid var(--border);
            background: var(--card);
            color: var(--text);
            border-radius: 10px;
            padding: 9px 14px;
            cursor: pointer;
            text-decoration: none;
        }

        .btn:hover {
            border-color: var(--accent);
            color: #0369a1;
        }

        .grid {
            display: grid;
            gap: 12px;
            grid-template-columns: repeat(auto-fit, minmax(230px, 1fr));
        }

        .card {
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 14px;
            padding: 14px;
            box-shadow: 0 8px 20px rgba(15, 23, 42, 0.04);
        }

        .label {
            color: var(--muted);
            font-size: 13px;
            margin-bottom: 6px;
        }

        .value {
            font-size: 20px;
            font-weight: 700;
        }

        .small {
            font-size: 13px;
            color: var(--muted);
            margin-top: 6px;
        }

        .table-wrap {
            margin-top: 14px;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            background: var(--card);
            border: 1px solid var(--border);
            border-radius: 12px;
            overflow: hidden;
        }

        th, td {
            border-bottom: 1px solid var(--border);
            text-align: left;
            padding: 10px;
            font-size: 13px;
        }

        th {
            background: #eef5fb;
            color: #1e3a56;
        }

        tr:last-child td {
            border-bottom: none;
        }

        .ok { color: var(--ok); }
        .warn { color: var(--warn); }

        .footer {
            margin-top: 16px;
            font-size: 13px;
            color: var(--muted);
        }
    </style>
</head>
<body>
<div class="wrap">
    <div class="header">
        <div>
            <h1>VPS Health Check</h1>
            <div class="meta">Updated: <?php echo safeValue($status['timestamp']); ?></div>
        </div>
        <div>
            <a class="btn" href="?">Refresh</a>
            <a class="btn" href="?format=json">View JSON</a>
        </div>
    </div>

    <div class="grid">
        <div class="card">
            <div class="label">Server</div>
            <div class="value"><?php echo safeValue($status['server_name']); ?></div>
            <div class="small"><?php echo safeValue($status['os']); ?></div>
        </div>

        <div class="card">
            <div class="label">IP Address</div>
            <div class="small">Local: <?php echo safeValue($status['local_ip']); ?></div>
            <div class="small">Public: <?php echo safeValue($status['public_ip']); ?></div>
        </div>

        <div class="card">
            <div class="label">PHP Version</div>
            <div class="value"><?php echo safeValue($status['php_version']); ?></div>
        </div>

        <div class="card">
            <div class="label">CPU Load (1m / 5m / 15m)</div>
            <?php if (!empty($status['cpu']['available'])): ?>
                <div class="value"><?php echo safeValue((string) $status['cpu']['load_1m']); ?> / <?php echo safeValue((string) $status['cpu']['load_5m']); ?> / <?php echo safeValue((string) $status['cpu']['load_15m']); ?></div>
            <?php else: ?>
                <div class="value warn">N/A</div>
                <div class="small"><?php echo safeValue($status['cpu']['message'] ?? 'No data'); ?></div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="label">Memory</div>
            <?php if (!empty($status['memory'])): ?>
                <div class="value <?php echo ($status['memory']['usage_percent'] > 85) ? 'warn' : 'ok'; ?>">
                    <?php echo safeValue((string) $status['memory']['usage_percent']); ?>%
                </div>
                <div class="small">
                    Used: <?php echo safeValue(formatBytes((float) $status['memory']['used'])); ?> /
                    Total: <?php echo safeValue(formatBytes((float) $status['memory']['total'])); ?>
                </div>
            <?php else: ?>
                <div class="value warn">N/A</div>
                <div class="small">Memory detail is available on Linux (/proc/meminfo).</div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="label">Disk (System)</div>
            <?php if (!empty($status['disk']['available'])): ?>
                <div class="value <?php echo ($status['disk']['usage_percent'] > 85) ? 'warn' : 'ok'; ?>">
                    <?php echo safeValue((string) $status['disk']['usage_percent']); ?>%
                </div>
                <div class="small">
                    Used: <?php echo safeValue(formatBytes((float) $status['disk']['used'])); ?> /
                    Total: <?php echo safeValue(formatBytes((float) $status['disk']['total'])); ?>
                </div>
            <?php else: ?>
                <div class="value warn">N/A</div>
                <div class="small"><?php echo safeValue($status['disk']['message'] ?? 'No data'); ?></div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="label">Uptime</div>
            <?php if (!empty($status['uptime'])): ?>
                <div class="value ok"><?php echo safeValue($status['uptime']['human']); ?></div>
            <?php else: ?>
                <div class="value warn">N/A</div>
                <div class="small">Uptime detail is available on Linux (/proc/uptime).</div>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="label">VPS from vps.json</div>
            <?php if (!empty($status['selected_vps'])): ?>
                <div class="small">Selected IP: <?php echo safeValue($status['selected_vps']['ip'] ?? ''); ?></div>
                <div class="small">User: <?php echo safeValue($status['selected_vps']['username'] ?? ''); ?></div>
                <div class="small">Team: <?php echo safeValue($status['selected_vps']['team'] ?? ''); ?></div>
                <div class="small">Info URL: <?php echo safeValue($status['selected_vps']['info'] ?? ''); ?></div>
                <div class="small">Password: <?php echo safeValue($status['selected_vps']['password_masked'] ?? ''); ?></div>
                <div class="small">KeyAPI: <?php echo safeValue($status['selected_vps']['aapanel_keyapi_masked'] ?? ''); ?></div>
            <?php else: ?>
                <div class="value warn">N/A</div>
                <div class="small">No valid VPS data found in vps.json.</div>
            <?php endif; ?>
            <div class="small">Total VPS in file: <?php echo safeValue((string) ($status['vps_count'] ?? 0)); ?></div>
            <div class="small">Choose IP: add <strong>?vps_ip=IP_ADDRESS</strong> to URL.</div>
        </div>
    </div>

    <?php if (!empty($status['vps_list'])): ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>#</th>
                    <th>IP</th>
                    <th>Username</th>
                    <th>Team</th>
                    <th>Info URL</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($status['vps_list'] as $index => $vps): ?>
                    <tr>
                        <td><?php echo safeValue((string) ($index + 1)); ?></td>
                        <td><?php echo safeValue($vps['ip'] ?? ''); ?></td>
                        <td><?php echo safeValue($vps['username'] ?? ''); ?></td>
                        <td><?php echo safeValue($vps['team'] ?? ''); ?></td>
                        <td><?php echo safeValue($vps['info'] ?? ''); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <div class="footer">
        Tip: Add this endpoint to monitoring by calling <strong>?format=json</strong> from your script.
    </div>
</div>
</body>
</html>
