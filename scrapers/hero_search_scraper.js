#!/usr/bin/env node
/**
 * Ulixee Hero Search Scraper
 * Scraper de búsquedas para evitar detección de bots
 * Uso: node hero_search_scraper.js <store> <searchQuery>
 */

const Hero = require('@ulixee/hero-playground');

class HeroSearchScraper {
    constructor(store, searchQuery) {
        this.store = store.toLowerCase();
        this.searchQuery = searchQuery;
        this.hero = null;
        this.result = {
            success: false,
            error: null,
            products: [],
            store: store
        };
    }

    getSearchUrl() {
        const encoded = encodeURIComponent(this.searchQuery);

        switch(this.store) {
            case 'pccomponentes':
                return `https://www.pccomponentes.com/search/?query=${encoded}`;
            case 'amazon':
                return `https://www.amazon.es/s?k=${encoded}`;
            case 'mediamarkt':
                return `https://www.mediamarkt.es/es/search.html?query=${encoded}`;
            case 'elcorteingles':
            case 'el corte inglés':
                return `https://www.elcorteingles.es/buscar/?s=${encoded}`;
            default:
                throw new Error(`Tienda no soportada: ${this.store}`);
        }
    }

    parsePrice(priceText) {
        try {
            if (!priceText) return null;

            let text = String(priceText).trim();
            text = text.replace(/€/g, '').replace(/\$/g, '').replace(/£/g, '');
            text = text.replace(/\s/g, '').replace(/&nbsp;/g, '');
            text = text.replace(/\./g, '').replace(/–/g, '').replace(/-/g, '');
            text = text.replace(',', '.');

            const priceMatch = text.match(/\d+\.?\d*/);
            if (!priceMatch) return null;

            const price = parseFloat(priceMatch[0]);
            return (isNaN(price) || price <= 0) ? null : price;
        } catch (error) {
            return null;
        }
    }

    async init() {
        try {
            this.hero = new Hero({
                userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
                viewport: {
                    width: 1920,
                    height: 1080,
                    deviceScaleFactor: 1,
                    screenWidth: 1920,
                    screenHeight: 1080
                },
                locale: 'es-ES',
                timezoneId: 'Europe/Madrid',

                // Configuración anti-detección
                showChrome: false,
                showReplay: false,
                connectionToCore: {
                    host: 'localhost'
                },

                // Timeouts
                connectionTimeoutMillis: 30000,
                defaultCommandTimeoutMillis: 30000
            });

            console.error(`🚀 Hero inicializado para búsqueda en ${this.store}`);
        } catch (error) {
            throw new Error(`Error inicializando Hero: ${error.message}`);
        }
    }

    async scrapeSearch() {
        try {
            const searchUrl = this.getSearchUrl();
            console.error(`🔍 Buscando: ${this.searchQuery}`);
            console.error(`📡 URL: ${searchUrl}`);

            await this.hero.goto(searchUrl, {
                waitForMillis: 5000
            });

            // Esperar a que la página esté estable
            await this.hero.waitForPaintingStable();

            // Esperar más tiempo para que React renderice
            console.error('⏳ Esperando a que React renderice los productos...');
            await new Promise(resolve => setTimeout(resolve, 5000));

            // Debug: obtener el HTML para ver qué estructura tiene
            const bodyHtml = await this.hero.document.body.innerHTML;
            console.error(`📄 HTML length: ${bodyHtml.length} chars`);

            let products = [];

            switch(this.store) {
                case 'pccomponentes':
                    products = await this.scrapePcComponentes();
                    break;
                case 'amazon':
                    products = await this.scrapeAmazon();
                    break;
                case 'mediamarkt':
                    products = await this.scrapeMediaMarkt();
                    break;
                case 'elcorteingles':
                case 'el corte inglés':
                    products = await this.scrapeElCorteIngles();
                    break;
                default:
                    throw new Error(`Tienda no soportada: ${this.store}`);
            }

            console.error(`✅ ${products.length} productos encontrados`);

            this.result.success = true;
            this.result.products = products.slice(0, 10); // Máximo 10 productos

        } catch (error) {
            this.result.success = false;
            this.result.error = error.message;
            console.error(`❌ Error: ${error.message}`);
        }
    }

