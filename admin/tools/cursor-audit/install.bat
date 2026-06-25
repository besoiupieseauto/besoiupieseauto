@echo off
REM Instalează Cursor SDK Python pentru audit imagini din admin
cd /d "%~dp0"
py -3 -m pip install cursor-sdk --target "%~dp0pydeps"
py -3 "%~dp0patch_windows_bridge.py"
echo.
echo Gata. Adauga CURSOR_API_KEY in admin/.env si apasa Audit imagini in admin.
