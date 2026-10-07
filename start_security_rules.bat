@echo off
REM Cloudflare Security Rule Manager - Windows Launcher

echo 🛡️ Cloudflare Security Rule Manager
echo =====================================

REM Check if PHP is available
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] PHP not found. Please install PHP first.
    echo Download from: https://php.net/downloads
    pause
    exit /b 1
)

REM Check if required files exist
if not exist "CloudflareSecurityRuleManager.php" (
    echo [ERROR] CloudflareSecurityRuleManager.php not found
    pause
    exit /b 1
)

if not exist "config.json" (
    echo [WARNING] config.json not found. Please create configuration file first.
    echo See documentation for setup instructions.
    pause
)

echo [INFO] Starting Security Rule Manager...
echo.

REM Run the Security Rule Manager
php CloudflareSecurityRuleManager.php

echo.
echo [INFO] Security Rule Manager stopped.
pause
exit /b 0