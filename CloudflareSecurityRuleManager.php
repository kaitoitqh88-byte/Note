<?php
/**
 * Cloudflare Security Rule Manager
 * Công cụ tạo custom security rules với Expression
 */

class CloudflareSecurityRuleManager {
    private $apiKey;
    private $email;
    private $zoneId;
    private $baseUrl = 'https://api.cloudflare.com/client/v4';
    private $configFile;
    
    public function __construct() {
        $this->configFile = __DIR__ . '/config.json';
        $this->loadConfig();
    }
    
    /**
     * Load configuration from file
     */
    private function loadConfig() {
        if (!file_exists($this->configFile)) {
            $this->printStatus("Config file not found. Please create config.json first.", "ERROR");
            return false;
        }
        
        $config = json_decode(file_get_contents($this->configFile), true);
        if (!$config) {
            $this->printStatus("Invalid config file format", "ERROR");
            return false;
        }
        
        $this->apiKey = $config['cloudflare']['api_key'] ?? '';
        $this->email = $config['cloudflare']['email'] ?? '';
        $this->zoneId = $config['cloudflare']['zone_id'] ?? '';
        
        if (empty($this->apiKey) || empty($this->email) || empty($this->zoneId)) {
            $this->printStatus("Missing Cloudflare credentials in config", "ERROR");
            return false;
        }
        
        return true;
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
     * Make API request to Cloudflare
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->baseUrl . $endpoint;
        
        $headers = [
            'X-Auth-Email: ' . $this->email,
            'X-Auth-Key: ' . $this->apiKey,
            'Content-Type: application/json'
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode !== 200 && $httpCode !== 201) {
            $this->printStatus("API request failed with HTTP $httpCode", "ERROR");
            if ($response) {
                $errorData = json_decode($response, true);
                if (isset($errorData['errors'][0]['message'])) {
                    $this->printStatus("Error: " . $errorData['errors'][0]['message'], "ERROR");
                }
            }
            return false;
        }
        
        return json_decode($response, true);
    }
    
    /**
     * Get existing security rules
     */
    public function getExistingRules() {
        $this->printStatus("Fetching existing security rules...", "INFO");
        
        $response = $this->makeRequest("/zones/{$this->zoneId}/firewall/rules");
        
        if (!$response || !$response['success']) {
            $this->printStatus("Failed to fetch existing rules", "ERROR");
            return [];
        }
        
        return $response['result'];
    }
    
    /**
     * Validate expression syntax
     */
    public function validateExpression($expression) {
        $this->printStatus("Validating expression...", "INFO");
        
        $data = [
            'expression' => $expression
        ];
        
        $response = $this->makeRequest("/zones/{$this->zoneId}/firewall/rules/validate", 'POST', $data);
        
        if (!$response || !$response['success']) {
            $this->printStatus("Expression validation failed", "ERROR");
            return false;
        }
        
        $this->printStatus("Expression is valid ✓", "SUCCESS");
        return true;
    }
    
    /**
     * Create custom security rule
     */
    public function createCustomRule($ruleName, $expression, $action = 'block', $priority = 100) {
        $this->printStatus("Creating custom security rule...", "INFO");
        
        // First validate expression
        if (!$this->validateExpression($expression)) {
            return false;
        }
        
        // Create filter first
        $filterData = [
            'expression' => $expression,
            'description' => "Filter for: $ruleName"
        ];
        
        $filterResponse = $this->makeRequest("/zones/{$this->zoneId}/filters", 'POST', $filterData);
        
        if (!$filterResponse || !$filterResponse['success']) {
            $this->printStatus("Failed to create filter", "ERROR");
            return false;
        }
        
        $filterId = $filterResponse['result']['id'];
        
        // Create rule
        $ruleData = [
            'filter' => ['id' => $filterId],
            'action' => $action,
            'priority' => $priority,
            'description' => $ruleName
        ];
        
        $ruleResponse = $this->makeRequest("/zones/{$this->zoneId}/firewall/rules", 'POST', $ruleData);
        
        if (!$ruleResponse || !$ruleResponse['success']) {
            $this->printStatus("Failed to create rule", "ERROR");
            return false;
        }
        
        $this->printStatus("Custom rule created successfully!", "SUCCESS");
        $this->printStatus("Rule ID: " . $ruleResponse['result']['id'], "INFO");
        
        return $ruleResponse['result'];
    }
    
