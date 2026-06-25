@echo off
setlocal
call "%~dp0_cron_env.bat"
if errorlevel 1 exit /b 1

cd /d "%CRON_PROJECT_DIR%"
"%CRON_PHP_EXE%" scripts\refresh_dashboard_snapshot.php
set "EXIT_CODE=%ERRORLEVEL%"
echo refresh_dashboard exit %EXIT_CODE% %DATE% %TIME%>> storage\logs\cron_refresh_dashboard.log
exit /b %EXIT_CODE%
