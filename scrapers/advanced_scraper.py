#!/usr/bin/env python3
"""
Advanced Scraper - Scraper Python unificado usando Scrapling, Crawlee y Camoufox
Integra múltiples estrategias de scraping con fallback automático.

Uso: python3 advanced_scraper.py <url> [strategy]
  strategy (opcional): scrapling_fetcher, scrapling_stealthy, camoufox, crawlee_playwright
  Si no se especifica, prueba todas las estrategias en orden.

Salida: JSON en stdout (logs en stderr)
"""

import sys
import json
import time
import re
import os
import signal

# ============================================================
# Registro de selectores por tienda
# ============================================================
STORE_SELECTORS = {
    'fnac': {
        'title': [
            'h1.f-productHeader__heading',
            'h1[data-automation-id="product-title-label"]',
            'h1',
        ],
        'price': [
            'span.f-faPriceBox__price.userPrice',
            'span[class*="faPriceBox__price"]:not([class*="striked"])',
            'span[class*="price"]:not([class*="striked"])',
        ],
        'image': [
            'img.f-productMedias__viewItem--main',
            'div[class*="productMedias"] img[src]',
            'img[itemprop="image"]',
        ],
        'original_price': [
            'strong.f-faPriceBox__price--striked',
            'span[class*="price"][class*="striked"]',
        ],
        'discount': [
            'span.f-faPriceBox__stimuliOpcLabel',
            'span[class*="discount"]',
        ],
    },
    'amazon': {
        'title': [
            '#productTitle',
            'h1#title span',
        ],
        'price': [
            'span.a-price-whole',
            '#priceblock_ourprice',
            '#priceblock_dealprice',
            '.a-price .a-offscreen',
        ],
        'image': [
            '#landingImage',
            '#imageBlock img',
        ],
        'original_price': [
            '.a-price.a-text-price span.a-offscreen',
        ],
        'discount': [
            '.savingsPercentage',
        ],
    },
    'pccomponentes': {
        'title': [
            'h1.pdp-title',
            'h1',
        ],
        'price': [
            '#pdp-price-current-integer',
            '[id*="price-current"]',
        ],
        'image': [
            'img[class*="product"]',
            'main img',
        ],
        'original_price': [
            '.crossedOutPrice-i0t4tX',
            '[class*="crossedOut"]',
        ],
        'discount': [
            '.discountText-ZsXk92',
            '[class*="discount"]',
        ],
    },
    'elcorteingles': {
        'title': [
            '#product_detail_title',
            'h1.product-title',
            'h1',
        ],
        'price': [
            '.price-sale',
            '[class*="price"]',
        ],
        'image': [
            'picture img',
            'img[class*="product"]',
        ],
        'original_price': [
            '.price-original',
        ],
        'discount': [
            '.price-discount',
        ],
    },
    'coolmod': {
        'title': ['h1'],
        'price': ['.product-price', '[class*="price"]'],
        'image': ['img[class*="product"]', 'main img'],
        'original_price': ['[class*="old-price"]'],
        'discount': ['[class*="discount"]'],
    },
    'mediamarkt': {
        'title': ['h1[data-test="product-title"]', 'h1'],
        'price': ['[data-test="product-price"]', '[class*="price"]'],
        'image': ['img[data-test="product-image"]', 'main img'],
        'original_price': ['[class*="strike"]'],
        'discount': ['[class*="discount"]', '[class*="saving"]'],
    },
    'zara': {
        'title': ['h1.product-detail-info__header-name', 'h1'],
        'price': ['.price__amount-current', '.price .money-amount__main'],
        'image': ['img.media-image__image', 'picture img'],
        'original_price': ['.price__amount--old'],
        'discount': ['.price__discount'],
    },
    'zalando': {
        'title': ['h1[class*="product"]', 'h1'],
        'price': ['[class*="current-price"]', 'span[class*="price"]'],
        'image': ['img[class*="product"]', 'picture img'],
        'original_price': ['[class*="original-price"]', '[class*="strike"]'],
        'discount': ['[class*="discount"]', '[class*="reduction"]'],
    },
    'ikea': {
        'title': ['h1.pip-header-section__title--big', 'h1'],
        'price': ['.pip-temp-price__integer', '.pip-price__integer'],
        'image': ['img.pip-image', 'img[class*="product"]'],
        'original_price': ['.pip-price__lastchance-price'],
        'discount': ['[class*="discount"]'],
    },
    'temu': {
        'title': ['h1', '[class*="product-title"]'],
        'price': ['[class*="price-current"]', '[class*="sale-price"]'],
        'image': ['img[class*="product"]', 'main img'],
        'original_price': ['[class*="original-price"]'],
        'discount': ['[class*="discount"]'],
    },
    'lego': {
        'title': ['h1[data-test="product-overview-name"]', 'h1'],
        'price': ['[data-test="product-price"]', 'span[class*="Price"]'],
        'image': ['img[data-test="product-image"]', 'picture img'],
        'original_price': ['[class*="list-price"]'],
        'discount': ['[class*="discount"]', '[class*="sale"]'],
    },
    'decathlon': {
        'title': ['h1.product-title', 'h1'],
        'price': ['.product-price__current', '[class*="price"]'],
        'image': ['img[class*="product"]', 'picture img'],
        'original_price': ['.product-price__old'],
        'discount': ['[class*="discount"]', '[class*="percent"]'],
    },
    'mangooutlet': {
        'title': ['h1.product-name', 'h1'],
        'price': ['.product-price .sale', '.product-price'],
        'image': ['img[class*="product"]', 'picture img'],
        'original_price': ['.product-price .original'],
        'discount': ['[class*="discount"]'],
    },
    'mango': {
        'title': ['h1.product-name', 'h1'],
        'price': ['.product-price .sale', '.product-price'],
        'image': ['img[class*="product"]', 'picture img'],
        'original_price': ['.product-price .original'],
        'discount': ['[class*="discount"]'],
    },
    'michaelkors': {
        'title': ['h1.product-name', 'h1'],
        'price': ['.product-price .sale-price', '.product-price'],
        'image': ['img[class*="product"]', 'picture img'],
        'original_price': ['.product-price .original-price'],
        'discount': ['[class*="discount"]'],
    },
    'aliexpress': {
        'title': ['h1[data-pl="product-title"]', 'h1'],
        'price': ['.product-price-current', '[class*="price--current"]'],
        'image': ['img[class*="product"]', 'picture img'],
        'original_price': ['.product-price-origin', '[class*="price--original"]'],
        'discount': ['[class*="discount"]', '[class*="off"]'],
    },
    'consum': {
        'title': ['h1', '.product-name'],
        'price': ['.product-price', '[class*="price"]'],
        'image': ['img[class*="product"]', 'main img'],
        'original_price': [],
        'discount': [],
    },
    'ebay': {
        'title': ['h1.x-item-title__mainTitle', 'h1'],
        'price': ['.x-price-primary span', '#prcIsum', '.notranslate'],
        'image': ['img[class*="image"]', '#icImg'],
        'original_price': ['.x-price-was span'],
        'discount': ['[class*="discount"]'],
    },
}

