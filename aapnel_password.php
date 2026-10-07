<?php
/**
 * aapnel_password.php
 * Reset password aaPanel qua SSH theo VPS da chon
 */

$autoloadFile = __DIR__ . '/vendor/autoload.php';
if (!file_exists($autoloadFile)) {
    http_response_code(500);
    echo 'Thieu vendor/autoload.php. Vui long chay composer install.';
    exit;
}

require_once $autoloadFile;
use phpseclib3\Net\SSH2;

function loadVpsList($file = 'vps.json') {
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
    foreach ($rows as $i => $row) {
        if (!is_array($row) || empty($row['ip'])) {
            continue;
        }

        $result[] = [
            'id' => $i + 1,
            'name' => !empty($row['name']) ? (string) $row['name'] : ('VPS ' . $row['ip']),
            'ip' => (string) $row['ip'],
            'username' => !empty($row['username']) ? (string) $row['username'] : 'root',
            'password' => (string) ($row['password'] ?? '')
        ];
    }

    return $result;
}

function buildResetCommand($newPassword) {
    $pw = escapeshellarg($newPassword);

    return "if [ -f /www/server/panel/tools.py ]; then cd /www/server/panel && (python3 tools.py panel {$pw} || python tools.py panel {$pw} || python3 tools.py panel admin {$pw} || python tools.py panel admin {$pw}); else echo 'tools.py not found'; exit 1; fi";
}

function commandLooksFailed($status, $output) {
    $text = strtolower(trim((string) $output));

    if ($status !== null && (int) $status !== 0) {
        return true;
    }

    if ($text === '') {
        return false;
    }

    $failHints = ['error', 'failed', 'not found', 'traceback', 'exception'];
    foreach ($failHints as $hint) {
        if (strpos($text, $hint) !== false) {
            return true;
        }
    }

    return false;
}

function tryResetCommands(SSH2 $ssh, $newPassword) {
    $pw = escapeshellarg($newPassword);
    $commands = [
        "cd /www/server/panel && python3 tools.py panel {$pw}",
        "cd /www/server/panel && python tools.py panel {$pw}",
        "cd /www/server/panel && python3 tools.py panel admin {$pw}",
        "cd /www/server/panel && python tools.py panel admin {$pw}",
        "printf '%s\\n%s\\n' {$pw} {$pw} | bt 5",
    ];

    $attemptLogs = [];
    foreach ($commands as $idx => $cmd) {
        $out = $ssh->exec($cmd . ' 2>&1');
        $status = $ssh->getExitStatus();
        $clean = trim((string) $out);

        $attemptLogs[] = [
            'step' => $idx + 1,
            'cmd' => $cmd,
            'status' => $status,
            'output' => $clean
        ];

        if (!commandLooksFailed($status, $clean)) {
            return [
                'success' => true,
                'detail' => $clean,
                'attempts' => $attemptLogs
            ];
        }
    }

    return [
        'success' => false,
        'detail' => 'Tat ca lenh fallback deu that bai',
        'attempts' => $attemptLogs
    ];
}

