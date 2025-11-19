# ============================================
# Monitor de Precios - Dockerfile Multi-Stage
# Imagen ligera y segura basada en Ubuntu Server
# ============================================

# ============================================
# Stage 1: Builder - Instalación de dependencias
# ============================================
FROM ubuntu:22.04 AS builder

# Evitar prompts interactivos
ENV DEBIAN_FRONTEND=noninteractive

# Instalar dependencias de build y Node.js en una sola capa
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    gnupg \
    lsb-release \
    && curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/* \
    && apt-get clean \
    && rm -rf /tmp/* /var/tmp/*

# Crear directorio de trabajo temporal
WORKDIR /build

# Copiar package.json desde el directorio raíz
COPY package*.json ./

# Instalar dependencias de Node.js
RUN npm install --omit=dev && npm cache clean --force

# ============================================
# Stage 2: Runtime - Imagen final ligera
# ============================================
FROM ubuntu:22.04

LABEL maintainer="Price Monitor <admin@pricemonitor.local>"
LABEL description="Sistema de monitoreo automático de precios"
LABEL version="2.1"

# Evitar prompts interactivos
ENV DEBIAN_FRONTEND=noninteractive

# Variables de entorno
ENV TZ=Europe/Madrid \
    APACHE_RUN_USER=www-data \
    APACHE_RUN_GROUP=www-data \
    APACHE_LOG_DIR=/var/log/apache2 \
    APACHE_RUN_DIR=/var/run/apache2 \
    APACHE_PID_FILE=/var/run/apache2/apache2.pid \
    APACHE_LOCK_DIR=/var/lock/apache2

# Instalar solo lo esencial y limpiar en una sola capa
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Apache y PHP
    apache2 \
    php \
    php-mysql \
    php-json \
    php-mbstring \
    php-curl \
    php-cli \
    # MySQL Client (para conexión)
    mariadb-client \
    # Node.js runtime
    ca-certificates \
    curl \
    gnupg \
    # Utilidades
    cron \
    tzdata \
    # Limpiar cache de apt
    && rm -rf /var/lib/apt/lists/* \
    && apt-get clean \
    && rm -rf /tmp/* /var/tmp/* \
    && rm -rf /var/cache/apt/archives/*

# Instalar Node.js 18.x (runtime) y limpiar
RUN curl -fsSL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get install -y nodejs \
    && rm -rf /var/lib/apt/lists/* \
    && apt-get clean \
    && rm -rf /tmp/* /var/tmp/* \
    && npm cache clean --force

# Copiar dependencias de Node.js desde builder
COPY --from=builder /build/node_modules /var/www/html/price-monitor/node_modules

# Configurar zona horaria
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo $TZ > /etc/timezone \
    && dpkg-reconfigure -f noninteractive tzdata

# Crear usuario no-root para scraping
RUN useradd -r -u 1001 -g www-data scraper

# Crear directorios necesarios
RUN mkdir -p /var/www/html/price-monitor \
    && mkdir -p /var/www/html/price-monitor/logs \
    && mkdir -p /var/run/apache2 \
    && mkdir -p /var/lock/apache2

# Copiar archivos del proyecto
WORKDIR /var/www/html/price-monitor
COPY . .

# Limpiar archivos innecesarios que pasaron el .dockerignore
RUN find . -type f -name "*.md" ! -name "README.md" -delete \
    && find . -type f -name ".git*" -delete \
    && find . -type d -name ".git" -exec rm -rf {} + 2>/dev/null || true \
    && find . -type d -name ".vscode" -exec rm -rf {} + 2>/dev/null || true \
    && find . -type d -name ".idea" -exec rm -rf {} + 2>/dev/null || true \
    && rm -rf docker/ 2>/dev/null || true \
    && rm -f *.log 2>/dev/null || true \
    && rm -rf logs/*.log 2>/dev/null || true

# Configurar permisos
RUN chown -R www-data:www-data /var/www/html/price-monitor \
    && chmod -R 755 /var/www/html/price-monitor \
    && chmod -R 777 /var/www/html/price-monitor/logs \
    && chmod 600 /var/www/html/price-monitor/config/database.php 2>/dev/null || true

# Configurar Apache
RUN a2enmod rewrite \
    && a2enmod headers \
    && echo "ServerName localhost" >> /etc/apache2/apache2.conf

# Crear configuración de VirtualHost
RUN echo '<VirtualHost *:80>\n\
    ServerAdmin admin@localhost\n\
    DocumentRoot /var/www/html/price-monitor\n\
    \n\
    <Directory /var/www/html/price-monitor>\n\
        Options -Indexes +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
    \n\
    # Denegar acceso a carpetas sensibles\n\
    <Directory /var/www/html/price-monitor/config>\n\
        Require all denied\n\
    </Directory>\n\
    \n\
    <Directory /var/www/html/price-monitor/database>\n\
        Require all denied\n\
    </Directory>\n\
    \n\
    <Directory /var/www/html/price-monitor/cron>\n\
        Require all denied\n\
    </Directory>\n\
    \n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/price-monitor.conf

RUN a2dissite 000-default.conf \
    && a2ensite price-monitor.conf

# Configurar CRON para verificaciones automáticas
RUN echo "0 9 * * * www-data cd /var/www/html/price-monitor && /usr/bin/php cron/check_prices.php >> logs/cron.log 2>&1" > /etc/cron.d/price-monitor \
    && echo "0 14 * * * www-data cd /var/www/html/price-monitor && /usr/bin/php cron/check_prices.php >> logs/cron.log 2>&1" >> /etc/cron.d/price-monitor \
    && echo "0 0 * * * www-data cd /var/www/html/price-monitor && /usr/bin/php cron/check_prices.php >> logs/cron.log 2>&1" >> /etc/cron.d/price-monitor \
    && chmod 0644 /etc/cron.d/price-monitor

# Healthcheck
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Exponer puerto
EXPOSE 80

# Script de inicio
COPY docker/entrypoint.sh /usr/local/bin/
COPY docker/apache2-foreground /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/apache2-foreground

# Limpieza final para reducir tamaño de imagen
RUN apt-get autoremove -y \
    && apt-get autoclean -y \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /var/cache/apt/* \
    && rm -rf /tmp/* \
    && rm -rf /var/tmp/* \
    && rm -rf /var/log/* \
    && find /var/log -type f -delete \
    && truncate -s 0 /var/log/lastlog

# Usuario no-root para ejecución (Apache maneja esto internamente)
# USER www-data

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/local/bin/apache2-foreground"]
