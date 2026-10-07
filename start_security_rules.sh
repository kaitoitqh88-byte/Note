#!/bin/bash

# Cloudflare Security Rule Manager - Unix Launcher

echo "🛡️ Cloudflare Security Rule Manager"
echo "====================================="

# Check if PHP is available
if ! command -v php &> /dev/null; then
    echo "[ERROR] PHP not found. Please install PHP first:"
    echo "  Ubuntu/Debian: sudo apt-get install php-cli"
    echo "  macOS: brew install php"
    echo "  CentOS/RHEL: sudo yum install php-cli"
    exit 1
fi

# Check if required files exist
if [ ! -f "CloudflareSecurityRuleManager.php" ]; then
    echo "[ERROR] CloudflareSecurityRuleManager.php not found"
    exit 1
fi

if [ ! -f "config.json" ]; then
    echo "[WARNING] config.json not found. Please create configuration file first."
    echo "See documentation for setup instructions."
    read -p "Press Enter to continue anyway..."
fi

echo "[INFO] Starting Security Rule Manager..."
echo

# Run the Security Rule Manager
php CloudflareSecurityRuleManager.php

echo
echo "[INFO] Security Rule Manager stopped."
exit 0