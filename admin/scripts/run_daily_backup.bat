@echo off
setlocal
call "%~dp0_cron_env.bat"
if errorlevel 1 exit /b 1

cd /d "%CRON_PROJECT_DIR%"
"%CRON_PHP_EXE%" cron_cli\daily_backup.php
set "EXIT_CODE=%ERRORLEVEL%"
echo daily_backup exit %EXIT_CODE% %DATE% %TIME%>> storage\logs\cron_daily_backup.log
exit /b %EXIT_CODE%
