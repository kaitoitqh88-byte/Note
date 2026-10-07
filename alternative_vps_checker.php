<?php
/**
 * Alternative VPS Connectivity Checker
 * For systems without SSH2 extension - uses socket connections and port testing
 */

/**
 * Test basic connectivity to VPS (port 22 check)
 */
function testVPSConnectivity($ip, $port = 22, $timeout = 5) {
    $startTime = microtime(true);
    
    $result = [
        'ip' => $ip,
        'port' => $port,
        'success' => false,
        'response_time' => null,
        'error' => null,
        'method' => 'socket'
    ];
    
    try {
        // Validate IP
        if (!filter_var($ip, FILTER_VALIDATE_IP)) {
            throw new Exception('Invalid IP address format');
        }
        
        // Test socket connection
        $socket = @fsockopen($ip, $port, $errno, $errstr, $timeout);
        
        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000, 2);
        
        if ($socket) {
            fclose($socket);
            $result['success'] = true;
            $result['message'] = "Port $port is open and reachable";
        } else {
            $result['error'] = "Connection failed: $errstr ($errno)";
        }
        
        $result['response_time'] = $responseTime;
        
    } catch (Exception $e) {
        $endTime = microtime(true);
        $result['response_time'] = round(($endTime - $startTime) * 1000, 2);
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

/**
 * Test HTTP connectivity (if web server is running)
 */
function testHTTPConnectivity($ip, $port = 80, $timeout = 5) {
    $result = [
        'ip' => $ip,
        'port' => $port,
        'success' => false,
        'response_time' => null,
        'error' => null,
        'method' => 'http'
    ];
    
    $startTime = microtime(true);
    
    try {
        $context = stream_context_create([
            'http' => [
                'timeout' => $timeout,
                'method' => 'HEAD'
            ]
        ]);
        
        $url = "http://$ip:$port/";
        $response = @file_get_contents($url, false, $context);
        
        $endTime = microtime(true);
        $responseTime = round(($endTime - $startTime) * 1000, 2);
        
        if ($response !== false || isset($http_response_header)) {
            $result['success'] = true;
            $result['message'] = "HTTP server responding on port $port";
            if (isset($http_response_header[0])) {
                $result['http_status'] = $http_response_header[0];
            }
        } else {
            $result['error'] = "No HTTP response on port $port";
        }
        
        $result['response_time'] = $responseTime;
        
    } catch (Exception $e) {
        $endTime = microtime(true);
        $result['response_time'] = round(($endTime - $startTime) * 1000, 2);
        $result['error'] = $e->getMessage();
    }
    
    return $result;
}

/**
 * Test multiple ports on VPS
 */
function testMultiplePorts($ip, $ports = [22, 80, 443, 21, 53], $timeout = 3) {
    $results = [];
    
    foreach ($ports as $port) {
        $results[$port] = testVPSConnectivity($ip, $port, $timeout);
    }
    
    return $results;
}

/**
 * Ping test using system ping command
 */
function testPing($ip, $count = 3) {
    $result = [
        'ip' => $ip,
        'success' => false,
        'method' => 'ping',
        'packets_sent' => $count,
        'packets_received' => 0,
        'packet_loss' => '100%',
        'avg_time' => null,
        'output' => ''
    ];
    
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $result['error'] = 'Invalid IP address';
        return $result;
    }
    
    // Windows ping command
    if (PHP_OS_FAMILY === 'Windows') {
        $cmd = "ping -n $count $ip";
    } else {
        // Linux/Unix ping command
        $cmd = "ping -c $count $ip";
    }
    
    $output = shell_exec($cmd);
    $result['output'] = $output;
    
    if ($output) {
        // Parse ping results
        if (PHP_OS_FAMILY === 'Windows') {
            // Windows ping parsing
            preg_match('/Packets: Sent = (\d+), Received = (\d+), Lost = (\d+)/', $output, $matches);
            if ($matches) {
                $result['packets_sent'] = intval($matches[1]);
                $result['packets_received'] = intval($matches[2]);
                $lost = intval($matches[3]);
                $result['packet_loss'] = round(($lost / $result['packets_sent']) * 100, 1) . '%';
                
                if ($result['packets_received'] > 0) {
                    $result['success'] = true;
                    
                    // Extract average time
                    preg_match('/Average = (\d+)ms/', $output, $timeMatch);
                    if ($timeMatch) {
                        $result['avg_time'] = $timeMatch[1] . 'ms';
                    }
                }
            }
        } else {
            // Linux ping parsing
            preg_match('/(\d+) packets transmitted, (\d+) received/', $output, $matches);
            if ($matches) {
                $result['packets_sent'] = intval($matches[1]);
                $result['packets_received'] = intval($matches[2]);
                $lost = $result['packets_sent'] - $result['packets_received'];
                $result['packet_loss'] = round(($lost / $result['packets_sent']) * 100, 1) . '%';
                
                if ($result['packets_received'] > 0) {
                    $result['success'] = true;
                    
                    // Extract average time
                    preg_match('/= [\d.]+\/([\d.]+)\/[\d.]+\/[\d.]+/', $output, $timeMatch);
                    if ($timeMatch) {
                        $result['avg_time'] = round($timeMatch[1], 1) . 'ms';
                    }
                }
            }
        }
    }
    
    return $result;
}