# Mapa de dominios a nombres de tienda
STORE_MAP = {
    'fnac.es': 'fnac',
    'amazon.es': 'amazon',
    'amazon.com': 'amazon',
    'pccomponentes.com': 'pccomponentes',
    'elcorteingles.es': 'elcorteingles',
    'coolmod.com': 'coolmod',
    'mediamarkt.es': 'mediamarkt',
    'mercadona': 'mercadona',
    'aliexpress': 'aliexpress',
    'consum.es': 'consum',
    'zara.com': 'zara',
    'zalando.es': 'zalando',
    'temu.com': 'temu',
    'lego.com': 'lego',
    'decathlon.es': 'decathlon',
    'mangooutlet.com': 'mangooutlet',
    'michaelkors.es': 'michaelkors',
    'shop.mango.com': 'mango',
    'ikea.com': 'ikea',
    'ebay.es': 'ebay',
    'ebay.com': 'ebay',
}


def detect_store(url):
    """Detecta la tienda según la URL"""
    url_lower = url.lower()
    for domain, store in STORE_MAP.items():
        if domain in url_lower:
            return store
    return 'unknown'


def parse_price(text):
    """Extrae precio de un texto (formato español: 1.234,56€)"""
    if not text:
        return None

    text = text.replace('€', '').replace('EUR', '').replace('$', '').replace('£', '')
    text = text.replace('\xa0', '').replace('&nbsp;', '').strip()
    # Formato español: 1.234,56 -> eliminar separador de miles, coma a punto
    text = text.replace('.', '').replace(',', '.')

    match = re.search(r'(\d+\.?\d*)', text)
    if match:
        try:
            price = float(match.group(1))
            return price if price > 0 else None
        except (ValueError, TypeError):
            return None
    return None


