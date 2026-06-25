@echo off
chcp 65001 >nul
title Besoiu — Robot PieseAuto
cd /d "%~dp0"
echo.
echo  Robot PieseAuto — lasa aceasta fereastra DESCHISA.
echo  Browserul Chrome se deschide doar din admin, la «Lansează browser robot».
echo.
call run_pieseauto_service.bat
