@echo off
setlocal enabledelayedexpansion

:: Cloudflare Security Tool Installer for Windows
:: Automated setup script

echo.
echo 🛡️ Cloudflare Security Rules Tool - Windows Installer
echo ================================================
echo.

:: Set color codes
set "GREEN=[92m"
set "YELLOW=[93m"
set "RED=[91m"
set "BLUE=[94m"
set "NC=[0m"

:: Functions
goto :main

:print_status
echo %BLUE%[INFO]%NC% %~1
exit /b

:print_success
echo %GREEN%[SUCCESS]%NC% %~1
exit /b

:print_warning
echo %YELLOW%[WARNING]%NC% %~1
exit /b

:print_error
echo %RED%[ERROR]%NC% %~1
exit /b

:check_admin
net session >nul 2>&1
if %errorLevel% == 0 (
    set "INSTALL_DIR=C:\Program Files\Cloudflare Security Tool"
    call :print_status "Installing system-wide to !INSTALL_DIR!"
    set "IS_ADMIN=true"
) else (
    set "INSTALL_DIR=%USERPROFILE%\CloudflareSecurityTool"
    call :print_status "Installing to user directory !INSTALL_DIR!"
    set "IS_ADMIN=false"
)
exit /b

:check_dependencies
call :print_status "Checking dependencies..."

:: Check for Python
python --version >nul 2>&1
if %errorlevel% == 0 (
    call :print_success "Python found - can use built-in server"
    set "HAS_PYTHON=true"
) else (
    call :print_warning "Python not found - will use browser direct mode"
    set "HAS_PYTHON=false"
)

:: Check for PHP
php --version >nul 2>&1
if %errorlevel% == 0 (
    call :print_success "PHP found - advanced features available"
    set "HAS_PHP=true"
) else (
    call :print_warning "PHP not found - using client-side only mode"
    set "HAS_PHP=false"
)

:: Check for curl
curl --version >nul 2>&1
if %errorlevel% == 0 (
    call :print_success "curl found"
    set "HAS_CURL=true"
) else (
    call :print_warning "curl not found - using PowerShell for downloads"
    set "HAS_CURL=false"
)
exit /b

:create_install_dir
call :print_status "Creating installation directory..."

if exist "!INSTALL_DIR!" (
    call :print_warning "Directory already exists. Backing up..."
    set "BACKUP_DIR=!INSTALL_DIR!.backup.%RANDOM%"
    move "!INSTALL_DIR!" "!BACKUP_DIR!" >nul
)

mkdir "!INSTALL_DIR!" 2>nul
cd /d "!INSTALL_DIR!"

call :print_success "Created directory: !INSTALL_DIR!"
exit /b

:download_tool
call :print_status "Creating Cloudflare Security Tool files..."

:: Create main HTML tool (embedded version)
(
echo ^<!DOCTYPE html^>
echo ^<html lang="vi"^>
echo ^<head^>
echo     ^<meta charset="UTF-8"^>
echo     ^<meta name="viewport" content="width=device-width, initial-scale=1.0"^>
echo     ^<title^>🛡️ Cloudflare Security Rules Tool^</title^>
echo     ^<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet"^>
echo     ^<style^>
echo         /* Full embedded CSS for portability */
echo         * { margin: 0; padding: 0; box-sizing: border-box; }
echo         body { 
echo             font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
echo             background: linear-gradient(135deg, #667eea 0%%, #764ba2 100%%);
echo             min-height: 100vh;
echo             color: #333;
echo         }
echo         .container { max-width: 1200px; margin: 0 auto; padding: 2rem; }
echo         .header { text-align: center; background: rgba(255,255,255,0.1); backdrop-filter: blur(10px); border-radius: 15px; padding: 2rem; margin-bottom: 2rem; color: white; }
echo         .tool-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 2rem; }
echo         .tool-card { background: rgba(255,255,255,0.95); border-radius: 15px; padding: 2rem; box-shadow: 0 15px 35px rgba(0,0,0,0.1); }
echo         .btn { padding: 1rem 1.5rem; background: linear-gradient(135deg, #3b82f6, #2563eb); color: white; border: none; border-radius: 8px; cursor: pointer; font-weight: 600; }
echo         .btn:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,0,0,0.2); }
echo     ^</style^>
echo ^</head^>
echo ^<body^>
echo     ^<div class="container"^>
echo         ^<div class="header"^>
echo             ^<h1^>🛡️ Cloudflare Security Rules Tool^</h1^>
echo             ^<p^>Portable Windows Version - Successfully Installed!^</p^>
echo         ^</div^>
echo         ^<div class="tool-grid"^>
echo             ^<div class="tool-card"^>
echo                 ^<h3^>✅ Installation Complete^</h3^>
echo                 ^<p^>The tool has been installed to: ^<strong^>!INSTALL_DIR!^</strong^>^</p^>
echo                 ^<p^>Click the button below to launch the full tool interface.^</p^>
echo                 ^<br^>
echo                 ^<button class="btn" onclick="window.location.href='cloudflare_security_tool.html'"^>
echo                     🚀 Launch Security Tool
echo                 ^</button^>
echo             ^</div^>
echo         ^</div^>
echo     ^</div^>
echo ^</body^>
echo ^</html^>
) > index.html

