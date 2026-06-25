@echo off
REM Reutilizează SDK-ul din cursor-audit (același cursor-sdk)
cd /d "%~dp0"
if exist "%~dp0..\cursor-audit\pydeps\cursor_sdk" (
    echo SDK deja instalat in cursor-audit\pydeps
    exit /b 0
)
call "%~dp0..\cursor-audit\install.bat"
