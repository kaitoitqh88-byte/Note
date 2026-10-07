# 🔒 Cloudflare Security Rules with Expression Support

This enhanced CloudflareAPI.php now includes comprehensive support for creating and managing custom security rules using Cloudflare's powerful Expression language.

## 🆕 New Features Added

### Core Security Rules API Methods

- **`createSecurityRule()`** - Create custom security rules with Expression support
- **`updateSecurityRule()`** - Update existing security rules
- **`deleteSecurityRule()`** - Delete security rules and their filters
- **`getFirewallRules()`** - Get all firewall rules for a zone
- **`getFilters()`** - Get all filters for a zone

### Expression Validation & Testing

- **`validateExpression()`** - Validate Expression syntax before creating rules
- **`testSecurityRule()`** - Simulate how a rule would behave against test requests
- **`getSecurityRuleTemplates()`** - Get pre-built rule templates
- **`createSecurityRuleFromTemplate()`** - Create rules from templates

### Bulk Operations & Management

- **`bulkCreateSecurityRules()`** - Create multiple rules efficiently
- **`getSecurityRulesSummary()`** - Get statistics and overview of all rules

## 📋 Available Rule Templates

The API includes 10 pre-built security rule templates:

1. **Block Country** - Block traffic from specific countries
2. **Block IP Range** - Block specific IP addresses or ranges
3. **Rate Limiting** - Rate limit requests per IP address
4. **Challenge Suspicious Traffic** - Challenge traffic with low bot scores
5. **Block User Agent** - Block requests with specific user agents
6. **Allow Whitelist** - Allow whitelisted IP addresses
7. **Block Path** - Block access to specific URL paths
8. **JS Challenge Crawlers** - Challenge automated crawlers
9. **Block ASN** - Block traffic from specific ASN numbers
10. **Bypass Trusted** - Bypass security for trusted sources

## 🎯 Supported Actions

- **`block`** - Block the request
- **`allow`** - Allow the request
- **`challenge`** - Legacy CAPTCHA challenge
- **`managed_challenge`** - Cloudflare Managed Challenge
- **`js_challenge`** - JavaScript Challenge
- **`log`** - Log only (no blocking)
- **`bypass`** - Bypass other security features

## 💻 Usage Examples

### 1. Create a Simple IP Block Rule

```php
<?php
require_once 'CloudflareAPI.php';

$api = new CloudflareAPI();
$zoneId = 'your-zone-id';

$result = $api->createSecurityRule(
    $zoneId,
    'ip.src eq 192.168.1.100',       // Expression
    'block',                          // Action
    'Block specific IP address',      // Description
    true                             // Enabled
);

if ($result['success']) {
    echo "Rule created: " . $result['rule']['id'];
} else {
    echo "Error: " . $result['error'];
}
?>
```

### 2. Create a Country Block Rule

```php
$result = $api->createSecurityRule(
    $zoneId,
    'ip.geoip.country in {"CN" "RU" "KP"}',
    'block',
    'Block traffic from suspicious countries'
);
```

### 3. Create a Rate Limiting Rule

```php
$result = $api->createSecurityRule(
    $zoneId,
    'rate(ip.src, 1m) gt 50',
    'block',
    'Block IPs making more than 50 requests per minute'
);
```

### 4. Create a Bot Management Rule

```php
$result = $api->createSecurityRule(
    $zoneId,
    'cf.bot_management.score lt 30',
    'managed_challenge',
    'Challenge requests with low bot score'
);
```

### 5. Create from Template

```php
$result = $api->createSecurityRuleFromTemplate(
    $zoneId,
    'block_user_agent',
    [
        'expression' => 'http.user_agent contains "BadBot"',
        'description' => 'Block BadBot crawler'
    ]
);
```

### 6. Validate Expression Before Creating

```php
$expression = 'ip.src eq 192.168.1.1 and http.host eq "example.com"';
$validation = $api->validateExpression($expression);

if ($validation['valid']) {
    $result = $api->createSecurityRule($zoneId, $expression, 'block', 'Complex rule');
} else {
    echo "Invalid expression: " . $validation['error'];
}
```

### 7. Test Expression Against Sample Requests

```php
$testRequests = [
    ['ip' => '192.168.1.1', 'country' => 'US', 'user_agent' => 'Chrome'],
    ['ip' => '10.0.0.1', 'country' => 'CN', 'user_agent' => 'BadBot']
];

$testResult = $api->testSecurityRule($zoneId, $expression, $testRequests);

foreach ($testResult['test_results'] as $result) {
    echo "Request would " . ($result['would_match'] ? 'MATCH' : 'NOT MATCH') . "\n";
}
```

### 8. Bulk Create Multiple Rules

```php
$rules = [
    [
        'expression' => 'ip.geoip.country eq "CN"',
        'action' => 'block',
        'description' => 'Block China'
    ],
    [
        'expression' => 'http.user_agent contains "bot"',
        'action' => 'js_challenge',
        'description' => 'Challenge bots'
    ]
];

$result = $api->bulkCreateSecurityRules($zoneId, $rules);
echo "Created {$result['successful']} out of {$result['total_rules']} rules";
```

### 9. Get Security Rules Summary

