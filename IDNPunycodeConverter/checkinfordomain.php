<?php
// checkinfordomain.php - Lấy thông tin Account cho danh sách domain từ API


require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../CloudflareAPI.php';

function clean_domain($domain) {
    // Sử dụng logic giống domain_status_checker.php
    $domain = trim($domain);
    $domain = preg_replace('/^[a-zA-Z]+:\/\//', '', $domain);
    $domain = preg_replace('/^www\./', '', $domain);
    $domain = preg_replace('/\/.*$/', '', $domain);
    $domain = preg_replace('/:.*$/', '', $domain);
    $domain = strtolower(trim($domain));
    if (!preg_match('/^[a-z0-9.-]+\.[a-z]{2,}$/i', $domain)) {
        return '';
    }
    $domain = trim($domain, '.');
    return $domain;
}

function get_account_info_cloudflare($domain, $cloudflareAPI) {
    try {
        $zones = $cloudflareAPI->searchZones($domain, 1, 1);
        if (!empty($zones['result']) && count($zones['result']) > 0) {
            foreach ($zones['result'] as $zone) {
                if (strtolower($zone['name']) === strtolower($domain)) {
                    return $zone;
                }
            }
            // Nếu không khớp tuyệt đối, trả về zone đầu tiên
            return $zones['result'][0];
        }
        return ['error' => 'Domain không tồn tại trong Cloudflare'];
    } catch (Exception $e) {
        return ['error' => $e->getMessage()];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['domains'])) {
    header('Content-Type: application/json');
    $domains = preg_split('/[\r\n,;]+/', $_POST['domains']);
    $results = [];
    try {
        $cloudflareAPI = new CloudflareAPI();
    } catch (Exception $e) {
        echo json_encode(['error' => 'Cloudflare API config error: ' . $e->getMessage()]);
        exit;
    }
    foreach ($domains as $domain) {
        $original = trim($domain);
        $cleaned = clean_domain($original);
        if (!$cleaned) continue;
        $info = get_account_info_cloudflare($cleaned, $cloudflareAPI);
        $results[] = [
            'domain' => $original,
            'cleaned_domain' => $cleaned,
            'account_info' => $info
        ];
    }
    echo json_encode($results, JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <title>Check Account Info for Domains</title>
    <style>
        body { background: #101c10; color: #00ff88; font-family: 'Segoe UI', Arial, sans-serif; }
        .container { max-width: 700px; margin: 40px auto; background: #181f18; border-radius: 1.2rem; padding: 32px; box-shadow: 0 0 24px #00ff8822; }
        textarea { width: 100%; min-height: 120px; border-radius: 0.7rem; border: 2px solid #00ff88; background: #101c10; color: #00ff88; padding: 12px; font-size: 1.1em; }
        button { background: transparent; color: #00ff88; border: 2px solid #00ff88; border-radius: 0.7rem; padding: 8px 24px; font-size: 1.1em; margin-top: 12px; cursor: pointer; }
        button:hover { background: #00ff88; color: #101c10; }
        table { width: 100%; border-collapse: collapse; margin-top: 24px; }
        th, td { border: 1.5px solid #00ff88; padding: 8px 10px; text-align: left; }
        th { background: #101c10; color: #00ff88; }
        tr:nth-child(even) { background: #181f18; }
        .error { color: #ff4c4c; }
    </style>
</head>
<body>
<div class="container">
    <h2>Check Account Info for Domains</h2>
    <form id="domainForm">
        <label>Nhập danh sách domain (mỗi dòng 1 domain):</label><br>
        <textarea name="domains" id="domains" required></textarea><br>
        <button type="submit">Lấy thông tin Account</button>
    </form>
    <div id="result"></div>
</div>
<script>
document.getElementById('domainForm').addEventListener('submit', function(e) {
    e.preventDefault();
    const domains = document.getElementById('domains').value.split(/\r?\n|,|;/).map(d => d.trim()).filter(Boolean);
    const resultDiv = document.getElementById('result');
    let html = '<table id="resultTable"><tr><th>Domain</th><th>Account Name</th></tr>';
    domains.forEach(domain => {
        html += `<tr id="row-${domain.replace(/[^a-zA-Z0-9]/g,'_')}"><td>${domain}</td><td>Đang xử lý...</td></tr>`;
    });
    html += '</table>';
    resultDiv.innerHTML = html;

    domains.forEach(domain => {
        const rowId = `row-${domain.replace(/[^a-zA-Z0-9]/g,'_')}`;
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'domains=' + encodeURIComponent(domain)
        })
        .then(r => r.json())
        .then(data => {
            let accName = '';
            let rawJson = '';
            let rowData = Array.isArray(data) && data.length > 0 ? data[0] : null;
            if (rowData && rowData.account_info && !rowData.account_info.error) {
                if (rowData.account_info.account && rowData.account_info.account.name) {
                    accName = rowData.account_info.account.name;
                } else {
                    accName = '<span class="error">Không có account.name</span>';
                }
                // rawJson = '<pre style="font-size:0.95em;background:#181f18;color:#00ff88;max-width:420px;overflow:auto;">' + JSON.stringify(rowData.account_info, null, 2) + '</pre>';
            } else {
                accName = '<span class="error">' + (rowData && rowData.account_info && rowData.account_info.error ? rowData.account_info.error : 'Lỗi') + '</span>';
                if(rowData && rowData.account_info) rawJson = '<pre style="font-size:0.95em;background:#181f18;color:#ff4c4c;max-width:420px;overflow:auto;">' + JSON.stringify(rowData.account_info, null, 2) + '</pre>';
            }
            document.querySelector(`#${rowId} td:nth-child(2)`).innerHTML = accName + rawJson;
        })
        .catch(err => {
            document.querySelector(`#${rowId} td:nth-child(2)`).innerHTML = '<span class="error">Lỗi AJAX: ' + err + '</span>';
        });
    });
});
</script>
</body>
</html>
