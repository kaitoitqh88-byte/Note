# WordPress Scanner API Secret Key System

## 🔑 API Authentication System

WordPress Scanner giờ đã được bảo mật bằng **API Secret Key system** với các tính năng enterprise-grade security.

## 🚀 Quick Start

### 1. First Time Setup
```bash
# Truy cập setup page để tạo API key đầu tiên
http://yoursite.com/api_setup.php
```

### 2. Login với API Key
```bash  
# Truy cập WordPress Scanner
http://yoursite.com/index.php?action=wordpress

# Nhập API key có format: wpsk_[64_random_characters]
```

### 3. Permissions System
| Permission | Description |
|------------|-------------|
| **scan** | WordPress scanning (local & basic features) |
| **backup** | Backup creation & download capabilities |
| **vps** | VPS remote connection & scanning |
| **admin** | API key management & system admin |

## 🔐 Security Features

### Authentication
- **64-character cryptographically secure API keys**
- **Session-based authentication** with automatic expiration (1 hour)
- **IP-based lockout protection** (5 failed attempts = 15 min lockout)
- **Role-based permissions** system

### VPS Security
- **Secure connection tokens** generated per VPS connection
- **Auto-expiring tokens** (5 minutes for connections, 1 minute for tests)
- **No credential storage** - tokens used once and discarded
- **Connection validation** with host matching

### Monitoring & Logging
- **Access logging** with IP tracking và user agent
- **Failed attempt tracking** với automatic lockouts  
- **Usage statistics** per API key
- **Security audit trail** for all actions

## 📊 API Key Management

### Generate New API Key
```php
// Via web interface: Go to API Keys management
// Or programmatically:
$keyManager = new APISecretKeyManager();
$newKey = $keyManager->generateAPIKey(
    'Key Name',
    ['scan', 'backup', 'vps'], // permissions
    2592000 // expires in 30 days (optional)
);
```

### Validate API Key
```php
$keyManager = new APISecretKeyManager();
$validation = $keyManager->validateAPIKey($apiKey, 'scan');

if ($validation['valid']) {
    // Proceed with action
    echo "Welcome " . $validation['key_name'];
} else {
    // Handle error
    echo "Error: " . $validation['error'];
}
```

### Create Authenticated Session
```php
$sessionResult = $keyManager->createSession($apiKey);
if ($sessionResult['success']) {
    // Session created, redirect to main app
    header('Location: main_app.php');
}
```

## 🌐 VPS Secure Connection

### VPS Token System
```php
// Generate secure VPS token
$vpsToken = $keyManager->generateVPSToken($vpsHost, 300); // 5 minutes

// Validate VPS token before connection
if ($keyManager->validateVPSToken($token, $expectedHost)) {
    // Proceed with VPS connection
    $vps = new VPSConnectionHandler();
    $vps->connectSSH(...);
}
```

### Connection Security
- **Host validation**: Tokens tied to specific VPS hosts
- **Expiration**: Short-lived tokens (auto-expire)
- **One-time use**: Tokens invalidated after use
- **Secure transmission**: No plain-text credential storage

## 🛠️ Integration Examples

### Basic Authentication Check
```php
require_once 'APISecretKeyManager.php';

// Require authentication for any page
$authResult = requireAPIAuthentication();
if ($authResult['valid']) {
    echo "User: " . $authResult['key_name'];
    echo "Permissions: " . implode(', ', $authResult['permissions']);
}
```

### Permission-based Access Control
```php
// Check specific permission
$authResult = requireAPIAuthentication('vps');

// Or check in code
if (in_array('backup', getCurrentPermissions())) {
    // Show backup functionality
    showBackupOptions();
}
```

### VPS Connection với Security  
```php
// Authenticated VPS scan
if (in_array('vps', getCurrentPermissions())) {
    $vpsConfig = [
        'host' => $_POST['vps_host'],
        'username' => $_POST['vps_username'],
        'password' => $_POST['vps_password'],
        'security_token' => $keyManager->generateVPSToken($_POST['vps_host'])
    ];
    
    $scanner = new WordPressHandler();
    $results = $scanner->scanWordPressOnVPS($vpsConfig, $wpPath);
}
```