```php
$summary = $api->getSecurityRulesSummary($zoneId);

if ($summary['success']) {
    $stats = $summary['summary'];
    echo "Total rules: {$stats['total_rules']}\n";
    echo "Enabled: {$stats['enabled_rules']}\n";
    echo "Block actions: {$stats['actions']['block']}\n";
}
```

### 10. Update Existing Rule

```php
$result = $api->updateSecurityRule(
    $zoneId,
    'rule-id-here',
    'ip.geoip.country in {"CN" "RU" "IR"}',  // New expression
    'block',                                  // New action
    'Updated country block rule',             // New description
    true                                      // Enable rule
);
```

## 🧪 Expression Language Examples

### IP-Based Rules
```
ip.src eq 192.168.1.1                    # Exact IP
ip.src in {192.168.1.1 10.0.0.1}        # Multiple IPs
ip.src in {192.168.1.0/24}              # IP range
```

### Geographic Rules
```
ip.geoip.country eq "CN"                 # Single country
ip.geoip.country in {"CN" "RU" "KP"}     # Multiple countries
ip.geoip.asnum eq 12345                  # Specific ASN
```

### HTTP-Based Rules
```
http.request.method eq "POST"            # HTTP method
http.host eq "admin.example.com"         # Specific host
http.request.uri.path contains "/admin"  # Path contains
http.request.uri.path matches "^/api/"   # Path regex
http.user_agent contains "bot"           # User agent contains
http.referer contains "malicious.com"    # Referrer check
```

### Rate Limiting Rules
```
rate(ip.src, 1m) gt 50                   # 50 requests per minute
rate(ip.src, 5m) gt 100                  # 100 requests per 5 minutes
rate(ip.geoip.country, 1m) gt 1000       # Country-based rate limit
```

### Bot Management Rules
```
cf.bot_management.score lt 30            # Low bot score
cf.bot_management.verified_bot           # Verified bots
not cf.bot_management.verified_bot       # Non-verified bots
```

### Complex Combined Rules
```
ip.geoip.country eq "CN" and http.request.uri.path contains "/admin"
http.user_agent contains "bot" or http.user_agent contains "crawler"
ip.src in {192.168.1.0/24} and http.request.method eq "POST"
rate(ip.src, 1m) gt 10 and not ip.src in {1.1.1.1 8.8.8.8}
```

## 🎨 Web Interface

Use the included `security_rules_ui.html` for a user-friendly interface to:

- ✅ Create rules using visual expression builder
- ✅ Select from pre-built templates
- ✅ Validate expressions in real-time
- ✅ Test rules against sample requests
- ✅ View current rules and statistics
- ✅ Manage existing rules

## 🚀 Demo Script

Run `security_rules_demo.php` to see all features in action:

```bash
php security_rules_demo.php
```

The demo will:
- Show available templates
- Test expression validation
- Simulate rule testing
- Show current rules summary
- Create and delete a test rule
- Display expression examples

## 🔧 Advanced Features

### Expression Validation
- Syntax checking for common errors
- Operator validation (eq vs ==)
- Parentheses matching
- Pattern recognition

### Rule Testing
- Simulate rule behavior without creating
- Test against custom request scenarios
- See which requests would match

### Cache Integration
- Security rules and filters are cached
- Automatic cache invalidation on updates
- Optimized for performance

### Error Handling
- Comprehensive error messages
- Graceful fallback on API failures
- Detailed logging support

## 📚 Expression Language Reference

### Operators
- **Comparison**: `eq`, `ne`, `gt`, `lt`, `ge`, `le`
- **String**: `contains`, `matches`, `starts_with`, `ends_with`
- **Lists**: `in`, `not in`
- **Logical**: `and`, `or`, `not`

### Common Fields
- **IP**: `ip.src`, `ip.geoip.country`, `ip.geoip.asnum`
- **HTTP**: `http.host`, `http.request.method`, `http.request.uri.path`
- **User Agent**: `http.user_agent`, `http.referer`
- **Bot Management**: `cf.bot_management.score`, `cf.bot_management.verified_bot`
- **Rate Limiting**: `rate(field, window)`

### Functions
- **`rate(field, window)`** - Rate limiting function
- **`lookup_json_string(json, key)`** - JSON lookup
- **`len(string)`** - String length
- **`lower(string)`** - Lowercase conversion

## ⚠️ Important Notes

1. **API Limits**: Cloudflare has limits on number of rules per zone
2. **Expression Complexity**: Keep expressions simple for better performance
3. **Testing**: Always test rules before enabling in production
4. **Caching**: Rules may take 1-2 minutes to propagate globally
5. **Permissions**: Ensure your API token has firewall permissions

## 🐛 Troubleshooting

### Common Issues

1. **Invalid Expression Error**
   - Check syntax using `validateExpression()`
   - Use 'eq' instead of '=='
   - Ensure parentheses are balanced

2. **Rule Creation Failed**
   - Verify API token permissions
   - Check zone ID is correct
   - Ensure expression is valid

3. **Rule Not Working**
   - Allow 1-2 minutes for propagation
   - Check rule is enabled
   - Verify expression matches intended traffic

### Getting Help

- Check the validation error messages
- Use the test function to debug expressions
- Review Cloudflare Expression documentation
- Check API response details in error messages

---

🎉 **Your CloudflareAPI.php now has powerful security rules capabilities with Expression support!**