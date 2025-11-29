# Price Monitor / Monitor de Precios

<div align="center">

<img src="imagen-proyecto.png" alt="Price Monitor" width="800"/>

<br/>

[![Language: ES](https://img.shields.io/badge/Idioma-Español-blue)](#español)
[![Language: EN](https://img.shields.io/badge/Language-English-green)](#english)
[![Add Website](https://img.shields.io/badge/➕_Add_Website-Añadir_Sitio-orange)](https://github.com/diaverso/price-monitor/issues/new?labels=add-website&template=add-website.md)
[![Report Bug](https://img.shields.io/badge/🐛_Report_Bug-Reportar_Error-red)](https://github.com/diaverso/price-monitor/issues/new?labels=bug&template=bug_report.md)

</div>

---

<a name="español"></a>
# 🇪🇸 Español

## Monitor de Precios

Sistema completo de monitoreo automático de precios con soporte para 17 tiendas españolas e internacionales.

### 🎯 Tiendas Soportadas (17 Scrapers Especializados)

#### E-commerce General
✅ Amazon | ✅ AliExpress | ✅ eBay

#### Tecnología
✅ PcComponentes | ✅ El Corte Inglés | ✅ Coolmod | ✅ MediaMarkt

#### Supermercados
✅ Mercadona (API) | ✅ Consum

#### Moda y Deportes
✅ Zara | ✅ Zalando | ✅ Mango | ✅ Mango Outlet
✅ Michael Kors | ✅ Decathlon

#### Otros
✅ IKEA | ✅ Lego | ✅ Temu

### 🚀 Características Principales

#### Sistema de Monitoreo
- **17 scrapers especializados** con Ulixee Hero (anti-detección de bots)
- **Extracción automática** de precios, imágenes, títulos y descuentos
- **Scraping automático al añadir** URLs (extracción inmediata de datos del producto)
- **Verificación automática** 3 veces al día (09:00, 14:00, 00:00 hora española)
- **Historial de precios** con gráficos interactivos (Chart.js)
- **Multi-usuario** con sistema completo de autenticación

#### Agrupación Inteligente de Productos ⭐ NUEVO
- **Agrupación automática** por similitud de nombres (algoritmo ponderado con detección de código de modelo)
- **Agrupación manual** para productos que el sistema no detecta automáticamente
- **Desagrupación flexible** (individual o de todo el grupo)
- **Actualización en grupo** - Un solo clic actualiza todos los productos agrupados
- **Progreso visual** - Muestra "Actualizando Amazon (1/2)..." en tiempo real
- **Comparación de precios** entre tiendas del mismo producto

#### Notificaciones Inteligentes Multi-Canal
- **Email** (SMTP)
- **Telegram Bot**
- **WhatsApp** (Twilio)
- **SMS** (Twilio)

#### Sistema de Objetivos Dual ⭐
- ✅ **Precio objetivo**: Notifica cuando el precio alcanza o baja del precio configurado
- ✅ **Descuento objetivo**: Notifica cuando el descuento alcanza o supera el porcentaje deseado
- ⚠️ **Sin spam**: NO notifica por bajadas que no alcancen los objetivos configurados

#### Interfaz Multiidioma 🌐
- **Sistema i18n completo** con soporte para español e inglés
- **Selector visual de idioma** con banderas en todas las páginas
- **Persistencia de preferencias** en localStorage
- **Auto-detección** del idioma del navegador
- **Fácil extensión** para agregar más idiomas

### ¿Cómo Funciona el Sistema Automático?

#### Verificación Automática - 100% Automatizado

Una vez configurado el CRON, el sistema funciona completamente en automático:

1. **El sistema se ejecuta 3 veces al día** (09:00, 14:00, 00:00 - horario español)
2. **Verifica TODAS las URLs** de TODOS los usuarios en la base de datos
3. **Extrae el precio actual** de cada página web automáticamente
4. **Compara con los objetivos** configurado por cada usuario
5. **Envía notificaciones automáticamente** SOLO cuando:
   - El precio actual es igual o menor al **precio objetivo** configurado
   - El descuento actual es igual o mayor al **descuento objetivo** configurado
   - **Nota**: No se envían notificaciones por bajadas de precio que no alcancen los objetivos
6. **Guarda el historial** de precios en la base de datos

**No necesitas hacer nada más**: Una vez agregues URLs en el dashboard, el sistema las monitorizará automáticamente.

### 📦 Instalación Rápida

#### Opción 1: Instalación Tradicional

```bash
cd /var/www/html/price-monitor
sudo chmod +x setup.sh
sudo ./setup.sh
```

El script configura automáticamente:
- ✓ Verifica PHP, MySQL, Node.js
- ✓ Instala Ulixee Hero
- ✓ Configura base de datos
- ✓ Configura CRON automático
- ✓ Configura zona horaria española

#### Opción 2: Docker (Recomendado)

Ver [docker/README.md](docker/README.md) para instrucciones completas de Docker.

```bash
cd docker
docker-compose up -d
```

Acceso: `http://localhost:8080`

### 🔧 Stack Tecnológico

- **Backend**: PHP 7.4+, MySQL
- **Scraping**: Ulixee Hero (Node.js), Selenium WebDriver
- **Frontend**: HTML5, CSS3, JavaScript ES6
- **Gráficos**: Chart.js 4.4.0
- **i18n**: Sistema personalizado con JSON
- **Automatización**: Cron Jobs (Linux)
- **Notificaciones**: SMTP, Telegram API, Twilio API

### ⚡ Uso Rápido

1. Accede a `http://localhost/price-monitor/`
2. Regístrate o inicia sesión
3. Cambia el idioma si lo deseas (selector en navbar)
4. Agrega URLs de productos con precio y/o descuento objetivo
5. Configura tus métodos de notificación preferidos
6. El sistema monitoriza automáticamente 3 veces al día

#### Uso de Agrupación de Productos

**Agrupación Automática:**
- El sistema agrupa automáticamente productos con nombres similares (>70% similitud)
- Detecta códigos de modelo (ej: "LG 65QNED84A6C") para agrupar con alta precisión
- Los productos agrupados se muestran con badge "+N tiendas"

**Agrupación Manual:**
1. En cualquier producto, haz clic en "🔗 Agrupar Manualmente"
2. Selecciona los productos que deseas agrupar
3. Haz clic en "Agrupar Seleccionados"
4. Los productos se agruparán aunque no sean similares

**Actualización de Grupos:**
- Haz clic en "🔄 Actualizar Datos" en un producto agrupado
- Se actualizan TODAS las tiendas del grupo automáticamente
- Muestra progreso: "Actualizando Amazon (1/3)...", "Actualizando PcComponentes (2/3)..."
- Resumen final: "✅ Todas las tiendas actualizadas correctamente (3/3)"

**Desagrupación:**
- "🔓 Desagrupar" - Quita solo ese producto del grupo
- "🔓 Desagrupar Todos" - Separa todos los productos del grupo

### 📁 Estructura del Proyecto

```
price-monitor/
├── index.html              # Página de inicio (multiidioma)
├── login.html              # Login/Registro (multiidioma)
├── dashboard.html          # Panel de usuario (multiidioma)
├── translations.json       # Traducciones centralizadas
├── api/                    # Backend PHP
│   ├── auth.php           # Autenticación
│   ├── urls.php           # Gestión de URLs
│   ├── scrape.php         # Router de scrapers
│   ├── manual-grouping.php # Agrupación manual de productos
│   └── history.php        # Historial de precios
├── scrapers/              # Scrapers Node.js
│   └── hero_scraper.js    # Ulixee Hero (principal)
├── js/                    # JavaScript frontend
│   ├── i18n.js           # Motor de traducción
│   ├── dashboard.js       # Lógica del dashboard
│   └── countdown.js       # Contador regresivo
├── config/
│   └── database.php       # Configuración DB
├── database/              # Archivos de base de datos
│   └── schema.sql         # Esquema unificado
├── cron/
│   └── check_prices.php   # Script automático
└── docker/                # Configuración Docker
    ├── Dockerfile
    ├── docker-compose.yml
    └── README.md
```

### 💾 Base de Datos

#### Instalación/Actualización

```bash
mysql -u root -p < database/schema.sql
```

### 📞 Soporte

Para problemas o consultas, abre un issue en GitHub.

---

<a name="english"></a>
# 🇬🇧 English

## Price Monitor

Complete automated price monitoring system with support for 17 Spanish and international stores.

### 🎯 Supported Stores (17 Specialized Scrapers)

#### General E-commerce
✅ Amazon | ✅ AliExpress | ✅ eBay

#### Technology
✅ PcComponentes | ✅ El Corte Inglés | ✅ Coolmod | ✅ MediaMarkt

#### Supermarkets
✅ Mercadona (API) | ✅ Consum

#### Fashion & Sports
✅ Zara | ✅ Zalando | ✅ Mango | ✅ Mango Outlet
✅ Michael Kors | ✅ Decathlon

#### Others
✅ IKEA | ✅ Lego | ✅ Temu

### 🚀 Main Features

#### Monitoring System
- **17 specialized scrapers** with Ulixee Hero (anti-bot detection)
- **Automatic extraction** of prices, images, titles, and discounts
- **Automatic scraping on add** - Immediate product data extraction when adding URLs
- **Automatic verification** 3 times a day (09:00, 14:00, 00:00 Spanish time)
- **Price history** with interactive charts (Chart.js)
- **Multi-user** with complete authentication system

#### Smart Product Grouping ⭐ NEW
- **Automatic grouping** by name similarity (weighted algorithm with model code detection)
- **Manual grouping** for products the system doesn't detect automatically
- **Flexible ungrouping** (individual or entire group)
- **Group update** - Single click updates all grouped products
- **Visual progress** - Shows "Updating Amazon (1/2)..." in real-time
- **Price comparison** between stores for the same product

#### Smart Multi-Channel Notifications
- **Email** (SMTP)
- **Telegram Bot**
- **WhatsApp** (Twilio)
- **SMS** (Twilio)

#### Dual Target System ⭐
- ✅ **Target price**: Notifies when price reaches or drops below configured price
- ✅ **Target discount**: Notifies when discount reaches or exceeds desired percentage
- ⚠️ **No spam**: Does NOT notify for price drops that don't meet configured targets

#### Multi-language Interface 🌐
- **Complete i18n system** with Spanish and English support
- **Visual language selector** with flags on all pages
- **Preference persistence** in localStorage
- **Auto-detection** of browser language
- **Easy extension** to add more languages

### How Does the Automatic System Work?

#### Automatic Verification - 100% Automated

Once CRON is configured, the system works completely automatically:

1. **The system runs 3 times a day** (09:00, 14:00, 00:00 - Spanish time)
2. **Checks ALL URLs** from ALL users in the database
3. **Extracts current price** from each webpage automatically
4. **Compares with targets** configured by each user
5. **Sends notifications automatically** ONLY when:
   - Current price is equal or lower than **target price** configured
   - Current discount is equal or higher than **target discount** configured
   - **Note**: No notifications are sent for price drops that don't meet the targets
6. **Saves price history** in the database

**You don't need to do anything else**: Once you add URLs in the dashboard, the system will monitor them automatically.

### 📦 Quick Installation

#### Option 1: Traditional Installation

```bash
cd /var/www/html/price-monitor
sudo chmod +x setup.sh
sudo ./setup.sh
```

The script automatically configures:
- ✓ Verifies PHP, MySQL, Node.js
- ✓ Installs Ulixee Hero
- ✓ Configures database
- ✓ Configures automatic CRON
- ✓ Configures Spanish timezone

#### Option 2: Docker (Recommended)

See [docker/README.md](docker/README.md) for complete Docker instructions.

```bash
cd docker
docker-compose up -d
```

Access: `http://localhost:8080`

### 🔧 Tech Stack

- **Backend**: PHP 7.4+, MySQL
- **Scraping**: Ulixee Hero (Node.js), Selenium WebDriver
- **Frontend**: HTML5, CSS3, JavaScript ES6
- **Charts**: Chart.js 4.4.0
- **i18n**: Custom JSON-based system
- **Automation**: Cron Jobs (Linux)
- **Notifications**: SMTP, Telegram API, Twilio API

### ⚡ Quick Usage

1. Access `http://localhost/price-monitor/`
2. Register or login
3. Change language if desired (selector in navbar)
4. Add product URLs with target price and/or discount
5. Configure your preferred notification methods
6. System monitors automatically 3 times a day

#### Product Grouping Usage

**Automatic Grouping:**
- System automatically groups products with similar names (>70% similarity)
- Detects model codes (e.g., "LG 65QNED84A6C") for high-precision grouping
- Grouped products show "+N stores" badge

**Manual Grouping:**
1. On any product, click "🔗 Group Manually"
2. Select products you want to group
3. Click "Group Selected"
4. Products will be grouped even if not similar

**Group Update:**
- Click "🔄 Update Data" on a grouped product
- Updates ALL stores in the group automatically
- Shows progress: "Updating Amazon (1/3)...", "Updating PcComponentes (2/3)..."
- Final summary: "✅ All stores updated successfully (3/3)"

**Ungrouping:**
- "🔓 Ungroup" - Remove only that product from the group
- "🔓 Ungroup All" - Separate all products in the group

### 📁 Project Structure

```
price-monitor/
├── index.html              # Home page (multi-language)
├── login.html              # Login/Register (multi-language)
├── dashboard.html          # User dashboard (multi-language)
├── translations.json       # Centralized translations
├── api/                    # PHP Backend
│   ├── auth.php           # Authentication
│   ├── urls.php           # URL management
│   ├── scrape.php         # Scraper router
│   ├── manual-grouping.php # Manual product grouping
│   └── history.php        # Price history
├── scrapers/              # Node.js Scrapers
│   └── hero_scraper.js    # Ulixee Hero (main)
├── js/                    # Frontend JavaScript
│   ├── i18n.js           # Translation engine
│   ├── dashboard.js       # Dashboard logic
│   └── countdown.js       # Countdown timer
├── config/
│   └── database.php       # DB Configuration
├── database/              # Database files
│   └── schema.sql         # Unified schema
├── cron/
│   └── check_prices.php   # Automatic script
└── docker/                # Docker configuration
    ├── Dockerfile
    ├── docker-compose.yml
    └── README.md
```

### 💾 Database

#### Installation/Update

```bash
mysql -u root -p < database/schema.sql
```

### 📞 Support

For issues or questions, open an issue on GitHub.
