#!/bin/bash

# ============================================
# Monitor de Precios - Script de Instalación Completo
# ============================================
# Este script instala y configura automáticamente
# todo el sistema de monitoreo de precios
# ============================================

set -e  # Salir en caso de error

# Colores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Variables globales
INSTALL_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
LOG_FILE="$INSTALL_DIR/logs/setup.log"
MYSQL_USER=""
MYSQL_PASS=""

# ============================================
# Funciones de utilidad
# ============================================

print_header() {
    echo ""
    echo -e "${CYAN}============================================${NC}"
    echo -e "${CYAN}$1${NC}"
    echo -e "${CYAN}============================================${NC}"
    echo ""
}

success() {
    echo -e "${GREEN}✓ $1${NC}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] SUCCESS: $1" >> "$LOG_FILE"
}

error() {
    echo -e "${RED}✗ Error: $1${NC}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] ERROR: $1" >> "$LOG_FILE"
    exit 1
}

warning() {
    echo -e "${YELLOW}⚠ $1${NC}"
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] WARNING: $1" >> "$LOG_FILE"
}

info() {
    echo -e "${BLUE}ℹ $1${NC}"
}

# ============================================
# Verificar que se ejecuta como root/sudo
# ============================================

check_root() {
    if [[ $EUID -ne 0 ]]; then
        error "Este script debe ejecutarse con sudo. Usa: sudo ./setup.sh"
    fi
    success "Permisos de administrador verificados"
}

# ============================================
# Crear directorio de logs
# ============================================

setup_logs() {
    mkdir -p "$INSTALL_DIR/logs"
    touch "$LOG_FILE"
    chmod 755 "$INSTALL_DIR/logs"
    success "Directorio de logs creado"
}

# ============================================
# Detectar sistema operativo
# ============================================

detect_os() {
    if [ -f /etc/os-release ]; then
        . /etc/os-release
        OS=$ID
        VER=$VERSION_ID
        info "Sistema detectado: $PRETTY_NAME"
    else
        error "No se pudo detectar el sistema operativo"
    fi
}

# ============================================
# Instalar dependencias según el SO
# ============================================

install_dependencies() {
    print_header "Instalando Dependencias del Sistema"

    case $OS in
        ubuntu|debian)
            info "Actualizando repositorios..."
            apt update -qq

            info "Instalando Apache2..."
            apt install -y apache2 > /dev/null 2>&1
            success "Apache2 instalado"

            info "Instalando PHP y extensiones..."
            apt install -y php php-mysql php-json php-mbstring php-curl php-cli > /dev/null 2>&1
            success "PHP instalado"

            info "Instalando MySQL..."
            apt install -y mysql-server > /dev/null 2>&1
            success "MySQL instalado"

            info "Instalando Node.js y npm..."
            if ! command -v node &> /dev/null; then
                curl -fsSL https://deb.nodesource.com/setup_18.x | bash - > /dev/null 2>&1
                apt install -y nodejs > /dev/null 2>&1
            fi
            success "Node.js instalado"

            info "Instalando herramientas adicionales..."
            apt install -y git curl wget > /dev/null 2>&1
            success "Herramientas adicionales instaladas"
            ;;

        centos|rhel|fedora)
            info "Instalando Apache..."
            dnf install -y httpd > /dev/null 2>&1
            success "Apache instalado"

            info "Instalando PHP..."
            dnf install -y php php-mysqlnd php-json php-mbstring php-curl > /dev/null 2>&1
            success "PHP instalado"

            info "Instalando MySQL..."
            dnf install -y mysql-server > /dev/null 2>&1
            success "MySQL instalado"

            info "Instalando Node.js..."
            dnf module install -y nodejs:18 > /dev/null 2>&1
            success "Node.js instalado"
            ;;

        *)
            error "Sistema operativo no soportado: $OS"
            ;;
    esac
}

# ============================================
# Verificar versiones de software
# ============================================

verify_software() {
    print_header "Verificando Versiones de Software"

    # PHP
    if command -v php &> /dev/null; then
        PHP_VERSION=$(php -v | head -n 1 | cut -d " " -f 2 | cut -d "." -f 1,2)
        success "PHP $PHP_VERSION instalado"
    else
        error "PHP no está instalado correctamente"
    fi

    # MySQL
    if command -v mysql &> /dev/null; then
        MYSQL_VERSION=$(mysql --version | awk '{print $5}' | cut -d "," -f 1)
        success "MySQL $MYSQL_VERSION instalado"
    else
        error "MySQL no está instalado correctamente"
    fi

    # Node.js
    if command -v node &> /dev/null; then
        NODE_VERSION=$(node -v)
        success "Node.js $NODE_VERSION instalado"
    else
        error "Node.js no está instalado correctamente"
    fi

    # npm
    if command -v npm &> /dev/null; then
        NPM_VERSION=$(npm -v)
        success "npm $NPM_VERSION instalado"
    else
        error "npm no está instalado correctamente"
    fi
}

