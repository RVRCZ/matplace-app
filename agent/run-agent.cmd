@echo off
rem Starts the farm agent; called by the scheduled task "matplace-farm-agent" (restarted by the task when it dies).
cd /d "%~dp0"
if exist agent.log move /y agent.log agent.log.prev >nul
set "PY=%~dp0venv\Scripts\python.exe"
if not exist "%PY%" set "PY=%LOCALAPPDATA%\Microsoft\WindowsApps\python.exe"
"%PY%" -m farm_agent --config "%~dp0config.yaml" 2>> "%~dp0agent.log"
exit /b %errorlevel%
