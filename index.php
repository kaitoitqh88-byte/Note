<?php
 

/**
 * Cloudflare PHP Project
 * Dự án PHP kết nối với Cloudflare API
 */

require_once 'config.php';
require_once 'CloudflareAPI.php';

// Include all handler files
require_once 'HomePageHandler.php';
require_once 'DashboardHandler.php';
require_once 'DNSHandler.php';
require_once 'CacheHandler.php';
require_once 'SearchHandler.php';
require_once 'SSLHandler.php';
require_once 'ConfigHandler.php';
require_once 'IDNHandler.php';
require_once 'APIStatsHandler.php';
require_once 'BackgroundHandler.php';
require_once 'VPSConnectionHandler.php';
require_once 'APISecretKeyManager.php';
require_once 'APIKeyAuthInterface.php';

// Khởi tạo Cloudflare API với cấu hình perPage tối ưu cho dashboard
try {
    // Sử dụng perPage = 75 để tối ưu hiệu suất hiển thị nhiều domains
    $cloudflare = new CloudflareAPI(null, null, 75);
    
    // Xử lý các action dựa vào request
    $action = $_GET['action'] ?? 'home';
    
    switch ($action) {
        case 'home':
            showHomepage($cloudflare);
            break;
            
        case 'dashboard':
            showDashboard($cloudflare);
            break;
            
        case 'dns':
            handleDNS($cloudflare);
            break;
            
            
        case 'cache':
            handleCache($cloudflare);
            break;
            
        case 'search':
            error_log("Index.php: Handling search action");
            try {
                handleSearch($cloudflare);
            } catch (Exception $e) {
                error_log("Index.php: Error in handleSearch: " . $e->getMessage());
                http_response_code(500);
                echo json_encode([
                    'success' => false, 
                    'error' => 'Search handler error: ' . $e->getMessage(),
                    'debug' => [
                        'file' => $e->getFile(),
                        'line' => $e->getLine()
                    ]
                ]);
            }
            break;
            
        case 'ssl':
            handleSSL($cloudflare);
            break;
            
        case 'https-check':
            handleHTTPSCheck($cloudflare);
            break;
            
        case 'https-test':
            handleHTTPSTest($cloudflare);
            break;
            
        case 'perpage-config':
            handlePerPageConfig($cloudflare);
            break;
            
        case 'idn-converter':
            handleIDNConverter();
            break;
            
        case 'cache-ops':
            handleCacheOperations($cloudflare);
            break;
            
        case 'api-stats':
            handleAPIStats($cloudflare);
            break;
            
        case 'background':
            handleBackgroundLoader($cloudflare);
            break;
            

        
        // Cache Management Actions
        case 'get_cache_stats':
            getCacheManagerStatistics();
            break;
            
        case 'clear_domain_cache':
            clearDomainCacheDemo();
            break;
            
        case 'warm_domain_cache':
            warmDomainCacheDemo($cloudflare);
            break;
            
        case 'cache_current_domains':
            cacheCurrentDomainsDemo($cloudflare);
            break;
            
        case 'view_cache_details':
            viewCacheDetailsDemo();
            break;
            
        case 'export_cache_data':
            exportCacheDataDemo();
            break;
            
        case 'import_cache_data':
            importCacheDataDemo();
            break;
            

            
        case 'cloudflare-dns':
            // Special handling for domain listing
            if (isset($_GET['_list_domains'])) {
                listCloudflaredomains();
            } elseif (isset($_GET['_debug'])) {
                debugCloudflareDNS();
            } else {
                getCloudflaresDNSRecords();
            }
            break;
            
        case 'api-key-manager':
            handleAPIKeyManager();
            break;
            
        case 'tools-menu':
            handleToolsMenu();
            break;
            
        default:
            showHomepage($cloudflare);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

/**
 * Handle API Key Manager
 */
function handleAPIKeyManager() {
    try {
        $apiManager = new APISecretKeyManager();
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            
            $action = $_POST['action'] ?? '';
            
            switch ($action) {
                case 'list_keys':
                    $reflection = new ReflectionClass($apiManager);
                    $loadDataMethod = $reflection->getMethod('loadData');
                    $loadDataMethod->setAccessible(true);
                    $data = $loadDataMethod->invoke($apiManager);
                    
                    echo json_encode([
                        'success' => true,
                        'data' => $data['api_keys'] ?? []
                    ]);
                    break;
                    
                case 'generate_key':
                    $name = $_POST['key_name'] ?? '';
                    $permissions = $_POST['permissions'] ?? ['scan'];
                    $expiresIn = !empty($_POST['expires_in']) ? (int)$_POST['expires_in'] * 24 * 60 * 60 : null;
                    
                    if (empty($name)) {
                        echo json_encode(['success' => false, 'error' => 'Key name required']);
                        break;
                    }
                    
                    $newKey = $apiManager->generateAPIKey($name, $permissions, $expiresIn);
                    
                    echo json_encode([
                        'success' => true,
                        'key' => $newKey,
                        'message' => 'API key generated successfully'
                    ]);
                    break;
                    
                case 'validate_key':
                    $apiKey = $_POST['api_key'] ?? '';
                    $permission = $_POST['permission'] ?? null;
                    
                    $result = $apiManager->validateAPIKey($apiKey, $permission);
                    
                    echo json_encode([
                        'success' => $result['valid'],
                        'data' => $result
                    ]);
                    break;
                    
                default:
                    echo json_encode(['success' => false, 'error' => 'Unknown action']);
            }
            exit;
        }
        
        // Show API Key Manager interface
        showAPIKeyManagerInterface();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'API Key Manager error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Handle Tools Menu
 */
function handleToolsMenu() {
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            header('Content-Type: application/json');
            
            $tool = $_POST['tool'] ?? '';
            
            switch ($tool) {
                case 'api_key_manager':
                    echo json_encode([
                        'success' => true,
                        'redirect' => '/?action=api-key-manager'
                    ]);
                    break;
                    
                    
                case 'dns_manager':
                    echo json_encode([
                        'success' => true,
                        'redirect' => '/?action=dns'
                    ]);
                    break;
                    
                    
                default:
                    echo json_encode(['success' => false, 'error' => 'Unknown tool']);
            }
            exit;
        }
        
        // Show Tools Menu interface
        showToolsMenuInterface();
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Tools Menu error: ' . $e->getMessage()
        ]);
    }
}

