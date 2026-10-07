<?php
/**
 * Tools Menu - Integrated Tools Management Interface
 * Giao diện quản lý tích hợp các tools
 */

require_once 'APISecretKeyManager.php';
require_once 'CloudflareSecurityRuleManager.php';
require_once 'CloudflareDomainSecurityManager.php';
require_once 'config.php';

class ToolsMenu {
    private $apiSecretManager;
    private $securityRuleManager;
    private $domainSecurityManager;
    
    public function __construct() {
        $this->apiSecretManager = new APISecretKeyManager();
        $this->securityRuleManager = new CloudflareSecurityRuleManager();
        $this->domainSecurityManager = new CloudflareDomainSecurityManager();
    }
    
    /**
     * Print status with color coding
     */
    private function printStatus($message, $type = "INFO") {
        $colors = [
            "SUCCESS" => "\033[32m✅ ",
            "ERROR" => "\033[31m❌ ",
            "WARNING" => "\033[33m⚠️  ",
            "INFO" => "\033[36mℹ️  "
        ];
        
        $color = $colors[$type] ?? $colors["INFO"];
        echo $color . $message . "\033[0m\n";
    }
    
    /**
     * Show main tools menu
     */
    public function showMainMenu() {
        while (true) {
            echo "\n" . str_repeat("=", 60) . "\n";
            echo "🛠️  CLOUDFLARE TOOLS MANAGEMENT CENTER\n";
            echo str_repeat("=", 60) . "\n";
            echo "🔧 SECURITY & AUTHENTICATION TOOLS:\n";
            echo "  1. 🔐 API Key Management\n";
            echo "  2. 🛡️  Security Rules Manager\n";
            echo "  3. 🌐 Domain Security Manager\n";
            echo "  4. 🔒 Authentication Dashboard\n\n";
            
            echo "🌐 CLOUDFLARE MANAGEMENT TOOLS:\n";
            echo "  5. 🌍 DNS Management\n";
            echo "  6. 📊 Analytics Dashboard\n";
            echo "  7. 💰 Cache Management\n";
            echo "  8. 🔍 Domain Search\n\n";
            
            echo "🔧 UTILITY TOOLS:\n";
            echo "  9. ⚙️  Configuration Manager\n";
            echo " 10. 📋 System Information\n";
            echo " 11. 🧪 Tool Testing\n\n";
            
            echo "📦 LAUNCHER OPTIONS:\n";
            echo " 12. 🚀 Start Web Launcher\n";
            echo " 13. 🌐 Open Web Interface\n";
            echo " 14. 🚪 Exit\n\n";
            
            echo " 13. ❌ Exit Tools Menu\n\n";
            
            echo "Choose tool (1-13): ";
            $choice = trim(fgets(STDIN));
            
            switch ($choice) {
                case '1':
                    $this->launchAPIKeyManager();
                    break;
                case '2':
                    $this->launchSecurityRuleManager();
                    break;
                case '3':
                    $this->launchAuthDashboard();
                    break;
                case '4':
                    $this->launchDNSManager();
                    break;
                case '5':
                    $this->launchAnalytics();
                    break;
                case '6':
                    $this->launchCacheManager();
                    break;
                case '7':
                    $this->launchDomainSearch();
                    break;
                case '8':
                    $this->launchConfigManager();
                    break;
                case '9':
                    $this->showSystemInfo();
                    break;
                case '10':
                    $this->launchToolTesting();
                    break;
                case '11':
                    $this->startWebLauncher();
                    break;
                case '12':
                    $this->openWebInterface();
                    break;
                case '13':
                    $this->printStatus("Exiting Tools Menu. Goodbye! 👋", "SUCCESS");
                    return;
                default:
                    $this->printStatus("Invalid option. Please choose 1-13.", "WARNING");
            }
        }
    }
    
    /**
     * Launch API Key Manager
     */
    private function launchAPIKeyManager() {
        echo "\n🔐 LAUNCHING API KEY MANAGER\n";
        echo str_repeat("=", 40) . "\n";
        
        try {
            $this->showAPIKeyMenu();
        } catch (Exception $e) {
            $this->printStatus("Error launching API Key Manager: " . $e->getMessage(), "ERROR");
        }
    }
    
