# ============================================
# Monitor de Precios - Dockerfile v4.0
# Ubuntu 24.04 con Node.js 18.19.1 y Hero
# ============================================
FROM ubuntu:24.04

LABEL maintainer="Price Monitor <admin@pricemonitor.local>"
LABEL description="Sistema de monitoreo automático de precios con Hero"
LABEL version="4.0"

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

# Instalar paquetes esenciales
RUN apt-get update && apt-get install -y --no-install-recommends \
    # Apache y PHP
    apache2 \
    php \
    php-mysql \
    php-json \
    php-mbstring \
    php-curl \
    php-cli \
    php-xml \
    # MySQL/MariaDB Server
    mariadb-server \
    mariadb-client \
    # Utilidades básicas
    ca-certificates \
    curl \
    gnupg \
    cron \
    tzdata \
    supervisor \
    wget \
    unzip \
    # Python y Selenium
    python3 \
    python3-pip \
    # Node.js v18.19.1 y npm desde repos de Ubuntu 24.04
    nodejs \
    npm \
    # Dependencias de Chrome y Firefox (Camoufox/Playwright)
    libglib2.0-0 \
    libnspr4 \
    libnss3 \
    libdbus-1-3 \
    libatk1.0-0 \
    libatk-bridge2.0-0 \
    libcups2 \
    libxcb1 \
    libxkbcommon0 \
    libatspi2.0-0 \
    libx11-6 \
    libxcomposite1 \
    libxdamage1 \
    libxext6 \
    libxfixes3 \
    libxrandr2 \
    libgbm1 \
    libcairo2 \
    libpango-1.0-0 \
    libasound2t64 \
    fonts-liberation \
    libappindicator3-1 \
    xdg-utils \
    # Dependencias adicionales para Firefox/Camoufox
    libgtk-3-0 \
    libdbus-glib-1-2 \
    && rm -rf /var/lib/apt/lists/* \
    && apt-get clean

# Instalar Selenium para Python
RUN pip3 install --no-cache-dir selenium --break-system-packages

# Instalar dependencias Python avanzadas (Scrapling, Crawlee, Camoufox)
COPY requirements.txt /tmp/requirements.txt
RUN pip3 install --no-cache-dir -r /tmp/requirements.txt --break-system-packages \
    && rm /tmp/requirements.txt

# Instalar navegadores para Scrapling
RUN python3 -m scrapling install 2>&1 || true

# Instalar navegadores para Playwright/Crawlee (Chromium + Firefox)
RUN python3 -m playwright install --with-deps chromium firefox 2>&1 || true

# Instalar navegador Camoufox (Firefox parcheado con anti-detección C++)
# Forzar descarga ejecutando un scrape de prueba
RUN timeout 120 python3 -c "from camoufox.sync_api import Camoufox; import time; browser = Camoufox(headless=True); page = browser.new_page(); page.goto('about:blank'); time.sleep(1); browser.close()" 2>&1 || true

# Copiar navegadores de root a www-data con permisos correctos
RUN mkdir -p /var/www/.cache \
    && cp -r /root/.cache/ms-playwright /var/www/.cache/ 2>/dev/null || true \
    && cp -r /root/.cache/camoufox /var/www/.cache/ 2>/dev/null || true \
    && chown -R www-data:www-data /var/www/.cache \
    && chmod -R 755 /var/www/.cache

# Crear archivo glxtest dummy para Camoufox (evita errores en Docker headless)
RUN if [ -d /var/www/.cache/camoufox ]; then \
        echo '#!/bin/bash' > /var/www/.cache/camoufox/glxtest && \
        echo 'exit 0' >> /var/www/.cache/camoufox/glxtest && \
        chmod +x /var/www/.cache/camoufox/glxtest; \
    fi

# Crear wrapper para crashpad_handler de Playwright
RUN if [ -f /var/www/.cache/ms-playwright/chromium-*/chrome-linux/chrome_crashpad_handler ]; then \
        cd /var/www/.cache/ms-playwright/chromium-*/chrome-linux && \
        mv chrome_crashpad_handler chrome_crashpad_handler.real && \
        echo '#!/bin/bash' > chrome_crashpad_handler && \
        echo 'exec $(dirname $0)/chrome_crashpad_handler.real --database=/tmp "$@"' >> chrome_crashpad_handler && \
        chmod +x chrome_crashpad_handler; \
    fi

# Instalar Google Chrome for Testing 141.0.7390.122
RUN CHROME_VERSION="141.0.7390.122" \
    && echo "Installing Chrome for Testing version: $CHROME_VERSION" \
    && wget -q "https://storage.googleapis.com/chrome-for-testing-public/${CHROME_VERSION}/linux64/chrome-linux64.zip" -O /tmp/chrome.zip \
    && unzip -q /tmp/chrome.zip -d /opt/ \
    && ln -s /opt/chrome-linux64/chrome /usr/local/bin/google-chrome \
    && ln -s /opt/chrome-linux64/chrome /usr/local/bin/chrome \
    && chmod +x /opt/chrome-linux64/chrome \
    && rm -rf /tmp/chrome.zip

# Instalar ChromeDriver 141.0.7390.122
RUN CHROME_VERSION="141.0.7390.122" \
    && echo "Installing ChromeDriver version: $CHROME_VERSION" \
    && wget -q "https://storage.googleapis.com/chrome-for-testing-public/${CHROME_VERSION}/linux64/chromedriver-linux64.zip" -O /tmp/chromedriver.zip \
    && unzip -q /tmp/chromedriver.zip -d /tmp/ \
    && mv /tmp/chromedriver-linux64/chromedriver /usr/local/bin/ \
    && chmod +x /usr/local/bin/chromedriver \
    && rm -rf /tmp/chromedriver*

