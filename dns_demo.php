<?php
/**
 * DNS Configuration Quick Demo
 * Demo tool for DNS configuration features
 */
require_once 'includes/navigation.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DNS Configuration - Quick Demo</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .demo-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .feature-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            text-decoration: none;
            color: inherit;
        }
        .feature-card:hover {
            border-color: #007bff;
            box-shadow: 0 8px 16px rgba(0,123,255,0.2);
            transform: translateY(-2px);
            color: inherit;
        }
        .feature-icon {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 10px;
            padding: 1rem;
            font-size: 2rem;
            margin-bottom: 1rem;
            display: inline-block;
        }
        .action-card {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            color: white;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1rem;
        }
        .warning-card {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 1rem;
            margin-bottom: 1rem;
        }
        .step-indicator {
            background: #007bff;
            color: white;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'dns-demo'; 
    include 'includes/navigation.php'; 
    ?>

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="demo-header">
            <div class="row align-items-center">
                <div class="col">
                    <h1><i class="fas fa-rocket"></i> DNS Configuration Demo</h1>
                    <p class="mb-0">Explore DNS management features and learn how to configure your records</p>
                </div>
                <div class="col-auto">
                    <a href="dns_configure.php" class="btn btn-light btn-lg">
                        <i class="fas fa-cogs"></i> Open DNS Configure
                    </a>
                </div>
            </div>
        </div>

        <!-- Warning Notice -->
        <div class="warning-card">
            <div class="d-flex align-items-center">
                <i class="fas fa-exclamation-triangle fa-2x text-warning me-3"></i>
                <div>
                    <h6 class="mb-1"><strong>Important Note</strong></h6>
                    <p class="mb-0">DNS configuration affects your website's accessibility. Always test changes carefully and have backups of your current DNS settings.</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Main Features -->
            <div class="col-lg-8">
                <h3 class="mb-4"><i class="fas fa-star"></i> Key Features</h3>

                <div class="row">
                    <div class="col-md-6">
                        <a href="#" class="feature-card d-block" onclick="showFeatureDemo('create')">
                            <div class="feature-icon">
                                <i class="fas fa-plus"></i>
                            </div>
                            <h5>Add DNS Records</h5>
                            <p class="mb-0 text-muted">Create new A, AAAA, CNAME, MX, TXT, NS, SRV, and CAA records with advanced options.</p>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="#" class="feature-card d-block" onclick="showFeatureDemo('edit')">
                            <div class="feature-icon">
                                <i class="fas fa-edit"></i>
                            </div>
                            <h5>Edit Records</h5>
                            <p class="mb-0 text-muted">Modify existing DNS records including TTL, proxy settings, and priorities.</p>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="#" class="feature-card d-block" onclick="showFeatureDemo('batch')">
                            <div class="feature-icon">
                                <i class="fas fa-tasks"></i>
                            </div>
                            <h5>Batch Operations</h5>
                            <p class="mb-0 text-muted">Select multiple records and perform bulk delete operations efficiently.</p>
                        </a>
                    </div>
                    <div class="col-md-6">
                        <a href="#" class="feature-card d-block" onclick="showFeatureDemo('validate')">
                            <div class="feature-icon">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <h5>Validation</h5>
                            <p class="mb-0 text-muted">Real-time validation of DNS record formats and content before saving.</p>
                        </a>
                    </div>
                </div>

                <!-- Getting Started Steps -->
                <h3 class="mb-4 mt-5"><i class="fas fa-list-ol"></i> Getting Started</h3>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <span class="step-indicator">1</span>
                            <div>
                                <h6>Select Your Domain</h6>
                                <p class="mb-0">Choose the Cloudflare zone/domain you want to manage from the sidebar.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <span class="step-indicator">2</span>
                            <div>
                                <h6>View Current Records</h6>
                                <p class="mb-0">Browse existing DNS records and use filters to find specific entries.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <span class="step-indicator">3</span>
                            <div>
                                <h6>Add or Modify Records</h6>
                                <p class="mb-0">Click "Add Record" to create new entries or use edit/delete buttons on existing records.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-body">
                        <div class="d-flex align-items-start">
                            <span class="step-indicator">4</span>
                            <div>
                                <h6>Test and Monitor</h6>
                                <p class="mb-0">Verify your changes work correctly and monitor DNS propagation.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Quick Actions Sidebar -->
            <div class="col-lg-4">
                <h3 class="mb-4"><i class="fas fa-bolt"></i> Quick Actions</h3>

                <div class="action-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <h6 class="mb-1">Try DNS Configure</h6>
                            <p class="mb-0 small">Start managing your DNS records now</p>
                        </div>
                        <a href="dns_configure.php" class="btn btn-light btn-sm">
                            <i class="fas fa-arrow-right"></i>
                        </a>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-info-circle"></i> Supported Record Types</h6>
                    </div>
                    <div class="card-body p-0">
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><strong>A</strong> - IPv4 Address</span>
                                <span class="badge bg-success rounded-pill">✓</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><strong>AAAA</strong> - IPv6 Address</span>
                                <span class="badge bg-success rounded-pill">✓</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><strong>CNAME</strong> - Alias</span>
                                <span class="badge bg-success rounded-pill">✓</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><strong>MX</strong> - Mail Exchange</span>
                                <span class="badge bg-success rounded-pill">✓</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><strong>TXT</strong> - Text Record</span>
                                <span class="badge bg-success rounded-pill">✓</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><strong>NS</strong> - Name Server</span>
                                <span class="badge bg-success rounded-pill">✓</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><strong>SRV</strong> - Service Record</span>
                                <span class="badge bg-success rounded-pill">✓</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span><strong>CAA</strong> - Certificate Authority</span>
                                <span class="badge bg-success rounded-pill">✓</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tips and Tricks -->
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-lightbulb"></i> Pro Tips</h6>
                    </div>
                    <div class="card-body">
                        <ul class="mb-0 small">
                            <li>Use <code>@</code> for root domain records</li>
                            <li>Enable proxy for better performance and security</li>
                            <li>Set appropriate TTL values for your use case</li>
                            <li>Always test changes on staging environments first</li>
                            <li>Keep backups of your DNS configuration</li>
                            <li>Use validation to avoid typos and errors</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- Safety Reminders -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card border-warning">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="fas fa-exclamation-triangle"></i> Safety Guidelines</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Before Making Changes</h6>
                                <ul>
                                    <li>Document current DNS configuration</li>
                                    <li>Understand the impact of each change</li>
                                    <li>Plan for DNS propagation time (up to 48 hours)</li>
                                    <li>Test changes in a staging environment</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Best Practices</h6>
                                <ul>
                                    <li>Make changes during low-traffic periods</li>
                                    <li>Monitor website availability after changes</li>
                                    <li>Keep TTL values reasonable (not too low/high)</li>
                                    <li>Use proxy settings appropriately</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Feature Demo Modal -->
    <div class="modal fade" id="featureModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="featureModalTitle">Feature Demo</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="featureModalBody">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="dns_configure.php" class="btn btn-primary">Try It Now</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showFeatureDemo(feature) {
            const modal = new bootstrap.Modal(document.getElementById('featureModal'));
            const title = document.getElementById('featureModalTitle');
            const body = document.getElementById('featureModalBody');

            const demos = {
                create: {
                    title: 'Add DNS Records',
                    content: `
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Supported Record Types</h6>
                                <ul>
                                    <li><strong>A Records:</strong> Point domain to IPv4 address</li>
                                    <li><strong>AAAA Records:</strong> Point domain to IPv6 address</li>
                                    <li><strong>CNAME Records:</strong> Create domain aliases</li>
                                    <li><strong>MX Records:</strong> Configure mail servers with priorities</li>
                                    <li><strong>TXT Records:</strong> Store text data for verification</li>
                                    <li><strong>NS Records:</strong> Delegate subdomains</li>
                                    <li><strong>SRV Records:</strong> Define service locations</li>
                                    <li><strong>CAA Records:</strong> Certificate authority authorization</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Advanced Options</h6>
                                <ul>
                                    <li><strong>TTL Settings:</strong> From Auto to 1 day</li>
                                    <li><strong>Proxy Settings:</strong> Enable Cloudflare proxy</li>
                                    <li><strong>Priority Values:</strong> For MX and SRV records</li>
                                    <li><strong>Real-time Validation:</strong> Prevent errors</li>
                                </ul>
                                
                                <div class="alert alert-info mt-3">
                                    <small><i class="fas fa-info-circle"></i> Each record type has specific validation rules to ensure proper configuration.</small>
                                </div>
                            </div>
                        </div>
                    `
                },
                edit: {
                    title: 'Edit DNS Records',
                    content: `
                        <h6>Edit Capabilities</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <ul>
                                    <li>Modify record content and values</li>
                                    <li>Change TTL settings</li>
                                    <li>Toggle proxy status</li>
                                    <li>Update priorities for MX/SRV records</li>
                                    <li>Real-time validation during editing</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-warning">
                                    <h6 class="alert-heading">⚠️ Important</h6>
                                    <p class="mb-0">Changes to DNS records may take up to 48 hours to propagate globally. Test critical changes carefully.</p>
                                </div>
                            </div>
                        </div>
                        
                        <h6 class="mt-3">Edit Process</h6>
                        <ol>
                            <li>Click the edit button on any record</li>
                            <li>Modify values in the popup form</li>
                            <li>Validation occurs in real-time</li>
                            <li>Save changes to update via Cloudflare API</li>
                        </ol>
                    `
                },
                batch: {
                    title: 'Batch Operations',
                    content: `
                        <h6>Efficient Bulk Management</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <ul>
                                    <li>Select multiple records using checkboxes</li>
                                    <li>Use "Select All" for entire list</li>
                                    <li>Batch delete multiple records at once</li>
                                    <li>Real-time selection counter</li>
                                    <li>Confirmation dialogs for safety</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <div class="alert alert-danger">
                                    <h6 class="alert-heading">⚠️ Caution</h6>
                                    <p class="mb-0">Batch delete operations cannot be undone. Always verify your selection before confirming.</p>
                                </div>
                            </div>
                        </div>
                        
                        <h6 class="mt-3">How to Use</h6>
                        <ol>
                            <li>Check boxes next to records you want to delete</li>
                            <li>Batch action bar appears when records are selected</li>
                            <li>Click "Delete Selected" for bulk removal</li>
                            <li>Confirm the action in the safety dialog</li>
                        </ol>
                    `
                },
                validate: {
                    title: 'DNS Validation',
                    content: `
                        <h6>Built-in Validation Rules</h6>
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="text-primary">Format Validation</h6>
                                <ul>
                                    <li><strong>A Records:</strong> Valid IPv4 addresses</li>
                                    <li><strong>AAAA Records:</strong> Valid IPv6 addresses</li>
                                    <li><strong>CNAME Records:</strong> Valid domain names</li>
                                    <li><strong>MX Records:</strong> Valid mail server domains</li>
                                    <li><strong>TXT Records:</strong> Length and format checks</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-success">Smart Features</h6>
                                <ul>
                                    <li>Real-time validation as you type</li>
                                    <li>Visual error indicators</li>
                                    <li>Helpful error messages</li>
                                    <li>Prevents saving invalid records</li>
                                    <li>Context-sensitive help text</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="alert alert-success mt-3">
                            <h6 class="alert-heading">✅ Benefits</h6>
                            <p class="mb-0">Validation prevents common DNS configuration errors and saves time by catching issues before they reach your live DNS settings.</p>
                        </div>
                    `
                }
            };

            const demo = demos[feature];
            title.textContent = demo.title;
            body.innerHTML = demo.content;
            modal.show();
        }
    </script>
</body>
</html>