    /**
     * Show API Key management menu
     */
    private function showAPIKeyMenu() {
        while (true) {
            echo "\n" . str_repeat("=", 45) . "\n";
            echo "🔐 API KEY MANAGEMENT\n";
            echo str_repeat("=", 45) . "\n";
            echo "1. 📋 List All API Keys\n";
            echo "2. 🔑 Generate New API Key\n";
            echo "3. ❌ Revoke API Key\n";
            echo "4. 📊 View Key Usage Stats\n";
            echo "5. 🔧 Key Settings\n";
            echo "6. 🧪 Test API Key\n";
            echo "7. 📁 Export API Keys\n";
            echo "8. 📥 Import API Keys\n";
            echo "9. 🔙 Back to Main Menu\n\n";
            
            echo "Choose option (1-9): ";
            $choice = trim(fgets(STDIN));
            
            switch ($choice) {
                case '1':
                    $this->listAPIKeys();
                    break;
                case '2':
                    $this->generateAPIKey();
                    break;
                case '3':
                    $this->revokeAPIKey();
                    break;
                case '4':
                    $this->viewKeyStats();
                    break;
                case '5':
                    $this->keySettings();
                    break;
                case '6':
                    $this->testAPIKey();
                    break;
                case '7':
                    $this->exportAPIKeys();
                    break;
                case '8':
                    $this->importAPIKeys();
                    break;
                case '9':
                    return; // Back to main menu
                default:
                    $this->printStatus("Invalid option. Please choose 1-9.", "WARNING");
            }
        }
    }
    
    /**
     * Launch Security Rule Manager
     */
    private function launchSecurityRuleManager() {
        echo "\n🛡️ LAUNCHING SECURITY RULE MANAGER\n";
        echo str_repeat("=", 45) . "\n";
        
        try {
            $this->securityRuleManager->showMenu();
        } catch (Exception $e) {
            $this->printStatus("Error launching Security Rule Manager: " . $e->getMessage(), "ERROR");
        }
    }
    
    /**
     * Launch Domain Security Manager
     */
    private function launchDomainSecurityManager() {
        echo "\n🌐 LAUNCHING DOMAIN SECURITY MANAGER\n";
        echo str_repeat("=", 50) . "\n";
        echo "Advanced domain-based security rule management\n";
        echo "Features: Domain targeting, Custom expressions, Rule templates\n\n";
        
        try {
            $this->domainSecurityManager->run();
        } catch (Exception $e) {
            $this->printStatus("Error launching Domain Security Manager: " . $e->getMessage(), "ERROR");
        }
    }
    
    /**
     * Launch Authentication Dashboard
     */
    private function launchAuthDashboard() {
        echo "\n🔒 AUTHENTICATION DASHBOARD\n";
        echo str_repeat("=", 35) . "\n";
        
        // Show auth statistics
        $this->showAuthStats();
        
        echo "\n1. 👥 User Management\n";
        echo "2. 🔐 Session Management\n";
        echo "3. 📊 Login Statistics\n";
        echo "4. 🔒 Security Settings\n";
        echo "5. 🔙 Back to Main Menu\n\n";
        
        echo "Choose option (1-5): ";
        $choice = trim(fgets(STDIN));
        
        switch ($choice) {
            case '1':
                $this->userManagement();
                break;
            case '2':
                $this->sessionManagement();
                break;
            case '3':
                $this->loginStats();
                break;
            case '4':
                $this->securitySettings();
                break;
            case '5':
                return;
            default:
                $this->printStatus("Invalid option. Please choose 1-5.", "WARNING");
        }
    }
    
