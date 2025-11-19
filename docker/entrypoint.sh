#!/bin/bash
set -e

# ============================================
# Entrypoint Script para Monitor de Precios
# ============================================

echo "============================================"
echo "Monitor de Precios - Iniciando Contenedor"
echo "============================================"

# Esperar a que MySQL esté disponible
if [ -n "$MYSQL_HOST" ]; then
    echo "Esperando a que MySQL esté disponible en $MYSQL_HOST:3306..."

    timeout=60
    while ! mariadb -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" -e "SELECT 1" >/dev/null 2>&1; do
        timeout=$((timeout - 1))
        if [ $timeout -le 0 ]; then
            echo "ERROR: MySQL no está disponible después de 60 segundos"
            exit 1
        fi
        echo "Esperando MySQL... ($timeout segundos restantes)"
        sleep 1
    done

    echo "✓ MySQL está disponible"
fi

# Crear archivo de configuración de base de datos si no existe
if [ ! -f /var/www/html/price-monitor/config/database.php ]; then
    echo "Creando archivo de configuración de base de datos..."

    cat > /var/www/html/price-monitor/config/database.php << EOF
<?php
// Configuración de conexión a base de datos
define('DB_HOST', '${MYSQL_HOST:-mysql}');
define('DB_NAME', '${MYSQL_DATABASE:-price_monitor}');
define('DB_USER', '${MYSQL_USER:-root}');
define('DB_PASS', '${MYSQL_PASSWORD:-}');
define('DB_CHARSET', 'utf8mb4');

// Conexión PDO
try {
    \$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    \$pdo = new PDO(\$dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false
    ]);
} catch (PDOException \$e) {
    die("Error de conexión: " . \$e->getMessage());
}
?>
EOF

    chmod 600 /var/www/html/price-monitor/config/database.php
    echo "✓ Archivo de configuración creado"
fi

# Inicializar base de datos si AUTO_INIT_DB=true
if [ "$AUTO_INIT_DB" = "true" ]; then
    echo "Inicializando base de datos..."

    # Esperar un poco más para asegurar que MySQL está completamente listo
    sleep 5

    if mariadb -h"$MYSQL_HOST" -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE" < /var/www/html/price-monitor/database/schema.sql 2>&1; then
        echo "✓ Base de datos inicializada"
    else
        echo "⚠ Error al inicializar la base de datos (puede que ya exista)"
    fi
fi

# Iniciar CRON
echo "Iniciando servicio CRON..."
cron
echo "✓ CRON iniciado"

# Asegurar permisos correctos
chown -R www-data:www-data /var/www/html/price-monitor/logs
chmod -R 777 /var/www/html/price-monitor/logs

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
