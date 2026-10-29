@echo off
setlocal enabledelayedexpansion

echo ==============================================
echo    Entidades encontradas en app/models
echo ==============================================
echo.

:: Verifica si el directorio existe
if not exist "app\models\" (
    echo [ERROR] No se encontro el directorio 'app\models'.
    echo Asegurate de ejecutar este script desde la raiz del proyecto.
    goto :fin
)

:: Bandera para saber si encontró archivos
set "encontrado=0"

:: Recorre todos los archivos .php en app/models
for %%F in (app\models\*.php) do (
    set "encontrado=1"
    echo - %%~nF
)

if "!encontrado!"=="0" (
    echo No se encontraron modelos PHP en la carpeta.
)

:fin
echo.
echo ==============================================
pause