/**
 * Show API Key Manager web interface
 */
function showAPIKeyManagerInterface() {
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>🔐 API Key Manager</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container-fluid mt-4">
            <div class="row">
                <div class="col-12">
                    <h1 class="mb-4">🔐 API Key Manager</h1>
                    
                    <!-- Navigation -->
                    <nav class="mb-4">
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-outline-primary" onclick="showSection('list')">
                                <i class="fas fa-list"></i> List Keys
                            </button>
                            <button type="button" class="btn btn-outline-success" onclick="showSection('generate')">
                                <i class="fas fa-plus"></i> Generate Key
                            </button>
                            <button type="button" class="btn btn-outline-info" onclick="showSection('validate')">
                                <i class="fas fa-check"></i> Validate Key
                            </button>
                            <a href="/" class="btn btn-outline-secondary">
                                <i class="fas fa-home"></i> Home
                            </a>
                        </div>
                    </nav>
                    
                    <!-- List Keys Section -->
                    <div id="list-section" class="section" style="display:none;">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-list"></i> API Keys List</h5>
                            </div>
                            <div class="card-body">
                                <div id="keys-list">
                                    <div class="text-center">
                                        <div class="spinner-border" role="status">
                                            <span class="visually-hidden">Loading...</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Generate Key Section -->
                    <div id="generate-section" class="section" style="display:none;">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-plus"></i> Generate New API Key</h5>
                            </div>
                            <div class="card-body">
                                <form id="generate-form">
                                    <div class="mb-3">
                                        <label for="key-name" class="form-label">Key Name</label>
                                        <input type="text" class="form-control" id="key-name" name="key_name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="permissions" class="form-label">Permissions</label>
                                        <select multiple class="form-select" id="permissions" name="permissions[]">
                                            <option value="scan">Scan</option>
                                            <option value="backup">Backup</option>
                                            <option value="manage">Manage</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                        <div class="form-text">Hold Ctrl/Cmd to select multiple permissions</div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="expires-in" class="form-label">Expires In (days)</label>
                                        <input type="number" class="form-control" id="expires-in" name="expires_in" placeholder="Leave empty for no expiration">
                                    </div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-key"></i> Generate Key
                                    </button>
                                </form>
                                <div id="generate-result" class="mt-3"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Validate Key Section -->
                    <div id="validate-section" class="section" style="display:none;">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-check"></i> Validate API Key</h5>
                            </div>
                            <div class="card-body">
                                <form id="validate-form">
                                    <div class="mb-3">
                                        <label for="api-key" class="form-label">API Key</label>
                                        <input type="text" class="form-control" id="api-key" name="api_key" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="permission" class="form-label">Required Permission (optional)</label>
                                        <select class="form-select" id="permission" name="permission">
                                            <option value="">Any Permission</option>
                                            <option value="scan">Scan</option>
                                            <option value="backup">Backup</option>
                                            <option value="manage">Manage</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-info">
                                        <i class="fas fa-check-circle"></i> Validate
                                    </button>
                                </form>
                                <div id="validate-result" class="mt-3"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
        <script>
        function showSection(section) {
            // Hide all sections
            document.querySelectorAll('.section').forEach(el => el.style.display = 'none');
            
            // Show selected section
            document.getElementById(section + '-section').style.display = 'block';
            
            // Load data for the section
            if (section === 'list') {
                loadAPIKeys();
            }
        }
        
        function loadAPIKeys() {
            const listDiv = document.getElementById('keys-list');
            
            fetch('/?action=api-key-manager', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=list_keys'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    let html = '';
                    if (Object.keys(data.data).length === 0) {
                        html = '<div class="alert alert-info">No API keys found.</div>';
                    } else {
                        html = '<div class="table-responsive"><table class="table table-striped"><thead><tr><th>Name</th><th>Status</th><th>Created</th><th>Usage</th><th>Permissions</th></tr></thead><tbody>';
                        
                        Object.entries(data.data).forEach(([key, keyData]) => {
                            const status = keyData.active ? '<span class="badge bg-success">Active</span>' : '<span class="badge bg-danger">Disabled</span>';
                            const keyShort = '...' + key.slice(-8);
                            
                            html += `<tr>
                                <td><strong>${keyData.name}</strong><br><small class="text-muted">${keyShort}</small></td>
                                <td>${status}</td>
                                <td><small>${keyData.created}</small></td>
                                <td><span class="badge bg-info">${keyData.usage_count}</span></td>
                                <td><small>${keyData.permissions.join(', ')}</small></td>
                            </tr>`;
                        });
                        
                        html += '</tbody></table></div>';
                    }
                    listDiv.innerHTML = html;
                } else {
                    listDiv.innerHTML = '<div class="alert alert-danger">Error loading API keys: ' + data.error + '</div>';
                }
            })
            .catch(error => {
                listDiv.innerHTML = '<div class="alert alert-danger">Network error: ' + error + '</div>';
            });
        }
        
        // Handle generate form
        document.getElementById('generate-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const permissions = Array.from(document.getElementById('permissions').selectedOptions).map(option => option.value);
            
            const body = new URLSearchParams({
                action: 'generate_key',
                key_name: formData.get('key_name'),
                expires_in: formData.get('expires_in') || ''
            });
            
            // Add permissions
            permissions.forEach(perm => body.append('permissions[]', perm));
            
            fetch('/?action=api-key-manager', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body.toString()
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('generate-result');
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <h6>✅ API Key Generated Successfully!</h6>
                            <p><strong>Key:</strong> <code>${data.key}</code></p>
                            <p class="mb-0"><small class="text-muted">⚠️ Save this key securely. It won't be shown again.</small></p>
                        </div>
                    `;
                    this.reset();
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger">❌ Error: ${data.error}</div>`;
                }
            })
            .catch(error => {
                document.getElementById('generate-result').innerHTML = `<div class="alert alert-danger">Network error: ${error}</div>`;
            });
        });
        
        // Handle validate form
        document.getElementById('validate-form').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            const body = new URLSearchParams({
                action: 'validate_key',
                api_key: formData.get('api_key'),
                permission: formData.get('permission') || ''
            });
            
            fetch('/?action=api-key-manager', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: body.toString()
            })
            .then(response => response.json())
            .then(data => {
                const resultDiv = document.getElementById('validate-result');
                if (data.success) {
                    resultDiv.innerHTML = `
                        <div class="alert alert-success">
                            <h6>✅ Valid API Key</h6>
                            <p><strong>Key Name:</strong> ${data.data.key_name}</p>
                            <p><strong>Permissions:</strong> ${data.data.permissions.join(', ')}</p>
                        </div>
                    `;
                } else {
                    resultDiv.innerHTML = `<div class="alert alert-danger">❌ ${data.data.error}</div>`;
                }
            })
            .catch(error => {
                document.getElementById('validate-result').innerHTML = `<div class="alert alert-danger">Network error: ${error}</div>`;
            });
        });
        
        // Show list section by default
        showSection('list');
        </script>
    </body>
    </html>
    <?php
}

