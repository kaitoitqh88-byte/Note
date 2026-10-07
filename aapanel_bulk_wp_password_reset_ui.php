        <style>
            .sidebar-menu {
                margin: 0 !important;
                padding: 0 !important;
            }
        </style>
<!DOCTYPE html>
<html lang="vi">

<head>
        <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&display=swap" rel="stylesheet">
        <style>
            :root {
                --tactical-primary: green;
                --tactical-bg: #000;
                --tactical-card: #000;
                --tactical-border: #00ff66;
                --tactical-accent: #00ff66;
                --tactical-text: #eaffea;
            }
            html, body {
                background: #000 !important;
                color: var(--tactical-text) !important;
                font-family: 'Roboto', Arial, sans-serif !important;
                min-height: 100vh;
            }
            .card, .card-header, .card-body, .form-control, .btn {
                background: #000 !important;
                color: var(--tactical-text) !important;
                border-color: var(--tactical-border) !important;
            }
            .card {
                border-radius: 12px;
                box-shadow: 0 0 24px 0 rgba(0,255,0,0.08);
                border: 1.5px solid var(--tactical-border);
            }
            .card-header {
                border-bottom: 1.5px solid var(--tactical-border);
                background: #000 !important;
            }
            .form-label, .form-control::placeholder {
                color: #00ff66 !important;
                font-weight: 500;
            }
            .btn-success, .btn-success:focus, .btn-success:active,
            .btn-primary, .btn-primary:focus, .btn-primary:active {
                background: #00ff66 !important;
                border-color: #00ff66 !important;
                color: #000 !important;
                font-weight: 700;
                box-shadow: 0 0 10px 0 #00ff66;
            }
            .btn-outline-secondary, .btn-outline-primary, .btn-outline-success {
                color: #00ff66 !important;
                border-color: #00ff66 !important;
            }
            .btn-outline-secondary:hover, .btn-outline-primary:hover, .btn-outline-success:hover {
                background: #00ff66 !important;
                color: #000 !important;
            }
            .active, .menu-link.active {
                background: var(--tactical-primary) !important;
                color: #000 !important;
            }
            pre.bg-light {
                background: #000 !important;
                color: #00ff66 !important;
                border: 1px solid var(--tactical-border) !important;
                border-radius: 8px;
            }
            /* Table styling */
            .table {
                background: #000 !important;
                color: #00ff66 !important;
            }
            .table-bordered th, .table-bordered td {
                border-color: #00ff66 !important;
                background: #000 !important;
            }
            .table {
                border-color: #00ff66 !important;
            }
            .table thead th {
                background: #000 !important;
                color: #00ff66 !important;
            }
            .table tbody td {
                color: #00ff66 !important;
            }
            /* Scrollbar green */
            ::-webkit-scrollbar {
                width: 8px;
                background: #000;
            }
            ::-webkit-scrollbar-thumb {
                background: #00ff66;
                border-radius: 4px;
            }
            /* Responsive tweaks */
            @media (max-width: 767px) {
                .container {
                    padding: 0 !important;
                }
                .card {
                    padding: 0.5rem !important;
                }
            }
            .sidebar-nav, nav.sidebar-nav {
                margin: 0 !important;
                padding: 0 !important;
                border-radius: 0 !important;
            }
        </style>
    <meta charset="UTF-8">
    <title>Bulk Reset WP Passwords qua aaPanel API</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
</head>

