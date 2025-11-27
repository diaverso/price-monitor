# Plan: Scraping Automático Cross-Site

## Objetivo
Implementar scraping automático de páginas de búsqueda para encontrar productos similares y extraer precios, similar a como funciona Idealo.es

## Ejemplo de Uso
**Input**: TV LG QNED AI 65QNED84A6C desde Amazon
**Output**: Encuentra automáticamente en PcComponentes la URL https://www.pccomponentes.com/tv-lg-qned-ai-65qned84a6c-65-4k-ultrahd-smart-tv-webos-hdr10-dolby-digital con su precio

## Arquitectura

### 1. Scraper de Páginas de Búsqueda
Crear scrapers específicos para cada tienda que extraigan:
- Lista de productos encontrados
- Nombre del producto
- Precio
- URL del producto
- Imagen (opcional)

### 2. Algoritmo de Matching
Comparar el producto original con los resultados para encontrar el mejor match:
- Similitud de marca
- Similitud de modelo
- Similitud de especificaciones clave
- Score de confianza (0-100%)

### 3. Integración con API
Modificar `api/cross-site-search.php` para:
- Endpoint `auto-search`: Realizar búsqueda automática
- Procesar resultados y añadir al grupo automáticamente
- Retornar resultados con scores de similitud

## Tiendas a Implementar (Fase 1)
1. **Amazon.es** - Alta prioridad
2. **PcComponentes** - Alta prioridad
3. **MediaMarkt** - Media prioridad
4. **El Corte Inglés** - Media prioridad

## Estructura de Archivos

```
scrapers/
├── search/
│   ├── BaseSearchScraper.php       # Clase base
│   ├── AmazonSearchScraper.php     # Amazon búsqueda
│   ├── PcComponentesSearchScraper.php
│   ├── MediaMarktSearchScraper.php
│   └── ElCorteInglesSearchScraper.php
│
services/
├── ProductMatcher.php              # Algoritmo de matching
└── AutoCrossSiteSearch.php         # Servicio principal
```

## Flujo de Trabajo

1. Usuario hace clic en "Buscar en otras tiendas"
2. Sistema extrae marca/modelo del producto original
3. Para cada tienda relevante:
   - Genera query de búsqueda
   - Ejecuta scraper de búsqueda
   - Obtiene lista de productos
   - Calcula similitud con producto original
   - Selecciona mejor match (>70% similitud)
4. Añade automáticamente productos encontrados al grupo
5. Muestra comparación de precios

## Consideraciones Técnicas

### Scraping
- Usar Hero para tiendas con JavaScript pesado
- Selenium como fallback
- Rate limiting: 1 búsqueda por segundo por tienda
- Cache de resultados: 1 hora

### Matching
- Levenshtein distance para nombres
- Extracción de números de modelo
- Palabras clave importantes: marca, modelo, pulgadas, etc.
- Score mínimo: 70% para auto-añadir

### Performance
- Búsquedas en paralelo (máx 3 simultáneas)
- Timeout: 30 segundos por tienda
- Mostrar progreso en tiempo real

## Tabla de Base de Datos

Añadir a `product_group_items`:
```sql
ALTER TABLE product_group_items
ADD COLUMN auto_matched BOOLEAN DEFAULT FALSE,
ADD COLUMN match_score DECIMAL(5,2) DEFAULT NULL,
ADD COLUMN match_metadata JSON DEFAULT NULL;
```

## Fases de Implementación

### Fase 1: Core (Esta sesión)
- [x] Crear estructura de carpetas
- [ ] Implementar BaseSearchScraper
- [ ] Implementar AmazonSearchScraper
- [ ] Implementar PcComponentesSearchScraper
- [ ] Crear ProductMatcher básico
- [ ] Modificar API para auto-search
- [ ] Actualizar UI para mostrar progreso

### Fase 2: Expansión
- [ ] Añadir más tiendas
- [ ] Mejorar algoritmo de matching
- [ ] ML para mejorar precisión

### Fase 3: Optimización
- [ ] Cache distribuido
- [ ] Queue system para búsquedas
- [ ] Analytics de precisión
