<?php
/**
 * Domain Redirect Test Interface
 * Giao diện test redirect rules trước khi áp dụng
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

$testResult = null;

// Handle test request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $testDomain = $_POST['test_domain'] ?? '';
    $testPath = $_POST['test_path'] ?? '/';
    $testProtocol = $_POST['test_protocol'] ?? 'https';
    
    if (!empty($testDomain)) {
        // Simulate redirect without actually redirecting
        $redirects = $redirectHandler->getAllRedirects();
        $matchedRule = null;
        $targetUrl = null;
        
        $requestDomain = strtolower(trim($testDomain));
        $requestDomain = preg_replace('#^https?://#i', '', $requestDomain);
        $requestDomain = preg_replace('#/$#', '', $requestDomain);
        
        foreach ($redirects as $redirect) {
            if (!$redirect['enabled']) {
                continue;
            }
            
            $shouldRedirect = false;
            
            // Check if domain matches
            foreach ($redirect['source_domains'] as $sourceDomain) {
                if ($requestDomain === strtolower($sourceDomain)) {
                    $shouldRedirect = true;
                    break;
                }
                
                // Check subdomain matching if enabled
                if ($redirect['include_subdomain']) {
                    if (str_ends_with($requestDomain, '.' . strtolower($sourceDomain))) {
                        $shouldRedirect = true;
                        break;
                    }
                }
            }
            
            if ($shouldRedirect) {
                $matchedRule = $redirect;
                
                // Build redirect URL
                $targetDomain = $redirect['target_domain'];
                $protocol = $redirect['enable_https_redirect'] ?? true ? 'https' : $testProtocol;
                
                switch ($redirect['path_handling']) {
                    case 'root':
                        $path = '/';
                        break;
                    case 'custom':
                        $path = '/' . ltrim($redirect['custom_path'], '/');
                        break;
                    case 'preserve':
                    default:
                        $path = $testPath ?: '/';
                        break;
                }
                
                $targetUrl = $protocol . '://' . $targetDomain . $path;
                break;
            }
        }
        
        $testResult = [
            'input' => [
                'domain' => $testDomain,
                'path' => $testPath,
                'protocol' => $testProtocol,
                'full_url' => $testProtocol . '://' . $requestDomain . $testPath
            ],
            'matched_rule' => $matchedRule,
            'target_url' => $targetUrl,
            'will_redirect' => !empty($matchedRule)
        ];
    }
}

$redirects = $redirectHandler->getAllRedirects();
$stats = $redirectHandler->getStats();
?>

<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirect Test Interface</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .test-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .result-success {
            background: linear-gradient(135deg, #56ab2f 0%, #a8e6cf 100%);
        }
        .result-none {
            background: linear-gradient(135deg, #ff6b6b 0%, #ffa8a8 100%);
        }
        .code-block {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            padding: 1rem;
            font-family: monospace;
            font-size: 0.9em;
        }
        .redirect-preview {
            background: #e9ecef;
            border-radius: 0.375rem;
            padding: 1rem;
            margin: 1rem 0;
        }
    </style>
</head>
<body class="bg-light">
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-vial me-2"></i>Redirect Test Interface
            </a>
            <div class="navbar-nav ms-auto">

                <a class="nav-link" href="check_301/">
                    <i class="fas fa-link me-1"></i>301 Chain Checker
                </a>
                    <i class="fas fa-tachometer-alt me-1"></i>aaPanel Manager
                </a>
                <a class="nav-link" href="index.php">
                    <i class="fas fa-home me-1"></i>Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Quick Stats -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card test-card">
                    <div class="card-body text-center">
                        <h4><i class="fas fa-vial me-2"></i>Test Your Redirect Rules</h4>
                        <p class="mb-0">Enter a domain and path to test if it would be redirected according to your current rules</p>
                        <small>Active Rules: <?= $stats['enabled_redirects'] ?> | Total Hits: <?= number_format($stats['total_hits']) ?></small>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Test Form -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-flask me-2"></i>Test Input</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Test Domain</label>
                                <input type="text" class="form-control" name="test_domain" 
                                       value="<?= htmlspecialchars($_POST['test_domain'] ?? '') ?>"
                                       placeholder="example.com"
                                       required>
                                <small class="form-text text-muted">Enter domain without http:// or https://</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Test Path</label>
                                <input type="text" class="form-control" name="test_path" 
                                       value="<?= htmlspecialchars($_POST['test_path'] ?? '/') ?>"
                                       placeholder="/page/about">
                                <small class="form-text text-muted">Path part of the URL (default: /)</small>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Protocol</label>
                                <select class="form-select" name="test_protocol">
                                    <option value="https" <?= ($_POST['test_protocol'] ?? 'https') === 'https' ? 'selected' : '' ?>>HTTPS</option>
                                    <option value="http" <?= ($_POST['test_protocol'] ?? '') === 'http' ? 'selected' : '' ?>>HTTP</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-play me-1"></i>Test Redirect
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Quick Test Buttons -->
                <?php if (!empty($redirects)): ?>
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-lightning me-2"></i>Quick Tests</h6>
                    </div>
                    <div class="card-body">
                        <p class="small text-muted">Click to quickly test your configured domains:</p>
                        <?php foreach ($redirects as $redirect): ?>
                            <?php if ($redirect['enabled']): ?>
                                <?php foreach (array_slice($redirect['source_domains'], 0, 3) as $domain): ?>
                                    <button class="btn btn-sm btn-outline-secondary me-1 mb-1" 
                                            onclick="quickTest('<?= htmlspecialchars($domain) ?>', '/')">
                                        <?= htmlspecialchars($domain) ?>
                                    </button>
                                <?php endforeach; ?>
                                <?php if (count($redirect['source_domains']) > 3): ?>
                                    <small class="text-muted">... and <?= count($redirect['source_domains']) - 3 ?> more</small>
                                <?php endif; ?>
                                <br>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Test Result -->
            <div class="col-md-6">
                <?php if ($testResult): ?>
                    <div class="card">
                        <div class="card-header <?= $testResult['will_redirect'] ? 'bg-success text-white' : 'bg-danger text-white' ?>">
                            <h5 class="mb-0">
                                <i class="fas <?= $testResult['will_redirect'] ? 'fa-check-circle' : 'fa-times-circle' ?> me-2"></i>
                                Test Result: <?= $testResult['will_redirect'] ? 'Will Redirect' : 'No Redirect' ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <!-- Input Summary -->
                            <div class="mb-3">
                                <h6><i class="fas fa-info-circle me-2"></i>Test Input</h6>
                                <div class="code-block">
                                    <strong>URL:</strong> <?= htmlspecialchars($testResult['input']['full_url']) ?><br>
                                    <strong>Domain:</strong> <?= htmlspecialchars($testResult['input']['domain']) ?><br>
                                    <strong>Path:</strong> <?= htmlspecialchars($testResult['input']['path']) ?><br>
                                    <strong>Protocol:</strong> <?= htmlspecialchars($testResult['input']['protocol']) ?>
                                </div>
                            </div>

                            <?php if ($testResult['will_redirect']): ?>
                                <!-- Redirect Details -->
                                <div class="mb-3">
                                    <h6><i class="fas fa-arrow-right me-2"></i>Redirect Details</h6>
                                    <div class="redirect-preview">
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <span class="text-muted">FROM:</span>
                                            <strong><?= htmlspecialchars($testResult['input']['full_url']) ?></strong>
                                        </div>
                                        <div class="text-center mb-2">
                                            <i class="fas fa-arrow-down text-success"></i>
                                        </div>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="text-muted">TO:</span>
                                            <strong class="text-success"><?= htmlspecialchars($testResult['target_url']) ?></strong>
                                        </div>
                                    </div>
                                </div>

                                <!-- Matched Rule -->
                                <div class="mb-3">
                                    <h6><i class="fas fa-cogs me-2"></i>Matched Rule</h6>
                                    <div class="code-block">
                                        <strong>Rule ID:</strong> <?= htmlspecialchars($testResult['matched_rule']['id']) ?><br>
                                        <strong>Source Domains:</strong> <?= implode(', ', array_map('htmlspecialchars', $testResult['matched_rule']['source_domains'])) ?><br>
                                        <strong>Target Domain:</strong> <?= htmlspecialchars($testResult['matched_rule']['target_domain']) ?><br>
                                        <strong>Redirect Type:</strong> <?= htmlspecialchars($testResult['matched_rule']['redirect_type']) ?><br>
                                        <strong>Path Handling:</strong> <?= htmlspecialchars($testResult['matched_rule']['path_handling']) ?>
                                        <?php if ($testResult['matched_rule']['path_handling'] === 'custom' && !empty($testResult['matched_rule']['custom_path'])): ?>
                                            (<?= htmlspecialchars($testResult['matched_rule']['custom_path']) ?>)
                                        <?php endif; ?><br>
                                        <strong>Include Subdomain:</strong> <?= $testResult['matched_rule']['include_subdomain'] ? 'Yes' : 'No' ?><br>
                                        <strong>Hits:</strong> <?= number_format($testResult['matched_rule']['hits'] ?? 0) ?>
                                    </div>
                                </div>

                                <!-- HTTP Response -->
                                <div class="mb-3">
                                    <h6><i class="fas fa-code me-2"></i>Expected HTTP Response</h6>
                                    <div class="code-block">
                                        HTTP/1.1 <?= htmlspecialchars($testResult['matched_rule']['redirect_type']) ?> 
                                        <?php
                                        $codes = [
                                            '301' => 'Moved Permanently',
                                            '302' => 'Found',
                                            '307' => 'Temporary Redirect',
                                            '308' => 'Permanent Redirect'
                                        ];
                                        echo $codes[$testResult['matched_rule']['redirect_type']] ?? 'Redirect';
                                        ?><br>
                                        Location: <?= htmlspecialchars($testResult['target_url']) ?>
                                    </div>
                                </div>
                                
                            <?php else: ?>
                                <!-- No Match -->
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <strong>No redirect rule matches this domain.</strong><br>
                                    The request would continue to the original content or result in a 404 error.
                                </div>
                                
                                <div class="mb-3">
                                    <h6><i class="fas fa-list me-2"></i>Available Rules</h6>
                                    <?php if (empty($redirects)): ?>
                                        <p class="text-muted">No redirect rules configured.</p>
                                    <?php else: ?>
                                        <ul class="list-group list-group-flush">
                                            <?php foreach (array_filter($redirects, fn($r) => $r['enabled']) as $redirect): ?>
                                                <li class="list-group-item d-flex justify-content-between">
                                                    <span>
                                                        <strong><?= implode(', ', array_map('htmlspecialchars', $redirect['source_domains'])) ?></strong>
                                                        <?php if ($redirect['include_subdomain']): ?>
                                                            <small class="text-muted">(+ subdomains)</small>
                                                        <?php endif; ?>
                                                    </span>
                                                    <span class="text-muted">→ <?= htmlspecialchars($redirect['target_domain']) ?></span>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="card">
                        <div class="card-body text-center text-muted">
                            <i class="fas fa-arrow-left fa-3x mb-3"></i>
                            <h5>Enter domain details</h5>
                            <p>Fill out the form on the left to test if a domain would be redirected.</p>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Help Section -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-question-circle me-2"></i>How to Use This Test</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Testing Process</h6>
                                <ol>
                                    <li>Enter the domain you want to test (without protocol)</li>
                                    <li>Optionally specify a path (e.g., /page/about)</li>
                                    <li>Select the protocol (HTTP or HTTPS)</li>
                                    <li>Click "Test Redirect" to see results</li>
                                </ol>
                            </div>
                            <div class="col-md-6">
                                <h6>Understanding Results</h6>
                                <ul>
                                    <li><span class="badge bg-success">Will Redirect</span> - Domain matches a rule and will be redirected</li>
                                    <li><span class="badge bg-danger">No Redirect</span> - No matching rule found</li>
                                    <li>Check the "Matched Rule" section for details</li>
                                    <li>Use this before deploying to production</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function quickTest(domain, path) {
            document.querySelector('input[name="test_domain"]').value = domain;
            document.querySelector('input[name="test_path"]').value = path;
            document.querySelector('form').submit();
        }
        
        // Auto-focus on domain input
        document.querySelector('input[name="test_domain"]').focus();
        
        // Add validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const domain = document.querySelector('input[name="test_domain"]').value.trim();
            if (!domain) {
                e.preventDefault();
                alert('Please enter a domain to test');
                return;
            }
            
            // Remove protocol if user entered it
            if (domain.startsWith('http://') || domain.startsWith('https://')) {
                document.querySelector('input[name="test_domain"]').value = domain.replace(/^https?:\/\//, '');
            }
        });
    </script>
</body>
</html>