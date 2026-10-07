@echo off
setlocal EnableDelayedExpansion

REM Cloudflare Security Tool PHP Installer for Windows

set TOOL_NAME=cloudflare_security_tool
set INSTALL_DIR=%USERPROFILE%\%TOOL_NAME%_php

echo 🛡️ Cloudflare Security Tool - PHP Installer for Windows
echo ========================================================
echo.

REM Function to check PHP
echo [INFO] Checking PHP installation...
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] PHP not found. Please install PHP first:
    echo   Download from: https://php.net/downloads
    echo   Or install with Chocolatey: choco install php
    echo   Or install with Scoop: scoop install php
    pause
    exit /b 1
)

REM Get PHP version
for /f "tokens=2 delims= " %%i in ('php --version 2^>nul ^| findstr /C:"PHP"') do (
    set PHP_VERSION=%%i
    goto :php_version_found
)
:php_version_found
echo [INFO] Found PHP %PHP_VERSION%

REM Check if installation directory exists
if exist "%INSTALL_DIR%" (
    echo [INFO] Installation directory exists: %INSTALL_DIR%
    set /p overwrite="Do you want to overwrite it? (y/N): "
    if /i not "!overwrite!"=="y" (
        echo [INFO] Installation cancelled.
        pause
        exit /b 0
    )
    echo [INFO] Removing existing installation...
    rmdir /s /q "%INSTALL_DIR%" 2>nul
)

REM Create installation directory
echo [INFO] Creating installation directory...
mkdir "%INSTALL_DIR%"
if %errorlevel% neq 0 (
    echo [ERROR] Failed to create installation directory: %INSTALL_DIR%
    pause
    exit /b 1
)
echo [INFO] Created directory: %INSTALL_DIR%
echo.

REM Install tool files
echo [INFO] Installing tool files...
set files=cloudflare_security_tool.html portable_launcher.php start_tool_php.bat

for %%f in (%files%) do (
    if not exist "%%f" (
        echo [ERROR] Required file not found: %%f
        pause
        exit /b 1
    )
    copy "%%f" "%INSTALL_DIR%\" >nul
    if !errorlevel! equ 0 (
        echo   ✓ %%f
    ) else (
        echo   ✗ %%f ^(failed^)
        pause
        exit /b 1
    )
)
echo.

REM Create configuration template
echo [INFO] Creating configuration template...
(
echo {
echo     "apiKey": "YOUR_CLOUDFLARE_API_KEY",
echo     "email": "your-email@example.com", 
echo     "zoneId": "your-zone-id",
echo     "serverPort": 8080,
echo     "autoOpenBrowser": true,
echo     "debug": false
echo }
) > "%INSTALL_DIR%\config_template.json"
echo [INFO] Created config template: config_template.json
echo.

REM Create desktop shortcut
echo [INFO] Creating desktop shortcut...
set SHORTCUT_PATH=%USERPROFILE%\Desktop\Cloudflare Security Tool (PHP).bat
(
echo @echo off
echo cd /d "%INSTALL_DIR%"
echo start_tool_php.bat
) > "%SHORTCUT_PATH%"
echo [INFO] Created desktop shortcut
echo.

REM Add to Start Menu
echo [INFO] Adding to Start Menu...
set START_MENU_DIR=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Cloudflare Security Tool
mkdir "%START_MENU_DIR%" 2>nul
(
echo @echo off
echo cd /d "%INSTALL_DIR%"
echo start_tool_php.bat
) > "%START_MENU_DIR%\Cloudflare Security Tool (PHP).bat"
echo [INFO] Added to Start Menu
echo.

REM Show completion message
echo.
echo 🎉 Installation completed successfully!
echo ======================================
echo.
echo Installation location: %INSTALL_DIR%
echo.
echo To start the tool:
echo   - Use desktop shortcut: Cloudflare Security Tool (PHP).bat
echo   - Or from Start Menu: Cloudflare Security Tool
echo   - Or run: %INSTALL_DIR%\start_tool_php.bat
echo.
echo First time setup:
echo 1. Copy config_template.json to config.json
echo 2. Edit config.json with your Cloudflare credentials
echo 3. Run the tool
echo.
echo Requirements met:
echo   ✓ PHP %PHP_VERSION% installed and working
echo   ✓ All tool files in place
echo   ✓ Desktop shortcut created
echo   ✓ Start Menu entry created
echo.
echo Press any key to exit...
pause >nul
exit /b 0