    async scrapePcComponentes() {
        const products = [];

        // Probar múltiples selectores (PcComponentes usa React y estructura dinámica)
        const selectors = [
            'article[id^="article-"]',  // Selector antiguo
            'article[data-id]',          // Article con data-id
            'article',                   // Cualquier article
            '[data-name]',               // Elementos con data-name
            'div[data-testid="product-card"]', // Data testid específico de card
            'a[href*="/p/"]'            // Enlaces de producto (último recurso)
        ];

        let productElements = null;
        let numProducts = 0;

        for (const selector of selectors) {
            productElements = await this.hero.document.querySelectorAll(selector);
            numProducts = await productElements.length;

            if (numProducts > 0) {
                console.error(`✅ Encontrado con selector: ${selector}`);
                break;
            } else {
                console.error(`⚠️  Selector "${selector}" no encontró nada`);
            }
        }

        console.error(`📦 PcComponentes: ${numProducts} elementos encontrados`);

        // Debug: imprimir HTML del primer elemento
        if (numProducts > 0) {
            const firstElement = productElements[0];
            const firstHtml = await firstElement.outerHTML;
            console.error(`\n🔍 DEBUG - HTML del primer elemento (primeros 500 chars):\n${firstHtml.substring(0, 500)}\n`);
        }

        for (let i = 0; i < Math.min(numProducts, 10); i++) {
            try {
                const article = productElements[i];

                // Nombre del producto - múltiples selectores
                let titleElement = await article.querySelector('h3 a');
                if (!titleElement) titleElement = await article.querySelector('h2 a');
                if (!titleElement) titleElement = await article.querySelector('a[data-testid*="name"]');
                if (!titleElement) titleElement = await article.querySelector('a');

                const name = titleElement ? await titleElement.textContent : null;

                // Precio - múltiples estrategias
                let price = null;

                // 1. Intentar data-price
                const priceElement = await article.querySelector('[data-price]');
                if (priceElement) {
                    const priceAttr = await priceElement.getAttribute('data-price');
                    price = parseFloat(priceAttr);
                }

                // 2. Intentar con selectores de clase
                if (!price || price <= 0) {
                    const priceSelectors = [
                        '[data-testid*="price"]',
                        '.price',
                        '[class*="price"]',
                        'span[id*="price"]'
                    ];

                    for (const selector of priceSelectors) {
                        const priceText = await article.querySelector(selector);
                        if (priceText) {
                            const textContent = await priceText.textContent;
                            price = this.parsePrice(textContent);
                            if (price && price > 0) break;
                        }
                    }
                }

                // URL - múltiples selectores
                let urlElement = await article.querySelector('h3 a');
                if (!urlElement) urlElement = await article.querySelector('h2 a');
                if (!urlElement) urlElement = await article.querySelector('a');

                let url = urlElement ? await urlElement.getAttribute('href') : null;
                if (url && !url.startsWith('http')) {
                    url = 'https://www.pccomponentes.com' + url;
                }

                // Imagen
                const imgElement = await article.querySelector('img');
                let image = null;
                if (imgElement) {
                    image = await imgElement.getAttribute('data-src') || await imgElement.getAttribute('src');
                    if (image && !image.startsWith('http')) {
                        image = 'https://www.pccomponentes.com' + image;
                    }
                }

                if (name && price && price > 0 && url) {
                    products.push({
                        name: name.trim(),
                        price: price,
                        url: url,
                        image: image || '',
                        store: 'PcComponentes'
                    });
                    console.error(`✅ Producto ${i+1}: ${name.trim().substring(0, 50)}... - ${price}€`);
                } else {
                    console.error(`⚠️  Producto ${i+1} incompleto - name: ${!!name}, price: ${price}, url: ${!!url}`);
                }
            } catch (error) {
                console.error(`⚠️  Error extrayendo producto ${i}: ${error.message}`);
                continue;
            }
        }

        return products;
    }

    async scrapeAmazon() {
        const products = [];

        // Selector de productos de Amazon
        const productElements = await this.hero.document.querySelectorAll('[data-component-type="s-search-result"]');

        console.error(`📦 Amazon: ${productElements.length} productos encontrados`);

        for (let i = 0; i < Math.min(productElements.length, 10); i++) {
            try {
                const item = productElements[i];

                // Nombre
                const titleElement = await item.querySelector('h2 a span');
                const name = titleElement ? await titleElement.textContent : null;

                // Precio
                const priceWhole = await item.querySelector('.a-price-whole');
                const priceFraction = await item.querySelector('.a-price-fraction');
                let price = null;
                if (priceWhole) {
                    const whole = await priceWhole.textContent;
                    const fraction = priceFraction ? await priceFraction.textContent : '00';
                    price = this.parsePrice(whole + ',' + fraction);
                }

                // URL
                const urlElement = await item.querySelector('h2 a');
                let url = urlElement ? await urlElement.getAttribute('href') : null;
                if (url && !url.startsWith('http')) {
                    url = 'https://www.amazon.es' + url;
                }

                // Limpiar URL de Amazon (solo ASIN)
                if (url) {
                    const asinMatch = url.match(/\/dp\/([A-Z0-9]{10})/);
                    if (asinMatch) {
                        url = `https://www.amazon.es/dp/${asinMatch[1]}`;
                    }
                }

                // Imagen
                const imgElement = await item.querySelector('img');
                const image = imgElement ? await imgElement.getAttribute('src') : null;

                if (name && price && price > 0 && url) {
                    products.push({
                        name: name.trim(),
                        price: price,
                        url: url,
                        image: image || '',
                        store: 'Amazon'
                    });
                }
            } catch (error) {
                console.error(`⚠️  Error extrayendo producto ${i}: ${error.message}`);
                continue;
            }
        }

        return products;
    }