def parse_discount(text):
    """Extrae porcentaje de descuento de un texto"""
    if not text:
        return None

    match = re.search(r'(\d+)\s*%', text)
    if match:
        try:
            return int(match.group(1))
        except (ValueError, TypeError):
            return None
    return None


def normalize_image_url(src, base_domain=''):
    """Normaliza URL de imagen"""
    if not src:
        return None
    if src.startswith('http'):
        return src
    if src.startswith('//'):
        return 'https:' + src
    if base_domain:
        return f'https://{base_domain}{src}'
    return src


# ============================================================
# Estrategia 1: Scrapling Fetcher (HTTP rápido con TLS fingerprint)
# ============================================================
def scrape_with_scrapling_fetcher(url, selectors):
    """Scraping HTTP rápido con TLS fingerprint impersonation de Chrome"""
    from scrapling.fetchers import Fetcher

    print(f"[scrapling_fetcher] Intentando HTTP con TLS impersonation...", file=sys.stderr)

    page = Fetcher.get(
        url,
        stealthy_headers=True,
        follow_redirects=True,
        timeout=15,
    )

    if not page or not page.status == 200:
        status = getattr(page, 'status', 'unknown')
        raise Exception(f"HTTP {status} - Página no accesible")

    return extract_from_page(page, selectors)


# ============================================================
# Estrategia 2: Scrapling StealthyFetcher (bypass Cloudflare)
# ============================================================
def scrape_with_scrapling_stealthy(url, selectors):
    """Scraping con bypass de Cloudflare Turnstile y anti-bot avanzado"""
    from scrapling.fetchers import StealthyFetcher

    print(f"[scrapling_stealthy] Intentando StealthyFetcher con Cloudflare bypass...", file=sys.stderr)

    page = StealthyFetcher.fetch(
        url,
        headless=True,
        solve_cloudflare=True,
        block_webrtc=True,
        hide_canvas=True,
        humanize=True,
        disable_resources=True,
        timeout=45000,
    )

    return extract_from_page(page, selectors)


