@echo off
REM Rulează cron watcher import — programabil în Task Scheduler Windows
cd /d "%~dp0src"
py -3 cron_watcher.py %*
