@echo off
setlocal enabledelayedexpansion

set "MODELS_DIR=C:\laragon\www\maco\app\Models"

if not exist "%MODELS_DIR%" (
    echo [ERROR] No existe la carpeta: %MODELS_DIR%
    pause
    exit /b 1
)

echo.
echo ===== Modelos en %MODELS_DIR% =====
echo.

for %%F in ("%MODELS_DIR%\*.php") do (
    set "name=%%~nF"
    echo !name!
)

echo.
echo ===== Total: contando... =====
set /a count=0
for %%F in ("%MODELS_DIR%\*.php") do set /a count+=1
echo Total: !count! modelos
echo.

pause