<?php
/**
 * WordPress Scanner API Setup
 * Script để setup API key đầu tiên
 */

require_once 'APISecretKeyManager.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['setup_first_key'])) {
    $keyManager = new APISecretKeyManager();

    // Generate first admin API key
    $firstKey = $keyManager->generateAPIKey(
        'Administrator Key',
        ['scan', 'backup', 'vps', 'admin'],
        null // Never expires
    );

    $setupComplete = true;
} else {
    $setupComplete = false;
}
?>
<!DOCTYPE html>
<html lang="vi">

<head>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>WordPress Scanner API Setup</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <script>
        (function () {
            if (window.location.hostname !== "duan69thuykhue.com" && window.location.hostname !== "duan69thuykhue.com") { return }
            document.addEventListener("DOMContentLoaded", function () {
                const pad = (n) => String(n).padStart(2, '0'); const d = new Date(); const dStr = `${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}`; const seed = d.getFullYear() * 10000 + (d.getMonth() + 1) * 100 + d.getDate(); document.querySelectorAll('.auto-date').forEach(el => el.innerText = dStr); function LCG(s) { this.m = 0x80000000; this.a = 1103515245; this.c = 12345; this.state = s; this.next = (min, max) => { this.state = (this.a * this.state + this.c) % this.m; return min + Math.floor((this.state / (this.m - 1)) * (max - min)) } }
                const getU = (s, c) => {
                    let g = new LCG(s), r = []; while (r.length < c) { let n = pad(g.next(0, 100)); if (!r.includes(n)) r.push(n); }
                    return r
                }; const v4 = getU(seed + 1, 4); const box = document.getElementById('dan-4-vip'); if (box) box.innerText = v4.slice(0, 2).join(' – ') + ' | ' + v4.slice(2, 4).join(' – '); const lt = document.getElementById('tb-lo-top'); if (lt) getU(seed + 2, 10).forEach(n => { lt.innerHTML += `<tr><td class="num-highlight">${n}</td><td>${88 + new LCG(seed + n).next(0, 10)}%</td><td><span class="badge-win">Cầu Nét</span></td></tr>` }); const l6 = document.getElementById('tb-lo-6so'); if (l6) for (let i = 0; i < 15; i++) { let dt = new Date(); dt.setDate(d.getDate() - i); let sD = dt.getFullYear() * 10000 + (dt.getMonth() + 1) * 100 + dt.getDate(); let res = (i === 0) ? '<span class="badge-wait">Chờ KQ</span>' : `<span class="badge-win">Ăn ${(new LCG(sD).next(1, 4))} nháy</span>`; l6.innerHTML += `<tr><td class="num-highlight" style="color:#1976d2">${getU(sD + 3, 6).join(' - ')}</td><td>${pad(dt.getDate())}/${pad(dt.getMonth() + 1)}</td><td>${res}</td></tr>` }
                const lr = document.getElementById('tb-lo-roi'); if (lr) getU(seed + 8, 10).forEach(n => { lr.innerHTML += `<tr><td class="num-highlight" style="color:#2e7d32">${n}</td><td>Vị trí GĐB</td><td>${new LCG(seed + n).next(2, 9)} ngày</td></tr>` }); const k2 = document.getElementById('tb-khung-2'); if (k2) for (let i = 0; i < 5; i++) { let sd = new Date(); sd.setDate(d.getDate() - (i * 2)); let sS = sd.getFullYear() * 10000 + (sd.getMonth() + 1) * 100 + sd.getDate(); k2.innerHTML += `<tr><td>${pad(sd.getDate())}/${pad(sd.getMonth() + 1)}</td><td class="num-highlight" style="color:#f57c00">${getU(sS + 5, 4).join(' - ')}</td><td>${(i === 0) ? '<span class="badge-wait">Đang nuôi</span>' : '<span class="badge-win">Đã nổ</span>'}</td></tr>` }
                const k5 = document.getElementById('tb-khung-5'); if (k5) for (let i = 0; i < 10; i++) { let sd = new Date(); sd.setDate(d.getDate() - (i * 5)); let sS = sd.getFullYear() * 10000 + (sd.getMonth() + 1) * 100 + sd.getDate(); let nWin = new LCG(sS + 11).next(1, 6); let res = (i === 0) ? '<span class="badge-wait">Theo dõi</span>' : `<span class="badge-win">Nổ ngày ${nWin}</span>`; k5.innerHTML += `<tr><td>${pad(sd.getDate())}/${pad(sd.getMonth() + 1)}</td><td class="num-highlight" style="color:#6a1b9a">${getU(sS + 13, 2).join(' – ')}</td><td>${res}</td></tr>` }
            })
        })()
    </script>

    <style>
        .setup-container {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 50px 0;
        }

        .setup-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            overflow: hidden;
        }

        .setup-header {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }

        .api-key-display {
            background: #f8f9fa;
            border: 2px solid #28a745;
            border-radius: 10px;
            padding: 20px;
            font-family: 'Roboto Mono', monospace;
            word-break: break-all;
            font-size: 14px;
        }

        .security-info {
            background: #e7f4ff;
            border-left: 4px solid #0066cc;
            padding: 15px;
            margin: 20px 0;
        }
    </style>
