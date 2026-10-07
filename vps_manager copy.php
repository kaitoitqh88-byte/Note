<?php
// vps_manager.php - Quản lý VPS (CRUD + Check Login)
$jsonFile = __DIR__ . '/vps.json'; 

function getVpsList($jsonFile) {
    if (!file_exists($jsonFile)) return [];
    $data = file_get_contents($jsonFile);
    $arr = json_decode($data, true);
    return is_array($arr) ? $arr : [];
}
function saveVpsList($jsonFile, $list) {
    file_put_contents($jsonFile, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$vpsList = getVpsList($jsonFile);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $new = [
            'ip' => $_POST['ip'],
            'username' => $_POST['username'],
            'password' => $_POST['password'],
            'info' => $_POST['info']
        ];
        $vpsList[] = $new;
        saveVpsList($jsonFile, $vpsList);
        header('Location: vps_manager.php'); exit;
    }
    if (isset($_POST['quick_add']) && isset($_POST['quick_vps_list'])) {
        $lines = explode("\n", trim($_POST['quick_vps_list']));
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;
            $parts = explode('|', $line);
            if (count($parts) < 3) continue;
            $ip = trim($parts[0]);
            $username = trim($parts[1]);
            $password = trim($parts[2]);
            $info = isset($parts[3]) ? trim($parts[3]) : '';
            $vpsList[] = [
                'ip' => $ip,
                'username' => $username,
                'password' => $password,
                'info' => $info
            ];
        }
        saveVpsList($jsonFile, $vpsList);
        header('Location: vps_manager.php'); exit;
    }
    if (isset($_POST['edit_id'])) {
        $id = (int)$_POST['edit_id'];
        $vpsList[$id] = [
            'ip' => $_POST['ip'],
            'username' => $_POST['username'],
            'password' => $_POST['password'],
            'info' => $_POST['info']
        ];
        saveVpsList($jsonFile, $vpsList);
        header('Location: vps_manager.php'); exit;
    }
    if (isset($_POST['delete_id'])) {
        $id = (int)$_POST['delete_id'];
        array_splice($vpsList, $id, 1);
        saveVpsList($jsonFile, $vpsList);
        header('Location: vps_manager.php'); exit;
    }
}
$currentPage = 'vps_manager';
include __DIR__ . '/includes/main_navigation.php';
// Xử lý check VPS trực tiếp
$checkResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_vps_id'])) {
    $id = (int)$_POST['check_vps_id'];
    if (isset($vpsList[$id])) {
        $vps = $vpsList[$id];
        $checkResult = checkVpsLogin($vps['ip'], $vps['username'], $vps['password']);
        $checkResult['id'] = $id;
    }
}
// Xử lý check VPS trực tiếp
$checkResult = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['check_vps_id'])) {
    $id = (int)$_POST['check_vps_id'];
    if (isset($vpsList[$id])) {
        $vps = $vpsList[$id];
        $checkResult = checkVpsLogin($vps['ip'], $vps['username'], $vps['password']);
        $checkResult['id'] = $id;
    }
}
?>
<div id="mainWrapper">
<div style="margin-bottom:14px; text-align:right;">
    <button type="button" id="openAddModal" class="matrix-btn">➕ Thêm VPS mới</button>
    <button type="button" id="openQuickAddModal" class="matrix-btn" style="margin-left:8px;">⚡ Thêm Nhanh</button>
    <button type="button" id="checkAllLoginBtn" class="matrix-btn" style="margin-left:8px;background:#222;color:#fff;">🔎 Check All Login</button>
</div>
<div style="margin-bottom:18px;">
    <input type="text" id="vpsSearchInput" class="matrix-input" placeholder="Tìm VPS theo IP, username, info..." style="max-width:300px;">
