<?php
/**
 * Extract Domain Script
 * Lấy tất cả domains hợp lệ từ danh sách input
 */

// Danh sách domains từ input của user
$inputText = "pg99.agency
hb88s.cn.com
go99ii.com
debets.co.com
fabets.co.com
uk88s.co.com
vmn.uk.com
nmv.uk.com
ctn.uk.com
km-28bet.com
789p-km.com
mju.uk.com
789p-khuyenmai789k.com
ok365-km.com
nohu90-km.com
gk8.uk.com
lc8.uk.com
irr.eu.com
xlx.eu.com
kk558.online
okfun88.online
39betvn.vip
gstc.sa.com
rmnt.sa.com
8888.radio.am
betwinningedge.radio.am
aleahboothe.ru.com
0532edu.cn.com
789bettv.com
ry1y8a.sa.com
ruse-contor.sa.com
ru3gvs.sa.com
i-i.eu.com
nch.eu.com
be1.us.com
j5c.us.com
88bet.jp.net";

/**
 * Validate domain format
 */
function isValidDomain($domain) {
    // Trim whitespace
    $domain = trim($domain);
    
    // Basic domain validation regex
    $domainRegex = '/^[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?(\.[a-zA-Z0-9]([a-zA-Z0-9\-]*[a-zA-Z0-9])?)*\.[a-zA-Z]{2,}$/';
    
    return preg_match($domainRegex, $domain) && 
           strlen($domain) > 4 && 
           strlen($domain) < 255 && 
           !str_starts_with($domain, '-') && 
           !str_ends_with($domain, '-') &&
           !str_contains($domain, '..');
}

/**
 * Extract domains from text
 */
function extractDomainsFromText($text) {
    // Split by lines and filter
    $lines = array_filter(array_map('trim', explode("\n", $text)));
    
    $validDomains = [];
    $invalidDomains = [];
    $stats = [
        'total_input' => count($lines),
        'valid_domains' => 0,
        'invalid_domains' => 0,
        'unique_domains' => 0,
        'subdomains' => 0,
        'root_domains' => 0
    ];
    
    foreach ($lines as $line) {
        if (empty($line)) continue;
        
        if (isValidDomain($line)) {
            $validDomains[] = $line;
            $stats['valid_domains']++;
            
            // Count subdomains
            if (substr_count($line, '.') > 1) {
                $stats['subdomains']++;
            }
        } else {
            $invalidDomains[] = $line;
            $stats['invalid_domains']++;
        }
    }
    
    // Calculate unique domains
    $stats['unique_domains'] = count(array_unique($validDomains));
    
    // Calculate root domains
    $rootDomains = [];
    foreach ($validDomains as $domain) {
        $parts = explode('.', $domain);
        if (count($parts) >= 2) {
            $rootDomain = implode('.', array_slice($parts, -2));
            $rootDomains[] = $rootDomain;
        }
    }
    $stats['root_domains'] = count(array_unique($rootDomains));
    
    return [
        'valid_domains' => $validDomains,
        'invalid_domains' => $invalidDomains,
        'stats' => $stats,
        'root_domains' => array_unique($rootDomains)
    ];
}

/**
 * Export functions
 */
function exportAsText($domains) {
    return implode("\n", $domains);
}

function exportAsCsv($domains) {
    $csv = "Domain\n";
    foreach ($domains as $domain) {
        $csv .= $domain . "\n";
    }
    return $csv;
}

