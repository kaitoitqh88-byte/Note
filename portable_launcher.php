<?php
/**
 * Cloudflare Security Tool - Portable PHP Launcher
 * Cross-platform launcher that works on Windows, macOS, and Linux
 * 
 * Usage: php portable_launcher.php [--start]
 */

// Include API Secret Key Manager
require_once __DIR__ . '/APISecretKeyManager.php';

// Include Security Rule Manager
require_once __DIR__ . '/CloudflareSecurityRuleManager.php';

// Include Domain Security Manager
require_once __DIR__ . '/CloudflareDomainSecurityManager.php';

class CloudflareSecurityToolLauncher {
    private $scriptDir;
    private $configFile;
    private $toolFile;
    private $port;
    private $serverProcess;
    private $isWindows;
    
    public function __construct() {
        $this->scriptDir = __DIR__;
        $this->configFile = $this->scriptDir . DIRECTORY_SEPARATOR . 'config.json';
        $this->toolFile = $this->scriptDir . DIRECTORY_SEPARATOR . 'cloudflare_security_tool.html';
        $this->port = null;
        $this->serverProcess = null;
        $this->isWindows = (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN');
    }
    
    /**
     * Print header with tool info
     */
    public function printHeader() {
        echo str_repeat("=", 50) . "\n";
        echo "🛡️  CLOUDFLARE SECURITY TOOL\n";
        echo "    Portable Cross-Platform PHP Launcher\n";
        echo str_repeat("=", 50) . "\n\n";
    }
    
    /**
     * Print status message with icon
     */
    public function printStatus($message, $level = "INFO") {
        $icons = [
            "INFO" => "ℹ️",
            "SUCCESS" => "✅", 
            "WARNING" => "⚠️",
            "ERROR" => "❌"
        ];
        
        $icon = isset($icons[$level]) ? $icons[$level] : "ℹ️";
        echo "$icon $message\n";
    }
    
    /**
     * Find available port starting from given port
     */
    public function findFreePort($startPort = 8080) {
        for ($port = $startPort; $port < $startPort + 100; $port++) {
            $socket = @fsockopen('127.0.0.1', $port, $errno, $errstr, 1);
            if (!$socket) {
                return $port; // Port is available
            }
            fclose($socket);
        }
        throw new Exception("No free ports found");
    }
    
    /**
     * Check if required files exist
     */
    public function checkFiles() {
        if (!file_exists($this->toolFile)) {
            $this->printStatus("Tool file not found: {$this->toolFile}", "ERROR");
            return false;
        }
        
        if (!file_exists($this->configFile)) {
            $this->printStatus("Config file not found, creating template...", "WARNING");
            $this->createConfigTemplate();
        }
        
        return true;
    }
    
    /**
     * Create configuration template file
     */
    public function createConfigTemplate() {
        $config = [
            "cloudflare" => [
                "email" => "",
                "api_key" => "",
                "zone_id" => ""
            ],
            "tool" => [
                "theme" => "default",
                "language" => "vi",
                "auto_refresh" => true,
                "refresh_interval" => 30,
                "port" => 8080
            ],
            "security" => [
                "require_confirmation" => true,
                "log_actions" => true,
                "backup_before_delete" => true
            ]
        ];
        
        try {
            file_put_contents($this->configFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            $this->printStatus("Created config template: {$this->configFile}", "SUCCESS");
        } catch (Exception $e) {
            $this->printStatus("Error creating config: " . $e->getMessage(), "ERROR");
        }
    }
    
    /**
     * Load configuration from file
     */
    public function loadConfig() {
        try {
            $config = json_decode(file_get_contents($this->configFile), true);
            return $config ?: [];
        } catch (Exception $e) {
            $this->printStatus("Error loading config: " . $e->getMessage(), "WARNING");
            return [];
        }
    }
    
    /**
     * Start built-in PHP web server
     */
    public function startServer() {
        try {
            $config = $this->loadConfig();
            $preferredPort = isset($config['tool']['port']) ? $config['tool']['port'] : 8080;
            
            $this->port = $this->findFreePort($preferredPort);
            
            $this->printStatus("Starting PHP server on port {$this->port}...", "INFO");
            $this->printStatus("Local URL: http://localhost:{$this->port}", "INFO");
            
            // Change to script directory
            chdir($this->scriptDir);
            
            // Start PHP built-in server
            $command = "php -S localhost:{$this->port}";
            
            if ($this->isWindows) {
                // Windows: Start in background
                $this->serverProcess = popen("start /B $command", "r");
            } else {
                // Unix: Start in background
                $this->serverProcess = popen("$command > /dev/null 2>&1 &", "r");
            }
            
            // Give server time to start
            sleep(2);
            
            return true;
            
        } catch (Exception $e) {
            $this->printStatus("Error starting server: " . $e->getMessage(), "ERROR");
            return false;
        }
    }
    
    /**
     * Open tool in default browser
     */
    public function openBrowser() {
        try {
            $url = "http://localhost:{$this->port}/cloudflare_security_tool.html";
            
            $this->printStatus("Opening browser...", "INFO");
            sleep(1); // Give server time to start
            
            if ($this->isWindows) {
                exec("start $url");
            } elseif (PHP_OS === 'Darwin') {
                exec("open '$url'");
            } else {
                exec("xdg-open '$url' 2>/dev/null || firefox '$url' 2>/dev/null &");
            }
            
            return true;
            
        } catch (Exception $e) {
            $this->printStatus("Error opening browser: " . $e->getMessage(), "WARNING");
            $this->printStatus("Please manually open: http://localhost:{$this->port}/cloudflare_security_tool.html", "INFO");
            return false;
        }
    }
    
    /**
     * Show interactive menu
     */
    public function showMenu() {
        while (true) {
            echo "\n" . str_repeat("=", 30) . "\n";
            echo "🛡️  TOOL OPTIONS\n";
            echo str_repeat("=", 30) . "\n";
            echo "1. 🚀 Start Server & Open Browser\n";
            echo "2. 🌐 Open in Browser (if server running)\n";
            echo "3. ⚙️  Edit Configuration\n";
            echo "4. 📁 Open Tool Directory\n";
            echo "5. ℹ️  Show Tool Info\n";
            echo "6. 🔐 API Key Management\n";
            echo "7. 🛡️ Security Rule Manager\n";
            echo "8. 🌐 Domain Security Manager\n";
            echo "9. 🛑 Stop Server & Exit\n\n";
            
            echo "Choose option (1-9): ";
            $choice = trim(fgets(STDIN));
            
            switch ($choice) {
                case '1':
                    $this->startTool();
                    break;
                case '2':
                    if ($this->port) {
                        $this->openBrowser();
                    } else {
                        $this->printStatus("Server not running. Choose option 1 first.", "WARNING");
                    }
                    break;
                case '3':
                    $this->editConfig();
                    break;
                case '4':
                    $this->openDirectory();
                    break;
                case '5':
                    $this->showInfo();
                    break;
                case '6':
                    $this->manageAPIKeys();
                    break;
                case '7':
                    $this->manageSecurityRules();
                    break;
                case '8':
                    $this->manageDomainSecurity();
                    break;
                case '9':
                    $this->stopAndExit();
                    return;
                default:
                    $this->printStatus("Invalid option. Please choose 1-9.", "WARNING");
            }
        }
    }
    
    /**
     * Start the tool (server + browser)
     */
    public function startTool() {
        if ($this->checkFiles()) {
            if ($this->startServer()) {
                $this->openBrowser();
                $this->printStatus("Tool started successfully!", "SUCCESS");
                $this->printStatus("Press Ctrl+C to stop the server", "INFO");
            } else {
                $this->printStatus("Failed to start server", "ERROR");
            }
        } else {
            $this->printStatus("Cannot start tool - missing files", "ERROR");
        }
    }
    
    /**
     * Edit configuration file
     */
    public function editConfig() {
        $this->printStatus("Configuration file: {$this->configFile}", "INFO");
        
        if ($this->isWindows) {
            exec("notepad \"{$this->configFile}\"");
        } elseif (PHP_OS === 'Darwin') {
            exec("open -a TextEdit \"{$this->configFile}\"");
        } else {
            // Linux: Try different editors
            $editors = ['nano', 'vim', 'gedit', 'kate'];
            $editorFound = false;
            
            foreach ($editors as $editor) {
                if (exec("which $editor 2>/dev/null")) {
                    exec("$editor \"{$this->configFile}\"");
                    $editorFound = true;
                    break;
                }
            }
            
            if (!$editorFound) {
                $this->printStatus("No text editor found. Please edit manually:", "WARNING");
                echo "  {$this->configFile}\n";
            }
        }
    }
    
    /**
     * Open tool directory
     */
    public function openDirectory() {
        if ($this->isWindows) {
            exec("explorer \"{$this->scriptDir}\"");
        } elseif (PHP_OS === 'Darwin') {
            exec("open \"{$this->scriptDir}\"");
        } else {
            exec("xdg-open \"{$this->scriptDir}\"");
        }
    }
    
    /**
     * Show tool information
     */
    public function showInfo() {
        echo "\n📋 TOOL INFORMATION\n";
        echo str_repeat("=", 30) . "\n";
        echo "📁 Directory: {$this->scriptDir}\n";
        echo "🌐 Tool File: {$this->toolFile}\n";
        echo "⚙️  Config File: {$this->configFile}\n";
        echo "🖥️  Platform: " . PHP_OS . "\n";
        echo "🐘 PHP Version: " . PHP_VERSION . "\n";
        
        if ($this->port) {
            echo "🚀 Server: Running on port {$this->port}\n";
            echo "🔗 URL: http://localhost:{$this->port}/cloudflare_security_tool.html\n";
        } else {
            echo "🛑 Server: Not running\n";
        }
        
        // Show config status
        $config = $this->loadConfig();
        $cfEmail = isset($config['cloudflare']['email']) ? $config['cloudflare']['email'] : '';
        
        if ($cfEmail) {
            echo "📧 Cloudflare Email: $cfEmail\n";
            echo "✅ Configuration appears to be set up\n";
        } else {
            echo "⚠️  Cloudflare configuration not set up\n";
        }
    }
    
    /**
     * Manage API Keys
     */
    public function manageAPIKeys() {
        try {
            $apiManager = new APISecretKeyManager();
            $this->showAPIKeyMenu($apiManager);
        } catch (Exception $e) {
            $this->printStatus("Error loading API manager: " . $e->getMessage(), "ERROR");
        }
    }
    
    /**
     * Show API Key management menu
     */
    private function showAPIKeyMenu($apiManager) {
        while (true) {
            echo "\n" . str_repeat("=", 40) . "\n";
            echo "🔐 API KEY MANAGEMENT\n";
            echo str_repeat("=", 40) . "\n";
            echo "1. 📋 List All API Keys\n";
            echo "2. 🔑 Generate New API Key\n";
            echo "3. ❌ Revoke API Key\n";
            echo "4. 📊 View Key Usage Stats\n";
            echo "5. 🔧 Key Settings\n";
            echo "6. 🔙 Back to Main Menu\n\n";
            
            echo "Choose option (1-6): ";
            $choice = trim(fgets(STDIN));
            
            switch ($choice) {
                case '1':
                    $this->listAPIKeys($apiManager);
                    break;
                case '2':
                    $this->generateAPIKey($apiManager);
                    break;
                case '3':
                    $this->revokeAPIKey($apiManager);
                    break;
                case '4':
                    $this->viewKeyStats($apiManager);
                    break;
                case '5':
                    $this->keySettings($apiManager);
                    break;
                case '6':
                    return; // Back to main menu
                default:
                    $this->printStatus("Invalid option. Please choose 1-6.", "WARNING");
            }
        }
    }
    
    /**
     * List all API keys
     */
    private function listAPIKeys($apiManager) {
        echo "\n📋 API KEYS LIST\n";
        echo str_repeat("=", 50) . "\n";
        
        $reflection = new ReflectionClass($apiManager);
        $loadDataMethod = $reflection->getMethod('loadData');
        $loadDataMethod->setAccessible(true);
        $data = $loadDataMethod->invoke($apiManager);
        
        if (empty($data['api_keys'])) {
            echo "❌ No API keys found.\n";
            echo "\nPress Enter to continue...";
            fgets(STDIN);
            return;
        }
        
        foreach ($data['api_keys'] as $key => $keyData) {
            $status = $keyData['active'] ? '🟢 Active' : '🔴 Disabled';
            $expires = $keyData['expires'] ? $keyData['expires'] : 'Never';
            
            echo "\n🔑 Key: ..." . substr($key, -8) . "\n";
            echo "   📝 Name: {$keyData['name']}\n";
            echo "   📊 Status: $status\n";
            echo "   📅 Created: {$keyData['created']}\n";
            echo "   ⏰ Expires: $expires\n";
            echo "   🔢 Usage: {$keyData['usage_count']} times\n";
            echo "   🛡️ Permissions: " . implode(', ', $keyData['permissions']) . "\n";
        }
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    /**
     * Generate new API key
     */
    private function generateAPIKey($apiManager) {
        echo "\n🔑 GENERATE NEW API KEY\n";
        echo str_repeat("=", 30) . "\n";
        
        echo "Enter key name: ";
        $name = trim(fgets(STDIN));
        if (empty($name)) {
            $this->printStatus("Key name cannot be empty", "ERROR");
            return;
        }
        
        echo "\nSelect permissions (comma separated):\n";
        echo "Available: scan, backup, manage, admin\n";
        echo "Enter permissions: ";
        $permissionsInput = trim(fgets(STDIN));
        $permissions = !empty($permissionsInput) ? 
            array_map('trim', explode(',', $permissionsInput)) : 
            ['scan'];
        
        echo "\nExpiration (days from now, press Enter for no expiration): ";
        $expirationInput = trim(fgets(STDIN));
        $expiresIn = !empty($expirationInput) && is_numeric($expirationInput) ? 
            (int)$expirationInput * 24 * 60 * 60 : null;
        
        try {
            $newKey = $apiManager->generateAPIKey($name, $permissions, $expiresIn);
            
            echo "\n✅ API Key Generated Successfully!\n";
            echo str_repeat("=", 50) . "\n";
            echo "🔑 Key: $newKey\n";
            echo "📝 Name: $name\n";
            echo "🛡️ Permissions: " . implode(', ', $permissions) . "\n";
            if ($expiresIn) {
                echo "⏰ Expires: " . date('Y-m-d H:i:s', time() + $expiresIn) . "\n";
            }
            echo "\n⚠️ IMPORTANT: Save this key securely. It won't be shown again.\n";
            
        } catch (Exception $e) {
            $this->printStatus("Error generating key: " . $e->getMessage(), "ERROR");
        }
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    /**
     * Revoke API key
     */
    private function revokeAPIKey($apiManager) {
        echo "\n❌ REVOKE API KEY\n";
        echo str_repeat("=", 20) . "\n";
        
        // First show available keys
        $reflection = new ReflectionClass($apiManager);
        $loadDataMethod = $reflection->getMethod('loadData');
        $loadDataMethod->setAccessible(true);
        $data = $loadDataMethod->invoke($apiManager);
        
        if (empty($data['api_keys'])) {
            echo "❌ No API keys found to revoke.\n";
            echo "\nPress Enter to continue...";
            fgets(STDIN);
            return;
        }
        
        echo "Available keys:\n";
        foreach ($data['api_keys'] as $key => $keyData) {
            if ($keyData['active']) {
                echo "🔑 ..." . substr($key, -8) . " - {$keyData['name']}\n";
            }
        }
        
        echo "\nEnter last 8 characters of key to revoke: ";
        $keyPart = trim(fgets(STDIN));
        
        if (strlen($keyPart) !== 8) {
            $this->printStatus("Please enter exactly 8 characters", "ERROR");
            return;
        }
        
        // Find matching key
        $matchedKey = null;
        foreach ($data['api_keys'] as $key => $keyData) {
            if (substr($key, -8) === $keyPart) {
                $matchedKey = $key;
                break;
            }
        }
        
        if (!$matchedKey) {
            $this->printStatus("Key not found", "ERROR");
            return;
        }
        
        echo "\nConfirm revoke key '{$data['api_keys'][$matchedKey]['name']}'? (y/N): ";
        $confirm = trim(fgets(STDIN));
        
        if (strtolower($confirm) === 'y') {
            $data['api_keys'][$matchedKey]['active'] = false;
            $saveDataMethod = $reflection->getMethod('saveData');
            $saveDataMethod->setAccessible(true);
            $saveDataMethod->invoke($apiManager, $data);
            
            $this->printStatus("Key revoked successfully", "SUCCESS");
        } else {
            $this->printStatus("Operation cancelled", "INFO");
        }
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    /**
     * View key usage statistics
     */
    private function viewKeyStats($apiManager) {
        echo "\n📊 API KEY USAGE STATISTICS\n";
        echo str_repeat("=", 35) . "\n";
        
        $reflection = new ReflectionClass($apiManager);
        $loadDataMethod = $reflection->getMethod('loadData');
        $loadDataMethod->setAccessible(true);
        $data = $loadDataMethod->invoke($apiManager);
        
        if (empty($data['api_keys'])) {
            echo "❌ No API keys found.\n";
            echo "\nPress Enter to continue...";
            fgets(STDIN);
            return;
        }
        
        $totalKeys = count($data['api_keys']);
        $activeKeys = 0;
        $totalUsage = 0;
        $mostUsedKey = null;
        $maxUsage = 0;
        
        foreach ($data['api_keys'] as $key => $keyData) {
            if ($keyData['active']) $activeKeys++;
            $totalUsage += $keyData['usage_count'];
            
            if ($keyData['usage_count'] > $maxUsage) {
                $maxUsage = $keyData['usage_count'];
                $mostUsedKey = $keyData['name'];
            }
        }
        
        echo "📈 Total Keys: $totalKeys\n";
        echo "🟢 Active Keys: $activeKeys\n";
        echo "🔴 Inactive Keys: " . ($totalKeys - $activeKeys) . "\n";
        echo "⚡ Total API Calls: $totalUsage\n";
        
        if ($mostUsedKey) {
            echo "🏆 Most Used Key: $mostUsedKey ($maxUsage calls)\n";
        }
        
        // Show recent access logs
        if (!empty($data['access_logs'])) {
            echo "\n📋 Recent Access (Last 5):\n";
            $recentLogs = array_slice(array_reverse($data['access_logs']), 0, 5);
            foreach ($recentLogs as $log) {
                echo "   🕒 {$log['timestamp']} - {$log['key_name']} from {$log['ip_address']}\n";
            }
        }
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    /**
     * Key settings management
     */
    private function keySettings($apiManager) {
        echo "\n🔧 API KEY SETTINGS\n";
        echo str_repeat("=", 25) . "\n";
        
        $reflection = new ReflectionClass($apiManager);
        $loadDataMethod = $reflection->getMethod('loadData');
        $loadDataMethod->setAccessible(true);
        $data = $loadDataMethod->invoke($apiManager);
        
        $settings = $data['settings'] ?? [];
        
        echo "Current Settings:\n";
        echo "🔐 Require API Key: " . ($settings['require_api_key'] ? 'Yes' : 'No') . "\n";
        echo "⏱️ Session Timeout: " . ($settings['session_timeout'] ?? 3600) . " seconds\n";
        echo "🔢 Max Login Attempts: " . ($settings['max_attempts'] ?? 5) . "\n";
        echo "🚫 Lockout Time: " . ($settings['lockout_time'] ?? 900) . " seconds\n";
        
        echo "\n1. Toggle API Key Requirement\n";
        echo "2. Change Session Timeout\n";
        echo "3. Change Max Attempts\n";
        echo "4. Change Lockout Time\n";
        echo "5. Back to API Menu\n\n";
        
        echo "Choose option (1-5): ";
        $choice = trim(fgets(STDIN));
        
        switch ($choice) {
            case '1':
                $data['settings']['require_api_key'] = !($settings['require_api_key'] ?? true);
                $status = $data['settings']['require_api_key'] ? 'enabled' : 'disabled';
                $this->printStatus("API Key requirement $status", "SUCCESS");
                break;
                
            case '2':
                echo "Enter new session timeout (seconds): ";
                $timeout = trim(fgets(STDIN));
                if (is_numeric($timeout) && $timeout > 0) {
                    $data['settings']['session_timeout'] = (int)$timeout;
                    $this->printStatus("Session timeout updated", "SUCCESS");
                } else {
                    $this->printStatus("Invalid timeout value", "ERROR");
                }
                break;
                
            case '3':
                echo "Enter max login attempts: ";
                $attempts = trim(fgets(STDIN));
                if (is_numeric($attempts) && $attempts > 0) {
                    $data['settings']['max_attempts'] = (int)$attempts;
                    $this->printStatus("Max attempts updated", "SUCCESS");
                } else {
                    $this->printStatus("Invalid attempts value", "ERROR");
                }
                break;
                
            case '4':
                echo "Enter lockout time (seconds): ";
                $lockout = trim(fgets(STDIN));
                if (is_numeric($lockout) && $lockout > 0) {
                    $data['settings']['lockout_time'] = (int)$lockout;
                    $this->printStatus("Lockout time updated", "SUCCESS");
                } else {
                    $this->printStatus("Invalid lockout value", "ERROR");
                }
                break;
                
            case '5':
                return;
                
            default:
                $this->printStatus("Invalid option", "WARNING");
                return;
        }
        
        if ($choice >= '1' && $choice <= '4') {
            $saveDataMethod = $reflection->getMethod('saveData');
            $saveDataMethod->setAccessible(true);
            $saveDataMethod->invoke($apiManager, $data);
        }
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    /**
     * Manage Security Rules
     */
    public function manageSecurityRules() {
        try {
            $ruleManager = new CloudflareSecurityRuleManager();
            $ruleManager->showMenu();
        } catch (Exception $e) {
            $this->printStatus("Error loading Security Rule Manager: " . $e->getMessage(), "ERROR");
        }
    }
    
    /**
     * Manage Domain Security
     */
    public function manageDomainSecurity() {
        try {
            $domainManager = new CloudflareDomainSecurityManager();
            $domainManager->run();
        } catch (Exception $e) {
            $this->printStatus("Error loading Domain Security Manager: " . $e->getMessage(), "ERROR");
        }
    }
    
    /**
     * Stop server and exit
     */
    public function stopAndExit() {
        if ($this->serverProcess) {
            $this->printStatus("Stopping server...", "INFO");
            if (is_resource($this->serverProcess)) {
                pclose($this->serverProcess);
            }
            
            // Try to kill PHP server process
            if ($this->isWindows) {
                exec("taskkill /F /IM php.exe 2>nul");
            } else {
                exec("pkill -f 'php -S localhost'");
            }
        }
        
        $this->printStatus("Goodbye! 👋", "SUCCESS");
    }
    
    /**
     * Run the launcher
     */
    public function run($args = []) {
        try {
            $this->printHeader();
            
            // Check if tool should start directly
            if (in_array('--start', $args)) {
                $this->startTool();
                
                // Keep running until interrupted
                echo "\nServer is running. Press Ctrl+C to stop.\n";
                
                // Install signal handler for graceful shutdown
                if (function_exists('pcntl_signal')) {
                    pcntl_signal(SIGINT, [$this, 'stopAndExit']);
                    pcntl_signal(SIGTERM, [$this, 'stopAndExit']);
                    
                    while (true) {
                        pcntl_signal_dispatch();
                        sleep(1);
                    }
                } else {
                    // Fallback for systems without pcntl
                    while (true) {
                        sleep(1);
                    }
                }
            } else {
                // Show interactive menu
                $this->showMenu();
            }
            
        } catch (Exception $e) {
            $this->printStatus("Unexpected error: " . $e->getMessage(), "ERROR");
            exit(1);
        }
    }
}

/**
 * Main entry point
 */
function main($argv) {
    $launcher = new CloudflareSecurityToolLauncher();
    $launcher->run(array_slice($argv, 1));
}

// Auto-start if running from command line
if (isset($argv)) {
    main($argv);
}

?>