<?php
/**
 * API Key Authentication Interface
 * Giao diện đăng nhập và quản lý API keys
 */

require_once 'APISecretKeyManager.php';

function showAPIAuthPage($error = null) {
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>WordPress Scanner - Authentication</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            .auth-container {
                min-height: 100vh;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .auth-card {
                background: white;
                border-radius: 15px;
                box-shadow: 0 15px 35px rgba(0,0,0,0.1);
                overflow: hidden;
                width: 100%;
                max-width: 400px;
            }
            .auth-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 30px;
                text-align: center;
            }
            .auth-body {
                padding: 30px;
            }
            .api-key-input {
                font-family: 'Roboto Mono', monospace;
                font-size: 14px;
            }
            .features-list {
                list-style: none;
                padding: 0;
            }
            .features-list li {
                padding: 8px 0;
                border-bottom: 1px solid #eee;
            }
            .features-list li:last-child {
                border-bottom: none;
            }
        </style>
    </head>
    <body>
        <div class="auth-container">
            <div class="auth-card">
                <div class="auth-header">
                    <i class="fas fa-shield-alt fa-3x mb-3"></i>
                    <h3>WordPress Scanner</h3>
                    <p class="mb-0">Secure API Authentication</p>
                </div>
                
                <div class="auth-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <?= htmlspecialchars($error) ?>
                    </div>
                    <?php endif; ?>
                    
                    <form method="POST" action="?action=wordpress&wp_action=auth" id="authForm">
                        <div class="mb-3">
                            <label for="api_key" class="form-label">
                                <i class="fas fa-key me-2"></i>
                                API Secret Key:
                            </label>
                            <input type="password" class="form-control api-key-input" id="api_key" name="api_key" 
                                   placeholder="wpsk_..." required autocomplete="off">
                            <div class="form-text">Enter your WordPress Scanner API key</div>
                        </div>
                        
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="checkbox" id="remember_session">
                            <label class="form-check-label" for="remember_session">
                                Keep me logged in (1 hour)
                            </label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary w-100 mb-3">
                            <i class="fas fa-sign-in-alt me-2"></i>
                            Authenticate & Continue
                        </button>
                    </form>
                    
                    <hr>
                    
                    <div class="text-center">
                        <h6 class="text-muted mb-3">What you can do with API key:</h6>
                        <ul class="features-list text-start">
                            <li><i class="fas fa-check text-success me-2"></i>Scan WordPress (Local & VPS)</li>
                            <li><i class="fas fa-check text-success me-2"></i>Backup & Download</li>
                            <li><i class="fas fa-check text-success me-2"></i>Security Analysis</li>
                            <li><i class="fas fa-check text-success me-2"></i>Remote VPS Management</li>
                        </ul>
                        
                        <div class="mt-3">
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="showAPIKeyInfo()">
                                <i class="fas fa-question-circle me-1"></i>
                                How to get API key?
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- API Key Info Modal -->
        <div class="modal fade" id="apiKeyInfoModal" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-key me-2"></i>
                            WordPress Scanner API Keys
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <h6>How to get an API Key:</h6>
                        <ol>
                            <li>Contact your administrator to generate an API key</li>
                            <li>API key format: <code>wpsk_[64_random_characters]</code></li>
                            <li>Each key has specific permissions (scan, backup, admin)</li>
                            <li>Keys can expire and be revoked</li>
                        </ol>
                        
                        <h6 class="mt-4">API Key Features:</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary">Security:</h6>
                                <ul>
                                    <li>64-character random keys</li>
                                    <li>Session-based authentication</li>
                                    <li>IP-based lockout protection</li>
                                    <li>Access logging & monitoring</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-success">Permissions:</h6>
                                <ul>
                                    <li><strong>scan:</strong> WordPress scanning</li>
                                    <li><strong>backup:</strong> Backup & download</li>
                                    <li><strong>vps:</strong> VPS remote access</li>
                                    <li><strong>admin:</strong> Key management</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="alert alert-info mt-3">
                            <strong>Note:</strong> API keys are never stored in plaintext and sessions automatically expire for security.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            function showAPIKeyInfo() {
                new bootstrap.Modal(document.getElementById('apiKeyInfoModal')).show();
            }
            
            // Auto-focus on API key input
            document.getElementById('api_key').focus();
            
            // Form validation
            document.getElementById('authForm').addEventListener('submit', function(e) {
                const apiKey = document.getElementById('api_key').value;
                if (!apiKey.startsWith('wpsk_') || apiKey.length < 20) {
                    e.preventDefault();
                    alert('Invalid API key format. Keys should start with "wpsk_" and be at least 20 characters long.');
                    return false;
                }
            });
        </script>
    </body>
    </html>
    <?php
}

