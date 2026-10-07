<?php
session_start();
require_once 'config.php';

// Kiểm tra file tồn tại
$accountsFile = __DIR__ . '/aapanel.txt';
if (!file_exists($accountsFile)) {
    die("Error: File aapanel.txt không tồn tại!");
}

// Đọc danh sách tài khoản
function loadAccounts($file) {
    $accounts = [];
    if (file_exists($file)) {
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            $parts = preg_split('/\s+/', trim($line), 3);
            if (count($parts) >= 3) {
                $accounts[] = [
                    'url' => $parts[0],
                    'username' => $parts[1],
                    'password' => $parts[2]
                ];
            }
        }
    }
    return $accounts;
}

// Kiểm tra kết nối aaPanel
function checkAAPanelConnection($url, $username, $password) {
    try {
        // Parse URL để lấy thông tin
        $parsedUrl = parse_url($url);
        if (!$parsedUrl) {
            return [
                'success' => false,
                'error' => 'URL không hợp lệ',
                'details' => 'Không thể parse URL'
            ];
        }

        $host = $parsedUrl['host'] ?? '';
        $port = $parsedUrl['port'] ?? (($parsedUrl['scheme'] === 'https') ? 443 : 80);
        $path = $parsedUrl['path'] ?? '/';

        // Kiểm tra kết nối cơ bản
        $startTime = microtime(true);
        $context = stream_context_create([
            'http' => [
                'timeout' => 10,
                'ignore_errors' => true
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false
            ]
        ]);

        // Thử kết nối đến URL aaPanel
        $loginUrl = $url;
        if (!str_contains($loginUrl, '/login')) {
            $loginUrl = rtrim($loginUrl, '/') . '/login';
        }

        $response = @file_get_contents($loginUrl, false, $context);
        $responseTime = round((microtime(true) - $startTime) * 1000, 2);

        if ($response === false) {
            // Thử kiểm tra kết nối TCP
            $socket = @fsockopen($host, $port, $errno, $errstr, 5);
            if (!$socket) {
                return [
                    'success' => false,
                    'error' => 'Không thể kết nối',
                    'details' => "Host: $host:$port - $errstr ($errno)"
                ];
            }
            fclose($socket);

            return [
                'success' => false,
                'error' => 'Server không phản hồi',
                'details' => 'TCP connection OK nhưng HTTP request failed'
            ];
        }

        // Kiểm tra response headers
        $headers = $http_response_header ?? [];
        $statusCode = 'Unknown';
        if (!empty($headers[0])) {
            preg_match('/HTTP\/\d\.\d\s+(\d+)/', $headers[0], $matches);
            $statusCode = $matches[1] ?? 'Unknown';
        }

        // Kiểm tra xem có phải trang aaPanel không
        $isAAPanelPage = false;
        if ($response) {
            $isAAPanelPage = (
                stripos($response, 'aapanel') !== false ||
                stripos($response, 'bt.cn') !== false ||
                stripos($response, 'panel') !== false ||
                stripos($response, 'login') !== false
            );
        }

        // Thử đăng nhập (simulation - không thực hiện đăng nhập thật)
        $loginAttempt = [
            'can_login' => 'Unknown',
            'login_form_found' => false
        ];

        if ($response && (stripos($response, 'form') !== false || stripos($response, 'login') !== false)) {
            $loginAttempt['login_form_found'] = true;
            
            // Kiểm tra form login
            if (preg_match('/<form[^>]*>/i', $response)) {
                // Có form login, có thể thực hiện đăng nhập
                $loginAttempt['can_login'] = 'Form available';
            }
        }

        return [
            'success' => true,
            'data' => [
                'url' => $url,
                'host' => $host,
                'port' => $port,
                'status_code' => $statusCode,
                'response_time' => $responseTime . 'ms',
                'is_aapanel' => $isAAPanelPage,
                'login_info' => $loginAttempt,
                'response_size' => strlen($response) . ' bytes'
            ]
        ];

    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Exception occurred',
            'details' => $e->getMessage()
        ];
    }
}

