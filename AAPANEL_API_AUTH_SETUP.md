# aaPanel API Secret Key Authentication Setup

## 🔐 Secure Access to aaPanel Manager

Your aaPanel management system is now secured with API Secret Key authentication, providing enterprise-grade security for accessing server administration tools.

## 📋 Setup Steps

### 1. Generate API Key (Admin Required)
```bash
# Truy cập API setup
http://your-domain/api_setup.php
```

**Required for aaPanel Access:**
- **API Key Name**: `aapanel-admin` (or custom name)
- **Permissions**: Must include `admin` or `vps`
- **Key Type**: 64-character secure key

### 2. Login to aaPanel Manager
```bash
# Secure login interface
http://your-domain/aapanel_login.php
```

**Login Process:**
1. Enter your 64-character API secret key
2. System validates permissions (`admin` or `vps` required)
3. Automatic redirect to aaPanel Manager upon success

### 3. Access aaPanel Features
```bash
# Main aaPanel management interface  
http://your-domain/aapanel_manager.php

# aaPanel API configuration
http://your-domain/aapanel_api_config.php
```

## 🛡️ Security Features

### Authentication System
- ✅ **API Secret Key**: 64-character secure keys
- ✅ **Role-Based Access**: Permission-based access control
- ✅ **Session Management**: Automatic expiration after inactivity
- ✅ **Action Logging**: All activities monitored and logged
- ✅ **IP Security**: Login attempts tracked and restricted

### Required Permissions
| Permission | Access Level | Description |
|------------|--------------|-------------|
| `admin` | Full Access | Complete aaPanel management + configuration |
| `vps` | Limited Access | Server monitoring and log viewing |
| `scan` | Read-Only | Basic system information access |

### Session Security
- **Auto-Expiration**: Sessions expire after 1 hour of inactivity
- **Secure Tokens**: Cryptographically strong session tokens
- **Logout Protection**: Secure session destruction on logout
- **Multi-User Support**: Multiple authenticated sessions possible

## 🚀 Usage Workflow

### 1. First-Time Setup
```bash
1. Admin generates API key với admin permissions
2. User receives 64-character secret key
3. User accesses aapanel_login.php
4. Enter API key to authenticate
5. Access granted to aaPanel Manager
```

### 2. Daily Usage
```bash
1. Direct access: aapanel_manager.php
2. Auto-redirect to login if session expired
3. Quick re-authentication với saved key
4. Full aaPanel access restored
```

### 3. aaPanel Configuration
```bash
1. Access aapanel_api_config.php (admin permission required)
2. Configure múltiple aaPanel servers
3. Set API credentials cho each server
4. Test connections và save configuration
5. Use configured servers in aaPanel Manager
```

## 🔧 aaPanel Manager Features

### Server Management
- **Multi-Server Support**: Manage multiple aaPanel installations
- **Real-Time Monitoring**: Live system information và resource usage
- **Connection Testing**: Verify aaPanel API connectivity
- **Status Overview**: Quick health check của all servers

### Log Management
- **Advanced Log Viewer**: Real-time log analysis với syntax highlighting
- **Log Filtering**: Search và filter logs by type, date, keyword
- **Export Capabilities**: Download logs cho offline analysis
- **Performance Insights**: System performance metrics từ logs

### Security Dashboard
- **Authentication Status**: Current user và permission display
- **Session Information**: Active session details và expiration time
- **Activity Logging**: Comprehensive audit trail của all actions
- **Permission Verification**: Real-time permission checking

## 📱 Mobile-Responsive Interface

### Features
- ✅ **Bootstrap 5**: Modern, responsive design
- ✅ **Touch-Friendly**: Mobile-optimized controls
- ✅ **Dark Theme**: Professional appearance với good contrast
- ✅ **Real-Time Updates**: Live data refresh without page reload

### Navigation
- **Authenticated Header**: User info và quick access menu
- **Dropdown Menu**: Easy access to settings và logout
- **Breadcrumb Navigation**: Clear page hierarchy
- **Quick Actions**: Fast access to common tasks

## 🛠️ API Integration

### aaPanel API Configuration
```json
{
  "servers": [
    {
      "name": "Production Server",
      "url": "https://your-server:8888",
      "api_key": "your-aapanel-api-key",
      "api_secret": "your-aapanel-secret",
      "timeout": 30,
      "enabled": true
    }
  ]
}
```

### API Security
- **Signature Verification**: MD5 signature validation for all API calls
- **Timestamp Protection**: Request timestamp validation to prevent replay attacks
- **SSL/TLS Encryption**: All communication encrypted via HTTPS
- **Rate Limiting**: Built-in protection against excessive API calls

## 🚨 Troubleshooting

### Common Issues

#### 1. Authentication Failed
```bash
Problem: "Invalid API key" error
Solution: 
- Verify 64-character key format
- Check permissions include 'admin' or 'vps'
- Generate new key if expired
```

#### 2. Permission Denied
```bash
Problem: "Insufficient permissions" message
Solution:
- Contact admin to add 'admin' or 'vps' permission
- Verify current permissions in user dropdown
- Re-authenticate với updated key
```

#### 3. Session Expired
```bash
Problem: Automatic redirect to login
Solution:
- Normal behavior after 1 hour inactivity
- Re-enter API key to restore access
- Check session expiration time in navbar
```

#### 4. aaPanel Connection Failed
```bash
Problem: Cannot connect to aaPanel servers
Solution:
- Verify aaPanel API credentials in config
- Test aaPanel API connectivity
- Check server URL và port accessibility
- Validate API key permissions in aaPanel
```

## 📞 Support & Documentation

### Quick Reference
- **Setup Guide**: `/api_setup.php` - Generate API keys
- **Login Portal**: `/aapanel_login.php` - Secure authentication
- **Main Interface**: `/aapanel_manager.php` - aaPanel management
- **Configuration**: `/aapanel_api_config.php` - API settings

### Security Best Practices
1. **Regular Key Rotation**: Generate new API keys periodically
2. **Minimal Permissions**: Grant only required access levels
3. **Session Monitoring**: Check active sessions regularly
4. **Activity Review**: Monitor access logs for unusual activity
5. **Secure Storage**: Never share API keys in plaintext

---

## 🎯 Quick Start Commands

```bash
# 1. Generate Admin API Key
curl -X POST http://your-domain/api_setup.php \
  -H "Content-Type: application/json" \
  -d '{"action":"generate","name":"aapanel-admin","permissions":["admin","vps"]}'

# 2. Login with API Key
curl -X POST http://your-domain/aapanel_login.php \
  -H "Content-Type: application/x-www-form-urlencoded" \
  -d "api_key=YOUR_64_CHARACTER_API_KEY"

# 3. Access aaPanel Manager
curl -H "Cookie: session_token=YOUR_SESSION" \
  http://your-domain/aapanel_manager.php
```

**🔥 Your aaPanel management system is now secured với enterprise-grade API authentication!**