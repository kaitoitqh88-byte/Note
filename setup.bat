@echo off
echo ============================================
echo   CLOUDFLARE PHP PROJECT SETUP
echo ============================================
echo.

REM Check if PHP is installed
php --version > nul 2>&1
if %ERRORLEVEL% == 0 (
    echo [OK] PHP da duoc cai dat
    php --version
    echo.
) else (
    echo [ERROR] PHP chua duoc cai dat!
    echo.
    echo Vui long cai dat PHP truoc:
    echo 1. Download XAMPP: https://www.apachefriends.org/download.html
    echo 2. Hoac Laragon: https://laragon.org/download/
    echo 3. Hoac PHP standalone: https://windows.php.net/download/
    echo.
    pause
    exit /b 1
)

REM Check PHP extensions
echo Checking PHP extensions...
php -m | findstr /i curl > nul
if %ERRORLEVEL% == 0 (
    echo [OK] cURL extension enabled
) else (
    echo [WARNING] cURL extension not found
)

php -m | findstr /i json > nul  
if %ERRORLEVEL% == 0 (
    echo [OK] JSON extension enabled
) else (
    echo [WARNING] JSON extension not found
)

echo.

REM Check if token.txt exists and has content
if exist "token.txt" (
    for /f %%i in ("token.txt") do set size=%%~zi
    if !size! gtr 10 (
        echo [OK] Token file exists and has content
    ) else (
        echo [WARNING] Token file is empty or too small
        echo Please add your Cloudflare API token to token.txt
    )
) else (
    echo [ERROR] token.txt not found!
    echo Creating empty token.txt file...
    echo. > token.txt
    echo Please add your Cloudflare API token to token.txt
)

echo.

REM Test example script
echo Testing basic functionality...
php example.php

echo.
echo ============================================
echo Setup completed!
echo.
echo To start the development server:
echo   php -S localhost:8000
echo.
echo Then open: http://localhost:8000
echo ============================================
pause