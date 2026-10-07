<?php
/**
 * Cloudflare Security Rules Demo with Expression Support
 * Demonstrates the new custom security rules functionality
 */

require_once 'config.php';
require_once 'CloudflareAPI.php';

try {
    $api = new CloudflareAPI();
    
    echo "=== CLOUDFLARE SECURITY RULES DEMO ===\n\n";
    
    // Get first zone for demo
    $zones = $api->getZones();
    if (!$zones['success'] || empty($zones['result'])) {
        throw new Exception('No zones found');
    }
    
    $zoneId = $zones['result'][0]['id'];
    $zoneName = $zones['result'][0]['name'];
    
    echo "Using zone: {$zoneName} ({$zoneId})\n\n";
    
    // 1. Show available security rule templates
    echo "=== AVAILABLE SECURITY RULE TEMPLATES ===\n";
    $templates = $api->getSecurityRuleTemplates();
    
    foreach ($templates as $key => $template) {
        echo "Template: {$key}\n";
        echo "  Name: {$template['name']}\n";
        echo "  Category: {$template['category']}\n";
        echo "  Description: {$template['description']}\n";
        echo "  Expression: {$template['expression']}\n";
        echo "  Action: {$template['action']}\n";
        echo "  ---\n";
    }
    echo "\n";
    
    // 2. Test expression validation
    echo "=== EXPRESSION VALIDATION TESTS ===\n";
    
    $testExpressions = [
        'ip.src eq 192.168.1.1',                           // Valid
        'ip.geoip.country in {"CN" "RU"}',                // Valid
        'http.user_agent contains "BadBot"',               // Valid
        'rate(ip.src, 1m) > 100',                         // Valid
        'ip.src == 192.168.1.1',                          // Invalid (wrong operator)
        'invalid expression with no pattern',              // Invalid
        'ip.src eq 192.168.1.1 and (http.host eq "test.com"' // Invalid (unmatched parens)
    ];
    
    foreach ($testExpressions as $expr) {
        $validation = $api->validateExpression($expr);
        echo "Expression: {$expr}\n";
        echo "  Valid: " . ($validation['valid'] ? 'Yes' : 'No') . "\n";
        if (!$validation['valid']) {
            echo "  Error: {$validation['error']}\n";
        } else {
            echo "  Status: {$validation['message']}\n";
        }
        echo "  ---\n";
    }
    echo "\n";
    
    // 3. Test security rule simulation
    echo "=== SECURITY RULE SIMULATION ===\n";
    
    $testExpression = 'ip.geoip.country in {"CN" "RU"}';
    $testRequests = [
        ['ip' => '192.168.1.1', 'user_agent' => 'Chrome', 'country' => 'US', 'path' => '/', 'method' => 'GET'],
        ['ip' => '10.0.0.1', 'user_agent' => 'Firefox', 'country' => 'CN', 'path' => '/admin', 'method' => 'POST'],
        ['ip' => '172.16.0.1', 'user_agent' => 'Bot', 'country' => 'RU', 'path' => '/api', 'method' => 'GET']
    ];
    
    $testResult = $api->testSecurityRule($zoneId, $testExpression, $testRequests);
    
    if ($testResult['success']) {
        echo "Testing expression: {$testExpression}\n";
        echo "Validation: " . ($testResult['validation']['valid'] ? 'Valid' : 'Invalid') . "\n\n";
        
        foreach ($testResult['test_results'] as $result) {
            echo "Request {$result['request_index']}:\n";
            echo "  IP: {$result['request']['ip']}\n";
            echo "  Country: {$result['request']['country']}\n";
            echo "  User Agent: {$result['request']['user_agent']}\n";
            echo "  Path: {$result['request']['path']}\n";
            echo "  Would match rule: " . ($result['would_match'] ? 'YES' : 'NO') . "\n";
            echo "  Note: {$result['note']}\n";
            echo "  ---\n";
        }
    } else {
        echo "Test failed: {$testResult['error']}\n";
    }
    echo "\n";
    
    // 4. Get current security rules summary
    echo "=== CURRENT SECURITY RULES SUMMARY ===\n";
    
    $summary = $api->getSecurityRulesSummary($zoneId);
    
    if ($summary['success']) {
        $stats = $summary['summary'];
        echo "Total Rules: {$stats['total_rules']}\n";
        echo "Enabled: {$stats['enabled_rules']}\n";
        echo "Disabled: {$stats['disabled_rules']}\n";
        echo "\nActions breakdown:\n";
        
        foreach ($stats['actions'] as $action => $count) {
            echo "  {$action}: {$count}\n";
        }
        
        echo "\nRecent rules:\n";
        foreach ($stats['recent_rules'] as $rule) {
            echo "  - {$rule['description']} ({$rule['action']}, " . 
                 ($rule['enabled'] ? 'enabled' : 'disabled') . ")\n";
        }
    } else {
        echo "Failed to get summary: {$summary['error']}\n";
    }
    echo "\n";
    
    // 5. Demo: Create a test security rule from template
    echo "=== CREATE TEST SECURITY RULE ===\n";
    
    $templateRule = $api->createSecurityRuleFromTemplate($zoneId, 'block_user_agent', [
        'expression' => 'http.user_agent contains "TestBot"',
        'description' => 'Block TestBot user agent - DEMO RULE'
    ]);
    
    if ($templateRule['success']) {
        echo "✅ Test rule created successfully!\n";
        echo "Rule ID: {$templateRule['rule']['id']}\n";
        echo "Filter ID: {$templateRule['filter']['id']}\n";
        echo "Expression: {$templateRule['expression']}\n";
        echo "Action: {$templateRule['action']}\n";
        
        $createdRuleId = $templateRule['rule']['id'];
        
        // Wait a moment then clean up
        echo "\nCleaning up test rule in 3 seconds...\n";
        sleep(3);
        
        // Delete the test rule
        $deleteResult = $api->deleteSecurityRule($zoneId, $createdRuleId, true);
        
        if ($deleteResult['success']) {
            echo "✅ Test rule deleted successfully!\n";
        } else {
            echo "❌ Failed to delete test rule: {$deleteResult['error']}\n";
        }
    } else {
        echo "❌ Failed to create test rule: {$templateRule['error']}\n";
    }
    echo "\n";
    
    // 6. Show expression building examples
    echo "=== EXPRESSION BUILDING EXAMPLES ===\n";
    
    $examples = [
        'Simple IP block' => 'ip.src eq 192.168.1.100',
        'Country block (multiple)' => 'ip.geoip.country in {"CN" "KP" "IR"}',
        'Rate limiting' => 'rate(ip.src, 10m) gt 50',
        'Bot score challenge' => 'cf.bot_management.score lt 30',
        'User agent contains' => 'http.user_agent contains "bot"',
        'Path-based block' => 'http.request.uri.path matches "^/wp-admin"',
        'Method-based rule' => 'http.request.method eq "POST"',
        'Host-based rule' => 'http.host eq "admin.example.com"',
        'ASN block' => 'ip.geoip.asnum eq 12345',
        'Complex rule' => 'ip.geoip.country eq "CN" and http.request.uri.path contains "/admin"',
        'Whitelist rule' => 'ip.src in {1.1.1.1 8.8.8.8 192.168.1.0/24}',
        'Headers check' => 'http.referer contains "malicious-site.com"',
        'SSL/TLS based' => 'ssl and http.request.version eq "HTTP/1.0"',
        'Time-based' => 'ip.src eq 192.168.1.1 and cf.edge.server_port eq 80'
    ];
    
    foreach ($examples as $description => $expression) {
        echo "{$description}:\n";
        echo "  {$expression}\n";
        
        $validation = $api->validateExpression($expression);
        echo "  Valid: " . ($validation['valid'] ? '✅' : '❌') . "\n";
        if (!$validation['valid']) {
            echo "  Error: {$validation['error']}\n";
        }
        echo "\n";
    }
    
    // 7. Show API statistics
    echo "=== API STATISTICS ===\n";
    $stats = $api->getAPIStats();
    echo "Total requests: {$stats['total_requests']}\n";
    echo "Cache hits: {$stats['cache_hits']}\n";
    echo "Cache hit rate: {$stats['cache_hit_rate']}%\n";
    echo "Cache enabled: " . ($stats['cache_enabled'] ? 'Yes' : 'No') . "\n";
    
    echo "\n=== DEMO COMPLETED ===\n";
    echo "Security rules functionality is now available in CloudflareAPI.php\n";
    echo "Use the methods to create powerful custom security rules with Cloudflare Expressions!\n\n";
    
    echo "Available methods:\n";
    echo "- createSecurityRule(\$zoneId, \$expression, \$action, \$description, \$enabled)\n";
    echo "- updateSecurityRule(\$zoneId, \$ruleId, \$expression, \$action, \$description, \$enabled)\n";
    echo "- deleteSecurityRule(\$zoneId, \$ruleId, \$deleteFilter)\n";
    echo "- validateExpression(\$expression)\n";
    echo "- getSecurityRuleTemplates()\n";
    echo "- createSecurityRuleFromTemplate(\$zoneId, \$templateKey, \$customValues)\n";
    echo "- bulkCreateSecurityRules(\$zoneId, \$rules)\n";
    echo "- getSecurityRulesSummary(\$zoneId)\n";
    echo "- testSecurityRule(\$zoneId, \$expression, \$testRequests)\n";
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
}