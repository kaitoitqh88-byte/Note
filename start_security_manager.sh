#!/bin/bash

# Cloudflare Security Rule Manager Launcher  
# Unix/Linux shell script for easy access to Security Rule Manager

echo ""
echo "========================================"
echo "    🛡️ Cloudflare Security Rule Manager"
echo "========================================"
echo ""

# Set working directory to script location
cd "$(dirname "$0")"

# Check if CloudflareSecurityRuleManager.php exists
if [ ! -f "CloudflareSecurityRuleManager.php" ]; then
    echo "❌ Error: CloudflareSecurityRuleManager.php not found!"
    echo "Please ensure the file exists in the current directory."
    read -p "Press Enter to continue..."
    exit 1
fi

# Check for PHP
if ! command -v php &> /dev/null; then
    echo "❌ Error: PHP not found in PATH!"
    echo "Please install PHP or add it to your system PATH."
    read -p "Press Enter to continue..."
    exit 1
fi

echo "🚀 Starting Cloudflare Security Rule Manager..."
echo ""

# Launch the tool
php CloudflareSecurityRuleManager.php

echo ""
echo "📋 Security Rule Manager session ended."
read -p "Press Enter to continue..."