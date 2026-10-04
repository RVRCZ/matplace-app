@echo off
rem Starts the farm agent; called by the scheduled task "matplace-farm-agent".
cd /d "%~dp0"
if exist agent.log move /y agent.log agent.log.prev >nul
"%~dp0venv\Scripts\python.exe" -m farm_agent --config "%~dp0config.yaml" 2>> "%~dp0agent.log"
exit /b %errorlevel%
