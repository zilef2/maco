@echo on
setlocal enabledelayedexpansion

REM ============================================================
REM  Detecta automaticamente la carpeta del proyecto.
REM  - Si el .bat esta en la raiz del proyecto, usa esa carpeta.
REM  - Si el .bat esta en una subcarpeta (ej: \losbat\),
REM    sube un nivel para encontrar la raiz del proyecto.
REM ============================================================

set "BAT_DIR=%~dp0"
set "BAT_DIR=%BAT_DIR:~0,-1%"

REM Si el .bat esta dentro de una carpeta llamada "losbat", subimos un nivel
for %%A in ("%BAT_DIR%") do set "BAT_PADRE=%%~dpA"
for %%A in ("%BAT_DIR%") do set "BAT_NOMBRE=%%~nxA"

if /i "%BAT_NOMBRE%"=="losbat" (
    set "PROYECTO=%BAT_PADRE:~0,-1%"
) else (
    set "PROYECTO=%BAT_DIR%"
)

set "NM_DIR=%PROYECTO%\node_modules"
set "PKG=%PROYECTO%\package.json"
set "SALIDA=%PROYECTO%\analisis_node_modules.txt"

echo.
echo ============================================================
echo  Proyecto detectado: %PROYECTO%
echo ============================================================
echo.

if not exist "%NM_DIR%" (
    echo [ERROR] No existe: %NM_DIR%
    echo Coloca este .bat en la raiz del proyecto o en \losbat\.
    pause
    exit /b 1
)

REM ============================================================
REM  ENCABEZADO
REM ============================================================
> "%SALIDA%" echo ============================================================
>>"%SALIDA%" echo  ANALISIS DE NODE_MODULES
>>"%SALIDA%" echo  Proyecto: %PROYECTO%
>>"%SALIDA%" echo  Fecha:    %date% %time%
>>"%SALIDA%" echo ============================================================
>>"%SALIDA%" echo.

REM ============================================================
REM  1. DEPENDENCIAS DECLARADAS EN package.json
REM ============================================================
>>"%SALIDA%" echo ------------------------------------------------------------
>>"%SALIDA%" echo  1. DEPENDENCIAS DECLARADAS (package.json)
>>"%SALIDA%" echo ------------------------------------------------------------
>>"%SALIDA%" echo.

if not exist "%PKG%" (
    >>"%SALIDA%" echo   [AVISO] No se encontro package.json en %PROYECTO%
    goto :seccion2
)

where node >nul 2>nul
if errorlevel 1 (
    >>"%SALIDA%" echo   [AVISO] Node no esta en PATH. Usando parseo simple.
    >>"%SALIDA%" echo.
    call :parse_simple
    goto :seccion2
)

REM Parseo con Node (robusto)
node -e "const fs=require('fs');const p=JSON.parse(fs.readFileSync(process.argv[1],'utf8'));const f=(t,obj)=>{const k=Object.keys(obj||{});console.log('');console.log('['+t+'] ('+k.length+')');k.sort().forEach(n=>console.log('  '+n+'@'+obj[n]));};f('dependencies',p.dependencies);f('devDependencies',p.devDependencies);f('optionalDependencies',p.optionalDependencies);f('peerDependencies',p.peerDependencies);" "%PKG%" >>"%SALIDA%" 2>&1

goto :seccion2

:parse_simple
REM Fallback: extrae lineas tipo "nombre": "version" del package.json
for /f "usebackq tokens=1,2 delims=:" %%A in (`findstr /R /C:"^ *\".*\": *\"" "%PKG%"`) do (
    set "linea=%%A"
    set "linea=!linea: =!"
    set "linea=!linea:"=!"
    if not "!linea!"=="name" if not "!linea!"=="version" if not "!linea!"=="description" if not "!linea!"=="main" if not "!linea!"=="license" if not "!linea!"=="author" (
        >>"%SALIDA%" echo   !linea!
    )
)
exit /b 0

:seccion2
>>"%SALIDA%" echo.
>>"%SALIDA%" echo.

REM ============================================================
REM  2. PAQUETES INSTALADOS EN node_modules (nivel raiz)
REM ============================================================
>>"%SALIDA%" echo ------------------------------------------------------------
>>"%SALIDA%" echo  2. PAQUETES INSTALADOS EN node_modules (nivel raiz)
>>"%SALIDA%" echo ------------------------------------------------------------
>>"%SALIDA%" echo.

set /a total=0
for /d %%D in ("%NM_DIR%\*") do (
    set "nombre=%%~nxD"
    if not "!nombre:~0,1!"=="." (
        if /i not "!nombre!"=="_bin" (
            >>"%SALIDA%" echo   !nombre!
            set /a total+=1
        )
    )
)

>>"%SALIDA%" echo.
>>"%SALIDA%" echo   TOTAL paquetes nivel raiz: !total!
>>"%SALIDA%" echo.
>>"%SALIDA%" echo.

