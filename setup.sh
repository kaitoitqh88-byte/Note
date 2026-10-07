#!/bin/bash

echo "============================================"
echo "   CLOUDFLARE PHP PROJECT SETUP"
echo "============================================"
echo ""

# Check if PHP is installed
if command -v php > /dev/null 2>&1; then
    echo "[OK] PHP is installed"
    php --version
    echo ""
else
    echo "[ERROR] PHP is not installed!"
    echo ""
    echo "Please install PHP first:"
    echo "Ubuntu/Debian: sudo apt install php php-curl php-json"
    echo "CentOS/RHEL: sudo yum install php php-curl php-json"
    echo "macOS: brew install php"
    echo ""
    exit 1
fi

# Check PHP extensions
echo "Checking PHP extensions..."
if php -m | grep -i curl > /dev/null; then
    echo "[OK] cURL extension enabled"
else
    echo "[WARNING] cURL extension not found"
fi

if php -m | grep -i json > /dev/null; then
    echo "[OK] JSON extension enabled"
else  
    echo "[WARNING] JSON extension not found"
fi

echo ""

# Check if token.txt exists and has content
if [ -f "token.txt" ]; then
    if [ -s "token.txt" ]; then
        echo "[OK] Token file exists and has content"
    else
        echo "[WARNING] Token file is empty"
        echo "Please add your Cloudflare API token to token.txt"
    fi
else
    echo "[ERROR] token.txt not found!"
    echo "Creating empty token.txt file..."
    touch token.txt
    echo "Please add your Cloudflare API token to token.txt"
fi

echo ""

# Make files executable
chmod +x setup.sh 2>/dev/null

# Test example script
echo "Testing basic functionality..."
php example.php

echo ""
echo "============================================"
echo "Setup completed!"
echo ""
echo "To start the development server:"
echo "  php -S localhost:8000"
echo ""
echo "Then open: http://localhost:8000"
echo "============================================"