function resetAaPanelPasswordBySsh(array $vps, $newPassword) {
    if ($vps['password'] === '') {
        return [
            'success' => false,
            'message' => 'Thieu mat khau SSH trong vps.json'
        ];
    }

    try {
        $ssh = new SSH2($vps['ip'], 22, 15);
        if (!$ssh->login($vps['username'], $vps['password'])) {
            return [
                'success' => false,
                'message' => 'Dang nhap SSH that bai'
            ];
        }

        $resetResult = tryResetCommands($ssh, $newPassword);
        if (empty($resetResult['success'])) {
            $attempts = $resetResult['attempts'] ?? [];
            $detailLines = [];
            foreach ($attempts as $a) {
                $statusText = ($a['status'] === null) ? 'null' : (string) $a['status'];
                $outputText = $a['output'] !== '' ? $a['output'] : '(khong co output)';
                $detailLines[] = 'Step ' . $a['step'] . ' | exit=' . $statusText . ' | ' . $outputText;
            }

            return [
                'success' => false,
                'message' => 'Lenh reset that bai',
                'detail' => implode("\n", $detailLines)
            ];
        }

        $detail = (string) ($resetResult['detail'] ?? '');

        return [
            'success' => true,
            'message' => 'Cap nhat thanh cong',
            'detail' => $detail
        ];
    } catch (Throwable $e) {
        return [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (($_POST['action'] ?? '') === 'update_password')) {
    header('Content-Type: application/json; charset=UTF-8');

    try {
        $selectedIds = $_POST['vps_ids'] ?? [];
        $newPassword = (string) ($_POST['new_password'] ?? '');

        if (!is_array($selectedIds) || count($selectedIds) === 0) {
            throw new Exception('Vui long chon danh sach VPS');
        }

        if (strlen($newPassword) < 6) {
            throw new Exception('Vui long nhap mat khau moi toi thieu 6 ky tu');
        }

        $allVps = loadVpsList('vps.json');
        $map = [];
        foreach ($allVps as $vps) {
            $map[(int) $vps['id']] = $vps;
        }

        $results = [];
        foreach ($selectedIds as $idRaw) {
            $id = (int) $idRaw;
            if (!isset($map[$id])) {
                $results[] = [
                    'id' => $id,
                    'name' => 'Unknown',
                    'ip' => '',
                    'success' => false,
                    'message' => 'Khong tim thay VPS'
                ];
                continue;
            }

            $vps = $map[$id];
            $res = resetAaPanelPasswordBySsh($vps, $newPassword);
            $results[] = [
                'id' => $id,
                'name' => $vps['name'],
                'ip' => $vps['ip'],
                'success' => (bool) ($res['success'] ?? false),
                'message' => (string) ($res['message'] ?? 'Unknown'),
                'detail' => (string) ($res['detail'] ?? '')
            ];
        }

        $ok = 0;
        $fail = 0;
        foreach ($results as $r) {
            if (!empty($r['success'])) {
                $ok++;
            } else {
                $fail++;
            }
        }

        echo json_encode([
            'success' => true,
            'summary' => [
                'total' => count($results),
                'success' => $ok,
                'failed' => $fail
            ],
            'results' => $results
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

$vpsList = [];
$error = '';
try {
    $vpsList = loadVpsList('vps.json');
} catch (Throwable $e) {
    $error = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>aapnel Password Update</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .vps-list {
            max-height: 380px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            background: #fff;
            padding: 10px;
        }
        .result-box {
            min-height: 140px;
            max-height: 320px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 10px;
            background: #f8f9fa;
            white-space: pre-wrap;
        }
    </style>
</head>
<body class="bg-light">
<div class="container py-4">
    <h3 class="mb-3">Reset password aaPanel thong qua SSH</h3>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-body">
            <label class="form-label fw-bold">1. Chon danh sach VPS</label>
            <div class="mb-2">
                <input type="checkbox" id="check-all"> <label for="check-all">Chon tat ca</label>
            </div>
            <div class="vps-list" id="vps-list">
                <?php if (empty($vpsList)): ?>
                    <div class="text-muted">Khong co VPS</div>
                <?php else: ?>
                    <?php foreach ($vpsList as $vps): ?>
                        <div class="form-check">
                            <input class="form-check-input vps-item" type="checkbox" value="<?= (int) $vps['id'] ?>" id="vps-<?= (int) $vps['id'] ?>">
                            <label class="form-check-label" for="vps-<?= (int) $vps['id'] ?>">
                                <?= htmlspecialchars($vps['name']) ?> - <?= htmlspecialchars($vps['ip']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body">
            <label class="form-label fw-bold" for="new-password">2. Nhap mat khau moi</label>
            <input type="password" class="form-control" id="new-password" placeholder="Nhap mat khau moi">
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-body d-flex justify-content-between align-items-center">
            <span class="fw-bold">3. Cap nhat</span>
            <button class="btn btn-primary" id="btn-update">Nut cap nhat</button>
        </div>
    </div>

    <div class="card">
        <div class="card-body">
            <div class="small text-muted mb-2" id="summary">Chua cap nhat</div>
            <div class="result-box" id="result-box">Chua co ket qua</div>
        </div>
    </div>
</div>

<script>
const resultBox = document.getElementById('result-box');
const summary = document.getElementById('summary');

function appendResult(text, cls) {
    if (resultBox.textContent === 'Chua co ket qua') {
        resultBox.textContent = '';
    }
    const row = document.createElement('div');
    row.className = cls || '';
    row.textContent = text;
    resultBox.appendChild(row);
    resultBox.scrollTop = resultBox.scrollHeight;
}

document.getElementById('check-all').addEventListener('change', function () {
    const checked = this.checked;
    document.querySelectorAll('.vps-item').forEach(el => el.checked = checked);
});

document.getElementById('btn-update').addEventListener('click', async function () {
    const password = document.getElementById('new-password').value;
    const selected = Array.from(document.querySelectorAll('.vps-item:checked')).map(el => el.value);

    if (selected.length === 0) {
        appendResult('Vui long chon VPS', 'text-warning');
        return;
    }

    if (password.length < 6) {
        appendResult('Vui long nhap mat khau moi toi thieu 6 ky tu', 'text-warning');
        return;
    }

    this.disabled = true;
    appendResult('Dang cap nhat password tren VPS da chon...', 'text-info');

    try {
        const fd = new FormData();
        fd.append('action', 'update_password');
        fd.append('new_password', password);
        selected.forEach(id => fd.append('vps_ids[]', id));

        const res = await fetch('aapnel_password.php', { method: 'POST', body: fd });
        const data = await res.json();

        if (!data.success) {
            appendResult(data.message || 'Co loi xay ra', 'text-danger');
            return;
        }

        const s = data.summary || { total: 0, success: 0, failed: 0 };
        summary.textContent = `Tong: ${s.total} | Thanh cong: ${s.success} | That bai: ${s.failed}`;

        (data.results || []).forEach(r => {
            const icon = r.success ? '[OK]' : '[FAIL]';
            const cls = r.success ? 'text-success' : 'text-danger';
            appendResult(`${icon} ${r.name} (${r.ip}) - ${r.message}`, cls);
            if (!r.success && r.detail) {
                appendResult(`  -> ${r.detail}`, 'text-muted');
            }
        });

        if ((s.failed || 0) === 0) {
            document.getElementById('new-password').value = '';
        }
    } catch (err) {
        appendResult('Loi ket noi: ' + err.message, 'text-danger');
    } finally {
        this.disabled = false;
    }
});
</script>
</body>
</html>