# ============================================================
# Estrategia 3: Camoufox (anti-detección C++ en Firefox)
# ============================================================
def scrape_with_camoufox(url, selectors):
    """Scraping con Camoufox - anti-detección a nivel C++ de Firefox"""
    import os
    import tempfile

    print(f"[camoufox] Intentando Camoufox (Firefox anti-detect)...", file=sys.stderr)

    # IMPORTANTE: Configurar variables de entorno ANTES de importar Camoufox
    # Crear directorio temporal en /var/www/ para perfiles de Firefox
    temp_profile_base = '/var/www/.cache/camoufox/tmp'
    os.makedirs(temp_profile_base, exist_ok=True)

    # Redirigir TMPDIR para que Playwright cree perfiles aquí
    os.environ['HOME'] = '/var/www'
    os.environ['TMPDIR'] = temp_profile_base
    os.environ['TEMP'] = temp_profile_base
    os.environ['TMP'] = temp_profile_base

    # Deshabilitar glxtest y verificación de GPU para evitar errores en Docker
    os.environ['MOZ_DISABLE_GMP_SANDBOX'] = '1'
    os.environ['MOZ_DISABLE_CONTENT_SANDBOX'] = '1'
    os.environ['MOZ_HEADLESS'] = '1'
    os.environ['LIBGL_ALWAYS_SOFTWARE'] = '1'

    # Ahora sí importar Camoufox (después de configurar variables)
    from camoufox.sync_api import Camoufox

    # Crear directorio temporal para el perfil en /var/www/
    profile_dir = tempfile.mkdtemp(prefix='camoufox_profile_', dir=temp_profile_base)

    firefox_args = [
        '--no-remote',
        '-headless',
        '--disable-gpu',
    ]

    try:
        with Camoufox(headless=True, addons=[], args=firefox_args, user_data_dir=profile_dir) as browser:
            page = browser.new_page()
            page.goto(url, timeout=45000, wait_until='domcontentloaded')

            # Esperar a que la red esté estable
            try:
                page.wait_for_load_state('networkidle', timeout=15000)
            except Exception:
                print("[camoufox] Timeout en networkidle, continuando...", file=sys.stderr)

            # Scroll para activar lazy loading
            page.mouse.wheel(0, 300)
            page.wait_for_timeout(1500)
            page.mouse.wheel(0, -300)
            page.wait_for_timeout(1000)

            html = page.content()

        # Parsear HTML con Scrapling para selectores consistentes
        from scrapling.parser import Adaptor
        parsed = Adaptor(html, url=url)
        return extract_from_page(parsed, selectors)
    finally:
        # Limpiar directorio temporal de perfil
        import shutil
        try:
            shutil.rmtree(profile_dir)
        except Exception:
            pass


# ============================================================
# Estrategia 4: Crawlee PlaywrightCrawler (sesiones + proxy)
# ============================================================
def scrape_with_crawlee(url, selectors):
    """Scraping con Crawlee PlaywrightCrawler - session pool y anti-blocking"""
    import asyncio

    async def _crawlee_scrape():
        from crawlee.crawlers import PlaywrightCrawler, PlaywrightCrawlingContext

        result_data = {}

        crawler = PlaywrightCrawler(
            max_requests_per_crawl=1,
            headless=True,
            browser_type='chromium',
        )

        @crawler.router.default_handler
        async def handler(context: PlaywrightCrawlingContext) -> None:
            nonlocal result_data
            await context.page.wait_for_load_state('networkidle')

            # Scroll para lazy loading
            await context.page.mouse.wheel(0, 300)
            await context.page.wait_for_timeout(1500)

            html = await context.page.content()

            from scrapling.parser import Adaptor
            parsed = Adaptor(html, url=url)
            result_data = extract_from_page(parsed, selectors)

        print(f"[crawlee_playwright] Intentando Crawlee PlaywrightCrawler...", file=sys.stderr)
        await crawler.run([url])
        return result_data

    return asyncio.run(_crawlee_scrape())


