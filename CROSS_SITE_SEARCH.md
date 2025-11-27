# 🔍 Sistema de Búsqueda Cross-Site

Sistema inteligente para buscar el mismo producto en diferentes tiendas y comparar precios automáticamente.

## 📋 Características

- **Detección automática de marca y modelo** del producto
- **Categorización inteligente** por tipo de producto
- **Agrupación de productos similares** de diferentes tiendas
- **Comparación de precios en tiempo real**
- **Búsqueda automática** en tiendas relevantes según categoría
- **Vista de comparación** con mejor precio destacado

## 🏗️ Arquitectura

### Base de Datos

El sistema añade 3 nuevas tablas:

1. **`product_groups`**: Agrupa productos similares
   - `id`: ID del grupo
   - `name`: Nombre del producto
   - `brand`: Marca detectada
   - `model`: Modelo detectado
   - `category`: Categoría (electronica, informatica, hogar, moda, deporte, alimentacion)

2. **`product_group_items`**: Relaciona productos con grupos
   - `group_id`: ID del grupo
   - `monitored_url_id`: ID de la URL monitorizada
   - `is_primary`: Si es el producto original
   - `similarity_score`: Porcentaje de similitud (0-100)

3. **`store_categories`**: Define categorías de cada tienda
   - `store_domain`: Dominio de la tienda
   - `store_name`: Nombre amigable
   - `categories`: JSON con array de categorías
   - `search_url_pattern`: Patrón de URL de búsqueda

### Tiendas Configuradas

**Electrónica e Informática:**
- Amazon España
- PcComponentes
- MediaMarkt
- Coolmod
- El Corte Inglés

**Moda:**
- Mango
- Mango Outlet
- Michael Kors
- Zalando

**Deporte:**
- Decathlon

**Hogar:**
- IKEA
- LEGO

**Alimentación:**
- Mercadona
- Consum

**Marketplaces:**
- AliExpress
- Temu

## 🚀 Instalación

### 1. Ejecutar el script SQL

```bash
mysql -u root -p price_monitor < database/cross_site_search.sql
```

O desde Docker:

```bash
docker exec -i price-monitor-db mysql -u root -proot_password price_monitor < /path/to/cross_site_search.sql
```

### 2. Añadir JavaScript al dashboard

Editar `dashboard.html` y añadir antes del cierre de `</body>`:

```html
<script src="js/cross-site-search.js"></script>
```

### 3. Añadir botón "Buscar en otras tiendas"

En la tabla de URLs monitorizadas, añadir un botón por cada producto:

```html
<button onclick="crossSiteSearch.showSearchModal(${url.id}, '${url.product_name}')"
    class="btn btn-primary">
    🔍 Buscar en otras tiendas
</button>
```

## 📖 Uso

### 1. Iniciar búsqueda

```javascript
// Desde el dashboard, hacer clic en "Buscar en otras tiendas"
crossSiteSearch.showSearchModal(urlId, productName);
```

El sistema:
1. Analiza el nombre del producto
2. Detecta marca, modelo y categoría
3. Crea un grupo de productos
4. Muestra enlaces de búsqueda en tiendas relevantes

### 2. Añadir productos encontrados

1. Hacer clic en las tiendas sugeridas
2. Buscar el producto manualmente
3. Copiar la URL del producto encontrado
4. Pegarla en el formulario "Añadir producto encontrado"
5. Hacer clic en "Añadir"

### 3. Ver comparación de precios

```javascript
// Ver comparación automática
crossSiteSearch.viewComparison(groupId);
```

Muestra:
- Mejor precio destacado en verde
- Precio más alto y precio medio
- Número de tiendas
- Tarjetas comparativas con imágenes
- Enlaces directos a cada tienda

## 🔌 API Endpoints

### POST `/api/cross-site-search.php?action=search`

Iniciar búsqueda cross-site.

**Request:**
```json
{
  "url_id": 123
}
```

**Response:**
```json
{
  "success": true,
  "group_id": 45,
  "product_info": {
    "brand": "Samsung",
    "model": "QE65Q7FAU",
    "category": "electronica",
    "search_terms": ["Samsung QE65Q7FAU", "QE65Q7FAU"]
  },
  "search_urls": [
    {
      "store_name": "Amazon",
      "store_domain": "amazon.es",
      "search_url": "https://www.amazon.es/s?k=Samsung+QE65Q7FAU",
      "search_term": "Samsung QE65Q7FAU"
    }
  ]
}
```

### GET `/api/cross-site-search.php?action=comparison&group_id=45`

Obtener comparación de precios.