## 📱 Usage Workflows

### Administrator Workflow
1. **Setup**: Generate administrator API key
2. **Login**: Authenticate với admin key
3. **Manage**: Create keys for other users với specific permissions
4. **Monitor**: Review access logs và security stats
5. **Audit**: Track key usage và failed attempts

### Scanner User Workflow  
1. **Receive**: Get API key from administrator
2. **Login**: Authenticate với provided key
3. **Scan**: Perform WordPress scans (local/VPS based on permissions)
4. **Backup**: Create/download backups if authorized
5. **Monitor**: View own activity và scan results

### VPS Management Workflow
1. **Authenticate**: Login với VPS-enabled key
2. **Connect**: Add VPS credentials (encrypted transmission)
3. **Test**: Verify VPS connection với secure token
4. **Scan**: Perform remote WordPress analysis
5. **Backup**: Create và download backups từ VPS

## 🔧 Configuration

### API Settings (in `Data_Config/api_keys.json`)
```json
{
  "settings": {
    "require_api_key": true,
    "session_timeout": 3600,
    "max_attempts": 5,
    "lockout_time": 900
  }
}
```

### Customization Options  
- **Session timeout**: Adjust session duration
- **Failed attempt limits**: Configure lockout thresholds
- **Token expiration**: Modify VPS token lifetimes
- **Permission sets**: Create custom permission combinations

## 🚨 Security Best Practices

### For Administrators
- **Keep admin keys secure** - never share or expose them
- **Regular key rotation** - generate new keys periodically
- **Monitor access logs** - check for suspicious activity
- **Limit permissions** - grant only necessary permissions per user
- **Review failed attempts** - investigate lockout events

### For Users
- **Store keys securely** - don't save in plain text files
- **Never share keys** - each user should have their own key
- **Logout when done** - don't leave sessions open  
- **Report issues** - notify admin of any security concerns
- **Use strong VPS credentials** - secure your remote connections

### For VPS Access
- **Use SSH keys** when possible instead of passwords
- **Secure VPS credentials** - strong passwords và 2FA
- **Monitor VPS access** - check server logs for connections
- **Update VPS systems** - keep remote servers patched
- **Network security** - use VPN or secure networks

## 📁 File Structure

```
WordPress Scanner with API Auth/
├── APISecretKeyManager.php      # Core API authentication
├── APIKeyAuthInterface.php      # Authentication UI
├── VPSConnectionHandler.php     # VPS secure connections  
├── WordPressHandler.php         # Main scanner với auth integration
├── api_setup.php               # First-time API key setup
├── Data_Config/                # Encrypted API key storage
│   └── api_keys.json          # Secure key database
├── downloads/                  # Secure backup downloads
│   └── .htaccess              # Access protection
└── VPS_SCANNER_README.md       # VPS guide

Integration Files:
├── index.php                   # Updated với auth requirements  
├── homepage.php               # Updated với auth navigation
└── includes/navigation.php    # Updated menu với auth status
```

## 🔍 Troubleshooting

### Common Issues

**"API key required" error:**
- Ensure you have a valid API key
- Check key format: must start with `wpsk_`
- Verify key is active and not expired

**"Permission denied" errors:**  
- Check your key permissions
- Contact admin to update permissions
- Ensure you have required permission for the action

**VPS connection failures:**
- Verify VPS credentials are correct
- Check if php-ssh2 extension is installed
- Ensure VPS allows SSH connections
- Check network connectivity

**Session expired:**
- Re-authenticate với your API key
- Sessions expire after 1 hour of inactivity
- IP changes invalidate sessions

### Debug Mode
```php
// Enable logging for troubleshooting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Check API key validation
$keyManager = new APISecretKeyManager();
$result = $keyManager->validateAPIKey($yourKey);
var_dump($result); // See validation details
```

### Support
- **Documentation**: Check this README và inline code comments
- **Logs**: Review access logs in API key management
- **Security**: Monitor failed attempts và lockout events
- **System**: Verify PHP extensions và server configuration

---

**WordPress Scanner API Secret Key System** - Enterprise-grade security cho WordPress management tools.

*Version: 2.0 with API Authentication & VPS Security*