    /**
     * Show rule templates and examples
     */
    public function showRuleTemplates() {
        echo "\n🛡️ SECURITY RULE TEMPLATES\n";
        echo str_repeat("=", 50) . "\n";
        
        $templates = [
            [
                'name' => 'Block specific country',
                'expression' => '(ip.geoip.country eq "XX")',
                'description' => 'Replace XX with country code (e.g., "CN", "RU")'
            ],
            [
                'name' => 'Block specific IP range',
                'expression' => '(ip.src in {192.168.1.0/24})',
                'description' => 'Replace with actual IP range'
            ],
            [
                'name' => 'Block user agents',
                'expression' => '(http.user_agent contains "badbot")',
                'description' => 'Replace "badbot" with actual user agent string'
            ],
            [
                'name' => 'Block specific paths',
                'expression' => '(http.request.uri.path contains "/admin" and ip.geoip.country ne "US")',
                'description' => 'Block admin access from non-US countries'
            ],
            [
                'name' => 'Rate limiting by IP',
                'expression' => '(rate_limit.requests_per_minute > 100)',
                'description' => 'Block IPs making more than 100 requests per minute'
            ],
            [
                'name' => 'Block SQL injection attempts',
                'expression' => '(http.request.uri.query contains "union" or http.request.uri.query contains "select")',
                'description' => 'Basic SQL injection protection'
            ],
            [
                'name' => 'Domain-specific rule',
                'expression' => '(http.host eq "example.com" and http.request.method eq "POST")',
                'description' => 'Target specific domain and HTTP method'
            ]
        ];
        
        foreach ($templates as $index => $template) {
            echo "\n" . ($index + 1) . ". {$template['name']}\n";
            echo "   Expression: {$template['expression']}\n";
            echo "   Description: {$template['description']}\n";
        }
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    /**
     * Interactive rule creator
     */
    public function createRuleInteractive() {
        echo "\n🔧 CREATE CUSTOM SECURITY RULE\n";
        echo str_repeat("=", 40) . "\n";
        
        // Get domain (optional)
        echo "Enter domain (optional, press Enter to skip): ";
        $domain = trim(fgets(STDIN));
        
        // Get rule name
        echo "Enter rule name/description: ";
        $ruleName = trim(fgets(STDIN));
        
        if (empty($ruleName)) {
            $this->printStatus("Rule name cannot be empty", "ERROR");
            return false;
        }
        
        // Show templates
        echo "\n📋 Would you like to see rule templates? (y/N): ";
        $showTemplates = trim(fgets(STDIN));
        if (strtolower($showTemplates) === 'y') {
            $this->showRuleTemplates();
        }
        
        // Get expression
        echo "\nEnter custom expression: ";
        $expression = trim(fgets(STDIN));
        
        if (empty($expression)) {
            $this->printStatus("Expression cannot be empty", "ERROR");
            return false;
        }
        
        // Add domain to expression if provided
        if (!empty($domain)) {
            if (strpos($expression, 'http.host') === false) {
                $domainCondition = '(http.host eq "' . $domain . '")';
                $expression = $domainCondition . ' and (' . $expression . ')';
                $this->printStatus("Added domain condition to expression", "INFO");
            }
        }
        
        // Get action
        echo "\nSelect action:\n";
        echo "1. Block (block)\n";
        echo "2. Challenge (challenge)\n";
        echo "3. JS Challenge (js_challenge)\n";
        echo "4. Allow (allow)\n";
        echo "5. Log (log)\n";
        echo "Choose action (1-5, default: 1): ";
        
        $actionChoice = trim(fgets(STDIN));
        $actions = ['1' => 'block', '2' => 'challenge', '3' => 'js_challenge', '4' => 'allow', '5' => 'log'];
        $action = $actions[$actionChoice] ?? 'block';
        
        // Get priority
        echo "\nEnter priority (1-1000, default: 100): ";
        $priorityInput = trim(fgets(STDIN));
        $priority = is_numeric($priorityInput) ? (int)$priorityInput : 100;
        
        // Show summary
        echo "\n📋 RULE SUMMARY\n";
        echo str_repeat("=", 25) . "\n";
        echo "Name: $ruleName\n";
        if (!empty($domain)) {
            echo "Target Domain: $domain\n";
        }
        echo "Expression: $expression\n";
        echo "Action: $action\n";
        echo "Priority: $priority\n";
        
        // Confirm creation
        echo "\nCreate this rule? (y/N): ";
        $confirm = trim(fgets(STDIN));
        
        if (strtolower($confirm) !== 'y') {
            $this->printStatus("Rule creation cancelled", "INFO");
            return false;
        }
        
        // Create the rule
        return $this->createCustomRule($ruleName, $expression, $action, $priority);
    }
    
    /**
     * List existing rules
     */
    public function listExistingRules() {
        echo "\n📋 EXISTING SECURITY RULES\n";
        echo str_repeat("=", 50) . "\n";
        
        $rules = $this->getExistingRules();
        
        if (empty($rules)) {
            echo "No security rules found.\n";
            return;
        }
        
        foreach ($rules as $rule) {
            $status = $rule['paused'] ? '❌ Disabled' : '✅ Active';
            
            echo "\n🛡️ {$rule['description']}\n";
            echo "   ID: {$rule['id']}\n";
            echo "   Status: $status\n";
            echo "   Action: {$rule['action']}\n";
            echo "   Priority: {$rule['priority']}\n";
            
            if (isset($rule['filter']['expression'])) {
                echo "   Expression: {$rule['filter']['expression']}\n";
            }
        }
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    /**
     * Test expression against sample data
     */
    public function testExpression() {
        echo "\n🧪 EXPRESSION TESTER\n";
        echo str_repeat("=", 25) . "\n";
        
        echo "Enter expression to test: ";
        $expression = trim(fgets(STDIN));
        
        if (empty($expression)) {
            $this->printStatus("Expression cannot be empty", "ERROR");
            return false;
        }
        
        // Validate the expression
        if ($this->validateExpression($expression)) {
            $this->printStatus("Expression syntax is valid!", "SUCCESS");
            
            // Show what the expression does
            echo "\n📖 Expression Analysis:\n";
            echo "Expression: $expression\n";
            
            // Basic analysis
            if (strpos($expression, 'ip.geoip.country') !== false) {
                echo "✓ Contains country-based filtering\n";
            }
            if (strpos($expression, 'http.host') !== false) {
                echo "✓ Contains domain-based filtering\n";
            }
            if (strpos($expression, 'http.user_agent') !== false) {
                echo "✓ Contains user agent filtering\n";
            }
            if (strpos($expression, 'ip.src') !== false) {
                echo "✓ Contains IP-based filtering\n";
            }
        }
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
    
    /**
     * Main menu for Security Rule Manager
     */
    public function showMenu() {
        while (true) {
            echo "\n" . str_repeat("=", 50) . "\n";
            echo "🛡️ CLOUDFLARE SECURITY RULE MANAGER\n";
            echo str_repeat("=", 50) . "\n";
            echo "1. 🔧 Create Custom Rule\n";
            echo "2. 📋 List Existing Rules\n";
            echo "3. 🧪 Test Expression\n";
            echo "4. 📚 View Rule Templates\n";
            echo "5. ⚙️ Check Configuration\n";
            echo "6. 🔙 Back to Main Menu\n\n";
            
            echo "Choose option (1-6): ";
            $choice = trim(fgets(STDIN));
            
            switch ($choice) {
                case '1':
                    $this->createRuleInteractive();
                    break;
                case '2':
                    $this->listExistingRules();
                    break;
                case '3':
                    $this->testExpression();
                    break;
                case '4':
                    $this->showRuleTemplates();
                    break;
                case '5':
                    $this->checkConfiguration();
                    break;
                case '6':
                    return; // Back to main menu
                default:
                    $this->printStatus("Invalid option. Please choose 1-6.", "WARNING");
            }
        }
    }
    
    /**
     * Check configuration status
     */
    private function checkConfiguration() {
        echo "\n⚙️ CONFIGURATION STATUS\n";
        echo str_repeat("=", 30) . "\n";
        
        echo "Config File: " . ($this->configFile) . "\n";
        echo "File Exists: " . (file_exists($this->configFile) ? '✅ Yes' : '❌ No') . "\n";
        
        if (file_exists($this->configFile)) {
            echo "API Key: " . (!empty($this->apiKey) ? '✅ Set' : '❌ Missing') . "\n";
            echo "Email: " . (!empty($this->email) ? '✅ Set (' . $this->email . ')' : '❌ Missing') . "\n";
            echo "Zone ID: " . (!empty($this->zoneId) ? '✅ Set' : '❌ Missing') . "\n";
            
            // Test API connection
            echo "\nTesting API connection...\n";
            $testResponse = $this->makeRequest("/zones/{$this->zoneId}");
            if ($testResponse && $testResponse['success']) {
                echo "API Connection: ✅ Working\n";
                echo "Domain: " . ($testResponse['result']['name'] ?? 'Unknown') . "\n";
            } else {
                echo "API Connection: ❌ Failed\n";
            }
        }
        
        echo "\nPress Enter to continue...";
        fgets(STDIN);
    }
}

// Check if this file is run directly
if (basename(__FILE__) == basename($_SERVER['SCRIPT_NAME'])) {
    echo "🛡️ Cloudflare Security Rule Manager\n";
    echo "===================================\n";
    
    $manager = new CloudflareSecurityRuleManager();
    $manager->showMenu();
}