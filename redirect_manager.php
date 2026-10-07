// File đã bị xóa theo yêu cầu
<?php
/**
 * Domain Redirect Manager
 * Giao diện quản lý redirect từ nhiều domain đến domain đích
 */

require_once 'RedirectHandler.php';
require_once 'APISecretKeyManager.php';

// Authentication removed - direct access enabled
session_start();
// $keyManager = new APISecretKeyManager();
// $authResult = $keyManager->validateSession();

// Check authentication - DISABLED
// if (!$authResult['valid']) {
//     header('Location: aapanel_login.php');
//     exit;
// }

$redirectHandler = new RedirectHandler();

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'add_redirect':
            $sourceDomains = array_filter(array_map('trim', explode("\n", $_POST['source_domains'] ?? '')));
            $targetDomain = $_POST['target_domain'] ?? '';
            $redirectType = $_POST['redirect_type'] ?? '301';
            $includeSubdomain = isset($_POST['include_subdomain']);
            $pathHandling = $_POST['path_handling'] ?? 'preserve';
            $customPath = $_POST['custom_path'] ?? '';
            
            $result = $redirectHandler->addRedirect($sourceDomains, $targetDomain, $redirectType, $includeSubdomain, $pathHandling, $customPath);
            echo json_encode($result);
            exit;
            
        case 'update_redirect':
            $id = $_POST['id'] ?? '';
            $data = [
                'source_domains' => array_filter(array_map('trim', explode("\n", $_POST['source_domains'] ?? ''))),
                'target_domain' => $_POST['target_domain'] ?? '',
                'redirect_type' => $_POST['redirect_type'] ?? '301',
                'include_subdomain' => isset($_POST['include_subdomain']),
                'path_handling' => $_POST['path_handling'] ?? 'preserve',
                'custom_path' => $_POST['custom_path'] ?? ''
            ];
            
            $success = $redirectHandler->updateRedirect($id, $data);
            echo json_encode(['success' => $success, 'message' => $success ? 'Updated successfully' : 'Update failed']);
            exit;
            
        case 'delete_redirect':
            $id = $_POST['id'] ?? '';
            $success = $redirectHandler->deleteRedirect($id);
            echo json_encode(['success' => $success, 'message' => $success ? 'Deleted successfully' : 'Delete failed']);
            exit;
            
        case 'toggle_redirect':
            $id = $_POST['id'] ?? '';
            $success = $redirectHandler->toggleRedirect($id);
            echo json_encode(['success' => $success, 'message' => $success ? 'Status updated' : 'Update failed']);
            exit;
            
        case 'update_settings':
            $settings = [
                'redirect_type' => $_POST['default_redirect_type'] ?? '301',
                'enable_wildcard' => isset($_POST['enable_wildcard']),
                'enable_https_redirect' => isset($_POST['enable_https_redirect'])
            ];
            
            $success = $redirectHandler->updateSettings($settings);
            echo json_encode(['success' => $success, 'message' => $success ? 'Settings updated' : 'Update failed']);
            exit;
            
        case 'import_config':
            $jsonConfig = $_POST['config_data'] ?? '';
            $result = $redirectHandler->importConfig($jsonConfig);
            echo json_encode($result);
            exit;
    }
}

// Handle AJAX GET requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['ajax']) {
        case 'export_config':
            $config = $redirectHandler->exportConfig();
            echo $config;
            exit;
            
        case 'get_redirect':
            $id = $_GET['id'] ?? '';
            $redirect = $redirectHandler->getRedirectById($id);
            echo json_encode($redirect);
            exit;
            
        case 'stats':
            $stats = $redirectHandler->getStats();
            echo json_encode($stats);
            exit;
    }
}

