@echo off
setlocal
call "%~dp0_cron_env.bat"
if errorlevel 1 exit /b 1

cd /d "%CRON_PROJECT_DIR%"
"%CRON_PHP_EXE%" cron_cli\queue_worker.php 20
set "EXIT_CODE=%ERRORLEVEL%"
echo queue_worker exit %EXIT_CODE% %DATE% %TIME%>> storage\logs\cron_queue_worker.log
exit /b %EXIT_CODE%
