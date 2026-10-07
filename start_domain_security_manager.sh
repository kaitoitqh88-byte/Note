# Cloudflare Domain Security Manager Launcher  
# Unix/Linux shell script for easy access to Domain Security Manager

echo ""
echo "=========================================="
echo "    🌐 Cloudflare Domain Security Manager"
echo "    Advanced Domain-Based Security Rules"
echo "=========================================="
echo ""

# Set working directory to script location
cd "$(dirname "$0")"

# Check if CloudflareDomainSecurityManager.php exists
if [ ! -f "CloudflareDomainSecurityManager.php" ]; then
    echo "❌ Error: CloudflareDomainSecurityManager.php not found!"
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

echo "🚀 Starting Cloudflare Domain Security Manager..."
echo ""
echo "Features:"
echo "  🌐 Domain-based rule targeting"
echo "  📝 Custom expression builder"
echo "  📋 Rule templates library"
echo "  ✅ Expression validation"
echo "  🧪 Rule testing"
echo ""

# Launch the tool
php CloudflareDomainSecurityManager.php

echo ""
echo "📋 Domain Security Manager session ended."
read -p "Press Enter to continue..."
#!/bin/bash

# Cloudflare Domain Security Manager Launcher  
# Unix/Linux shell script for easy access to Domain Security Manager

echo ""
echo "=========================================="
echo "    🌐 Cloudflare Domain Security Manager"
echo "    Advanced Domain-Based Security Rules"
echo "=========================================="
echo ""

# Set working directory to script location
cd "$(dirname "$0")"

# Check if CloudflareDomainSecurityManager.php exists
if [ ! -f "CloudflareDomainSecurityManager.php" ]; then
    echo "❌ Error: CloudflareDomainSecurityManager.php not found!"
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

echo "🚀 Starting Cloudflare Domain Security Manager..."
echo ""
echo "Features:"
echo "  🌐 Domain-based rule targeting"
echo "  📝 Custom expression builder"
echo "  📋 Rule templates library"
echo "  ✅ Expression validation"
echo "  🧪 Rule testing"
echo ""

# Launch the tool
php CloudflareDomainSecurityManager.php

echo ""
echo "📋 Domain Security Manager session ended."
read -p "Press Enter to continue..."