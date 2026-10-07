<?php
/**
 * VPS Connection Handler
 * Quản lý kết nối SSH/FTP để quét WordPress trên VPS từ xa
 */

class VPSConnectionHandler {
    private $connection;
    private $connectionType; // 'ssh' hoặc 'ftp'
    private $host;
    private $username;
    private $isConnected = false;
    
    public function __construct() {
        // Initialize
    }
    
    /**
     * Kết nối SSH đến VPS
     */
    public function connectSSH($host, $port, $username, $password = null, $keyFile = null) {
        if (!extension_loaded('ssh2')) {
            return ['error' => 'SSH2 extension not installed. Please install php-ssh2'];
        }
        
        try {
            $this->connection = ssh2_connect($host, $port);
            if (!$this->connection) {
                return ['error' => "Cannot connect to $host:$port"];
            }
            
            // Authenticate
            if ($keyFile && file_exists($keyFile)) {
                // Key-based authentication
                $authResult = ssh2_auth_pubkey_file($this->connection, $username, 
                    $keyFile . '.pub', $keyFile, $password);
            } else {
                // Password authentication
                $authResult = ssh2_auth_password($this->connection, $username, $password);
            }
            
            if (!$authResult) {
                return ['error' => 'Authentication failed'];
            }
            
            $this->connectionType = 'ssh';
            $this->host = $host;
            $this->username = $username;
            $this->isConnected = true;
            
            return ['success' => true, 'message' => "Connected to $host via SSH"];
            
        } catch (Exception $e) {
            return ['error' => 'SSH connection failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Kết nối FTP đến VPS
     */
    public function connectFTP($host, $port, $username, $password, $passive = true) {
        try {
            $this->connection = ftp_connect($host, $port);
            if (!$this->connection) {
                return ['error' => "Cannot connect to $host:$port"];
            }
            
            if (!ftp_login($this->connection, $username, $password)) {
                return ['error' => 'FTP authentication failed'];
            }
            
            // Set passive mode
            if ($passive) {
                ftp_pasv($this->connection, true);
            }
            
            $this->connectionType = 'ftp';
            $this->host = $host;
            $this->username = $username;
            $this->isConnected = true;
            
            return ['success' => true, 'message' => "Connected to $host via FTP"];
            
        } catch (Exception $e) {
            return ['error' => 'FTP connection failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Kiểm tra WordPress path trên VPS
     */
    public function validateWordPressPath($path) {
        if (!$this->isConnected) {
            return false;
        }
        
        if ($this->connectionType === 'ssh') {
            return $this->validateWordPressPathSSH($path);
        } else {
            return $this->validateWordPressPathFTP($path);
        }
    }
    
    /**
     * Validate WordPress path via SSH
     */
    private function validateWordPressPathSSH($path) {
        $requiredFiles = ['wp-config.php', 'wp-load.php', 'wp-includes', 'wp-admin'];
        
        foreach ($requiredFiles as $file) {
            $command = "test -e '$path/$file' && echo 'EXISTS' || echo 'NOT_FOUND'";
            $stream = ssh2_exec($this->connection, $command);
            stream_set_blocking($stream, true);
            $result = trim(stream_get_contents($stream));
            fclose($stream);
            
            if ($result !== 'EXISTS') {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Validate WordPress path via FTP
     */
    private function validateWordPressPathFTP($path) {
        $requiredFiles = ['wp-config.php', 'wp-load.php'];
        
        foreach ($requiredFiles as $file) {
            if (!ftp_size($this->connection, $path . '/' . $file) > 0) {
                return false;
            }
        }
        return true;
    }
    
    /**
     * Download file từ VPS
     */
    public function downloadFile($remotePath, $localPath = null) {
        if (!$this->isConnected) {
            return ['error' => 'Not connected to VPS'];
        }
        
        if (!$localPath) {
            $localPath = sys_get_temp_dir() . '/' . basename($remotePath);
        }
        
        try {
            if ($this->connectionType === 'ssh') {
                return $this->downloadFileSSH($remotePath, $localPath);
            } else {
                return $this->downloadFileFTP($remotePath, $localPath);
            }
        } catch (Exception $e) {
            return ['error' => 'Download failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Download file via SSH
     */
    private function downloadFileSSH($remotePath, $localPath) {
        $sftpConnection = ssh2_sftp($this->connection);
        if (!$sftpConnection) {
            return ['error' => 'SFTP connection failed'];
        }
        
        $remoteStream = fopen("ssh2.sftp://$sftpConnection$remotePath", 'r');
        if (!$remoteStream) {
            return ['error' => "Cannot open remote file: $remotePath"];
        }
        
        $localStream = fopen($localPath, 'w');
        if (!$localStream) {
            fclose($remoteStream);
            return ['error' => "Cannot create local file: $localPath"];
        }
        
        $bytes = stream_copy_to_stream($remoteStream, $localStream);
        fclose($remoteStream);
        fclose($localStream);
        
        return [
            'success' => true,
            'local_path' => $localPath,
            'bytes_downloaded' => $bytes
        ];
    }
    
    /**
     * Download file via FTP
     */
    private function downloadFileFTP($remotePath, $localPath) {
        if (!ftp_get($this->connection, $localPath, $remotePath, FTP_BINARY)) {
            return ['error' => "FTP download failed: $remotePath"];
        }
        
        return [
            'success' => true,
            'local_path' => $localPath,
            'bytes_downloaded' => filesize($localPath)
        ];
    }
    
    /**
     * Execute command trên VPS (SSH only)
     */
    public function executeCommand($command) {
        if (!$this->isConnected || $this->connectionType !== 'ssh') {
            return ['error' => 'SSH connection required for command execution'];
        }
        
        try {
            $stream = ssh2_exec($this->connection, $command);
            
            if (!$stream) {
                return ['error' => 'Command execution failed'];
            }
            
            stream_set_blocking($stream, true);
            $output = stream_get_contents($stream);
            
            $errorStream = ssh2_fetch_stream($stream, SSH2_STREAM_STDERR);
            stream_set_blocking($errorStream, true);
            $error = stream_get_contents($errorStream);
            
            fclose($stream);
            fclose($errorStream);
            
            return [
                'success' => true,
                'output' => $output,
                'error' => $error
            ];
            
        } catch (Exception $e) {
            return ['error' => 'Command execution failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Lấy danh sách files trong directory
     */
    public function listDirectory($path) {
        if (!$this->isConnected) {
            return ['error' => 'Not connected to VPS'];
        }
        
        if ($this->connectionType === 'ssh') {
            return $this->listDirectorySSH($path);
        } else {
            return $this->listDirectoryFTP($path);
        }
    }
    
    /**
     * List directory via SSH
     */
    private function listDirectorySSH($path) {
        $command = "ls -la '$path'";
        $result = $this->executeCommand($command);
        
        if (!$result['success']) {
            return $result;
        }
        
        $files = [];
        $lines = explode("\n", trim($result['output']));
        
        foreach ($lines as $line) {
            if (preg_match('/^([-drwx]+)\s+\d+\s+\S+\s+\S+\s+(\d+)\s+(\S+\s+\S+\s+\S+)\s+(.+)$/', $line, $matches)) {
                $files[] = [
                    'permissions' => $matches[1],
                    'size' => $matches[2],
                    'date' => $matches[3],
                    'name' => $matches[4],
                    'type' => $matches[1][0] === 'd' ? 'directory' : 'file'
                ];
            }
        }
        
        return ['success' => true, 'files' => $files];
    }
    
    /**
     * List directory via FTP
     */
    private function listDirectoryFTP($path) {
        $files = ftp_nlist($this->connection, $path);
        if ($files === false) {
            return ['error' => "Cannot list directory: $path"];
        }
        
        $fileList = [];
        foreach ($files as $file) {
            $size = ftp_size($this->connection, $file);
            $fileList[] = [
                'name' => basename($file),
                'size' => $size > 0 ? $size : 'Unknown',
                'type' => $size === -1 ? 'directory' : 'file'
            ];
        }
        
        return ['success' => true, 'files' => $fileList];
    }
    
    /**
     * Quét WordPress trên VPS
     */
    public function scanWordPressOnVPS($wordpressPath) {
        if (!$this->isConnected) {
            return ['error' => 'Not connected to VPS'];
        }
        
        if (!$this->validateWordPressPath($wordpressPath)) {
            return ['error' => 'Invalid WordPress path on VPS'];
        }
        
        $scanResults = [
            'vps_info' => [
                'host' => $this->host,
                'username' => $this->username,
                'connection_type' => $this->connectionType,
                'wordpress_path' => $wordpressPath
            ],
            'basic_info' => $this->getVPSWordPressInfo($wordpressPath),
            'security_scan' => $this->scanVPSSecurity($wordpressPath),
            'file_integrity' => $this->scanVPSFileIntegrity($wordpressPath),
            'plugins_themes' => $this->scanVPSPluginsThemes($wordpressPath)
        ];
        
        return $scanResults;
    }
    
    /**
     * Lấy thông tin WordPress trên VPS
     */
    private function getVPSWordPressInfo($path) {
        $info = [
            'version' => 'Unknown',
            'database' => 'Unknown',
            'debug_mode' => false,
            'multisite' => false
        ];
        
        // Download wp-includes/version.php để check version
        $versionFile = $path . '/wp-includes/version.php';
        $downloadResult = $this->downloadFile($versionFile);
        
        if ($downloadResult['success']) {
            $content = file_get_contents($downloadResult['local_path']);
            if (preg_match('/\$wp_version\s*=\s*[\'"]([^\'\"]+)[\'"]/', $content, $matches)) {
                $info['version'] = $matches[1];
            }
            unlink($downloadResult['local_path']); // Cleanup
        }
        
        // Download wp-config.php để check database và settings
        $configFile = $path . '/wp-config.php';
        $downloadResult = $this->downloadFile($configFile);
        
        if ($downloadResult['success']) {
            $content = file_get_contents($downloadResult['local_path']);
            
            // Database name
            if (preg_match('/DB_NAME[\'"],\s*[\'"]([^\'\"]+)/', $content, $matches)) {
                $info['database'] = $matches[1];
            }
            
            // Debug mode
            $info['debug_mode'] = (strpos($content, 'WP_DEBUG', true) !== false);
            
            // Multisite
            $info['multisite'] = (strpos($content, 'MULTISITE', true) !== false);
            
            unlink($downloadResult['local_path']); // Cleanup
        }
        
        return $info;
    }
    
    /**
     * Quét bảo mật WordPress trên VPS
     */
    private function scanVPSSecurity($path) {
        $security = [
            'status' => 'secure',
            'issues' => [],
            'recommendations' => []
        ];
        
        // Check file permissions via SSH
        if ($this->connectionType === 'ssh') {
            $permCheckCommand = "find '$path' -type f -name '*.php' -perm /o+w | head -10";
            $result = $this->executeCommand($permCheckCommand);
            
            if ($result['success'] && !empty(trim($result['output']))) {
                $security['issues'][] = 'Found world-writable PHP files';
                $security['status'] = 'warning';
            }
            
            // Check for suspicious files
            $suspiciousCommand = "find '$path' -type f \( -name '*.php*' -o -name '*backup*' -o -name '*shell*' \) | grep -E '\.(php\d+|suspected|backup_)' | head -10";
            $result = $this->executeCommand($suspiciousCommand);
            
            if ($result['success'] && !empty(trim($result['output']))) {
                $security['issues'][] = 'Found suspicious files: ' . trim($result['output']);
                $security['status'] = 'critical';
            }
        }
        
        return $security;
    }
    
    /**
     * Quét file integrity trên VPS
     */
    private function scanVPSFileIntegrity($path) {
        $integrity = [
            'status' => 'healthy',
            'missing_files' => [],
            'extra_files' => [],
            'total_checked' => 0
        ];
        
        $coreFiles = [
            'wp-config.php',
            'wp-load.php',
            'wp-blog-header.php',
            'index.php',
            'wp-includes/wp-db.php',
            'wp-includes/functions.php',
            'wp-admin/index.php'
        ];
        
        foreach ($coreFiles as $file) {
            $integrity['total_checked']++;
            
            if ($this->connectionType === 'ssh') {
                $checkCommand = "test -f '$path/$file' && echo 'EXISTS' || echo 'MISSING'";
                $result = $this->executeCommand($checkCommand);
                
                if (!$result['success'] || trim($result['output']) === 'MISSING') {
                    $integrity['missing_files'][] = $file;
                    $integrity['status'] = 'warning';
                }
            }
        }
        
        return $integrity;
    }
    
    /**
     * Quét plugins và themes trên VPS
     */
    private function scanVPSPluginsThemes($path) {
        $results = [
            'plugins' => [],
            'themes' => [],
            'total_plugins' => 0,
            'total_themes' => 0
        ];
        
        if ($this->connectionType === 'ssh') {
            // Count plugins
            $pluginCountCommand = "find '$path/wp-content/plugins' -maxdepth 1 -type d | wc -l";
            $result = $this->executeCommand($pluginCountCommand);
            if ($result['success']) {
                $results['total_plugins'] = max(0, intval(trim($result['output'])) - 1);
            }
            
            // Count themes  
            $themeCountCommand = "find '$path/wp-content/themes' -maxdepth 1 -type d | wc -l";
            $result = $this->executeCommand($themeCountCommand);
            if ($result['success']) {
                $results['total_themes'] = max(0, intval(trim($result['output'])) - 1);
            }
            
            // List plugins
            $pluginListCommand = "find '$path/wp-content/plugins' -maxdepth 1 -type d -exec basename {} \; | tail -n +2 | head -10";
            $result = $this->executeCommand($pluginListCommand);
            if ($result['success']) {
                $plugins = explode("\n", trim($result['output']));
                foreach ($plugins as $plugin) {
                    if (!empty($plugin)) {
                        $results['plugins'][] = ['name' => $plugin, 'status' => 'unknown'];
                    }
                }
            }
            
            // List themes
            $themeListCommand = "find '$path/wp-content/themes' -maxdepth 1 -type d -exec basename {} \; | tail -n +2 | head -10";
            $result = $this->executeCommand($themeListCommand);
            if ($result['success']) {
                $themes = explode("\n", trim($result['output']));
                foreach ($themes as $theme) {
                    if (!empty($theme)) {
                        $results['themes'][] = ['name' => $theme, 'status' => 'unknown'];
                    }
                }
            }
        }
        
        return $results;
    }
    
    /**
     * Backup WordPress trên VPS
     */
    public function backupWordPressOnVPS($wordpressPath, $backupName = null) {
        if (!$this->isConnected || $this->connectionType !== 'ssh') {
            return ['error' => 'SSH connection required for backup'];
        }
        
        if (!$backupName) {
            $backupName = 'wordpress_backup_' . date('Y-m-d_H-i-s') . '.tar.gz';
        }
        
        $backupPath = "/tmp/$backupName";
        
        // Create backup command
        $backupCommand = "cd '$wordpressPath' && tar -czf '$backupPath' --exclude='wp-content/uploads/*' --exclude='*.log' . 2>/dev/null && echo 'SUCCESS' || echo 'FAILED'";
        
        $result = $this->executeCommand($backupCommand);
        
        if (!$result['success'] || trim($result['output']) !== 'SUCCESS') {
            return ['error' => 'Backup creation failed on VPS'];
        }
        
        // Get backup size
        $sizeCommand = "ls -lh '$backupPath' | awk '{print \$5}'";
        $sizeResult = $this->executeCommand($sizeCommand);
        $backupSize = $sizeResult['success'] ? trim($sizeResult['output']) : 'Unknown';
        
        return [
            'success' => true,
            'backup_path' => $backupPath,
            'backup_name' => $backupName,
            'backup_size' => $backupSize,
            'message' => "Backup created successfully on VPS"
        ];
    }
    
    /**
     * Download backup từ VPS
     */
    public function downloadBackup($remotePath, $localPath = null) {
        if (!$localPath) {
            $localPath = 'downloads/' . basename($remotePath);
        }
        
        // Create downloads directory if not exists
        $downloadDir = dirname($localPath);
        if (!is_dir($downloadDir)) {
            mkdir($downloadDir, 0755, true);
        }
        
        return $this->downloadFile($remotePath, $localPath);
    }
    
    /**
     * Đóng kết nối
     */
    public function disconnect() {
        if ($this->isConnected) {
            if ($this->connectionType === 'ftp') {
                ftp_close($this->connection);
            }
            // SSH connections close automatically
            
            $this->isConnected = false;
            $this->connection = null;
        }
    }
    
    /**
     * Get connection status
     */
    public function isConnected() {
        return $this->isConnected;
    }
    
    /**
     * Get connection info
     */
    public function getConnectionInfo() {
        return [
            'host' => $this->host,
            'username' => $this->username,
            'type' => $this->connectionType,
            'connected' => $this->isConnected
        ];
    }
    
    /**
     * Test VPS connection
     */
    public function testConnection() {
        if (!$this->isConnected) {
            return ['error' => 'Not connected'];
        }
        
        if ($this->connectionType === 'ssh') {
            $result = $this->executeCommand('echo "Connection test successful"');
            return $result['success'] ? 
                ['success' => true, 'message' => 'SSH connection is working'] : 
                ['error' => 'SSH connection test failed'];
        } else {
            $pwdResult = ftp_pwd($this->connection);
            return $pwdResult !== false ? 
                ['success' => true, 'message' => 'FTP connection is working', 'current_dir' => $pwdResult] : 
                ['error' => 'FTP connection test failed'];
        }
    }
    
    /**
     * Get VPS system info
     */
    public function getVPSSystemInfo() {
        if (!$this->isConnected || $this->connectionType !== 'ssh') {
            return ['error' => 'SSH connection required'];
        }
        
        $commands = [
            'hostname' => 'hostname',
            'os' => 'cat /etc/os-release | head -1',
            'kernel' => 'uname -r',
            'uptime' => 'uptime -p',
            'disk_space' => 'df -h / | tail -1',
            'memory' => 'free -h | grep Mem',
            'php_version' => 'php -v | head -1',
            'apache_nginx' => 'ps aux | grep -E "(apache|nginx)" | grep -v grep | wc -l'
        ];
        
        $info = [];
        foreach ($commands as $key => $command) {
            $result = $this->executeCommand($command);
            if ($result['success']) {
                $info[$key] = trim($result['output']);
            } else {
                $info[$key] = 'Unknown';
            }
        }
        
        return ['success' => true, 'system_info' => $info];
    }
}
?>