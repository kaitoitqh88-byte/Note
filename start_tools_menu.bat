@echo off
REM Cloudflare Tools Menu - Windows Launcher

echo 🛠️ Cloudflare Tools Management Center
echo ======================================

REM Check if PHP is available
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] PHP not found. Please install PHP first.
    echo Download from: https://php.net/downloads
    pause
    exit /b 1
)

REM Check if required tools exist
if not exist "tools_menu.php" (
    echo [ERROR] tools_menu.php not found
    pause
    exit /b 1
)

if not exist "APISecretKeyManager.php" (
    echo [WARNING] APISecretKeyManager.php not found
    echo Some features may not work properly.
)

if not exist "CloudflareSecurityRuleManager.php" (
    echo [WARNING] CloudflareSecurityRuleManager.php not found  
    echo Security Rules Manager may not work properly.
)

echo [INFO] Starting Tools Management Center...
echo.

REM Run the Tools Menu
php tools_menu.php

echo.
echo [INFO] Tools Menu stopped.
pause
exit /b 0