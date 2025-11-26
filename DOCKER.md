# 🐳 Guía Docker - Crear y Publicar Imagen

## ⚡ Imagen Todo-en-Uno

**IMPORTANTE:** Esta imagen Docker incluye **todo lo necesario** en un solo contenedor:
- ✅ Apache2 + PHP
- ✅ MySQL/MariaDB Server
- ✅ Node.js para dependencias
- ✅ CRON para monitoreo automático
- ✅ Supervisor para manejar múltiples servicios

**No necesitas un contenedor separado para MySQL.** Todo funciona en una sola imagen.

---

## 📋 Tabla de Contenidos

1. [Crear Imagen Docker (Primera Vez)](#crear-imagen)
2. [Publicar en Docker Hub](#publicar-docker-hub)
3. [Actualizar Imagen (Nuevas Versiones)](#actualizar-imagen)
4. [Usar la Imagen Publicada](#usar-imagen)
5. [Comandos Útiles](#comandos-útiles)

---

<a name="crear-imagen"></a>
## 🆕 1. Crear Imagen Docker (Primera Vez)

### Requisitos Previos

- Docker instalado
- Cuenta en Docker Hub (https://hub.docker.com)
- Estar logueado en Docker Hub

### Paso 1: Login en Docker Hub

```bash
# Login en Docker Hub
docker login

# Te pedirá:
# Username: diaverso
# Password: (tu contraseña de Docker Hub)
```

### Paso 2: Construir la Imagen

```bash
# Navegar a la carpeta del proyecto
cd /var/www/html/price-monitor/Github

# Construir la imagen con tag de Docker Hub
docker build -f docker/Dockerfile -t diaverso/price-monitor:latest .

# También puedes crear una versión específica
docker build -f docker/Dockerfile -t diaverso/price-monitor:2.1 .
```

### Paso 3: Verificar que se Creó

```bash
# Ver imágenes locales
docker images | grep price-monitor

# Deberías ver algo como:
# diaverso/price-monitor   latest   abc123def456   2 minutes ago   500MB
# diaverso/price-monitor   2.1      abc123def456   2 minutes ago   500MB
```

---

<a name="publicar-docker-hub"></a>
## 📤 2. Publicar en Docker Hub

### Subir la Imagen

```bash
# Subir la versión latest
docker push diaverso/price-monitor:latest

# Subir una versión específica (si la creaste)
docker push diaverso/price-monitor:2.1
```

### Verificar en Docker Hub

1. Ve a https://hub.docker.com/r/diaverso/price-monitor
2. Deberías ver tu imagen publicada con:
   - Tag: `latest` y/o `2.1`
   - Tamaño de la imagen
   - Fecha de publicación

---

<a name="actualizar-imagen"></a>
## 🔄 3. Actualizar Imagen (Nuevas Versiones)

### Cuando Haces Cambios en el Código

```bash
# 1. Navegar a la carpeta
cd /var/www/html/price-monitor/Github

# 2. Reconstruir la imagen (esto usa caché para ser más rápido)
docker build -f docker/Dockerfile -t diaverso/price-monitor:latest .

# 3. Si es una nueva versión, también tagear con número de versión
docker tag diaverso/price-monitor:latest diaverso/price-monitor:2.2

# 4. Subir ambas versiones
docker push diaverso/price-monitor:latest
docker push diaverso/price-monitor:2.2
```

### Script Rápido para Actualizar

Crea este script para actualizar rápidamente:

```bash
# Archivo: update-docker.sh
#!/bin/bash

VERSION=${1:-latest}

echo "🐳 Construyendo imagen diaverso/price-monitor:$VERSION..."
docker build -f docker/Dockerfile -t diaverso/price-monitor:$VERSION .

if [ "$VERSION" != "latest" ]; then
    echo "🏷️  Tageando también como latest..."
    docker tag diaverso/price-monitor:$VERSION diaverso/price-monitor:latest
fi

echo "📤 Subiendo a Docker Hub..."
docker push diaverso/price-monitor:$VERSION

if [ "$VERSION" != "latest" ]; then
    docker push diaverso/price-monitor:latest
fi

echo "✅ ¡Imagen actualizada y publicada!"
```

**Uso:**

```bash
# Dar permisos de ejecución
chmod +x update-docker.sh

# Actualizar como latest
./update-docker.sh

# Actualizar como versión específica
./update-docker.sh 2.2
```

---

<a name="usar-imagen"></a>
## 🚀 4. Usar la Imagen Publicada

### Opción A: Con Docker Compose (Recomendado)

El archivo `docker-compose.yml` ya está configurado para usar `diaverso/price-monitor:latest`:

```bash
# Navegar a la carpeta docker
cd /var/www/html/price-monitor/Github/docker

# Levantar los servicios (descargará la imagen automáticamente)
docker-compose up -d

# Ver logs
docker-compose logs -f web

# Detener servicios
docker-compose down
```

### Opción B: Con Docker Run

```bash
# Descargar la imagen
docker pull diaverso/price-monitor:latest

# Ejecutar el contenedor
docker run -d \
  --name price-monitor-web \
  -p 8080:80 \
  -e MYSQL_HOST=mysql \
  -e MYSQL_DATABASE=price_monitor \
  -e MYSQL_USER=price_monitor_user \
  -e MYSQL_PASSWORD=tu_password \
  diaverso/price-monitor:latest

# Ver logs
docker logs -f price-monitor-web
```

### Opción C: Actualizar Imagen en Servidor en Producción

```bash
# Detener contenedores
docker-compose down

# Descargar última versión
docker pull diaverso/price-monitor:latest

# Levantar con nueva versión
docker-compose up -d

# Limpiar imágenes antiguas
docker image prune -f
```

---

<a name="comandos-útiles"></a>
## 🛠️ 5. Comandos Útiles

### Gestión de Imágenes

```bash
# Ver todas las imágenes
docker images

# Ver solo imágenes de price-monitor
docker images | grep price-monitor

# Eliminar imagen local
docker rmi diaverso/price-monitor:latest

# Eliminar imágenes sin usar
docker image prune -a

# Ver tamaño de imágenes
docker images --format "table {{.Repository}}\t{{.Tag}}\t{{.Size}}"
```

### Gestión de Contenedores

```bash
# Ver contenedores corriendo
docker ps

# Ver todos los contenedores (incluidos detenidos)
docker ps -a

# Detener contenedor
docker stop price-monitor-web

# Eliminar contenedor
docker rm price-monitor-web

# Ver logs en tiempo real
docker logs -f price-monitor-web

# Entrar al contenedor
docker exec -it price-monitor-web bash
```

### Gestión de Docker Compose

```bash
# Levantar servicios
docker-compose up -d

# Ver logs de todos los servicios
docker-compose logs -f

# Ver logs de un servicio específico
docker-compose logs -f web

# Reiniciar un servicio
docker-compose restart web

# Detener servicios
docker-compose down

# Detener y eliminar volúmenes
docker-compose down -v

# Ver estado de servicios
docker-compose ps

# Reconstruir imágenes (si cambió el Dockerfile)
docker-compose build

# Reconstruir y levantar
docker-compose up -d --build
```

### Limpieza

```bash
# Limpiar todo lo que no se está usando
docker system prune -a

# Limpiar solo imágenes sin usar
docker image prune -a

# Limpiar solo contenedores detenidos
docker container prune

# Limpiar solo volúmenes sin usar
docker volume prune

# Ver espacio usado por Docker
docker system df
```

### Información y Debug

```bash
# Ver información de un contenedor
docker inspect price-monitor-web

# Ver procesos dentro del contenedor
docker top price-monitor-web

# Ver recursos usados
docker stats price-monitor-web

# Ver red
docker network ls
docker network inspect price-monitor-network

# Ver volúmenes
docker volume ls
docker volume inspect price-monitor_mysql-data
```

---

## 📦 Flujo Completo de Trabajo

### Desarrollo Local → Docker Hub → Producción

```bash
# ===================================
# 1. DESARROLLO LOCAL
# ===================================

cd /var/www/html/price-monitor/Github

# Hacer cambios en el código
# Editar archivos, agregar features, etc.

# Probar localmente (sin Docker)
# http://localhost/price-monitor


# ===================================
# 2. CONSTRUIR IMAGEN DOCKER
# ===================================

# Login en Docker Hub (primera vez)
docker login

# Construir imagen con nueva versión
docker build -f docker/Dockerfile -t diaverso/price-monitor:2.2 .

# Tagear también como latest
docker tag diaverso/price-monitor:2.2 diaverso/price-monitor:latest


# ===================================
# 3. PROBAR IMAGEN LOCALMENTE
# ===================================

# Levantar con docker-compose
cd docker
docker-compose up -d

# Ver logs para verificar que funciona
docker-compose logs -f web

# Abrir navegador: http://localhost:8080

# Si todo funciona, continuar. Si no, corregir y reconstruir


# ===================================
# 4. PUBLICAR EN DOCKER HUB
# ===================================

# Subir versión específica
docker push diaverso/price-monitor:2.2

# Subir latest
docker push diaverso/price-monitor:latest


# ===================================
# 5. ACTUALIZAR EN PRODUCCIÓN
# ===================================

# En el servidor de producción:

cd /ruta/al/proyecto/docker

# Detener servicios
docker-compose down

# Descargar nueva versión
docker pull diaverso/price-monitor:latest

# Levantar con nueva versión
docker-compose up -d

# Ver logs
docker-compose logs -f web

# Limpiar imágenes antiguas
docker image prune -f


# ===================================
# 6. SUBIR CAMBIOS A GITHUB
# ===================================

cd /var/www/html/price-monitor/Github

git add .
git commit -m "feat: nueva funcionalidad añadida v2.2"
git push origin main
```

---

## 🏷️ Versionado Recomendado

### Estrategia de Tags

```bash
# Siempre mantener 'latest' apuntando a la última versión estable
diaverso/price-monitor:latest

# Usar versionado semántico: MAJOR.MINOR.PATCH
diaverso/price-monitor:2.1.0    # Versión estable
diaverso/price-monitor:2.1.1    # Bugfix
diaverso/price-monitor:2.2.0    # Nueva feature
diaverso/price-monitor:3.0.0    # Breaking changes

# Tags adicionales útiles
diaverso/price-monitor:stable   # Última versión 100% estable
diaverso/price-monitor:dev      # Versión de desarrollo
```

### Ejemplo de Versionado

```bash
# Release 2.1.0 (versión actual)
docker build -t diaverso/price-monitor:2.1.0 .
docker tag diaverso/price-monitor:2.1.0 diaverso/price-monitor:latest
docker tag diaverso/price-monitor:2.1.0 diaverso/price-monitor:stable

docker push diaverso/price-monitor:2.1.0
docker push diaverso/price-monitor:latest
docker push diaverso/price-monitor:stable

# Bugfix 2.1.1
docker build -t diaverso/price-monitor:2.1.1 .
docker tag diaverso/price-monitor:2.1.1 diaverso/price-monitor:latest
docker push diaverso/price-monitor:2.1.1
docker push diaverso/price-monitor:latest

# Nueva feature 2.2.0
docker build -t diaverso/price-monitor:2.2.0 .
docker tag diaverso/price-monitor:2.2.0 diaverso/price-monitor:latest
docker push diaverso/price-monitor:2.2.0
docker push diaverso/price-monitor:latest
```

---

## ✅ Checklist para Publicar Nueva Versión

- [ ] Código testeado localmente
- [ ] Dockerfile actualizado (si es necesario)
- [ ] docker-compose.yml actualizado (si es necesario)
- [ ] README actualizado con nuevos cambios
- [ ] Login en Docker Hub (`docker login`)
- [ ] Construir imagen con tag de versión
- [ ] Tagear como `latest`
- [ ] Probar imagen localmente con `docker-compose`
- [ ] Push de la versión específica a Docker Hub
- [ ] Push de `latest` a Docker Hub
- [ ] Verificar en Docker Hub que se subió
- [ ] Actualizar servidor de producción
- [ ] Commit y push a GitHub
- [ ] Crear GitHub Release (opcional)

---

## 🆘 Solución de Problemas

### Error: "denied: requested access to the resource is denied"

**Problema:** No tienes permisos o no estás logueado.

**Solución:**
```bash
docker login
# Usuario: diaverso
# Password: tu password de Docker Hub
```

---

### Error: "manifest for diaverso/price-monitor:latest not found"

**Problema:** La imagen no existe en Docker Hub.

**Solución:**
```bash
# Construir y subir la imagen
docker build -f docker/Dockerfile -t diaverso/price-monitor:latest .
docker push diaverso/price-monitor:latest
```

---

### Error: "no space left on device"

**Problema:** Docker ocupa mucho espacio.

**Solución:**
```bash
# Limpiar todo
docker system prune -a --volumes

# Ver espacio
docker system df
```

---

### La imagen es muy grande (> 1GB)

**Solución:** Optimizar el Dockerfile usando multi-stage builds y limpiando caché:

```dockerfile
# Ejemplo de optimización
RUN apt-get update && apt-get install -y \
    paquete1 \
    paquete2 \
    && rm -rf /var/lib/apt/lists/*
```

---

## 📞 Enlaces Útiles

- **Docker Hub:** https://hub.docker.com/r/diaverso/price-monitor
- **GitHub Repo:** https://github.com/diaverso/price-monitor
- **Documentación Docker:** https://docs.docker.com
- **Documentación Docker Compose:** https://docs.docker.com/compose

---

**Creado:** Noviembre 2025
**Autor:** diaverso
**Versión Actual:** 2.1

🤖 Generated with [Claude Code](https://claude.com/claude-code)
