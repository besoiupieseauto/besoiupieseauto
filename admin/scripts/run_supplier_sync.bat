@echo off
setlocal

call "%~dp0_cron_env.bat"
if errorlevel 1 exit /b 1

cd /d "%CRON_PROJECT_DIR%"
set "PHP_EXE=%CRON_PHP_EXE%"

if not exist "storage\logs" mkdir "storage\logs"

"%PHP_EXE%" scripts\supplier_sync_agent.php %*
set "EXIT_CODE=%ERRORLEVEL%"

echo.
echo Finalizat cu cod %EXIT_CODE% la %DATE% %TIME% >> storage\logs\supplier_sync.log

exit /b %EXIT_CODE%