/**
 * Comprehensive VPS test (all methods combined)
 */
function comprehensiveVPSTest($ip, $username = null, $password = null) {
    $results = [
        'ip' => $ip,
        'timestamp' => date('Y-m-d H:i:s'),
        'tests' => []
    ];
    
    // Test 1: Ping
    $results['tests']['ping'] = testPing($ip);
    
    // Test 2: SSH Port
    $results['tests']['ssh'] = testVPSConnectivity($ip, 22);
    
    // Test 3: HTTP Port  
    $results['tests']['http'] = testHTTPConnectivity($ip, 80);
    
    // Test 4: HTTPS Port
    $results['tests']['https'] = testVPSConnectivity($ip, 443);
    
    // Test 5: Multiple common ports
    $results['tests']['ports'] = testMultiplePorts($ip, [21, 53, 3306, 5432, 6379]);
    
    // Determine overall status
    $results['overall_status'] = 'unreachable';
    if ($results['tests']['ping']['success']) {
        $results['overall_status'] = 'reachable';
        if ($results['tests']['ssh']['success']) {
            $results['overall_status'] = 'ssh_ready';
        }
    }
    
    return $results;
}

// Handle API requests
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    
    switch ($_GET['action']) {
        case 'test_connectivity':
            $ip = $_GET['ip'] ?? '';
            $port = intval($_GET['port'] ?? 22);
            $timeout = intval($_GET['timeout'] ?? 5);
            
            $result = testVPSConnectivity($ip, $port, $timeout);
            echo json_encode($result);
            break;
            
        case 'test_ping':
            $ip = $_GET['ip'] ?? '';
            $count = intval($_GET['count'] ?? 3);
            
            $result = testPing($ip, $count);
            echo json_encode($result);
            break;
            
        case 'comprehensive_test':
            $ip = $_GET['ip'] ?? '';
            
            $result = comprehensiveVPSTest($ip);
            echo json_encode($result, JSON_PRETTY_PRINT);
            break;
            
        case 'batch_test':
            $ips = $_POST['ips'] ?? [];
            if (is_string($ips)) {
                $ips = array_filter(array_map('trim', explode("\n", $ips)));
            }
            
            $results = [];
            foreach ($ips as $ip) {
                $results[] = comprehensiveVPSTest($ip);
            }
            
            echo json_encode($results);
            break;
            
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
    exit;
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
<link rel="icon" type="image/x-icon" href="assets/favicon.ico">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Alternative VPS Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-robot"></i> Alternative VPS Connectivity Checker</h5>
                        <p class="mb-0 text-muted">For systems without SSH2 extension - Tests basic connectivity, ports, and ping</p>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Single IP Test</h6>
                                <form id="single-test-form">
                                    <div class="mb-3">
                                        <label class="form-label">IP Address</label>
                                        <input type="text" class="form-control" id="single-ip" placeholder="192.168.1.1" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-play"></i> Test Comprehensive
                                    </button>
                                </form>
                            </div>
                            
                            <div class="col-md-6">
                                <h6>Batch IPs Test</h6>
                                <form id="batch-test-form">
                                    <div class="mb-3">
                                        <label class="form-label">IP Addresses (one per line)</label>
                                        <textarea class="form-control" id="batch-ips" rows="4" placeholder="192.168.1.1&#10;192.168.1.2&#10;192.168.1.3"></textarea>
                                    </div>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fas fa-list"></i> Test Batch
                                    </button>
                                </form>
                            </div>
                        </div>
                        
                        <div id="results-section" style="display: none;">
                            <hr>
                            <h6>Test Results</h6>
                            <div id="results-container"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('single-test-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const ip = document.getElementById('single-ip').value;
            await testSingleIP(ip);
        });

        document.getElementById('batch-test-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            const ips = document.getElementById('batch-ips').value;
            await testBatchIPs(ips);
        });

        async function testSingleIP(ip) {
            const resultsSection = document.getElementById('results-section');
            const resultsContainer = document.getElementById('results-container');
            
            resultsSection.style.display = 'block';
            resultsContainer.innerHTML = '<div class="text-center"><div class="spinner-border"></div><p>Testing ' + ip + '...</p></div>';
            
            try {
                const response = await fetch(`?action=comprehensive_test&ip=${encodeURIComponent(ip)}`);
                const data = await response.json();
                
                displaySingleResult(data);
                
            } catch (error) {
                resultsContainer.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
            }
        }

        async function testBatchIPs(ipsText) {
            const resultsSection = document.getElementById('results-section');
            const resultsContainer = document.getElementById('results-container');
            
            resultsSection.style.display = 'block';
            resultsContainer.innerHTML = '<div class="text-center"><div class="spinner-border"></div><p>Testing multiple IPs...</p></div>';
            
            try {
                const formData = new FormData();
                formData.append('ips', ipsText);
                
                const response = await fetch('?action=batch_test', {
                    method: 'POST',
                    body: formData
                });
                
                const data = await response.json();
                displayBatchResults(data);
                
            } catch (error) {
                resultsContainer.innerHTML = `<div class="alert alert-danger">Error: ${error.message}</div>`;
            }
        }

        function displaySingleResult(data) {
            const container = document.getElementById('results-container');
            
            const statusBadge = getStatusBadge(data.overall_status);
            
            let html = `
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <strong>${data.ip}</strong>
                        ${statusBadge}
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Ping Test</h6>
                                ${formatPingResult(data.tests.ping)}
                            </div>
                            <div class="col-md-6">
                                <h6>Port Tests</h6>
                                ${formatPortResult(data.tests.ssh, 'SSH (22)')}
                                ${formatPortResult(data.tests.http, 'HTTP (80)')}
                                ${formatPortResult(data.tests.https, 'HTTPS (443)')}
                            </div>
                        </div>
                        
                        <h6 class="mt-3">Additional Ports</h6>
                        <div class="row">
                            ${formatPortsGrid(data.tests.ports)}
                        </div>
                    </div>
                </div>
            `;
            
            container.innerHTML = html;
        }

        function displayBatchResults(results) {
            const container = document.getElementById('results-container');
            
            let html = '<div class="row">';
            
            results.forEach(data => {
                const statusBadge = getStatusBadge(data.overall_status);
                
                html += `
                    <div class="col-md-4 mb-3">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between">
                                <strong>${data.ip}</strong>
                                ${statusBadge}
                            </div>
                            <div class="card-body">
                                <small>
                                    Ping: ${data.tests.ping.success ? '✓' : '✗'}<br>
                                    SSH: ${data.tests.ssh.success ? '✓' : '✗'}<br>
                                    HTTP: ${data.tests.http.success ? '✓' : '✗'}
                                </small>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
        }

        function getStatusBadge(status) {
            const badges = {
                'unreachable': '<span class="badge bg-danger">Unreachable</span>',
                'reachable': '<span class="badge bg-warning">Reachable</span>',
                'ssh_ready': '<span class="badge bg-success">SSH Ready</span>'
            };
            return badges[status] || '<span class="badge bg-secondary">Unknown</span>';
        }

        function formatPingResult(ping) {
            if (ping.success) {
                return `
                    <div class="text-success">
                        ✓ ${ping.packets_received}/${ping.packets_sent} packets received<br>
                        Average: ${ping.avg_time || 'N/A'}<br>
                        Loss: ${ping.packet_loss}
                    </div>
                `;
            } else {
                return `<div class="text-danger">✗ ${ping.packet_loss} packet loss</div>`;
            }
        }

        function formatPortResult(result, label) {
            const icon = result.success ? '✓' : '✗';
            const color = result.success ? 'text-success' : 'text-danger';
            const time = result.response_time ? ` (${result.response_time}ms)` : '';
            
            return `<div class="${color}">${icon} ${label}${time}</div>`;
        }

        function formatPortsGrid(ports) {
            let html = '';
            for (const [port, result] of Object.entries(ports)) {
                const icon = result.success ? '✓' : '✗';
                const color = result.success ? 'text-success' : 'text-muted';
                html += `<div class="col-4"><small class="${color}">${icon} Port ${port}</small></div>`;
            }
            return html;
        }
    </script>
</body>
</html>