<?php
/**
 * aaPanel Actions UI
 * Bulk update aaPanel passwords for multiple VPS
 */

require_once 'APISecretKeyManager.php';
require_once 'APIKeyAuthInterface.php';

function buildVpsServerList($vpsFile = 'vps.json') {
    if (!file_exists($vpsFile)) {
        throw new Exception('Không tìm thấy file vps.json');
    }

    $raw = file_get_contents($vpsFile);
    $rows = json_decode($raw, true);
    if (!is_array($rows)) {
        throw new Exception('Dữ liệu vps.json không hợp lệ');
    }

    $servers = [];
    foreach ($rows as $index => $row) {
        if (!is_array($row) || empty($row['ip'])) {
            continue;
        }

        $servers[] = [
            'id' => $index + 1,
            'ip' => $row['ip'],
            'name' => !empty($row['name']) ? $row['name'] : ('VPS ' . $row['ip']),
            'panel_url' => $row['info'] ?? ('http://' . $row['ip'] . ':7800'),
            'team' => !empty($row['team']) ? $row['team'] : 'unknown',
            'status' => 'unknown'
        ];
    }

    return $servers;
}

session_start();
$keyManager = new APISecretKeyManager();
$authResult = $keyManager->validateSession();

if (!$authResult['valid']) {
    showAPIAuthPage('aapanel_actions');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $keyManager->destroySession();
    session_destroy();
    header('Location: index.php');
    exit;
}

$permissions = $authResult['permissions'] ?? [];
if (!in_array('admin', $permissions, true)) {
    header('HTTP/1.0 403 Forbidden');
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Access Denied - aaPanel Actions</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <div class="container mt-5">
            <div class="alert alert-danger text-center">
                <h4>Admin permission required</h4>
                <p>Bạn cần quyền admin để cập nhật mật khẩu aaPanel.</p>
                <a href="aapanel_manager.php" class="btn btn-primary">Quay lại aaPanel Manager</a>
            </div>
        </div>
    </body>
    </html>
    <?php
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'get_vps_list') {
    header('Content-Type: application/json; charset=UTF-8');

    try {
        $servers = buildVpsServerList('vps.json');

        echo json_encode([
            'success' => true,
            'data' => $servers,
            'total' => count($servers)
        ]);
    } catch (Throwable $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
    }
    exit;
}

