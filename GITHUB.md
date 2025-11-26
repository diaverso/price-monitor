# 📦 Guía Completa de GitHub para Price Monitor

## 📋 Tabla de Contenidos

1. [Crear Repositorio Nuevo (Primera Vez)](#crear-repositorio-nuevo)
2. [Subir Cambios Después](#subir-cambios)
3. [Comandos Útiles](#comandos-útiles)
4. [Solución de Problemas](#solución-de-problemas)

---

<a name="crear-repositorio-nuevo"></a>
## 🆕 1. Crear Repositorio Nuevo (Primera Vez)

### Paso 1: Crear el repositorio en GitHub.com

1. Ve a https://github.com
2. Click en el botón **"+"** (arriba a la derecha) → **"New repository"**
3. Configuración:
   - **Repository name:** `price-monitor`
   - **Description:** `Sistema completo de monitoreo automático de precios con 17 tiendas`
   - **Visibility:** Public
   - ❌ **NO marques** "Add a README file"
   - ❌ **NO marques** "Add .gitignore"
   - ❌ **NO marques** "Choose a license"
4. Click en **"Create repository"**

### Paso 2: Preparar tu carpeta local

```bash
# Navegar a la carpeta Github
cd /var/www/html/price-monitor/Github

# Si existe .git anterior, eliminarlo
rm -rf .git

# Inicializar nuevo repositorio
git init
```

### Paso 3: Configurar tu identidad (solo la primera vez)

```bash
# Configurar nombre
git config --global user.name "diaverso"

# Configurar email (usa el mismo de tu cuenta GitHub)
git config --global user.email "tu-email@gmail.com"
```

### Paso 4: Subir todo al repositorio

```bash
# Agregar todos los archivos
git add .

# Crear el primer commit
git commit -m "Initial commit: Price Monitor v2.1 - Complete price monitoring system

✨ Features:
- 17 specialized scrapers for Spanish and international stores
- Bilingual interface (Spanish/English)
- Automatic notifications when price/discount targets are reached
- Price history with interactive charts
- GitHub issue templates for reporting bugs and requesting new stores
- Issues dropdown integrated in all pages
- Docker support with custom image instructions

🏪 Supported Stores:
Amazon, AliExpress, eBay, PcComponentes, El Corte Inglés, Coolmod,
MediaMarkt, Mercadona, Consum, Carrefour, Lidl, Decathlon, Leroy Merlin,
Zara, Mango, FNAC, Worten

🤖 Generated with Claude Code
https://claude.com/claude-code

Co-Authored-By: Claude <noreply@anthropic.com>"

# Cambiar a rama main
git branch -M main

# Conectar con GitHub (reemplaza 'diaverso' con tu usuario si es diferente)
git remote add origin https://github.com/diaverso/price-monitor.git

# Subir todo
git push -u origin main
```

### Paso 5: Autenticación

Cuando te pida credenciales:
- **Username:** `diaverso`
- **Password:** ⚠️ **USA TU PERSONAL ACCESS TOKEN** (NO tu contraseña)

#### ¿Cómo crear un Personal Access Token?

1. Ve a GitHub.com → Click en tu foto → **Settings**
2. Scroll hasta abajo → **Developer settings**
3. **Personal access tokens** → **Tokens (classic)**
4. Click en **"Generate new token (classic)"**
5. Configuración:
   - **Note:** `Price Monitor - Full Access`
   - **Expiration:** 90 days (o sin expiración)
   - **Scopes:** Marca ✅ **repo** (acceso completo)
6. Click en **"Generate token"**
7. **⚠️ COPIA EL TOKEN AHORA** (solo se muestra una vez)
8. Guárdalo en un lugar seguro
9. Úsalo como contraseña cuando hagas `git push`

---

<a name="subir-cambios"></a>
## 🔄 2. Subir Cambios Después (Actualizaciones Futuras)

### Opción A: Subir TODOS los cambios

```bash
# Navegar a la carpeta
cd /var/www/html/price-monitor/Github

# Ver qué archivos cambiaron
git status

# Agregar TODOS los cambios
git add .

# Crear commit con descripción
git commit -m "Descripción de los cambios realizados"

# Subir a GitHub
git push origin main
```

### Opción B: Subir archivos específicos

```bash
# Navegar a la carpeta
cd /var/www/html/price-monitor/Github

# Agregar solo archivos específicos
git add README.md
git add js/i18n.js
git add translations.json

# Crear commit
git commit -m "Update: descripción de cambios específicos"

# Subir a GitHub
git push origin main
```

### Ejemplos de Mensajes de Commit

**Agregar nueva funcionalidad:**
```bash
git commit -m "feat: Add new scraper for Zara store"
```

**Corregir un bug:**
```bash
git commit -m "fix: Resolved notification spam issue"
```

**Actualizar documentación:**
```bash
git commit -m "docs: Update README with new installation steps"
```

**Mejorar código existente:**
```bash
git commit -m "refactor: Improve price comparison logic"
```

**Actualizar dependencias:**
```bash
git commit -m "chore: Update npm dependencies"
```

---

<a name="comandos-útiles"></a>
## 🛠️ 3. Comandos Útiles

### Ver el estado actual

```bash
cd /var/www/html/price-monitor/Github

# Ver archivos modificados
git status

# Ver diferencias en archivos
git diff

# Ver diferencias de un archivo específico
git diff README.md
```

### Ver historial de commits

```bash
# Ver últimos commits
git log

# Ver últimos 5 commits resumidos
git log --oneline -5

# Ver historial con gráfico
git log --oneline --graph --all
```

### Deshacer cambios

```bash
# Deshacer cambios en un archivo (antes de git add)
git checkout -- README.md

# Deshacer git add (quitar del staging)
git reset HEAD README.md

# Deshacer último commit (mantener cambios)
git reset --soft HEAD~1

# Deshacer último commit (BORRAR cambios - ⚠️ cuidado)
git reset --hard HEAD~1
```

### Actualizar desde GitHub (traer cambios)

```bash
# Si alguien más hizo cambios en GitHub
git pull origin main
```

### Ver información del repositorio

```bash
# Ver URL del repositorio remoto
git remote -v

# Ver ramas
git branch -a

# Ver configuración
git config --list
```

---

<a name="solución-de-problemas"></a>
## 🆘 4. Solución de Problemas

### Error: "Author identity unknown"

**Problema:** Git no sabe quién eres.

**Solución:**
```bash
git config --global user.name "diaverso"
git config --global user.email "tu-email@gmail.com"
```

---

### Error: "Support for password authentication was removed"

**Problema:** Estás usando tu contraseña de GitHub en lugar del token.

**Solución:**
1. Crea un Personal Access Token (ver instrucciones arriba)
2. Usa el TOKEN como contraseña, no tu contraseña de GitHub

---

### Error: "fatal: 'origin' does not appear to be a git repository"

**Problema:** No has conectado tu repositorio local con GitHub.

**Solución:**
```bash
git remote add origin https://github.com/diaverso/price-monitor.git
```

---

### Error: "Updates were rejected because the remote contains work"

**Problema:** Hay cambios en GitHub que no tienes localmente.

**Solución:**
```bash
# Traer cambios primero
git pull origin main --no-rebase

# Resolver conflictos si hay
# Luego subir
git push origin main
```

---

### Error: "fatal: not a git repository"

**Problema:** No estás en una carpeta con git inicializado.

**Solución:**
```bash
cd /var/www/html/price-monitor/Github
git init
```

---

### Quiero empezar de cero (borrar todo el historial)

```bash
cd /var/www/html/price-monitor/Github

# Borrar .git
rm -rf .git

# Inicializar de nuevo
git init
git add .
git commit -m "Initial commit"
git branch -M main
git remote add origin https://github.com/diaverso/price-monitor.git
git push -u origin main --force
```

---

## 📁 Archivos que se Subirán

```
price-monitor/
├── .github/
│   └── ISSUE_TEMPLATE/
│       ├── add-website.md      ← Template para solicitar sitios
│       ├── bug_report.md       ← Template para reportar bugs
│       └── config.yml          ← Configuración de issues
├── api/                        ← Endpoints de la API
├── config/                     ← Configuración de la aplicación
├── cron/                       ← Scripts de monitoreo automático
├── database/                   ← Esquema de base de datos
├── docker/                     ← Configuración Docker
├── js/                         ← JavaScript (incluye i18n.js)
├── scrapers/                   ← Scrapers de las 17 tiendas
├── services/                   ← Servicios de notificación
├── .env.example                ← Ejemplo de variables de entorno
├── .gitignore                  ← Archivos ignorados
├── .htaccess                   ← Configuración Apache
├── dashboard.html              ← Panel principal
├── index.html                  ← Página de inicio
├── login.html                  ← Página de login
├── price-chart.html            ← Gráficos de precios
├── imagen-proyecto.png         ← Imagen del proyecto (2.3 MB)
├── README.md                   ← Documentación principal
├── translations.json           ← Traducciones ES/EN
├── package.json                ← Dependencias Node.js
└── setup.sh                    ← Script de instalación
```

---

## 🎯 Flujo de Trabajo Recomendado

### Cuando trabajas en tu proyecto:

1. **Haces cambios** en los archivos (editas código, adds features, etc.)

2. **Verificas qué cambió:**
   ```bash
   cd /var/www/html/price-monitor/Github
   git status
   ```

3. **Agregas los cambios:**
   ```bash
   git add .
   ```

4. **Creas un commit:**
   ```bash
   git commit -m "feat: descripción clara de lo que hiciste"
   ```

5. **Subes a GitHub:**
   ```bash
   git push origin main
   ```

### Comandos rápidos para copiar-pegar:

```bash
# Subir cambios rápido
cd /var/www/html/price-monitor/Github && git add . && git commit -m "Update: cambios realizados" && git push origin main
```

---

## ✅ Verificar que Funcionó

Después de `git push`, verifica:

1. Ve a https://github.com/diaverso/price-monitor
2. Deberías ver:
   - ✅ Todos tus archivos
   - ✅ La imagen del proyecto en el README
   - ✅ Los badges de idioma
3. Click en **"Issues"** → **"New issue"**
   - Deberías ver las plantillas:
     - 🌐 Add Website / Añadir Sitio Web
     - 🐛 Bug Report / Reporte de Error

---

## 📞 Contacto

- **GitHub:** https://github.com/diaverso/price-monitor
- **Issues:** https://github.com/diaverso/price-monitor/issues

---

**Creado:** Noviembre 2025
**Autor:** diaverso
**Versión:** 2.1

🤖 Generated with [Claude Code](https://claude.com/claude-code)
