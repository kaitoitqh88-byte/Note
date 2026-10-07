<?php
// ================= SSH Terminal Functionality (phpseclib) =================

require_once __DIR__ . '/../vendor/autoload.php';
use phpseclib3\Net\SSH2;

// Đọc danh sách VPS từ vps.json
$vps_list = [];
$vps_file = __DIR__ . '/../vps.json';
if (file_exists($vps_file)) {
    $json = file_get_contents($vps_file);
    $vps_list = json_decode($json, true);
    if (!is_array($vps_list)) $vps_list = [];
}

$ssh_outputs = [];
$ssh_error = '';
// AJAX: Nếu có tham số ajax_vps_ip thì chỉ chạy 1 VPS
if (isset($_POST['ajax_vps_ip']) && isset($_POST['ssh_cmd'])) {
    $selected_ip = $_POST['ajax_vps_ip'];
    $cmd = trim($_POST['ssh_cmd']);
    $selected_vps = null;
    foreach ($vps_list as $vps) {
        if ($vps['ip'] === $selected_ip) {
            $selected_vps = $vps;
            break;
        }
    }
    $result = [
        'ip' => $selected_ip,
        'info' => $selected_vps['info'] ?? ''
    ];
    if ($selected_vps && $cmd) {
        try {
            $ssh = new SSH2($selected_vps['ip']);
            if (!$ssh->login($selected_vps['username'], $selected_vps['password'])) {
                $result['error'] = 'Đăng nhập SSH thất bại!';
            } else {
                $result['output'] = $ssh->exec($cmd);
            }
        } catch (Exception $e) {
            $result['error'] = 'Lỗi SSH: ' . $e->getMessage();
        }
    } else {
        $result['error'] = 'Thiếu thông tin VPS hoặc lệnh.';
    }
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chạy lệnh SSH từ xa (phpseclib)</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<?php 
$currentPage = 'runterminal';
include_once __DIR__ . '/../includes/main_navigation.php'; 
?>
<style>
    body, .main-dark-bg {
        background: #000 !important;
        color: green !important;
    }
    .main-dark-bg input, .main-dark-bg select, .main-dark-bg textarea {
        background: #001a0d !important;
        color: green !important;
        border: 1px solid green;
    }
    .main-dark-bg .form-control:focus, .main-dark-bg .form-select:focus {
        box-shadow: 0 0 8px #00ff88;
        border-color: #00ff88;
    }
    .main-dark-bg .card {
        background: #000 !important;
        border: 1.5px solid green;
        box-shadow: 0 0 24px 0 rgba(0,255,0,0.08);
    }
    .main-dark-bg .card-header {
        background: #001a0d !important;
        color: green !important;
        border-bottom: 1px solid green;
    }
    .main-dark-bg .btn-primary {
        background: green !important;
        color: #000 !important;
        border: none;
        font-weight: bold;
        box-shadow: 0 0 8px #00ff88;
    }
    .main-dark-bg .btn-primary:hover {
        background: #00ff88 !important;
        color: #000 !important;
    }
    .main-dark-bg .alert-success {
        background: #001a0d;
        color: green;
        border-color: green;
    }
    .main-dark-bg .alert-danger {
        background: #1a0000;
        color: #ff4444;
        border-color: #ff4444;
    }
    .main-dark-bg .alert-info {
        background: #001a0d;
        color: #00ff88;
        border-color: #00ff88;
    }
</style>
<div class="container mt-5 main-dark-bg" style="margin-left:300px;max-width:calc(100vw - 320px);">
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-terminal me-2"></i>Chạy lệnh SSH từ xa (phpseclib)</h5>
        </div>
        <div class="card-body">
            <form id="sshForm" onsubmit="return runSSHMultiAjax(event)">
                <div class="row g-2 mb-2">
                    <div class="col-md-8">
                        <select name="vps_ip[]" id="vps_ip" class="form-select" multiple size="6">
                            <?php foreach ($vps_list as $vps): ?>
                                <option value="<?php echo htmlspecialchars($vps['ip']); ?>" <?php if(isset($_POST['vps_ip']) && is_array($_POST['vps_ip']) && in_array($vps['ip'], $_POST['vps_ip'])) echo 'selected'; ?>>
                                    <?php echo htmlspecialchars($vps['ip'] . (isset($vps['info']) ? ' - ' . $vps['info'] : '')); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <input type="text" name="ssh_cmd" class="form-control" placeholder="Lệnh (ví dụ: ls -la)" required  value="<?php echo htmlspecialchars($_POST['ssh_cmd'] ?? ''); ?>" />
                    </div>
                </div>
                <div class="row mb-2">
                    <div class="col-md-12 text-end">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-play"></i> Thực thi</button>
                    </div>
                </div>
                <div id="ajaxResults"></div>
            </form>
            <script>
            // Không cần tự động điền thông tin VPS nữa

            function runSSHMultiAjax(e) {
                e.preventDefault();
                const vpsSelect = document.getElementById('vps_ip');
                const cmd = document.querySelector('input[name="ssh_cmd"]').value;
                const resultsDiv = document.getElementById('ajaxResults');
                const vpsList = Array.from(vpsSelect.selectedOptions).map(opt => opt.value);
                if (vpsList.length === 0 || !cmd) {
                    resultsDiv.innerHTML = '<div class="alert alert-warning">Vui lòng chọn ít nhất 1 VPS và nhập lệnh.</div>';
                    return false;
                }
                resultsDiv.innerHTML = '';
                vpsList.forEach(ip => {
                    const resultId = 'result_' + ip.replace(/[^a-zA-Z0-9]/g, '_');
                    const resultBox = document.createElement('div');
                    resultBox.id = resultId;
                    resultBox.className = 'alert alert-info mt-2';
                    resultBox.innerHTML = `<b>${ip}</b>: Đang thực thi...`;
                    resultsDiv.appendChild(resultBox);
                    const formData = new FormData();
                    formData.append('ajax_vps_ip', ip);
                    formData.append('ssh_cmd', cmd);
                    fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.error) {
                            resultBox.className = 'alert alert-danger mt-2';
                            resultBox.innerHTML = `<b>${data.ip}${data.info ? ' - ' + data.info : ''}</b><br>${data.error}`;
                        } else {
                            resultBox.className = 'alert alert-success mt-2';
                            resultBox.innerHTML = `<b>${data.ip}${data.info ? ' - ' + data.info : ''}</b><br><span style='white-space:pre-wrap;font-family:monospace;'>${data.output}</span>`;
                        }
                    })
                    .catch(err => {
                        resultBox.className = 'alert alert-danger mt-2';
                        resultBox.innerHTML = `<b>${ip}</b><br>Lỗi AJAX: ${err}`;
                    });
                });
                return false;
            }
            </script>
            <!-- Kết quả sẽ được AJAX render vào đây -->
        </div>
    </div>
</div>
</body>
</html>
