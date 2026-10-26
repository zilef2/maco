@echo off
setlocal enabledelayedexpansion

set "WWW=C:\laragon\www"

if not exist "%WWW%" (
    echo La carpeta %WWW% no existe. Saliendo...
    pause
    exit /b 1
)

echo ============================================
echo  Carpetas disponibles en %WWW%
echo ============================================

set "i=0"
for /d %%D in ("%WWW%\*") do (
    set /a i+=1
    set "dir[!i!]=%%~nxD"
    echo  !i!) %%~nxD
)

if %i%==0 (
    echo No hay carpetas en %WWW%. Saliendo...
    pause
    exit /b 1
)

echo ============================================
set /p opcion="Elige una opcion (1-%i%): "

set "carpeta=!dir[%opcion%]!"

if not defined carpeta (
    echo Opcion no valida. Saliendo...
    pause
    exit /b 1
)

set "BASE=%WWW%\%carpeta%"

echo.
echo Carpeta base seleccionada: %BASE%
echo.

set /p modelo="Introduce el nombre del modelo (ej. Proceso): "

if "%modelo%"=="" (
    echo No se especifico modelo. Saliendo...
    pause
    exit /b 1
)

echo Eliminando archivos para %modelo% en %BASE%...

:: Borrar Modelo
if exist "%BASE%\app\Models\%modelo%.php" del "%BASE%\app\Models\%modelo%.php"

:: Borrar Controlador
if exist "%BASE%\app\Http\Controllers\%modelo%Controller.php" del "%BASE%\app\Http\Controllers\%modelo%Controller.php"

:: Borrar Requests
if exist "%BASE%\app\Http\Requests\%modelo%" rd /s /q "%BASE%\app\Http\Requests\%modelo%"

:: Borrar Factory
if exist "%BASE%\database\factories\%modelo%Factory.php" del "%BASE%\database\factories\%modelo%Factory.php"

:: Borrar Seeder
if exist "%BASE%\database\seeders\%modelo%Seeder.php" del "%BASE%\database\seeders\%modelo%Seeder.php"

:: Borrar Migracion (busca por nombre parcial por el timestamp)
if exist "%BASE%\database\migrations\*_create_%modelo%s_table.php" del "%BASE%\database\migrations\*_create_%modelo%s_table.php"

echo Proceso finalizado. Recuerda ejecutar 'composer dump-autoload'.
pause
endlocal