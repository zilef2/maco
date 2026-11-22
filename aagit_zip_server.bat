
@echo off
chcp 65001 >nul
setlocal EnableExtensions EnableDelayedExpansion

:: ==================================================
:: CONFIGURACION
:: ==================================================
set "DIR=C:\laragon\www\maco"
set "DEST=%DIR%\maco.zip"
set "SEVENZIP=C:\Program Files\7-Zip\7z.exe"
set "SSH_KEY=C:\Users\alejo\.ssh\id_rsa"
set "SERVER=zilef@162.240.147.79"
set "REMOTE=/home/zilef"
set "PORT=22022"

cd /d "%DIR%" || goto error

:: ==================================================
:: MENU
:: ==================================================
:menu
cls
echo.
echo ==========================================
echo             MACONDAS DEPLOY
echo ==========================================
echo.
echo Selecciona las acciones:
echo.
echo [1] Build + Git + Comprimir + Subir
echo [2] Build + Git + Comprimir
echo [3] Comprimir + Subir
echo [4] Solo comprimir
echo [5] Personalizado
echo [0] Salir
echo.
set "OPTION="
set /p "OPTION=Opcion: "

set "DO_BUILD=0"
set "DO_UPLOAD=0"
set "DO_ZIP=1"

if "%OPTION%"=="1" (
    set "DO_BUILD=1"
    set "DO_UPLOAD=1"
) else if "%OPTION%"=="2" (
    set "DO_BUILD=1"
) else if "%OPTION%"=="3" (
    set "DO_UPLOAD=1"
) else if "%OPTION%"=="4" (
    rem Solo comprimir
) else if "%OPTION%"=="5" (
    goto custom
) else if "%OPTION%"=="0" (
    exit /b
) else (
    goto menu
)

goto preview

:: ==================================================
:: PERSONALIZADO
:: ==================================================
:custom
cls
echo.
echo ===== CONFIGURACION PERSONALIZADA =====
echo.

choice /C SN /N /M "Ejecutar npm run build? (S/N): "
if errorlevel 2 (
    set "DO_BUILD=0"
) else (
    set "DO_BUILD=1"
)

choice /C SN /N /M "Subir ZIP al servidor? (S/N): "
if errorlevel 2 (
    set "DO_UPLOAD=0"
) else (
    set "DO_UPLOAD=1"
)

goto preview

:: ==================================================
:: VISTA PREVIA
:: ==================================================
:preview
cls
echo.
echo ==========================================
echo             VISTA PREVIA
echo ==========================================
echo.
echo Proyecto: %DIR%
echo ZIP:      %DEST%
echo.

if "!DO_BUILD!"=="1" (
    echo [X] npm run build
    echo [X] Git add, commit y push
) else (
    echo [ ] Build
    echo [ ] Git
)

if "!DO_ZIP!"=="1" echo [X] Comprimir archivos

if "!DO_UPLOAD!"=="1" (
    echo [X] Subir por SCP
    echo     Servidor: %SERVER%
    echo     Destino: %REMOTE%
) else (
    echo [ ] Subir al servidor
)

echo.
echo ==========================================
echo.
echo [1] Ejecutar
echo [2] Volver al menu
echo [0] Cancelar
echo.

choice /C 120 /N /M "Seleccion: "
if errorlevel 3 exit /b
if errorlevel 2 goto menu

:: ==================================================
:: BUILD
:: ==================================================
if "!DO_BUILD!"=="1" (
    echo.
    echo [BUILD] Construyendo assets...

    call npm run build
    if errorlevel 1 goto error

    echo [OK] Build terminado.

    call :git_commit
    if errorlevel 1 goto error
)

:: ==================================================
:: COMPRESION
:: ==================================================
if "!DO_ZIP!"=="1" (
    echo.
    echo [ZIP] Comprimiendo archivos...

    if not exist "%SEVENZIP%" (
        echo [ERROR] No se encontro 7-Zip.
        goto error
    )

    if exist "%DEST%" del /F /Q "%DEST%"

    if exist "public\hot" del /F /Q "public\hot"

    "%SEVENZIP%" a -tzip -r "%DEST%" ^
        -x!vendor ^
        -x!storage ^
        -x!node_modules ^
        -x!public\hot ^
        -x!public\hot.* ^
        app resources routes database public lang config

    if errorlevel 1 goto error

    echo [OK] ZIP generado.
)

:: ==================================================
:: SUBIDA SSH
:: ==================================================
if "!DO_UPLOAD!"=="1" (
    echo.
    echo [SCP] Subiendo al servidor...

    if not exist "%DEST%" (
        echo [ERROR] No existe el ZIP.
        goto error
    )

    scp -P %PORT% -i "%SSH_KEY%" "%DEST%" %SERVER%:%REMOTE%

    if errorlevel 1 goto error

    echo [OK] Archivo subido.
)

:: ==================================================
:: FINAL
:: ==================================================
echo.
echo ==========================================
echo       PROCESO COMPLETADO
echo ==========================================
echo.
pause
exit /b 0

:: ==================================================
:: GIT
:: ==================================================
:git_commit
set "COMMIT_MSG="

set /a R1=!random! %% 11
set /a R2=!random! %% 11
set /a R3=!random! %% 11

set "W1_0=Mejorando"
set "W1_1=Ajustando"
set "W1_2=Optimizando"
set "W1_3=Puliendo"
set "W1_4=Refactorizando"
set "W1_5=Potenciando"
set "W1_6=Sincronizando"
set "W1_7=Desplegando"
set "W1_8=Magia en"
set "W1_9=Limpiando"
set "W1_10=Fixeando"

set "W2_0=el backend"
set "W2_1=la UI"
set "W2_2=las vistas"
set "W2_3=la DB"
set "W2_4=el core"
set "W2_5=componentes"
set "W2_6=las rutas"
set "W2_7=el sistema"
set "W2_8=el frontend"
set "W2_9=Macondas"
set "W2_10=los estilos"

set "W3_0=con estilo"
set "W3_1=al maximo"
set "W3_2=exitosamente"
set "W3_3=como un pro"
set "W3_4=rapidito"
set "W3_5=sin bugs"
set "W3_6=para produccion"
set "W3_7=modo ninja"
set "W3_8=impecable"
set "W3_9=con todo"
set "W3_10=al 100%%"

call set "COMMIT_MSG=%%W1_!R1!%% %%W2_!R2!%% %%W3_!R3!%%"

echo [GIT] Commit: !COMMIT_MSG!

git add .
if errorlevel 1 exit /b 1

git commit -m "!COMMIT_MSG!"
if errorlevel 1 exit /b 1

git push
if errorlevel 1 exit /b 1

echo [OK] Git sincronizado.
exit /b 0

:: ==================================================
:: ERROR
:: ==================================================
:error
echo.
echo [ERROR] El proceso se detuvo por un error.
echo Revisa el mensaje anterior.
echo.
pause
exit /b 1