// Xử lý AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'check_account') {
        $accounts = loadAccounts($accountsFile);
        $index = (int)$_POST['index'];
        
        if (isset($accounts[$index])) {
            $account = $accounts[$index];
            $result = checkAAPanelConnection($account['url'], $account['username'], $account['password']);
            echo json_encode($result);
        } else {
            echo json_encode(['success' => false, 'error' => 'Account not found']);
        }
        exit;
    }
    
    if ($_POST['action'] === 'check_all') {
        $accounts = loadAccounts($accountsFile);
        $results = [];
        
        foreach ($accounts as $index => $account) {
            $result = checkAAPanelConnection($account['url'], $account['username'], $account['password']);
            $result['index'] = $index;
            $result['account'] = [
                'url' => $account['url'],
                'username' => $account['username']
            ];
            $results[] = $result;
            
            // Ngừng 0.5s giữa các lần check để tránh quá tải
            usleep(500000);
        }
        
        echo json_encode(['success' => true, 'results' => $results]);
        exit;
    }
}

$accounts = loadAccounts($accountsFile);
?>

<!DOCTYPE html>
<html lang="vi">
<head>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>aaPanel Account Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Roboto', sans-serif;
        }
        
        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            backdrop-filter: blur(10px);
            margin: 20px auto;
            max-width: 1200px;
        }
        
        .header-section {
            background: linear-gradient(135deg, #2c3e50, #3498db);
            color: white;
            padding: 30px;
            border-radius: 20px 20px 0 0;
            text-align: center;
        }
        
        .stats-card {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 15px;
            padding: 20px;
            margin: 10px;
        }
        
        .account-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }
        
        .account-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }
        
        .status-badge {
            font-size: 0.85em;
            padding: 8px 12px;
            border-radius: 20px;
        }
        
        .btn-check {
            border-radius: 20px;
            padding: 10px 20px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .progress-container {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
        }
        
        .result-details {
            font-size: 0.9em;
            margin-top: 10px;
        }
        
        .url-link {
            color: #2980b9;
            text-decoration: none;
            font-weight: 500;
        }
        
        .url-link:hover {
            color: #3498db;
            text-decoration: underline;
        }
        
        .spinner-border-sm {
            width: 1rem;
            height: 1rem;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'includes/navigation.php'; ?>
    
    <div class="container-fluid py-4">
        <div class="main-container">
            <!-- Header -->
            <div class="header-section">
                <h1 class="mb-3">
                    <i class="fas fa-server"></i>
                    aaPanel Account Checker
                </h1>
                <p class="mb-4">Kiểm tra kết nối và trạng thái tài khoản aaPanel</p>
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3 id="totalAccounts"><?= count($accounts) ?></h3>
                            <p class="mb-0">Tổng số tài khoản</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3 id="successCount">0</h3>
                            <p class="mb-0">Kết nối thành công</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3 id="failedCount">0</h3>
                            <p class="mb-0">Kết nối thất bại</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stats-card">
                            <h3 id="checkedCount">0</h3>
                            <p class="mb-0">Đã kiểm tra</p>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Controls -->
            <div class="p-4">
                <div class="row mb-4">
                    <div class="col-md-8">
                        <button id="checkAllBtn" class="btn btn-primary btn-check me-3">
                            <i class="fas fa-play"></i> Kiểm tra tất cả
                        </button>
                        <button id="rechecFailedBtn" class="btn btn-warning btn-check me-3" disabled>
                            <i class="fas fa-redo"></i> Kiểm tra lại lỗi
                        </button>
                        <button id="exportBtn" class="btn btn-success btn-check">
                            <i class="fas fa-download"></i> Xuất kết quả
                        </button>
                    </div>
                    <div class="col-md-4">
                        <div class="input-group">
                            <input type="text" id="searchInput" class="form-control" placeholder="Tìm kiếm theo URL hoặc username...">
                            <button id="searchBtn" class="btn btn-outline-secondary">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Progress Bar -->
                <div id="progressContainer" class="progress-container" style="display: none;">
                    <div class="d-flex justify-content-between mb-2">
                        <span>Tiến độ kiểm tra</span>
                        <span id="progressText">0/<?= count($accounts) ?></span>
                    </div>
                    <div class="progress">
                        <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" style="width: 0%"></div>
                    </div>
                </div>
                
                <!-- Account List -->
                <div id="accountsList">
                    <?php foreach ($accounts as $index => $account): ?>
                    <div class="account-card card" data-index="<?= $index ?>" data-url="<?= htmlspecialchars($account['url']) ?>" data-username="<?= htmlspecialchars($account['username']) ?>">
                        <div class="card-body">
                            <div class="row align-items-center">
                                <div class="col-md-6">
                                    <h6 class="card-title mb-1">
                                        <a href="<?= htmlspecialchars($account['url']) ?>" target="_blank" class="url-link">
                                            <?= htmlspecialchars($account['url']) ?>
                                        </a>
                                    </h6>
                                    <p class="text-muted mb-0">
                                        <i class="fas fa-user"></i> <?= htmlspecialchars($account['username']) ?>
                                    </p>
                                </div>
                                <div class="col-md-3">
                                    <span class="status-badge badge bg-secondary" id="status-<?= $index ?>">
                                        <i class="fas fa-question"></i> Chưa kiểm tra
                                    </span>
                                </div>
                                <div class="col-md-3 text-end">
                                    <button class="btn btn-sm btn-outline-primary check-single" data-index="<?= $index ?>">
                                        <i class="fas fa-play"></i> Kiểm tra
                                    </button>
                                </div>
                            </div>
                            <div class="result-details" id="details-<?= $index ?>" style="display: none;">
                                <!-- Chi tiết kết quả sẽ hiển thị ở đây -->
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let isChecking = false;
        let checkResults = {};
        
        // Kiểm tra một tài khoản
        async function checkSingleAccount(index) {
            const statusEl = document.getElementById(`status-${index}`);
            const detailsEl = document.getElementById(`details-${index}`);
            const btn = document.querySelector(`[data-index="${index}"].check-single`);
            
            // Reset UI
            statusEl.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang kiểm tra...';
            statusEl.className = 'status-badge badge bg-primary';
            btn.disabled = true;
            detailsEl.style.display = 'none';
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `action=check_account&index=${index}`
                });
                
                const result = await response.json();
                checkResults[index] = result;
                
                updateAccountStatus(index, result);
                updateStats();
                
            } catch (error) {
                console.error('Error:', error);
                statusEl.innerHTML = '<i class="fas fa-times"></i> Lỗi kiểm tra';
                statusEl.className = 'status-badge badge bg-danger';
            }
            
            btn.disabled = false;
        }
        
        // Cập nhật trạng thái tài khoản
        function updateAccountStatus(index, result) {
            const statusEl = document.getElementById(`status-${index}`);
            const detailsEl = document.getElementById(`details-${index}`);
            
            if (result.success) {
                statusEl.innerHTML = '<i class="fas fa-check"></i> Kết nối OK';
                statusEl.className = 'status-badge badge bg-success';
                
                const data = result.data;
                detailsEl.innerHTML = `
                    <div class="row text-muted">
                        <div class="col-md-6">
                            <small><strong>Host:</strong> ${data.host}:${data.port}</small><br>
                            <small><strong>Status:</strong> ${data.status_code}</small><br>
                            <small><strong>Response Time:</strong> ${data.response_time}</small>
                        </div>
                        <div class="col-md-6">
                            <small><strong>aaPanel Page:</strong> ${data.is_aapanel ? 'Yes' : 'No'}</small><br>
                            <small><strong>Login Form:</strong> ${data.login_info.login_form_found ? 'Found' : 'Not Found'}</small><br>
                            <small><strong>Size:</strong> ${data.response_size}</small>
                        </div>
                    </div>
                `;
                detailsEl.style.display = 'block';
            } else {
                statusEl.innerHTML = '<i class="fas fa-times"></i> Lỗi kết nối';
                statusEl.className = 'status-badge badge bg-danger';
                
                detailsEl.innerHTML = `
                    <div class="text-danger">
                        <small><strong>Error:</strong> ${result.error}</small><br>
                        ${result.details ? `<small><strong>Details:</strong> ${result.details}</small>` : ''}
                    </div>
                `;
                detailsEl.style.display = 'block';
            }
        }
        
        // Cập nhật thống kê
        function updateStats() {
            const total = Object.keys(checkResults).length;
            const success = Object.values(checkResults).filter(r => r.success).length;
            const failed = total - success;
            
            document.getElementById('checkedCount').textContent = total;
            document.getElementById('successCount').textContent = success;
            document.getElementById('failedCount').textContent = failed;
            
            // Enable recheck failed button if there are failed results
            document.getElementById('recheckFailedBtn').disabled = failed === 0;
        }
        
        // Kiểm tra tất cả
        async function checkAllAccounts() {
            if (isChecking) return;
            
            isChecking = true;
            checkResults = {};
            
            const checkAllBtn = document.getElementById('checkAllBtn');
            const progressContainer = document.getElementById('progressContainer');
            const progressBar = document.getElementById('progressBar');
            const progressText = document.getElementById('progressText');
            
            checkAllBtn.disabled = true;
            checkAllBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Đang kiểm tra...';
            progressContainer.style.display = 'block';
            
            const accountCards = document.querySelectorAll('.account-card');
            const total = accountCards.length;
            
            for (let i = 0; i < total; i++) {
                const index = accountCards[i].dataset.index;
                await checkSingleAccount(index);
                
                const progress = ((i + 1) / total) * 100;
                progressBar.style.width = `${progress}%`;
                progressText.textContent = `${i + 1}/${total}`;
                
                // Ngừng 500ms giữa các lần check
                if (i < total - 1) {
                    await new Promise(resolve => setTimeout(resolve, 500));
                }
            }
            
            isChecking = false;
            checkAllBtn.disabled = false;
            checkAllBtn.innerHTML = '<i class="fas fa-play"></i> Kiểm tra tất cả';
            progressContainer.style.display = 'none';
        }
        
        // Tìm kiếm
        function searchAccounts() {
            const searchTerm = document.getElementById('searchInput').value.toLowerCase();
            const accountCards = document.querySelectorAll('.account-card');
            
            accountCards.forEach(card => {
                const url = card.dataset.url.toLowerCase();
                const username = card.dataset.username.toLowerCase();
                
                if (url.includes(searchTerm) || username.includes(searchTerm)) {
                    card.style.display = 'block';
                } else {
                    card.style.display = 'none';
                }
            });
        }
        
        // Xuất kết quả
        function exportResults() {
            let csvContent = "URL,Username,Status,Host,Port,Response Time,Is aaPanel,Error\n";
            
            document.querySelectorAll('.account-card').forEach(card => {
                const index = card.dataset.index;
                const url = card.dataset.url;
                const username = card.dataset.username;
                const result = checkResults[index];
                
                if (result) {
                    if (result.success) {
                        const data = result.data;
                        csvContent += `"${url}","${username}","Success","${data.host}","${data.port}","${data.response_time}","${data.is_aapanel}",""\n`;
                    } else {
                        csvContent += `"${url}","${username}","Failed","","","","","${result.error}"\n`;
                    }
                } else {
                    csvContent += `"${url}","${username}","Not Checked","","","","",""\n`;
                }
            });
            
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const timestamp = new Date().toISOString().slice(0, 10);
            link.download = `aapanel_check_results_${timestamp}.csv`;
            link.href = URL.createObjectURL(blob);
            link.click();
        }
        
        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Check single account
            document.querySelectorAll('.check-single').forEach(btn => {
                btn.addEventListener('click', function() {
                    const index = this.dataset.index;
                    checkSingleAccount(index);
                });
            });
            
            // Check all accounts
            document.getElementById('checkAllBtn').addEventListener('click', checkAllAccounts);
            
            // Recheck failed
            document.getElementById('recheckFailedBtn').addEventListener('click', async function() {
                const failedIndexes = Object.keys(checkResults).filter(index => !checkResults[index].success);
                
                for (const index of failedIndexes) {
                    await checkSingleAccount(index);
                    await new Promise(resolve => setTimeout(resolve, 500));
                }
            });
            
            // Search
            document.getElementById('searchBtn').addEventListener('click', searchAccounts);
            document.getElementById('searchInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    searchAccounts();
                }
            });
            
            // Export
            document.getElementById('exportBtn').addEventListener('click', exportResults);
        });
    </script>
</body>
</html>