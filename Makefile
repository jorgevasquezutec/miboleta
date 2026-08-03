# ---------------------------------------------------------------------------
# MiBoleta — despliegue MANUAL a producción (Docker Swarm) — plan B del CI
# ---------------------------------------------------------------------------
# El flujo normal es automático: merge a main -> workflow "Build & Deploy"
# (Tests -> build imágenes -> VPN/SSH -> stack deploy). Este Makefile es el
# fallback para correrlo a mano desde tu Mac cuando el CI no despliega
# (típicamente: build OK pero el paso de deploy falló por VPN/SSH flaky).
#
# Uso rápido (un solo comando, hace TODO lo que se puede a mano):
#     make publish
#
# Por partes:
#     make signer-build   # construye la imagen amd64 del signer y la sube a GHCR
#     make stack          # copia docker-stack.yml al server
#     make deploy         # server: pull imágenes + stack deploy + migrate + cache
#
# QUÉ CUBRE Y QUÉ NO (importante):
#   ✅ Despliega la imagen del APP que el CI YA subió a GHCR (última build) +
#      la del signer (que se construye aquí) + migraciones + caches.
#   ❌ NO reconstruye el app image (backend+frontend van en la MISMA imagen).
#      El frontend necesita secrets de Vite (VITE_REVERB_*) que no están en
#      local, así que ese build se deja al CI. Si necesitas publicar código
#      nuevo de backend/frontend y el build del CI también está caído, hay que
#      construir el app image aparte (no cubierto aquí).
#
# Gotchas ya resueltos por el Makefile:
#   - Mac arm64 vs server amd64  -> la imagen del signer se construye con
#     --platform linux/amd64, o no corre en el server.
#   - .env.stack se carga con `set -a; . ./.env.stack` (no xargs), o
#     REDIS_PASSWORD queda vacío y tumba redis/horizon/reverb.
#   - El server necesita el docker-stack.yml nuevo -> `stack` lo copia primero.
#
# Nota: `ssh $(SSH_HOST)` corre desde TU Mac; la VPN la pones tú.
# ---------------------------------------------------------------------------

# Variables (override en línea: `make publish SSH_HOST=otro REMOTE_DIR=/ruta`)
APP_IMAGE    ?= ghcr.io/jorgevasquezutec/miboleta:latest
SIGNER_IMAGE ?= ghcr.io/jorgevasquezutec/miboleta-signer:latest
PLATFORM     ?= linux/amd64
SSH_HOST     ?= miboleta
REMOTE_DIR   ?= /opt/miboleta
STACK        ?= miboleta

# Copia local de producción (targets *-pull)
COMPOSE      ?= docker-compose.yml
LOCAL_DB     ?= miboleta
LOCAL_DB_PWD ?= root
DUMP_DIR     ?= /tmp
# Clave que db-pull deja a TODOS los usuarios de la copia local (los hashes de
# producción no se pueden revertir, así que sin esto no podrías entrar a nada).
LOCAL_PWD    ?= password
# Nombre literal del volumen en el server (docker-stack.yml lo fija con `name:`,
# así que NO se deriva de $(STACK) aunque hoy coincidan).
STORAGE_VOL  ?= miboleta_swarm_storage_data

# MYSQL_PWD por entorno y no `-p<clave>`: evita el warning "Using a password on
# the command line interface can be insecure" en cada una de las ~10 llamadas.
MYSQL = docker compose -f $(COMPOSE) exec -T -e MYSQL_PWD=$(LOCAL_DB_PWD) db mysql -uroot

.DEFAULT_GOAL := help
.PHONY: help publish signer-build stack deploy nginx db-pull storage-pull prod-pull db-sanitize db-archives db-restore db-passwords

help: ## Muestra esta ayuda
	@grep -E '^[a-zA-Z_-]+:.*?## .*$$' $(MAKEFILE_LIST) \
	  | awk 'BEGIN{FS=":.*?## "}{printf "  \033[36m%-14s\033[0m %s\n", $$1, $$2}'

publish: signer-build stack deploy ## TODO: build+push signer, copia stack y despliega en el server
	@echo "✅ Publicado. Verifica arriba que todos los servicios salgan N/N (esp. miboleta_signer)."

signer-build: ## Construye la imagen amd64 del signer y la sube a GHCR
	@echo "==> build+push $(SIGNER_IMAGE) ($(PLATFORM))"
	docker buildx build --platform $(PLATFORM) -t $(SIGNER_IMAGE) --push ./signer

stack: ## Copia el docker-stack.yml actualizado al server
	@echo "==> scp docker-stack.yml -> $(SSH_HOST):$(REMOTE_DIR)/"
	scp docker-stack.yml $(SSH_HOST):$(REMOTE_DIR)/

