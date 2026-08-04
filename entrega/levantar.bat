@echo off
REM ---------------------------------------------------------------------------
REM MiBoleta - levantar la aplicacion entregada (Windows)
REM ---------------------------------------------------------------------------
REM Haga doble clic en este archivo con Docker Desktop ya abierto.
REM ---------------------------------------------------------------------------
setlocal
cd /d "%~dp0"

set PROYECTO=miboleta_entrega
set COMPOSE=docker-compose.entrega.yml
if "%MIBOLETA_HTTP_PORT%"=="" set MIBOLETA_HTTP_PORT=9090

echo.
echo == MiBoleta - arranque de la copia entregada ==
echo.

docker info >nul 2>&1
if errorlevel 1 (
  echo No se pudo contactar con Docker.
  echo Abra Docker Desktop, espere a que indique "Running" y vuelva a intentarlo.
  echo.
  pause
  exit /b 1
)
echo Docker disponible.

REM Las imagenes se cargan del disco: asi el paquete arranca sin internet y
REM queda congelada la version exacta que se entrego.
if exist "imagenes\*.tar" (
  echo Cargando imagenes desde el disco ^(puede tardar unos minutos^)...
  for %%f in (imagenes\*.tar) do (
    echo    %%~nxf
    docker load -i "%%f" >nul
  )
  echo Imagenes cargadas.
) else (
  echo No hay imagenes en .\imagenes: se descargaran del registro publico.
)

echo Levantando los servicios...
docker compose -f %COMPOSE% -p %PROYECTO% up -d
if errorlevel 1 (
  echo Fallo el arranque de los servicios.
  pause
  exit /b 1
)

echo Esperando a que la aplicacion este lista...
set LISTA=0
for /l %%i in (1,1,60) do (
  if !LISTA!==0 (
    curl -fsS "http://localhost:%MIBOLETA_HTTP_PORT%/api/health/check" >nul 2>&1
    if not errorlevel 1 set LISTA=1
    if !LISTA!==0 timeout /t 5 /nobreak >nul
  )
)

echo.
echo == La aplicacion esta funcionando ==
echo.
echo    Aplicacion .......... http://localhost:%MIBOLETA_HTTP_PORT%
echo    Correo ^(Mailpit^) .... http://localhost:9025
echo    Base de datos ....... http://localhost:9091
echo.
echo    Usuarios de prueba ^(contrasena: password^)
echo      Super Administrador ... admin@email.com
echo      Admin Clientes ........ admin.clientes@miboleta.demo
echo      Admin Empleados ....... admin@corporacionabc.com
echo      Aprobador ............. aprobador@miboleta.demo
echo      Empleado .............. juan.perez@corporacionabc.com
echo.
echo    Los datos son ficticios, creados para esta demostracion.
echo    Los correos no salen a internet: se ven en Mailpit.
echo.
start "" "http://localhost:%MIBOLETA_HTTP_PORT%"
pause