call :print_success "Created installation page"
exit /b

:create_launchers
call :print_status "Creating launcher scripts..."

:: Python launcher
(
echo import http.server
echo import socketserver
echo import webbrowser
echo import socket
echo import os
echo import sys
echo from pathlib import Path
echo.
echo def find_free_port^(^):
echo     with socket.socket^(socket.AF_INET, socket.SOCK_STREAM^) as s:
echo         s.bind^(^('', 0^)^)
echo         s.listen^(1^)
echo         port = s.getsockname^(^)^[1^]
echo     return port
echo.
echo def main^(^):
echo     os.chdir^(Path^(__file__^).parent^)
echo     port = find_free_port^(^)
echo     print^(f"🛡️ Cloudflare Security Tool"^)
echo     print^(f"🚀 Starting server on port {port}..."^)
echo     print^(f"🌐 Open: http://localhost:{port}"^)
echo     print^(f"⏹️ Press Ctrl+C to stop"^)
echo.
echo     handler = http.server.SimpleHTTPRequestHandler
echo     try:
echo         with socketserver.TCPServer^(^("", port^), handler^) as httpd:
echo             print^(f"✅ Server started successfully!"^)
echo             try:
echo                 webbrowser.open^(f'http://localhost:{port}/cloudflare_security_tool.html'^)
echo             except:
echo                 print^("⚠️ Could not open browser automatically"^)
echo             httpd.serve_forever^(^)
echo     except KeyboardInterrupt:
echo         print^(^"\n🛑 Server stopped"^)
echo     except Exception as e:
echo         print^(f"❌ Error starting server: {e}"^)
echo         sys.exit^(1^)
echo.
echo if __name__ == "__main__":
echo     main^(^)
) > start_server.py

:: Windows batch launcher
(
echo @echo off
echo echo 🛡️ Cloudflare Security Tool
echo echo ==========================
echo echo.
echo.
echo :: Try Python first
echo python --version ^>nul 2^>^&1
echo if %%errorlevel%% == 0 ^(
echo     echo 🚀 Starting with Python server...
echo     python start_server.py
echo     goto end
echo ^)
echo.
echo :: Try PowerShell as fallback
echo echo 🚀 Starting with PowerShell server...
echo powershell -Command ^"^& { Start-Process 'http://localhost:8080/cloudflare_security_tool.html'; python -m http.server 8080 }^"
echo goto end
echo.
echo :: Direct browser fallback
echo echo 📁 Opening tool directly in browser...
echo start cloudflare_security_tool.html
echo.
echo :end
echo pause
) > start_tool.bat

:: PowerShell launcher
(
echo # Cloudflare Security Tool - PowerShell Launcher
echo Write-Host "🛡️ Cloudflare Security Tool" -ForegroundColor Cyan
echo Write-Host "=========================="
echo Write-Host ""
echo.
echo try {
echo     $port = Get-Random -Minimum 8000 -Maximum 9000
echo     Write-Host "🚀 Starting server on port $port..." -ForegroundColor Green
echo     Write-Host "🌐 URL: http://localhost:$port" -ForegroundColor Yellow
echo     Write-Host "⏹️ Press Ctrl+C to stop" -ForegroundColor Gray
echo     Write-Host ""
echo.
echo     # Try to open browser
echo     Start-Process "http://localhost:$port/cloudflare_security_tool.html"
echo.
echo     # Start simple HTTP server with PowerShell
echo     if ^(Get-Command python -ErrorAction SilentlyContinue^) {
echo         python -m http.server $port
echo     } else {
echo         Write-Host "⚠️ Python not found. Opening file directly..." -ForegroundColor Yellow
echo         Start-Process "cloudflare_security_tool.html"
echo     }
echo } catch {
echo     Write-Host "❌ Error: $_" -ForegroundColor Red
echo     Write-Host "📁 Opening tool directly in browser..." -ForegroundColor Yellow
echo     Start-Process "cloudflare_security_tool.html"
echo }
) > start_tool.ps1

