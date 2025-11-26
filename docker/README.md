# Docker - Price Monitor / Monitor de Precios

<div align="center">

[![Language: ES](https://img.shields.io/badge/Idioma-Español-blue)](#español) [![Language: EN](https://img.shields.io/badge/Language-English-green)](#english)

</div>

---

<a name="español"></a>
# 🇪🇸 Español

## Docker - Monitor de Precios

Configuración completa de Docker para el sistema de monitoreo de precios con **red aislada y segura**.

### 🎯 Características

#### Seguridad
- ✅ **Red aislada** interna entre contenedores
- ✅ **Sin acceso directo** a MySQL desde el host
- ✅ **Capabilities limitadas** (principio de mínimo privilegio)
- ✅ **Usuario no-root** para procesos
- ✅ **Carpetas sensibles protegidas** (config, database, cron)
- ✅ **Imagen multi-stage** (ligera y sin dependencias de build)

#### Optimización
- ✅ **Ubuntu Server** como base (imagen oficial)
- ✅ **Multi-stage build** (reduce tamaño final)
- ✅ **Sin archivos innecesarios** (.dockerignore)
- ✅ **Healthchecks** integrados
- ✅ **Límites de recursos** configurables

#### Funcionalidad
- ✅ **Auto-inicialización** de base de datos
- ✅ **CRON automático** (3 veces al día)
- ✅ **Logs persistentes** en volúmenes
- ✅ **Variables de entorno** configurables

---

### 📋 Requisitos

- **Docker** 20.10+
- **Docker Compose** 2.0+
- **4 GB RAM** mínimo
- **10 GB disco** disponible

#### Verificar instalación:

```bash
docker --version
docker-compose --version
```

---

### 🚀 Inicio Rápido

#### 1. Clonar Repositorio

```bash
git clone https://github.com/TU_USUARIO/price-monitor.git
cd price-monitor/docker
```

#### 2. Configurar Variables de Entorno

```bash
# Copiar archivo de ejemplo
cp .env.example .env

# Editar con tus contraseñas
nano .env
```

**⚠️ IMPORTANTE:** Cambia las contraseñas por defecto:

```env
MYSQL_ROOT_PASSWORD=TuContraseñaSegura123!
MYSQL_PASSWORD=OtraContraseñaSegura456!
```

#### 3. Iniciar Contenedores

```bash
docker-compose up -d
```

#### 4. Verificar Estado

```bash
docker-compose ps
docker-compose logs -f web
```

#### 5. Acceder a la Aplicación

```
http://localhost:8080
```

---

### 🏗️ Crear Imagen Docker Personalizada

#### Método 1: Usando Docker Compose (Recomendado)

```bash
# Navegar a la carpeta docker
cd price-monitor/docker

# Construir la imagen
docker-compose build

# Iniciar servicios
docker-compose up -d

# Verificar que todo funciona
docker-compose ps
```

#### Método 2: Construir Imagen Manualmente

```bash
# Desde la raíz del proyecto
docker build -f docker/Dockerfile -t mi-price-monitor:1.0 .

# Ver la imagen creada
docker images | grep mi-price-monitor

# Etiquetar para diferentes versiones
docker tag mi-price-monitor:1.0 mi-price-monitor:latest
```

#### Método 3: Script Automatizado

```bash
cd docker
chmod +x build.sh
./build.sh
```

---

### 🚢 Publicar Imagen en Docker Hub

#### 1. Login en Docker Hub

```bash
docker login
# Ingresa tu usuario y contraseña de Docker Hub
```

#### 2. Etiquetar Imagen

```bash
# Formato: docker tag imagen-local usuario/repositorio:tag
docker tag mi-price-monitor:1.0 tuusuario/price-monitor:1.0
docker tag mi-price-monitor:1.0 tuusuario/price-monitor:latest
```

#### 3. Subir Imagen

```bash
# Subir versión específica
docker push tuusuario/price-monitor:1.0

# Subir versión latest
docker push tuusuario/price-monitor:latest
```

#### 4. Verificar en Docker Hub

Visita: `https://hub.docker.com/r/tuusuario/price-monitor`

---

### 📦 Usar Imagen desde Docker Hub

#### Descargar y ejecutar:

```bash
# Descargar imagen
docker pull tuusuario/price-monitor:latest

# Ejecutar con docker-compose
# Edita docker-compose.yml y cambia:
# image: tuusuario/price-monitor:latest

# Iniciar
docker-compose up -d
```

---

### 🔧 Personalizar la Imagen Docker

#### Modificar Dockerfile

```dockerfile
# docker/Dockerfile

# Cambiar imagen base
FROM ubuntu:22.04

# Agregar paquetes adicionales
RUN apt-get update && apt-get install -y \
    tu-paquete-extra \
    otro-paquete

# Agregar configuración personalizada
COPY mi-config.conf /etc/apache2/sites-available/

# Cambiar puerto
EXPOSE 8080
```

#### Reconstruir después de cambios

```bash
docker-compose build --no-cache
docker-compose up -d
```

---

### 🎨 Crear Variantes de Imagen

#### Imagen Mínima (Solo Web)

```bash
# Crear Dockerfile.minimal
docker build -f docker/Dockerfile.minimal -t price-monitor:minimal .
```

#### Imagen de Desarrollo

```bash
# Crear Dockerfile.dev con herramientas de debug
docker build -f docker/Dockerfile.dev -t price-monitor:dev .
```

#### Imagen de Producción Optimizada

```bash
# Usar multi-stage build (ya incluido)
docker build -f docker/Dockerfile -t price-monitor:prod .
```

---

### 🛠️ Comandos Útiles

#### Gestión de Contenedores

```bash
# Iniciar servicios
docker-compose up -d

# Detener servicios
docker-compose down

# Reiniciar servicios
docker-compose restart

# Ver logs en tiempo real
docker-compose logs -f

# Ver logs solo de web
docker-compose logs -f web

# Ver estado de servicios
docker-compose ps

# Estadísticas de recursos
docker stats
```

#### Acceso a Contenedores

```bash
# Shell en contenedor web
docker-compose exec web bash

# Shell en contenedor MySQL
docker-compose exec mysql bash

# Ejecutar comando en web
docker-compose exec web php -v

# MySQL CLI
docker-compose exec mysql mysql -u root -p
```

#### Gestión de Imágenes

```bash
# Listar imágenes locales
docker images

# Eliminar imagen específica
docker rmi price-monitor:latest

# Eliminar imágenes no utilizadas
docker image prune

# Ver tamaño de imagen
docker images price-monitor:latest
```

---

### 🔄 Actualización de Imagen

#### Desde GitHub

```bash
cd price-monitor

# Pull de cambios
git pull origin main

# Reconstruir imagen
cd docker
docker-compose build --no-cache

# Reiniciar servicios
docker-compose down
docker-compose up -d
```

#### Desde Docker Hub

```bash
# Descargar última versión
docker pull tuusuario/price-monitor:latest

# Reiniciar con nueva imagen
docker-compose down
docker-compose up -d
```

---

### 📊 Variables de Entorno (.env)

| Variable | Descripción | Default | Requerido |
|----------|-------------|---------|-----------|
| `MYSQL_ROOT_PASSWORD` | Contraseña root MySQL | - | ✅ |
| `MYSQL_DATABASE` | Nombre de base de datos | `price_monitor` | ❌ |
| `MYSQL_USER` | Usuario de aplicación | `price_monitor_user` | ❌ |
| `MYSQL_PASSWORD` | Contraseña de usuario | - | ✅ |
| `WEB_PORT` | Puerto en host | `8080` | ❌ |
| `AUTO_INIT_DB` | Inicializar BD automáticamente | `true` | ❌ |
| `TZ` | Zona horaria | `Europe/Madrid` | ❌ |

---

### 🐛 Solución de Problemas

#### Error: "Cannot connect to MySQL"

```bash
# Verificar que MySQL está corriendo
docker-compose ps mysql

# Ver logs de MySQL
docker-compose logs mysql

# Reiniciar MySQL
docker-compose restart mysql
```

#### Error: "Port 8080 already in use"

Cambiar puerto en `.env`:

```env
WEB_PORT=9090
```

Luego reiniciar:

```bash
docker-compose down
docker-compose up -d
```

#### Imagen muy grande

```bash
# Ver tamaño
docker images price-monitor:latest

# Reconstruir sin caché
docker-compose build --no-cache --pull

# Limpiar imágenes intermedias
docker image prune
```

---

### 🔒 Seguridad

#### Red Aislada

La configuración utiliza una **red bridge interna** que:

1. ✅ **Aísla** los contenedores del resto de la red del host
2. ✅ **Permite comunicación** solo entre contenedores del proyecto
3. ✅ **Bloquea acceso directo** a MySQL desde fuera
4. ✅ **Permite scraping** (acceso a internet para descargar precios)

#### Mejores Prácticas

```bash
# Generar contraseña segura
openssl rand -base64 32

# NO exponer MySQL al host
# En docker-compose.yml, mantener comentado:
# ports:
#   - "3306:3306"  # ❌ MANTENER COMENTADO
```

---

### 📖 Referencias

- [Documentación principal](../README.md)
- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Reference](https://docs.docker.com/compose/)

---

**Versión:** 2.1
**Imagen base:** Ubuntu 22.04
**Tamaño aproximado:** ~500 MB

---

<a name="english"></a>
# 🇬🇧 English

## Docker - Price Monitor

Complete Docker configuration for the price monitoring system with **isolated and secure network**.

### 🎯 Features

#### Security
- ✅ **Isolated network** between containers
- ✅ **No direct access** to MySQL from host
- ✅ **Limited capabilities** (least privilege principle)
- ✅ **Non-root user** for processes
- ✅ **Protected sensitive folders** (config, database, cron)
- ✅ **Multi-stage image** (lightweight without build dependencies)

#### Optimization
- ✅ **Ubuntu Server** as base (official image)
- ✅ **Multi-stage build** (reduces final size)
- ✅ **No unnecessary files** (.dockerignore)
- ✅ **Integrated healthchecks**
- ✅ **Configurable resource limits**

#### Functionality
- ✅ **Auto-initialization** of database
- ✅ **Automatic CRON** (3 times a day)
- ✅ **Persistent logs** in volumes
- ✅ **Configurable environment variables**

---

### 📋 Requirements

- **Docker** 20.10+
- **Docker Compose** 2.0+
- **4 GB RAM** minimum
- **10 GB disk** available

#### Verify installation:

```bash
docker --version
docker-compose --version
```

---

### 🚀 Quick Start

#### 1. Clone Repository

```bash
git clone https://github.com/YOUR_USER/price-monitor.git
cd price-monitor/docker
```

#### 2. Configure Environment Variables

```bash
# Copy example file
cp .env.example .env

# Edit with your passwords
nano .env
```

**⚠️ IMPORTANT:** Change default passwords:

```env
MYSQL_ROOT_PASSWORD=YourSecurePassword123!
MYSQL_PASSWORD=AnotherSecurePassword456!
```

#### 3. Start Containers

```bash
docker-compose up -d
```

#### 4. Verify Status

```bash
docker-compose ps
docker-compose logs -f web
```

#### 5. Access Application

```
http://localhost:8080
```

---

### 🏗️ Create Custom Docker Image

#### Method 1: Using Docker Compose (Recommended)

```bash
# Navigate to docker folder
cd price-monitor/docker

# Build image
docker-compose build

# Start services
docker-compose up -d

# Verify everything works
docker-compose ps
```

#### Method 2: Build Image Manually

```bash
# From project root
docker build -f docker/Dockerfile -t my-price-monitor:1.0 .

# View created image
docker images | grep my-price-monitor

# Tag for different versions
docker tag my-price-monitor:1.0 my-price-monitor:latest
```

#### Method 3: Automated Script

```bash
cd docker
chmod +x build.sh
./build.sh
```

---

### 🚢 Publish Image to Docker Hub

#### 1. Login to Docker Hub

```bash
docker login
# Enter your Docker Hub username and password
```

#### 2. Tag Image

```bash
# Format: docker tag local-image username/repository:tag
docker tag my-price-monitor:1.0 yourusername/price-monitor:1.0
docker tag my-price-monitor:1.0 yourusername/price-monitor:latest
```

#### 3. Push Image

```bash
# Push specific version
docker push yourusername/price-monitor:1.0

# Push latest version
docker push yourusername/price-monitor:latest
```

#### 4. Verify on Docker Hub

Visit: `https://hub.docker.com/r/yourusername/price-monitor`

---

### 📦 Use Image from Docker Hub

#### Download and run:

```bash
# Download image
docker pull yourusername/price-monitor:latest

# Run with docker-compose
# Edit docker-compose.yml and change:
# image: yourusername/price-monitor:latest

# Start
docker-compose up -d
```

---

### 🔧 Customize Docker Image

#### Modify Dockerfile

```dockerfile
# docker/Dockerfile

# Change base image
FROM ubuntu:22.04

# Add additional packages
RUN apt-get update && apt-get install -y \
    your-extra-package \
    another-package

# Add custom configuration
COPY my-config.conf /etc/apache2/sites-available/

# Change port
EXPOSE 8080
```

#### Rebuild after changes

```bash
docker-compose build --no-cache
docker-compose up -d
```

---

### 🎨 Create Image Variants

#### Minimal Image (Web Only)

```bash
# Create Dockerfile.minimal
docker build -f docker/Dockerfile.minimal -t price-monitor:minimal .
```

#### Development Image

```bash
# Create Dockerfile.dev with debug tools
docker build -f docker/Dockerfile.dev -t price-monitor:dev .
```

#### Optimized Production Image

```bash
# Use multi-stage build (already included)
docker build -f docker/Dockerfile -t price-monitor:prod .
```

---

### 🛠️ Useful Commands

#### Container Management

```bash
# Start services
docker-compose up -d

# Stop services
docker-compose down

# Restart services
docker-compose restart

# View logs in real-time
docker-compose logs -f

# View web logs only
docker-compose logs -f web

# View service status
docker-compose ps

# Resource statistics
docker stats
```

#### Container Access

```bash
# Shell in web container
docker-compose exec web bash

# Shell in MySQL container
docker-compose exec mysql bash

# Execute command in web
docker-compose exec web php -v

# MySQL CLI
docker-compose exec mysql mysql -u root -p
```

#### Image Management

```bash
# List local images
docker images

# Remove specific image
docker rmi price-monitor:latest

# Remove unused images
docker image prune

# View image size
docker images price-monitor:latest
```

---

### 🔄 Image Update

#### From GitHub

```bash
cd price-monitor

# Pull changes
git pull origin main

# Rebuild image
cd docker
docker-compose build --no-cache

# Restart services
docker-compose down
docker-compose up -d
```

#### From Docker Hub

```bash
# Download latest version
docker pull yourusername/price-monitor:latest

# Restart with new image
docker-compose down
docker-compose up -d
```

---

### 📊 Environment Variables (.env)

| Variable | Description | Default | Required |
|----------|-------------|---------|----------|
| `MYSQL_ROOT_PASSWORD` | MySQL root password | - | ✅ |
| `MYSQL_DATABASE` | Database name | `price_monitor` | ❌ |
| `MYSQL_USER` | Application user | `price_monitor_user` | ❌ |
| `MYSQL_PASSWORD` | User password | - | ✅ |
| `WEB_PORT` | Port on host | `8080` | ❌ |
| `AUTO_INIT_DB` | Auto-initialize DB | `true` | ❌ |
| `TZ` | Timezone | `Europe/Madrid` | ❌ |

---

### 🐛 Troubleshooting

#### Error: "Cannot connect to MySQL"

```bash
# Verify MySQL is running
docker-compose ps mysql

# View MySQL logs
docker-compose logs mysql

# Restart MySQL
docker-compose restart mysql
```

#### Error: "Port 8080 already in use"

Change port in `.env`:

```env
WEB_PORT=9090
```

Then restart:

```bash
docker-compose down
docker-compose up -d
```

#### Image too large

```bash
# View size
docker images price-monitor:latest

# Rebuild without cache
docker-compose build --no-cache --pull

# Clean intermediate images
docker image prune
```

---

### 🔒 Security

#### Isolated Network

Configuration uses an **internal bridge network** that:

1. ✅ **Isolates** containers from rest of host network
2. ✅ **Allows communication** only between project containers
3. ✅ **Blocks direct access** to MySQL from outside
4. ✅ **Allows scraping** (internet access to download prices)

#### Best Practices

```bash
# Generate secure password
openssl rand -base64 32

# DO NOT expose MySQL to host
# In docker-compose.yml, keep commented:
# ports:
#   - "3306:3306"  # ❌ KEEP COMMENTED
```

---

### 📖 References

- [Main Documentation](../README.md)
- [Docker Documentation](https://docs.docker.com/)
- [Docker Compose Reference](https://docs.docker.com/compose/)

---

**Version:** 2.1
**Base Image:** Ubuntu 22.04
**Approximate Size:** ~500 MB
