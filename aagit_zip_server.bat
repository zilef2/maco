@echo off
:: Arreglar caracteres raros en la consola para los emojis
chcp 65001 >nul
setlocal enabledelayedexpansion

:: 1. Definir rutas
set "DIR=C:\laragon\www\maco"
set "DEST=%DIR%\maco.zip"
cd /d "%DIR%"

:: 2. Preguntar si se compilan los assets
set /p do_build="¿Ejecutar npm run build para Macondas? (S/N): "
if /I "%do_build%"=="S" (
    echo [⚙️] Construyendo las cositas wujuju...
    call npm run build
)

:: 3. Limpieza y compresión (rutas relativas para un zip más limpio)
echo [📦] Comprimiendo archivos...
if exist "%DEST%" del "%DEST%"
if exist "public\hot" del /F /S "public\hot" 2>nul

"C:\Program Files\7-Zip\7z.exe" a -r -x!"vendor" -x!"storage" -x!"node_modules" -x!"public\hot.*" "%DEST%" "app" "resources" "routes" "database" "public" "lang" "config"

:: 4. Generador de Commits (11x11x11 = 1331 combinaciones + random ID)
set "w1_0=Mejorando" & set "w1_1=Ajustando" & set "w1_2=Optimizando" & set "w1_3=Puliendo" & set "w1_4=Refactorizando" & set "w1_5=Potenciando" & set "w1_6=Sincronizando" & set "w1_7=Desplegando" & set "w1_8=Magia en" & set "w1_9=Limpiando" & set "w1_10=Fixeando"
set "w2_0=el backend" & set "w2_1=la UI" & set "w2_2=las vistas" & set "w2_3=la DB" & set "w2_4=el core" & set "w2_5=componentes" & set "w2_6=las rutas" & set "w2_7=el sistema" & set "w2_8=el frontend" & set "w2_9=Macondas" & set "w2_10=los estilos"
set "w3_0=con estilo" & set "w3_1=al maximo" & set "w3_2=exitosamente" & set "w3_3=como un pro" & set "w3_4=rapidito" & set "w3_5=sin bugs" & set "w3_6=para produccion" & set "w3_7=modo ninja" & set "w3_8=impecable" & set "w3_9=con todo" & set "w3_10=al 100%%"

set /a r1=!random! %% 11, r2=!random! %% 11, r3=!random! %% 11
call set "commit_msg=%%w1_!r1!%% %%w2_!r2!%% %%w3_!r3!%% [!random!]"

:: 5. Git Automation
echo [🌿] Git commit: "!commit_msg!"
git add .
git commit -m "!commit_msg!"
git push

:: 6. Subir por SCP
echo [🚀] Subiendo al servidor de zilef...
scp -P 22022 -i C:\Users\alejo\.ssh\id_rsa "%DEST%" zilef@162.240.147.79:/home/zilef

:: 7. Levantar entorno dev
echo [💻] Listo
pause