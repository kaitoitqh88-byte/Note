#!/bin/bash

# Cloudflare Security Tool - PHP Unix Launcher
# Compatible with macOS and Linux

echo "🛡️ Cloudflare Security Tool - PHP Version"
echo "=========================================="

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo "[ERROR] PHP not found. Please install PHP first:"
    echo "  Ubuntu/Debian: sudo apt-get install php-cli"
    echo "  macOS: brew install php"
    echo "  CentOS/RHEL: sudo yum install php-cli"
    exit 1
fi

# Check if tool file exists
if [ ! -f "cloudflare_security_tool.html" ]; then
    echo "[ERROR] Tool file not found: cloudflare_security_tool.html"
    exit 1
fi

echo "[INFO] Starting PHP launcher..."
echo

# Run the PHP launcher
php portable_launcher.php --start

# If we get here, the server stopped
echo
echo "[INFO] Server stopped."
exit 0