nginx: ## Copia config/nginx.conf al server, valida y recarga nginx (sin rebuild)
	@echo "==> scp config/nginx.conf -> $(SSH_HOST):$(REMOTE_DIR)/config/"
	scp config/nginx.conf $(SSH_HOST):$(REMOTE_DIR)/config/nginx.conf
	@echo "==> validar y recargar nginx en $(SSH_HOST)"
	ssh $(SSH_HOST) 'NGX=$$(docker ps -qf name=$(STACK)_nginx | head -1); \
	  if [ -z "$$NGX" ]; then echo "❌ contenedor nginx no encontrado"; exit 1; fi; \
	  if docker exec $$NGX nginx -t && docker exec $$NGX nginx -s reload; then \
	    echo "✅ nginx recargado en caliente"; \
	  else \
	    echo "⚠ reload no tomó (inode del bind-mount); forzando la task..."; \
	    docker service update --force $(STACK)_nginx && echo "✅ nginx redeployado"; \
	  fi'

deploy: ## Server: pull imágenes + stack deploy + migrate + caches + estado
	@echo "==> deploy en $(SSH_HOST) ($(REMOTE_DIR))"
	ssh $(SSH_HOST) 'cd $(REMOTE_DIR); \
	  echo "== pull imágenes =="; \
	  docker pull $(APP_IMAGE); \
	  docker pull $(SIGNER_IMAGE); \
	  echo "== cargar .env.stack =="; \
	  set -a; . ./.env.stack; set +a; \
	  echo "== stack deploy =="; \
	  docker stack deploy -c docker-stack.yml $(STACK) --with-registry-auth; \
	  echo "== esperar task del app =="; \
	  APP=""; for i in $$(seq 1 24); do \
	    APP=$$(docker ps -qf name=$(STACK)_app | head -1); \
	    [ -n "$$APP" ] && break; echo "  esperando app... ($$i/24)"; sleep 5; \
	  done; \
	  if [ -z "$$APP" ]; then echo "❌ contenedor del app no encontrado"; exit 1; fi; \
	  echo "  app=$$APP"; \
	  echo "== esperar MySQL =="; \
	  for i in $$(seq 1 18); do \
	    docker exec $$APP php artisan tinker --execute="DB::connection()->getPdo();" >/dev/null 2>&1 && { echo "  MySQL listo"; break; }; \
	    echo "  esperando MySQL... ($$i/18)"; sleep 5; \
	  done; \
	  echo "== migraciones =="; \
	  docker exec $$APP php artisan migrate --force; \
	  echo "== caches =="; \
	  docker exec $$APP php artisan config:cache; \
	  docker exec $$APP php artisan route:cache; \
	  docker exec $$APP php artisan view:cache; \
	  echo "== estado final =="; \
	  docker stack services $(STACK)'

# ---------------------------------------------------------------------------
# Copia de producción -> local
# ---------------------------------------------------------------------------
# La VPN la pones tú (igual que en `deploy`): estos targets solo corren
# `ssh $(SSH_HOST)` desde tu Mac.
#
# El dump SALE con datos personales reales (DNIs, boletas, correos de
# empleados). `db-pull` borra el .sql.gz local al terminar; usa KEEP_DUMP=1 si
# necesitas conservarlo, y bórralo tú cuando acabes.
#
# db-sanitize NO es opcional y por eso va dentro de db-pull: `tenants` y
# `platform_settings` guardan credenciales SMTP en la BASE DE DATOS, y
# Tenant::hasCustomMailer() decide el mailer leyendo esas filas — no tu .env.
# Sin sanear, un job de correo en tu local saldría por el SMTP real hacia los
# correos reales de los empleados. De paso anula los tres campos con cast
# `encrypted` (cifrados con el APP_KEY de producción), que si no revientan con
# DecryptException al leerlos en local.
# ---------------------------------------------------------------------------