<body>
    <?php 
    $currentPage = 'aapanel_bulk_wp_password_reset';
    include __DIR__ . '/includes/main_navigation.php'; 
    ?>
    <?php
    // Đọc danh sách VPS từ vps.json
    $vpsList = [];
    $vpsFile = __DIR__ . '/vps.json';
    if (file_exists($vpsFile)) {
        $json = file_get_contents($vpsFile);
        $vpsList = json_decode($json, true);
        if (!is_array($vpsList)) $vpsList = [];
    }
    ?>
    <div class="container-fluid py-3" style="padding-left:280px;">
        <div class="row">
            <div class="col-md-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header bg-dark">
                        <h5 class="mb-0">Danh sách VPS (bấm để điền API)</h5>
                    </div>
                    <div class="card-body p-2">
                        <div class="table-responsive">
                            <table class="table table-bordered table-sm align-middle mb-0" id="vpsTable">
                                <thead>
                                    <tr>
                                        <th>IP</th>
                                        <th>Info</th>
                                        <th>API Key</th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php foreach ($vpsList as $vps):
                                    $apiKey = isset($vps['api_key']) ? $vps['api_key'] : (isset($vps['aapanel_keyapi']) ? $vps['aapanel_keyapi'] : '');
                                ?>
                                    <tr class="vps-row" style="cursor:pointer" data-api-url="<?php echo isset($vps['info']) && strpos($vps['info'],'http')===0 ? htmlspecialchars($vps['info']) : '' ?>" data-api-key="<?php echo htmlspecialchars($apiKey); ?>">
                                        <td><?php echo htmlspecialchars($vps['ip']); ?></td>
                                        <td><?php echo isset($vps['info']) ? htmlspecialchars($vps['info']) : ''; ?></td>
                                        <td><?php echo $apiKey ? htmlspecialchars($apiKey) : '<span class="text-muted">(chưa có)</span>'; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="small text-info mt-2">* Bấm vào dòng VPS để tự động điền API URL và API Key vào form bên phải.</div>
                    </div>
                </div>
            </div>
            <div class="col-md-6 mb-4">
                <div class="card shadow h-100">
                    <div class="card-header ">
                        <h4 class="mb-0">Reset mật khẩu WordPress hàng loạt qua aaPanel API</h4>
                    </div>
                    <div class="card-body">
                        <form id="bulkResetForm">
                            <div class="mb-3">
                                <button type="button" class="btn btn-outline-secondary w-100" id="btnGetSiteList">Lấy danh sách site (s_id)</button>
                                <div id="siteListResult" class="mt-2"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">API Key (API-Interface)</label>
                                <input type="text" value="" class="form-control" name="api_key" required placeholder="Nhập API Key từ aaPanel">
                            </div>
                            <div class="mb-3">
                                <button type="button" class="btn btn-outline-primary w-100" id="btnTestApiKey">Kiểm tra API Key</button>
                                <div id="apiKeyResult" class="mt-2"></div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">aaPanel API URL</label>
                                <input type="text" class="form-control" name="api_url" required placeholder="http://IP:8888" value="">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Danh sách domain (mỗi dòng 1 domain)</label>
                                <textarea class="form-control" name="domains" rows="6" required
                                    placeholder="site1.com\nsite2.net\nsite3.vn"></textarea>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Tài khoản WordPress (user)</label>
                                <input type="text" class="form-control" name="user" required placeholder="admin"
                                    value="admin@#224">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Mật khẩu mới</label>
                                <input type="text" class="form-control" name="pass" required
                                    placeholder="MatkhauMoi2026" value="lm%8kez32">
                            </div>
                            <button type="submit" class="btn btn-success w-100">Thực thi reset mật khẩu</button>
                        </form>
                        <div id="result" class="mt-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <script>
        // Sự kiện click vào dòng VPS để điền API URL và API Key
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.vps-row').forEach(function(row) {
                row.addEventListener('click', function() {
                    var apiUrl = this.getAttribute('data-api-url') || '';
                    var apiKey = this.getAttribute('data-api-key');
                    if(apiUrl) document.querySelector('input[name="api_url"]').value = apiUrl.replace(/\\\//g, '/');
                    if(typeof apiKey === 'string' && apiKey.length > 0) {
                        document.querySelector('input[name="api_key"]').value = apiKey;
                    }
                    // Nếu không có api_key thì giữ nguyên giá trị cũ
                });
            });
        });
                // Nút lấy danh sách site (s_id)
                document.getElementById('btnGetSiteList').onclick = async function () {
            const api_url = document.querySelector('input[name="api_url"]').value;
            const api_key = document.querySelector('input[name="api_key"]').value;
            document.getElementById('siteListResult').innerHTML = '<span class="text-info">Đang lấy danh sách site...</span>';
            try {
                const params = new URLSearchParams({
                    api_url,
                    api_key,
                    get_site_list: 1
                });
                const res = await fetch('aapanel_bulk_wp_password_reset.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params
                });
                const data = await res.json();
                if (data.site_table && data.site_table.length > 0) {
                    let html = '<table class="table table-bordered table-sm"><thead><tr><th>ID</th><th>Name (Domain)</th></tr></thead><tbody>';
                    for (const row of data.site_table) {
                        html += `<tr><td>${row.s_id}</td><td>${row.domain}</td></tr>`;
                    }
                    html += '</tbody></table>';
                    document.getElementById('siteListResult').innerHTML = html;
                } else {
                    document.getElementById('siteListResult').innerHTML = '<span class="text-danger">Không lấy được danh sách site!</span>';
                }
            } catch (err) {
                document.getElementById('siteListResult').innerHTML = '<span class="text-danger">Lỗi kết nối hoặc API Key sai!</span>';
            }
        };
        // Hàm md5 cho JS (toàn cục)
        function md5cycle(x, k) { /* ...existing code... */ }
        function md5blk(s) { /* ...existing code... */ }
        function rhex(n) { /* ...existing code... */ }
        function hex(x) { /* ...existing code... */ }
        function md5(s) { /* ...existing code... */ }

        // Nút kiểm tra API Key
        document.getElementById('btnTestApiKey').onclick = async function () {
            const api_url = document.querySelector('input[name="api_url"]').value;
            const api_key = document.querySelector('input[name="api_key"]').value;
            document.getElementById('apiKeyResult').innerHTML = '<span class="text-info">Đang kiểm tra...</span>';
            try {
                const params = new URLSearchParams({
                    api_url,
                    api_key,
                    test_api_key: 1
                });
                const res = await fetch('aapanel_bulk_wp_password_reset.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: params
                });
                const data = await res.json();
                if (data.valid) {
                    document.getElementById('apiKeyResult').innerHTML = '<span class="text-success">Đăng nhập thành công! API Key hợp lệ.</span>';
                } else {
                    document.getElementById('apiKeyResult').innerHTML = `<span class="text-danger">${data.message || 'API Key không hợp lệ hoặc không kết nối được!'}</span>`;
                }
            } catch (err) {
                document.getElementById('apiKeyResult').innerHTML = '<span class="text-danger">Lỗi kết nối hoặc API Key sai!</span>';
            }
        };

        // Xử lý submit form (API Key only)
        document.getElementById('bulkResetForm').onsubmit = async function (e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const api_url = formData.get('api_url');
            const api_key = formData.get('api_key');
            const domains_raw = formData.get('domains');
            const user = formData.get('user');
            const pass = formData.get('pass');
            if (!api_url || !api_key || !domains_raw || !user || !pass) return;
            // domains_raw là danh sách domain, cần map sang s_id
            const domains = domains_raw.split(/\r?\n/).map(line => line.trim()).filter(Boolean);
            // Lấy site_table từ lần lấy danh sách site gần nhất
            let siteTable = window._siteTableCache || [];
            // Nếu chưa có cache, thử lấy lại từ API
            if (!siteTable.length) {
                try {
                    const params = new URLSearchParams({ api_url, api_key, get_site_list: 1 });
                    const res = await fetch('aapanel_bulk_wp_password_reset.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: params
                    });
                    const data = await res.json();
                    siteTable = data.site_table || [];
                    window._siteTableCache = siteTable;
                } catch (err) {}
            }
            // Map domain -> s_id
            const domainToSid = {};
            for (const row of siteTable) {
                if (row.domain && row.s_id) domainToSid[row.domain.trim().toLowerCase()] = row.s_id;
            }
            const sids = [];
            const notFound = [];
            for (const domain of domains) {
                const sid = domainToSid[domain.toLowerCase()];
                if (sid) sids.push({ domain, s_id: sid });
                else notFound.push(domain);
            }
            if (sids.length === 0) {
                document.getElementById('result').innerHTML = '<div class="alert alert-danger">Không tìm thấy s_id cho các domain đã nhập!</div>';
                return;
            }
            document.getElementById('result').innerHTML = '<div class="alert alert-info">Đang gửi yêu cầu...</div>';
            let output = '';
            for (const {domain, s_id} of sids) {
                const params = new URLSearchParams({
                    api_url,
                    api_key,
                    s_id,
                    user,
                    pass
                });
                try {
                    const res = await fetch('aapanel_bulk_wp_password_reset.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: params
                    });
                    const text = await res.text();
                    output += `<pre class='bg-light border p-2 mb-2'><b>${domain} (s_id=${s_id})</b>:\n${text}</pre>`;
                } catch (err) {
                    output += `<div class='alert alert-danger'>${domain} (s_id=${s_id}): Lỗi kết nối!</div>`;
                }
            }
            if (notFound.length) {
                output = `<div class='alert alert-warning'>Không tìm thấy s_id cho các domain sau:<br>${notFound.join('<br>')}</div>` + output;
            }
            document.getElementById('result').innerHTML = output;
        };
    </script>
</body>

</html>