$redirects = $redirectHandler->getAllRedirects();
$stats = $redirectHandler->getStats();
$settings = $redirectHandler->getSettings();
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Domain Redirect Manager</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        .stats-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .redirect-card {
            transition: transform 0.2s;
        }
        .redirect-card:hover {
            transform: translateY(-2px);
        }
        .domain-list {
            max-height: 150px;
            overflow-y: auto;
        }
        .badge-status {
            font-size: 0.8em;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-exchange-alt me-2"></i>Domain Redirect Manager
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="redirect_test.php">
                    <i class="fas fa-vial me-1"></i>Test Redirects
                </a>
                <a class="nav-link" href="check_301/">
                    <i class="fas fa-link me-1"></i>301 Chain Checker
                </a>
                    <i class="fas fa-tachometer-alt me-1"></i>aaPanel Manager
                </a>
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home me-1"></i>Dashboard
                </a>
                <a class="nav-link" href="?action=logout">
                    <i class="fas fa-sign-out-alt me-1"></i>Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3><?= $stats['total_redirects'] ?></h3>
                        <p class="mb-0"><i class="fas fa-list me-1"></i>Total Redirects</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3><?= $stats['enabled_redirects'] ?></h3>
                        <p class="mb-0"><i class="fas fa-check-circle me-1"></i>Active Redirects</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3><?= $stats['disabled_redirects'] ?></h3>
                        <p class="mb-0"><i class="fas fa-pause-circle me-1"></i>Disabled Redirects</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card">
                    <div class="card-body text-center">
                        <h3><?= number_format($stats['total_hits']) ?></h3>
                        <p class="mb-0"><i class="fas fa-mouse-pointer me-1"></i>Total Hits</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Action Buttons -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="d-flex flex-wrap gap-2">
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRedirectModal">
                        <i class="fas fa-plus me-1"></i>Add New Redirect
                    </button>
                    <button class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#settingsModal">
                        <i class="fas fa-cogs me-1"></i>Settings
                    </button>
                    <button class="btn btn-info" onclick="exportConfig()">
                        <i class="fas fa-download me-1"></i>Export Config
                    </button>
                    <button class="btn btn-warning" data-bs-toggle="modal" data-bs-target="#importModal">
                        <i class="fas fa-upload me-1"></i>Import Config
                    </button>
                    <button class="btn btn-success" onclick="generateHtaccess()">
                        <i class="fas fa-file-code me-1"></i>Generate .htaccess
                    </button>
                </div>
            </div>
        </div>

        <!-- Redirects Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-list me-2"></i>Redirect Rules</h5>
            </div>
            <div class="card-body">
                <?php if (empty($redirects)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                        <h5>No redirect rules found</h5>
                        <p class="text-muted">Click "Add New Redirect" to create your first redirect rule.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead class="table-dark">
                                <tr>
                                    <th>Source Domains</th>
                                    <th>Target Domain</th>
                                    <th>Type</th>
                                    <th>Path Handling</th>
                                    <th>Hits</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($redirects as $redirect): ?>
                                    <tr>
                                        <td>
                                            <div class="domain-list">
                                                <?php foreach ($redirect['source_domains'] as $domain): ?>
                                                    <span class="badge bg-secondary mb-1"><?= htmlspecialchars($domain) ?></span><br>
                                                <?php endforeach; ?>
                                                <?php if ($redirect['include_subdomain']): ?>
                                                    <small class="text-muted">
                                                        <i class="fas fa-sitemap me-1"></i>Including subdomains
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($redirect['target_domain']) ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-info"><?= $redirect['redirect_type'] ?></span>
                                        </td>
                                        <td>
                                            <?php
                                            $pathLabels = [
                                                'preserve' => '<i class="fas fa-arrow-right"></i> Preserve',
                                                'root' => '<i class="fas fa-home"></i> Root',
                                                'custom' => '<i class="fas fa-edit"></i> Custom'
                                            ];
                                            echo $pathLabels[$redirect['path_handling']] ?? $redirect['path_handling'];
                                            
                                            if ($redirect['path_handling'] === 'custom' && !empty($redirect['custom_path'])) {
                                                echo '<br><small class="text-muted">' . htmlspecialchars($redirect['custom_path']) . '</small>';
                                            }
                                            ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success"><?= number_format($redirect['hits'] ?? 0) ?></span>
                                        </td>
                                        <td>
                                            <?php if ($redirect['enabled']): ?>
                                                <span class="badge bg-success badge-status">
                                                    <i class="fas fa-check-circle me-1"></i>Active
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-danger badge-status">
                                                    <i class="fas fa-pause-circle me-1"></i>Disabled
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary" onclick="editRedirect('<?= $redirect['id'] ?>')">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-outline-warning" onclick="toggleRedirect('<?= $redirect['id'] ?>')">
                                                    <i class="fas fa-power-off"></i>
                                                </button>
                                                <button class="btn btn-outline-danger" onclick="deleteRedirect('<?= $redirect['id'] ?>')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add/Edit Redirect Modal -->
    <div class="modal fade" id="addRedirectModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="redirectForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Redirect</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_redirect">
                        <input type="hidden" name="id" id="edit_redirect_id">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Source Domains (one per line)</label>
                                    <textarea class="form-control" name="source_domains" id="source_domains" rows="5" required placeholder="example.com&#10;old-domain.com&#10;another-domain.net"></textarea>
                                    <small class="form-text text-muted">Enter one domain per line without http:// or https://</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Target Domain</label>
                                    <input type="text" class="form-control" name="target_domain" id="target_domain" required placeholder="new-domain.com">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Redirect Type</label>
                                    <select class="form-select" name="redirect_type" id="redirect_type">
                                        <option value="301">301 - Permanent Redirect</option>
                                        <option value="302">302 - Temporary Redirect</option>
                                        <option value="307">307 - Temporary Redirect (Preserve Method)</option>
                                        <option value="308">308 - Permanent Redirect (Preserve Method)</option>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="include_subdomain" id="include_subdomain">
                                        <label class="form-check-label" for="include_subdomain">
                                            Include Subdomains
                                        </label>
                                        <small class="form-text text-muted d-block">Redirect subdomains as well (e.g., www.example.com, blog.example.com)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-12">
                                <label class="form-label">Path Handling</label>
                                <div class="mb-3">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="path_handling" id="path_preserve" value="preserve" checked>
                                        <label class="form-check-label" for="path_preserve">
                                            Preserve original path (example.com/page → target.com/page)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="path_handling" id="path_root" value="root">
                                        <label class="form-check-label" for="path_root">
                                            Redirect to root (example.com/page → target.com/)
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="path_handling" id="path_custom" value="custom">
                                        <label class="form-check-label" for="path_custom">
                                            Redirect to custom path
                                        </label>
                                    </div>
                                </div>
                                
                                <div id="custom_path_group" style="display: none;">
                                    <input type="text" class="form-control" name="custom_path" id="custom_path" placeholder="/landing-page">
                                    <small class="form-text text-muted">Custom path to redirect to (e.g., /landing-page)</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Redirect</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div class="modal fade" id="settingsModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form id="settingsForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Redirect Settings</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_settings">
                        
                        <div class="mb-3">
                            <label class="form-label">Default Redirect Type</label>
                            <select class="form-select" name="default_redirect_type">
                                <option value="301" <?= $settings['redirect_type'] === '301' ? 'selected' : '' ?>>301 - Permanent</option>
                                <option value="302" <?= $settings['redirect_type'] === '302' ? 'selected' : '' ?>>302 - Temporary</option>
                                <option value="307" <?= $settings['redirect_type'] === '307' ? 'selected' : '' ?>>307 - Temporary (Preserve Method)</option>
                                <option value="308" <?= $settings['redirect_type'] === '308' ? 'selected' : '' ?>>308 - Permanent (Preserve Method)</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="enable_wildcard" id="enable_wildcard" <?= $settings['enable_wildcard'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="enable_wildcard">
                                    Enable Wildcard Matching
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="enable_https_redirect" id="enable_https_redirect" <?= $settings['enable_https_redirect'] ? 'checked' : '' ?>>
                                <label class="form-check-label" for="enable_https_redirect">
                                    Force HTTPS on Target Domain
                                </label>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Import Modal -->
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="importForm">
                    <div class="modal-header">
                        <h5 class="modal-title">Import Configuration</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="import_config">
                        
                        <div class="mb-3">
                            <label class="form-label">Configuration Data (JSON)</label>
                            <textarea class="form-control" name="config_data" rows="10" placeholder="Paste JSON configuration here..."></textarea>
                            <small class="form-text text-muted">This will replace your current configuration. Make sure to backup first!</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Import Configuration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        // Path handling radio button logic
        document.querySelectorAll('input[name="path_handling"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const customPathGroup = document.getElementById('custom_path_group');
                if (this.value === 'custom') {
                    customPathGroup.style.display = 'block';
                } else {
                    customPathGroup.style.display = 'none';
                }
            });
        });

        // Handle redirect form submission
        document.getElementById('redirectForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('redirect_manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Success!',
                        text: data.message,
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message
                    });
                }
            })
            .catch(error => {
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'An error occurred while processing your request.'
                });
            });
        });

        // Handle settings form submission
        document.getElementById('settingsForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            fetch('redirect_manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Settings Updated!',
                        showConfirmButton: false,
                        timer: 1500
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message
                    });
                }
            });
        });

        // Handle import form submission
        document.getElementById('importForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            
            Swal.fire({
                title: 'Are you sure?',
                text: 'This will replace your current configuration!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, import it!'
            }).then((result) => {
                if (result.isConfirmed) {
                    fetch('redirect_manager.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Imported!',
                                text: data.message,
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Import Failed!',
                                text: data.message
                            });
                        }
                    });
                }
            });
        });

        // Edit redirect function
        function editRedirect(id) {
            fetch(`redirect_manager.php?ajax=get_redirect&id=${id}`)
                .then(response => response.json())
                .then(redirect => {
                    if (redirect) {
                        document.querySelector('#addRedirectModal .modal-title').textContent = 'Edit Redirect';
                        document.querySelector('input[name="action"]').value = 'update_redirect';
                        document.getElementById('edit_redirect_id').value = redirect.id;
                        
                        document.getElementById('source_domains').value = redirect.source_domains.join('\n');
                        document.getElementById('target_domain').value = redirect.target_domain;
                        document.getElementById('redirect_type').value = redirect.redirect_type;
                        document.getElementById('include_subdomain').checked = redirect.include_subdomain;
                        
                        document.querySelector(`input[name="path_handling"][value="${redirect.path_handling}"]`).checked = true;
                        document.getElementById('custom_path').value = redirect.custom_path || '';
                        
                        // Trigger path handling change
                        const customPathGroup = document.getElementById('custom_path_group');
                        if (redirect.path_handling === 'custom') {
                            customPathGroup.style.display = 'block';
                        } else {
                            customPathGroup.style.display = 'none';
                        }
                        
                        const modal = new bootstrap.Modal(document.getElementById('addRedirectModal'));
                        modal.show();
                    }
                });
        }

        // Toggle redirect function
        function toggleRedirect(id) {
            const formData = new FormData();
            formData.append('action', 'toggle_redirect');
            formData.append('id', id);
            
            fetch('redirect_manager.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message
                    });
                }
            });
        }

        // Delete redirect function
        function deleteRedirect(id) {
            Swal.fire({
                title: 'Are you sure?',
                text: 'This redirect rule will be permanently deleted!',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, delete it!',
                confirmButtonColor: '#d33'
            }).then((result) => {
                if (result.isConfirmed) {
                    const formData = new FormData();
                    formData.append('action', 'delete_redirect');
                    formData.append('id', id);
                    
                    fetch('redirect_manager.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                icon: 'success',
                                title: 'Deleted!',
                                text: 'Redirect rule has been deleted.',
                                showConfirmButton: false,
                                timer: 1500
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                icon: 'error',
                                title: 'Error!',
                                text: data.message
                            });
                        }
                    });
                }
            });
        }

        // Export configuration function
        function exportConfig() {
            fetch('redirect_manager.php?ajax=export_config')
                .then(response => response.text())
                .then(data => {
                    const blob = new Blob([data], { type: 'application/json' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = 'redirect_config_' + new Date().toISOString().slice(0, 10) + '.json';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    
                    Swal.fire({
                        icon: 'success',
                        title: 'Config Exported!',
                        text: 'Configuration has been downloaded.',
                        showConfirmButton: false,
                        timer: 1500
                    });
                });
        }

        // Generate .htaccess function
        function generateHtaccess() {
            // This would generate .htaccess rules based on the current redirects
            fetch('redirect_manager.php?ajax=export_config')
                .then(response => response.json())
                .then(config => {
                    let htaccess = "# Generated by Domain Redirect Manager\n";
                    htaccess += "# " + new Date().toLocaleString() + "\n\n";
                    htaccess += "RewriteEngine On\n\n";
                    
                    if (config.redirects) {
                        config.redirects.forEach(redirect => {
                            if (redirect.enabled) {
                                redirect.source_domains.forEach(domain => {
                                    htaccess += `# Redirect from ${domain} to ${redirect.target_domain}\n`;
                                    let redirectCondition = `RewriteCond %{HTTP_HOST} ^(www\\.)?${domain.replace('.', '\\.')}$ [NC]\n`;
                                    
                                    let redirectRule = "";
                                    switch (redirect.path_handling) {
                                        case 'root':
                                            redirectRule = `RewriteRule ^.*$ http${config.settings?.enable_https_redirect ? 's' : ''}://${redirect.target_domain}/ [R=${redirect.redirect_type},L]\n`;
                                            break;
                                        case 'custom':
                                            const customPath = redirect.custom_path.startsWith('/') ? redirect.custom_path : '/' + redirect.custom_path;
                                            redirectRule = `RewriteRule ^.*$ http${config.settings?.enable_https_redirect ? 's' : ''}://${redirect.target_domain}${customPath} [R=${redirect.redirect_type},L]\n`;
                                            break;
                                        default:
                                            redirectRule = `RewriteRule ^(.*)$ http${config.settings?.enable_https_redirect ? 's' : ''}://${redirect.target_domain}/$1 [R=${redirect.redirect_type},L]\n`;
                                    }
                                    
                                    htaccess += redirectCondition + redirectRule + "\n";
                                });
                            }
                        });
                    }
                    
                    // Download the .htaccess file
                    const blob = new Blob([htaccess], { type: 'text/plain' });
                    const url = window.URL.createObjectURL(blob);
                    const a = document.createElement('a');
                    a.style.display = 'none';
                    a.href = url;
                    a.download = '.htaccess';
                    document.body.appendChild(a);
                    a.click();
                    window.URL.revokeObjectURL(url);
                    
                    Swal.fire({
                        icon: 'success',
                        title: '.htaccess Generated!',
                        text: 'The .htaccess file has been downloaded.',
                        showConfirmButton: false,
                        timer: 1500
                    });
                });
        }

        // Reset modal form when hiding
        document.getElementById('addRedirectModal').addEventListener('hidden.bs.modal', function() {
            document.querySelector('#addRedirectModal .modal-title').textContent = 'Add New Redirect';
            document.querySelector('input[name="action"]').value = 'add_redirect';
            document.getElementById('edit_redirect_id').value = '';
            document.getElementById('redirectForm').reset();
            document.getElementById('custom_path_group').style.display = 'none';
        });
    </script>
</body>
</html>