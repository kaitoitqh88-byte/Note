<?php
/**
 * Cloudflare Debug Tool
 * Công cụ debug và kiểm tra cấu hình Cloudflare
 */

require_once 'config.php';
require_once 'CloudflareAPI.php';

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_POST['action']) {
            case 'test_config':
                $result = testCloudflareConfig();
                echo json_encode($result);
                break;
                
            case 'list_zones':
                $result = listAllZones();
                echo json_encode($result);
                break;
                
            case 'search_domain':
                $domain = $_POST['domain'] ?? '';
                $result = searchDomainInAccount($domain);
                echo json_encode($result);
                break;
                
            case 'verify_token':
                $result = verifyAPIToken();
                echo json_encode($result);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Invalid action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

function testCloudflareConfig() {
    try {
        // Check if config constants are defined
        if (!defined('CLOUDFLARE_EMAIL') || !defined('CLOUDFLARE_API_TOKEN')) {
            return [
                'success' => false,
                'error' => 'Cloudflare constants not defined',
                'details' => 'Check config.php file'
            ];
        }
        
        // Check if token file exists and is readable
        if (!file_exists(__DIR__ . '/token.txt')) {
            return [
                'success' => false,
                'error' => 'Token file not found',
                'details' => 'File token.txt does not exist'
            ];
        }
        
        $token = trim(file_get_contents(__DIR__ . '/token.txt'));
        if (empty($token)) {
            return [
                'success' => false,
                'error' => 'Token file is empty',
                'details' => 'Add your Cloudflare API token to token.txt'
            ];
        }
        
        // Test API connection
        $cloudflare = new CloudflareAPI();
        $result = $cloudflare->verifyToken();
        
        if ($result['success']) {
            return [
                'success' => true,
                'message' => 'Cloudflare configuration is working',
                'details' => [
                    'email' => CLOUDFLARE_EMAIL,
                    'token_length' => strlen($token),
                    'token_preview' => substr($token, 0, 8) . '...' . substr($token, -4),
                    'api_status' => 'Connected'
                ]
            ];
        } else {
            return [
                'success' => false,
                'error' => 'API connection failed',
                'details' => $result
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Configuration test failed: ' . $e->getMessage()
        ];
    }
}

function listAllZones() {
    try {
        $cloudflare = new CloudflareAPI();
        $zones = $cloudflare->listZones();
        
        if ($zones['success']) {
            $zoneList = [];
            foreach ($zones['result'] as $zone) {
                $zoneList[] = [
                    'id' => $zone['id'],
                    'name' => $zone['name'],
                    'status' => $zone['status'],
                    'plan' => $zone['plan']['name'] ?? 'Unknown',
                    'created_on' => $zone['created_on'] ?? '',
                    'name_servers' => $zone['name_servers'] ?? []
                ];
            }
            
            return [
                'success' => true,
                'total_zones' => count($zoneList),
                'zones' => $zoneList
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Failed to fetch zones',
                'details' => $zones['errors'] ?? []
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error listing zones: ' . $e->getMessage()
        ];
    }
}

function searchDomainInAccount($searchDomain) {
    try {
        $cloudflare = new CloudflareAPI();
        $zones = $cloudflare->listZones();
        
        if (!$zones['success']) {
            return [
                'success' => false,
                'error' => 'Failed to fetch zones for search',
                'details' => $zones['errors'] ?? []
            ];
        }
        
        $found = false;
        $matches = [];
        $allDomains = [];
        
        foreach ($zones['result'] as $zone) {
            $allDomains[] = $zone['name'];
            
            if (strtolower($zone['name']) === strtolower($searchDomain)) {
                $found = true;
                $matches[] = [
                    'id' => $zone['id'],
                    'name' => $zone['name'],
                    'status' => $zone['status'],
                    'plan' => $zone['plan']['name'] ?? 'Unknown',
                    'match_type' => 'exact'
                ];
            } elseif (strpos(strtolower($zone['name']), strtolower($searchDomain)) !== false) {
                $matches[] = [
                    'id' => $zone['id'],
                    'name' => $zone['name'],
                    'status' => $zone['status'],
                    'plan' => $zone['plan']['name'] ?? 'Unknown',
                    'match_type' => 'partial'
                ];
            }
        }
        
        return [
            'success' => true,
            'found' => $found,
            'search_domain' => $searchDomain,
            'matches' => $matches,
            'total_domains_in_account' => count($allDomains),
            'all_domains' => $allDomains
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Error searching domain: ' . $e->getMessage()
        ];
    }
}

function verifyAPIToken() {
    try {
        $cloudflare = new CloudflareAPI();
        $result = $cloudflare->verifyToken();
        
        if ($result['success']) {
            // Get user info if available
            $userInfo = $cloudflare->getUserDetails();
            
            return [
                'success' => true,
                'message' => 'API token is valid',
                'details' => [
                    'token_status' => 'Valid',
                    'user_info' => $userInfo['success'] ? $userInfo['result'] : null,
                    'permissions' => $result['result'] ?? null
                ]
            ];
        } else {
            return [
                'success' => false,
                'error' => 'Invalid API token',
                'details' => $result['errors'] ?? []
            ];
        }
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => 'Token verification failed: ' . $e->getMessage()
        ];
    }
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>[TACTICAL-CF] Debug Tool - System Diagnostics Console</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/rpg-shooter-theme.css" rel="stylesheet">
    <link href="assets/css/sidebar-navigation.css" rel="stylesheet">
</head>
<body>
    <?php 
    $currentPage = 'cloudflare-debug'; 
    include 'includes/main_navigation.php'; 
    ?>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loading-overlay">
        <div class="text-center text-primary">
            <div class="spinner-border mb-3" style="width: 3rem; height: 3rem;"></div>
            <h5>Running system diagnostics...</h5>
        </div>
    </div>

    <div class="container-fluid mt-4 hud-container">
        <!-- Tactical Command Header -->
        <div class="tactical-header">
            <div class="row align-items-center">
                <div class="col">
                    <h1><i class="fas fa-bug"></i> DEBUG CONSOLE</h1>
                    <p class="mb-0">System diagnostics and troubleshooting interface</p>
                </div>
                <div class="col-auto">
                    <button class="btn btn-primary btn-lg" onclick="runAllTests()">
                        <i class="fas fa-play"></i> EXECUTE DIAGNOSTICS
                    </button>
                </div>
            </div>
        </div>

        <!-- Error Alert -->
        <div class="alert alert-danger" role="alert">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle fa-2x me-3"></i>
                <div>
                    <h5 class="alert-heading mb-1">Domain Error Detected</h5>
                    <p class="mb-2"><strong>Error:</strong> Domain not found in Cloudflare account: <code>rr88-km.com</code></p>
                    <p class="mb-0">This tool will help diagnose the issue and provide solutions.</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Test Controls -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-flask"></i> Diagnostic Tests</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary" onclick="testConfig()">
                                <i class="fas fa-cog"></i> Test Configuration
                            </button>
                            <button class="btn btn-info" onclick="verifyToken()">
                                <i class="fas fa-key"></i> Verify API Token
                            </button>
                            <button class="btn btn-success" onclick="listZones()">
                                <i class="fas fa-list"></i> List All Domains
                            </button>
                            <button class="btn btn-warning" onclick="searchSpecificDomain()">
                                <i class="fas fa-search"></i> Search rr88-km.com
                            </button>
                        </div>
                        
                        <!-- Custom Domain Search -->
                        <div class="mt-4">
                            <label class="form-label">Search Custom Domain</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="custom-domain" placeholder="example.com">
                                <button class="btn btn-outline-primary" onclick="searchCustomDomain()">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Configuration Info -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle"></i> Current Config</h6>
                    </div>
                    <div class="card-body">
                        <p><strong>Email:</strong> <code><?= CLOUDFLARE_EMAIL ?></code></p>
                        <p><strong>API URL:</strong> <code><?= CLOUDFLARE_API_URL ?></code></p>
                        <p><strong>Token File:</strong> 
                            <?php if (file_exists(__DIR__ . '/token.txt')): ?>
                                <span class="badge bg-success">Found</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Missing</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Test Results -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-clipboard-list"></i> Diagnostic Results</h6>
                    </div>
                    <div class="card-body">
                        <div id="test-results">
                            <div class="text-center p-5 text-muted">
                                <i class="fas fa-play-circle fa-3x mb-3"></i>
                                <h5>Ready to diagnose</h5>
                                <p>Click "Run All Tests" or individual test buttons to start</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Solutions Section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-lightbulb"></i> Common Solutions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-plus text-success"></i> If Domain Not Found</h6>
                                <ol>
                                    <li>Add domain to your Cloudflare account</li>
                                    <li>Go to Cloudflare dashboard → Add a Site</li>
                                    <li>Enter domain name and follow setup wizard</li>
                                    <li>Update nameservers at your domain registrar</li>
                                    <li>Wait for DNS propagation (up to 24 hours)</li>
                                </ol>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-key text-warning"></i> If Token Issues</h6>
                                <ol>
                                    <li>Check API token permissions</li>
                                    <li>Ensure token has Zone:Read access</li>
                                    <li>Verify token isn't expired</li>
                                    <li>Check IP restrictions on token</li>
                                    <li>Create new token if needed</li>
                                </ol>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        async function runAllTests() {
            showLoading();
            const results = document.getElementById('test-results');
            results.innerHTML = '';

            try {
                // Test configuration
                await testConfig();
                await new Promise(resolve => setTimeout(resolve, 500));

                // Verify token
                await verifyToken();
                await new Promise(resolve => setTimeout(resolve, 500));

                // List zones
                await listZones();
                await new Promise(resolve => setTimeout(resolve, 500));

                // Search specific domain
                await searchSpecificDomain();
                
            } catch (error) {
                console.error('Error running tests:', error);
                showNotification('error', 'Error running tests: ' + error.message);
            } finally {
                hideLoading();
            }
        }

        async function testConfig() {
            try {
                showLoading();
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=test_config'
                });
                
                const data = await response.json();
                displayTestResult('Configuration Test', data);
                
            } catch (error) {
                displayTestResult('Configuration Test', {
                    success: false,
                    error: 'Test failed: ' + error.message
                });
            } finally {
                hideLoading();
            }
        }

        async function verifyToken() {
            try {
                showLoading();
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=verify_token'
                });
                
                const data = await response.json();
                displayTestResult('API Token Verification', data);
                
            } catch (error) {
                displayTestResult('API Token Verification', {
                    success: false,
                    error: 'Verification failed: ' + error.message
                });
            } finally {
                hideLoading();
            }
        }

        async function listZones() {
            try {
                showLoading();
                
                const response = await fetch('', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=list_zones'
                });
                
                const data = await response.json();
                displayZonesList(data);
                
            } catch (error) {
                displayTestResult('List Zones', {
                    success: false,
                    error: 'Failed to list zones: ' + error.message
                });
            } finally {
                hideLoading();
            }
        }

        async function searchSpecificDomain() {
            await searchDomain('rr88-km.com');
        }

        async function searchCustomDomain() {
            const domain = document.getElementById('custom-domain').value.trim();
            if (!domain) {
                showNotification('warning', 'Please enter a domain name');
                return;
            }
            await searchDomain(domain);
        }

        async function searchDomain(domain) {
            try {
                showLoading();
                
                const formData = new FormData();
                formData.append('action', 'search_domain');
                formData.append('domain', domain);
                
                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                displaySearchResults(domain, data);
                
            } catch (error) {
                displayTestResult(`Search "${domain}"`, {
                    success: false,
                    error: 'Search failed: ' + error.message
                });
            } finally {
                hideLoading();
            }
        }

        function displayTestResult(testName, result) {
            const results = document.getElementById('test-results');
            const statusClass = result.success ? 'status-success' : 'status-error';
            const icon = result.success ? 'fas fa-check-circle text-success' : 'fas fa-times-circle text-danger';
            
            const resultCard = document.createElement('div');
            resultCard.className = `test-card ${statusClass}`;
            
            let detailsHtml = '';
            if (result.details) {
                detailsHtml = `
                    <div class="error-details">
                        <strong>Details:</strong><br>
                        <pre>${JSON.stringify(result.details, null, 2)}</pre>
                    </div>
                `;
            }
            
            resultCard.innerHTML = `
                <div class="d-flex align-items-start">
                    <i class="${icon} fa-lg me-3 mt-1"></i>
                    <div class="flex-grow-1">
                        <h6 class="mb-2">${testName}</h6>
                        <p class="mb-1">${result.success ? (result.message || 'Test passed') : (result.error || 'Test failed')}</p>
                        ${detailsHtml}
                    </div>
                </div>
            `;
            
            results.appendChild(resultCard);
        }

        function displayZonesList(data) {
            const results = document.getElementById('test-results');
            const statusClass = data.success ? 'status-success' : 'status-error';
            const icon = data.success ? 'fas fa-check-circle text-success' : 'fas fa-times-circle text-danger';
            
            const resultCard = document.createElement('div');
            resultCard.className = `test-card ${statusClass}`;
            
            let zonesHtml = '';
            if (data.success && data.zones) {
                zonesHtml = `
                    <div class="mt-3">
                        <strong>Found ${data.total_zones} domains in your account:</strong>
                        <div class="results-container mt-2">
                            ${data.zones.map(zone => `
                                <div class="domain-card">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>${zone.name}</strong>
                                            <span class="badge bg-${zone.status === 'active' ? 'success' : 'warning'} ms-2">${zone.status}</span>
                                        </div>
                                        <small class="text-muted">${zone.plan}</small>
                                    </div>
                                </div>
                            `).join('')}
                        </div>
                    </div>
                `;
            }
            
            resultCard.innerHTML = `
                <div class="d-flex align-items-start">
                    <i class="${icon} fa-lg me-3 mt-1"></i>
                    <div class="flex-grow-1">
                        <h6 class="mb-2">List All Domains</h6>
                        <p class="mb-1">${data.success ? `Found ${data.total_zones} domains` : (data.error || 'Failed to list domains')}</p>
                        ${zonesHtml}
                    </div>
                </div>
            `;
            
            results.appendChild(resultCard);
        }

        function displaySearchResults(searchDomain, data) {
            const results = document.getElementById('test-results');
            const found = data.success && data.found;
            const statusClass = found ? 'status-success' : (data.success ? 'status-warning' : 'status-error');
            const icon = found ? 'fas fa-check-circle text-success' : (data.success ? 'fas fa-exclamation-triangle text-warning' : 'fas fa-times-circle text-danger');
            
            const resultCard = document.createElement('div');
            resultCard.className = `test-card ${statusClass}`;
            
            let searchHtml = '';
            if (data.success) {
                if (found) {
                    searchHtml = `
                        <div class="alert alert-success mt-3">
                            <strong>✅ Domain Found!</strong> ${searchDomain} is in your Cloudflare account.
                        </div>
                        ${data.matches.map(match => `
                            <div class="domain-card exact-match">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <strong>${match.name}</strong>
                                        <span class="badge bg-success ms-2">Found</span>
                                    </div>
                                    <small class="text-muted">${match.plan}</small>
                                </div>
                            </div>
                        `).join('')}
                    `;
                } else {
                    searchHtml = `
                        <div class="alert alert-warning mt-3">
                            <strong>⚠️ Domain Not Found!</strong> ${searchDomain} is not in your Cloudflare account.
                        </div>
                        <div class="mt-3">
                            <strong>Solutions:</strong>
                            <ol>
                                <li>Add the domain to your Cloudflare account</li>
                                <li>Check if the domain is spelled correctly</li>
                                <li>Verify your API token has access to this domain</li>
                                <li>Check if the domain was removed from Cloudflare</li>
                            </ol>
                        </div>
                        ${data.matches && data.matches.length > 0 ? `
                            <div class="mt-3">
                                <strong>Similar domains found:</strong>
                                ${data.matches.map(match => `
                                    <div class="domain-card partial-match">
                                        <strong>${match.name}</strong>
                                        <span class="badge bg-warning text-dark ms-2">Partial Match</span>
                                    </div>
                                `).join('')}
                            </div>
                        ` : ''}
                    `;
                }
            }
            
            resultCard.innerHTML = `
                <div class="d-flex align-items-start">
                    <i class="${icon} fa-lg me-3 mt-1"></i>
                    <div class="flex-grow-1">
                        <h6 class="mb-2">Search "${searchDomain}"</h6>
                        <p class="mb-1">${data.success ? (found ? 'Domain found in account' : 'Domain not found in account') : (data.error || 'Search failed')}</p>
                        ${searchHtml}
                    </div>
                </div>
            `;
            
            results.appendChild(resultCard);
        }

        function showLoading() {
            document.getElementById('loading-overlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loading-overlay').style.display = 'none';
        }

        function showNotification(type, message) {
            const notification = document.createElement('div');
            notification.className = `alert alert-${type === 'success' ? 'success' : type === 'warning' ? 'warning' : 'danger'} alert-dismissible fade show`;
            notification.style.position = 'fixed';
            notification.style.top = '20px';
            notification.style.right = '20px';
            notification.style.zIndex = '9999';
            notification.style.maxWidth = '400px';
            notification.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.remove();
                }
            }, 5000);
        }

        // Auto-run tests on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Run a quick config test on load
            setTimeout(testConfig, 1000);
        });
    </script>
    
    <!-- Tactical Gaming Interface -->
    <script src="assets/js/tactical-interface.js"></script>
    
</body>
</html>