@echo off
setlocal enabledelayedexpansion
chcp 65001 >nul
title Besoiu Piese Auto - Pornire servicii fundal

REM ============================================================================
REM  START_ALL.bat  -  Porneste serviciile de fundal necesare proiectului.
REM
REM  Porneste (daca nu ruleaza deja):
REM    1. Ollama            (LLM local, port 11434)
REM    2. Redis             (coada async + AI events, port 6379 - din Laragon)
REM    3. modulOrchestr     (Python FastAPI :8765) - DOAR daca e activat in .env
REM    4. event_writer      (worker AI events -> MySQL) - doar daca Redis merge
REM
REM  NU porneste: Apache/Nginx + MySQL -> acelea le porneste Laragon (butonul Start).
REM  Scraperul (stealth-browser-mcp / scraper-core) porneste la cerere din admin.
REM ============================================================================

cd /d "%~dp0"
set "PROJECT_ROOT=%~dp0"
set "PROJECT_ROOT=%PROJECT_ROOT:~0,-1%"
for %%I in ("%PROJECT_ROOT%\..\..") do set "LARAGON_ROOT=%%~fI"

echo(
echo ============================================================
echo   BESOIU PIESE AUTO - PORNIRE SERVICII FUNDAL
echo ============================================================
echo(

REM --- PHP ---
set "PHP_EXE="
for /d %%D in ("%LARAGON_ROOT%\bin\php\php-*") do set "PHP_EXE=%%D\php.exe"
if not defined PHP_EXE ( where php >nul 2>&1 && set "PHP_EXE=php" )

REM ---------------------------------------------------------------------------
REM  1) OLLAMA
REM ---------------------------------------------------------------------------
echo [1] Ollama (port 11434)...
call :port_open 11434
if "!PORT_OK!"=="1" (
    echo     [deja pornit]
) else (
    where ollama >nul 2>&1
    if errorlevel 1 (
        echo     [LIPSA] Ollama nu e instalat - vezi INSTALL_ALL.bat / https://ollama.com
    ) else (
        echo     pornesc "ollama serve"...
        start "Ollama" /min ollama serve
    )
)

REM ---------------------------------------------------------------------------
REM  2) REDIS (din Laragon)
REM ---------------------------------------------------------------------------
echo [2] Redis (port 6379)...
call :port_open 6379
if "!PORT_OK!"=="1" (
    echo     [deja pornit]
) else (
    set "REDIS_EXE="
    for /f "delims=" %%R in ('dir /b /s "%LARAGON_ROOT%\bin\redis\redis-server.exe" 2^>nul') do set "REDIS_EXE=%%R"
    if defined REDIS_EXE (
        echo     pornesc Redis...
        start "Redis" /min "!REDIS_EXE!"
    ) else (
        echo     [optional] Redis negasit - proiectul foloseste fallback pe fisier ^(ASYNC_FILE_FALLBACK=1^).
    )
)

REM ---------------------------------------------------------------------------
REM  3) modulOrchestr - Python FastAPI (:8765) - doar daca e activat
REM ---------------------------------------------------------------------------
echo [3] modulOrchestr (Python :8765)...
set "ORCH_ON=0"
if exist "%PROJECT_ROOT%\app\Import\modulOrchestr\.env" (
    for /f "usebackq tokens=1,2 delims==" %%A in ("%PROJECT_ROOT%\app\Import\modulOrchestr\.env") do (
        if /i "%%A"=="ORCHESTR_PYTHON_ENABLED" if "%%B"=="1" set "ORCH_ON=1"
    )
)
if "!ORCH_ON!"=="1" (
    if exist "%PROJECT_ROOT%\app\Import\modulOrchestr\python\tools\start_engine.bat" (
        echo     pornesc engine LangGraph...
        start "modulOrchestr" /min cmd /c "%PROJECT_ROOT%\app\Import\modulOrchestr\python\tools\start_engine.bat"
    )
) else (
    echo     [dezactivat] ORCHESTR_PYTHON_ENABLED != 1 in .env - sar peste.
)

REM ---------------------------------------------------------------------------
REM  4) Worker AI events (Redis -> MySQL)
REM ---------------------------------------------------------------------------
echo [4] Worker AI events...
call :port_open 6379
if "!PORT_OK!"=="1" (
    if exist "%PROJECT_ROOT%\admin\scripts\run_event_writer.bat" (
        echo     pornesc event_writer_worker ^(loop 60s^)...
        start "AI Event Writer" /min cmd /c "%PROJECT_ROOT%\admin\scripts\run_event_writer.bat"
    )
) else (
    echo     [sarit] Redis inactiv - workerul nu e necesar.
)

echo(
echo ============================================================
echo   Servicii pornite. NU uita:
echo     - Porneste LARAGON (Apache/Nginx + MySQL) din interfata Laragon.
echo     - Scraperul porneste automat la cerere din /admin.
echo ============================================================
echo(
pause
exit /b 0

REM ===========================================================================
:port_open
REM  %~1 = port ; seteaza PORT_OK=1 daca portul raspunde pe 127.0.0.1
set "PORT_OK=0"
powershell -NoProfile -Command "try{$c=New-Object Net.Sockets.TcpClient;$c.Connect('127.0.0.1',%~1);$c.Close();exit 0}catch{exit 1}" >nul 2>&1
if not errorlevel 1 set "PORT_OK=1"
goto :eof