</div>
<h2>Danh sách VPS</h2>
<table class="vps-table">
    <tr>
        <th>IP</th><th>Username</th><th>Password</th><th>Info</th><th>Hành động</th>
    </tr>
    <?php foreach ($vpsList as $i => $vps): ?>
        <?php if (isset($_GET['edit']) && $_GET['edit'] == $i): ?>
        <tr>
            <form method="post">
                <td><input name="ip" value="<?=htmlspecialchars($vps['ip'])?>"></td>
                <td><input name="username" value="<?=htmlspecialchars($vps['username'])?>"></td>
                <td><input name="password" value="<?=htmlspecialchars($vps['password'])?>"></td>
                <td><input name="info" value="<?=htmlspecialchars($vps['info'])?>"></td>
                <td>
                    <input type="hidden" name="edit_id" value="<?=$i?>">
                    <button type="submit">Lưu</button>
                    <a href="vps_manager.php">Hủy</a>
                </td>
            </form>
        </tr>
        <?php else: ?>
        <tr>
            <td><?=htmlspecialchars($vps['ip'])?></td>
            <td><?=htmlspecialchars($vps['username'])?></td>
            <td>
                <span class="vps-password" id="vps-password-<?=$i?>"><?=htmlspecialchars($vps['password'])?></span>
                <button type="button" class="copy-btn matrix-btn btn-sm" data-password-id="vps-password-<?=$i?>" title="Copy password" style="margin-left:6px;">📋</button>
            </td>
            <td>
                <?php
                $info = $vps['info'];
                if (preg_match('/^https?:\/\//i', $info)) {
                    echo '<a href="' . htmlspecialchars($info) . '" target="_blank" rel="noopener noreferrer">' . htmlspecialchars($info) . '</a>';
                } else {
                    echo htmlspecialchars($info);
                }
                ?>
            </td>
            <td>
                <button type="button" class="edit-btn" data-index="<?=$i?>">Sửa</button>
                <form method="post" style="display:inline">
                    <input type="hidden" name="delete_id" value="<?=$i?>">
                    <button type="submit" onclick="return confirm('Xóa VPS này?')">Xóa</button>
                </form>
                <button type="button" class="matrix-btn btn-sm check-login-btn" data-index="<?=$i?>">Check Login</button>
                <a href="ssh://<?=htmlspecialchars($vps['username'])?>@<?=htmlspecialchars($vps['ip'])?>" class="matrix-btn btn-sm putty-btn" title="Mở PuTTY SSH" style="margin-left:6px;">PuTTY</a>
            </td>
        </tr>
        <tr>
            <td colspan="5" class="login-result" data-index="<?=$i?>"></td>
        </tr>
    <?php endif; ?>
<?php endforeach; ?>
</table>
<!-- Modal thêm nhanh VPS -->
<div id="quickAddModal" class="modal" style="display:none;">
    <div class="modal-content matrix-card matrix-modal">
        <span class="close-modal-quick matrix-close">&times;</span>
        <h3 class="matrix-title">Thêm Nhanh VPS (vps.txt)</h3>
        <form id="quickAddForm" method="post" autocomplete="off">
            <div class="matrix-form-group">
                <label class="matrix-label" for="quick_vps_list">Danh sách VPS (mỗi dòng: ip|username|password|info):</label>
                <textarea name="quick_vps_list" id="quick_vps_list" class="matrix-input" rows="8"></textarea>
            </div>
            <button type="submit" name="quick_add" class="matrix-btn">⚡ Thêm Nhanh</button>
        </form>
    </div>
</div>
<!-- Modal thêm VPS mới -->
<div id="addModal" class="modal" style="display:none;">
    <div class="modal-content matrix-card matrix-modal">
        <span class="close-modal-add matrix-close">&times;</span>
        <h3 class="matrix-title">Thêm VPS mới</h3>
        <form id="addForm" method="post" autocomplete="off">
            <div class="matrix-form-group">
                <label class="matrix-label" for="add_ip">IP:</label>
                <input type="text" name="ip" id="add_ip" class="matrix-input" autocomplete="off" placeholder="Nhập IP VPS">
            </div>
            <div class="matrix-form-group">
                <label class="matrix-label" for="add_username">Username:</label>
                <input type="text" name="username" id="add_username" class="matrix-input" autocomplete="off" placeholder="Nhập Username">
            </div>
            <div class="matrix-form-group">
                <label class="matrix-label" for="add_password">Password:</label>
                <input type="password" name="password" id="add_password" class="matrix-input" autocomplete="off" placeholder="Nhập Password">
            </div>
            <div class="matrix-form-group">
                <label class="matrix-label" for="add_info">Info:</label>
                <input type="text" name="info" id="add_info" class="matrix-input" autocomplete="off" placeholder="Thông tin VPS">
            </div>
            <button type="submit" name="add" class="matrix-btn">➕ Thêm mới</button>
        </form>
    </div>