function showAPIKeyManagement() {
    $keyManager = new APISecretKeyManager();
    $apiKeys = $keyManager->getAPIKeys();
    $stats = $keyManager->getSecurityStats();
    $logs = $keyManager->getAccessLogs(20);
    
    ?>
    <!DOCTYPE html>
    <html lang="vi">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>API Key Management</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <link href="assets/css/common.css" rel="stylesheet">
    </head>
    <body class="bg-light">
        <nav class="navbar navbar-expand-lg navbar-main">
            <div class="container">
                <a class="navbar-brand" href="?action=wordpress">
                    <i class="fas fa-shield-alt me-2"></i>
                    WordPress Scanner
                </a>
                <div class="navbar-nav ms-auto">
                    <a class="nav-link" href="?action=wordpress">Back to Scanner</a>
                    <a class="nav-link" href="?action=wordpress&wp_action=logout">Logout</a>
                </div>
            </div>
        </nav>

        <div class="container mt-4">
            <!-- Statistics Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-key text-primary fa-2x mb-2"></i>
                            <h5><?= $stats['total_keys'] ?></h5>
                            <p class="text-muted mb-0">Total Keys</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-check-circle text-success fa-2x mb-2"></i>
                            <h5><?= $stats['active_keys'] ?></h5>
                            <p class="text-muted mb-0">Active Keys</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-eye text-info fa-2x mb-2"></i>
                            <h5><?= $stats['recent_access_24h'] ?></h5>
                            <p class="text-muted mb-0">Access (24h)</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center">
                        <div class="card-body">
                            <i class="fas fa-exclamation-triangle text-warning fa-2x mb-2"></i>
                            <h5><?= $stats['failed_attempts'] ?></h5>
                            <p class="text-muted mb-0">Failed Attempts</p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <!-- API Keys Management -->
                <div class="col-lg-8">
                    <div class="card">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">API Keys</h5>
                            <button type="button" class="btn btn-primary btn-sm" onclick="showGenerateKeyModal()">
                                <i class="fas fa-plus me-1"></i>
                                Generate New Key
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Name</th>
                                            <th>Key</th>
                                            <th>Permissions</th>
                                            <th>Usage</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($apiKeys as $key): ?>
                                        <tr>
                                            <td><strong><?= htmlspecialchars($key['name']) ?></strong></td>
                                            <td><code><?= htmlspecialchars($key['key']) ?></code></td>
                                            <td>
                                                <?php foreach ($key['permissions'] as $perm): ?>
                                                    <span class="badge bg-secondary me-1"><?= htmlspecialchars($perm) ?></span>
                                                <?php endforeach; ?>
                                            </td>
                                            <td>
                                                <small>
                                                    Used: <?= $key['usage_count'] ?> times<br>
                                                    Last: <?= $key['last_used'] ?: 'Never' ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if ($key['active']): ?>
                                                    <span class="badge bg-success">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger">Disabled</span>
                                                <?php endif; ?>
                                                
                                                <?php if ($key['expires']): ?>
                                                    <br><small class="text-muted">Expires: <?= $key['expires'] ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <?php if ($key['active']): ?>
                                                    <a href="?action=wordpress&wp_action=api_manage&deactivate=<?= urlencode($key['full_key']) ?>" 
                                                       class="btn btn-warning btn-sm" onclick="return confirm('Deactivate this key?')">
                                                        <i class="fas fa-pause"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    <a href="?action=wordpress&wp_action=api_manage&delete=<?= urlencode($key['full_key']) ?>" 
                                                       class="btn btn-danger btn-sm" onclick="return confirm('Delete this key permanently?')">
                                                        <i class="fas fa-trash"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Access Logs -->
                <div class="col-lg-4">
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">Recent Access Logs</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($logs as $log): ?>
                            <div class="border-bottom pb-2 mb-2">
                                <div class="d-flex justify-content-between">
                                    <small><strong><?= htmlspecialchars($log['key_name']) ?></strong></small>
                                    <small class="text-muted"><?= date('H:i', strtotime($log['timestamp'])) ?></small>
                                </div>
                                <div class="text-muted small">
                                    IP: <?= htmlspecialchars($log['ip']) ?><br>
                                    Action: <?= htmlspecialchars($log['action']) ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Generate Key Modal -->
        <div class="modal fade" id="generateKeyModal" tabindex="-1">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Generate New API Key</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <form method="POST" action="?action=wordpress&wp_action=api_manage">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="key_name" class="form-label">Key Name:</label>
                                <input type="text" class="form-control" id="key_name" name="key_name" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Permissions:</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="perm_scan" name="permissions[]" value="scan" checked>
                                    <label class="form-check-label" for="perm_scan">WordPress Scanning</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="perm_backup" name="permissions[]" value="backup">
                                    <label class="form-check-label" for="perm_backup">Backup & Download</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="perm_vps" name="permissions[]" value="vps">
                                    <label class="form-check-label" for="perm_vps">VPS Remote Access</label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="perm_admin" name="permissions[]" value="admin">
                                    <label class="form-check-label" for="perm_admin">API Management</label>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="expires_in" class="form-label">Expires In:</label>
                                <select class="form-select" id="expires_in" name="expires_in">
                                    <option value="">Never</option>
                                    <option value="3600">1 Hour</option>
                                    <option value="86400">1 Day</option>
                                    <option value="604800">1 Week</option>
                                    <option value="2592000">1 Month</option>
                                </select>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="generate_key" class="btn btn-primary">Generate Key</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            function showGenerateKeyModal() {
                new bootstrap.Modal(document.getElementById('generateKeyModal')).show();
            }
        </script>
    </body>
    </html>
    <?php
}
?>