$initialServers = [];
$initialLoadError = '';
try {
    $initialServers = buildVpsServerList('vps.json');
} catch (Throwable $e) {
    $initialLoadError = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>aaPanel Actions - Bulk Password Update</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/common.css" rel="stylesheet">
    <style>
        .server-list {
            max-height: 520px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            background: #fff;
        }
        .server-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #f1f3f5;
        }
        .server-item:last-child {
            border-bottom: 0;
        }
        .server-item:hover {
            background: #f8f9fa;
        }
        .progress-box {
            min-height: 220px;
            max-height: 320px;
            overflow-y: auto;
            border: 1px solid #dee2e6;
            border-radius: 0.5rem;
            background: #f8f9fa;
            padding: 0.75rem;
            font-size: 0.92rem;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-main navbar-expand-lg">
        <div class="container-fluid">
            <a class="navbar-brand" href="aapanel_actions.php">
                <i class="fas fa-tools me-2"></i>
                aaPanel Actions
                <span class="badge bg-success ms-2">Secured</span>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="aapanel_manager.php">
                    <i class="fas fa-arrow-left me-1"></i>Quay lại Manager
                </a>
                <a class="nav-link text-danger" href="?action=logout">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <?php
    $currentPage = 'aapanel-actions';
    include 'includes/navigation.php';
    ?>

    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h3 class="mb-0"><i class="fas fa-key me-2"></i>Bulk cập nhật mật khẩu aaPanel</h3>
                    <div class="btn-group">
                        <button class="btn btn-primary" onclick="loadServers()"><i class="fas fa-sync-alt me-1"></i>Tải danh sách</button>
                        <button class="btn btn-success" onclick="updateSelectedServerPasswords()" id="bulk-update-btn"><i class="fas fa-save me-1"></i>Cập nhật</button>
                    </div>
                </div>

                <div class="alert alert-info">
                    Đang đăng nhập bằng: <strong><?= htmlspecialchars($authResult['key_name'] ?? 'Unknown') ?></strong> |
                    Quyền: <strong><?= htmlspecialchars(implode(', ', $permissions)) ?></strong>
                </div>
                <?php if (!empty($initialLoadError)): ?>
                <div class="alert alert-warning">
                    Không tải được dữ liệu ban đầu từ vps.json: <?= htmlspecialchars($initialLoadError) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-lg-5">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Danh sách VPS</strong>
                        <div>
                            <span class="badge bg-secondary" id="server-count">0</span>
                            <span class="badge bg-dark" id="selected-count">0 đã chọn</span>
                        </div>
                    </div>
                    <div class="card-body pt-2">
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="select-all" onchange="toggleSelectAll(this.checked)">
                            <label class="form-check-label" for="select-all">Chọn tất cả</label>
                        </div>
                        <div class="mb-2">
                            <label for="team-filter" class="form-label form-label-sm mb-1">Lọc theo team</label>
                            <select class="form-select form-select-sm" id="team-filter" onchange="filterServerList()">
                                <option value="">Tất cả team</option>
                            </select>
                        </div>
                        <div class="mb-2">
                            <input type="text" class="form-control form-control-sm" id="server-filter" placeholder="Tìm theo IP / tên VPS" oninput="filterServerList()">
                        </div>
                        <div class="server-list" id="server-list">
                            <div class="text-center p-3 text-muted">Đang tải dữ liệu...</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header"><strong>Cấu hình cập nhật</strong></div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label" for="new-password">Mật khẩu mới</label>
                            <input type="password" class="form-control" id="new-password" placeholder="Nhập mật khẩu mới (tối thiểu 6 ký tự)">
                        </div>
                        <div class="mb-0">
                            <label class="form-label" for="confirm-password">Nhập lại mật khẩu</label>
                            <input type="password" class="form-control" id="confirm-password" placeholder="Nhập lại mật khẩu mới">
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Tiến trình</strong>
                        <button class="btn btn-outline-secondary btn-sm" onclick="clearProgress()">Xóa log</button>
                    </div>
                    <div class="card-body">
                        <div class="progress-box" id="progress-box">Chưa bắt đầu.</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let allServers = <?= json_encode($initialServers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
        let displayedServers = [...allServers];
        let selectedServerIds = new Set();

        document.addEventListener('DOMContentLoaded', () => {
            if (allServers.length > 0) {
                updateTeamFilterOptions();
                renderServerList(displayedServers);
                document.getElementById('server-count').textContent = allServers.length;
                refreshSelectionCount();
            } else {
                loadServers();
            }
        });

        async function loadServers() {
            const list = document.getElementById('server-list');
            list.innerHTML = '<div class="text-center p-3 text-muted">Đang tải dữ liệu...</div>';

            try {
                const data = await loadServersWithFallback();

                allServers = Array.isArray(data.data) ? data.data : [];
                displayedServers = [...allServers];
                selectedServerIds.forEach(id => {
                    if (!allServers.some(server => server.id === id)) {
                        selectedServerIds.delete(id);
                    }
                });

                updateTeamFilterOptions();
                renderServerList(displayedServers);
                document.getElementById('server-count').textContent = allServers.length;
                refreshSelectionCount();
            } catch (error) {
                list.innerHTML = `<div class="text-center p-3 text-danger">Không tải được danh sách VPS<br><small>${escapeHtml(error.message)}</small></div>`;
                notify('danger', 'Lỗi tải danh sách VPS: ' + error.message);
            }
        }

        async function loadServersWithFallback() {
            const endpoints = [
                'aapanel_actions.php?action=get_vps_list',
                'aapanel_manager.php?action=get_servers'
            ];

            let lastError = 'Unknown error';
            for (const endpoint of endpoints) {
                try {
                    const data = await fetchJsonSafe(endpoint);
                    if (data && data.success) {
                        return data;
                    }
                    lastError = data && data.message ? data.message : `API failed at ${endpoint}`;
                } catch (err) {
                    lastError = err.message;
                }
            }

            throw new Error(lastError);
        }

        async function fetchJsonSafe(url) {
            const response = await fetch(url, {
                method: 'GET',
                headers: { 'Accept': 'application/json' }
            });

            const rawText = await response.text();
            let data = null;
            try {
                data = JSON.parse(rawText);
            } catch (e) {
                const preview = (rawText || '').replace(/\s+/g, ' ').slice(0, 180);
                throw new Error(`Response không phải JSON từ ${url}. Preview: ${preview}`);
            }

            if (!response.ok) {
                throw new Error(data.message || `HTTP ${response.status}`);
            }

            return data;
        }

        function escapeHtml(value) {
            return String(value || '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#39;');
        }

        function updateTeamFilterOptions() {
            const teamSelect = document.getElementById('team-filter');
            const currentValue = teamSelect.value;
            const teams = [...new Set(allServers.map(server => (server.team || 'unknown')))]
                .filter(Boolean)
                .sort((a, b) => a.localeCompare(b));

            teamSelect.innerHTML = '<option value="">Tất cả team</option>' +
                teams.map(team => `<option value="${team}">${team}</option>`).join('');

            if (teams.includes(currentValue)) {
                teamSelect.value = currentValue;
            }
        }

        function renderServerList(servers) {
            const list = document.getElementById('server-list');

            if (!servers || servers.length === 0) {
                list.innerHTML = '<div class="text-center p-3 text-muted">Không có VPS nào</div>';
                return;
            }

            list.innerHTML = servers.map(server => {
                const checked = selectedServerIds.has(server.id) ? 'checked' : '';
                const info = server.ip || server.panel_url || 'N/A';
                const team = server.team || 'unknown';
                return `
                    <div class="server-item">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="server-${server.id}" ${checked}
                                   onchange="toggleServer(${server.id}, this.checked)">
                            <label class="form-check-label w-100" for="server-${server.id}">
                                <div class="d-flex justify-content-between">
                                    <span>
                                        <strong>${server.name}</strong><br>
                                        <small class="text-muted">${info}</small><br>
                                        <span class="badge bg-light text-dark border mt-1">Team: ${team}</span>
                                    </span>
                                    <span class="badge bg-secondary align-self-start">${server.status || 'unknown'}</span>
                                </div>
                            </label>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function filterServerList() {
            const keyword = (document.getElementById('server-filter').value || '').toLowerCase().trim();
            const selectedTeam = (document.getElementById('team-filter').value || '').toLowerCase().trim();

            displayedServers = allServers.filter(server => {
                const ip = (server.ip || '').toLowerCase();
                const name = (server.name || '').toLowerCase();
                const panelUrl = (server.panel_url || '').toLowerCase();
                const team = (server.team || 'unknown').toLowerCase();

                const matchesKeyword = !keyword || ip.includes(keyword) || name.includes(keyword) || panelUrl.includes(keyword);
                const matchesTeam = !selectedTeam || team === selectedTeam;
                return matchesKeyword && matchesTeam;
            });

            renderServerList(displayedServers);
            refreshSelectionCount();
        }

        function toggleServer(serverId, checked) {
            if (checked) {
                selectedServerIds.add(serverId);
            } else {
                selectedServerIds.delete(serverId);
            }
            refreshSelectionCount();
        }

        function toggleSelectAll(checked) {
            if (checked) {
                displayedServers.forEach(server => selectedServerIds.add(server.id));
            } else {
                displayedServers.forEach(server => selectedServerIds.delete(server.id));
            }
            renderServerList(displayedServers);
            refreshSelectionCount();
        }

        function refreshSelectionCount() {
            const selectedCount = selectedServerIds.size;
            document.getElementById('selected-count').textContent = `${selectedCount} đã chọn`;

            const selectAll = document.getElementById('select-all');
            selectAll.checked = allServers.length > 0 && selectedCount === allServers.length;
            selectAll.indeterminate = selectedCount > 0 && selectedCount < allServers.length;
        }

        function clearProgress() {
            document.getElementById('progress-box').textContent = 'Chưa bắt đầu.';
        }

        function appendProgress(html) {
            const box = document.getElementById('progress-box');
            if (box.textContent === 'Chưa bắt đầu.') {
                box.innerHTML = '';
            }
            box.innerHTML += html;
            box.scrollTop = box.scrollHeight;
        }

        async function updateSelectedServerPasswords() {
            const selectedServers = allServers.filter(server => selectedServerIds.has(server.id));
            const newPassword = document.getElementById('new-password').value;
            const confirmPassword = document.getElementById('confirm-password').value;
            const updateBtn = document.getElementById('bulk-update-btn');

            if (selectedServers.length === 0) {
                notify('warning', 'Vui lòng chọn ít nhất 1 VPS');
                return;
            }

            if (newPassword.length < 6) {
                notify('warning', 'Mật khẩu mới phải có ít nhất 6 ký tự');
                return;
            }

            if (newPassword !== confirmPassword) {
                notify('warning', 'Mật khẩu nhập lại không khớp');
                return;
            }

            updateBtn.disabled = true;
            appendProgress('<div class="text-info">Bắt đầu cập nhật...</div>');

            let successCount = 0;
            let failCount = 0;

            for (const server of selectedServers) {
                const identifier = server.panel_url || server.ip;
                if (!identifier) {
                    failCount++;
                    appendProgress(`<div class="text-danger">❌ ${server.name}: Thiếu identifier để cập nhật</div>`);
                    continue;
                }

                try {
                    const body = new URLSearchParams({
                        action: 'update_aapanel_password',
                        identifier: identifier,
                        new_password: newPassword
                    });

                    const response = await fetch('aapnel_action.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: body.toString()
                    });

                    const result = await response.json();
                    if (result.success) {
                        successCount++;
                        appendProgress(`<div class="text-success">✅ ${server.name}: Cập nhật thành công</div>`);
                    } else {
                        failCount++;
                        appendProgress(`<div class="text-danger">❌ ${server.name}: ${result.message || 'Lỗi không xác định'}</div>`);
                    }
                } catch (error) {
                    failCount++;
                    appendProgress(`<div class="text-danger">❌ ${server.name}: ${error.message}</div>`);
                }
            }

            appendProgress(`<hr><div><strong>Kết quả:</strong> ${successCount} thành công, ${failCount} thất bại</div>`);
            updateBtn.disabled = false;

            if (failCount === 0) {
                notify('success', `Đã cập nhật mật khẩu cho ${successCount} VPS`);
                document.getElementById('new-password').value = '';
                document.getElementById('confirm-password').value = '';
            } else {
                notify('warning', `Hoàn tất với ${successCount} thành công, ${failCount} thất bại`);
            }
        }

        function notify(type, message) {
            const alertClass = type === 'success' ? 'alert-success' : type === 'warning' ? 'alert-warning' : type === 'info' ? 'alert-info' : 'alert-danger';
            const wrapper = document.createElement('div');
            wrapper.className = `alert ${alertClass} alert-dismissible fade show`;
            wrapper.style.position = 'fixed';
            wrapper.style.top = '20px';
            wrapper.style.right = '20px';
            wrapper.style.zIndex = '9999';
            wrapper.style.maxWidth = '420px';
            wrapper.innerHTML = `${message}<button type="button" class="btn-close" data-bs-dismiss="alert"></button>`;
            document.body.appendChild(wrapper);
            setTimeout(() => {
                if (wrapper.parentNode) {
                    wrapper.remove();
                }
            }, 4500);
        }
    </script>
</body>
</html>
