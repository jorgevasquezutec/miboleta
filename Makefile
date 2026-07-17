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

.DEFAULT_GOAL := help
.PHONY: help publish signer-build stack deploy nginx

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
