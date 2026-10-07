@echo off
REM Cloudflare Security Tool - PHP Windows Launcher

echo 🛡️ Cloudflare Security Tool - PHP Version
echo ==========================================

REM Check if PHP is available
php --version >nul 2>&1
if %errorlevel% neq 0 (
    echo [ERROR] PHP not found. Please install PHP first.
    echo Download from: https://php.net/downloads
    pause
    exit /b 1
)

REM Check if tool file exists
if not exist "cloudflare_security_tool.html" (
    echo [ERROR] Tool file not found: cloudflare_security_tool.html
    pause
    exit /b 1
)

echo [INFO] Starting PHP launcher...
echo.

REM Run the PHP launcher
php portable_launcher.php --start

REM If we get here, the server stopped
echo.
echo [INFO] Server stopped.
pause
exit /b 0