</div>
<!-- Modal sửa VPS -->
<div id="editModal" class="modal" style="display:none;">
    <div class="modal-content matrix-card matrix-modal">
        <span class="close-modal matrix-close">&times;</span>
        <h3 class="matrix-title">Sửa VPS</h3>
        <form id="editForm" method="post" autocomplete="off">
            <input type="hidden" name="edit_id" id="edit_id">
            <div class="matrix-form-group">
                <label class="matrix-label" for="edit_ip">IP:</label>
                <input type="text" name="ip" id="edit_ip" class="matrix-input" autocomplete="off" placeholder="Nhập IP VPS">
            </div>
            <div class="matrix-form-group">
                <label class="matrix-label" for="edit_username">Username:</label>
                <input type="text" name="username" id="edit_username" class="matrix-input" autocomplete="off" placeholder="Nhập Username">
            </div>
            <div class="matrix-form-group">
                <label class="matrix-label" for="edit_password">Password:</label>
                <input type="password" name="password" id="edit_password" class="matrix-input" autocomplete="off" placeholder="Nhập Password">
            </div>
            <div class="matrix-form-group">
                <label class="matrix-label" for="edit_info">Info:</label>
                <input type="text" name="info" id="edit_info" class="matrix-input" autocomplete="off" placeholder="Thông tin VPS">
            </div>
            <button type="submit" class="matrix-btn">💾 Lưu</button>
        </form>
    </div>
</div>
<link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/vps_manager.css">
<script>
window.vpsList = <?=json_encode($vpsList)?>;


// Copy password to clipboard (with fallback)
function setupCopyPasswordButtons() {
    document.querySelectorAll('.copy-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var passId = this.getAttribute('data-password-id');
            var passElem = document.getElementById(passId);
            if (passElem) {
                var text = passElem.textContent;
                // Try modern clipboard API
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(text).then(() => {
                        btn.textContent = '✅';
                        setTimeout(() => { btn.textContent = '📋'; }, 1200);
                    }).catch(() => {
                        fallbackCopyText(text, btn);
                    });
                } else {
                    fallbackCopyText(text, btn);
                }
            }
        });
    });
}

function fallbackCopyText(text, btn) {
    var textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.style.position = 'fixed';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.focus();
    textarea.select();
    try {
        var successful = document.execCommand('copy');
        btn.textContent = successful ? '✅' : '❌';
    } catch (err) {
        btn.textContent = '❌';
    }
    setTimeout(() => { btn.textContent = '📋'; }, 1200);
    document.body.removeChild(textarea);
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', setupCopyPasswordButtons);
} else {
    setupCopyPasswordButtons();
}

// AJAX VPS login check
function ajaxCheckLogin(ip, username, password, rowIdx) {
    const btn = document.querySelector('.check-login-btn[data-index="' + rowIdx + '"]');
    const resultCell = document.querySelector('.login-result[data-index="' + rowIdx + '"]');
    if (btn) btn.disabled = true;
    if (resultCell) resultCell.innerHTML = '<span style="color:#00ff00;">Đang kiểm tra...</span>';
    fetch('assets/vps_login_ajax.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'ip=' + encodeURIComponent(ip) + '&username=' + encodeURIComponent(username) + '&password=' + encodeURIComponent(password)
    })
    .then(res => res.json())
    .then(data => {
        let msg = '';
        if (data.success) {
            msg = '✅ VPS Login thành công!';
            if (resultCell) resultCell.innerHTML = '<span style="color:#00ff00;">' + msg + '</span>';
        } else {
            msg = '❌ ' + (data.error || 'VPS Login thất bại');
            if (resultCell) resultCell.innerHTML = '<span style="color:#ff4444;">' + msg + '</span>';
        }
        // Ghi nhận báo cáo nếu đang check all
        if (window._isCheckAllRunning) {
            checkAllReport[rowIdx] = (ip + ' | ' + msg);
            // Nếu là lần cuối thì show báo cáo
            if (checkAllReport.filter(Boolean).length === window.vpsList.length) {
                showCheckAllReport();
                window._isCheckAllRunning = false;
            }
        }
        if (btn) btn.disabled = false;
    })
    .catch(() => {
        let msg = '❌ Lỗi AJAX';
        if (resultCell) resultCell.innerHTML = '<span style="color:#ff4444;">' + msg + '</span>';
        if (window._isCheckAllRunning) {
            checkAllReport[rowIdx] = (ip + ' | ' + msg);
            if (checkAllReport.filter(Boolean).length === window.vpsList.length) {
                showCheckAllReport();
                window._isCheckAllRunning = false;
            }
        }
        if (btn) btn.disabled = false;
    });
}

