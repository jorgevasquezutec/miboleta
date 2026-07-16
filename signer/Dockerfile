# ---------------------------------------------------------------------------
# MiBoleta - Sidecar de firma digital legal
# ---------------------------------------------------------------------------
# Por qué un contenedor aparte y por qué Debian (no Alpine):
#   La app Laravel corre en php:8.4-fpm-alpine (musl). pyHanko depende de
#   `cryptography`, que en PyPI solo publica wheels manylinux (glibc); en
#   musl obligaría a compilar `cryptography` + Rust en cada build, lo cual es
#   lento y fragil. Por eso este sidecar usa python:3.12-slim (Debian
#   bookworm, glibc) y consume wheels precompiladas.
#
# Este contenedor sirve tráfico HTTP (signer/app.py, FastAPI + uvicorn) SOLO
# dentro de miboleta_network (ver docker-compose.yml: `expose`, no `ports`).
# También se puede seguir usando signer/spike_sign.py de forma puntual para
# diagnóstico manual con `docker compose run --rm signer python spike_sign.py ...`
# (ver README.md).
# ---------------------------------------------------------------------------
FROM python:3.12-slim

LABEL maintainer="MiBoleta" \
      description="Sidecar de firma digital PAdES: Ghostscript (PDF/A-2b) + pyHanko, servido via FastAPI/uvicorn"

ENV PYTHONUNBUFFERED=1 \
    PYTHONDONTWRITEBYTECODE=1 \
    DEBIAN_FRONTEND=noninteractive

# ghostscript          -> normalización del PDF a PDF/A-2b.
# icc-profiles-free    -> perfil ICC sRGB.icc (/usr/share/color/icc/sRGB.icc).
#                         IMPORTANTE: el paquete "ghostscript" de Debian NO
#                         trae perfiles ICC propios (a diferencia de los
#                         instaladores oficiales de Artifex); sin este
#                         paquete no hay OutputIntent válido para PDF/A y la
#                         conformidad sería, en el mejor de los casos, parcial.
# ca-certificates      -> validar TLS al llamar a una TSA externa por HTTPS
#                         (p.ej. freetsa.org) para el sellado de tiempo opcional.
# libssl3              -> runtime de OpenSSL. `cryptography` trae su propio
#                         OpenSSL vendorizado en la wheel manylinux, pero se
#                         deja explícito por si alguna dependencia del SO lo
#                         requiere en tiempo de ejecución.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        ghostscript \
        icc-profiles-free \
        ca-certificates \
        libssl3 \
    && rm -rf /var/lib/apt/lists/*

# El script vive fuera de /var/www/html a propósito: ese path lo pisa el
# bind mount ./backend:/var/www/html en tiempo de ejecución (docker-compose),
# así que cualquier archivo copiado ahí en build time quedaría oculto.
WORKDIR /opt/signer

COPY requirements.txt .
RUN pip install --no-cache-dir --upgrade pip \
    && pip install --no-cache-dir -r requirements.txt

# pipeline.py concentra la lógica normalizar->firmar->verificar; la usan
# tanto app.py (API HTTP productiva) como spike_sign.py (CLI de diagnóstico).
COPY pipeline.py app.py spike_sign.py ./

# El working_dir en tiempo de ejecución se fija a /var/www/html desde
# docker-compose.yml (donde vive el bind mount de storage/app), para que las
# rutas relativas al PDF de la boleta sean cómodas al usar spike_sign.py a
# mano. `uvicorn --app-dir /opt/signer` (ver CMD/docker-compose) hace que
# `app.py`/`pipeline.py` sigan siendo importables aunque el cwd en runtime
# no sea /opt/signer.
WORKDIR /var/www/html

EXPOSE 8000

# Arranca la API HTTP interna (ver signer/app.py). docker-compose.yml fija
# explícitamente este mismo `command` (ver comentario ahí) y NO publica el
# puerto al host (`expose`, no `ports`): solo alcanzable desde
# miboleta_network. Para el uso puntual de spike_sign.py, sobreescribe el
# comando: `docker compose run --rm signer python spike_sign.py ...`.
CMD ["uvicorn", "app:app", "--app-dir", "/opt/signer", "--host", "0.0.0.0", "--port", "8000"]