# ============================================================
# Extracción genérica desde página parseada
# ============================================================
def extract_from_page(page, selectors):
    """Extrae datos de producto usando los selectores de la tienda"""
    result = {
        'title': None,
        'price': None,
        'image': None,
        'original_price': None,
        'discount': None,
    }

    # Extraer título
    for sel in selectors.get('title', []):
        try:
            elem = page.css_first(sel)
            if elem:
                text = elem.text
                if text and text.strip() and len(text.strip()) > 3:
                    result['title'] = text.strip()
                    print(f"  [title] Encontrado con '{sel}': {result['title'][:60]}", file=sys.stderr)
                    break
        except Exception as e:
            print(f"  [title] Error con '{sel}': {e}", file=sys.stderr)
            continue

    # Extraer precio
    for sel in selectors.get('price', []):
        try:
            elem = page.css_first(sel)
            if elem:
                text = elem.text
                price = parse_price(text)
                if price:
                    result['price'] = price
                    print(f"  [price] Encontrado con '{sel}': {price}", file=sys.stderr)
                    break
        except Exception as e:
            print(f"  [price] Error con '{sel}': {e}", file=sys.stderr)
            continue

    # Extraer imagen
    for sel in selectors.get('image', []):
        try:
            elem = page.css_first(sel)
            if elem:
                src = elem.attrib.get('src') or elem.attrib.get('data-src')
                if src:
                    result['image'] = normalize_image_url(src)
                    print(f"  [image] Encontrada con '{sel}'", file=sys.stderr)
                    break
        except Exception as e:
            print(f"  [image] Error con '{sel}': {e}", file=sys.stderr)
            continue

    # Extraer precio original
    for sel in selectors.get('original_price', []):
        try:
            elem = page.css_first(sel)
            if elem:
                text = elem.text
                orig_price = parse_price(text)
                if orig_price and (not result['price'] or orig_price > result['price']):
                    result['original_price'] = orig_price
                    print(f"  [original_price] Encontrado con '{sel}': {orig_price}", file=sys.stderr)
                    break
        except Exception:
            continue

    # Extraer descuento
    for sel in selectors.get('discount', []):
        try:
            elem = page.css_first(sel)
            if elem:
                text = elem.text
                discount = parse_discount(text)
                if discount:
                    result['discount'] = discount
                    print(f"  [discount] Encontrado con '{sel}': {discount}%", file=sys.stderr)
                    break
        except Exception:
            continue

    # Calcular descuento si tenemos ambos precios pero no descuento
    if result['original_price'] and result['price'] and not result['discount']:
        discount_pct = round(
            ((result['original_price'] - result['price']) / result['original_price']) * 100
        )
        if discount_pct > 0:
            result['discount'] = discount_pct
            print(f"  [discount] Calculado: {discount_pct}%", file=sys.stderr)

    # Fallback: intentar Schema.org JSON-LD para precio
    if not result['price']:
        try:
            scripts = page.css('script[type="application/ld+json"]')
            for script in scripts:
                try:
                    data = json.loads(script.text)
                    if isinstance(data, list):
                        data = data[0]
                    # Buscar en offers
                    offers = data.get('offers', data.get('Offers', {}))
                    if isinstance(offers, list):
                        offers = offers[0]
                    price_val = offers.get('price') or offers.get('lowPrice')
                    if price_val:
                        result['price'] = float(price_val)
                        print(f"  [price] Encontrado via JSON-LD: {result['price']}", file=sys.stderr)
                    # Nombre del producto
                    if not result['title']:
                        name = data.get('name')
                        if name:
                            result['title'] = name
                            print(f"  [title] Encontrado via JSON-LD: {result['title'][:60]}", file=sys.stderr)
                    # Imagen
                    if not result['image']:
                        img = data.get('image')
                        if isinstance(img, list):
                            img = img[0]
                        if isinstance(img, str):
                            result['image'] = normalize_image_url(img)
                        elif isinstance(img, dict):
                            result['image'] = normalize_image_url(img.get('url', ''))
                except (json.JSONDecodeError, TypeError, KeyError):
                    continue
        except Exception:
            pass

    # Fallback: Open Graph meta tags
    if not result['price'] or not result['title']:
        try:
            if not result['title']:
                og_title = page.css_first('meta[property="og:title"]')
                if og_title:
                    result['title'] = og_title.attrib.get('content', '').strip()
            if not result['image']:
                og_image = page.css_first('meta[property="og:image"]')
                if og_image:
                    result['image'] = normalize_image_url(og_image.attrib.get('content', ''))
            if not result['price']:
                og_price = page.css_first('meta[property="product:price:amount"]')
                if og_price:
                    result['price'] = parse_price(og_price.attrib.get('content', ''))
        except Exception:
            pass

    return result


