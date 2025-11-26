#!/bin/bash
set -e

# ============================================
# Entrypoint Script para Monitor de Precios
# ============================================

echo "============================================"
echo "Monitor de Precios - Iniciando Contenedor"
echo "============================================"

# Verificar si es la primera inicialización
# Comprobamos si existe el directorio mysql/mysql (tablas del sistema)
FIRST_RUN=false
if [ ! -f "/var/lib/mysql/mysql/user.frm" ] && [ ! -f "/var/lib/mysql/mysql/global_priv.MAD" ]; then
    echo "Inicializando MySQL por primera vez..."
    mysql_install_db --user=mysql --ldata=/var/lib/mysql
    FIRST_RUN=true
    echo "✓ MySQL inicializado"
fi

# Solo realizar configuración inicial en el primer inicio
if [ "$FIRST_RUN" = true ]; then
    # Iniciar MySQL temporalmente para crear base de datos
    echo "Iniciando MySQL temporalmente..."
    mysqld_safe --skip-networking &
    MYSQL_PID=$!

    # Esperar a que MySQL esté disponible
    echo "Esperando a que MySQL esté disponible..."
    timeout=60
    while ! mysqladmin ping -h localhost --silent 2>/dev/null; do
        timeout=$((timeout - 1))
        if [ $timeout -le 0 ]; then
            echo "ERROR: MySQL no está disponible después de 60 segundos"
            kill $MYSQL_PID 2>/dev/null || true
            exit 1
        fi
        sleep 1
    done

    echo "✓ MySQL está disponible"

    echo "Configurando base de datos (primera vez)..."
    mysql --protocol=socket -uroot <<-EOSQL
        CREATE DATABASE IF NOT EXISTS ${MYSQL_DATABASE:-price_monitor} CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
        CREATE USER IF NOT EXISTS '${MYSQL_USER:-price_monitor_user}'@'%' IDENTIFIED BY '${MYSQL_PASSWORD:-change_this_password}';
        CREATE USER IF NOT EXISTS '${MYSQL_USER:-price_monitor_user}'@'localhost' IDENTIFIED BY '${MYSQL_PASSWORD:-change_this_password}';
        GRANT ALL PRIVILEGES ON ${MYSQL_DATABASE:-price_monitor}.* TO '${MYSQL_USER:-price_monitor_user}'@'%';
        GRANT ALL PRIVILEGES ON ${MYSQL_DATABASE:-price_monitor}.* TO '${MYSQL_USER:-price_monitor_user}'@'localhost';
        ALTER USER 'root'@'localhost' IDENTIFIED BY '${MYSQL_ROOT_PASSWORD:-root_password}';
        FLUSH PRIVILEGES;
EOSQL
    echo "✓ Base de datos configurada"

    # Crear/actualizar archivo .env con configuración de base de datos
    echo "Configurando archivo .env..."
    cat > /var/www/html/price-monitor/.env << EOF
# Configuración de Base de Datos
DB_HOST=localhost
DB_PORT=3306
DB_NAME=${MYSQL_DATABASE:-price_monitor}
DB_USER=${MYSQL_USER:-price_monitor_user}
DB_PASS=${MYSQL_PASSWORD:-change_this_password}
DB_CHARSET=utf8mb4

# Entorno
APP_ENV=production
APP_DEBUG=false
EOF
    chown www-data:www-data /var/www/html/price-monitor/.env
    chmod 640 /var/www/html/price-monitor/.env
    echo "✓ Archivo .env configurado"

    # Importar schema de base de datos
    echo "Importando schema de base de datos..."
    if mysql --protocol=socket -u"${MYSQL_USER:-price_monitor_user}" -p"${MYSQL_PASSWORD:-change_this_password}" "${MYSQL_DATABASE:-price_monitor}" < /var/www/html/price-monitor/database/schema.sql 2>&1; then
        echo "✓ Schema importado correctamente"
    else
        echo "⚠ Error al importar schema (puede que ya exista)"
    fi

    # Detener MySQL temporal
    echo "Deteniendo MySQL temporal..."

    # Usar mysqladmin para detener limpiamente
    mysqladmin --protocol=socket -uroot -p"${MYSQL_ROOT_PASSWORD:-root_password}" shutdown 2>/dev/null || true

    # Esperar hasta 15 segundos a que MySQL se detenga
    timeout=15
    while kill -0 $MYSQL_PID 2>/dev/null; do
        timeout=$((timeout - 1))
        if [ $timeout -le 0 ]; then
            echo "⚠ Timeout esperando mysqld_safe, matando procesos MySQL..."
            # Matar todos los procesos MySQL
            killall mysqld mariadbd mysqld_safe 2>/dev/null || true
            sleep 2
            break
        fi
        sleep 1
    done

    # Asegurar que no quede ningún proceso MySQL
    killall -9 mysqld mariadbd mysqld_safe 2>/dev/null || true
    sleep 1

    echo "✓ MySQL temporal detenido"
else
    echo "⏭️  MySQL ya configurado, dejando que supervisor lo maneje"
    echo "⏭️  Base de datos ya existe (saltando configuración)"
    echo "⏭️  Schema ya existe (saltando importación)"
fi


# Verificar e instalar dependencias de Node.js si faltan
echo "Verificando dependencias de Node.js..."
if [ ! -d "/var/www/html/price-monitor/node_modules/@ulixee/hero-playground" ] || [ ! -d "/var/www/html/price-monitor/node_modules/@ulixee/chrome-139-0" ]; then
    echo "Instalando dependencias de Node.js..."
    cd /var/www/html/price-monitor
    npm install --omit=dev 2>&1 | grep -v "npm WARN" || true
    echo "✓ Dependencias de Node.js instaladas"
else
    echo "✓ Dependencias de Node.js ya instaladas"
fi

# Verificar si el binario de Chrome está descargado
echo "Verificando binario de Chrome para Hero..."
if [ ! -d "/var/www/html/price-monitor/node_modules/@ulixee/chrome-139-0/.local-chromium" ]; then
    echo "Descargando binario de Chrome..."
    cd /var/www/html/price-monitor
    # Descargar como root, pero asegurar que www-data pueda acceder
    HOME=/var/www npm rebuild @ulixee/chrome-139-0 2>&1 | grep -v "npm WARN" || true
    echo "✓ Binario de Chrome descargado"
else
    echo "✓ Binario de Chrome ya descargado"
fi

# Crear y configurar directorios para Hero con permisos correctos
echo "Configurando directorios para Hero..."
mkdir -p /tmp/.ulixee
chmod 777 /tmp/.ulixee
mkdir -p /var/www/.cache
chown -R www-data:www-data /var/www/.cache
chmod -R 755 /var/www/.cache

# Asegurar permisos correctos para logs y node_modules
chown -R www-data:www-data /var/www/html/price-monitor/logs
chmod -R 777 /var/www/html/price-monitor/logs
chown -R www-data:www-data /var/www/html/price-monitor/node_modules
chmod -R 755 /var/www/html/price-monitor/node_modules

echo "✓ Permisos configurados"
echo ""
echo "============================================"
echo "Monitor de Precios - Contenedor Iniciado"
echo "============================================"
echo ""
echo "Acceso: http://localhost (o el puerto mapeado)"
echo "Logs: /var/www/html/price-monitor/logs/"
echo ""

# Ejecutar comando principal (Apache)
exec "$@"

