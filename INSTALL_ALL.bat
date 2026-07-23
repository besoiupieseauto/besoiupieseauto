@echo off
setlocal enabledelayedexpansion
chcp 65001 >nul
title Besoiu Piese Auto - Instalare completa dependinte

REM ============================================================================
REM  INSTALL_ALL.bat  -  Instaleaza TOATE dependintele proiectului pe un PC nou.
REM
REM  Ce face:
REM    1. Detecteaza PHP + Composer din Laragon (sau din PATH)
REM    2. composer install in  admin/  si  app/Backend/
REM    3. Copiaza .env.example -> .env (unde lipseste)
REM    4. Ollama: verifica + descarca modelele LLM locale
REM    5. Python venv + pip pentru: stealth-browser-mcp, scraper-core, modulOrchestr
REM    6. Playwright (chromium) pentru scraper-core
REM
REM  Rulare: dublu-click pe fisier SAU  INSTALL_ALL.bat  din terminal Laragon.
REM ============================================================================

cd /d "%~dp0"
set "PROJECT_ROOT=%~dp0"
set "PROJECT_ROOT=%PROJECT_ROOT:~0,-1%"
REM Laragon root = ...\laragon\www\<proiect>  ->  urca 2 nivele
for %%I in ("%PROJECT_ROOT%\..\..") do set "LARAGON_ROOT=%%~fI"

set "WARN=0"

