<?php
// SecurityLevel.php
// Giao diện và xử lý cập nhật Security Level cho danh sách domain

require_once __DIR__ . '/CloudflareAPI.php';

// AJAX endpoint: cập nhật từng domain một lần gọi
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_single_domain') {
    header('Content-Type: application/json; charset=utf-8');

    $rawDomain = trim(strtolower($_POST['domain'] ?? ''));
    $domain = normalizeToZoneCandidate($rawDomain);
    $underAttack = $_POST['under_attack_mode'] ?? '';

    if ($domain === '') {
        echo json_encode([
            'success' => false,
            'domain' => '',
            'error' => 'Domain is required'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($underAttack !== 'on' && $underAttack !== 'off') {
        echo json_encode([
            'success' => false,
            'domain' => $rawDomain,
            'error' => 'Invalid under_attack_mode'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    try {
        $cf = new CloudflareAPI();
        $result = updateSingleDomainUnderAttack($cf, $domain, $underAttack);
        echo json_encode($result, JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'domain' => $rawDomain,
            'error' => $e->getMessage()
        ], JSON_UNESCAPED_UNICODE);
    }
    exit;
}

/**
 * Chuyển input domain về dạng candidate zone:
 * - bỏ http/https
 * - bỏ path/query/fragment/port
 * - bỏ www.
 */
function normalizeToZoneCandidate($input) {
    $value = trim(strtolower($input));
    if ($value === '') {
        return '';
    }

    if (!preg_match('#^[a-z][a-z0-9+.-]*://#i', $value)) {
        $value = 'http://' . $value;
    }

    $host = parse_url($value, PHP_URL_HOST);
    if (!$host) {
        $host = strtolower(trim($input));
        $host = preg_replace('#^[a-z][a-z0-9+.-]*://#i', '', $host);
        $host = preg_replace('#[/?:#].*$#', '', $host);
    }

    $host = strtolower(trim($host, " .\t\n\r\0\x0B"));
    $host = preg_replace('/:\\d+$/', '', $host);
    $host = preg_replace('/^www\./', '', $host);

    return $host;
}

/**
 * Cập nhật Under Attack mode cho 1 domain
 * @param CloudflareAPI $cf
 * @param string $domain
 * @param string $underAttack on|off
 * @return array
 */
function updateSingleDomainUnderAttack($cf, $domain, $underAttack) {
    // Try to fetch as many zones as possible to avoid missing zones in large accounts.
    $zonesResp = null;
    if (method_exists($cf, 'getAllZonesPaginated')) {
        $zonesResp = $cf->getAllZonesPaginated(5000, true, false);
    }
    if (!$zonesResp || !isset($zonesResp['result']) || !is_array($zonesResp['result'])) {
        $zonesResp = $cf->getZones(100, true);
    }
    $zoneMap = [];

    if (isset($zonesResp['result']) && is_array($zonesResp['result'])) {
        foreach ($zonesResp['result'] as $zone) {
            $zoneMap[strtolower($zone['name'])] = $zone['id'];
        }
    }

    $zoneName = null;
    $zoneId = $zoneMap[$domain] ?? null;
    if ($zoneId) {
        $zoneName = $domain;
    }

    // Nếu nhập subdomain, tự map về zone theo hậu tố dài nhất
    if (!$zoneId) {
        $bestMatch = null;
        foreach ($zoneMap as $candidateZone => $candidateId) {
            if ($domain === $candidateZone || str_ends_with($domain, '.' . $candidateZone)) {
                if ($bestMatch === null || strlen($candidateZone) > strlen($bestMatch)) {
                    $bestMatch = $candidateZone;
                    $zoneId = $candidateId;
                    $zoneName = $candidateZone;
                }
            }
        }
    }

    if (!$zoneId && method_exists($cf, 'searchZones')) {
        // Fallback: direct search by domain candidate
        try {
            $searchResp = $cf->searchZones($domain, 1, 50, null, null, true);
            if (isset($searchResp['result']) && is_array($searchResp['result'])) {
                foreach ($searchResp['result'] as $zone) {
                    $candidateName = strtolower($zone['name'] ?? '');
                    if ($candidateName !== '' && ($domain === $candidateName || str_ends_with($domain, '.' . $candidateName))) {
                        $zoneId = $zone['id'] ?? null;
                        $zoneName = $candidateName;
                        break;
                    }
                }
            }
        } catch (Exception $e) {
            // Ignore fallback search exception and keep final Zone not found response.
        }
    }

    if (!$zoneId) {
        return [
            'success' => false,
            'domain' => $domain,
            'error' => 'Zone not found'
        ];
    }

    $securityLevel = $underAttack === 'on' ? 'under_attack' : 'medium';
    $resp = $cf->setSecurityLevel($zoneId, $securityLevel);

    return [
        'success' => (bool)($resp['success'] ?? false),
        'domain' => $domain,
        'zone_name' => $zoneName,
        'zone_id' => $zoneId,
        'security_level' => $securityLevel,
        'result' => $resp['result'] ?? null,
        'errors' => $resp['errors'] ?? null
    ];
}

$currentPage = 'SecurityLevel';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Cập nhật Security Level Cloudflare</title>
    <style>
        body {
            font-family: 'Roboto Mono', Consolas, Monaco, monospace;
            background: #050805;
            color: #00ff88;
            margin: 0;
        }
        .main-content {
            margin-left: 300px;
            padding: 24px;
            min-height: 100vh;
            background:
                radial-gradient(circle at 15% 20%, rgba(0, 255, 136, 0.08), transparent 40%),
                radial-gradient(circle at 85% 80%, rgba(0, 255, 136, 0.06), transparent 35%),
                #050805;
        }
        .container {
            max-width: 900px;
            margin: 16px auto;
            background: #071107;
            border: 1px solid #00ff66;
            border-radius: 10px;
            box-shadow: 0 0 24px rgba(0, 255, 102, 0.2);
            padding: 32px;
        }
        h2, h3, label {
            color: #00ff88;
            text-shadow: 0 0 8px rgba(0, 255, 136, 0.45);
        }
        textarea,
        select,
        button {
            padding: 10px 12px;
            margin-top: 8px;
            border-radius: 6px;
            border: 1px solid #00b359;
            background: #020602;
            color: #7dffbe;
            outline: none;
        }
        textarea {
            width: 100%;
            min-height: 120px;
        }
        textarea:focus,
        select:focus {
            border-color: #00ff88;
            box-shadow: 0 0 10px rgba(0, 255, 136, 0.35);
        }
        button {
            background: linear-gradient(180deg, #0a2b13 0%, #071a0d 100%);
            color: #00ff88;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
        }
        button:hover {
            border-color: #00ff88;
            box-shadow: 0 0 14px rgba(0, 255, 136, 0.4);
            transform: translateY(-1px);
        }
        .result-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 24px;
            background: #040a04;
        }
        .result-table th,
        .result-table td {
            border: 1px solid #0a5f34;
            padding: 8px 10px;
            color: #8cffc8;
        }
        .result-table th {
            background: #0a1d10;
            color: #00ff88;
        }
        .success { color: #00ff88; font-weight: 700; }
        .fail { color: #ff5f5f; font-weight: 700; }
        .error-msg {
            color: #ff7a7a;
            margin-bottom: 12px;
            border: 1px solid #b33636;
            background: #1a0808;
            padding: 8px 10px;
            border-radius: 6px;
        }
        @media (max-width: 991px) {
            .main-content {
                margin-left: 0;
                padding: 12px;
            }
            .container {
                padding: 16px;
            }
        }
    </style>
</head>
<body>
<?php include __DIR__ . '/includes/main_navigation.php'; ?>
<div class="main-content" id="mainWrapper">
<div class="container">
    <h2>Cập nhật Security Level cho nhiều domain</h2>
    <form id="security-form" method="post">
        <label>Nhập danh sách domain (mỗi dòng 1 domain):</label><br>
        <textarea id="domains" name="domains" required><?= htmlspecialchars($_POST['domains'] ?? '') ?></textarea><br>
        <label>Bật chế độ "I'm Under Attack" cho tất cả domain?</label><br>
        <select id="under_attack_mode" name="under_attack_mode" required>
            <option value="">-- Chọn --</option>
            <option value="on" <?= (($_POST['under_attack_mode'] ?? '')==='on')?'selected':'' ?>>Bật</option>
            <option value="off" <?= (($_POST['under_attack_mode'] ?? '')==='off')?'selected':'' ?>>Tắt</option>
        </select><br><br>
        <button id="submit-btn" type="submit">Cập nhật từng domain (AJAX)</button>
    </form>
    <div id="error-box" class="error-msg" style="display:none;"></div>
    <h3>Kết quả cập nhật:</h3>
    <table class="result-table" id="result-table">
        <thead>
            <tr><th>Domain</th><th>Zone ID</th><th>Kết quả</th><th>Chi tiết</th></tr>
        </thead>
        <tbody></tbody>
    </table>
</div>
</div>
<script>
const form = document.getElementById('security-form');
const domainsInput = document.getElementById('domains');
const modeInput = document.getElementById('under_attack_mode');
const submitBtn = document.getElementById('submit-btn');
const errorBox = document.getElementById('error-box');
const resultTableBody = document.querySelector('#result-table tbody');

function parseDomains(rawText) {
    const seen = new Set();

    function normalizeDomainInput(value) {
        let domain = value.trim().toLowerCase();
        domain = domain.replace(/^[a-z][a-z0-9+.-]*:\/\//i, '');
        domain = domain.replace(/^www\./, '');
        domain = domain.replace(/[/?#].*$/, '');
        domain = domain.replace(/:\d+$/, '');
        domain = domain.replace(/^\.+|\.+$/g, '');
        return domain;
    }

    return rawText
        .split(/\r?\n|,/)
        .map(item => normalizeDomainInput(item))
        .filter(item => item.length > 0)
        .filter(item => {
            if (seen.has(item)) return false;
            seen.add(item);
            return true;
        });
}

function appendResultRow(data) {
    const tr = document.createElement('tr');
    const success = !!data.success;
    const detail = data.error
        ? data.error
        : (data.errors ? JSON.stringify(data.errors) : (data.result ? JSON.stringify(data.result) : '-'));

    tr.innerHTML = `
        <td>${(data.domain || '').replace(/</g, '&lt;')}</td>
        <td>${((data.zone_id || '-') + '').replace(/</g, '&lt;')}</td>
        <td class="${success ? 'success' : 'fail'}">${success ? 'Thành công' : 'Thất bại'}</td>
        <td>${(detail + '').replace(/</g, '&lt;')}</td>
    `;
    resultTableBody.appendChild(tr);
}

async function updateSingleDomain(domain, mode) {
    const formData = new FormData();
    formData.append('action', 'update_single_domain');
    formData.append('domain', domain);
    formData.append('under_attack_mode', mode);

    const response = await fetch(window.location.href, {
        method: 'POST',
        body: formData
    });

    return response.json();
}

form.addEventListener('submit', async (event) => {
    event.preventDefault();
    errorBox.style.display = 'none';
    errorBox.textContent = '';
    resultTableBody.innerHTML = '';

    const domains = parseDomains(domainsInput.value);
    const mode = modeInput.value;

    if (domains.length === 0) {
        errorBox.textContent = 'Vui lòng nhập ít nhất 1 domain.';
        errorBox.style.display = 'block';
        return;
    }

    if (mode !== 'on' && mode !== 'off') {
        errorBox.textContent = 'Vui lòng chọn bật/tắt chế độ Under Attack.';
        errorBox.style.display = 'block';
        return;
    }

    submitBtn.disabled = true;
    submitBtn.textContent = 'Đang cập nhật...';

    try {
        for (const domain of domains) {
            try {
                const data = await updateSingleDomain(domain, mode);
                appendResultRow(data);
            } catch (err) {
                appendResultRow({
                    success: false,
                    domain,
                    error: 'AJAX error: ' + err.message
                });
            }
            await new Promise(resolve => setTimeout(resolve, 250));
        }
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = 'Cập nhật từng domain (AJAX)';
    }
});
</script>
</body>
</html>
