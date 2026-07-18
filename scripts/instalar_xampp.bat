@echo off
setlocal
cd /d "%~dp0\.."
where bash >nul 2>nul
if errorlevel 1 (
  echo No se encontro Bash. Abra Git Bash dentro de esta carpeta y ejecute:
  echo.
  echo     bash install.sh
  echo.
  pause
  exit /b 1
)
bash install.sh
pause
