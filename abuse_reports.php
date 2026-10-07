<?php
// abuse_reports.php - Lấy danh sách Abuse Reports từ Cloudflare API hoặc nguồn khác

// Nếu là AJAX POST thì trả về JSON, còn lại render UI
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['input'])) {
    header('Content-Type: application/json');
    $input = trim($_POST['input'] ?? '');
    if (empty($input)) {
        echo json_encode([
            'success' => false,
            'error' => 'Vui lòng nhập domain hoặc IP để tra cứu.'
        ]);
        exit;
    }
    // Lấy danh sách Abuse Reports từ Cloudflare API v4
    function getAbuseReportsFromCloudflare($input) {
        $api_url = 'https://dash.cloudflare.com/api/v4/accounts/3f0cec3e9be6f3a734e88ec8c4af812c/abuse-reports?page=1&per_page=25&sort=cdate%2Cdesc&mitigation_status=in_review&mitigation_status=active&mitigation_status=pending';
        $api_token = getenv('CF_API_TOKEN') ?: '';
        if (!$api_token) {
            return [ 'error' => 'Chưa cấu hình API Token (biến môi trường CF_API_TOKEN).' ];
        }
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $api_token,
            'Content-Type: application/json',
            'Accept: application/json'
        ]);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (curl_errno($ch)) {
            $err = curl_error($ch);
            curl_close($ch);
            return [ 'error' => 'Lỗi cURL: ' . $err ];
        }
        curl_close($ch);
        $data = json_decode($response, true);
        if ($httpcode !== 200 || !$data || !isset($data['result'])) {
            return [ 'error' => 'API trả về lỗi hoặc không hợp lệ.', 'debug' => $data ];
        }
        // Lọc theo input nếu có (domain hoặc IP)
        $filtered = array_filter($data['result'], function($r) use ($input) {
            return stripos($r['domain'] ?? '', $input) !== false || stripos($r['ip'] ?? '', $input) !== false;
        });
        return array_values($filtered);
    }
    $reports = getAbuseReportsFromCloudflare($input);
    if (isset($reports['error'])) {
        echo json_encode([
            'success' => false,
            'error' => $reports['error'],
            'debug' => $reports['debug'] ?? null
        ]);
        exit;
    }
    if (empty($reports)) {
        echo json_encode([
            'success' => true,
            'reports' => [],
            'message' => 'Không tìm thấy báo cáo abuse nào.'
        ]);
        exit;
    }
    echo json_encode([
        'success' => true,
        'reports' => $reports
    ]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abuse Reports Lookup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        html, body { height: 100%; margin: 0; padding: 0; }
        body { background: #181818; color: #00ff88; font-family: 'Roboto', Arial, sans-serif; min-height: 100vh; }
        .container { max-width: 100vw !important; width: 100vw !important; padding: 0; margin: 0; }
        .abuse-form {
            background: #222;
            border-radius: 0;
            padding: 3vw 5vw 2vw 5vw;
            margin: 0;
            box-shadow: none;
            min-height: 100vh;
            width: 100vw;
        }
        .abuse-form h3 { font-size: 2.2rem; margin-top: 1vw; }
        .abuse-form form { max-width: 600px; margin: 2vw 0 2vw 0; }
        .abuse-table th, .abuse-table td { color: #fff; font-size: 1.1rem; }
        .abuse-table th { background: #222; font-size: 1.15rem; }
        .abuse-table tr:nth-child(even) { background: #232323; }
        .badge-open { background: #ff3860; }
        .badge-closed { background: #23d160; }
        #abuse-default-list, #abuse-result { width: 100%; }
        @media (max-width: 900px) {
            .abuse-form { padding: 2vw 1vw; }
            .abuse-form form { max-width: 100%; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="abuse-form">
                        <h3 class="mb-3"><i class="fas fa-exclamation-triangle"></i> Abuse Reports Lookup</h3>
                        <div class="alert alert-info" style="background:#1a1a1a; color:#00ff88; border:1px solid #00ff88;">
                                <b>Hướng dẫn gửi Abuse Report qua API (Cloudflare):</b><br>
                                Cloudflare không cung cấp API public để lấy danh sách Abuse Reports, nhưng bạn có thể gửi report qua endpoint:<br>
                                <code>POST https://abuse.cloudflare.com/report</code><br>
                                <b>Tham số cần thiết:</b>
                                <ul>
                                        <li><b>email</b>: Email liên hệ của bạn</li>
                                        <li><b>type</b>: Loại abuse (copyright, trademark, phishing, malware, ...)</li>
                                        <li><b>domain</b>: Tên miền bị tố cáo</li>
                                        <li><b>url</b>: URL chi tiết (nếu có)</li>
                                        <li><b>evidence</b>: Mô tả chi tiết, bằng chứng</li>
                                </ul>
                                <b>Ví dụ gửi bằng cURL:</b>
                                <pre style="background:#222; color:#fff; padding:10px; border-radius:6px;">
curl -X POST https://abuse.cloudflare.com/report \
    -H "Content-Type: application/json" \
    -d '{
        "email": "your@email.com",
        "type": "phishing",
        "domain": "example.com",
        "url": "http://example.com/phishing",
        "evidence": "Trang này giả mạo ngân hàng."
    }'
                                </pre>
                                <b>Tham khảo thêm:</b> <a href="https://developers.cloudflare.com/fundamentals/reference/report-abuse/submit-report/" target="_blank" style="color:#00ff88;">Cloudflare Submit Abuse Report API</a>
                        </div>
            <form id="abuseLookupForm" autocomplete="off">
                <div class="mb-3">
                    <label for="abuse-input" class="form-label">Domain hoặc IP</label>
                    <input type="text" class="form-control" id="abuse-input" placeholder="Nhập domain hoặc IP" required>
                </div>
                <button type="submit" class="btn btn-primary"><i class="fas fa-search"></i> Tra cứu</button>
            </form>
            <div id="abuse-result" class="mt-4"></div>
            <script>
            // Hàm render bảng Abuse Reports
            function renderAbuseReportsTable(reports, resultDiv) {
                if (!reports || reports.length === 0) {
                    resultDiv.innerHTML = '<div class="alert alert-success">Không tìm thấy báo cáo abuse nào.</div>';
                    return;
                }
                let html = '<div class="table-responsive"><table class="table abuse-table"><thead><tr>' +
                    '<th>ID</th><th>Loại</th><th>Domain/IP</th><th>Ngày</th><th>Trạng thái</th><th>Chi tiết</th></tr></thead><tbody>';
                reports.forEach(r => {
                    const id = r.id || r.report_id || '';
                    const type = r.type || r.category || r.abuse_type || '';
                    const target = r.domain || r.ip || r.target || '';
                    const date = r.created_at || r.date || '';
                    const status = r.mitigation_status || r.status || '';
                    const details = r.details || r.description || r.reason || '';
                    html += `<tr><td>${id}</td><td>${type}</td><td>${target}</td><td>${date}</td>` +
                        `<td><span class="badge ${status === 'in_review' || status === 'active' || status === 'Open' ? 'badge-open' : 'badge-closed'}">${status}</span></td>` +
                        `<td>${details}</td></tr>`;
                });
                html += '</tbody></table></div>';
                resultDiv.innerHTML = html;
            }
            // Lấy danh sách Abuse Reports khi tải trang
            window.addEventListener('DOMContentLoaded', function() {
                const resultDiv = document.getElementById('abuse-result');
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'input='
                })
                .then(res => res.json())
                .then(data => {
                    if (!data.success) {
                        resultDiv.innerHTML = '<div class="alert alert-danger">' + (data.error || 'Lỗi không xác định') + '</div>';
                        return;
                    }
                    renderAbuseReportsTable(data.reports, resultDiv);
                })
                .catch(() => {
                    resultDiv.innerHTML = '<div class="alert alert-danger">Lỗi kết nối server.</div>';
                });
            });
            </script>
            <div id="abuse-default-list" class="mt-5">
                <h5 class="mb-3"><i class="fas fa-list"></i> Danh sách Abuse Reports mẫu</h5>
                <div class="table-responsive">
                    <table class="table abuse-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Loại</th>
                                <th>Target</th>
                                <th>Ngày</th>
                                <th>Trạng thái</th>
                                <th>Chi tiết</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>RPT-20240301-001</td>
                                <td>Phishing</td>
                                <td>example.com</td>
                                <td>2024-03-01</td>
                                <td><span class="badge badge-open">Open</span></td>
                                <td>Phát hiện nội dung lừa đảo trên domain.</td>
                            </tr>
                            <tr>
                                <td>RPT-20240215-002</td>
                                <td>Malware</td>
                                <td>malicious.net</td>
                                <td>2024-02-15</td>
                                <td><span class="badge badge-closed">Closed</span></td>
                                <td>Phát tán mã độc qua subdomain.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <script>
    // Ẩn danh sách mẫu khi có kết quả tra cứu
    document.getElementById('abuseLookupForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const input = document.getElementById('abuse-input').value.trim();
        const resultDiv = document.getElementById('abuse-result');
        document.getElementById('abuse-default-list').style.display = 'none';
        if (!input) {
            resultDiv.innerHTML = '<div class="alert alert-warning">Vui lòng nhập domain hoặc IP.</div>';
            return;
        }
        resultDiv.innerHTML = '<div class="text-info"><i class="fas fa-spinner fa-spin"></i> Đang tra cứu...</div>';
        fetch(window.location.href, {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'input=' + encodeURIComponent(input)
        })
        .then(res => res.json())
        .then(data => {
            if (!data.success) {
                resultDiv.innerHTML = '<div class="alert alert-danger">' + (data.error || 'Lỗi không xác định') + '</div>';
                return;
            }
            if (!data.reports || data.reports.length === 0) {
                resultDiv.innerHTML = '<div class="alert alert-success">Không tìm thấy báo cáo abuse nào.</div>';
                return;
            }
            let html = '<div class="table-responsive"><table class="table abuse-table"><thead><tr>' +
                '<th>ID</th><th>Loại</th><th>Domain/IP</th><th>Ngày</th><th>Trạng thái</th><th>Chi tiết</th></tr></thead><tbody>';
            data.reports.forEach(r => {
                // Map trường dữ liệu thực tế từ Cloudflare API
                const id = r.id || r.report_id || '';
                const type = r.type || r.category || r.abuse_type || '';
                const target = r.domain || r.ip || r.target || '';
                const date = r.created_at || r.date || '';
                const status = r.mitigation_status || r.status || '';
                const details = r.details || r.description || r.reason || '';
                html += `<tr><td>${id}</td><td>${type}</td><td>${target}</td><td>${date}</td>` +
                    `<td><span class="badge ${status === 'in_review' || status === 'active' || status === 'Open' ? 'badge-open' : 'badge-closed'}">${status}</span></td>` +
                    `<td>${details}</td></tr>`;
            });
            html += '</tbody></table></div>';
            resultDiv.innerHTML = html;
        })
        .catch(() => {
            resultDiv.innerHTML = '<div class="alert alert-danger">Lỗi kết nối server.</div>';
        });
    });
    </script>
</body>
</html>
