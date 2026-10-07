<?php
/**
 * resetpassword_web.php
 * Reset password admin WordPress theo danh sach domain.
 * Script tu dong tim VPS chua domain thong qua SSH (vps.json), sau do chay WP-CLI tren dung VPS.
 */

$autoloadFile = __DIR__ . '/vendor/autoload.php';
$hasSshLibrary = file_exists($autoloadFile);
if ($hasSshLibrary) {
    require_once $autoloadFile;
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/CloudflareAPI.php';

use phpseclib3\Net\SSH2;

define('WWWROOT', '/www/wwwroot');
define('RANDOM_PASS_LENGTH', 12);
define('SSH_TIMEOUT_SECONDS', 12);
define('SCRIPT_TIMEOUT_SECONDS', 900);

@set_time_limit(SCRIPT_TIMEOUT_SECONDS);
@ini_set('max_execution_time', (string) SCRIPT_TIMEOUT_SECONDS);

function h(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function generatePassword(int $length = RANDOM_PASS_LENGTH): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyz';
    $chars .= 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $chars .= '0123456789';
    $chars .= '!@#$%^&*()_+-=';
    $max = strlen($chars) - 1;
    $pass = '';

    for ($i = 0; $i < $length; $i++) {
        $pass .= $chars[random_int(0, $max)];
    }

    return $pass;
}

function parseDomains(string $input): array
{
    $lines = preg_split('/[\r\n,]+/', $input);
    $domains = [];

    foreach ($lines as $line) {
        $d = strtolower(trim($line));
        $d = preg_replace('#^https?://#', '', $d);
        $d = preg_replace('#^www\.#', '', $d);
        $d = explode('/', $d)[0];

        if ($d !== '' && filter_var('http://' . $d, FILTER_VALIDATE_URL)) {
            $domains[] = $d;
        }
    }

    return array_values(array_unique($domains));
}

function loadVpsList(string $file = 'vps.json'): array
{
    if (!file_exists($file)) {
        throw new Exception('Khong tim thay file vps.json');
    }

    $raw = file_get_contents($file);
    if ($raw === false) {
        throw new Exception('Khong the doc vps.json');
    }

    $rows = json_decode($raw, true);
    if (!is_array($rows)) {
        throw new Exception('Du lieu vps.json khong hop le');
    }

    $result = [];
    foreach ($rows as $idx => $row) {
        if (!is_array($row) || empty($row['ip'])) {
            continue;
        }

        $result[] = [
            'id' => $idx + 1,
            'ip' => (string) $row['ip'],
            'username' => !empty($row['username']) ? (string) $row['username'] : 'root',
            'password' => (string) ($row['password'] ?? ''),
            'team' => (string) ($row['team'] ?? ''),
        ];
    }

    return $result;
}

/**
 * Tao ket noi SSH moi cho moi lan xu ly de tranh loi channel bi treo.
 */
function getSshConnection(array $vps, array &$sshCache, string &$error): ?SSH2
{
    $ip = $vps['ip'];
    if (isset($sshCache[$ip])) {
        if (!$sshCache[$ip]['ok']) {
            $error = $sshCache[$ip]['error'];
            return null;
        }
    }

    if ($vps['password'] === '') {
        $error = 'Thieu password SSH';
        $sshCache[$ip] = ['ok' => false, 'error' => $error];
        return null;
    }

    try {
        $ssh = new SSH2($ip, 22, SSH_TIMEOUT_SECONDS);
        $ssh->setTimeout(30);
        if (!$ssh->login($vps['username'], $vps['password'])) {
            $error = 'Dang nhap SSH that bai';
            $sshCache[$ip] = ['ok' => false, 'error' => $error];
            return null;
        }

        $sshCache[$ip] = ['ok' => true, 'error' => ''];
        return $ssh;
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $sshCache[$ip] = ['ok' => false, 'error' => $error];
        return null;
    }
}

function sshExec(SSH2 $ssh, string $command): array
{
    $output = $ssh->exec($command . ' 2>&1');
    $status = $ssh->getExitStatus();
    return [trim((string) $output), $status];
}

function buildWpCommand(string $wpPhpCommand, string $args): string
{
    if ($wpPhpCommand !== '') {
        return $wpPhpCommand . ' $(command -v wp) ' . $args;
    }
    return 'wp ' . $args;
}

function canExecutePhpCandidate(SSH2 $ssh, string $bin): bool
{
    $binArg = escapeshellarg($bin);

    if (strpos($bin, '/') !== false) {
        [, $status] = sshExec($ssh, "if [ -x {$binArg} ]; then echo YES; else echo NO; fi");
        return $status === 0 || $status === null;
    }

    [$out, $status] = sshExec($ssh, "if command -v {$binArg} >/dev/null 2>&1; then echo YES; else echo NO; fi");
    return ($status === 0 || $status === null) && trim($out) === 'YES';
}

function discoverPhpCandidates(SSH2 $ssh): array
{
    $candidates = [
        '/www/server/php/84/bin/php',
        '/www/server/php/83/bin/php',
        '/www/server/php/82/bin/php',
        '/www/server/php/81/bin/php',
        '/www/server/php/80/bin/php',
        '/www/server/php/74/bin/php',
        '/www/server/php/73/bin/php',
        '/usr/bin/php',
        '/usr/local/bin/php',
        'php',
        'php84',
        'php83',
        'php82',
        'php81',
        'php80',
        'php74',
        'php73',
    ];

    [$scanOut, $scanStatus] = sshExec(
        $ssh,
        "for path in /www/server/php/*/bin/php /www/server/php/*/bin/php* /usr/bin/php* /usr/local/bin/php*; do if [ -x \"\$path\" ]; then printf '%s\\n' \"\$path\"; fi; done | sort -u"
    );

    if ($scanStatus === 0 || $scanStatus === null) {
        foreach (preg_split('/\r?\n/', $scanOut) as $line) {
            $line = trim($line);
            if ($line !== '') {
                $candidates[] = $line;
            }
        }
    }

    return array_values(array_unique($candidates));
}

/**
 * Tim PHP binary co mysqli de WP-CLI chay duoc tren aaPanel.
 */
function detectWpPhpBinary(SSH2 $ssh, string $cacheKey, array &$phpBinCache): string
{
    if (isset($phpBinCache[$cacheKey])) {
        return $phpBinCache[$cacheKey];
    }

    $candidates = discoverPhpCandidates($ssh);

    foreach ($candidates as $bin) {
        if (!canExecutePhpCandidate($ssh, $bin)) {
            continue;
        }

        $binArg = escapeshellarg($bin);
        [$out, $status] = sshExec($ssh, "{$binArg} -r 'echo extension_loaded(\"mysqli\")?\"YES\":\"NO\";'");
        if (($status === 0 || $status === null) && trim($out) === 'YES') {
            $phpBinCache[$cacheKey] = $bin;
            return $bin;
        }

        [$extDirOut, $extDirStatus] = sshExec($ssh, "{$binArg} -i | grep -i '^extension_dir' | head -n 1 | awk -F'=> ' '{print \$2}'");
        $extDir = trim($extDirOut);
        if (($extDirStatus === 0 || $extDirStatus === null) && $extDir !== '') {
            $mysqliSo = rtrim($extDir, '/') . '/mysqli.so';
            $mysqliSoArg = escapeshellarg($mysqliSo);
            [$soCheckOut, $soCheckStatus] = sshExec($ssh, "if [ -f {$mysqliSoArg} ]; then {$binArg} -d extension=mysqli.so -r 'echo extension_loaded(\"mysqli\")?\"YES\":\"NO\";'; else echo NO; fi");
            if (($soCheckStatus === 0 || $soCheckStatus === null) && trim($soCheckOut) === 'YES') {
                $phpBinCache[$cacheKey] = $bin . ' -d extension=mysqli.so';
                return $phpBinCache[$cacheKey];
            }
        }
    }

    $phpBinCache[$cacheKey] = '';
    return '';
}

/**
 * Lay danh sach IPv4 tu DNS records Cloudflare cua domain.
 */
function resolveDomainIpv4List(string $domain): array
{
    static $cloudflareApi = null;

    if ($cloudflareApi === null) {
        try {
            $cloudflareApi = new CloudflareAPI();
        } catch (Throwable $e) {
            return [];
        }
    }

    try {
        $zoneId = $cloudflareApi->getZoneIdByDomain($domain);
        if (!$zoneId) {
            return [];
        }

        $recordsResponse = $cloudflareApi->getDNSRecords($zoneId, ['per_page' => 100], true);
        $records = $recordsResponse['result'] ?? [];

        $domain = strtolower(trim($domain));
        $preferredIps = [];
        $allARecordIps = [];

        foreach ($records as $record) {
            if (($record['type'] ?? '') !== 'A') {
                continue;
            }

            $ip = (string) ($record['content'] ?? '');
            if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                continue;
            }

            $allARecordIps[] = $ip;

            $recordName = strtolower(trim((string) ($record['name'] ?? '')));
            if ($recordName === $domain || $recordName === 'www.' . $domain) {
                $preferredIps[] = $ip;
            }
        }

        $ips = !empty($preferredIps) ? $preferredIps : $allARecordIps;
        return array_values(array_unique($ips));
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Tim VPS theo IP trong ban ghi DNS cua domain.
 */
function findVpsByDomain(string $domain, array $vpsList, array &$sshCache, string &$error, array &$checkedIps): ?array
{
    $dnsIps = resolveDomainIpv4List($domain);
    $checkedIps = $dnsIps;

    $vpsByIp = [];
    foreach ($vpsList as $vps) {
        $ip = (string)($vps['ip'] ?? '');
        if ($ip !== '') {
            $vpsByIp[$ip] = $vps;
        }
    }

    if (count($dnsIps) === 0) {
        $error = 'Khong lay duoc ban ghi A cua domain';
        return null;
    }

    foreach ($dnsIps as $resolvedIp) {
        if (isset($vpsByIp[$resolvedIp])) {
            return $vpsByIp[$resolvedIp];
        }
    }

    $availableIps = array_keys($vpsByIp);
    $error = 'IP tu ban ghi domain khong ton tai trong vps.json | DNS: ' . implode(', ', $dnsIps) . ' | VPS: ' . implode(', ', $availableIps);

    return null;
}

function buildWordPressCandidatePaths(string $siteDir): array
{
    return [
        $siteDir,
        $siteDir . '/public_html',
        $siteDir . '/public',
        $siteDir . '/htdocs',
        $siteDir . '/web',
        $siteDir . '/wordpress',
    ];
}

/**
 * Tim dung duong dan WordPress trong domain root va cac thu muc con pho bien.
 */
function detectWordPressPath(SSH2 $ssh, string $siteDir, string $wpPhpBinary, string &$error): ?string
{
    $candidates = buildWordPressCandidatePaths($siteDir);
    $checked = [];

    foreach ($candidates as $candidate) {
        $checked[] = $candidate;
        $pathArg = escapeshellarg($candidate);

        $wpCheckCmd = buildWpCommand($wpPhpBinary, "core is-installed --path={$pathArg} --allow-root");
        [$out, $status] = sshExec($ssh, $wpCheckCmd);
        if ($status === 0 || stripos($out, 'installed') !== false) {
            return $candidate;
        }

        [$fallbackOut, $fallbackStatus] = sshExec($ssh, "if [ -f {$pathArg}/wp-load.php ] && [ -f {$pathArg}/wp-includes/version.php ]; then echo WP_FILES; else echo NOPE; fi");
        if (($fallbackStatus === 0 || $fallbackStatus === null) && stripos($fallbackOut, 'WP_FILES') !== false) {
            return $candidate;
        }
    }

    $error = 'Khong tim thay ma nguon WordPress hop le. Da thu: ' . implode(' | ', $checked);
    return null;
}

function getAdminUsername(SSH2 $ssh, string $siteDir, string &$error): ?string
{
    $wpPath = escapeshellarg($siteDir);
    $phpBinCache = [];
    $phpBin = detectWpPhpBinary($ssh, 'legacy-admin-lookup', $phpBinCache);
    $cmd = buildWpCommand($phpBin, "user list --path={$wpPath} --role=administrator --field=user_login --allow-root");
    [$out, $status] = sshExec($ssh, $cmd);

    if ($status !== 0 && $status !== null) {
        $error = 'WP-CLI loi lay admin: ' . $out;
        return null;
    }

    $lines = array_values(array_filter(array_map('trim', explode("\n", $out))));
    if (count($lines) === 0) {
        $error = 'Khong tim thay tai khoan administrator';
        return null;
    }

    return $lines[0];
}

function getAdminUsernameWithPhp(SSH2 $ssh, string $siteDir, string $wpPhpBinary, string &$error): ?string
{
    $wpPath = escapeshellarg($siteDir);
    $cmd = buildWpCommand($wpPhpBinary, "user list --path={$wpPath} --role=administrator --field=user_login --allow-root");
    [$out, $status] = sshExec($ssh, $cmd);

    if ($status !== 0 && $status !== null) {
        $error = 'WP-CLI loi lay admin: ' . $out;
        return null;
    }

    $lines = array_values(array_filter(array_map('trim', explode("\n", $out))));
    if (count($lines) === 0) {
        $error = 'Khong tim thay tai khoan administrator';
        return null;
    }

    return $lines[0];
}

function updateAdminPassword(SSH2 $ssh, string $siteDir, string $username, string $newPassword, string &$error): bool
{
    $wpPath = escapeshellarg($siteDir);
    $user = escapeshellarg($username);
    $pass = escapeshellarg($newPassword);
    $phpBinCache = [];
    $phpBin = detectWpPhpBinary($ssh, 'legacy-admin-update', $phpBinCache);
    $cmd = buildWpCommand($phpBin, "user update {$user} --user_pass={$pass} --path={$wpPath} --allow-root");
    [$out, $status] = sshExec($ssh, $cmd);

    if ($status !== 0 && $status !== null) {
        $error = 'WP-CLI loi update password: ' . $out;
        return false;
    }

    return true;
}

function updateAdminPasswordWithPhp(SSH2 $ssh, string $siteDir, string $username, string $newPassword, string $wpPhpBinary, string &$error): bool
{
    $wpPath = escapeshellarg($siteDir);
    $user = escapeshellarg($username);
    $pass = escapeshellarg($newPassword);
    $cmd = buildWpCommand($wpPhpBinary, "user update {$user} --user_pass={$pass} --path={$wpPath} --allow-root");
    [$out, $status] = sshExec($ssh, $cmd);

    if ($status !== 0 && $status !== null) {
        $error = 'WP-CLI loi update password: ' . $out;
        return false;
    }

    return true;
}

$domainInput = '';
$newPasswordInput = '';
$effectivePassword = '';
$results = [];
$successCount = 0;
$failCount = 0;
$globalError = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $domainInput = trim((string) ($_POST['domains'] ?? ''));
    $newPasswordInput = (string) ($_POST['new_password'] ?? '');

    if (!$hasSshLibrary) {
        $globalError = 'Thieu vendor/autoload.php (phpseclib). Hay chay composer install.';
    } else {
        try {
            $vpsList = loadVpsList(__DIR__ . '/vps.json');
            if (count($vpsList) === 0) {
                throw new Exception('Khong co VPS nao trong vps.json');
            }

            $domains = parseDomains($domainInput);
            if (count($domains) === 0) {
                throw new Exception('Vui long nhap it nhat 1 domain hop le');
            }

            $effectivePassword = trim($newPasswordInput);
            if ($effectivePassword === '') {
                $effectivePassword = generatePassword();
            }
            if (strlen($effectivePassword) < 6) {
                throw new Exception('Mat khau phai toi thieu 6 ky tu');
            }

            $sshCache = [];
            $phpBinCache = [];
            foreach ($domains as $index => $domain) {
                $siteDir = WWWROOT . '/' . $domain;
                $row = [
                    'no' => $index + 1,
                    'domain' => $domain,
                    'status' => 'That bai',
                    'vps_ip' => '-',
                    'username' => '-',
                    'password' => '-',
                    'note' => ''
                ];

                $checkedIps = [];
                $findErr = '';
                $foundVps = findVpsByDomain($domain, $vpsList, $sshCache, $findErr, $checkedIps);
                if ($foundVps === null) {
                    $row['note'] = $findErr . ' | Da kiem tra: ' . implode(', ', $checkedIps);
                    $results[] = $row;
                    $failCount++;
                    continue;
                }

                $row['vps_ip'] = $foundVps['ip'];
                $connErr = '';
                $ssh = getSshConnection($foundVps, $sshCache, $connErr);
                if (!$ssh) {
                    $row['note'] = 'Khong the ket noi lai SSH: ' . $connErr;
                    $results[] = $row;
                    $failCount++;
                    continue;
                }

                $wpPhpBinary = detectWpPhpBinary($ssh, $foundVps['ip'], $phpBinCache);
                if ($wpPhpBinary === '') {
                    $row['note'] = 'Khong tim duoc PHP binary co mysqli tren VPS de chay WP-CLI';
                    $results[] = $row;
                    $failCount++;
                    continue;
                }

                $wpPathError = '';
                $wpPath = detectWordPressPath($ssh, $siteDir, $wpPhpBinary, $wpPathError);
                if ($wpPath === null) {
                    $row['note'] = $wpPathError;
                    $results[] = $row;
                    $failCount++;
                    continue;
                }

                $adminErr = '';
                $adminUser = getAdminUsernameWithPhp($ssh, $wpPath, $wpPhpBinary, $adminErr);
                if ($adminUser === null) {
                    $row['note'] = $adminErr;
                    $results[] = $row;
                    $failCount++;
                    continue;
                }

                $updateErr = '';
                $ok = updateAdminPasswordWithPhp($ssh, $wpPath, $adminUser, $effectivePassword, $wpPhpBinary, $updateErr);
                if (!$ok) {
                    $row['username'] = $adminUser;
                    $row['note'] = $updateErr;
                    $results[] = $row;
                    $failCount++;
                    continue;
                }

                $row['status'] = 'Thanh cong';
                $row['username'] = $adminUser;
                $row['password'] = $effectivePassword;
                $results[] = $row;
                $successCount++;
            }
        } catch (Throwable $e) {
            $globalError = $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="vi" data-bs-theme="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password WP theo Domain + Tim VPS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        :root {
            --bg-main: #0b1220;
            --bg-surface: #121a2b;
            --bg-surface-2: #1a2438;
            --text-main: #e5e7eb;
            --text-subtle: #94a3b8;
            --border-soft: rgba(148, 163, 184, 0.25);
        }

        body {
            background: radial-gradient(circle at 20% 0%, #17243f 0%, var(--bg-main) 50%, #070b14 100%);
            color: var(--text-main);
        }

        .panel {
            border: 1px solid var(--border-soft);
            border-radius: 14px;
            box-shadow: 0 14px 40px rgba(2, 6, 23, 0.55);
            background: linear-gradient(180deg, rgba(26, 36, 56, 0.95), rgba(18, 26, 43, 0.95));
        }

        .mono { font-family: Consolas, Menlo, Monaco, monospace; font-size: .92rem; }
        .hint { font-size: .92rem; color: var(--text-subtle); }
        .main-content { padding-left: 280px; }

        .text-muted {
            color: var(--text-subtle) !important;
        }

        .form-control,
        .form-control:focus {
            background-color: var(--bg-surface);
            color: var(--text-main);
            border-color: var(--border-soft);
        }

        .form-control::placeholder {
            color: #8b93a5;
        }

        .btn-outline-secondary {
            border-color: #64748b;
            color: #cbd5e1;
        }

        .btn-outline-secondary:hover {
            background-color: #334155;
            border-color: #334155;
            color: #f8fafc;
        }

        .alert-info {
            background-color: rgba(14, 116, 144, 0.2);
            border-color: rgba(34, 211, 238, 0.35);
            color: #bae6fd;
        }

        .table {
            color: var(--text-main);
            border-color: var(--border-soft);
            --bs-table-bg: transparent;
            --bs-table-striped-bg: rgba(148, 163, 184, 0.08);
            --bs-table-hover-bg: rgba(148, 163, 184, 0.12);
        }

        .table thead {
            --bs-table-bg: var(--bg-surface-2);
        }

        @media (max-width: 991px) {
            .main-content { padding-left: 0; }
        }
    </style>
</head>
<body>
<?php
$currentPage = 'resetpassword_web';
include __DIR__ . '/includes/main_navigation.php';
?>
<div class="container py-4 py-lg-5 main-content">
    <div class="row justify-content-center">
        <div class="col-12 col-xl-11">
            <div class="card panel">
                <div class="card-body p-4 p-lg-5">
                    <h2 class="mb-2">Reset Password WordPress Theo Domain</h2>
                    <p class="text-muted mb-4">Tu dong tim VPS chua domain qua SSH, sau do reset password admin bang WP-CLI.</p>

                    <form method="post" class="row g-3">
                        <div class="col-12">
                            <label class="form-label fw-semibold">1) Danh sach domain</label>
                            <textarea name="domains" class="form-control mono" rows="6" placeholder="domain1.com, domain2.com hoac moi dong 1 domain" required><?= h($domainInput) ?></textarea>
                            <div class="hint mt-1">Ho tro dau phay hoac xuong dong. Tu dong bo http://, https://, www.</div>
                        </div>
                        <div class="col-12 col-lg-8">
                            <label class="form-label fw-semibold">2) Mat khau moi</label>
                            <input type="text" name="new_password" class="form-control mono" value="<?= h($newPasswordInput) ?>" placeholder="De trong de tu random 12 ky tu manh">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Tim VPS va Reset</button>
                            <a href="resetpassword_web.php" class="btn btn-outline-secondary">Lam moi</a>
                        </div>
                    </form>

                    <?php if ($globalError !== ''): ?>
                        <div class="alert alert-danger mt-4 mb-0"><?= h($globalError) ?></div>
                    <?php endif; ?>

                    <?php if ($_SERVER['REQUEST_METHOD'] === 'POST' && $globalError === ''): ?>
                        <hr class="my-4">
                        <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                            <h5 class="mb-0">Ket qua</h5>
                            <span class="badge text-bg-success">Thanh cong: <?= $successCount ?></span>
                            <span class="badge text-bg-danger">That bai: <?= $failCount ?></span>
                            <span class="badge text-bg-dark">Tong: <?= count($results) ?></span>
                        </div>

                        <div class="alert alert-info">
                            Mat khau ap dung cho cac domain thanh cong: <strong class="mono"><?= h($effectivePassword) ?></strong>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-bordered table-hover align-middle">
                                <thead>
                                <tr>
                                    <th style="width:60px;">STT</th>
                                    <th>Domain</th>
                                    <th>VPS IP</th>
                                    <th>Trang thai</th>
                                    <th>Username Admin</th>
                                    <th>Mat khau moi</th>
                                    <th>Ghi chu</th>
                                </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($results as $row): ?>
                                    <tr>
                                        <td><?= (int) $row['no'] ?></td>
                                        <td class="mono"><?= h($row['domain']) ?></td>
                                        <td class="mono"><?= h($row['vps_ip']) ?></td>
                                        <td>
                                            <?php if ($row['status'] === 'Thanh cong'): ?>
                                                <span class="badge text-bg-success">Thanh cong</span>
                                            <?php else: ?>
                                                <span class="badge text-bg-danger">That bai</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="mono"><?= h($row['username']) ?></td>
                                        <td class="mono"><?= h($row['password']) ?></td>
                                        <td><?= h($row['note']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
</body>
</html>