/**
 * Show Tools Menu web interface
 */
function showToolsMenuInterface() {
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <link rel="icon" type="image/x-icon" href="assets/favicon.ico">
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>🛠️ Tools Menu</title>
        <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    </head>
    <body>
        <div class="container-fluid mt-4">
            <div class="row">
                <div class="col-12">
                    <h1 class="mb-4">🛠️ Cloudflare Tools Management Center</h1>
                    
                    <div class="row">
                        <!-- Security & Auth Tools -->
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header bg-primary text-white">
                                    <h5><i class="fas fa-shield-alt"></i> Security & Authentication</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="/?action=api-key-manager" class="btn btn-outline-primary">
                                            <i class="fas fa-key"></i> API Key Manager
                                        </a>
                                        <button class="btn btn-outline-secondary" disabled>
                                            <i class="fas fa-lock"></i> Auth Dashboard (Soon)
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Cloudflare Management -->
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header bg-success text-white">
                                    <h5><i class="fas fa-cloud"></i> Cloudflare Management</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="/?action=dns" class="btn btn-outline-success">
                                            <i class="fas fa-globe"></i> DNS Management
                                        </a>
                                        <a href="/?action=cache" class="btn btn-outline-success">
                                            <i class="fas fa-database"></i> Cache Management
                                        </a>
                                        <a href="/?action=search" class="btn btn-outline-success">
                                            <i class="fas fa-search"></i> Domain Search
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Utilities -->
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card h-100">
                                <div class="card-header bg-info text-white">
                                    <h5><i class="fas fa-tools"></i> Utilities</h5>
                                </div>
                                <div class="card-body">
                                    <div class="d-grid gap-2">
                                        <a href="/?action=config" class="btn btn-outline-info">
                                            <i class="fas fa-cog"></i> Configuration
                                        </a>
                                        <a href="/cloudflare_security_tool.html" class="btn btn-outline-info">
                                            <i class="fas fa-rocket"></i> Standalone Tool
                                        </a>
                                        <button class="btn btn-outline-secondary" onclick="showSystemInfo()" 
                                            data-bs-toggle="modal" data-bs-target="#systemInfoModal">
                                            <i class="fas fa-info-circle"></i> System Info
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Links -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5><i class="fas fa-external-link-alt"></i> Quick Links</h5>
                        </div>
                        <div class="card-body">
                            <div class="btn-group me-2" role="group">
                                <a href="/" class="btn btn-outline-primary">
                                    <i class="fas fa-home"></i> Home
                                </a>
                                <a href="/?action=dashboard" class="btn btn-outline-primary">  
                                    <i class="fas fa-tachometer-alt"></i> Dashboard
                                </a>
                            </div>
                            <div class="btn-group" role="group">
                                <a href="/portable_launcher.php" class="btn btn-outline-secondary" target="_blank">
                                    <i class="fas fa-terminal"></i> CLI Launcher
                                </a>
                                <a href="/tools_menu.php" class="btn btn-outline-secondary" target="_blank">
                                    <i class="fas fa-terminal"></i> CLI Tools Menu
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- System Info Modal -->
        <div class="modal fade" id="systemInfoModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title"><i class="fas fa-info-circle"></i> System Information</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" id="systemInfoContent">
                        <div class="text-center">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.1.3/js/bootstrap.bundle.min.js"></script>
        <script>
        function showSystemInfo() {
            const content = document.getElementById('systemInfoContent');
            
            // Show system information
            content.innerHTML = `
                <div class="row">
                    <div class="col-md-6">
                        <h6><i class="fas fa-server"></i> Server Information</h6>
                        <table class="table table-sm">
                            <tr><td>PHP Version</td><td><?php echo PHP_VERSION; ?></td></tr>
                            <tr><td>Server OS</td><td><?php echo PHP_OS; ?></td></tr>
                            <tr><td>Current Time</td><td><?php echo date('Y-m-d H:i:s'); ?></td></tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <h6><i class="fas fa-file"></i> File Status</h6>
                        <table class="table table-sm">
                            <tr><td>Config File</td><td><?php echo file_exists('config.json') ? '✅ Exists' : '❌ Missing'; ?></td></tr>
                            <tr><td>API Manager</td><td><?php echo file_exists('APISecretKeyManager.php') ? '✅ Loaded' : '❌ Missing'; ?></td></tr>
                            <tr><td>Security Manager</td><td><?php echo file_exists('CloudflareSecurityRuleManager.php') ? '✅ Available' : '❌ Missing'; ?></td></tr>
                        </table>
                    </div>
                </div>
            `;
        }
        </script>
    </body>
    </html>
    <?php
}