db-pull: ## Archiva tu BD local (renombrándola) e importa la de producción
	@echo "⚠  Tu BD local '$(LOCAL_DB)' se ARCHIVA con otro nombre (no se borra) y en su"
	@echo "   lugar queda la de PRODUCCIÓN. Reversible con 'make db-restore'."
	@if [ "$(FORCE)" != "1" ]; then \
	  printf "   Escribe 'si' para continuar: "; read ans; \
	  [ "$$ans" = "si" ] || { echo "Cancelado."; exit 1; }; \
	fi
	@echo "==> dump en $(SSH_HOST)"
	ssh $(SSH_HOST) 'CID=$$(docker ps -qf name=$(STACK)_db | head -1); \
	  if [ -z "$$CID" ]; then echo "❌ contenedor de MySQL no encontrado"; exit 1; fi; \
	  echo "  db=$$CID"; \
	  docker exec $$CID bash -c "MYSQL_PWD=\"\$$MYSQL_ROOT_PASSWORD\" mysqldump -u root \
	    --single-transaction --quick --routines --no-tablespaces \
	    \"\$$MYSQL_DATABASE\" | gzip > /backups/miboleta.sql.gz"; \
	  docker cp $$CID:/backups/miboleta.sql.gz /tmp/miboleta.sql.gz; \
	  docker exec $$CID rm -f /backups/miboleta.sql.gz'
	@echo "==> descargando a $(DUMP_DIR)/miboleta.sql.gz"
	scp $(SSH_HOST):/tmp/miboleta.sql.gz $(DUMP_DIR)/miboleta.sql.gz
	ssh $(SSH_HOST) 'rm -f /tmp/miboleta.sql.gz'
	@echo "==> archivando la BD local (se renombra, no se borra)"
	@set -e; \
	  ARCHIVE="$(LOCAL_DB)_local_$$(date +%Y%m%d_%H%M%S)"; \
	  if [ "$$($(MYSQL) -N -B -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$(LOCAL_DB)'")" = "1" ]; then \
	    EXTRA=$$($(MYSQL) -N -B -e "SELECT (SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$(LOCAL_DB)' AND table_type='VIEW') + (SELECT COUNT(*) FROM information_schema.routines WHERE routine_schema='$(LOCAL_DB)') + (SELECT COUNT(*) FROM information_schema.triggers WHERE trigger_schema='$(LOCAL_DB)')"); \
	    if [ "$$EXTRA" != "0" ]; then \
	      echo "⚠  la BD local tiene $$EXTRA vistas/rutinas/triggers: RENAME TABLE NO los mueve y se perderán."; \
	      echo "   Aborto. Sácalos con mysqldump antes de continuar."; exit 1; \
	    fi; \
	    $(MYSQL) -e "CREATE DATABASE $$ARCHIVE CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"; \
	    $(MYSQL) -N -B -e "SELECT CONCAT('RENAME TABLE \`$(LOCAL_DB)\`.\`', table_name, '\` TO \`$$ARCHIVE\`.\`', table_name, '\`;') FROM information_schema.tables WHERE table_schema='$(LOCAL_DB)' AND table_type='BASE TABLE'" | $(MYSQL); \
	    $(MYSQL) -e "DROP DATABASE $(LOCAL_DB)"; \
	    echo "  archivada como '$$ARCHIVE' (RENAME TABLE: instantáneo, no copia datos)"; \
	  else \
	    echo "  no existía '$(LOCAL_DB)': nada que archivar"; \
	  fi; \
	  $(MYSQL) -e "CREATE DATABASE $(LOCAL_DB) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
	@echo "==> importando (puede tardar)"
	gunzip -c $(DUMP_DIR)/miboleta.sql.gz | $(MYSQL) $(LOCAL_DB)
	@$(MAKE) --no-print-directory db-sanitize
	@if [ "$(KEEP_PASSWORDS)" = "1" ]; then \
	  echo "==> contraseñas intactas (KEEP_PASSWORDS=1): no vas a poder loguearte"; \
	else \
	  $(MAKE) --no-print-directory db-passwords; \
	fi
	@echo "==> migraciones pendientes de tu rama + limpiar caches"
	docker compose -f $(COMPOSE) exec -T app php artisan migrate --force
	docker compose -f $(COMPOSE) exec -T app php artisan optimize:clear
	@if [ "$(KEEP_DUMP)" != "1" ]; then \
	  rm -f $(DUMP_DIR)/miboleta.sql.gz; \
	  echo "==> dump local borrado (KEEP_DUMP=1 para conservarlo)"; \
	else \
	  echo "⚠  dump conservado en $(DUMP_DIR)/miboleta.sql.gz — contiene datos personales reales"; \
	fi
	@echo "✅ BD de producción corriendo en local. Tu BD anterior sigue ahí:"
	@$(MAKE) --no-print-directory db-archives

db-sanitize: ## Anula SMTP y secretos cifrados de la BD local (ya lo hace db-pull)
	@echo "==> saneando SMTP y campos cifrados"
	@$(MYSQL) $(LOCAL_DB) -e "UPDATE tenants SET mail_host=NULL, mail_password=NULL;" \
	  || { echo "❌ no se pudo sanear 'tenants': la BD local podría enviar correos REALES"; exit 1; }
	@$(MYSQL) $(LOCAL_DB) -e "UPDATE platform_settings SET mail_host=NULL, mail_password=NULL;" \
	  || echo "⚠  'platform_settings' no saneada (¿tabla ausente?) — revísala antes de levantar horizon"
	@$(MYSQL) $(LOCAL_DB) -e "UPDATE signature_settings SET certificate_password=NULL;" \
	  || echo "⚠  'signature_settings' no saneada (¿tabla ausente?)"
	@echo "  SMTP por empresa y de plataforma anulados: el mailer cae al .env local."

db-passwords: ## Pone la clave '$(LOCAL_PWD)' a TODOS los usuarios locales (ya lo hace db-pull)
	@echo "==> poniendo la clave '$(LOCAL_PWD)' a todos los usuarios"
	@docker compose -f $(COMPOSE) exec -T app php artisan tinker --execute="\
	  \$$n = \App\Models\User::query()->update([ \
	    'password' => \Illuminate\Support\Facades\Hash::make('$(LOCAL_PWD)'), \
	    'must_change_password' => false, \
	  ]); \
	  echo \"  \$$n usuarios actualizados\n\";"
	@echo "  Nota: los usuarios con status != 'active' siguen sin poder entrar."

db-archives: ## Lista las BD locales archivadas por db-pull
	@$(MYSQL) -N -B -e "SELECT CONCAT('  ', schema_name) FROM information_schema.schemata \
	  WHERE schema_name LIKE '$(LOCAL_DB)\_local\_%' ORDER BY schema_name DESC" \
	  | grep . || echo "  (ninguna)"
	@echo "  Restaurar:  make db-restore ARCHIVE=<nombre>"

db-restore: ## Vuelve a una BD archivada (ARCHIVE=...). Descarta la local actual
	@if [ -z "$(ARCHIVE)" ]; then \
	  echo "❌ Falta ARCHIVE. Mira las disponibles con 'make db-archives'."; exit 1; \
	fi
	@if [ "$$($(MYSQL) -N -B -e "SELECT COUNT(*) FROM information_schema.schemata WHERE schema_name='$(ARCHIVE)'")" != "1" ]; then \
	  echo "❌ '$(ARCHIVE)' no existe. Mira 'make db-archives'."; exit 1; \
	fi
	@echo "⚠  Se DESCARTA la BD local '$(LOCAL_DB)' actual (la copia de producción) y"
	@echo "   '$(ARCHIVE)' vuelve a ocupar su lugar."
	@if [ "$(FORCE)" != "1" ]; then \
	  printf "   Escribe 'si' para continuar: "; read ans; \
	  [ "$$ans" = "si" ] || { echo "Cancelado."; exit 1; }; \
	fi
	@set -e; \
	  $(MYSQL) -e "DROP DATABASE IF EXISTS $(LOCAL_DB)"; \
	  $(MYSQL) -e "CREATE DATABASE $(LOCAL_DB) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"; \
	  $(MYSQL) -N -B -e "SELECT CONCAT('RENAME TABLE \`$(ARCHIVE)\`.\`', table_name, '\` TO \`$(LOCAL_DB)\`.\`', table_name, '\`;') FROM information_schema.tables WHERE table_schema='$(ARCHIVE)' AND table_type='BASE TABLE'" | $(MYSQL); \
	  $(MYSQL) -e "DROP DATABASE $(ARCHIVE)"
	@echo "✅ '$(ARCHIVE)' restaurada como '$(LOCAL_DB)'."

# El chown del tar NO es cosmético: el contenedor corre como root, así que el
# .tgz queda root en /tmp del server y, con el sticky bit de /tmp, tu usuario
# SSH no puede borrarlo después ("Operation not permitted").
storage-pull: ## Copia storage/app de producción (boletas y PDFs firmados) a tu local
	@echo "==> empaquetando storage/app en $(SSH_HOST)"
	ssh $(SSH_HOST) 'docker run --rm \
	  -v $(STORAGE_VOL):/data:ro -v /tmp:/out alpine \
	  sh -c "tar czf /out/miboleta_storage.tgz -C /data app && \
	         chown $$(id -u):$$(id -g) /out/miboleta_storage.tgz"'
	@echo "==> descargando"
	scp $(SSH_HOST):/tmp/miboleta_storage.tgz $(DUMP_DIR)/miboleta_storage.tgz
	ssh $(SSH_HOST) 'rm -f /tmp/miboleta_storage.tgz'
	@echo "==> extrayendo en el contenedor del app"
	docker compose -f $(COMPOSE) cp $(DUMP_DIR)/miboleta_storage.tgz app:/tmp/miboleta_storage.tgz
	docker compose -f $(COMPOSE) exec -T app tar xzf /tmp/miboleta_storage.tgz -C /var/www/html/storage
	docker compose -f $(COMPOSE) exec -T app rm -f /tmp/miboleta_storage.tgz
	docker compose -f $(COMPOSE) exec -T app chown -R www-data:www-data /var/www/html/storage
	@if [ "$(KEEP_DUMP)" != "1" ]; then rm -f $(DUMP_DIR)/miboleta_storage.tgz; fi
	@echo "✅ storage/app de producción copiado."

prod-pull: db-pull storage-pull ## BD + archivos de producción en tu local, de una
	@echo "✅ Copia completa. Recuerda: NO levantes horizon si vas a tocar datos reales."