    async scrapeMediaMarkt() {
        const products = [];

        // Varios selectores posibles para MediaMarkt
        let productElements = await this.hero.document.querySelectorAll('article[class*="product"]');

        if (productElements.length === 0) {
            productElements = await this.hero.document.querySelectorAll('[data-test="mms-search-srp-productlist-item"]');
        }

        console.error(`📦 MediaMarkt: ${productElements.length} productos encontrados`);

        for (let i = 0; i < Math.min(productElements.length, 10); i++) {
            try {
                const item = productElements[i];

                // Nombre
                let titleElement = await item.querySelector('h2');
                if (!titleElement) titleElement = await item.querySelector('h3');
                const name = titleElement ? await titleElement.textContent : null;

                // Precio
                const priceElement = await item.querySelector('[class*="price"]');
                const price = priceElement ? this.parsePrice(await priceElement.textContent) : null;

                // URL
                const urlElement = await item.querySelector('a');
                let url = urlElement ? await urlElement.getAttribute('href') : null;
                if (url && !url.startsWith('http')) {
                    url = 'https://www.mediamarkt.es' + url;
                }

                // Imagen
                const imgElement = await item.querySelector('img');
                let image = null;
                if (imgElement) {
                    image = await imgElement.getAttribute('data-src') || await imgElement.getAttribute('src');
                    if (image && !image.startsWith('http') && !image.startsWith('data:')) {
                        image = 'https://www.mediamarkt.es' + image;
                    }
                }

                if (name && price && price > 0 && url) {
                    products.push({
                        name: name.trim(),
                        price: price,
                        url: url,
                        image: image || '',
                        store: 'MediaMarkt'
                    });
                }
            } catch (error) {
                console.error(`⚠️  Error extrayendo producto ${i}: ${error.message}`);
                continue;
            }
        }

        return products;
    }

    async scrapeElCorteIngles() {
        const products = [];

        // Selectores para El Corte Inglés
        let productElements = await this.hero.document.querySelectorAll('article[class*="product"]');

        if (productElements.length === 0) {
            productElements = await this.hero.document.querySelectorAll('li[class*="product"]');
        }

        console.error(`📦 El Corte Inglés: ${productElements.length} productos encontrados`);

        for (let i = 0; i < Math.min(productElements.length, 10); i++) {
            try {
                const item = productElements[i];

                // Nombre
                const titleElement = await item.querySelector('h2 a, h3 a');
                const name = titleElement ? await titleElement.textContent : null;

                // Precio
                let price = null;
                const dataPrice = await item.getAttribute('data-price');
                if (dataPrice) {
                    price = parseFloat(dataPrice);
                } else {
                    const priceElement = await item.querySelector('[class*="price"]');
                    if (priceElement) {
                        price = this.parsePrice(await priceElement.textContent);
                    }
                }

                // URL
                const urlElement = await item.querySelector('a');
                let url = urlElement ? await urlElement.getAttribute('href') : null;
                if (url && !url.startsWith('http')) {
                    url = 'https://www.elcorteingles.es' + url;
                }

                // Imagen
                const imgElement = await item.querySelector('img');
                let image = null;
                if (imgElement) {
                    image = await imgElement.getAttribute('data-src') ||
                           await imgElement.getAttribute('data-original') ||
                           await imgElement.getAttribute('src');
                    if (image && !image.startsWith('http') && !image.startsWith('data:')) {
                        image = 'https://www.elcorteingles.es' + image;
                    }
                }

                if (name && price && price > 0 && url) {
                    products.push({
                        name: name.trim(),
                        price: price,
                        url: url,
                        image: image || '',
                        store: 'El Corte Inglés'
                    });
                }
            } catch (error) {
                console.error(`⚠️  Error extrayendo producto ${i}: ${error.message}`);
                continue;
            }
        }

        return products;
    }

    async close() {
        if (this.hero) {
            await this.hero.close();
        }
    }
}

// Main execution
(async () => {
    if (process.argv.length < 4) {
        console.error('Uso: node hero_search_scraper.js <store> <searchQuery>');
        process.exit(1);
    }

    const store = process.argv[2];
    const searchQuery = process.argv[3];

    const scraper = new HeroSearchScraper(store, searchQuery);

    try {
        await scraper.init();
        await scraper.scrapeSearch();

        // Imprimir resultado JSON en stdout
        console.log(JSON.stringify(scraper.result));
        process.exit(0);

    } catch (error) {
        console.error(`❌ Fatal error: ${error.message}`);
        console.log(JSON.stringify({
            success: false,
            error: error.message,
            products: [],
            store: store
        }));
        process.exit(1);

    } finally {
        await scraper.close();
    }
})();
