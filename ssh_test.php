<?php
/**
 * SSH Extension Test
 * Test SSH2 extension availability and basic connectivity
 */

// Test if SSH2 extension is loaded
function checkSSH2Extension() {
    return extension_loaded('ssh2');
}

// Test SSH connection to a sample VPS
function testSSHConnection($ip, $username, $password, $timeout = 5) {
    if (!checkSSH2Extension()) {
        return [
            'success' => false,
            'error' => 'SSH2 extension is not installed',
            'suggestion' => 'Please install php-ssh2 extension'
        ];
    }
    
    try {
        $context = stream_context_create([
            'socket' => [
                'timeout' => $timeout
            ]
        ]);
        
        $connection = ssh2_connect($ip, 22, null, ['timeout' => $timeout]);
        
        if (!$connection) {
            return [
                'success' => false,
                'error' => 'Cannot connect to SSH server',
                'ip' => $ip
            ];
        }
        
        $auth = ssh2_auth_password($connection, $username, $password);
        
        if (!$auth) {
            return [
                'success' => false,
                'error' => 'Authentication failed',
                'ip' => $ip
            ];
        }
        
        // Test command execution
        $stream = ssh2_exec($connection, 'echo "SSH connection successful"');
        if ($stream) {
            stream_set_blocking($stream, true);
            $output = stream_get_contents($stream);
            fclose($stream);
            
            return [
                'success' => true,
                'message' => 'SSH connection and authentication successful',
                'output' => trim($output),
                'ip' => $ip
            ];
        }
        
        return [
            'success' => true,
            'message' => 'SSH connection successful but command execution failed',
            'ip' => $ip
        ];
        
    } catch (Exception $e) {
        return [
            'success' => false,
            'error' => $e->getMessage(),
            'ip' => $ip
        ];
    }
}

// Handle test request
if (isset($_GET['test']) && $_GET['test'] === 'ssh') {
    header('Content-Type: application/json');
    
    $result = [
        'ssh2_extension' => checkSSH2Extension(),
        'php_version' => PHP_VERSION,
        'loaded_extensions' => get_loaded_extensions()
    ];
    
    if (isset($_GET['ip']) && isset($_GET['username']) && isset($_GET['password'])) {
        $result['connection_test'] = testSSHConnection(
            $_GET['ip'], 
            $_GET['username'], 
            $_GET['password']
        );
    }
    
    echo json_encode($result, JSON_PRETTY_PRINT);
    exit;
}

?>
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SSH Test - VPS Checker</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-md-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h5>SSH Extension & Connectivity Test</h5>
                    </div>
                    <div class="card-body">
                        <div id="extension-status">
                            <h6>Testing SSH2 Extension...</h6>
                            <div class="spinner-border spinner-border-sm"></div>
                        </div>
                        
                        <div id="test-form" style="display: none;">
                            <hr>
                            <h6>Test SSH Connection</h6>
                            <form id="ssh-test-form">
                                <div class="mb-3">
                                    <label class="form-label">IP Address</label>
                                    <input type="text" class="form-control" id="test-ip" placeholder="192.168.1.1">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Username</label>
                                    <input type="text" class="form-control" id="test-username" value="root">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Password</label>
                                    <input type="password" class="form-control" id="test-password">
                                </div>
                                <button type="submit" class="btn btn-primary">Test Connection</button>
                            </form>
                        </div>
                        
                        <div id="test-results" style="display: none;">
                            <hr>
                            <h6>Test Results</h6>
                            <pre id="results-output"></pre>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            testSSHExtension();
        });

        async function testSSHExtension() {
            try {
                const response = await fetch('?test=ssh');
                const data = await response.json();
                
                const statusDiv = document.getElementById('extension-status');
                const testForm = document.getElementById('test-form');
                
                if (data.ssh2_extension) {
                    statusDiv.innerHTML = `
                        <div class="alert alert-success">
                            <strong>✓ SSH2 Extension Available</strong><br>
                            PHP Version: ${data.php_version}<br>
                            Ready for VPS checking!
                        </div>
                    `;
                    testForm.style.display = 'block';
                } else {
                    statusDiv.innerHTML = `
                        <div class="alert alert-danger">
                            <strong>✗ SSH2 Extension Not Available</strong><br>
                            PHP Version: ${data.php_version}<br>
                            <hr>
                            <strong>Installation Guide:</strong><br>
                            <code>sudo apt-get install php-ssh2</code> (Ubuntu/Debian)<br>
                            <code>sudo yum install php-ssh2</code> (CentOS/RHEL)<br>
                            Or enable in php.ini: <code>extension=ssh2</code>
                        </div>
                    `;
                }
                
                document.getElementById('ssh-test-form').onsubmit = testConnection;
                
            } catch (error) {
                document.getElementById('extension-status').innerHTML = `
                    <div class="alert alert-danger">
                        Error testing SSH extension: ${error.message}
                    </div>
                `;
            }
        }

        async function testConnection(event) {
            event.preventDefault();
            
            const ip = document.getElementById('test-ip').value;
            const username = document.getElementById('test-username').value;
            const password = document.getElementById('test-password').value;
            
            if (!ip || !username || !password) {
                alert('Please fill all fields');
                return;
            }
            
            const resultsDiv = document.getElementById('test-results');
            const outputPre = document.getElementById('results-output');
            
            resultsDiv.style.display = 'block';
            outputPre.textContent = 'Testing connection...';
            
            try {
                const response = await fetch(`?test=ssh&ip=${encodeURIComponent(ip)}&username=${encodeURIComponent(username)}&password=${encodeURIComponent(password)}`);
                const data = await response.json();
                
                outputPre.textContent = JSON.stringify(data, null, 2);
                
            } catch (error) {
                outputPre.textContent = `Error: ${error.message}`;
            }
        }
    </script>
</body>
</html>