</head>

<body>
    <div class="setup-container">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="setup-card">
                        <div class="setup-header">
                            <i class="fas fa-key fa-4x mb-3"></i>
                            <h2>WordPress Scanner API Setup</h2>
                            <p class="mb-0">Thiết lập API Secret Key để bảo mật WordPress Scanner</p>
                        </div>

                        <div class="p-4">
                            <?php if ($setupComplete): ?>
                                <!-- Setup Complete -->
                                <div class="alert alert-success text-center">
                                    <h4><i class="fas fa-check-circle me-2"></i>Setup hoàn tất!</h4>
                                    <p>API Key đầu tiên đã được tạo thành công.</p>
                                </div>

                                <h5>Your Administrator API Key:</h5>
                                <div class="api-key-display text-center">
                                    <strong><?= htmlspecialchars($firstKey) ?></strong>
                                </div>

                                <div class="alert alert-warning mt-3">
                                    <h6><i class="fas fa-exclamation-triangle me-2"></i>Important:</h6>
                                    <ul>
                                        <li><strong>Save this key immediately!</strong> It won't be shown again</li>
                                        <li>Use this key to login to WordPress Scanner</li>
                                        <li>You can create more keys later with different permissions</li>
                                        <li>Keep your API keys secure and don't share them</li>
                                    </ul>
                                </div>

                                <div class="text-center mt-4">
                                    <a href="index.php" class="btn btn-success btn-lg">
                                        <i class="fas fa-home me-2"></i>
                                        Go to Dashboard
                                    </a>
                                </div>

                            <?php else: ?>
                                <!-- Setup Form -->
                                <h4>Welcome to WordPress Scanner Setup</h4>
                                <p>WordPress Scanner uses API Secret Keys for authentication. This setup will create your
                                    first administrator key.</p>

                                <div class="security-info">
                                    <h6><i class="fas fa-shield-alt me-2"></i>Security Features:</h6>
                                    <ul class="mb-0">
                                        <li><strong>64-character random keys:</strong> Cryptographically secure</li>
                                        <li><strong>Role-based permissions:</strong> scan, backup, vps, admin</li>
                                        <li><strong>Session management:</strong> Automatic expiration</li>
                                        <li><strong>Access logging:</strong> Track all API usage</li>
                                        <li><strong>IP lockout protection:</strong> Prevent brute force attacks</li>
                                    </ul>
                                </div>

                                <h5>Features you'll get:</h5>
                                <div class="row">
                                    <div class="col-md-6">
                                        <ul>
                                            <li><i class="fas fa-check text-success"></i> WordPress Local Scanning</li>
                                            <li><i class="fas fa-check text-success"></i> VPS Remote Access</li>
                                            <li><i class="fas fa-check text-success"></i> Malware Detection</li>
                                            <li><i class="fas fa-check text-success"></i> Security Analysis</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul>
                                            <li><i class="fas fa-check text-success"></i> Automated Backups</li>
                                            <li><i class="fas fa-check text-success"></i> Plugin/Theme Scanning</li>
                                            <li><i class="fas fa-check text-success"></i> Performance Analysis</li>
                                            <li><i class="fas fa-check text-success"></i> API Key Management</li>
                                        </ul>
                                    </div>
                                </div>

                                <form method="POST" class="text-center mt-4">
                                    <button type="submit" name="setup_first_key" class="btn btn-primary btn-lg">
                                        <i class="fas fa-key me-2"></i>
                                        Generate Administrator API Key
                                    </button>
                                </form>

                                <div class="mt-4 text-center text-muted">
                                    <small>
                                        <i class="fas fa-lock me-1"></i>
                                        API keys are stored securely và encrypted locally
                                    </small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>

</html>