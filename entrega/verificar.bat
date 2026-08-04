@echo off
REM ---------------------------------------------------------------------------
REM MiBoleta - comprobar que la copia entregada funciona (Windows)
REM ---------------------------------------------------------------------------
REM Deja constancia escrita con fecha en la carpeta evidencia\.
REM Ese registro se adjunta al acta de entrega.
REM ---------------------------------------------------------------------------
setlocal enabledelayedexpansion
cd /d "%~dp0"

if "%MIBOLETA_HTTP_PORT%"=="" set MIBOLETA_HTTP_PORT=9090
set BASE=http://localhost:%MIBOLETA_HTTP_PORT%

if not exist evidencia mkdir evidencia
for /f "tokens=2 delims==" %%I in ('wmic os get localdatetime /value') do set DT=%%I
set SALIDA=evidencia\ejecucion-%DT:~0,8%-%DT:~8,6%.log

set TOTAL=0
set OK=0

echo ===========================================================  > "%SALIDA%"
echo  MiBoleta - verificacion de la copia entregada              >> "%SALIDA%"
echo ===========================================================  >> "%SALIDA%"
echo  Fecha ....... %DATE% %TIME%                                 >> "%SALIDA%"
echo  Equipo ...... %COMPUTERNAME%                                >> "%SALIDA%"
if exist VERSION.txt type VERSION.txt                             >> "%SALIDA%"
echo ===========================================================  >> "%SALIDA%"
echo.                                                             >> "%SALIDA%"
echo Comprobaciones:                                              >> "%SALIDA%"

call :comprobar "La aplicacion responde" "%BASE%/api/health/check"
call :comprobar "La aplicacion esta lista" "%BASE%/api/health/ready"
call :comprobar "El frontend carga" "%BASE%/"

set /a TOTAL+=1
curl -fsS -X POST "%BASE%/api/login" -H "Content-Type: application/json" -H "Accept: application/json" -d "{\"login\":\"aprobador@miboleta.demo\",\"password\":\"password\"}" >nul 2>&1
if errorlevel 1 (
  echo   [FALLA] Un usuario puede iniciar sesion >> "%SALIDA%"
) else (
  set /a OK+=1
  echo   [OK]    Un usuario puede iniciar sesion >> "%SALIDA%"
)

echo. >> "%SALIDA%"
echo ----------------------------------------------------------- >> "%SALIDA%"
echo  Resultado: %OK%/%TOTAL% comprobaciones correctas            >> "%SALIDA%"
echo ----------------------------------------------------------- >> "%SALIDA%"

type "%SALIDA%"
echo.
if %OK%==%TOTAL% (
  echo La aplicacion entregada funciona correctamente.
  echo Constancia guardada en: %SALIDA%
  echo Adjunte este archivo al acta de entrega.
) else (
  echo Hay comprobaciones que fallaron. Revise %SALIDA%
  echo Si la aplicacion acaba de arrancar, espere un minuto y reintente.
)
echo.
pause
exit /b 0

:comprobar
set /a TOTAL+=1
curl -fsS %~2 >nul 2>&1
if errorlevel 1 (
  echo   [FALLA] %~1 >> "%SALIDA%"
) else (
  set /a OK+=1
  echo   [OK]    %~1 >> "%SALIDA%"
)
exit /b 0
