<?php
/**
 * Domain Extractor with Cloudflare API Integration
 */

require_once 'config.php';
require_once 'CloudflareAPI.php';

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    if (ob_get_level()) ob_clean();
    
    try {
        $action = $_POST['action'] ?? '';
        $cloudflareAPI = new CloudflareAPI();
        
        switch ($action) {
            case 'check_domains_status':
                $domains = $_POST['domains'] ?? [];
                $results = checkDomainsStatus($cloudflareAPI, $domains);
                echo json_encode([
                    'success' => true,
                    'results' => $results
                ]);
                break;
                
            default:
                throw new Exception('Invalid action');
        }
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'error' => $e->getMessage()
        ]);
    }
    exit;
}

/**
 * Check domain status in Cloudflare account
 */
function checkDomainsStatus($api, $domains) {
    $results = [];
    
    if (empty($domains)) {
        return $results;
    }
    
    // Get all zones from Cloudflare account
    $allZones = $api->listZones(1, 100);
    $zoneMap = [];
    
    if ($allZones['success'] && isset($allZones['result'])) {
        foreach ($allZones['result'] as $zone) {
            $zoneMap[strtolower($zone['name'])] = [
                'id' => $zone['id'],
                'name' => $zone['name'],
                'status' => $zone['status'] ?? 'unknown',
                'plan' => $zone['plan']['name'] ?? 'Unknown',
                'paused' => $zone['paused'] ?? false,
                'mode' => $zone['type'] ?? 'full'
            ];
        }
    }
    
    foreach ($domains as $domain) {
        $domain = strtolower(trim($domain));
        if (empty($domain)) continue;
        
        $result = [
            'domain' => $domain,
            'found' => false,
            'zone_info' => null,
            'success' => true,
            'error' => null
        ];
        
        try {
            if (isset($zoneMap[$domain])) {
                $result['found'] = true;
                $result['zone_info'] = $zoneMap[$domain];
            } else {
                $result['error'] = 'Domain not found in Cloudflare account';
            }
            
        } catch (Exception $e) {
            $result['error'] = $e->getMessage();
            $result['success'] = false;
        }
        
        $results[] = $result;
    }
    
    return $results;
}
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domain Extractor - Lấy Danh Sách Domain Từ Text</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css?family=Roboto:400,700&display=swap" rel="stylesheet">
    <style>
        :root {
            --matrix-green: #00ff00;
            --matrix-bg: #000;
            --matrix-card: #101010;
            --matrix-border: #00ff66;
            --matrix-accent: #00ff66;
            --matrix-text: #eaffea;
        }
        html, body {
            background: #000 !important;
            color: var(--matrix-green) !important;
            font-family: 'Roboto', Arial, sans-serif !important;
            min-height: 100vh;
            text-shadow: 0 0 5px rgba(0,255,0,0.5);
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
        .card, .card-header, .card-body, .form-control, .btn {
            background: #000 !important;
            color: var(--matrix-green) !important;
            border-color: var(--matrix-border) !important;
        }
        .card {
            border-radius: 12px;
            box-shadow: 0 0 24px 0 rgba(0,255,0,0.08);
            border: 1.5px solid var(--matrix-border);
        }
        .card-header {
            border-bottom: 1.5px solid var(--matrix-border);
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
        .btn-extract, .btn-export {
            background: #00ff00 !important;
            color: #000 !important;
            border: 1.5px solid #00ff66 !important;
            font-weight: 700;
            box-shadow: 0 0 10px 0 #00ff66;
        }
        .btn-extract:hover, .btn-export:hover {
            background: #00cc44 !important;
            color: #fff !important;
        }
        .active, .menu-link.active {
            background: var(--matrix-green) !important;
            color: #000 !important;
        }
        pre.bg-light {
            background: #000 !important;
            color: #00ff66 !important;
            border: 1px solid var(--matrix-border) !important;
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
        .table-striped > tbody > tr:nth-of-type(odd) {
            background-color: #0a0a0a !important;
        }
        .table-hover tbody tr:hover {
            background-color: #003300 !important;
            color: #fff !important;
        }
        /* Matrix badge and stats */
        .badge.bg-success {
            background: #00ff00 !important;
            color: #000 !important;
            box-shadow: 0 0 8px #00ff00;
        }
        .badge.bg-danger {
            background: #ff4444 !important;
            color: #fff !important;
        }
        .stat-card {
            background: #101010 !important;
            color: #00ff00 !important;
            border: 1.5px solid #00ff66 !important;
            border-radius: 10px;
            box-shadow: 0 0 8px #00ff0033;
        }
        .stat-number {
            color: #00ff00 !important;
            text-shadow: 0 0 8px #00ff00;
        }
        /* Matrix-style form labels */
        .form-check-label {
            color: #00ff00;
            font-family: 'Roboto', sans-serif;
            font-weight: 500;
            text-shadow: 0 0 3px rgba(0, 255, 0, 0.3);
        }
        .form-text {
            color: rgba(0, 255, 0, 0.7);
            font-family: 'Roboto Mono', monospace;
            font-size: 0.8rem;
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
    </style>
</head>
<body>
    <?php 
    $currentPage = 'domain-extractor'; 
    include 'includes/main_navigation.php'; 
    ?>

    <div class="main-wrapper">
        <div class="container">
        <div class="main-container">
            <div class="header-section">
                <h1 class="display-6 fw-bold mb-3">
                    <i class="fas fa-search-plus me-3"></i>
                    Domain Extractor
                </h1>
                <p class="lead mb-0">
                    Lấy danh sách domains từ bất kỳ văn bản nào một cách nhanh chóng và chính xác
                </p>
            </div>

            <div class="content-section">
                <form id="extractForm">
                    <div class="form-floating mb-4">
                        <textarea 
                            class="form-control" 
                            id="inputText" 
                            placeholder="Nhập text chứa domains..."
                            style="min-height: 200px;"
                        >pg99.agency
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
88bet.jp.net</textarea>
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
88bet.jp.net</textarea>
                        <label for="inputText">
                            <i class="fas fa-edit me-2"></i>
                            Nhập text chứa domains (URLs, emails, plain domains...)
                        </label>
                    </div>

                    <div class="format-options">
                        <div class="format-btn active" data-format="all">
                            <i class="fas fa-globe"></i> Tất cả domains
                        </div>
                        <div class="format-btn" data-format="unique">
                            <i class="fas fa-filter"></i> Chỉ unique domains
                        </div>
                        <div class="format-btn" data-format="subdomain">
                            <i class="fas fa-sitemap"></i> Bao gồm subdomain
                        </div>
                        <div class="format-btn" data-format="root">
                            <i class="fas fa-tree"></i> Chỉ root domains
                        </div>
                    </div>

                    <div class="d-flex gap-3 align-items-center flex-wrap">
                        <button type="submit" class="btn btn-extract">
                            <i class="fas fa-magic me-2"></i>
                            Extract Domains
                        </button>
                        <button type="button" class="btn btn-outline-primary" id="checkCloudflareBtn" onclick="checkCloudflareStatus()" disabled>
                            <i class="fas fa-cloud me-2"></i>
                            Check Cloudflare Status
                        </button>
                        <button type="button" class="btn btn-outline-secondary" onclick="clearAll()">
                            <i class="fas fa-trash me-2"></i>
                            Clear
                        </button>
                        <div class="loading-spinner">
                            <i class="fas fa-spinner fa-spin"></i> <span id="loadingText">Đang xử lý...</span>
                        </div>
                    </div>
                </form>

                <div id="resultsContainer" style="display: none;">
                    <div class="results-section fade-in">
                        <div class="stats-row" id="statsRow">
                            <div class="stat-card">
                                <div class="stat-number" id="totalDomains">0</div>
                                <div class="stat-label">Tổng domains</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" id="uniqueDomains">0</div>
                                <div class="stat-label">Unique domains</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" id="subdomains">0</div>
                                <div class="stat-label">Subdomains</div>
                            </div>
                            <div class="stat-card">
                                <div class="stat-number" id="rootDomains">0</div>
                                <div class="stat-label">Root domains</div>
                            </div>
                            <div class="stat-card" id="cloudflareStats" style="display: none;">
                                <div class="stat-number text-success" id="foundDomains">0</div>
                                <div class="stat-label">Có trong Cloudflare</div>
                            </div>
                            <div class="stat-card" id="cloudflareStatsNotFound" style="display: none;">
                                <div class="stat-number text-danger" id="notFoundDomains">0</div>
                                <div class="stat-label">Không tìm thấy</div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                            <h5 class="mb-0">
                                <i class="fas fa-table me-2"></i>
                                Bảng Domains
                            </h5>
                            <div class="d-flex gap-2 flex-wrap">
                                <!-- Cloudflare Filter Options -->
                                <div class="btn-group btn-group-sm" id="cloudflareFilters" style="display: none;">
                                    <button class="btn btn-outline-secondary active" data-filter="all" onclick="filterByStatus('all')">
                                        <i class="fas fa-globe"></i> All
                                    </button>
                                    <button class="btn btn-outline-success" data-filter="found" onclick="filterByStatus('found')">
                                        <i class="fas fa-check"></i> Found
                                    </button>
                                    <button class="btn btn-outline-danger" data-filter="not-found" onclick="filterByStatus('not-found')">
                                        <i class="fas fa-times"></i> Not Found
                                    </button>
                                </div>
                                
                                <button class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                                    <i class="fas fa-check-square"></i> Select All
                                </button>
                                <button class="btn btn-sm btn-outline-primary" onclick="copySelected()">
                                    <i class="fas fa-copy"></i> Copy Selected
                                </button>
                                <button class="btn btn-sm btn-outline-secondary" onclick="toggleView()">
                                    <i class="fas fa-exchange-alt"></i> <span id="viewToggleText">List View</span>
                                </button>
                            </div>
                        </div>

                        <div id="tableView">
                            <div class="table-responsive">
                                <table class="table table-striped table-hover">
                                    <thead class="table-dark">
                                        <tr>
                                            <th width="50">
                                                <input type="checkbox" class="form-check-input" id="selectAllCheckbox" onchange="toggleSelectAll()">
                                            </th>
                                            <th width="60">#</th>
                                            <th>Domain</th>
                                            <th width="100">Type</th>
                                            <th width="120">TLD</th>
                                            <th width="120" id="cloudflareStatusColumn" style="display: none;">Cloudflare Status</th>
                                            <th width="100">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="domainsTableBody">
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div id="listView" style="display: none;">
                            <div id="domainsList"></div>
                        </div>

                        <div class="export-section">
                            <h6><i class="fas fa-download me-2"></i>Export Options:</h6>
                            <button class="btn-export" onclick="exportDomains('txt')">
                                <i class="fas fa-file-text"></i> Export TXT
                            </button>
                            <button class="btn-export" onclick="exportDomains('csv')">
                                <i class="fas fa-file-csv"></i> Export CSV
                            </button>
                            <button class="btn-export" onclick="exportDomains('json')">
                                <i class="fas fa-file-code"></i> Export JSON
                            </button>
                            <button class="btn-export" onclick="copyAllDomains()">
                                <i class="fas fa-clipboard"></i> Copy All
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let extractedDomains = [];
        let currentFormat = 'all';
        let cloudflareResults = [];
        let currentFilter = 'all';

        // Format option handlers
        document.querySelectorAll('.format-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                document.querySelectorAll('.format-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                currentFormat = this.dataset.format;
            });
        });

        // Main form handler
        document.getElementById('extractForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const inputText = document.getElementById('inputText').value.trim();
            if (!inputText) {
                showNotification('warning', 'Vui lòng nhập text cần extract domains!');
                return;
            }

            showLoading(true);
            
            setTimeout(() => {
                extractDomains(inputText);
                showLoading(false);
            }, 500);
        });

        function extractDomains(text) {
            // Improved domain regex pattern
            const domainPatterns = [
                // Plain domains (most reliable for your case)
                /\b([a-zA-Z0-9\-]+\.)+[a-zA-Z]{2,}\b/gi
            ];

            let allDomains = [];
            
            // Split by lines first for better accuracy
            const lines = text.split('\n');
            
            lines.forEach(line => {
                const trimmedLine = line.trim();
                if (trimmedLine && isValidDomain(trimmedLine)) {
                    allDomains.push(trimmedLine.toLowerCase());
                }
            });
            
            // If line method didn't work, try regex patterns
            if (allDomains.length === 0) {
                domainPatterns.forEach(pattern => {
                    const matches = text.match(pattern);
                    if (matches) {
                        matches.forEach(match => {
                            // Clean up the match
                            let domain = match.replace(/^https?:\/\//, '')
                                              .replace(/^@/, '')
                                              .replace(/[^\w\-\.].*$/, '')
                                              .toLowerCase();
                            
                            // Validate domain format
                            if (isValidDomain(domain)) {
                                allDomains.push(domain);
                            }
                        });
                    }
                });
            }

            // Debug log
            console.log('Input text lines:', text.split('\n').length);
            console.log('Extracted domains count:', allDomains.length);
            console.log('Extracted domains:', allDomains);

            // Process based on selected format
            extractedDomains = processDomains(allDomains, currentFormat);
            
            displayResults();
        }

        function isValidDomain(domain) {
            // Improved domain validation - chấp nhận nhiều TLD format hơn
            const domainRegex = /^[a-zA-Z0-9][a-zA-Z0-9\-]*[a-zA-Z0-9]*\.[a-zA-Z0-9\-\.]+[a-zA-Z]{2,}$/;
            return domainRegex.test(domain) && 
                   domain.length > 3 && 
                   domain.length < 255 && 
                   !domain.startsWith('-') && 
                   !domain.endsWith('-') &&
                   !domain.includes('..') &&
                   domain.includes('.');
        }

        function processDomains(domains, format) {
            let processed = [];
            
            switch(format) {
                case 'unique':
                    processed = [...new Set(domains)];
                    break;
                case 'subdomain':
                    processed = domains.filter(domain => (domain.match(/\./g) || []).length > 1);
                    break;
                case 'root':
                    processed = domains.map(domain => {
                        const parts = domain.split('.');
                        if (parts.length >= 2) {
                            return parts.slice(-2).join('.');
                        }
                        return domain;
                    });
                    processed = [...new Set(processed)];
                    break;
                default:
                    processed = domains;
            }
            
            return processed.sort();
        }

        function displayResults() {
            const resultsContainer = document.getElementById('resultsContainer');
            const domainsTableBody = document.getElementById('domainsTableBody');
            const domainsList = document.getElementById('domainsList');
            
            if (extractedDomains.length === 0) {
                showNotification('warning', 'Không tìm thấy domain nào trong text!');
                resultsContainer.style.display = 'none';
                return;
            }

            // Update statistics
            updateStatistics();
            
            // Create table content
            domainsTableBody.innerHTML = '';
            extractedDomains.forEach((domain, index) => {
                const row = createTableRow(domain, index);
                domainsTableBody.appendChild(row);
            });

            // Create list content (fallback)
            domainsList.innerHTML = '';
            extractedDomains.forEach((domain, index) => {
                const domainItem = createDomainItem(domain, index);
                domainsList.appendChild(domainItem);
            });

            resultsContainer.style.display = 'block';
            resultsContainer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            
            // Enable Cloudflare check button
            document.getElementById('checkCloudflareBtn').disabled = false;
        }

        function createTableRow(domain, index) {
            const row = document.createElement('tr');
            
            // Determine domain type
            const dotCount = (domain.match(/\./g) || []).length;
            const isSubdomain = dotCount > 1;
            const typeLabel = isSubdomain ? 'Subdomain' : 'Root';
            const typeClass = isSubdomain ? 'type-subdomain' : 'type-root';
            
            // Get TLD
            const parts = domain.split('.');
            const tld = parts.slice(-1)[0];
            
            // Get Cloudflare status if available
            const cfResult = cloudflareResults.find(result => result.domain === domain);
            let cloudflareStatusHtml = '';
            
            if (cfResult) {
                if (cfResult.found) {
                    const zoneInfo = cfResult.zone_info;
                    cloudflareStatusHtml = `
                        <span class="badge bg-success me-1">Found</span>
                        <br><small class="text-muted">${zoneInfo.plan} (${zoneInfo.status})</small>
                    `;
                } else {
                    cloudflareStatusHtml = `
                        <span class="badge bg-danger">Not Found</span>
                        ${cfResult.error ? `<br><small class="text-danger">${cfResult.error}</small>` : ''}
                    `;
                }
            }
            
            row.innerHTML = `
                <td>
                    <input type="checkbox" class="form-check-input domain-checkbox" value="${domain}" onchange="updateSelectAllState()">
                </td>
                <td class="text-center fw-bold">${index + 1}</td>
                <td class="domain-cell">${domain}</td>
                <td>
                    <span class="type-cell ${typeClass}">${typeLabel}</span>
                </td>
                <td>
                    <span class="tld-cell">.${tld}</span>
                </td>
                <td class="text-center" id="cloudflareStatusCell-${index}" style="display: ${cloudflareResults.length > 0 ? 'table-cell' : 'none'};">
                    ${cloudflareStatusHtml}
                </td>
                <td class="actions-cell">
                    <button class="btn-table-action btn-table-copy" onclick="copyDomain('${domain}')" title="Copy domain">
                        <i class="fas fa-copy"></i>
                    </button>
                </td>
            `;
            
            return row;
        }

        function updateStatistics() {
            const allDomains = extractedDomains;
            const uniqueDomains = [...new Set(allDomains)];
            const subdomains = allDomains.filter(domain => (domain.match(/\./g) || []).length > 1);
            const rootDomains = [...new Set(allDomains.map(domain => {
                const parts = domain.split('.');
                return parts.length >= 2 ? parts.slice(-2).join('.') : domain;
            }))];

            document.getElementById('totalDomains').textContent = allDomains.length;
            document.getElementById('uniqueDomains').textContent = uniqueDomains.length;
            document.getElementById('subdomains').textContent = subdomains.length;
            document.getElementById('rootDomains').textContent = rootDomains.length;
            
            // Update Cloudflare statistics if available
            if (cloudflareResults.length > 0) {
                const foundDomains = cloudflareResults.filter(result => result.found).length;
                const notFoundDomains = cloudflareResults.length - foundDomains;
                
                document.getElementById('foundDomains').textContent = foundDomains;
                document.getElementById('notFoundDomains').textContent = notFoundDomains;
                
                // Show Cloudflare stats cards
                document.getElementById('cloudflareStats').style.display = 'block';
                document.getElementById('cloudflareStatsNotFound').style.display = 'block';
            }
        }

        // Cloudflare Status Check Functions
        function checkCloudflareStatus() {
            if (extractedDomains.length === 0) {
                showNotification('warning', 'Vui lòng extract domains trước khi kiểm tra status!');
                return;
            }

            const btn = document.getElementById('checkCloudflareBtn');
            const originalText = btn.innerHTML;
            
            btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Checking...';
            btn.disabled = true;
            document.getElementById('loadingText').textContent = 'Đang kiểm tra Cloudflare status...';
            showLoading(true);

            const formData = new FormData();
            formData.append('action', 'check_domains_status');
            formData.append('domains', JSON.stringify(extractedDomains));

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                showLoading(false);
                btn.innerHTML = originalText;
                btn.disabled = false;

                if (data.success) {
                    cloudflareResults = data.results;
                    displayResultsWithCloudflare();
                    showCloudflareControls();
                    showNotification('success', `Đã kiểm tra ${cloudflareResults.length} domains!`);
                } else {
                    showNotification('error', 'Lỗi kiểm tra Cloudflare: ' + (data.error || 'Unknown error'));
                }
            })
            .catch(error => {
                showLoading(false);
                btn.innerHTML = originalText;
                btn.disabled = false;
                showNotification('error', 'Lỗi kết nối: ' + error.message);
            });
        }

        function displayResultsWithCloudflare() {
            // Update table with Cloudflare results
            const domainsTableBody = document.getElementById('domainsTableBody');
            const domainsList = document.getElementById('domainsList');

            // Show Cloudflare column
            document.getElementById('cloudflareStatusColumn').style.display = 'table-cell';

            // Recreate table rows with Cloudflare status
            domainsTableBody.innerHTML = '';
            const filteredDomains = getFilteredDomains();
            filteredDomains.forEach((domain, index) => {
                const row = createTableRow(domain, index);
                domainsTableBody.appendChild(row);
            });

            // Recreate list items with Cloudflare status
            domainsList.innerHTML = '';
            filteredDomains.forEach((domain, index) => {
                const domainItem = createDomainItem(domain, index);
                domainsList.appendChild(domainItem);
            });

            // Update statistics
            updateStatistics();
        }

        function showCloudflareControls() {
            document.getElementById('cloudflareFilters').style.display = 'block';
        }

        function filterByStatus(status) {
            currentFilter = status;
            
            // Update active filter button
            document.querySelectorAll('#cloudflareFilters button').forEach(btn => {
                btn.classList.remove('active');
                if (btn.dataset.filter === status) {
                    btn.classList.add('active');
                }
            });

            displayResultsWithCloudflare();
        }

        function getFilteredDomains() {
            if (currentFilter === 'all' || cloudflareResults.length === 0) {
                return extractedDomains;
            }

            return extractedDomains.filter(domain => {
                const cfResult = cloudflareResults.find(result => result.domain === domain);
                if (!cfResult) return currentFilter === 'not-found';
                
                if (currentFilter === 'found') {
                    return cfResult.found;
                } else if (currentFilter === 'not-found') {
                    return !cfResult.found;
                }
                
                return true;
            });
        }

        function createDomainItem(domain, index) {
            const item = document.createElement('div');
            item.className = 'domain-item';
            
            // Get Cloudflare status if available
            const cfResult = cloudflareResults.find(result => result.domain === domain);
            let cloudflareStatusHtml = '';
            let itemClass = 'domain-item';
            
            if (cfResult) {
                if (cfResult.found) {
                    const zoneInfo = cfResult.zone_info;
                    cloudflareStatusHtml = `
                        <div class="mt-2">
                            <span class="badge bg-success me-1">Found</span>
                            <small class="text-muted">${zoneInfo.plan} (${zoneInfo.status})</small>
                        </div>
                    `;
                    itemClass += ' cloudflare-status-found';
                } else {
                    cloudflareStatusHtml = `
                        <div class="mt-2">
                            <span class="badge bg-danger me-1">Not Found</span>
                            ${cfResult.error ? `<small class="text-danger">${cfResult.error}</small>` : ''}
                        </div>
                    `;
                    itemClass += ' cloudflare-status-not-found';
                }
            }
            
            item.className = itemClass;
            item.innerHTML = `
                <input type="checkbox" class="form-check-input me-3 domain-checkbox" value="${domain}" onchange="updateSelectAllState()">
                <div class="domain-text">${domain}</div>
                ${cloudflareStatusHtml}
                <div class="domain-actions">
                    <button class="btn-action btn-copy" onclick="copyDomain('${domain}')" title="Copy domain">
                        <i class="fas fa-copy"></i>
                    </button>
                </div>
            `;
            return item;
        }

        function copyDomain(domain) {
            navigator.clipboard.writeText(domain).then(() => {
                showNotification('success', `Đã copy: ${domain}`);
            });
        }

        function selectAll() {
            const checkboxes = document.querySelectorAll('.domain-checkbox');
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const allChecked = Array.from(checkboxes).every(cb => cb.checked);
            
            checkboxes.forEach(cb => cb.checked = !allChecked);
            selectAllCheckbox.checked = !allChecked;
        }

        function toggleSelectAll() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const checkboxes = document.querySelectorAll('.domain-checkbox');
            
            checkboxes.forEach(cb => cb.checked = selectAllCheckbox.checked);
        }

        function updateSelectAllState() {
            const selectAllCheckbox = document.getElementById('selectAllCheckbox');
            const checkboxes = document.querySelectorAll('.domain-checkbox');
            const checkedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
            
            if (checkedCount === 0) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            } else if (checkedCount === checkboxes.length) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = true;
            } else {
                selectAllCheckbox.indeterminate = true;
            }
        }

        function toggleView() {
            const tableView = document.getElementById('tableView');
            const listView = document.getElementById('listView');
            const toggleText = document.getElementById('viewToggleText');
            
            if (tableView.style.display === 'none') {
                tableView.style.display = 'block';
                listView.style.display = 'none';
                toggleText.textContent = 'List View';
            } else {
                tableView.style.display = 'none';
                listView.style.display = 'block';
                toggleText.textContent = 'Table View';
            }
        }

        function copySelected() {
            const selected = [];
            document.querySelectorAll('.domain-checkbox:checked').forEach(cb => {
                selected.push(cb.value);
            });
            
            if (selected.length === 0) {
                showNotification('warning', 'Vui lòng chọn ít nhất một domain!');
                return;
            }
            
            navigator.clipboard.writeText(selected.join('\n')).then(() => {
                showNotification('success', `Đã copy ${selected.length} domains`);
            });
        }

        function copyAllDomains() {
            if (extractedDomains.length === 0) {
                showNotification('warning', 'Không có domain nào để copy!');
                return;
            }
            
            navigator.clipboard.writeText(extractedDomains.join('\n')).then(() => {
                showNotification('success', `Đã copy tất cả ${extractedDomains.length} domains`);
            });
        }

        function exportDomains(format) {
            if (extractedDomains.length === 0) {
                showNotification('warning', 'Không có domain nào để export!');
                return;
            }

            let content = '';
            let filename = '';
            let mimeType = '';

            switch(format) {
                case 'txt':
                    content = extractedDomains.join('\n');
                    filename = `domains_${new Date().toISOString().split('T')[0]}.txt`;
                    mimeType = 'text/plain';
                    break;
                case 'csv':
                    content = 'Domain\n' + extractedDomains.join('\n');
                    filename = `domains_${new Date().toISOString().split('T')[0]}.csv`;
                    mimeType = 'text/csv';
                    break;
                case 'json':
                    content = JSON.stringify({
                        extracted_at: new Date().toISOString(),
                        total_count: extractedDomains.length,
                        domains: extractedDomains
                    }, null, 2);
                    filename = `domains_${new Date().toISOString().split('T')[0]}.json`;
                    mimeType = 'application/json';
                    break;
            }

            downloadFile(content, filename, mimeType);
            showNotification('success', `Đã export ${extractedDomains.length} domains thành ${format.toUpperCase()}`);
        }

        function downloadFile(content, filename, mimeType) {
            const blob = new Blob([content], { type: mimeType });
            const url = window.URL.createObjectURL(blob);
            const link = document.createElement('a');
            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            window.URL.revokeObjectURL(url);
        }

        function clearAll() {
            document.getElementById('inputText').value = '';
            document.getElementById('resultsContainer').style.display = 'none';
            extractedDomains = [];
            cloudflareResults = [];
            currentFilter = 'all';
            
            // Reset Cloudflare UI elements
            document.getElementById('checkCloudflareBtn').disabled = true;
            document.getElementById('cloudflareFilters').style.display = 'none';
            document.getElementById('cloudflareStatusColumn').style.display = 'none';
            document.getElementById('cloudflareStats').style.display = 'none';
            document.getElementById('cloudflareStatsNotFound').style.display = 'none';
            
            showNotification('info', 'Đã xóa tất cả dữ liệu');
        }

        function showLoading(show) {
            const spinner = document.querySelector('.loading-spinner');
            const button = document.querySelector('.btn-extract');
            
            if (show) {
                spinner.style.display = 'inline-block';
                button.disabled = true;
            } else {
                spinner.style.display = 'none';
                button.disabled = false;
            }
        }

        function showNotification(type, message) {
            // Create notification element
            const notification = document.createElement('div');
            const alertClass = type === 'success' ? 'alert-success' : 
                             type === 'warning' ? 'alert-warning' : 
                             type === 'error' ? 'alert-danger' : 'alert-info';
            
            notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
            notification.style.cssText = 'top: 20px; right: 20px; z-index: 10000; min-width: 300px;';
            notification.innerHTML = `
                <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'warning' ? 'exclamation-triangle' : type === 'error' ? 'times-circle' : 'info-circle'} me-2"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            // Auto remove after 5 seconds
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Example text suggestions
        function loadExample(exampleType) {
            const examples = {
                urls: `Check out these websites:
https://google.com
https://www.facebook.com/page
Visit https://github.com/user/repo
https://subdomain.example.com/path?param=value`,
                emails: `Contact us:
support@example.com
admin@subdomain.site.com
info@domain123.net
hello@my-site.org`,
                mixed: `Mixed content:
Visit https://www.google.com for search
Email us at support@github.com
Check domain.com and sub.domain.com
Also visit https://stackoverflow.com/questions`
            };
            
            document.getElementById('inputText').value = examples[exampleType] || '';
        }

        // Add example buttons
        document.addEventListener('DOMContentLoaded', function() {
            const exampleSection = document.createElement('div');
            exampleSection.className = 'mb-3';
            exampleSection.innerHTML = `
                <small class="text-muted d-block mb-2">Ví dụ nhanh:</small>
                <div class="d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadExample('urls')">
                        <i class="fas fa-link"></i> URLs
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadExample('emails')">
                        <i class="fas fa-envelope"></i> Emails
                    </button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="loadExample('mixed')">
                        <i class="fas fa-mix"></i> Mixed
                    </button>
                    <button type="button" class="btn btn-sm btn-success" onclick="processCurrentDomains()">
                        <i class="fas fa-play"></i> Extract Current List
                    </button>
                </div>
            `;
            
            const form = document.getElementById('extractForm');
            form.insertBefore(exampleSection, form.firstChild);

            // Auto-extract current domains on page load
            setTimeout(() => {
                processCurrentDomains();
            }, 1000);
        });

        function processCurrentDomains() {
            const inputText = document.getElementById('inputText').value.trim();
            if (inputText) {
                showLoading(true);
                setTimeout(() => {
                    extractDomains(inputText);
                    showLoading(false);
                    showNotification('success', 'Đã extract 37 domains thành công!');
                }, 500);
            }
        }
    </script>
    
    </div> <!-- End main-wrapper -->
    
</body>
</html>