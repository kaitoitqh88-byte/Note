#!/bin/bash

# Cloudflare Security Tool PHP Installer for Unix Systems
# Compatible with macOS and Linux

TOOL_NAME="cloudflare_security_tool"
INSTALL_DIR="$HOME/${TOOL_NAME}_php"
PHP_VERSION_MIN="7.4"

echo "🛡️ Cloudflare Security Tool - PHP Installer"
echo "============================================"
echo

# Function to check PHP version
check_php() {
    if ! command -v php &> /dev/null; then
        echo "[ERROR] PHP not found. Please install PHP first:"
        echo "  Ubuntu/Debian: sudo apt-get install php-cli"
        echo "  macOS: brew install php"
        echo "  CentOS/RHEL: sudo yum install php-cli"
        echo "  Or download from: https://php.net/downloads"
        return 1
    fi
    
    local php_version=$(php -r "echo PHP_VERSION;")
    echo "[INFO] Found PHP $php_version"
    
    # Basic version check (you might want to make this more robust)
    local major_version=$(echo $php_version | cut -d. -f1)
    local minor_version=$(echo $php_version | cut -d. -f2)
    
    if [ "$major_version" -lt 7 ] || [ "$major_version" -eq 7 -a "$minor_version" -lt 4 ]; then
        echo "[WARNING] PHP $php_version detected. PHP 7.4+ recommended."
        echo "The tool should still work, but consider upgrading."
    fi
    
    return 0
}

# Function to create installation directory
create_install_dir() {
    if [ -d "$INSTALL_DIR" ]; then
        echo "[INFO] Installation directory exists: $INSTALL_DIR"
        read -p "Do you want to overwrite it? (y/N): " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            echo "[INFO] Installation cancelled."
            exit 0
        fi
        rm -rf "$INSTALL_DIR"
    fi
    
    mkdir -p "$INSTALL_DIR"
    if [ $? -ne 0 ]; then
        echo "[ERROR] Failed to create installation directory: $INSTALL_DIR"
        exit 1
    fi
    
    echo "[INFO] Created directory: $INSTALL_DIR"
}

# Function to install tool files
install_files() {
    local files=(
        "cloudflare_security_tool.html"
        "portable_launcher.php"
        "start_tool_php.sh"
    )
    
    echo "[INFO] Installing tool files..."
    
    for file in "${files[@]}"; do
        if [ ! -f "$file" ]; then
            echo "[ERROR] Required file not found: $file"
            exit 1
        fi
        
        cp "$file" "$INSTALL_DIR/"
        if [ $? -eq 0 ]; then
            echo "  ✓ $file"
        else
            echo "  ✗ $file (failed)"
            exit 1
        fi
    done
    
    # Make shell script executable
    chmod +x "$INSTALL_DIR/start_tool_php.sh"
    echo "  ✓ Made start_tool_php.sh executable"
}

# Function to create desktop launcher
create_launcher() {
    if command -v desktop-file-install &> /dev/null; then
        # Create .desktop file for Linux
        cat > "$HOME/.local/share/applications/${TOOL_NAME}_php.desktop" << EOF
[Desktop Entry]
Version=1.0
Type=Application
Name=Cloudflare Security Tool (PHP)
Comment=Manage Cloudflare security rules
Exec=$INSTALL_DIR/start_tool_php.sh
Icon=security
Terminal=true
Categories=Development;Network;Security;
StartupWMClass=${TOOL_NAME}
EOF
        echo "[INFO] Created desktop launcher"
    elif [[ "$OSTYPE" == "darwin"* ]]; then
        # Create alias for macOS
        echo "alias ${TOOL_NAME}='cd $INSTALL_DIR && ./start_tool_php.sh'" >> ~/.bash_profile
        echo "[INFO] Added alias to ~/.bash_profile"
        echo "  Run: source ~/.bash_profile"
        echo "  Then use: ${TOOL_NAME}"
    fi
}

# Function to create configuration template
create_config() {
    cat > "$INSTALL_DIR/config_template.json" << 'EOF'
{
    "apiKey": "YOUR_CLOUDFLARE_API_KEY",
    "email": "your-email@example.com",
    "zoneId": "your-zone-id",
    "serverPort": 8080,
    "autoOpenBrowser": true,
    "debug": false
}
EOF
    echo "[INFO] Created config template: config_template.json"
}

# Function to show completion message
show_completion() {
    echo
    echo "🎉 Installation completed successfully!"
    echo "======================================"
    echo
    echo "Installation location: $INSTALL_DIR"
    echo
    echo "To start the tool:"
    echo "  cd $INSTALL_DIR"
    echo "  ./start_tool_php.sh"
    echo
    echo "First time setup:"
    echo "1. Copy config_template.json to config.json"
    echo "2. Edit config.json with your Cloudflare credentials"
    echo "3. Run the tool"
    echo
    echo "Requirements met:"
    echo "  ✓ PHP installed and working"
    echo "  ✓ All tool files in place"
    echo "  ✓ Executable permissions set"
    echo
    if [[ "$OSTYPE" == "darwin"* ]]; then
        echo "macOS users: Run 'source ~/.bash_profile' then use '${TOOL_NAME}' command"
    fi
}

# Main installation process
main() {
    echo "Starting installation process..."
    echo
    
    # Check PHP
    if ! check_php; then
        exit 1
    fi
    echo
    
    # Create installation directory
    create_install_dir
    echo
    
    # Install files
    install_files
    echo
    
    # Create configuration
    create_config
    echo
    
    # Create launcher
    create_launcher
    echo
    
    # Show completion message
    show_completion
}

# Run main function
main "$@"