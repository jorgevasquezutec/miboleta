@echo off
REM MiBoleta - detener la copia entregada (Windows)
REM   detener.bat           conserva los datos
REM   detener.bat --borrar  vuelve a los datos de fabrica en el proximo arranque
setlocal
cd /d "%~dp0"

if "%1"=="--borrar" (
  echo Deteniendo y borrando los datos...
  docker compose -f docker-compose.entrega.yml -p miboleta_entrega down -v
  echo Listo. El proximo arranque volvera a crear los datos de demostracion.
) else (
  echo Deteniendo los servicios ^(los datos se conservan^)...
  docker compose -f docker-compose.entrega.yml -p miboleta_entrega down
  echo Listo. Vuelva a arrancar con levantar.bat
)
pause
