@echo off
setlocal enabledelayedexpansion
chcp 65001 >nul
title Besoiu - AI Event Writer (loop 60s)

REM ============================================================================
REM  run_event_writer.bat - ruleaza event_writer_worker.php in bucla (la 60s).
REM  Consuma coada Redis "ai-events" si scrie batch-uri in MySQL.
REM  Poate fi folosit si ca task in Task Scheduler (fara bucla, un singur pas).
REM ============================================================================

REM  admin\scripts -> urca la root proiect -> urca 2 la Laragon
for %%I in ("%~dp0..\..") do set "PROJECT_ROOT=%%~fI"
for %%I in ("%PROJECT_ROOT%\..\..") do set "LARAGON_ROOT=%%~fI"

set "PHP_EXE="
for /d %%D in ("%LARAGON_ROOT%\bin\php\php-*") do set "PHP_EXE=%%D\php.exe"
if not defined PHP_EXE ( where php >nul 2>&1 && set "PHP_EXE=php" )
if not defined PHP_EXE (
    echo [EROARE] php.exe negasit.
    pause
    exit /b 1
)

:loop
"%PHP_EXE%" "%PROJECT_ROOT%\admin\workers\event_writer_worker.php" --max=100
timeout /t 60 /nobreak >nul
goto loop
