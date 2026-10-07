#!/bin/bash

# Cloudflare Tools Menu - Unix/Linux Launcher

echo "🛠️ Cloudflare Tools Management Center"
echo "======================================"

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo "[ERROR] PHP not found. Please install PHP first:"
    echo "  Ubuntu/Debian: sudo apt-get install php-cli"
    echo "  macOS: brew install php"
    echo "  CentOS/RHEL: sudo yum install php-cli"
    exit 1
fi

# Check if required tools exist
if [ ! -f "tools_menu.php" ]; then
    echo "[ERROR] tools_menu.php not found"
    exit 1
fi

if [ ! -f "APISecretKeyManager.php" ]; then
    echo "[WARNING] APISecretKeyManager.php not found"
    echo "Some features may not work properly."
fi

if [ ! -f "CloudflareSecurityRuleManager.php" ]; then
    echo "[WARNING] CloudflareSecurityRuleManager.php not found"
    echo "Security Rules Manager may not work properly."  
fi

echo "[INFO] Starting Tools Management Center..."
echo

# Run the Tools Menu
php tools_menu.php

echo
echo "[INFO] Tools Menu stopped."
exit 0