**Response:**
```json
{
  "success": true,
  "group_id": 45,
  "products": [
    {
      "url_id": 123,
      "store_name": "PcComponentes",
      "current_price": 799.99,
      "product_discount": 15.00,
      "is_primary": true
    },
    {
      "url_id": 124,
      "store_name": "Amazon",
      "current_price": 749.99,
      "product_discount": 20.00,
      "is_primary": false
    }
  ],
  "stats": {
    "best_price": 749.99,
    "highest_price": 799.99,
    "avg_price": 774.99,
    "store_count": 2
  }
}
```

### POST `/api/cross-site-search.php?action=add-to-group`

Añadir producto al grupo.

**Request:**
```json
{
  "group_id": 45,
  "url": "https://www.amazon.es/...",
  "target_price": 700
}
```

## 🎯 Ejemplo de Uso

```javascript
// 1. Usuario tiene una TV Samsung en PcComponentes
const urlId = 123; // ID de la URL monitorizada

// 2. Hacer clic en "Buscar en otras tiendas"
crossSiteSearch.showSearchModal(urlId, 'TV Samsung AI QLED 65" TQ65Q7FAAUXXC');

// 3. El sistema detecta:
// - Marca: Samsung
// - Modelo: TQ65Q7FAAUXXC
// - Categoría: electronica

// 4. Muestra enlaces de búsqueda en:
// - Amazon: https://www.amazon.es/s?k=Samsung+TQ65Q7FAAUXXC
// - MediaMarkt: https://www.mediamarkt.es/es/search.html?query=Samsung+TQ65Q7FAAUXXC
// - Coolmod: https://www.coolmod.com/buscar/?q=Samsung+TQ65Q7FAAUXXC

// 5. Usuario encuentra el producto en Amazon y añade la URL

// 6. Ver comparación:
crossSiteSearch.viewComparison(45);

// 7. Resultado:
// PcComponentes: 799.99€
// Amazon: 749.99€ ← MEJOR PRECIO
// MediaMarkt: 829.99€
```

## 🔧 Personalización

### Añadir nueva tienda

```sql
INSERT INTO store_categories (store_domain, store_name, categories, search_url_pattern)
VALUES (
    'nuevatienda.com',
    'Nueva Tienda',
    '["electronica", "informatica"]',
    'https://nuevatienda.com/buscar?q={query}'
);
```

### Añadir nueva categoría

1. Modificar ENUM en `product_groups.category`
2. Añadir categoría en `store_categories.categories`
3. Actualizar detección en `extractProductInfo()`

### Mejorar detección de marca/modelo

Editar en `api/cross-site-search.php`:

```php
function extractProductInfo($productName) {
    // Añadir nuevas marcas
    $brands = [
        'electronica' => ['Samsung', 'LG', 'Sony', 'TuMarca'],
        // ...
    ];
    // ...
}
```

## 📊 Vistas SQL

### `v_price_comparison`

Vista con todos los productos de cada grupo y sus precios:

```sql
SELECT * FROM v_price_comparison WHERE group_id = 45;
```

### `v_best_prices`

Vista con estadísticas resumidas por grupo:

```sql
SELECT * FROM v_best_prices WHERE group_id = 45;
```

## ⚠️ Limitaciones

1. **Búsqueda manual**: El usuario debe buscar y añadir manualmente las URLs encontradas
2. **Detección de marca/modelo**: Puede no funcionar para todos los productos
3. **Sin scraping automático**: Las URLs de búsqueda son solo sugerencias
4. **Categorización básica**: Usa palabras clave simples para detectar categoría

## 🔮 Mejoras Futuras

1. **Scraping automático** de resultados de búsqueda
2. **Machine Learning** para mejor detección de productos similares
3. **Comparación basada en especificaciones técnicas**
4. **Alertas cuando el mejor precio cambia**
5. **Historial de precios comparativo** entre tiendas
6. **API de búsqueda** integrada con cada tienda
7. **Sugerencias inteligentes** basadas en compras anteriores

## 📝 Notas

- El sistema no realiza scraping automático de búsquedas para evitar sobrecarga
- El usuario mantiene control total sobre qué productos son similares
- El `similarity_score` puede ajustarse manualmente si se detecta baja similitud
- Las URLs de búsqueda son generadas automáticamente pero el usuario debe validarlas

## 🤝 Contribuir

Para añadir más tiendas o mejorar la detección, editar:
- `database/cross_site_search.sql` - Datos de tiendas
- `api/cross-site-search.php` - Lógica de detección
- `js/cross-site-search.js` - Interfaz de usuario
