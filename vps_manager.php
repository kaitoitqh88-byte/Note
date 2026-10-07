<?php
// vps_manager.php - Quản lý VPS (CRUD + Check Login)
$jsonFile = __DIR__ . '/vps.json'; 

function getVpsList($jsonFile) {
    if (!file_exists($jsonFile)) return [];
    $data = file_get_contents($jsonFile);
    if ($data === false) return [];

    // Handle UTF-8 BOM to avoid json_decode returning null on valid JSON.
    if (strncmp($data, "\xEF\xBB\xBF", 3) === 0) {
        $data = substr($data, 3);
    }

    $arr = json_decode($data, true);
    return is_array($arr) ? $arr : [];
}
function saveVpsList($jsonFile, $list) {
    file_put_contents($jsonFile, json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

$vpsList = getVpsList($jsonFile);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add'])) {
        $status = trim($_POST['status'] ?? '');
        $new = [
            'ip' => $_POST['ip'],
            'username' => $_POST['username'],
            'password' => $_POST['password'],
            'info' => $_POST['info'],
            'aapanel_keyapi' => $_POST['aapanel_keyapi'] ?? '',
            'status' => $status
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
            $aapanel_keyapi = isset($parts[4]) ? trim($parts[4]) : '';
            $status = isset($parts[5]) ? trim($parts[5]) : '';
            $vpsList[] = [
                'ip' => $ip,
                'username' => $username,
                'password' => $password,
                'info' => $info,
                'aapanel_keyapi' => $aapanel_keyapi,
                'status' => $status
            ];
        }
        saveVpsList($jsonFile, $vpsList);
        header('Location: vps_manager.php'); exit;
    }
    if (isset($_POST['edit_id'])) {
        $id = (int)$_POST['edit_id'];
        if (isset($vpsList[$id])) {
            $vpsList[$id]['ip'] = $_POST['ip'];
            $vpsList[$id]['username'] = $_POST['username'];
            $vpsList[$id]['password'] = $_POST['password'];
            $vpsList[$id]['info'] = $_POST['info'];
            $vpsList[$id]['aapanel_keyapi'] = $_POST['aapanel_keyapi'] ?? '';
            $vpsList[$id]['status'] = trim($_POST['status'] ?? '');
        }
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
// Sidebar menu include for vps_manager.php
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
<div id="mainWrapper" style="padding-left:280px; background:#000; min-height:100vh; color:#00ff00;">
<style>
.matrix-input, .vps-table input[type="text"], .vps-table input[type="password"], input[type="text"], input[type="password"], textarea {
    background: #000 !important;
    border: 2px solid #00ff00 !important;
    color: #00ff00 !important;
    font-family: 'Roboto', monospace, Arial, sans-serif !important;
    border-radius: 8px !important;
    font-size: 15px !important;
    font-weight: 500;
    padding: 8px 12px !important;
    outline: none !important;
    box-shadow: none !important;
    transition: border 0.2s, background 0.2s;
}
.matrix-input:focus, .vps-table input[type="text"]:focus, .vps-table input[type="password"]:focus, input[type="text"]:focus, input[type="password"]:focus, textarea:focus {
    border-color: #00ff88 !important;
    background: #101010 !important;
}
.matrix-btn, .vps-table button, .btn, button {
    background: #000;
    border: 2px solid #00ff00;
    color: #00ff00;
    font-family: 'Roboto', monospace, Arial, sans-serif;
    font-weight: 600;
    border-radius: 10px;
    padding: 7px 18px;
    font-size: 15px;
    margin: 2px 4px 2px 0;
    box-shadow: 0 0 8px #00ff0033, 0 0 2px #00ff0044;
    transition: all 0.22s cubic-bezier(.4,2,.6,1);
    outline: none;
    cursor: pointer;
    text-shadow: 0 0 8px #00ff00, 0 0 2px #00ff00;
}
.matrix-btn:hover, .vps-table button:hover, .btn:hover, button:hover {
    background: #00ff00;
    color: #000;
    border-color: #00ff88;
    box-shadow: 0 0 1px #00ff00, 0 0 1px #00ff00;
    text-shadow: none;
}
.matrix-btn:active, .vps-table button:active, .btn:active, button:active {
    background: #003300;
    color: #00ff00;
    border-color: #00ff00;
    box-shadow: 0 0 4px #00ff00;
}
.matrix-btn:focus, .vps-table button:focus, .btn:focus, button:focus {
    outline: 2px solid #00ff00;
    outline-offset: 2px;
}
/* Matrix style cho bảng danh sách VPS */
.vps-table {
    width: 100%;
    border-collapse: separate;
    border-spacing: 0;
    background: rgba(0,0,0,0.85);
    border: 2px solid #00ff00;
    border-radius: 14px;
    box-shadow: 0 0 24px 0 rgba(0,255,0,0.08);
    font-family: 'Roboto', monospace, Arial, sans-serif;
    margin-bottom: 32px;
    overflow: hidden;
}
.vps-table th, .vps-table td {
    border: 1px solid #00ff00;
    padding: 10px 14px;
    font-size: 15px;
    color: #00ff00;
    background: transparent;
    text-align: left;
    transition: background 0.2s, color 0.2s;
}
.vps-table th {
    background: rgba(0,0,0,0.95);
    font-weight: 700;
    color: #00ff00; 
    border-bottom: 2px solid #00ff00;
}
.vps-table tr {
    transition: background 0.2s, box-shadow 0.2s;
}
.vps-table tr:hover {
    background: rgba(0,255,0,0.08);
    box-shadow: 0 0 16px #00ff00;
}
.vps-table td:last-child, .vps-table th:last-child {
    text-align: center;
}
.vps-table input[type="text"], .vps-table input[type="password"] {
    width: 100%;
    padding: 8px 12px;
    border: 2px solid #00ff00;
    border-radius: 8px;
    font-size: 15px;
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
    padding: 6px 14px;
    border-radius: 8px;
    font-size: 15px;
    font-weight: 500;
    margin-right: 4px;
    transition: all 0.3s;
}
.vps-table button:hover {
    background: #00ff00;
    color: #000;
    border-color: #00ff88;
    box-shadow: 0 0 1px #00ff00;
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
    text-shadow: 0 0 1px #00ff00;
}
.vps-table form { margin: 0; }
.vps-table tr:last-child td { border-bottom: none; }
.vps-table td, .vps-table th { border-right: none; }
.vps-table th:first-child, .vps-table td:first-child { border-left: none; }
.vps-table th:last-child, .vps-table td:last-child { border-right: none; }
  
#mainWrapper, #mainWrapper * {
    color: #00ff00 !important;
}
</style>
    <!-- Sidebar Toggle Button (hiện trên mobile và desktop) -->
<div class="container-fluid" style="padding-top:32px; padding-bottom:32px;">
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
        <th>IP</th><th>Username</th><th>Password</th><th>Info</th><th>KeyAPI aaPanel</th><th>Tình trạng</th><th>Hành động</th>
    </tr>
    <?php foreach ($vpsList as $i => $vps): ?>
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
            <td><?=htmlspecialchars($vps['aapanel_keyapi'] ?? '')?></td>
            <td><?=htmlspecialchars($vps['status'] ?? '')?></td>
            <td>
                <button type="button" class="edit-btn" data-index="<?=$i?>">Sửa</button>
                <form method="post" style="display:inline">
                    <input type="hidden" name="delete_id" value="<?=$i?>">
                    <button type="submit" onclick="return confirm('Xóa VPS này?')">Xóa</button>
                </form>
                <button type="button" class="matrix-btn btn-sm check-login-btn" data-index="<?=$i?>">Check Login</button>
            </td>
        </tr>
        <tr>
            <td colspan="7" class="login-result" data-index="<?=$i?>"></td>
        </tr>
    <?php endforeach; ?>
    </table>
    </div> <!-- end .container -->
<!-- Modal thêm nhanh VPS -->
<div id="quickAddModal" class="modal matrix-fade-modal" style="display:none;z-index:9999;">
    <div class="modal-content matrix-card matrix-modal">
        <span class="close-modal-quick matrix-close">&times;</span>
        <h3 class="matrix-title">Thêm Nhanh VPS (vps.txt)</h3>
        <form id="quickAddForm" method="post" autocomplete="off">
            <div class="matrix-form-group">
                <label class="matrix-label" for="quick_vps_list">Danh sách VPS (mỗi dòng: ip|username|password|info|aapanel_keyapi|status):</label>
                <textarea name="quick_vps_list" id="quick_vps_list" class="matrix-input" rows="8"></textarea>
            </div>
            <button type="submit" name="quick_add" class="matrix-btn">⚡ Thêm Nhanh</button>
        </form>
    </div>
</div>
<!-- Modal thêm VPS mới -->
<div id="addModal" class="modal matrix-fade-modal" style="display:none;z-index:9999;">
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
            <div class="matrix-form-group">
                <label class="matrix-label" for="add_aapanel_keyapi">KeyAPI aaPanel:</label>
                <input type="text" name="aapanel_keyapi" id="add_aapanel_keyapi" class="matrix-input" autocomplete="off" placeholder="KeyAPI aaPanel (nếu có)">
            </div>
            <div class="matrix-form-group">
                <label class="matrix-label" for="add_status">Tình trạng:</label>
                <select name="status" id="add_status" class="matrix-input">
                    <option value="">-- Chọn tình trạng --</option>
                    <option value="Active">Active</option>
                    <option value="Blocked">Blocked</option>
                    <option value="Stopped">Stopped</option>
                </select>
            </div>
            <button type="submit" name="add" class="matrix-btn">➕ Thêm mới</button>
        </form>
    </div>
</div>
<!-- Modal sửa VPS -->
<div id="editModal" class="modal matrix-fade-modal" style="display:none;z-index:9999;">
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
            <div class="matrix-form-group">
                <label class="matrix-label" for="edit_aapanel_keyapi">KeyAPI aaPanel:</label>
                <input type="text" name="aapanel_keyapi" id="edit_aapanel_keyapi" class="matrix-input" autocomplete="off" placeholder="KeyAPI aaPanel (nếu có)">
            </div>
            <div class="matrix-form-group">
                <label class="matrix-label" for="edit_status">Tình trạng:</label>
                <select name="status" id="edit_status" class="matrix-input">
                    <option value="">-- Chọn tình trạng --</option>
                    <option value="Active">Active</option>
                    <option value="Blocked">Blocked</option>
                    <option value="Stopped">Stopped</option>
                </select>
            </div>
            <button type="submit" class="matrix-btn">💾 Lưu</button>
        </form>
    </div>
</div>
<!-- Sidebar and main style are handled by main_navigation.php for consistency -->
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
<script src="assets/vps_manager.js"></script>

<!-- Sidebar Overlay for Mobile -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
<script>
// Sidebar toggle for mobile (đồng bộ hiệu ứng với main_navigation.php)
document.addEventListener('DOMContentLoaded', function() {
    const sidebarNav = document.getElementById('sidebarNav');
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const mainWrapper = document.getElementById('mainWrapper') || document.body;

    function toggleSidebar() {
        sidebarNav.classList.toggle('active');
        sidebarOverlay.classList.toggle('active');
        mainWrapper.classList.toggle('sidebar-open');
    }
    function closeSidebar() {
        sidebarNav.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        mainWrapper.classList.remove('sidebar-open');
    }
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function(e) {
            e.preventDefault();
            toggleSidebar();
        });
    }
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', closeSidebar);
    }
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeSidebar();
    });
    // Handle submenu toggles
    const submenuToggles = document.querySelectorAll('.submenu-toggle');
    submenuToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('data-bs-target');
            const submenu = document.querySelector(targetId);
            const arrow = this.querySelector('.submenu-arrow');
            if (submenu) {
                submenu.classList.toggle('show');
                arrow.innerHTML = submenu.classList.contains('show') ? '▲' : '▼';
            }
        });
    });
    // Auto-close sidebar on mobile khi click menu item (thêm delay như main_navigation.php)
    const menuLinks = document.querySelectorAll('.sidebar-nav .menu-link, .sidebar-nav .submenu-link');
    menuLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                setTimeout(closeSidebar, 150);
            }
        });
    });
});
</script>
