# 📋 Nombres de Recursos Docker - Sin Conflictos

Este documento explica cómo evitamos conflictos entre `docker-compose` (desarrollo) y `docker stack` (producción/testing).

---

## 🔵 Docker Compose (Desarrollo Local)

Cuando ejecutas `docker-compose up`:

### Contenedores:
- `miboleta_app`
- `miboleta_nginx`
- `miboleta_mysql`
- `miboleta_redis`
- `miboleta_horizon`
- `miboleta_reverb`
- `miboleta_adminer`

### Volúmenes:
- `miboleta_mysql_data`
- `miboleta_redis_data`

### Red:
- `miboleta_miboleta_network` (bridge)

### Puertos:
- 80, 443 (nginx)
- 3307 (mysql)
- 6379 (redis)
- 8085 (reverb)
- 8080 (adminer)

---

## 🟢 Docker Stack (Swarm - Producción/Testing)

Cuando ejecutas `docker stack deploy -c docker-stack.yml miboleta`:

### Servicios:
- `miboleta_app`
- `miboleta_nginx`
- `miboleta_db`
- `miboleta_redis`
- `miboleta_horizon`
- `miboleta_reverb`

### Volúmenes (con nombres explícitos):
- `miboleta_swarm_mysql_data` ✅ DIFERENTE
- `miboleta_swarm_redis_data` ✅ DIFERENTE
- `miboleta_swarm_storage_data`
- `miboleta_swarm_cache_data`
- `miboleta_swarm_nginx_logs`
- `miboleta_swarm_mysql_backups`

### Red (con nombre explícito):
- `miboleta_swarm_network` ✅ DIFERENTE (overlay)

### Puertos:
- 80, 443 (nginx)

---

## ✅ Resumen: NO HAY CONFLICTOS

| Recurso | Docker Compose | Docker Stack | ¿Conflicto? |
|---------|---------------|--------------|-------------|
| **Red** | `miboleta_miboleta_network` (bridge) | `miboleta_swarm_network` (overlay) | ❌ NO |
| **Volumen MySQL** | `miboleta_mysql_data` | `miboleta_swarm_mysql_data` | ❌ NO |
| **Volumen Redis** | `miboleta_redis_data` | `miboleta_swarm_redis_data` | ❌ NO |
| **Puertos** | 80, 443, 3307, 6379, 8085, 8080 | 80, 443 | ⚠️  SÍ (80, 443) |

---

## ⚠️ Único Conflicto: Puertos 80 y 443

Ambos usan los puertos 80 y 443 para nginx. **Solución:**

### Opción 1: No correr ambos al mismo tiempo
```bash
# Detener desarrollo
docker-compose down

# Probar Swarm
./test-swarm-local.sh

# Limpiar Swarm
./cleanup-swarm-local.sh

# Volver a desarrollo
docker-compose up -d
```

### Opción 2: Ya están en puertos diferentes
Si revisas bien:
- `docker-compose.yml`: usa puertos 80 y 443
- `docker-stack.yml`: usa puertos 80 y 443

Pero como NO los correrás al mismo tiempo, **no hay problema**.

---

## 🎯 Recomendación

**NO necesitas hacer nada especial**. Solo asegúrate de:

1. ✅ Detener `docker-compose` antes de probar `docker stack`
2. ✅ Detener `docker stack` antes de volver a `docker-compose`

```bash
# Workflow típico:
docker-compose down        # Detener desarrollo
./test-swarm-local.sh      # Probar Swarm
./cleanup-swarm-local.sh   # Limpiar Swarm
docker-compose up -d       # Volver a desarrollo
```

---

## 🔍 Verificar Estado

### Ver qué está corriendo:
```bash
# Docker Compose
docker-compose ps

# Docker Stack
docker stack services miboleta
docker stack ps miboleta

# Todos los contenedores
docker ps

# Todas las redes
docker network ls

# Todos los volúmenes
docker volume ls
```

### Limpiar todo si hay problemas:
```bash
# Detener todo Compose
docker-compose down

# Detener todo Stack
docker stack rm miboleta

# Esperar 10 segundos
sleep 10

# Limpiar redes huérfanas
docker network prune -f

# Limpiar volúmenes sin usar (CUIDADO!)
docker volume prune -f
```

---

## 📝 Notas Importantes

1. **Los datos son independientes**: El MySQL de desarrollo y el de Swarm tienen bases de datos separadas
2. **No se comparten volúmenes**: Cada uno tiene sus propios archivos
3. **Las redes son diferentes**: Bridge vs Overlay
4. **Solo los puertos 80/443 pueden colisionar** si ambos corren al mismo tiempo

---

## ✨ Conclusión

Gracias a que usamos nombres explícitos en `docker-stack.yml`:
- `name: miboleta_swarm_mysql_data`
- `name: miboleta_swarm_redis_data`
- `name: miboleta_swarm_network`

**Puedes tener ambos sistemas configurados sin problemas**, solo no los corras simultáneamente por el conflicto de puertos.