# ============================================
# Configurar Apache
# ============================================

configure_apache() {
    print_header "Configurando Apache"

    # Iniciar y habilitar Apache
    if [[ $OS == "ubuntu" || $OS == "debian" ]]; then
        systemctl start apache2
        systemctl enable apache2 > /dev/null 2>&1
        success "Apache iniciado y habilitado"
    else
        systemctl start httpd
        systemctl enable httpd > /dev/null 2>&1
        success "Apache iniciado y habilitado"
    fi

    # Establecer permisos
    chown -R www-data:www-data "$INSTALL_DIR" 2>/dev/null || chown -R apache:apache "$INSTALL_DIR"
    chmod -R 755 "$INSTALL_DIR"
    success "Permisos configurados"

    # Habilitar mod_rewrite
    if [[ $OS == "ubuntu" || $OS == "debian" ]]; then
        a2enmod rewrite > /dev/null 2>&1
        systemctl reload apache2
        success "mod_rewrite habilitado"
    fi
}

# ============================================
# Configurar MySQL
# ============================================

configure_mysql() {
    print_header "Configurando MySQL"

    # Iniciar MySQL
    systemctl start mysql 2>/dev/null || systemctl start mysqld 2>/dev/null
    systemctl enable mysql 2>/dev/null || systemctl enable mysqld 2>/dev/null
    success "MySQL iniciado y habilitado"

    # Solicitar credenciales
    echo ""
    info "Configuración de MySQL"
    read -p "Usuario MySQL (default: root): " MYSQL_USER
    MYSQL_USER=${MYSQL_USER:-root}

    read -sp "Contraseña MySQL para $MYSQL_USER: " MYSQL_PASS
    echo ""

    # Verificar conexión
    if mysql -u"$MYSQL_USER" -p"$MYSQL_PASS" -e "SELECT 1;" > /dev/null 2>&1; then
        success "Conexión a MySQL exitosa"
    else
        error "No se pudo conectar a MySQL. Verifica tus credenciales."
    fi
}

# ============================================
# Crear base de datos
# ============================================

create_database() {
    print_header "Creando Base de Datos"

    info "Ejecutando schema.sql..."
    if mysql -u"$MYSQL_USER" -p"$MYSQL_PASS" < "$INSTALL_DIR/database/schema.sql" 2>&1 | tee -a "$LOG_FILE"; then
        success "Base de datos creada exitosamente"
    else
        error "Error al crear la base de datos"
    fi

    # Verificar tablas
    TABLE_COUNT=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASS" price_monitor -se "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = 'price_monitor'")

    if [ "$TABLE_COUNT" -ge 10 ]; then
        success "$TABLE_COUNT tablas creadas correctamente"
    else
        error "Solo se crearon $TABLE_COUNT tablas. Se esperaban al menos 10."
    fi
}

# ============================================
# Configurar archivo de configuración
# ============================================

configure_database_file() {
    print_header "Configurando Archivo de Base de Datos"

    CONFIG_FILE="$INSTALL_DIR/config/database.php"

    if [ ! -f "$CONFIG_FILE" ]; then
        info "Creando archivo de configuración..."
        cat > "$CONFIG_FILE" << EOF
<?php
// Configuración de conexión a base de datos
define('DB_HOST', 'localhost');
define('DB_NAME', 'price_monitor');
define('DB_USER', '$MYSQL_USER');
define('DB_PASS', '$MYSQL_PASS');
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
        chmod 600 "$CONFIG_FILE"
        success "Archivo de configuración creado"
    else
        warning "El archivo de configuración ya existe. No se modificó."
    fi
}

# ============================================
# Instalar Ulixee Hero
# ============================================