    /**
     * Launch DNS Manager
     */
    private function launchDNSManager() {
        echo "\n🌍 DNS MANAGEMENT TOOL\n";
        echo str_repeat("=", 30) . "\n";
        
        $this->printStatus("DNS Manager will open in web interface", "INFO");
        $this->printStatus("URL: http://localhost:8080/?action=dns", "INFO");
        
        // Try to open web browser
        $this->openURL("http://localhost:8080/?action=dns");
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    /**
     * Launch Analytics
     */
    private function launchAnalytics() {
        echo "\n📊 ANALYTICS DASHBOARD\n";
        echo str_repeat("=", 30) . "\n";
        
        $this->printStatus("Analytics Dashboard will open in web interface", "INFO");
        $this->printStatus("URL: http://localhost:8080/?action=analytics", "INFO");
        
        $this->openURL("http://localhost:8080/?action=analytics");
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    /**
     * Launch Cache Manager
     */
    private function launchCacheManager() {
        echo "\n💰 CACHE MANAGEMENT\n";
        echo str_repeat("=", 25) . "\n";
        
        $this->printStatus("Cache Manager will open in web interface", "INFO");
        $this->printStatus("URL: http://localhost:8080/?action=cache", "INFO");
        
        $this->openURL("http://localhost:8080/?action=cache");
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    /**
     * Launch Domain Search
     */
    private function launchDomainSearch() {
        echo "\n🔍 DOMAIN SEARCH TOOL\n";
        echo str_repeat("=", 30) . "\n";
        
        $this->printStatus("Domain Search will open in web interface", "INFO");
        $this->printStatus("URL: http://localhost:8080/?action=search", "INFO");
        
        $this->openURL("http://localhost:8080/?action=search");
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    /**
     * Launch Configuration Manager
     */
    private function launchConfigManager() {
        echo "\n⚙️ CONFIGURATION MANAGER\n";
        echo str_repeat("=", 35) . "\n";
        
        $configFile = __DIR__ . '/config.json';
        
        echo "Current configuration:\n";
        if (file_exists($configFile)) {
            $config = json_decode(file_get_contents($configFile), true);
            if ($config) {
                echo "📧 Email: " . ($config['cloudflare']['email'] ?? 'Not set') . "\n";
                echo "🔑 API Key: " . (isset($config['cloudflare']['api_key']) ? '***' . substr($config['cloudflare']['api_key'], -5) : 'Not set') . "\n";
                echo "🌍 Zone ID: " . ($config['cloudflare']['zone_id'] ?? 'Not set') . "\n";
            }
        } else {
            echo "❌ Configuration file not found\n";
        }
        
        echo "\n1. ✏️ Edit Configuration\n";
        echo "2. 🔄 Reset Configuration\n";
        echo "3. ✅ Test Configuration\n";
        echo "4. 🔙 Back to Main Menu\n\n";
        
        echo "Choose option (1-4): ";
        $choice = trim(fgets(STDIN));
        
        switch ($choice) {
            case '1':
                $this->editConfig();
                break;
            case '2':
                $this->resetConfig();
                break;
            case '3':
                $this->testConfig();
                break;
            case '4':
                return;
            default:
                $this->printStatus("Invalid option.", "WARNING");
        }
    }
    
    /**
     * Show System Information
     */
    private function showSystemInfo() {
        echo "\n📋 SYSTEM INFORMATION\n";
        echo str_repeat("=", 30) . "\n";
        
        echo "🖥️ Operating System: " . PHP_OS . "\n";
        echo "🐘 PHP Version: " . PHP_VERSION . "\n";
        echo "📁 Current Directory: " . __DIR__ . "\n";
        echo "🕐 Current Time: " . date('Y-m-d H:i:s') . "\n";
        echo "💾 Memory Usage: " . round(memory_get_usage() / 1024 / 1024, 2) . " MB\n";
        
        // Check file permissions
        $files = ['config.json', 'APISecretKeyManager.php', 'CloudflareSecurityRuleManager.php'];
        echo "\n📁 File Status:\n";
        foreach ($files as $file) {
            $path = __DIR__ . '/' . $file;
            if (file_exists($path)) {
                echo "  ✅ $file - " . filesize($path) . " bytes\n";
            } else {
                echo "  ❌ $file - Not found\n";
            }
        }
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    /**
     * Launch Tool Testing
     */
    private function launchToolTesting() {
        echo "\n🧪 TOOL TESTING INTERFACE\n";
        echo str_repeat("=", 35) . "\n";
        
        echo "1. 🔐 Test API Key Manager\n";
        echo "2. 🛡️ Test Security Rules\n";
        echo "3. 🌍 Test DNS Connection\n";
        echo "4. 📊 Test Analytics API\n";
        echo "5. 🔙 Back to Main Menu\n\n";
        
        echo "Choose test (1-5): ";
        $choice = trim(fgets(STDIN));
        
        switch ($choice) {
            case '1':
                $this->testAPIKeyManager();
                break;
            case '2':
                $this->testSecurityRules();
                break;
            case '3':
                $this->testDNSConnection();
                break;
            case '4':
                $this->testAnalyticsAPI();
                break;
            case '5':
                return;
            default:
                $this->printStatus("Invalid option.", "WARNING");
        }
    }
    
    /**
     * Start Web Launcher
     */
    private function startWebLauncher() {
        echo "\n🚀 STARTING WEB LAUNCHER\n";
        echo str_repeat("=", 30) . "\n";
        
        if (file_exists(__DIR__ . '/portable_launcher.php')) {
            $this->printStatus("Starting PHP portable launcher...", "INFO");
            
            // Check if server is already running
            $socket = @fsockopen('127.0.0.1', 8080, $errno, $errstr, 1);
            if ($socket) {
                fclose($socket);
                $this->printStatus("Server already running on port 8080", "WARNING");
            } else {
                // Start the launcher
                if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                    exec("start /B php portable_launcher.php", $output, $return);
                } else {
                    exec("php portable_launcher.php > /dev/null 2>&1 &", $output, $return);
                }
                
                $this->printStatus("Web launcher started!", "SUCCESS");
            }
            
            $this->printStatus("Access at: http://localhost:8080", "INFO");
        } else {
            $this->printStatus("portable_launcher.php not found", "ERROR");
        }
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    /**
     * Open Web Interface
     */
    private function openWebInterface() {
        echo "\n🌐 OPENING WEB INTERFACE\n";
        echo str_repeat("=", 35) . "\n";
        
        $url = "http://localhost:8080";
        $this->printStatus("Opening web interface: $url", "INFO");
        
        $this->openURL($url);
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    /**
     * Helper method to open URL in browser
     */
    private function openURL($url) {
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("start $url");
        } elseif (PHP_OS === 'Darwin') {
            exec("open '$url'");
        } else {
            exec("xdg-open '$url' 2>/dev/null || firefox '$url' 2>/dev/null &");
        }
    }
    
    // API Key Management Methods
    private function listAPIKeys() {
        echo "\n📋 API KEYS LIST\n";
        echo str_repeat("=", 20) . "\n";
        
        $reflection = new ReflectionClass($this->apiSecretManager);
        $loadDataMethod = $reflection->getMethod('loadData');
        $loadDataMethod->setAccessible(true);
        $data = $loadDataMethod->invoke($this->apiSecretManager);
        
        if (empty($data['api_keys'])) {
            echo "❌ No API keys found.\n";
        } else {
            foreach ($data['api_keys'] as $key => $keyData) {
                $status = $keyData['active'] ? '🟢 Active' : '🔴 Disabled';
                echo "\n🔑 Key: ..." . substr($key, -8) . "\n";
                echo "   📝 Name: {$keyData['name']}\n";
                echo "   📊 Status: $status\n";
                echo "   📅 Created: {$keyData['created']}\n";
                echo "   🔢 Usage: {$keyData['usage_count']} times\n";
            }
        }
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    private function generateAPIKey() {
        echo "\n🔑 GENERATE NEW API KEY\n";
        echo str_repeat("=", 25) . "\n";
        
        echo "Enter key name: ";
        $name = trim(fgets(STDIN));
        if (empty($name)) {
            $this->printStatus("Key name cannot be empty", "ERROR");
            return;
        }
        
        echo "Enter permissions (comma separated): ";
        $permissionsInput = trim(fgets(STDIN));
        $permissions = !empty($permissionsInput) ? 
            array_map('trim', explode(',', $permissionsInput)) : 
            ['scan'];
        
        try {
            $newKey = $this->apiSecretManager->generateAPIKey($name, $permissions);
            
            echo "\n✅ API Key Generated Successfully!\n";
            echo "🔑 Key: $newKey\n";
            echo "📝 Name: $name\n";
            echo "🛡️ Permissions: " . implode(', ', $permissions) . "\n";
            echo "\n⚠️ IMPORTANT: Save this key securely. It won't be shown again.\n";
            
        } catch (Exception $e) {
            $this->printStatus("Error generating key: " . $e->getMessage(), "ERROR");
        }
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    private function revokeAPIKey() {
        echo "\n❌ REVOKE API KEY\n";
        echo str_repeat("=", 20) . "\n";
        
        // Implementation for revoking API key
        $this->printStatus("API Key revocation feature", "INFO");
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    private function viewKeyStats() {
        echo "\n📊 API KEY STATISTICS\n";
        echo str_repeat("=", 25) . "\n";
        
        // Implementation for viewing key statistics
        $this->printStatus("Key statistics feature", "INFO");
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    private function keySettings() {
        echo "\n🔧 KEY SETTINGS\n";
        echo str_repeat("=", 15) . "\n";
        
        // Implementation for key settings
        $this->printStatus("Key settings feature", "INFO");
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    private function testAPIKey() {
        echo "\n🧪 TEST API KEY\n";
        echo str_repeat("=", 15) . "\n";
        
        // Implementation for testing API key
        $this->printStatus("API key testing feature", "INFO");
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    private function exportAPIKeys() {
        echo "\n📁 EXPORT API KEYS\n";
        echo str_repeat("=", 20) . "\n";
        
        try {
            $reflection = new ReflectionClass($this->apiSecretManager);
            $loadDataMethod = $reflection->getMethod('loadData');
            $loadDataMethod->setAccessible(true);
            $data = $loadDataMethod->invoke($this->apiSecretManager);
            
            $exportFile = 'api_keys_export_' . date('Y-m-d_H-i-s') . '.json';
            file_put_contents($exportFile, json_encode($data, JSON_PRETTY_PRINT));
            
            $this->printStatus("API keys exported to: $exportFile", "SUCCESS");
        } catch (Exception $e) {
            $this->printStatus("Export failed: " . $e->getMessage(), "ERROR");
        }
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    private function importAPIKeys() {
        echo "\n📥 IMPORT API KEYS\n";
        echo str_repeat("=", 20) . "\n";
        
        echo "Enter import file path: ";
        $importFile = trim(fgets(STDIN));
        
        if (!file_exists($importFile)) {
            $this->printStatus("File not found: $importFile", "ERROR");
            return;
        }
        
        try {
            $importData = json_decode(file_get_contents($importFile), true);
            if ($importData) {
                $this->printStatus("Import simulation - file loaded successfully", "SUCCESS");
                $this->printStatus("Found " . count($importData['api_keys'] ?? []) . " API keys", "INFO");
            } else {
                $this->printStatus("Invalid JSON format", "ERROR");
            }
        } catch (Exception $e) {
            $this->printStatus("Import failed: " . $e->getMessage(), "ERROR");
        }
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    // Authentication Dashboard Methods
    private function showAuthStats() {
        $reflection = new ReflectionClass($this->apiSecretManager);
        $loadDataMethod = $reflection->getMethod('loadData');
        $loadDataMethod->setAccessible(true);
        $data = $loadDataMethod->invoke($this->apiSecretManager);
        
        $totalKeys = count($data['api_keys'] ?? []);
        $activeKeys = 0;
        $totalUsage = 0;
        
        foreach (($data['api_keys'] ?? []) as $keyData) {
            if ($keyData['active']) $activeKeys++;
            $totalUsage += $keyData['usage_count'];
        }
        
        echo "📊 Authentication Statistics:\n";
        echo "   🔑 Total API Keys: $totalKeys\n";
        echo "   🟢 Active Keys: $activeKeys\n";
        echo "   ⚡ Total API Calls: $totalUsage\n";
        echo "   📊 Recent Access Logs: " . count($data['access_logs'] ?? []) . "\n";
    }
    
    private function userManagement() {
        $this->printStatus("User management feature - coming soon", "INFO");
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    private function sessionManagement() {
        $this->printStatus("Session management feature - coming soon", "INFO");
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    private function loginStats() {
        $this->printStatus("Login statistics feature - coming soon", "INFO");
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    private function securitySettings() {
        $this->printStatus("Security settings feature - coming soon", "INFO");
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    // Configuration Methods
    private function editConfig() {
        $configFile = __DIR__ . '/config.json';
        
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            exec("notepad \"$configFile\"");
        } elseif (PHP_OS === 'Darwin') {
            exec("open -a TextEdit \"$configFile\"");
        } else {
            exec("nano \"$configFile\"");
        }
    }
    
    private function resetConfig() {
        $this->printStatus("Reset configuration feature - coming soon", "INFO");
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    private function testConfig() {
        $this->printStatus("Test configuration feature - coming soon", "INFO");
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    // Testing Methods
    private function testAPIKeyManager() {
        $this->printStatus("Testing API Key Manager...", "INFO");
        
        try {
            $testManager = new APISecretKeyManager();
            $this->printStatus("API Key Manager loaded successfully", "SUCCESS");
        } catch (Exception $e) {
            $this->printStatus("API Key Manager test failed: " . $e->getMessage(), "ERROR");
        }
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    private function testSecurityRules() {
        $this->printStatus("Testing Security Rules Manager...", "INFO");
        
        try {
            $testManager = new CloudflareSecurityRuleManager();
            $this->printStatus("Security Rules Manager loaded successfully", "SUCCESS");
        } catch (Exception $e) {
            $this->printStatus("Security Rules Manager test failed: " . $e->getMessage(), "ERROR");
        }
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    private function testDNSConnection() {
        $this->printStatus("Testing DNS connection - feature coming soon", "INFO");
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    private function testAnalyticsAPI() {
        $this->printStatus("Testing Analytics API - feature coming soon", "INFO");
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
}

// Check if this file is run directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "🛠️ Cloudflare Tools Management Center\n";
    echo "======================================\n";
    echo "🚀 Integrated Tools Interface\n\n";
    
    $toolsMenu = new ToolsMenu();
    $toolsMenu->showMainMenu();
}