echo(
echo ============================================================
echo   BESOIU PIESE AUTO - INSTALARE DEPENDINTE
echo   Proiect : %PROJECT_ROOT%
echo   Laragon : %LARAGON_ROOT%
echo ============================================================
echo(

REM ---------------------------------------------------------------------------
REM  [0] Detectare PHP
REM ---------------------------------------------------------------------------
set "PHP_EXE="
for /d %%D in ("%LARAGON_ROOT%\bin\php\php-*") do set "PHP_EXE=%%D\php.exe"
if not defined PHP_EXE (
    where php >nul 2>&1 && set "PHP_EXE=php"
)
if not defined PHP_EXE (
    echo [EROARE] Nu am gasit php.exe. Porneste Laragon sau adauga PHP in PATH.
    set "WARN=1"
) else (
    echo [OK] PHP: %PHP_EXE%
)

REM ---------------------------------------------------------------------------
REM  [0] Detectare Composer
REM ---------------------------------------------------------------------------
set "COMPOSER_CMD="
if exist "%LARAGON_ROOT%\bin\composer\composer.phar" if defined PHP_EXE (
    set "COMPOSER_CMD="%PHP_EXE%" "%LARAGON_ROOT%\bin\composer\composer.phar""
)
if not defined COMPOSER_CMD (
    where composer >nul 2>&1 && set "COMPOSER_CMD=composer"
)
if not defined COMPOSER_CMD (
    echo [AVERTISMENT] Composer negasit - sar peste pasul PHP.
    set "WARN=1"
) else (
    echo [OK] Composer detectat.
)

echo(
echo ------------------------------------------------------------
echo   [1/5] Composer install (PHP)
echo ------------------------------------------------------------
if defined COMPOSER_CMD (
    call :composer_install "%PROJECT_ROOT%\admin"
    call :composer_install "%PROJECT_ROOT%\app\Backend"
) else (
    echo   ... sarit (Composer indisponibil^)
)

echo(
echo ------------------------------------------------------------
echo   [2/5] Fisiere .env (copiere din .env.example)
echo ------------------------------------------------------------
call :copy_env "%PROJECT_ROOT%\app\Config\.env.example"                 "%PROJECT_ROOT%\app\Config\.env"
call :copy_env "%PROJECT_ROOT%\app\Import\Scraper\.env.example"         "%PROJECT_ROOT%\app\Import\Scraper\.env"
call :copy_env "%PROJECT_ROOT%\app\Import\modulOrchestr\.env.example"   "%PROJECT_ROOT%\app\Import\modulOrchestr\.env"

echo(
echo ------------------------------------------------------------
echo   [3/5] Ollama - modele LLM locale
echo ------------------------------------------------------------
where ollama >nul 2>&1
if errorlevel 1 (
    echo [AVERTISMENT] Ollama nu e instalat.
    echo   Descarca de la: https://ollama.com/download
    echo   Apoi ruleaza din nou acest script, sau manual:
    echo      ollama pull qwen2.5:7b
    echo      ollama pull qwen2.5:3b
    echo      ollama pull llava:7b
    echo      ollama pull nomic-embed-text
    set "WARN=1"
) else (
    echo [OK] Ollama detectat. Descarc modelele (poate dura mult prima data^)...
    call :ollama_pull qwen2.5:7b
    call :ollama_pull qwen2.5:3b
    call :ollama_pull llava:7b
    call :ollama_pull nomic-embed-text
    echo   (optional vision scraper^) llama3.2-vision:11b - descarca manual daca il folosesti.
)

echo(
echo ------------------------------------------------------------
echo   [4/5] Detectare Python
echo ------------------------------------------------------------
set "PY_CMD="
py -3.12 --version >nul 2>&1 && set "PY_CMD=py -3.12"
if not defined PY_CMD ( py -3.11 --version >nul 2>&1 && set "PY_CMD=py -3.11" )
if not defined PY_CMD if exist "%LARAGON_ROOT%\bin\python\python.exe" set "PY_CMD="%LARAGON_ROOT%\bin\python\python.exe""
if not defined PY_CMD ( py --version >nul 2>&1 && set "PY_CMD=py" )
if not defined PY_CMD ( where python >nul 2>&1 && set "PY_CMD=python" )
if not defined PY_CMD (
    echo [AVERTISMENT] Python negasit - sar peste venv-urile Python.
    set "WARN=1"
) else (
    echo [OK] Python: %PY_CMD%
    echo     Nota: stealth-browser-mcp merge cel mai bine pe Python 3.11-3.12.
)

echo(
echo ------------------------------------------------------------
echo   [5/5] Python venv + dependinte
echo ------------------------------------------------------------
if defined PY_CMD (
    call :setup_venv "%PROJECT_ROOT%\tools\stealth-browser-mcp"        "venv"  "0"
    call :setup_venv "%PROJECT_ROOT%\tools\scraper-core"               "venv"  "1"
    call :setup_venv "%PROJECT_ROOT%\app\Import\modulOrchestr\python"  ".venv" "0"
) else (
    echo   ... sarit (Python indisponibil^)
)

echo(
echo ============================================================
if "%WARN%"=="1" (
    echo   GATA - cu AVERTISMENTE. Vezi mesajele [AVERTISMENT] mai sus.
) else (
    echo   GATA - toate dependintele au fost procesate cu succes.
)
echo   Urmatorul pas: porneste serviciile cu  START_ALL.bat
echo ============================================================
echo(
pause
exit /b 0

REM ===========================================================================
REM  SUBRUTINE
REM ===========================================================================

:composer_install
REM  %~1 = folder care contine composer.json
if not exist "%~1\composer.json" (
    echo   [skip] %~1  (fara composer.json^)
    goto :eof
)
echo   composer install -^> %~1
pushd "%~1"
call %COMPOSER_CMD% install --no-interaction --prefer-dist
if errorlevel 1 (
    echo   [AVERTISMENT] composer install a esuat in %~1
    set "WARN=1"
)
popd
goto :eof

:copy_env
REM  %~1 = .env.example sursa   %~2 = .env destinatie
if not exist "%~1" (
    echo   [skip] lipseste %~1
    goto :eof
)
if exist "%~2" (
    echo   [pastrat] %~2 exista deja
) else (
    copy /y "%~1" "%~2" >nul
    echo   [creat]  %~2  ^(completeaza cheile secrete^!^)
)
goto :eof

:ollama_pull
echo   ollama pull %~1
ollama pull %~1
if errorlevel 1 (
    echo   [AVERTISMENT] Nu am putut descarca modelul %~1
    set "WARN=1"
)
goto :eof

:setup_venv
REM  %~1 = folder tool   %~2 = nume venv (venv/.venv)   %~3 = 1 daca are nevoie de playwright
if not exist "%~1\requirements.txt" (
    echo   [skip] %~1  (fara requirements.txt^)
    goto :eof
)
echo(
echo   [tool] %~1
pushd "%~1"
if not exist "%~2\Scripts\python.exe" (
    echo      creez venv (%~2^)...
    %PY_CMD% -m venv "%~2"
)
if not exist "%~2\Scripts\python.exe" (
    echo      [AVERTISMENT] nu am putut crea venv in %~1
    set "WARN=1"
    popd
    goto :eof
)
echo      pip install -r requirements.txt ...
"%~2\Scripts\python.exe" -m pip install --upgrade pip >nul 2>&1
"%~2\Scripts\python.exe" -m pip install -r requirements.txt
if errorlevel 1 (
    echo      [AVERTISMENT] pip install a esuat in %~1
    echo      Sfat: daca esueaza pydantic/nodriver, foloseste Python 3.12.
    set "WARN=1"
)
if "%~3"=="1" (
    echo      playwright install chromium ...
    "%~2\Scripts\python.exe" -m playwright install chromium
)
popd
goto :eof
