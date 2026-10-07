# 🛡️ Cloudflare Security Tool - Complete PHP & Python Package

**Version 2.0.0** - Enhanced Multi-Language Support

A comprehensive web-based tool for managing Cloudflare security rules, now available in both Python and PHP versions with multiple deployment options.

## 🚀 Quick Start Options

### Option 1: Standalone HTML (Simplest)
**No programming language required**
- Open `cloudflare_security_tool.html` in any modern web browser
- Enter your Cloudflare API credentials directly in the interface
- ✅ Zero installation • ✅ Works offline • ✅ Completely portable

### Option 2: Python Launcher (Recommended)
**For users with Python installed**
```bash
# Windows
install_security_tool.bat

# macOS/Linux  
./install_security_tool.sh
```

### Option 3: PHP Launcher (New!)
**For users with PHP installed**
```bash
# Windows
install_security_tool_php.bat

# macOS/Linux
./install_security_tool_php.sh
```

## 📦 Package Contents

### Core Files
- `cloudflare_security_tool.html` - Main web interface
- `config_template.json` - Configuration template
- `README.md` - This documentation

### Python Version
- `portable_launcher.py` - Python launcher with web server
- `start_tool.bat` / `start_tool.sh` - Platform-specific launchers
- `install_security_tool.bat` / `install_security_tool.sh` - Auto-installers

### PHP Version  
- `portable_launcher.php` - PHP launcher with web server
- `start_tool_php.bat` / `start_tool_php.sh` - Platform-specific launchers
- `install_security_tool_php.bat` / `install_security_tool_php.sh` - Auto-installers

### Enhanced Tools
- `package_builder_enhanced.py` - Build custom packages
- Multiple documentation files for setup and troubleshooting

## 🔧 System Requirements

### For HTML-Only Version
- ✅ Modern web browser (Chrome, Firefox, Safari, Edge)
- ✅ Internet connection (for Cloudflare API calls)

### For Python Version
- ✅ Python 3.6 or higher
- ✅ No additional packages required (uses built-in libraries)

### For PHP Version
- ✅ PHP 7.4 or higher (8.0+ recommended)
- ✅ PHP CLI enabled
- ✅ No additional extensions required

## 🎯 Feature Comparison

| Feature | HTML Only | Python Launcher | PHP Launcher |
|---------|-----------|----------------|--------------|
| Web Interface | ✅ | ✅ | ✅ |
| Auto Server Start | ❌ | ✅ | ✅ |
| Auto Browser Open | ❌ | ✅ | ✅ |
| Configuration File | ❌ | ✅ | ✅ |
| Interactive Menu | ❌ | ✅ | ✅ |
| Platform Detection | ❌ | ✅ | ✅ |
| Background Server | ❌ | ✅ | ✅ |
| Installation Required | ❌ | Optional | Optional |

## 📋 Detailed Setup Instructions

### 🔹 HTML Standalone Setup
1. Simply open `cloudflare_security_tool.html` in your browser
2. No additional setup required!

### 🔹 Python Launcher Setup

**Automatic Installation (Recommended):**
```bash
# Windows - Double-click or run in Command Prompt
install_security_tool.bat

# macOS/Linux - Run in terminal
chmod +x install_security_tool.sh
./install_security_tool.sh
```

**Manual Setup:**
1. Copy `config_template.json` to `config.json`
2. Edit `config.json` with your Cloudflare credentials
3. Run: `python portable_launcher.py`

### 🔹 PHP Launcher Setup

**Automatic Installation (Recommended):**
```bash
# Windows - Double-click or run in Command Prompt  
install_security_tool_php.bat

# macOS/Linux - Run in terminal
chmod +x install_security_tool_php.sh
./install_security_tool_php.sh
```

**Manual Setup:**
1. Copy `config_template.json` to `config.json`  
2. Edit `config.json` with your Cloudflare credentials
3. Run: `php portable_launcher.php`

## ⚙️ Configuration

### Cloudflare API Credentials
You'll need these from your Cloudflare dashboard:

1. **Global API Key:**
   - Go to: Cloudflare Dashboard → My Profile → API Tokens
   - Click "View" next to Global API Key
   - Copy the key

2. **Email Address:**
   - Your Cloudflare account email

3. **Zone ID:**
   - Go to your domain in Cloudflare Dashboard
   - Zone ID is shown in the right sidebar under "Overview"