function showCheckAllReport() {
    let report = checkAllReport.join('\n');
    // Ghi ra file log trên server
    fetch('assets/vps_checkall_report.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'report=' + encodeURIComponent(report)
    })
    .then(res => res.json())
    .then(data => {
        if(data.success && data.file){
            alert('BÁO CÁO ĐÃ LƯU: ' + data.file + '\nBạn có thể tải file log về!');
        } else {
            alert('Lỗi ghi file log!');
        }
    })
    .catch(()=>{
        alert('Lỗi ghi file log!');
    });
}

// Báo cáo kết quả check all
let checkAllReport = [];

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('.check-login-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const idx = this.getAttribute('data-index');
            const vps = window.vpsList[idx];
            ajaxCheckLogin(vps.ip, vps.username, vps.password, idx);
        });
    });
    // Check All Login
    const checkAllBtn = document.getElementById('checkAllLoginBtn');
    if (checkAllBtn) {
        checkAllBtn.addEventListener('click', function() {
            checkAllReport = [];
            window._isCheckAllRunning = true;
            window.vpsList.forEach(function(vps, idx) {
                setTimeout(function() {
                    ajaxCheckLogin(vps.ip, vps.username, vps.password, idx);
                }, idx * 500); // 0.5s delay mỗi VPS để tránh nghẽn
            });
        });
    }
});
</script>
<style>
    body {
        background: #000;
        color: #00ff00;
        font-family: 'Roboto', sans-serif;
        text-shadow: 0 0 5px rgba(0,255,0,0.5);
        overflow-x: hidden;
    }
    /* Matrix Modal Styles */
    .matrix-modal {
        background: #101010;
        border: 2px solid #00ff00;
        border-radius: 18px;
        /* box-shadow removed for flat modal */
        animation: matrixGlow 1.5s infinite alternate;
    }
    @keyframes matrixGlow {
        0% {}
        100% {}
    }
    .matrix-title {
        color: #00ff00;
        font-size: 24px;
        font-weight: 600;
        letter-spacing: 1px;
        margin-bottom: 22px;
    }
    .matrix-form-group {
        margin-bottom: 10px;
    }
    .matrix-label {
        color: #00ff00;
        font-size: 15px;
        font-weight: 500;
        margin-bottom: 6px;
        display: block;
    }
    .matrix-input {
        width: 100%;
        padding: 6px 8px !important;
        border: 2px solid #00ff00;
        border-radius: 10px;
        font-size: 15px !important;
        background: #181818;
        color: #00ff00;
        outline: none;
        transition: border 0.2s, background 0.2s;
        font-weight: 600;
        margin-bottom: 2px;
    }
    .matrix-input:focus {
        border-color: #00ff88;
        background: #222;
    }
    .matrix-btn {
        background: rgba(0,255,0,0.12);
        border: 2px solid #00ff00;
        color: #00ff00;
        padding: 6px 14px !important;
        border-radius: 10px;
        font-size: 15px !important;
        margin-right: 4px;
        transition: all 0.3s;
        cursor: pointer;
    }
    .matrix-btn:hover {
        background: #00ff00;
        color: #000;
        border-color: #00ff88;
    }
    .matrix-close {
        font-size: 32px;
        color: #00ff00;
        cursor: pointer;
        transition: color 0.2s;
    }
    .matrix-close:hover {
        color: #fff;
    }
    body::before {
        content: '';
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: repeating-linear-gradient(90deg,transparent,transparent 98px,rgba(0,255,0,0.03) 100px);
        pointer-events: none;
        z-index: -1;
        animation: matrixLines 10s linear infinite;
    }
    @keyframes matrixLines {
        0% { transform: translateX(0); }
        100% { transform: translateX(100px); }
    }
    .matrix-card {
        background: rgba(0,0,0,0.9);
        border: 2px solid #00ff00;
        border-radius: 12px;
        padding: 1.5rem;
        margin-bottom: 1.5rem;
        backdrop-filter: blur(10px);
        transition: all 0.3s ease;
    }
    .matrix-card:hover {
        border-color: #00ff88;
        transform: translateY(-5px);
    }
    .vps-table {
        border-collapse: collapse;
        width: 100%;
        background: rgba(0,0,0,0.85);
        border: 2px solid #00ff00;
        border-radius: 12px;
        margin-bottom: 32px;
        color: #00ff00;
        font-family: 'Roboto', sans-serif;
    }
    .vps-table th, .vps-table td {
        border: 1px solid #00ff00;
        padding: 6px 10px;
        text-align: left;
        font-size: 15px;
        background: transparent;
    }
    .vps-table th {
        background: rgba(0,0,0,0.95);
        font-weight: 600;
        color: #00ff00;
        text-shadow: 0 0 10px rgba(0,255,0,0.8);
    }
    .vps-table tr.data-row:hover {
        background: rgba(0,255,0,0.08);
    }
    .vps-table input[type="text"], .vps-table input[type="password"] {
        width: 100%;
        padding: 10px 16px;
        border: 2px solid #00ff00;
        border-radius: 10px;
        font-size: 17px;
        background: #101010;
        color: #00ff00;
        outline: none;
        transition: border 0.2s, background 0.2s;
        font-weight: 600;
        margin-bottom: 2px;
    }
    .vps-table input[type="text"]:focus, .vps-table input[type="password"]:focus {
        border-color: #00ff88;
        background: #181818;
    }
    .vps-table button {
        background: rgba(0,255,0,0.1);
        border: 2px solid #00ff00;
        color: #00ff00;
        padding: 8px 18px;
        border-radius: 8px;
        font-size: 16px;
        font-weight: 500;
        margin-right: 4px;
        transition: all 0.3s;
    }
    .vps-table button:hover {
        background: #00ff00;
        color: #000;
        border-color: #00ff88;
    }
    .vps-table a {
        color: #00ff00;
        text-decoration: none;
        margin-right: 8px;
        font-weight: 500;
        transition: color 0.2s;
    }
    .vps-table a:hover {
        color: #fff;
        text-shadow: 0 0 8px #00ff00;
    }
    .vps-table form { margin: 0; }
    .vps-table td:last-child, .vps-table th:last-child { text-align: center; }
    h2 {
        color: #00ff00 !important;
        text-shadow: 0 0 10px rgba(0,255,0,0.8), 0 0 20px rgba(0,255,0,0.4);
        font-family: 'Roboto', sans-serif !important;
        font-weight: 500;
        margin-bottom: 24px;
    }
    @media (max-width: 700px) {
        .vps-table, .vps-table thead, .vps-table tbody, .vps-table th, .vps-table td, .vps-table tr {
            display: block;
        }
        .vps-table tr { margin-bottom: 16px; }
        .vps-table td, .vps-table th {
            border: none;
            padding: 8px 4px;
        }
        .vps-table th { background: rgba(0,0,0,0.95); }
    }
</style>
<script>
// Tự động focus ô IP khi thêm mới
window.addEventListener('DOMContentLoaded', function() {
    const addRow = document.querySelector('.vps-table tr:last-child input[name="ip"]');
    if (addRow) setTimeout(() => addRow.focus(), 200);
});
</script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const vpsSearchInput = document.getElementById('vpsSearchInput');
    if (vpsSearchInput) {
        vpsSearchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const rows = document.querySelectorAll('.vps-table tr');
            rows.forEach((row, idx) => {
                if (idx === 0) return; // Skip header
                const cells = row.querySelectorAll('td');
                let match = false;
                cells.forEach(cell => {
                    if (cell.textContent.toLowerCase().includes(query)) {
                        match = true;
                    }
                });
                row.style.display = match ? '' : 'none';
            });
        });
    }
});
</script>
