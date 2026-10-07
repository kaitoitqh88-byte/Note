<?php
/**
 * aaPanel Bulk Website Creator
 * Tạo hàng loạt website qua API aaPanel với WordPress Admin: admin / KaitoIT@@@123zaq
 */

// ======================== AJAX BACKEND ========================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax_action'])) {
    header('Content-Type: application/json; charset=utf-8');

    $api_url = trim($_POST['api_url'] ?? '');
    $api_key  = trim($_POST['api_key'] ?? '');
    $domain   = strtolower(trim($_POST['domain'] ?? ''));

    // ------ helper: make aaPanel API request ------
    function aapanel_req(string $api_url, string $api_key, string $endpoint, array $data = []): array {
        $now  = time();
        $tok  = md5($now . md5($api_key));
        $data['request_time']  = $now;
        $data['request_token'] = $tok;

        $ch = curl_init(rtrim($api_url, '/') . $endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => http_build_query($data),
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT        => 90,
            CURLOPT_CONNECTTIMEOUT => 15,
            CURLOPT_HTTPHEADER     => [
                'x-http-token: ' . $api_key,
                'Content-Type: application/x-www-form-urlencoded',
            ],
        ]);
        $resp  = curl_exec($ch);
        $errno = curl_errno($ch);
        $emsg  = curl_error($ch);
        curl_close($ch);

        if ($errno) {
            return ['status' => false, 'msg' => 'cURL #' . $errno . ': ' . $emsg];
        }
        $decoded = json_decode($resp, true);
        if ($decoded === null) {
            return ['status' => false, 'msg' => 'Invalid JSON: ' . substr($resp, 0, 200)];
        }
        return $decoded;
    }

    // ------ validate basic inputs ------
    if (!$api_url || !$api_key) {
        echo json_encode(['success' => false, 'msg' => 'Thiếu API URL hoặc API Key']);
        exit;
    }

    $action = $_POST['ajax_action'];

    // ---- 1. Test connection ----
    if ($action === 'test_connection') {
        $r = aapanel_req($api_url, $api_key, '/system?action=GetSystemTotal');
        $ok = isset($r['memTotal']) || (isset($r['status']) && $r['status'] === true);
        echo json_encode([
            'success' => $ok,
            'msg'     => $ok ? 'Kết nối thành công!' : ($r['msg'] ?? 'Kết nối thất bại'),
        ]);
        exit;
    }

    // ---- 2. Create website ----
    if ($action === 'create_site') {
        if (!$domain) { echo json_encode(['success' => false, 'msg' => 'Thiếu domain']); exit; }

        $php_ver  = preg_replace('/[^0-9]/', '', $_POST['php_version'] ?? '74');
        $webname  = json_encode(['domain' => $domain, 'domainlist' => [], 'count' => 0]);
        $path     = '/www/wwwroot/' . $domain;

        $r = aapanel_req($api_url, $api_key, '/site?action=AddSite', [
            'webname'  => $webname,
            'path'     => $path,
            'type_id'  => '0',
            'version'  => $php_ver,
            'port'     => '80',
            'ps'       => 'Bulk create - ' . date('Y-m-d'),
            'type'     => 'PHP',
        ]);

        $ok = (isset($r['siteStatus']) && $r['siteStatus'])
            || (isset($r['status']) && $r['status'] === true)
            || (isset($r['id']) && $r['id'] > 0);

        echo json_encode(['success' => $ok, 'msg' => $r['msg'] ?? '', 'data' => $r]);
        exit;
    }

    // ---- 3. Create database ----
    if ($action === 'create_database') {
        if (!$domain) { echo json_encode(['success' => false, 'msg' => 'Thiếu domain']); exit; }

        // Build safe db name (max 16 chars)
        $base   = preg_replace('/[^a-z0-9]/', '_', str_replace('www.', '', $domain));
        $db_name = 'wp_' . substr($base, 0, 12);
        $db_user = substr('u_' . $base, 0, 16);
        $db_pass = 'P' . strtoupper(substr(md5($domain . 'kaito'), 0, 6)) . '@' . rand(100, 999);

        $r = aapanel_req($api_url, $api_key, '/database?action=AddDatabase', [
            'name'       => $db_name,
            'username'   => $db_user,
            'password'   => $db_pass,
            'coding'     => 'utf8mb4',
            'dataAccess' => '127.0.0.1',
            'ps'         => $domain,
        ]);

        $ok = (isset($r['status']) && $r['status'] === true)
            || (isset($r['id']) && $r['id'] > 0);

        echo json_encode([
            'success'  => $ok,
            'msg'      => $r['msg'] ?? '',
            'db_name'  => $db_name,
            'db_user'  => $db_user,
            'db_pass'  => $db_pass,
            'data'     => $r,
        ]);
        exit;
    }

    // ---- 4. Install WordPress via WP Toolkit ----
    if ($action === 'install_wordpress') {
        if (!$domain) { echo json_encode(['success' => false, 'msg' => 'Thiếu domain']); exit; }

        $db_name = $_POST['db_name'] ?? ('wp_' . substr(preg_replace('/[^a-z0-9]/', '_', str_replace('www.', '', $domain)), 0, 12));
        $db_user = $_POST['db_user'] ?? ('u_' . substr(preg_replace('/[^a-z0-9]/', '_', $domain), 0, 14));
        $db_pass = $_POST['db_pass'] ?? 'KaitoDBPass123';

        $wp_admin = 'admin';
        $wp_pass  = 'KaitoIT@@@123zaq';
        $wp_email = 'admin@' . $domain;
        $wp_title = ucwords(str_replace(['.', '-'], ' ', explode('.', $domain)[0]));

        // Try WP Toolkit install endpoint
        $r = aapanel_req($api_url, $api_key, '/plugin?action=a&name=wp_toolkit&s=install', [
            'domain'       => $domain,
            'site_url'     => 'https://' . $domain,
            'blog_name'    => $wp_title,
            'admin_user'   => $wp_admin,
            'admin_pass'   => $wp_pass,
            'admin_email'  => $wp_email,
            'db_name'      => $db_name,
            'db_user'      => $db_user,
            'db_pass'      => $db_pass,
            'table_prefix' => 'wp_',
            'lang'         => 'vi',
        ]);

        $ok = isset($r['status']) && $r['status'] === true;

        // Fallback: try alternate install endpoint
        if (!$ok) {
            $r2 = aapanel_req($api_url, $api_key, '/plugin?action=a&name=wp_toolkit&s=install_wordpress', [
                'domain'       => $domain,
                'admin_user'   => $wp_admin,
                'admin_pass'   => $wp_pass,
                'admin_email'  => $wp_email,
                'db_name'      => $db_name,
                'db_user'      => $db_user,
                'db_pass'      => $db_pass,
            ]);
            if (isset($r2['status']) && $r2['status'] === true) {
                $ok = true;
                $r  = $r2;
            }
        }

        echo json_encode([
            'success'    => $ok,
            'msg'        => $r['msg'] ?? '',
            'admin_url'  => 'https://' . $domain . '/wp-admin/',
            'admin_user' => $wp_admin,
            'admin_pass' => $wp_pass,
            'data'       => $r,
        ]);
        exit;
    }

    echo json_encode(['success' => false, 'msg' => 'Hành động không hợp lệ']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bulk Website Creator - aaPanel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --g: #00ff66;
            --g2: #00cc44;
            --bg: #050505;
            --card: #0a0a0a;
            --border: #00ff66;
        }
        *, body { box-sizing: border-box; }
        html, body {
            background: var(--bg) !important;
            color: #d0ffd0 !important;
            font-family: 'Consolas', 'Courier New', monospace;
            min-height: 100vh;
        }
        .main-title {
            color: var(--g);
            text-shadow: 0 0 18px #00ff6688;
            font-size: 1.7rem;
            font-weight: 700;
            letter-spacing: 1px;
        }
        .card {
            background: var(--card) !important;
            border: 1.5px solid var(--border) !important;
            border-radius: 10px;
            box-shadow: 0 0 20px #00ff6622;
            color: #d0ffd0 !important;
        }
        .card-header {
            background: #000 !important;
            border-bottom: 1.5px solid var(--g) !important;
            color: var(--g) !important;
            font-weight: 700;
            letter-spacing: 0.5px;
        }
        .form-control, .form-select {
            background: #000 !important;
            color: var(--g) !important;
            border: 1px solid var(--border) !important;
            font-family: 'Consolas', monospace;
        }
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 2px #00ff6644 !important;
            border-color: var(--g) !important;
        }
        .form-control::placeholder { color: #00994422 !important; }
        .form-label { color: var(--g) !important; font-weight: 600; font-size: 0.88rem; }
        .btn-success {
            background: var(--g) !important;
            border-color: var(--g) !important;
            color: #000 !important;
            font-weight: 700;
            box-shadow: 0 0 10px #00ff6644;
        }
        .btn-success:hover { background: #00ff99 !important; box-shadow: 0 0 18px #00ff66aa; }
        .btn-outline-success { color: var(--g) !important; border-color: var(--g) !important; }
        .btn-outline-success:hover { background: var(--g) !important; color: #000 !important; }
        .btn-outline-danger { color: #ff4444 !important; border-color: #ff4444 !important; }
        .btn-outline-warning { color: #ffcc00 !important; border-color: #ffcc00 !important; }

        /* Progress table */
        .result-table { width: 100%; border-collapse: collapse; font-size: 0.83rem; }
        .result-table th {
            background: #000 !important;
            color: var(--g) !important;
            border: 1px solid #00ff6644 !important;
            padding: 8px 10px;
            text-align: left;
        }
        .result-table td {
            border: 1px solid #00ff6622 !important;
            padding: 7px 10px;
            vertical-align: middle;
            color: #c0f0c0;
        }
        .result-table tr:hover td { background: #0d1a0d !important; }
        .result-table tr:nth-child(even) td { background: #07100780 !important; }

        .badge-wait    { background: #333 !important; color: #888 !important; }
        .badge-running { background: #00449900 !important; color: #ffcc00 !important; border: 1px solid #ffcc00; animation: pulse 1s infinite; }
        .badge-ok      { background: #00440033 !important; color: #00ff66 !important; border: 1px solid #00ff66; }
        .badge-fail    { background: #44000033 !important; color: #ff4444 !important; border: 1px solid #ff4444; }

        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }

        .progress-bar-g {
            background: var(--g) !important;
            box-shadow: 0 0 8px var(--g);
            transition: width 0.4s ease;
        }
        .progress { background: #111 !important; border: 1px solid #00ff6633; }

        pre.log-box {
            background: #030b03 !important;
            border: 1px solid #00ff6633 !important;
            color: #7fff7f !important;
            font-size: 0.78rem;
            max-height: 220px;
            overflow-y: auto;
            padding: 10px;
            border-radius: 6px;
            white-space: pre-wrap;
        }
        .stat-box { text-align: center; padding: 12px 0; }
        .stat-num { font-size: 2rem; font-weight: 700; color: var(--g); text-shadow: 0 0 10px var(--g); }
        .stat-lbl { font-size: 0.75rem; color: #668866; }

        .step-badge {
            display: inline-block;
            width: 22px; height: 22px;
            line-height: 22px; text-align: center;
            border-radius: 50%;
            font-size: 0.7rem; font-weight: 700;
            border: 1px solid #00ff6644;
            margin-right: 4px;
        }
        .copy-btn { cursor: pointer; color: var(--g); font-size: 0.78rem; }
        .copy-btn:hover { color: #fff; }
        ::-webkit-scrollbar { width: 7px; background: #000; }
        ::-webkit-scrollbar-thumb { background: #00ff6688; border-radius: 4px; }
    </style>
</head>
<body>
<div class="container-fluid py-4" style="max-width:1300px">

    <!-- Header -->
    <div class="d-flex align-items-center gap-3 mb-4">
        <i class="fa-solid fa-server" style="font-size:2rem;color:var(--g)"></i>
        <div>
            <div class="main-title">aaPanel Bulk Website Creator</div>
            <div style="color:#668866;font-size:0.82rem">Tạo hàng loạt website WordPress qua API aaPanel</div>
        </div>
        <div class="ms-auto">
            <a href="aapanel_manager.php" class="btn btn-outline-success btn-sm">
                <i class="fa fa-arrow-left me-1"></i>Quay lại
            </a>
        </div>
    </div>

    <div class="row g-3">

        <!-- LEFT: Config Form -->
        <div class="col-lg-4">
            <div class="card mb-3">
                <div class="card-header">
                    <i class="fa fa-plug me-2"></i>Cấu hình kết nối
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">aaPanel URL</label>
                        <input type="text" class="form-control" id="api_url"
                            placeholder="http://IP:8888" autocomplete="off">
                        <div class="form-text" style="color:#446644;font-size:0.75rem">VD: http://1.2.3.4:8888</div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">API Key</label>
                        <div class="input-group">
                            <input type="password" class="form-control" id="api_key"
                                placeholder="aaPanel API Key" autocomplete="off">
                            <button class="btn btn-outline-success btn-sm" type="button" onclick="toggleKey()">
                                <i class="fa fa-eye" id="eye_icon"></i>
                            </button>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phiên bản PHP</label>
                        <select class="form-select" id="php_version">
                            <option value="82">PHP 8.2</option>
                            <option value="81" selected>PHP 8.1</option>
                            <option value="80">PHP 8.0</option>
                            <option value="74">PHP 7.4</option>
                            <option value="72">PHP 7.2</option>
                        </select>
                    </div>
                    <button class="btn btn-outline-success w-100 btn-sm mb-2" onclick="testConnection()">
                        <i class="fa fa-vial me-2"></i>Test kết nối
                    </button>
                    <div id="conn_status"></div>
                </div>
            </div>

            <!-- WordPress Credentials Info -->
            <div class="card mb-3">
                <div class="card-header">
                    <i class="fab fa-wordpress me-2"></i>Thông tin WordPress
                </div>
                <div class="card-body" style="font-size:0.84rem">
                    <table class="w-100" style="color:#90d490">
                        <tr><td style="color:#668866;width:45%">Admin User:</td><td><b style="color:var(--g)">admin</b></td></tr>
                        <tr><td style="color:#668866">Admin Pass:</td>
                            <td>
                                <span id="show_pass_val" style="color:var(--g);font-weight:700">••••••••••••••</span>
                                <i class="fa fa-eye ms-2 copy-btn" onclick="revealPass()" title="Hiện mật khẩu"></i>
                                <i class="fa fa-copy ms-1 copy-btn" onclick="copyText('KaitoIT@@@123zaq')" title="Copy"></i>
                            </td>
                        </tr>
                        <tr><td style="color:#668866">Email:</td><td>admin@[domain]</td></tr>
                        <tr><td style="color:#668866">Ngôn ngữ:</td><td>Tiếng Việt</td></tr>
                    </table>
                </div>
            </div>

            <!-- Stats -->
            <div class="card">
                <div class="card-header"><i class="fa fa-chart-bar me-2"></i>Thống kê</div>
                <div class="card-body p-0">
                    <div class="row g-0 text-center">
                        <div class="col-4 stat-box" style="border-right:1px solid #00ff6622">
                            <div class="stat-num" id="stat_total">0</div>
                            <div class="stat-lbl">Tổng</div>
                        </div>
                        <div class="col-4 stat-box" style="border-right:1px solid #00ff6622">
                            <div class="stat-num" style="color:#00ff66" id="stat_ok">0</div>
                            <div class="stat-lbl">Thành công</div>
                        </div>
                        <div class="col-4 stat-box">
                            <div class="stat-num" style="color:#ff4444" id="stat_fail">0</div>
                            <div class="stat-lbl">Thất bại</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- RIGHT: Domain Input + Results -->
        <div class="col-lg-8">

            <!-- Domain input -->
            <div class="card mb-3">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fa fa-list me-2"></i>Danh sách domain</span>
                    <span id="domain_count" style="color:#668866;font-size:0.8rem">0 domain</span>
                </div>
                <div class="card-body">
                    <textarea class="form-control" id="domain_list" rows="8"
                        placeholder="Nhập mỗi domain trên một dòng&#10;Ví dụ:&#10;example.com&#10;mysite.net&#10;test.org"
                        oninput="countDomains()"></textarea>
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-success flex-fill" onclick="startBulkCreate()" id="btn_start">
                            <i class="fa fa-rocket me-2"></i>BẮT ĐẦU TẠO WEBSITE
                        </button>
                        <button class="btn btn-outline-danger" onclick="stopProcess()" id="btn_stop" disabled>
                            <i class="fa fa-stop me-1"></i>Dừng
                        </button>
                        <button class="btn btn-outline-warning" onclick="clearAll()">
                            <i class="fa fa-trash me-1"></i>Xóa
                        </button>
                    </div>
                </div>
            </div>

            <!-- Progress -->
            <div class="card mb-3" id="progress_card" style="display:none">
                <div class="card-header"><i class="fa fa-spinner fa-spin me-2"></i>Đang xử lý...</div>
                <div class="card-body">
                    <div class="d-flex justify-content-between mb-1" style="font-size:0.82rem">
                        <span id="prog_label">0 / 0 domain</span>
                        <span id="prog_pct" style="color:var(--g)">0%</span>
                    </div>
                    <div class="progress" style="height:12px">
                        <div class="progress-bar progress-bar-g" id="prog_bar" style="width:0%"></div>
                    </div>
                </div>
            </div>

            <!-- Results table -->
            <div class="card" id="results_card" style="display:none">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="fa fa-table me-2"></i>Kết quả tạo website</span>
                    <button class="btn btn-outline-success btn-sm" onclick="exportResults()">
                        <i class="fa fa-download me-1"></i>Xuất CSV
                    </button>
                </div>
                <div class="card-body p-0" style="overflow-x:auto">
                    <table class="result-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Domain</th>
                                <th><span class="step-badge">1</span>Tạo Site</th>
                                <th><span class="step-badge">2</span>Tạo DB</th>
                                <th><span class="step-badge">3</span>Cài WP</th>
                                <th>Admin URL</th>
                                <th>Chi tiết</th>
                            </tr>
                        </thead>
                        <tbody id="result_body"></tbody>
                    </table>
                </div>
            </div>

            <!-- Log box -->
            <div class="card mt-3" id="log_card" style="display:none">
                <div class="card-header d-flex justify-content-between">
                    <span><i class="fa fa-terminal me-2"></i>Log hoạt động</span>
                    <button class="btn btn-outline-success btn-sm" onclick="document.getElementById('log_content').textContent=''">
                        <i class="fa fa-eraser me-1"></i>Xóa log
                    </button>
                </div>
                <div class="card-body p-0">
                    <pre class="log-box m-0" id="log_content"></pre>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- Detail Modal -->
<div class="modal fade" id="detailModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content" style="background:#050505;border:1px solid var(--g);color:#c0f0c0">
            <div class="modal-header" style="border-bottom:1px solid #00ff6644">
                <h6 class="modal-title" style="color:var(--g)"><i class="fa fa-info-circle me-2"></i>Chi tiết: <span id="modal_domain"></span></h6>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="modal_content"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
const ADMIN_PASS = 'KaitoIT@@@123zaq';
let stopFlag  = false;
let results   = {};
let passVisible = false;

// ============ UI helpers ============
function log(msg, type='info') {
    const box = document.getElementById('log_content');
    const ts  = new Date().toLocaleTimeString('vi-VN');
    const pfx = type === 'ok' ? '✓' : type === 'err' ? '✗' : type === 'warn' ? '⚠' : '›';
    box.textContent += `[${ts}] ${pfx} ${msg}\n`;
    box.scrollTop = box.scrollHeight;
}

function countDomains() {
    const raw = document.getElementById('domain_list').value;
    const cnt = parseDomains(raw).length;
    document.getElementById('domain_count').textContent = cnt + ' domain';
}

function parseDomains(text) {
    return text.split('\n')
        .map(d => d.trim().toLowerCase()
            .replace(/^https?:\/\//,'').replace(/^www\./,'').replace(/\/.*/,''))
        .filter(d => d && /^[a-z0-9][a-z0-9\-\.]{1,251}[a-z0-9]\.[a-z]{2,}$/.test(d));
}

function toggleKey() {
    const f = document.getElementById('api_key');
    const i = document.getElementById('eye_icon');
    f.type = f.type === 'password' ? 'text' : 'password';
    i.className = f.type === 'password' ? 'fa fa-eye' : 'fa fa-eye-slash';
}

function revealPass() {
    passVisible = !passVisible;
    document.getElementById('show_pass_val').textContent = passVisible ? ADMIN_PASS : '••••••••••••••';
}

function copyText(txt) {
    navigator.clipboard.writeText(txt).then(() => showToast('Đã copy!'));
}

function showToast(msg) {
    const t = document.createElement('div');
    t.style.cssText = 'position:fixed;bottom:20px;right:20px;background:#00ff66;color:#000;padding:8px 16px;border-radius:6px;font-weight:700;z-index:9999;font-size:0.85rem';
    t.textContent = msg;
    document.body.appendChild(t);
    setTimeout(() => t.remove(), 2000);
}

function badge(state, text) {
    const cls = { wait:'badge-wait', running:'badge-running', ok:'badge-ok', fail:'badge-fail' }[state] || 'badge-wait';
    return `<span class="badge ${cls} px-2 py-1" style="font-size:0.75rem">${text}</span>`;
}

function updateStats() {
    const keys  = Object.keys(results);
    const total = keys.length;
    const ok    = keys.filter(k => results[k].wp_ok === true).length;
    const fail  = keys.filter(k => results[k].wp_ok === false).length;
    document.getElementById('stat_total').textContent = total;
    document.getElementById('stat_ok').textContent    = ok;
    document.getElementById('stat_fail').textContent  = fail;
}

function updateProgress(done, total) {
    const pct = total > 0 ? Math.round((done/total)*100) : 0;
    document.getElementById('prog_bar').style.width   = pct + '%';
    document.getElementById('prog_pct').textContent   = pct + '%';
    document.getElementById('prog_label').textContent = done + ' / ' + total + ' domain';
}

function initRow(idx, domain) {
    const tr = document.createElement('tr');
    tr.id = 'row_' + domain.replace(/\./g,'_');
    tr.innerHTML = `
        <td style="color:#446644">${idx}</td>
        <td><b style="color:var(--g)">${domain}</b></td>
        <td id="step1_${domain.replace(/\./g,'_')}">${badge('wait','Chờ')}</td>
        <td id="step2_${domain.replace(/\./g,'_')}">${badge('wait','Chờ')}</td>
        <td id="step3_${domain.replace(/\./g,'_')}">${badge('wait','Chờ')}</td>
        <td id="url_${domain.replace(/\./g,'_')}"><span style="color:#446644">—</span></td>
        <td><button class="btn btn-outline-success btn-sm py-0" onclick="showDetail('${domain}')">
            <i class="fa fa-eye"></i></button></td>`;
    document.getElementById('result_body').appendChild(tr);
}

function setStep(domain, step, state, text) {
    const key = domain.replace(/\./g,'_');
    const el  = document.getElementById('step' + step + '_' + key);
    if (el) el.innerHTML = badge(state, text);
}

function setAdminUrl(domain) {
    const key = domain.replace(/\./g,'_');
    const el  = document.getElementById('url_' + key);
    if (el) el.innerHTML = `<a href="https://${domain}/wp-admin/" target="_blank" style="color:var(--g);font-size:0.8rem">
        <i class="fa fa-external-link-alt me-1"></i>${domain}/wp-admin/</a>`;
}

// ============ API call ============
async function apiCall(action, extra = {}) {
    const fd = new FormData();
    fd.append('ajax_action', action);
    fd.append('api_url',     document.getElementById('api_url').value.trim());
    fd.append('api_key',     document.getElementById('api_key').value.trim());
    fd.append('php_version', document.getElementById('php_version').value);
    for (const [k,v] of Object.entries(extra)) fd.append(k, v);

    const res = await fetch(window.location.href, { method:'POST', body: fd });
    return res.json();
}

// ============ Test connection ============
async function testConnection() {
    const el = document.getElementById('conn_status');
    el.innerHTML = '<span style="color:#ffcc00"><i class="fa fa-spinner fa-spin me-1"></i>Đang test...</span>';
    try {
        const r = await apiCall('test_connection');
        el.innerHTML = r.success
            ? `<div class="alert p-2 mt-2" style="background:#003300;border:1px solid var(--g);color:var(--g);font-size:0.82rem"><i class="fa fa-check-circle me-1"></i>${r.msg}</div>`
            : `<div class="alert p-2 mt-2" style="background:#330000;border:1px solid #ff4444;color:#ff4444;font-size:0.82rem"><i class="fa fa-times-circle me-1"></i>${r.msg}</div>`;
    } catch(e) {
        el.innerHTML = `<div class="alert p-2 mt-2" style="background:#330000;border:1px solid #ff4444;color:#ff4444;font-size:0.82rem">Lỗi: ${e.message}</div>`;
    }
}

// ============ Main bulk create ============
async function startBulkCreate() {
    const apiUrl = document.getElementById('api_url').value.trim();
    const apiKey = document.getElementById('api_key').value.trim();
    if (!apiUrl || !apiKey) { alert('Vui lòng nhập API URL và API Key!'); return; }

    const raw     = document.getElementById('domain_list').value;
    const domains = parseDomains(raw);
    if (!domains.length) { alert('Vui lòng nhập ít nhất 1 domain hợp lệ!'); return; }

    // Reset state
    stopFlag = false;
    results  = {};
    document.getElementById('result_body').innerHTML = '';
    document.getElementById('btn_start').disabled = true;
    document.getElementById('btn_stop').disabled  = false;
    document.getElementById('progress_card').style.display = '';
    document.getElementById('results_card').style.display  = '';
    document.getElementById('log_card').style.display      = '';
    document.getElementById('log_content').textContent     = '';

    // Init rows
    domains.forEach((d, i) => initRow(i+1, d));
    updateStats();

    log(`Bắt đầu tạo ${domains.length} website...`);

    for (let i = 0; i < domains.length; i++) {
        if (stopFlag) { log('⛔ Đã dừng bởi người dùng.', 'warn'); break; }

        const domain = domains[i];
        results[domain] = { site_ok: null, db_ok: null, wp_ok: null, db_name:'', db_user:'', db_pass:'' };
        updateProgress(i, domains.length);
        log(`[${i+1}/${domains.length}] Xử lý: ${domain}`);

        // ---- STEP 1: Create site ----
        setStep(domain, 1, 'running', 'Đang tạo...');
        try {
            const r1 = await apiCall('create_site', { domain });
            if (r1.success) {
                setStep(domain, 1, 'ok', 'Thành công');
                results[domain].site_ok = true;
                log(`  ✓ Tạo site ${domain} OK`, 'ok');
            } else {
                const errMsg = r1.msg || 'Thất bại';
                // If site already exists, treat as ok to continue
                if (errMsg.toLowerCase().includes('exist') || errMsg.toLowerCase().includes('đã tồn tại') || errMsg.toLowerCase().includes('already')) {
                    setStep(domain, 1, 'ok', 'Đã tồn tại');
                    results[domain].site_ok = true;
                    log(`  ⚠ Site ${domain} đã tồn tại, tiếp tục cài WP`, 'warn');
                } else {
                    setStep(domain, 1, 'fail', 'Thất bại');
                    results[domain].site_ok = false;
                    log(`  ✗ Tạo site ${domain} thất bại: ${errMsg}`, 'err');
                }
            }
        } catch(e) {
            setStep(domain, 1, 'fail', 'Lỗi');
            results[domain].site_ok = false;
            log(`  ✗ Exception tạo site: ${e.message}`, 'err');
        }

        if (stopFlag) break;

        // ---- STEP 2: Create database ----
        setStep(domain, 2, 'running', 'Đang tạo...');
        try {
            const r2 = await apiCall('create_database', { domain });
            if (r2.success) {
                setStep(domain, 2, 'ok', 'Thành công');
                results[domain].db_ok   = true;
                results[domain].db_name = r2.db_name;
                results[domain].db_user = r2.db_user;
                results[domain].db_pass = r2.db_pass;
                log(`  ✓ Tạo DB "${r2.db_name}" OK`, 'ok');
            } else {
                const errMsg = r2.msg || 'Thất bại';
                if (errMsg.toLowerCase().includes('exist') || errMsg.toLowerCase().includes('đã tồn tại')) {
                    setStep(domain, 2, 'ok', 'Đã tồn tại');
                    results[domain].db_ok = true;
                    log(`  ⚠ DB ${domain} đã tồn tại`, 'warn');
                } else {
                    setStep(domain, 2, 'fail', 'Thất bại');
                    results[domain].db_ok = false;
                    log(`  ✗ Tạo DB thất bại: ${errMsg}`, 'err');
                }
            }
        } catch(e) {
            setStep(domain, 2, 'fail', 'Lỗi');
            results[domain].db_ok = false;
            log(`  ✗ Exception tạo DB: ${e.message}`, 'err');
        }

        if (stopFlag) break;

        // ---- STEP 3: Install WordPress ----
        setStep(domain, 3, 'running', 'Đang cài...');
        const dbInfo = results[domain];
        try {
            const r3 = await apiCall('install_wordpress', {
                domain,
                db_name: dbInfo.db_name,
                db_user: dbInfo.db_user,
                db_pass: dbInfo.db_pass,
            });
            if (r3.success) {
                setStep(domain, 3, 'ok', 'Thành công');
                results[domain].wp_ok = true;
                setAdminUrl(domain);
                log(`  ✓ Cài WordPress ${domain} OK → ${r3.admin_url}`, 'ok');
            } else {
                setStep(domain, 3, 'fail', r3.msg ? r3.msg.substring(0,20) : 'Thất bại');
                results[domain].wp_ok = false;
                log(`  ✗ Cài WP thất bại: ${r3.msg}`, 'err');
            }
        } catch(e) {
            setStep(domain, 3, 'fail', 'Lỗi');
            results[domain].wp_ok = false;
            log(`  ✗ Exception cài WP: ${e.message}`, 'err');
        }

        updateStats();
        // Small delay between domains to avoid overloading panel
        if (i < domains.length - 1) await sleep(800);
    }

    updateProgress(domains.length, domains.length);
    document.getElementById('btn_start').disabled = false;
    document.getElementById('btn_stop').disabled  = true;
    document.getElementById('progress_card').querySelector('.card-header').innerHTML =
        '<i class="fa fa-check-circle me-2" style="color:var(--g)"></i>Hoàn tất!';
    log(`Xử lý xong! Thành công: ${document.getElementById('stat_ok').textContent}, Thất bại: ${document.getElementById('stat_fail').textContent}`);
    showToast('Hoàn tất!');
}

function stopProcess() {
    stopFlag = true;
    document.getElementById('btn_stop').disabled = true;
}

function clearAll() {
    document.getElementById('domain_list').value = '';
    document.getElementById('result_body').innerHTML = '';
    document.getElementById('log_content').textContent = '';
    document.getElementById('progress_card').style.display = 'none';
    document.getElementById('results_card').style.display  = 'none';
    document.getElementById('log_card').style.display      = 'none';
    results = {};
    updateStats();
    countDomains();
}

function sleep(ms) { return new Promise(r => setTimeout(r, ms)); }

// ============ Show detail modal ============
function showDetail(domain) {
    const d = results[domain] || {};
    document.getElementById('modal_domain').textContent = domain;
    document.getElementById('modal_content').innerHTML = `
        <table class="table table-sm" style="color:#90d490;background:#000;border-color:#00ff6633">
            <tr><td style="color:#668866;width:40%">Tạo Site</td><td>${d.site_ok === true ? '✓ Thành công' : d.site_ok === false ? '✗ Thất bại' : '—'}</td></tr>
            <tr><td style="color:#668866">Tạo Database</td><td>${d.db_ok === true ? '✓ Thành công' : d.db_ok === false ? '✗ Thất bại' : '—'}</td></tr>
            <tr><td style="color:#668866">Tên DB</td><td><code style="color:var(--g)">${d.db_name || '—'}</code></td></tr>
            <tr><td style="color:#668866">User DB</td><td><code style="color:var(--g)">${d.db_user || '—'}</code></td></tr>
            <tr><td style="color:#668866">Pass DB</td><td><code style="color:var(--g)">${d.db_pass || '—'}</code>
                ${d.db_pass ? `<i class="fa fa-copy ms-2 copy-btn" onclick="copyText('${d.db_pass}')"></i>` : ''}</td></tr>
            <tr><td style="color:#668866">Cài WordPress</td><td>${d.wp_ok === true ? '✓ Thành công' : d.wp_ok === false ? '✗ Thất bại' : '—'}</td></tr>
            <tr><td style="color:#668866">Admin User</td><td><code style="color:var(--g)">admin</code></td></tr>
            <tr><td style="color:#668866">Admin Pass</td><td><code style="color:var(--g)">${ADMIN_PASS}</code>
                <i class="fa fa-copy ms-2 copy-btn" onclick="copyText('${ADMIN_PASS}')"></i></td></tr>
            <tr><td style="color:#668866">Admin URL</td><td>
                <a href="https://${domain}/wp-admin/" target="_blank" style="color:var(--g)">https://${domain}/wp-admin/</a>
            </td></tr>
        </table>`;
    new bootstrap.Modal(document.getElementById('detailModal')).show();
}

// ============ Export CSV ============
function exportResults() {
    let csv = 'Domain,Tạo Site,Tạo DB,Tên DB,User DB,Pass DB,Cài WP,Admin URL,Admin User,Admin Pass\n';
    for (const [domain, d] of Object.entries(results)) {
        csv += `${domain},${d.site_ok?'OK':'Fail'},${d.db_ok?'OK':'Fail'},${d.db_name||''},${d.db_user||''},${d.db_pass||''},${d.wp_ok?'OK':'Fail'},https://${domain}/wp-admin/,admin,${ADMIN_PASS}\n`;
    }
    const blob = new Blob(['\uFEFF' + csv], { type:'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);
    const a    = document.createElement('a');
    a.href     = url;
    a.download = 'website_created_' + new Date().toISOString().slice(0,10) + '.csv';
    a.click();
    URL.revokeObjectURL(url);
}

// Init
countDomains();
</script>
</body>
</html>
