@echo off

chcp 65001 >nul

cd /d "%~dp0"

set ROBOT_PIESEAUTO_PORT=5011

set ROBOT_CHANNEL_ID=besoiu

set PYTHON_BIN=C:\laragon\bin\python\python-3.13\python.exe

if not exist "%PYTHON_BIN%" set PYTHON_BIN=python



set ENV_FILE=%~dp0..\admin\.env

if exist "%ENV_FILE%" (

    for /f "usebackq tokens=1,2 delims==" %%A in (`findstr /r "^ROBOT_PIESEAUTO_PORT= ^ROBOT_CHANNEL_ID=" "%ENV_FILE%"`) do (

        if /i "%%A"=="ROBOT_PIESEAUTO_PORT" set ROBOT_PIESEAUTO_PORT=%%B

        if /i "%%A"=="ROBOT_CHANNEL_ID" set ROBOT_CHANNEL_ID=%%B

    )

)



:loop

echo [%date% %time%] Pornesc robot_pieseauto.py (canal %ROBOT_CHANNEL_ID%, port dorit %ROBOT_PIESEAUTO_PORT%)...

set ROBOT_PIESEAUTO_PORT=%ROBOT_PIESEAUTO_PORT%

set ROBOT_CHANNEL_ID=%ROBOT_CHANNEL_ID%

"%PYTHON_BIN%" -u robot_pieseauto.py >> robot_pieseauto_service.log 2>&1

echo [%date% %time%] Robot oprit — repornesc in 3 secunde...

timeout /t 3 /nobreak >nul

goto loop