# ============================================================
# Estrategias disponibles (orden: menor a mayor coste)
# ============================================================
STRATEGIES = {
    'scrapling_fetcher': scrape_with_scrapling_fetcher,
    'scrapling_stealthy': scrape_with_scrapling_stealthy,
    'camoufox': scrape_with_camoufox,
    'crawlee_playwright': scrape_with_crawlee,
}

STRATEGY_ORDER = ['scrapling_fetcher', 'scrapling_stealthy', 'camoufox', 'crawlee_playwright']


def main():
    if len(sys.argv) < 2:
        print(json.dumps({'success': False, 'error': 'URL no proporcionada'}))
        sys.exit(1)

    url = sys.argv[1]
    requested_strategy = sys.argv[2] if len(sys.argv) > 2 else None

    store = detect_store(url)
    selectors = STORE_SELECTORS.get(store, {})

    print(f"URL: {url}", file=sys.stderr)
    print(f"Tienda detectada: {store}", file=sys.stderr)
    print(f"Selectores disponibles: {bool(selectors)}", file=sys.stderr)

    # Si no hay selectores para la tienda, usar selectores genéricos
    if not selectors:
        selectors = {
            'title': ['h1', 'meta[property="og:title"]'],
            'price': ['[class*="price"]', '[itemprop="price"]'],
            'image': ['meta[property="og:image"]', 'img[class*="product"]'],
            'original_price': ['[class*="original"]', '[class*="strike"]'],
            'discount': ['[class*="discount"]', '[class*="saving"]'],
        }

    # Determinar qué estrategias ejecutar
    if requested_strategy:
        if requested_strategy not in STRATEGIES:
            print(json.dumps({
                'success': False,
                'error': f'Estrategia desconocida: {requested_strategy}. Disponibles: {", ".join(STRATEGY_ORDER)}'
            }))
            sys.exit(1)
        strategies_to_try = [requested_strategy]
    else:
        strategies_to_try = STRATEGY_ORDER

    # Ejecutar estrategias con fallback
    last_error = None
    for strategy_name in strategies_to_try:
        strategy_fn = STRATEGIES[strategy_name]
        start_time = time.time()

        try:
            print(f"\n{'='*50}", file=sys.stderr)
            print(f"Estrategia: {strategy_name}", file=sys.stderr)
            print(f"{'='*50}", file=sys.stderr)

            result = strategy_fn(url, selectors)

            elapsed = round((time.time() - start_time) * 1000)
            print(f"Tiempo: {elapsed}ms", file=sys.stderr)

            if result and result.get('price') and result['price'] > 0:
                output = {
                    'success': True,
                    'title': result.get('title'),
                    'price': result['price'],
                    'image': result.get('image'),
                    'discount': result.get('discount'),
                    'original_price': result.get('original_price'),
                    'store': store,
                    'method': f'advanced_{strategy_name}',
                    'extraction_time_ms': elapsed,
                }
                print(f"\nExito con {strategy_name}! Precio: {result['price']}", file=sys.stderr)
                print(json.dumps(output, ensure_ascii=False))
                sys.exit(0)
            else:
                last_error = f"{strategy_name}: No se extrajo precio válido"
                print(f"Sin precio válido, probando siguiente estrategia...", file=sys.stderr)

        except Exception as e:
            elapsed = round((time.time() - start_time) * 1000)
            last_error = f"{strategy_name}: {str(e)}"
            print(f"Error ({elapsed}ms): {e}", file=sys.stderr)
            continue

    # Todas las estrategias fallaron
    print(json.dumps({
        'success': False,
        'error': f'Todas las estrategias fallaron. Último error: {last_error}',
        'store': store,
    }, ensure_ascii=False))
    sys.exit(1)


if __name__ == '__main__':
    main()
