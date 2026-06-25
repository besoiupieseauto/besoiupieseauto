@echo off
setlocal

call "%~dp0_cron_env.bat"
if errorlevel 1 exit /b 1

set "PROJECT_DIR=%CRON_PROJECT_DIR%"
set "PHP_EXE=%CRON_PHP_EXE%"
set "RCLONE_PS1=%PROJECT_DIR%\tools\install_rclone.ps1"

cd /d "%PROJECT_DIR%"

if not exist "tools\rclone\rclone.exe" (
  echo rclone lipseste. Instalez...
  powershell -NoProfile -ExecutionPolicy Bypass -File "%RCLONE_PS1%"
  if errorlevel 1 exit /b 1
)

echo === Generez rclone.conf din baza de date ===
"%PHP_EXE%" tools\generate_rclone_config.php
if errorlevel 1 exit /b 1

echo.
echo === Sync furnizori (rclone + upload server) ===
"%PHP_EXE%" scripts\supplier_sync_agent.php %*
set "EXIT_CODE=%ERRORLEVEL%"

if not exist "storage\logs" mkdir "storage\logs"
echo Finalizat cu cod %EXIT_CODE% la %DATE% %TIME% >> storage\logs\supplier_rclone_sync.log

exit /b %EXIT_CODE%
