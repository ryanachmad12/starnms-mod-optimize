@echo off
cd /d C:\xampp\htdocs\STARNMS VERSION1
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\start-redis.ps1"
if errorlevel 1 (
    echo.
    echo ERROR: Redis belum aktif. Install Memurai atau Docker Desktop terlebih dahulu.
    echo Redis wajib listening pada 127.0.0.1:6379 sebelum worker dijalankan.
    pause
    exit /b 1
)
timeout /t 2 /nobreak >nul
start "STARNMS Web" /min C:\xampp\php\php.exe -S 127.0.0.1:8000 -t public public\router.php
start "STARNMS Scheduler" /min C:\xampp\php\php.exe artisan schedule:work
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\start-workers.ps1"
timeout /t 2 /nobreak >nul
start http://127.0.0.1:8000