### Configuration File (config.json)
```json
{
    "apiKey": "your-global-api-key-here",
    "email": "your-cloudflare-email@example.com",
    "zoneId": "your-zone-id-here",
    "serverPort": 8080,
    "autoOpenBrowser": true,
    "debug": false
}
```

## 🚀 Usage Guide

### Starting the Tool

**HTML Version:**
- Open `cloudflare_security_tool.html` in browser

**Python Version:**
```bash
# Using launcher scripts
start_tool.bat        # Windows
./start_tool.sh       # macOS/Linux

# Direct execution
python portable_launcher.py
```

**PHP Version:**
```bash
# Using launcher scripts  
start_tool_php.bat        # Windows
./start_tool_php.sh       # macOS/Linux

# Direct execution
php portable_launcher.php
```

### Interactive Menu (Launchers Only)
Both Python and PHP launchers provide an interactive menu:

```
🛡️ Cloudflare Security Tool Launcher
=====================================

1. 🚀 Start Tool
2. ⚙️ Edit Configuration  
3. 📊 Show Current Config
4. ℹ️ About
5. 🔄 Restart Tool
6. ❌ Exit

Choose an option (1-6):
```

### Using the Web Interface
1. Tool starts automatically in your browser
2. Current security rules are loaded from Cloudflare
3. Add new rules using the form at the top
4. Edit existing rules by clicking on them
5. Delete rules using the delete button
6. All changes are applied immediately to Cloudflare

## 🔧 Advanced Features

### Custom Package Building
Use the enhanced package builder to create custom distributions:

```bash
# Build all package types
python package_builder_enhanced.py build all

# Build specific package type
python package_builder_enhanced.py build php_complete
python package_builder_enhanced.py build standalone

# List available package types
python package_builder_enhanced.py list
```

**Available Package Types:**
- `standalone` - HTML-only version
- `python_complete` - Full Python package
- `php_complete` - Full PHP package  
- `dual_language` - Both Python and PHP
- `minimal` - Minimal HTML package

### Server Configuration
Both launchers allow custom server ports and settings:

```bash
# Start with custom port
python portable_launcher.py --port 8090
php portable_launcher.php --port 8090

# Start without auto-opening browser
python portable_launcher.py --no-browser
php portable_launcher.php --no-browser
```

## 🐛 Troubleshooting

### Common Issues

**"PHP/Python not found"**
- Install PHP from: https://php.net/downloads
- Install Python from: https://python.org/downloads
- Ensure PHP/Python is added to your system PATH

**"Port already in use"**
- Change the port in config.json
- Or stop other programs using port 8080

**"API Key invalid"**
- Double-check your Cloudflare API key
- Ensure you're using the Global API Key, not a custom token
- Verify email address matches your Cloudflare account

**"Zone not found"**
- Verify the Zone ID from your Cloudflare dashboard
- Ensure the domain is properly added to Cloudflare

### Debug Mode
Enable debug mode in config.json for detailed logging:
```json
{
    "debug": true
}
```

### Browser Console
Check your browser's developer console (F12) for JavaScript errors.

## 🛠️ Platform-Specific Notes

### Windows
- Both `.bat` files can be double-clicked to run
- PHP/Python should be installed with "Add to PATH" option
- Windows Defender may require permission for local server

### macOS
- Use Terminal for shell scripts  
- May need to install PHP: `brew install php`
- Python is usually pre-installed

### Linux
- Make shell scripts executable: `chmod +x *.sh`
- Install PHP: `sudo apt-get install php-cli` (Ubuntu/Debian)
- Python is usually pre-installed

## 📄 License & Security

- This tool is provided as-is for authorized use only
- Ensure you have proper permissions for domains you manage
- Use responsibly and follow Cloudflare's API terms of service
- Keep your API credentials secure and never share them

## 📞 Support

**Getting Help:**
1. Check this README for setup instructions
2. Review the browser console for error messages
3. Enable debug mode for detailed logging
4. Verify your Cloudflare credentials and permissions

**Reporting Issues:**
Include the following information:
- Operating system and version
- PHP/Python version (if using launchers)
- Browser version
- Error messages from console/terminal
- Steps to reproduce the issue

---

**Version 2.0.0** - Enhanced multi-language support with both Python and PHP launchers, improved packaging system, and comprehensive documentation.

*Built with ❤️ for the Cloudflare community*