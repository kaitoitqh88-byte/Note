<?php
/**
 * DNS Lookup Quick Start Guide
 * Hướng dẫn sử dụng DNS Records Lookup
 */
require_once 'includes/navigation.php';
?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DNS Lookup - Quick Start Guide</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .guide-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 2rem;
            margin-bottom: 2rem;
        }
        .step-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }
        .step-card:hover {
            border-color: #007bff;
            box-shadow: 0 4px 8px rgba(0,123,255,0.2);
        }
        .step-number {
            background: #007bff;
            color: white;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
        }
        .feature-icon {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
            border-radius: 10px;
            padding: 1rem;
            font-size: 1.5rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <?php 
    $currentPage = 'dns-guide'; 
    include 'includes/navigation.php'; 
    ?>

    <div class="container-fluid mt-4">
        <!-- Header -->
        <div class="guide-header">
            <div class="row align-items-center">
                <div class="col">
                    <h1><i class="fas fa-graduation-cap"></i> DNS Lookup Quick Start</h1>
                    <p class="mb-0">Learn how to use the DNS Records Lookup tool effectively</p>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Getting Started Steps -->
            <div class="col-lg-8">
                <h3 class="mb-4"><i class="fas fa-rocket"></i> Getting Started</h3>

                <div class="step-card">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="step-number">1</div>
                        </div>
                        <div class="col">
                            <h5>Select Your Domain</h5>
                            <p class="mb-0 text-muted">Choose from your Cloudflare zones in the sidebar. All your domains managed by Cloudflare will appear here.</p>
                        </div>
                    </div>
                </div>

                <div class="step-card">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="step-number">2</div>
                        </div>
                        <div class="col">
                            <h5>Filter Records</h5>
                            <p class="mb-0 text-muted">Use the record type filter (A, AAAA, CNAME, etc.) and name search to find specific DNS entries quickly.</p>
                        </div>
                    </div>
                </div>

                <div class="step-card">
                    <div class="row align-items-center">
                        <div class="col-auto">
                            <div class="step-number">3</div>
                        </div>
                        <div class="col">
                            <h5>View & Analyze</h5>
                            <p class="mb-0 text-muted">Examine your DNS configuration, check proxy status, TTL values, and export data as needed.</p>
                        </div>
                    </div>
                </div>

                <!-- DNS Record Types -->
                <h3 class="mb-4 mt-5"><i class="fas fa-info-circle"></i> DNS Record Types</h3>

                <div class="row">
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <span class="badge bg-success me-2">A</span> IPv4 Address
                                </h6>
                                <p class="card-text small">Points domain to IPv4 address (e.g., 192.168.1.1)</p>
                            </div>
                        </div>
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <span class="badge bg-info me-2">AAAA</span> IPv6 Address
                                </h6>
                                <p class="card-text small">Points domain to IPv6 address</p>
                            </div>
                        </div>
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <span class="badge bg-warning text-dark me-2">CNAME</span> Canonical Name
                                </h6>
                                <p class="card-text small">Creates alias pointing to another domain</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <span class="badge bg-danger me-2">MX</span> Mail Exchange
                                </h6>
                                <p class="card-text small">Specifies mail servers for email delivery</p>
                            </div>
                        </div>
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <span class="badge bg-purple me-2">TXT</span> Text Record
                                </h6>
                                <p class="card-text small">Stores text data for verification, SPF, DKIM</p>
                            </div>
                        </div>
                        <div class="card mb-3">
                            <div class="card-body">
                                <h6 class="card-title">
                                    <span class="badge bg-secondary me-2">NS</span> Name Server
                                </h6>
                                <p class="card-text small">Delegates subdomain to other name servers</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Features Sidebar -->
            <div class="col-lg-4">
                <h3 class="mb-4"><i class="fas fa-star"></i> Key Features</h3>

                <div class="card mb-3">
                    <div class="card-body text-center">
                        <div class="feature-icon">
                            <i class="fas fa-filter"></i>
                        </div>
                        <h6>Advanced Filtering</h6>
                        <p class="small text-muted">Search by record type, name, and other criteria instantly.</p>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body text-center">
                        <div class="feature-icon">
                            <i class="fas fa-cloud"></i>
                        </div>
                        <h6>Proxy Status</h6>
                        <p class="small text-muted">See which records are proxied through Cloudflare.</p>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body text-center">
                        <div class="feature-icon">
                            <i class="fas fa-download"></i>
                        </div>
                        <h6>Export Data</h6>
                        <p class="small text-muted">Download DNS records as JSON or CSV files.</p>
                    </div>
                </div>

                <div class="card mb-3">
                    <div class="card-body text-center">
                        <div class="feature-icon">
                            <i class="fas fa-sync"></i>
                        </div>
                        <h6>Real-time Data</h6>
                        <p class="small text-muted">Connect directly to Cloudflare API for live data.</p>
                    </div>
                </div>

                <!-- Tips -->
                <div class="card bg-light mb-3">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-lightbulb"></i> Pro Tips</h6>
                    </div>
                    <div class="card-body">
                        <ul class="small mb-0">
                            <li>Use <kbd>Ctrl+R</kbd> to refresh records quickly</li>
                            <li>Type in the name filter to search as you type</li>
                            <li>Check the proxy badge to see CDN status</li>
                            <li>Export records for backup or documentation</li>
                        </ul>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="d-grid gap-2">
                    <a href="index.php" class="btn btn-outline-secondary">
                        <i class="fas fa-home"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>

        <!-- Troubleshooting Section -->
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-wrench"></i> Troubleshooting</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-exclamation-triangle text-warning"></i> No Zones Found</h6>
                                <p class="small">
                                    - Check your Cloudflare API token in <code>token.txt</code><br>
                                    - Verify your email in <code>config.php</code><br>
                                    - Ensure you have domains in your Cloudflare account
                                </p>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-times text-danger"></i> API Errors</h6>
                                <p class="small">
                                    - Check internet connection<br>
                                    - Verify API token permissions<br>
                                    - Try refreshing the page
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>