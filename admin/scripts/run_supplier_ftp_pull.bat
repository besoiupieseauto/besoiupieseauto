@echo off
setlocal
chcp 65001 >nul
title Besoiu - FTP/SFTP pull furnizori

REM Descarca listele de pe FTP/SFTP in admin\storage\supplier_feeds\{cod}\
REM Task Scheduler: la 15-60 min. Optiuni: --force  --code=ELIT

for %%I in ("%~dp0..\..") do set "PROJECT_ROOT=%%~fI"
for %%I in ("%PROJECT_ROOT%\..\..") do set "LARAGON_ROOT=%%~fI"

set "PHP_EXE="
for /d %%D in ("%LARAGON_ROOT%\bin\php\php-*") do set "PHP_EXE=%%D\php.exe"
if not defined PHP_EXE ( where php >nul 2>&1 && set "PHP_EXE=php" )
if not defined PHP_EXE (
    echo [EROARE] php.exe negasit.
    exit /b 1
)

"%PHP_EXE%" "%PROJECT_ROOT%\admin\cron_cli\supplier_ftp_pull.php" %*
exit /b %ERRORLEVEL%
