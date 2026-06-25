@echo off
rem Cale proiect + PHP CLI Laragon (partajat de toate joburile .bat)
set "CRON_PROJECT_DIR=E:\laragon\www\besoiupieseauto.ro\admin"
set "CRON_PHP_EXE=E:\laragon\bin\php\php-8.3.30-Win32-vs16-x64\php.exe"

if not exist "%CRON_PHP_EXE%" set "CRON_PHP_EXE=E:\laragon\bin\php\php-8.1.31-Win32-vs16-x64\php.exe"

if not exist "%CRON_PHP_EXE%" (
  echo PHP CLI negasit in Laragon bin\php
  exit /b 1
)

if not exist "%CRON_PROJECT_DIR%" (
  echo Director admin negasit: %CRON_PROJECT_DIR%
  exit /b 1
)

exit /b 0
