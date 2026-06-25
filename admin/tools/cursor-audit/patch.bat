@echo off
REM Patch cursor-sdk pentru Windows (WinError 10038 la bridge stderr)
cd /d "%~dp0"
py -3 patch_windows_bridge.py
echo.
echo Daca apare "Patch aplicat", ruleaza din nou auditul din admin.
