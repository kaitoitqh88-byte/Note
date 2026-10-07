<?php
/**
 * Add Domain to Cloudflare Free Plan
 * Thêm domain vào Cloudflare với gói Free
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/CloudflareAPI.php';

$result    = null;
$error     = null;
$domains   = [];
$accountId = '';
$accounts  = []; // danh sách tất cả accounts

$api = null;
try {
    $api = new CloudflareAPI();
} catch (Throwable $e) {
    $error = 'Không thể khởi tạo CloudflareAPI: ' . htmlspecialchars($e->getMessage());
}

// Lấy tất cả Accounts
if ($api) {
    $accountsResp = $api->listAccounts();
    if (!empty($accountsResp['result']) && is_array($accountsResp['result'])) {
        foreach ($accountsResp['result'] as $acc) {
            if (!empty($acc['id'])) {
                $accounts[] = [
                    'id'   => $acc['id'],
                    'name' => $acc['name'] ?? $acc['id'],
                ];
            }
        }
    }
    // Default: account đầu tiên
    if (!empty($accounts)) {
        $accountId = $accounts[0]['id'];
    }
}

// Xử lý POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $api) {
    $rawInput         = trim($_POST['domains'] ?? '');
    $jumpStart        = isset($_POST['jump_start']);
    $setEncryptFull   = isset($_POST['set_encrypt_full']);

    // Account được chọn từ form
    $postedAccount = trim($_POST['account_id'] ?? '');
    $validIds = array_column($accounts, 'id');
    if ($postedAccount !== '' && in_array($postedAccount, $validIds, true)) {
        $accountId = $postedAccount;
    }

    // Parse danh sách domain
    $lines = preg_split('/[\r\n,]+/', $rawInput);
    foreach ($lines as $line) {
        $d = strtolower(trim($line));
        $d = preg_replace('#^https?://#', '', $d);
        $d = preg_replace('#^www\.#', '', $d);
        $d = explode('/', $d)[0];
        if ($d !== '' && preg_match('/^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)+$/', $d)) {
            $domains[] = $d;
        }
    }
    $domains = array_unique($domains);

    if (empty($domains)) {
        $error = 'Không có domain hợp lệ nào được nhập.';
    } elseif (empty($accountId)) {
        $error = 'Không lấy được Account ID từ Cloudflare. Kiểm tra lại API token.';
    } else {
        $result = [];
        foreach ($domains as $domain) {
            $resp = $api->addZone($domain, $accountId, $jumpStart);

            if (!empty($resp['result']['id'])) {
                $zone    = $resp['result'];
                $nsStr   = implode(', ', $zone['name_servers'] ?? []);
                $sslNote = '';
                if ($setEncryptFull) {
                    try {
                        $sslResp = $api->setSSLMode($zone['id'], 'full');
                        $sslNote = (!empty($sslResp['success'])) ? 'SSL: full ✓' : 'SSL: lỗi';
                    } catch (Throwable $e) {
                        $sslNote = 'SSL: ' . $e->getMessage();
                    }
                }
                $result[] = [
                    'domain'  => $domain,
                    'success' => true,
                    'zone_id' => $zone['id'],
                    'status'  => $zone['status'] ?? 'pending',
                    'ns'      => $nsStr,
                    'ssl'     => $sslNote,
                    'msg'     => 'Thêm thành công',
                ];
            } else {
                $errors        = $resp['errors'] ?? [];
                $alreadyExists = false;
                $msg           = [];
                foreach ($errors as $err) {
                    if (($err['code'] ?? 0) == 1061) {
                        $alreadyExists = true;
                    }
                    $msg[] = '[' . ($err['code'] ?? '') . '] ' . ($err['message'] ?? '');
                }
                $result[] = [
                    'domain'  => $domain,
                    'success' => false,
                    'zone_id' => '',
                    'status'  => $alreadyExists ? 'already_exists' : 'error',
                    'ns'      => '',
                    'ssl'     => '',
                    'msg'     => $alreadyExists ? 'Domain đã tồn tại trong tài khoản' : implode('; ', $msg),
                ];
            }
        }
    }
}

$successCount = $result ? count(array_filter($result, fn($r) => $r['success'])) : 0;
$failCount    = $result ? count(array_filter($result, fn($r) => !$r['success'])) : 0;

$nameserverGroups = [];
$nameserverTextOutput = '';
if (!empty($result)) {
    foreach ($result as $row) {
        if (!empty($row['success']) && !empty($row['ns'])) {
            $nsKey = trim($row['ns']);
            if (!isset($nameserverGroups[$nsKey])) {
                $nameserverGroups[$nsKey] = [];
            }
            $nameserverGroups[$nsKey][] = $row['domain'];
        }
    }

    if (!empty($nameserverGroups)) {
        ksort($nameserverGroups, SORT_NATURAL | SORT_FLAG_CASE);

        $lines = [];
        foreach ($nameserverGroups as $ns => $groupDomains) {
            sort($groupDomains, SORT_NATURAL | SORT_FLAG_CASE);
            $lines[] = 'Nameserver: ' . $ns;
            foreach ($groupDomains as $domain) {
                $lines[] = $domain;
            }
            $lines[] = '';
        }

        // Xóa dòng trống cuối để output gọn hơn khi copy/paste
        if (!empty($lines) && end($lines) === '') {
            array_pop($lines);
        }

        $nameserverTextOutput = implode("\n", $lines);
    }
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Thêm Domain vào Cloudflare Free</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        body { background: #0f172a; color: #ffffff; font-family: 'Segoe UI', sans-serif; }
        .card  { background: #1e293b; border: 1px solid #334155; border-radius: 12px; }
        .card-header { background: #0f172a; border-bottom: 1px solid #334155; border-radius: 12px 12px 0 0 !important; }
        textarea { background: #0f172a !important; color: #ffffff !important; border-color: #475569 !important; resize: vertical; }
        textarea:focus { border-color: #f6821f !important; box-shadow: 0 0 0 3px rgba(246,130,31,.2) !important; }
        .btn-cf { background: #f6821f; border: none; color: #fff; font-weight: 600; }
        .btn-cf:hover { background: #e07010; color: #fff; }
        .badge-free { background: #22c55e; font-size: .75rem; }
        .ns-box { font-family: monospace; font-size: .85rem; background: #0f172a; padding: 4px 8px; border-radius: 6px; color: #ffffff; }
        .table { color: #ffffff; }
        .table-dark { --bs-table-bg: #1e293b; --bs-table-border-color: #334155; }
        .domain-count { font-size: .8rem; color: #ffffff; }
        #domainPreview { max-height: 140px; overflow-y: auto; }
        .zone-id { font-size: .75rem; color: #ffffff; font-family: monospace; }
        .text-muted { color: #ffffff !important; }
        .form-text { color: #ffffff !important; }
        select.form-select { color: #ffffff !important; }
    </style>
</head>
<body>
<div class="container py-4">

    <?php if (file_exists(__DIR__ . '/includes/main_navigation.php')): ?>
    <?php $currentPage = 'add_domain_cf'; include __DIR__ . '/includes/main_navigation.php'; ?>
    <?php endif; ?>

    <div class="row justify-content-center">
        <div class="col-lg-9">

            <!-- Header -->
            <div class="d-flex align-items-center gap-3 mb-4">
                <div style="background:#f6821f;width:48px;height:48px;border-radius:12px;display:flex;align-items:center;justify-content:center;">
                    <i class="bi bi-cloud-plus-fill fs-4 text-white"></i>
                </div>
                <div>
                    <h4 class="mb-0 fw-bold">Thêm Domain vào Cloudflare</h4>
                    <small class="text-muted">Đăng ký domain với gói <span class="badge badge-free">Free</span></small>
                </div>
                <?php if (!empty($accounts)): ?>
                <div class="ms-auto">
                    <small class="text-muted d-block mb-1">Tài khoản CF</small>
                    <span class="badge" style="background:#1e3a5f;color:#ffffff;font-size:.8rem;">
                        <?= htmlspecialchars(count($accounts) === 1 ? $accounts[0]['name'] : count($accounts) . ' tài khoản') ?>
                    </span>
                </div>
                <?php endif; ?>
            </div>

            <!-- Error toàn cục -->
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
                    <span class="fw-semibold"><i class="bi bi-list-check me-2 text-warning"></i>Kết quả thêm domain</span>
                    <div class="d-flex gap-2">
                        <span class="badge bg-success"><?= $successCount ?> thành công</span>
                        <?php if ($failCount): ?>
                        <span class="badge bg-danger"><?= $failCount ?> thất bại</span>
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
                                    <th>Nameservers</th>
                                    <th>Encryption</th>
                                    <th>Thông báo</th>
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
                                        <?php if ($row['ns']): ?>
                                        <span class="ns-box"><?= htmlspecialchars($row['ns']) ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($row['ssl'])): ?>
                                        <span class="badge <?= str_contains($row['ssl'], '✓') ? 'bg-success' : 'bg-warning text-dark' ?>"><?= htmlspecialchars($row['ssl']) ?></span>
                                        <?php else: ?>
                                        <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($row['msg']) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <?php if ($nameserverTextOutput !== ''): ?>
            <div class="card mb-4">
                <div class="card-header py-3 d-flex align-items-center justify-content-between">
                    <span class="fw-semibold"><i class="bi bi-text-paragraph me-2 text-warning"></i>Nhóm theo nameserver (văn bản thuần)</span>
                    <button type="button" class="btn btn-sm btn-outline-light" onclick="copyNameserverText()">
                        <i class="bi bi-clipboard me-1"></i>Copy
                    </button>
                </div>
                <div class="card-body">
                    <textarea
                        id="nameserverTextOutput"
                        class="form-control"
                        rows="10"
                        readonly><?= htmlspecialchars($nameserverTextOutput) ?></textarea>
                    <small class="text-muted d-block mt-2">Mỗi nhóm bắt đầu bằng dòng Nameserver, các dòng bên dưới là domain thuộc nhóm đó.</small>
                </div>
            </div>
            <?php endif; ?>
            <?php endif; ?>

            <!-- Form nhập domain -->
            <div class="card">
                <div class="card-header py-3">
                    <span class="fw-semibold"><i class="bi bi-plus-circle me-2" style="color:#f6821f;"></i>Nhập danh sách domain</span>
                </div>
                <div class="card-body">
                    <form method="POST" id="addForm">

                        <!-- Chọn tài khoản Cloudflare -->
                        <?php if (count($accounts) > 1): ?>
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="accountSelect">
                                <i class="bi bi-person-circle me-1" style="color:#f6821f;"></i>Tài khoản Cloudflare
                            </label>
                            <select name="account_id" id="accountSelect" class="form-select" style="background:#0f172a;color:#e2e8f0;border-color:#475569;">
                                <?php foreach ($accounts as $acc): ?>
                                <option value="<?= htmlspecialchars($acc['id']) ?>"
                                    <?= (($_POST['account_id'] ?? '') === $acc['id'] || (empty($_POST['account_id']) && $acc === $accounts[0])) ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($acc['name']) ?>
                                    <span style="color:#64748b;"> — <?= htmlspecialchars($acc['id']) ?></span>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php else: ?>
                        <input type="hidden" name="account_id" value="<?= htmlspecialchars($accounts[0]['id'] ?? '') ?>">
                        <?php if (!empty($accounts[0])): ?>
                        <div class="mb-3 p-3" style="background:#0f172a;border-radius:8px;border:1px solid #334155;">
                            <small class="text-muted"><i class="bi bi-person-circle me-1"></i>Tài khoản:</small>
                            <strong class="ms-2"><?= htmlspecialchars($accounts[0]['name']) ?></strong>
                            <code class="ms-2" style="font-size:.75rem;color:#ffffff;"><?= htmlspecialchars($accounts[0]['id']) ?></code>
                        </div>
                        <?php endif; ?>
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label fw-semibold">
                                Danh sách domain
                                <span class="domain-count ms-2" id="domainCount"></span>
                            </label>
                            <textarea
                                name="domains"
                                id="domainsInput"
                                class="form-control"
                                rows="8"
                                placeholder="Mỗi domain một dòng, hoặc phân cách bằng dấu phẩy:&#10;example.com&#10;mysite.net&#10;another-domain.org"><?= htmlspecialchars($_POST['domains'] ?? '') ?></textarea>
                            <div class="form-text">Hỗ trợ mọi định dạng: có/không có http://, www., đường dẫn — sẽ tự động làm sạch.</div>
                        </div>

                        <!-- Preview domain hợp lệ -->
                        <div id="domainPreview" class="mb-3 d-none">
                            <div class="form-label fw-semibold text-success"><i class="bi bi-check2-all me-1"></i>Domain hợp lệ:</div>
                            <div id="previewList" class="d-flex flex-wrap gap-1"></div>
                        </div>

                        <div class="mb-3">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="jump_start" id="jumpStart" checked>
                                <label class="form-check-label" for="jumpStart">
                                    <strong>Jump Start</strong>
                                    <span class="text-muted ms-1">— Tự động quét và thêm DNS records hiện có</span>
                                </label>
                            </div>
                        </div>
                        <div class="mb-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" name="set_encrypt_full" id="setEncryptFull" <?= isset($_POST['set_encrypt_full']) ? 'checked' : 'checked' ?>>
                                <label class="form-check-label" for="setEncryptFull">
                                    <strong>Encryption Mode: Full</strong>
                                    <span class="text-muted ms-1">— Tự động cấu hình SSL/TLS encryption mode thành <span class="badge bg-success">full</span> sau khi thêm</span>
                                </label>
                            </div>
                        </div>

                        <div class="d-flex gap-2 align-items-center">
                            <button type="submit" class="btn btn-cf px-4" id="submitBtn">
                                <i class="bi bi-cloud-upload me-2"></i>Thêm vào Cloudflare Free
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="document.getElementById('domainsInput').value='';updatePreview();">
                                <i class="bi bi-trash me-1"></i>Xóa
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Hướng dẫn -->
            <div class="card mt-4">
                <div class="card-header py-2">
                    <small class="fw-semibold text-muted"><i class="bi bi-info-circle me-1"></i>Sau khi thêm domain</small>
                </div>
                <div class="card-body py-3">
                    <ol class="mb-0 text-muted small">
                        <li>Copy nameservers hiển thị ở bảng kết quả</li>
                        <li>Đăng nhập vào nhà đăng ký tên miền (Namecheap, GoDaddy, BKNS…)</li>
                        <li>Thay đổi Nameservers thành nameservers của Cloudflare</li>
                        <li>Chờ 24-48h để DNS propagate — trạng thái sẽ chuyển từ <span class="badge bg-warning text-dark">pending</span> sang <span class="badge bg-success">active</span></li>
                    </ol>
                </div>
            </div>

        </div>
    </div>
</div>

<script>
function parseDomains(raw) {
    const lines = raw.split(/[\r\n,]+/);
    const valid = [];
    const re = /^[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?(\.[a-z0-9]([a-z0-9\-]{0,61}[a-z0-9])?)+$/;
    const seen = new Set();
    for (let line of lines) {
        let d = line.trim().toLowerCase();
        d = d.replace(/^https?:\/\//, '').replace(/^www\./, '').split('/')[0];
        if (d && re.test(d) && !seen.has(d)) {
            seen.add(d);
            valid.push(d);
        }
    }
    return valid;
}

function updatePreview() {
    const raw = document.getElementById('domainsInput').value;
    const domains = parseDomains(raw);
    const countEl = document.getElementById('domainCount');
    const previewDiv = document.getElementById('domainPreview');
    const previewList = document.getElementById('previewList');

    if (domains.length === 0) {
        countEl.textContent = '';
        previewDiv.classList.add('d-none');
        return;
    }

    countEl.textContent = '(' + domains.length + ' domain hợp lệ)';
    previewList.innerHTML = domains.map(d =>
        `<span class="badge" style="background:#1e3a5f;color:#ffffff;font-size:.8rem;">${d}</span>`
    ).join('');
    previewDiv.classList.remove('d-none');
}

function copyNameserverText() {
    const output = document.getElementById('nameserverTextOutput');
    if (!output) return;

    output.select();
    output.setSelectionRange(0, 999999);
    document.execCommand('copy');
}

document.getElementById('domainsInput').addEventListener('input', updatePreview);
document.getElementById('addForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Đang xử lý...';
});

// Khởi tạo preview nếu có giá trị sẵn
updatePreview();
</script>
</body>
</html>