REM ============================================================
REM  3. PAQUETES CON SCOPE (@org/paquete)
REM ============================================================
>>"%SALIDA%" echo ------------------------------------------------------------
>>"%SALIDA%" echo  3. PAQUETES CON SCOPE (organizaciones @org)
>>"%SALIDA%" echo ------------------------------------------------------------
>>"%SALIDA%" echo.

set /a scoped=0
for /d %%S in ("%NM_DIR%\@*") do (
    >>"%SALIDA%" echo [%%~nxS]
    for /d %%P in ("%%S\*") do (
        >>"%SALIDA%" echo     %%~nxP
        set /a scoped+=1
    )
    >>"%SALIDA%" echo.
)

>>"%SALIDA%" echo   TOTAL paquetes con scope: !scoped!
>>"%SALIDA%" echo.
>>"%SALIDA%" echo.

REM ============================================================
REM  4. CATEGORIZACION POR PALABRA CLAVE
REM ============================================================
>>"%SALIDA%" echo ------------------------------------------------------------
>>"%SALIDA%" echo  4. CATEGORIZACION POR PALABRA CLAVE
>>"%SALIDA%" echo ------------------------------------------------------------
>>"%SALIDA%" echo.

call :categoria "Testing"             "jest,mocha,chai,jasmine,vitest,karma,cypress,playwright,enzyme,supertest,sinon,ava,tap"
call :categoria "Linting / Formato"   "eslint,prettier,tslint,stylelint,husky,lint-staged,commitlint,standard,biome"
call :categoria "Bundlers / Build"    "webpack,rollup,vite,esbuild,parcel,snowpack,turbopack,gulp,grunt,browserify,babel"
call :categoria "Frameworks Frontend" "react,vue,angular,svelte,next,nuxt,astro,remix,solid,qwik,ember,preact,alpine"
call :categoria "Frameworks Backend"  "express,koa,fastify,nest,hapi,restify,meteor,adonis"
call :categoria "TypeScript"          "typescript,ts-node,tsx"
call :categoria "CSS / Estilos"       "tailwind,postcss,sass,less,stylus,bootstrap,foundation,bulma,styled,emotion"
call :categoria "Utilidades"          "lodash,underscore,ramda,rxjs,axios,node-fetch,got,superagent,dayjs,moment,date-fns,uuid,nanoid"
call :categoria "CLI / Herramientas"  "commander,yargs,minimist,chalk,ora,inquirer,boxen,cli-progress,prompts"
call :categoria "Base de Datos / ORM" "sequelize,typeorm,prisma,mongoose,knex,bookshelf,objection,drizzle"
call :categoria "Testing E2E"         "puppeteer,selenium,nightwatch,webdriverio"
call :categoria "Seguridad"           "helmet,bcrypt,jsonwebtoken,jose,passport,crypto-js,csurf,express-rate-limit"
call :categoria "Documentacion"       "jsdoc,typedoc,storybook,docusaurus,vitepress,swagger,redoc"
call :categoria "Monorepo"            "lerna,nx,rush,turbo,changesets"

>>"%SALIDA%" echo.

REM ============================================================
REM  5. RESUMEN
REM ============================================================
>>"%SALIDA%" echo ------------------------------------------------------------
>>"%SALIDA%" echo  5. RESUMEN
>>"%SALIDA%" echo ------------------------------------------------------------
>>"%SALIDA%" echo.

set /a carpetas=0
set /a archivos=0
for /d %%D in ("%NM_DIR%\*") do set /a carpetas+=1
for %%F in ("%NM_DIR%\*") do set /a archivos+=1

>>"%SALIDA%" echo   Carpetas (paquetes): !carpetas!
>>"%SALIDA%" echo   Archivos sueltos:    !archivos!
>>"%SALIDA%" echo.

>>"%SALIDA%" echo ============================================================
>>"%SALIDA%" echo  FIN DEL ANALISIS
>>"%SALIDA%" echo ============================================================

echo.
echo Listo. Archivo generado en:
echo   %SALIDA%
echo.
pause
exit /b 0

REM ============================================================
REM  SUBRUTINA: categoria
REM ============================================================
:categoria
set "etiqueta=%~1"
set "patrones=%~2"
set /a num=0

REM Primera pasada: contar
for /d %%D in ("%NM_DIR%\*") do (
    set "n=%%~nxD"
    if not "!n:~0,1!"=="@" (
        echo !patrones! | findstr /I /C:"!n!" >nul
        if !errorlevel! equ 0 set /a num+=1
    )
)

if !num! equ 0 exit /b 0

>>"%SALIDA%" echo [!etiqueta!] (!num!)
for /d %%D in ("%NM_DIR%\*") do (
    set "n=%%~nxD"
    if not "!n:~0,1!"=="@" (
        echo !patrones! | findstr /I /C:"!n!" >nul
        if !errorlevel! equ 0 >>"%SALIDA%" echo     !n!
    )
)
>>"%SALIDA%" echo.
exit /b 0