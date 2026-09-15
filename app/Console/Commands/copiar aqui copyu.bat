@echo off
setlocal enabledelayedexpansion
@REM EJECUTAR PARA TOMAR LOS ARCHIVOS DE ARCHIVOSCOPY
:: Configuración de rutas
set "ORIGEN=C:\laragon\www\archivoscopy"
set "DESTINO=%~dp0"

echo Iniciando proceso de copia...
echo.

:: Verificar si existe el directorio origen
if not exist "%ORIGEN%" (
    echo ERROR: No se encuentra el directorio origen: %ORIGEN%
    pause
    exit /b 1
)

:: 1. Copiar carpeta generic
set "RUTA_GENERIC_ORIGEN=%ORIGEN%\resources\js\Pages\generic"
set "RUTA_GENERIC_DESTINO=%DESTINO%\resources\js\Pages\generic"

if exist "%RUTA_GENERIC_ORIGEN%" (
    echo Copiando carpeta generic...
    xcopy "%RUTA_GENERIC_ORIGEN%" "%RUTA_GENERIC_DESTINO%" /E /I /Y
    if !errorlevel! equ 0 (
        echo ✓ Carpeta generic copiada exitosamente
    ) else (
        echo ✗ Error al copiar carpeta generic
    )
) else (
    echo ✗ No se encuentra la carpeta origen: %RUTA_GENERIC_ORIGEN%
)

:: 2. Copiar archivo genericController.php
set "ARCHIVO_CONTROLLER_ORIGEN=%ORIGEN%\app\Http\Controllers\genericController.php"
set "ARCHIVO_CONTROLLER_DESTINO=%DESTINO%\app\Http\Controllers\"

if exist "%ARCHIVO_CONTROLLER_ORIGEN%" (
    echo Copiando genericController.php...
    if not exist "%ARCHIVO_CONTROLLER_DESTINO%" (
        mkdir "%ARCHIVO_CONTROLLER_DESTINO%"
    )
    copy "%ARCHIVO_CONTROLLER_ORIGEN%" "%ARCHIVO_CONTROLLER_DESTINO%" /Y
    if !errorlevel! equ 0 (
        echo ✓ genericController.php copiado exitosamente
    ) else (
        echo ✗ Error al copiar genericController.php
    )
) else (
    echo ✗ No se encuentra el archivo: %ARCHIVO_CONTROLLER_ORIGEN%
)

echo.

:: Verificar si las operaciones anteriores fueron exitosas
set "OPERACION_EXITOSA=1"
if not exist "%RUTA_GENERIC_DESTINO%" set "OPERACION_EXITOSA=0"
if not exist "%ARCHIVO_CONTROLLER_DESTINO%genericController.php" set "OPERACION_EXITOSA=0"

:: 3. Si no hubo errores, copiar archivos PHP a Commands
if "%OPERACION_EXITOSA%"=="1" (
    echo Proceso exitoso, copiando archivos PHP a Commands...
    
    set "RUTA_COMMANDS_DESTINO=%DESTINO%\app\Console\Commands\"
    
    :: Crear directorio si no existe
    if not exist "%RUTA_COMMANDS_DESTINO%" (
        mkdir "%RUTA_COMMANDS_DESTINO%"
    )
    
    :: Copiar todos los archivos .php del origen al destino Commands
    for /r "%ORIGEN%" %%f in (*.php) do (
        echo Copiando: %%~nxf
        copy "%%f" "%RUTA_COMMANDS_DESTINO%" /Y >nul
    )
    
    echo ✓ Todos los archivos PHP copiados a Commands
) else (
    echo ✗ No se completaron las operaciones anteriores, se omite la copia a Commands
)

echo.
echo Proceso completado.
pause