# Crear directorios para Hero
RUN mkdir -p /tmp/.ulixee \
    && chmod 777 /tmp/.ulixee \
    && mkdir -p /var/www/.cache \
    && chown -R www-data:www-data /var/www/.cache \
    && chmod 755 /var/www/.cache \
    && mkdir -p /root/.cache/ulixee

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
    && mkdir -p /var/lock/apache2 \
    && mkdir -p /var/run/mysqld \
    && mkdir -p /var/lib/mysql \
    && chown -R mysql:mysql /var/run/mysqld \
    && chown -R mysql:mysql /var/lib/mysql

# Copiar archivos del proyecto
WORKDIR /var/www/html/price-monitor
COPY . .

# Verificar que nodejs viene de repos de Ubuntu, no de nodesource
RUN rm -f /etc/apt/sources.list.d/nodesource.list \
    && rm -f /usr/share/keyrings/nodesource.gpg \
    && apt-get purge -y nodejs npm 2>/dev/null || true \
    && apt-get update \
    && apt-get install -y --no-install-recommends nodejs npm \
    && rm -rf /var/lib/apt/lists/*

# Instalar Hero con npm
RUN npm install --save @ulixee/hero-playground

# Instalar Chrome binario para Hero
RUN node /var/www/html/price-monitor/node_modules/@ulixee/chrome-139-0/install.js || true

# Copiar Hero Chrome para www-data y configurar permisos
RUN cp -r /root/.cache/ulixee/chrome/139.0.7258.154 /var/www/.cache/ulixee/chrome/ 2>/dev/null || true \
    && chown -R www-data:www-data /var/www/.cache/ulixee/chrome/139.0.7258.154 \
    && find /root/.cache/ulixee/chrome -name "chrome-sandbox" -exec chown root:root {} \; -exec chmod 4755 {} \; 2>/dev/null || true \
    && find /var/www/.cache/ulixee/chrome -name "chrome-sandbox" -exec chown root:root {} \; -exec chmod 4755 {} \; 2>/dev/null || true

# Crear wrapper para chrome_crashpad_handler
RUN cd /var/www/.cache/ulixee/chrome/139.0.7258.154 && \
    mv chrome_crashpad_handler chrome_crashpad_handler.real 2>/dev/null || true && \
    echo '#!/bin/bash' > chrome_crashpad_handler && \
    echo 'if echo "$@" | grep -q "\--database"; then' >> chrome_crashpad_handler && \
    echo '    exec /var/www/.cache/ulixee/chrome/139.0.7258.154/chrome_crashpad_handler.real "$@"' >> chrome_crashpad_handler && \
    echo 'else' >> chrome_crashpad_handler && \
    echo '    exec /var/www/.cache/ulixee/chrome/139.0.7258.154/chrome_crashpad_handler.real --database=/tmp "$@"' >> chrome_crashpad_handler && \
    echo 'fi' >> chrome_crashpad_handler && \
    chmod +x chrome_crashpad_handler

# Limpiar archivos innecesarios
RUN find . -type f -name "*.md" ! -name "README.md" -delete \
    && find . -type f -name ".git*" -delete \
    && find . -type d -name ".git" -exec rm -rf {} + 2>/dev/null || true \
    && find . -type d -name ".vscode" -exec rm -rf {} + 2>/dev/null || true \
    && find . -type d -name ".idea" -exec rm -rf {} + 2>/dev/null || true \
    && rm -rf docker/ 2>/dev/null || true \
    && rm -f *.log 2>/dev/null || true

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

# Configurar CRON
RUN echo "0 9 * * * www-data cd /var/www/html/price-monitor && /usr/bin/php cron/check_prices.php >> logs/cron.log 2>&1" > /etc/cron.d/price-monitor \
    && echo "0 14 * * * www-data cd /var/www/html/price-monitor && /usr/bin/php cron/check_prices.php >> logs/cron.log 2>&1" >> /etc/cron.d/price-monitor \
    && echo "0 0 * * * www-data cd /var/www/html/price-monitor && /usr/bin/php cron/check_prices.php >> logs/cron.log 2>&1" >> /etc/cron.d/price-monitor \
    && chmod 0644 /etc/cron.d/price-monitor

# Healthcheck
HEALTHCHECK --interval=30s --timeout=10s --start-period=40s --retries=3 \
    CMD curl -f http://localhost/ || exit 1

# Exponer puerto
EXPOSE 80

# Copiar configuración de Supervisor
COPY docker/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

# Script de inicio
COPY docker/entrypoint.sh /usr/local/bin/
COPY docker/apache2-foreground /usr/local/bin/
RUN chmod +x /usr/local/bin/entrypoint.sh \
    && chmod +x /usr/local/bin/apache2-foreground

# Limpieza final
RUN apt-get autoremove -y \
    && apt-get autoclean -y \
    && rm -rf /var/lib/apt/lists/* \
    && rm -rf /var/cache/apt/* \
    && rm -rf /tmp/* \
    && rm -rf /var/tmp/*

# Limpiar datos de MySQL
RUN rm -rf /var/lib/mysql/* \
    && chown -R mysql:mysql /var/lib/mysql

# Crear directorios de logs
RUN mkdir -p /var/log/supervisor \
    && mkdir -p /var/log/apache2 \
    && mkdir -p /var/www/html/price-monitor/logs

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