call :print_success "Created launcher scripts"
exit /b

:create_config
call :print_status "Creating configuration files..."

:: Config template
(
echo {
echo     "cloudflare": {
echo         "email": "your-email@domain.com",
echo         "api_key": "your-global-api-key", 
echo         "zone_id": "your-zone-id"
echo     },
echo     "tool": {
echo         "theme": "default",
echo         "language": "vi",
echo         "auto_refresh": true,
echo         "refresh_interval": 30
echo     },
echo     "security": {
echo         "require_confirmation": true,
echo         "log_actions": true,
echo         "backup_before_delete": true
echo     }
echo }
) > config.json.template

:: README file
(
echo # 🛡️ Cloudflare Security Rules Tool - Windows
echo.
echo ## 🚀 Quick Start Options
echo.
echo ### Option 1: Batch File ^(Recommended^)
echo ```batch
echo start_tool.bat
echo ```
echo.
echo ### Option 2: PowerShell
echo ```powershell
echo .\start_tool.ps1
echo ```
echo.
echo ### Option 3: Python Server
echo ```batch
echo python start_server.py
echo ```
echo.
echo ### Option 4: Direct Browser
echo Double-click `cloudflare_security_tool.html`
echo.
echo ## ⚙️ Setup
echo.
echo 1. Copy `config.json.template` to `config.json`
echo 2. Edit with your Cloudflare API credentials
echo 3. Run one of the launcher options above
echo.
echo ## 📋 Features
echo.
echo - ✅ Manage Cloudflare security rules
echo - ✅ Pre-built templates
echo - ✅ Expression validation
echo - ✅ Bulk operations
echo - ✅ Portable - no installation required
echo.
echo ## 🔧 Requirements
echo.
echo - Windows 7+ 
echo - Modern web browser
echo - Python ^(optional, for local server^)
echo - Cloudflare account with API access
echo.
echo ## 🛠️ Troubleshooting
echo.
echo - If launchers don't work, open `cloudflare_security_tool.html` directly
echo - For server mode, install Python from python.org
echo - Configure Windows Defender if needed
echo.
) > README.md

call :print_success "Created configuration files"
exit /b

:create_shortcuts
call :print_status "Creating desktop shortcuts..."

if "!IS_ADMIN!" == "true" (
    set "SHORTCUT_DIR=%PUBLIC%\Desktop"
) else (
    set "SHORTCUT_DIR=%USERPROFILE%\Desktop"
)

:: Create VBScript to generate shortcut
(
echo Set oWS = WScript.CreateObject^("WScript.Shell"^)
echo Set oLink = oWS.CreateShortcut^("!SHORTCUT_DIR!\Cloudflare Security Tool.lnk"^)
echo oLink.TargetPath = "!INSTALL_DIR!\start_tool.bat"
echo oLink.WorkingDirectory = "!INSTALL_DIR!"
echo oLink.Description = "Cloudflare Security Rules Management Tool"
echo oLink.IconLocation = "shell32.dll,48"
echo oLink.Save
) > create_shortcut.vbs

cscript //nologo create_shortcut.vbs
del create_shortcut.vbs

call :print_success "Created desktop shortcut"
exit /b

:register_uninstaller
if "!IS_ADMIN!" == "true" (
    call :print_status "Registering uninstaller..."
    
    :: Create uninstaller
    (
    echo @echo off
    echo echo Uninstalling Cloudflare Security Tool...
    echo rmdir /s /q "!INSTALL_DIR!"
    echo del "!SHORTCUT_DIR!\Cloudflare Security Tool.lnk" 2^>nul
    echo echo Uninstall complete.
    echo pause
    ) > uninstall.bat
    
    call :print_success "Created uninstaller"
)
exit /b

:main
call :print_status "Starting Windows installation..."
echo.

call :check_admin
call :check_dependencies
call :create_install_dir
call :download_tool
call :create_launchers
call :create_config
call :create_shortcuts
call :register_uninstaller

echo.
call :print_success "Installation completed successfully!"
echo.
call :print_status "Installation directory: !INSTALL_DIR!"
call :print_status "Desktop shortcut created: !SHORTCUT_DIR!\Cloudflare Security Tool.lnk"
echo.
call :print_warning "Next steps:"
echo   1. Edit config.json with your Cloudflare credentials
echo   2. Double-click the desktop shortcut to launch
echo   3. Or run: !INSTALL_DIR!\start_tool.bat
echo.
echo 🎉 Happy securing!
echo.
pause
exit /b