# 🛠️ Cloudflare Tools Management Center

## 📋 Overview

Complete tools ecosystem for Cloudflare management with multiple access methods and integrated functionality.

## 🎯 Quick Start

### Method 1: Web Interface (Recommended)
```bash
# Start web server
php -S localhost:8080

# Access tools:
# Main Dashboard: http://localhost:8080/
# Tools Menu: http://localhost:8080/?action=tools-menu
# API Key Manager: http://localhost:8080/?action=api-key-manager
# Security Manager: http://localhost:8080/security_manager.php
```

### Method 2: Command Line Launchers

#### Windows (.bat files)
```cmd
:: Main launcher with 8 options
start_portable_launcher.bat

:: Individual tools
start_api_manager.bat
start_security_manager.bat
start_tools_menu.bat
```

#### Unix/Linux (.sh files)  
```bash
# Main launcher with 8 options
./start_portable_launcher.sh

# Individual tools
./start_api_manager.sh
./start_security_manager.sh
./start_tools_menu.sh
```

### Method 3: Direct PHP Execution
```bash
# Main portable launcher
php portable_launcher.php

# Individual tools
php APISecretKeyManager.php
php CloudflareSecurityRuleManager.php
php tools_menu.php
```

## 🏗️ System Architecture

### Core Components

1. **portable_launcher.php** - Main CLI launcher (8 options)
   - API Key Manager (Option 6)
   - Security Rule Manager (Option 7)
   - Integrated server management
   - Cross-platform browser launching

2. **tools_menu.php** - Comprehensive tools center (13 options)
   - Security & Authentication tools
   - Cloudflare management
   - Utilities and configuration
   - System integration

3. **index.php** - Web interface entry point
   - Action routing system
   - API Key Manager web interface
   - Tools Menu web interface
   - Complete handler functions

### Specialized Tools

#### 🔐 API Key Manager (APISecretKeyManager.php)
- Generate secure API keys with permissions
- Validate keys and check permissions
- Usage tracking and statistics
- Expiration management
- Web interface: `/?action=api-key-manager`

#### 🛡️ Security Rule Manager (CloudflareSecurityRuleManager.php)  
- Create custom security rules with expressions
- Expression validation and testing
- Domain-specific targeting
- Template library
- Web interface: `/security_manager.php`

## 📁 File Structure

```
📦 Cloudflare Tools Suite
├── 🎛️ Main Launchers
│   ├── portable_launcher.php          # Main CLI launcher (8 options)
│   ├── tools_menu.php                 # Comprehensive tools center (13 options)  
│   └── index.php                      # Web interface entry point
├── 🔧 Core Tools
│   ├── APISecretKeyManager.php        # API key management system
│   └── CloudflareSecurityRuleManager.php # Security rules with expressions
├── 🌐 Web Interfaces
│   ├── security_manager.php           # Security rules web UI
│   └── [index.php handles other UIs]  # API manager, tools menu
├── 🪟 Windows Launchers (.bat)
│   ├── start_portable_launcher.bat    # Main launcher
│   ├── start_tools_menu.bat          # Tools menu
│   ├── start_api_manager.bat         # API manager
│   └── start_security_manager.bat    # Security manager
└── 🐧 Unix Launchers (.sh)  
    ├── start_portable_launcher.sh     # Main launcher
    ├── start_tools_menu.sh           # Tools menu
    ├── start_api_manager.sh          # API manager
    └── start_security_manager.sh     # Security manager
```

## 🎮 Access Methods Summary

| Method | Access Point | Features |
|--------|---------------|----------|
| **Web Interface** | `http://localhost:8080/` | Full featured, mobile responsive |
| **Main Launcher** | `portable_launcher.php` | 8 integrated options |
| **Tools Menu** | `tools_menu.php` | 13 comprehensive tools |
| **Windows Scripts** | `.bat files` | One-click access |
| **Unix Scripts** | `.sh files` | Cross-platform support |

## 🔧 Features by Tool

### API Key Manager
- ✅ Generate keys with custom permissions
- ✅ Expiration date management
- ✅ Usage tracking and statistics
- ✅ Validation and testing
- ✅ Web + CLI interfaces

### Security Rule Manager  
- ✅ Custom Cloudflare expressions
- ✅ Domain-specific targeting
- ✅ Expression validation
- ✅ Rule testing with sample data
- ✅ Template library with examples

### Tools Menu Hub
- ✅ 13 organized tool categories
- ✅ Security & Authentication section
- ✅ Cloudflare Management section  
- ✅ Utilities & Configuration section
- ✅ System information display

## 🚀 Getting Started

1. **Setup Configuration**
   ```bash
   # Ensure config files exist:
   # - config.json (Cloudflare API settings)
   # - config.php (if using legacy tools)
   ```

2. **Choose Access Method**
   - **Web**: Start PHP server and use browser
   - **CLI**: Run portable_launcher.php
   - **Tools Hub**: Run tools_menu.php
   - **Individual**: Run specific tool files

3. **API Key Management**
   - Generate keys: Main launcher → Option 6
   - Web interface: `/?action=api-key-manager`
   - Permissions: scan, backup, manage, admin

4. **Security Rules**
   - Create rules: Main launcher → Option 7
   - Web interface: `/security_manager.php`
   - Use templates for common patterns

## 💡 Tips for Usage

- **Web Interface**: Best for interactive use and mobile access
- **CLI Tools**: Ideal for automation and scripting
- **Batch/Shell Scripts**: Quick one-click access
- **Individual Tools**: Direct access when you know exactly what you need

## 📝 Integration Notes

All tools are fully integrated:
- Shared configuration system
- Consistent error handling  
- Cross-platform compatibility
- Mobile-responsive web interfaces
- Complete documentation

## 🔗 Quick Links

- **Main Dashboard**: `http://localhost:8080/`
- **Tools Menu**: `http://localhost:8080/?action=tools-menu`
- **API Manager**: `http://localhost:8080/?action=api-key-manager` 
- **Security Manager**: `http://localhost:8080/security_manager.php`

---

**Complete Cloudflare Tools Ecosystem** - Multiple access methods, unified interface, comprehensive functionality.