install_hero() {
    print_header "Instalando Ulixee Hero (Scraper)"

    cd "$INSTALL_DIR"

    info "Instalando dependencias de Node.js..."
    if npm install @ulixee/hero >> "$LOG_FILE" 2>&1; then
        success "Ulixee Hero instalado"
    else
        warning "Error al instalar Hero. Intenta manualmente: npm install @ulixee/hero"
    fi

    # Verificar instalación
    if [ -d "$INSTALL_DIR/node_modules/@ulixee/hero" ]; then
        success "Hero verificado en node_modules"
    else
        warning "Hero no se encuentra en node_modules"
    fi
}

# ============================================
# Configurar CRON
# ============================================

configure_cron() {
    print_header "Configurando CRON Jobs"

    info "¿Deseas configurar las verificaciones automáticas?"
    read -p "Ejecutar 3 veces al día (09:00, 14:00, 00:00)? (s/n): " SETUP_CRON

    if [[ "$SETUP_CRON" =~ ^[Ss]$ ]]; then
        # Obtener el usuario real (no root)
        REAL_USER="${SUDO_USER:-$USER}"

        # Crear entradas de cron temporales
        CRON_TEMP="/tmp/price_monitor_cron_$$"

        # Exportar crontab actual del usuario real
        sudo -u "$REAL_USER" crontab -l > "$CRON_TEMP" 2>/dev/null || true

        # Añadir nuevas entradas si no existen
        if ! grep -q "price-monitor/cron/check_prices.php" "$CRON_TEMP"; then
            cat >> "$CRON_TEMP" << EOF

# Monitor de Precios - Verificación automática
0 9 * * * cd $INSTALL_DIR && /usr/bin/php cron/check_prices.php >> logs/cron.log 2>&1
0 14 * * * cd $INSTALL_DIR && /usr/bin/php cron/check_prices.php >> logs/cron.log 2>&1
0 0 * * * cd $INSTALL_DIR && /usr/bin/php cron/check_prices.php >> logs/cron.log 2>&1
EOF
            # Instalar nuevo crontab
            sudo -u "$REAL_USER" crontab "$CRON_TEMP"
            success "CRON configurado (09:00, 14:00, 00:00)"
        else
            warning "CRON ya estaba configurado"
        fi

        rm -f "$CRON_TEMP"
    else
        info "CRON no configurado. Puedes configurarlo manualmente con: crontab -e"
    fi
}

# ============================================
# Configurar zona horaria
# ============================================

configure_timezone() {
    print_header "Configurando Zona Horaria"

    info "¿Deseas configurar la zona horaria española (Europe/Madrid)? (s/n): "
    read -p "" SET_TIMEZONE

    if [[ "$SET_TIMEZONE" =~ ^[Ss]$ ]]; then
        # Zona horaria del sistema
        timedatectl set-timezone Europe/Madrid 2>/dev/null || true
        success "Zona horaria del sistema: Europe/Madrid"

        # Zona horaria de PHP
        PHP_INI=$(php -i | grep "Loaded Configuration File" | awk '{print $5}')
        if [ -f "$PHP_INI" ]; then
            if grep -q "date.timezone" "$PHP_INI"; then
                sed -i 's/^;\?date.timezone.*/date.timezone = Europe\/Madrid/' "$PHP_INI"
            else
                echo "date.timezone = Europe/Madrid" >> "$PHP_INI"
            fi
            success "Zona horaria de PHP configurada"

            # Reiniciar Apache
            systemctl reload apache2 2>/dev/null || systemctl reload httpd 2>/dev/null
        fi
    else
        info "Zona horaria no modificada"
    fi
}

# ============================================
# Verificar event scheduler de MySQL
# ============================================

check_event_scheduler() {
    print_header "Verificando Event Scheduler de MySQL"

    EVENT_SCHEDULER=$(mysql -u"$MYSQL_USER" -p"$MYSQL_PASS" -se "SELECT @@event_scheduler" 2>/dev/null)

    if [ "$EVENT_SCHEDULER" == "ON" ]; then
        success "Event scheduler está activado"
    else
        warning "Event scheduler está desactivado"
        read -p "¿Deseas activarlo? (recomendado para limpieza automática) (s/n): " ENABLE_EVENTS

        if [[ "$ENABLE_EVENTS" =~ ^[Ss]$ ]]; then
            mysql -u"$MYSQL_USER" -p"$MYSQL_PASS" -e "SET GLOBAL event_scheduler = ON;" 2>/dev/null
            success "Event scheduler activado"
            warning "Para hacerlo permanente, añade 'event_scheduler=ON' en /etc/mysql/my.cnf bajo [mysqld]"
        fi
    fi
}

# ============================================
# Proteger archivos sensibles
# ============================================