function exportAsJson($result) {
    return json_encode([
        'extracted_at' => date('Y-m-d H:i:s'),
        'statistics' => $result['stats'],
        'valid_domains' => $result['valid_domains'],
        'invalid_domains' => $result['invalid_domains'],
        'unique_root_domains' => array_values($result['root_domains'])
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
}

// Xử lý request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $inputData = $_POST['input_text'] ?? $inputText;
    $exportFormat = $_POST['export_format'] ?? 'json';
    
    $result = extractDomainsFromText($inputData);
    
    switch ($exportFormat) {
        case 'txt':
            header('Content-Type: text/plain');
            header('Content-Disposition: attachment; filename="extracted_domains.txt"');
            echo exportAsText($result['valid_domains']);
            break;
        case 'csv':
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="extracted_domains.csv"');
            echo exportAsCsv($result['valid_domains']);
            break;
        default:
            echo exportAsJson($result);
    }
    exit;
}

// Process the input text
$result = extractDomainsFromText($inputText);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domain Extraction Results</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Roboto', sans-serif;
        }
        .container {
            background: white;
            border-radius: 15px;
            margin: 2rem auto;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }
        .content {
            padding: 2rem;
        }
        .domain-item {
            background: #f8f9fa;
            border-left: 4px solid #28a745;
            padding: 10px 15px;
            margin-bottom: 5px;
            border-radius: 5px;
            font-family: 'Roboto Mono', monospace;
            transition: all 0.2s ease;
        }
        .domain-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }
        .invalid-item {
            border-left-color: #dc3545;
            background: #fff5f5;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            border: 1px solid #dee2e6;
        }
        .stat-number {
            font-size: 2rem;
            font-weight: bold;
            color: #667eea;
        }
        .btn-export {
            margin: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1><i class="fas fa-search-plus"></i> Kết Quả Extract Domains</h1>
            <p>Phân tích và validate các domains từ danh sách input</p>
        </div>
        
        <div class="content">
            <!-- Statistics -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?= $result['stats']['total_input'] ?></div>
                    <div>Tổng input</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number text-success"><?= $result['stats']['valid_domains'] ?></div>
                    <div>Valid domains</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number text-danger"><?= $result['stats']['invalid_domains'] ?></div>
                    <div>Invalid domains</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number text-info"><?= $result['stats']['unique_domains'] ?></div>
                    <div>Unique domains</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number text-warning"><?= $result['stats']['subdomains'] ?></div>
                    <div>Subdomains</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number text-primary"><?= $result['stats']['root_domains'] ?></div>
                    <div>Root domains</div>
                </div>
            </div>

            <!-- Export buttons -->
            <div class="mb-4">
                <h5><i class="fas fa-download"></i> Export Options:</h5>
                <button class="btn btn-success btn-export" onclick="exportDomains('txt')">
                    <i class="fas fa-file-text"></i> Export TXT
                </button>
                <button class="btn btn-info btn-export" onclick="exportDomains('csv')">
                    <i class="fas fa-file-csv"></i> Export CSV
                </button>
                <button class="btn btn-warning btn-export" onclick="exportDomains('json')">
                    <i class="fas fa-file-code"></i> Export JSON
                </button>
                <button class="btn btn-primary btn-export" onclick="copyAllDomains()">
                    <i class="fas fa-copy"></i> Copy All Valid
                </button>
            </div>

            <!-- Valid Domains -->
            <?php if (!empty($result['valid_domains'])): ?>
            <div class="mb-4">
                <h5 class="text-success">
                    <i class="fas fa-check-circle"></i> Valid Domains (<?= count($result['valid_domains']) ?>):
                </h5>
                <div id="validDomains">
                    <?php foreach ($result['valid_domains'] as $domain): ?>
                    <div class="domain-item" onclick="copyToClipboard('<?= htmlspecialchars($domain) ?>')">
                        <?= htmlspecialchars($domain) ?>
                        <i class="fas fa-copy float-end text-muted"></i>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Invalid Domains -->
            <?php if (!empty($result['invalid_domains'])): ?>
            <div class="mb-4">
                <h5 class="text-danger">
                    <i class="fas fa-times-circle"></i> Invalid Domains (<?= count($result['invalid_domains']) ?>):
                </h5>
                <div>
                    <?php foreach ($result['invalid_domains'] as $domain): ?>
                    <div class="domain-item invalid-item">
                        <?= htmlspecialchars($domain) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Unique Root Domains -->
            <div class="mb-4">
                <h5 class="text-primary">
                    <i class="fas fa-tree"></i> Unique Root Domains (<?= count($result['root_domains']) ?>):
                </h5>
                <div>
                    <?php foreach ($result['root_domains'] as $rootDomain): ?>
                    <div class="domain-item" onclick="copyToClipboard('<?= htmlspecialchars($rootDomain) ?>')">
                        <?= htmlspecialchars($rootDomain) ?>
                        <i class="fas fa-copy float-end text-muted"></i>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Back to extractor -->
            <div class="text-center">
                <a href="domain_extractor.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left"></i> Quay lại Domain Extractor
                </a>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(() => {
                showToast('Đã copy: ' + text);
            });
        }

        function copyAllDomains() {
            const validDomains = <?= json_encode($result['valid_domains']) ?>;
            const text = validDomains.join('\n');
            navigator.clipboard.writeText(text).then(() => {
                showToast('Đã copy tất cả ' + validDomains.length + ' domains!');
            });
        }

        function exportDomains(format) {
            const form = document.createElement('form');
            form.method = 'POST';
            form.style.display = 'none';
            
            const formatInput = document.createElement('input');
            formatInput.name = 'export_format';
            formatInput.value = format;
            
            const textInput = document.createElement('input');
            textInput.name = 'input_text';
            textInput.value = `<?= addslashes($inputText) ?>`;
            
            form.appendChild(formatInput);
            form.appendChild(textInput);
            document.body.appendChild(form);
            form.submit();
        }

        function showToast(message) {
            // Create toast element
            const toast = document.createElement('div');
            toast.className = 'position-fixed top-0 end-0 p-3';
            toast.style.zIndex = '10000';
            toast.innerHTML = `
                <div class="toast show" role="alert">
                    <div class="toast-header">
                        <i class="fas fa-info-circle text-primary me-2"></i>
                        <strong class="me-auto">Thông báo</strong>
                        <button type="button" class="btn-close" onclick="this.closest('.toast').parentNode.remove()"></button>
                    </div>
                    <div class="toast-body">
                        ${message}
                    </div>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            // Auto remove after 3 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 3000);
        }
    </script>
</body>
</html>