secure_files() {
    print_header "Protegiendo Archivos Sensibles"

    # Proteger config
    if [ -f "$INSTALL_DIR/config/database.php" ]; then
        chmod 600 "$INSTALL_DIR/config/database.php"
        success "config/database.php protegido"
    fi

    # Crear .htaccess para proteger carpetas
    for dir in config database cron; do
        if [ -d "$INSTALL_DIR/$dir" ]; then
            echo "Deny from all" > "$INSTALL_DIR/$dir/.htaccess"
            success "$dir/.htaccess creado"
        fi
    done
}

# ============================================
# Resumen final
# ============================================

print_summary() {
    print_header "Instalación Completada"

    echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║                  INSTALACIÓN EXITOSA                       ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
    echo -e "${CYAN}📊 Resumen de la Instalación:${NC}"
    echo ""
    echo -e "  ${GREEN}✓${NC} Sistema operativo: $OS"
    echo -e "  ${GREEN}✓${NC} PHP: $(php -v | head -n 1 | cut -d " " -f 2)"
    echo -e "  ${GREEN}✓${NC} MySQL: $(mysql --version | awk '{print $5}' | cut -d "," -f 1)"
    echo -e "  ${GREEN}✓${NC} Node.js: $(node -v)"
    echo -e "  ${GREEN}✓${NC} Base de datos: price_monitor ($TABLE_COUNT tablas)"
    echo -e "  ${GREEN}✓${NC} Directorio: $INSTALL_DIR"
    echo ""
    echo -e "${CYAN}🌐 Acceso:${NC}"
    echo ""
    echo -e "  ${BLUE}→${NC} http://localhost/price-monitor/"
    echo -e "  ${BLUE}→${NC} http://localhost/price-monitor/login.html"
    echo -e "  ${BLUE}→${NC} http://localhost/price-monitor/dashboard.html"
    echo ""
    echo -e "${CYAN}📝 Próximos pasos:${NC}"
    echo ""
    echo -e "  1. Abre el navegador en: ${BLUE}http://localhost/price-monitor/${NC}"
    echo -e "  2. Regístrate como nuevo usuario"
    echo -e "  3. Agrega URLs de productos para monitorizar"
    echo -e "  4. Configura tus métodos de notificación"
    echo ""
    echo -e "${CYAN}📚 Documentación:${NC}"
    echo ""
    echo -e "  ${BLUE}→${NC} README.md - Documentación principal"
    echo -e "  ${BLUE}→${NC} INSTALL.md - Guía de instalación detallada"
    echo -e "  ${BLUE}→${NC} I18N_README.md - Sistema de idiomas"
    echo -e "  ${BLUE}→${NC} database/README.md - Base de datos"
    echo ""
    echo -e "${CYAN}📋 Logs:${NC}"
    echo ""
    echo -e "  ${BLUE}→${NC} Instalación: $LOG_FILE"
    echo -e "  ${BLUE}→${NC} CRON: $INSTALL_DIR/logs/cron.log"
    echo -e "  ${BLUE}→${NC} Scraping: $INSTALL_DIR/logs/scraping.log"
    echo ""
    echo -e "${GREEN}╔════════════════════════════════════════════════════════════╗${NC}"
    echo -e "${GREEN}║              ¡Disfruta del Monitor de Precios!             ║${NC}"
    echo -e "${GREEN}╚════════════════════════════════════════════════════════════╝${NC}"
    echo ""
}

# ============================================
# Función principal
# ============================================

main() {
    clear

    print_header "Monitor de Precios - Instalación Automática"

    info "Este script instalará automáticamente:"
    echo "  • PHP, MySQL, Node.js, Apache"
    echo "  • Ulixee Hero (scraper)"
    echo "  • Base de datos price_monitor"
    echo "  • CRON jobs (opcional)"
    echo "  • Configuración de zona horaria (opcional)"
    echo ""
    read -p "¿Continuar con la instalación? (s/n): " CONTINUE

    if [[ ! "$CONTINUE" =~ ^[Ss]$ ]]; then
        info "Instalación cancelada por el usuario"
        exit 0
    fi

    # Ejecutar pasos de instalación
    check_root
    setup_logs
    detect_os
    install_dependencies
    verify_software
    configure_apache
    configure_mysql
    create_database
    configure_database_file
    install_hero
    configure_cron
    configure_timezone
    check_event_scheduler
    secure_files
    print_summary
}

# Ejecutar script principal
main "$@"
