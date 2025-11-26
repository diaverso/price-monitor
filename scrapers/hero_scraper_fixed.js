#!/usr/bin/env node
/**
 * Ulixee Hero Scraper - Bypass Akamai Bot Manager
 * Scraper avanzado para sitios protegidos con Akamai (El Corte Inglés)
 */

const Hero = require('@ulixee/hero-playground');

class HeroScraper {
    constructor(url) {
        this.url = url;
        this.hero = null;
        this.result = {
            success: false,
            error: null,
            title: null,
            price: null,
            image: null,
            discount: null,
            original_price: null,
            store: this.detectStore(url)
        };
    }

    detectStore(url) {
        if (url.includes('amazon')) return 'amazon';
        if (url.includes('pccomponentes')) return 'pccomponentes';
        if (url.includes('elcorteingles')) return 'elcorteingles';
        if (url.includes('coolmod')) return 'coolmod';
        if (url.includes('mediamarkt')) return 'mediamarkt';
        if (url.includes('mercadona')) return 'mercadona';
        if (url.includes('aliexpress')) return 'aliexpress';
        if (url.includes('consum')) return 'consum';
        if (url.includes('zara.com')) return 'zara';
        if (url.includes('zalando.es')) return 'zalando';
        if (url.includes('temu.com')) return 'temu';
        if (url.includes('lego.com')) return 'lego';
        if (url.includes('decathlon.es')) return 'decathlon';
        if (url.includes('mangooutlet.com')) return 'mangooutlet';
        if (url.includes('michaelkors.es')) return 'michaelkors';
        if (url.includes('shop.mango.com')) return 'mango';
        if (url.includes('ikea.com')) return 'ikea';
        return 'unknown';
    }

    parsePrice(priceText) {
        try {
            if (!priceText) return null;

            let text = String(priceText).trim();
            text = text.replace(/€/g, '').replace(/$/g, '').replace(/£/g, '');
            text = text.replace(/\s/g, '').replace(/&nbsp;/g, '');
            text = text.replace(/\./g, '').replace(/–/g, '').replace(/-/g, '');
            text = text.replace(',', '.');

            const priceMatch = text.match(/\d+\.?\d*/);
            if (!priceMatch) return null;

            const price = parseFloat(priceMatch[0]);
            return (isNaN(price) || price <= 0) ? null : price;
        } catch (error) {
            console.error('⚠️  Error parseando precio:', error.message);
            return null;
        }
    }

    parseDiscount(discountText) {
        try {
            if (!discountText) return null;
            
            const text = String(discountText).trim();
            const match = text.match(/(\d+)\s*%/);
            if (match) {
                const discount = parseInt(match[1]);
                return (isNaN(discount) || discount <= 0) ? null : discount;
            }
            return null;
        } catch (error) {
            console.error('⚠️  Error parseando descuento:', error.message);
            return null;
        }
    }
    async init() {
        try {
            // Inicializar Hero con configuración optimizada para bypass
            this.hero = new Hero({
                // Usar emulación de navegador real
                userAgent: 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',

                // Viewport realista
                viewport: {
                    width: 1920,
                    height: 1080,
                    deviceScaleFactor: 1,
                    screenWidth: 1920,
                    screenHeight: 1080
                },

                // Configuración para parecer más humano
                locale: 'es-ES',
                timezoneId: 'Europe/Madrid',

                // Deshabilitar detección de automatización
                blockedResourceTypes: [], // No bloquear nada para parecer más real

                // Deshabilitar sandbox (necesario cuando se ejecuta como root/www-data)
                noChromeSandbox: true,

                // Flags adicionales de Chrome para Docker
                showChrome: false,
                showChromeInteractions: false
            });

            console.error('✓ Hero inicializado correctamente');
        } catch (error) {
            console.error('✗ Error inicializando Hero:', error.message);
            throw error;
        }
    }

    async scrape() {
        try {
            await this.init();

            console.error(`📡 Navegando a: ${this.url}`);
            await this.hero.goto(this.url);

            // Esperar a que la página cargue completamente
            console.error('⏳ Esperando carga completa...');
            await this.hero.waitForPaintingStable();

            // Ejecutar extracción según tienda
            if (this.result.store === 'elcorteingles') {
                await this.extractElCorteIngles();
            } else if (this.result.store === 'amazon') {
                await this.extractAmazon();
            } else if (this.result.store === 'pccomponentes') {
                await this.extractPcComponentes();
            } else if (this.result.store === 'coolmod') {
                await this.extractCoolmod();
            } else if (this.result.store === 'mediamarkt') {
                await this.extractMediaMarkt();
            } else if (this.result.store === 'mercadona') {
                await this.extractMercadona();
            } else if (this.result.store === 'aliexpress') {
                await this.extractAliExpress();
            } else if (this.result.store === 'consum') {
                await this.extractConsum();
            } else if (this.result.store === 'zara') {
                await this.extractZara();
            } else if (this.result.store === 'zalando') {
                await this.extractZalando();
            } else if (this.result.store === 'temu') {
                await this.extractTemu();
            } else if (this.result.store === 'lego') {
                await this.extractLego();
            } else if (this.result.store === 'decathlon') {
                await this.extractDecathlon();
            } else if (this.result.store === 'mangooutlet') {
                await this.extractMangoOutlet();
            } else if (this.result.store === 'michaelkors') {
                await this.extractMichaelKors();
            } else if (this.result.store === 'mango') {
                await this.extractMango();
            } else if (this.result.store === 'ikea') {
                await this.extractIkea();
            } else {
                this.result.error = 'Tienda no soportada';
                return this.result;
            }

            // Validar que tengamos al menos precio
            if (this.result.price && this.result.price > 0) {
                this.result.success = true;
                console.error('✓ Datos extraídos exitosamente');
            } else {
                // Verificar si el producto no está disponible
                if (!this.result.title && !this.result.image && !this.result.price) {
                    this.result.error = 'Producto no disponible o ya no existe en la tienda';
                    console.error('✗ Producto no disponible');
                } else {
                    this.result.error = 'No se pudo extraer el precio';
                    console.error('✗ No se pudo extraer el precio');
                }
            }

        } catch (error) {
            this.result.error = `Error: ${error.message}`;
            console.error('✗ Error en scraping:', error.message);
        } finally {
            if (this.hero) {
                await this.hero.close();
            }
        }

        return this.result;
    }

    async extractElCorteIngles() {
        console.error('🛍️  Extrayendo datos de El Corte Inglés...');

        try {
            // Esperar más tiempo para Akamai
            await this.hero.waitForMillis(8000);
            await this.hero.waitForPaintingStable();

            // Verificar si hay challenge de Akamai
            try {
                const bodyElement = await this.hero.document.body;
                if (bodyElement) {
                    const pageText = await bodyElement.textContent;
                    if (pageText && (pageText.includes('Access Denied') || pageText.includes('akam'))) {
                        console.error('⚠️  Detectada protección Akamai, esperando resolución...');
                        await this.hero.waitForMillis(10000);
                    }
                        await this.hero.waitForPaintingStable();
                }
            } catch (e) {
                console.error('⚠️  No se pudo verificar body:', e.message);
                // Continuar de todos modos
            }

            // Extraer título
            try {
                const titleElement = await this.hero.document.querySelector('#product_detail_title');
                if (titleElement) {
                    this.result.title = await titleElement.textContent;
                    this.result.title = this.result.title.trim();
                    console.error(`✓ Título: ${this.result.title.substring(0, 50)}...`);
                } else {
                    // Fallback: cualquier h1
                    const h1 = await this.hero.document.querySelector('h1');
                    if (h1) {
                        this.result.title = (await h1.textContent).trim();
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer título:', e.message);
            }

            // Extraer precio actual con descuento
            try {
                const priceElement = await this.hero.document.querySelector('span.price-sale[aria-label="Precio de venta"]');
                if (priceElement) {
                    const priceText = await priceElement.textContent;
                    this.result.price = this.parsePrice(priceText);
                    console.error(`✓ Precio: €${this.result.price}`);
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer precio:', e.message);
            }

            // Extraer precio original
            try {
                const originalPriceElement = await this.hero.document.querySelector('span.price-unit--original[aria-label="Precio original"]');
                if (originalPriceElement) {
                    const originalPriceText = await originalPriceElement.textContent;
                    this.result.original_price = this.parsePrice(originalPriceText);
                    console.error(`✓ Precio original: €${this.result.original_price}`);
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer precio original:', e.message);
            }

            // Extraer descuento
            try {
                const discountElement = await this.hero.document.querySelector('span.price-discount[aria-label="Descuento"]');
                if (discountElement) {
                    const discountText = await discountElement.textContent;
                    this.result.discount = this.parseDiscount(discountText);
                    console.error(`✓ Descuento: ${this.result.discount}%`);
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer descuento:', e.message);
            }

            // Extraer imagen
            try {
                const pictureImg = await this.hero.document.querySelector('picture img');
                if (pictureImg) {
                    this.result.image = await pictureImg.src;
                    console.error(`✓ Imagen encontrada`);
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer imagen:', e.message);
            }

        } catch (error) {
            console.error('✗ Error extrayendo El Corte Inglés:', error.message);
            throw error;
        }
    }

    async extractAmazon() {
        console.error('🛍️  Extrayendo datos de Amazon...');

        try {
            // Título
            const titleElem = await this.hero.document.querySelector('#productTitle');
            if (titleElem) {
                this.result.title = (await titleElem.textContent).trim();
            }

            // Precio
            const priceSelectors = [
                '.a-price-whole',
                '.a-price .a-offscreen',
                '#priceblock_ourprice',
                '#priceblock_dealprice'
            ];

            for (const selector of priceSelectors) {
                try {
                    const elem = await this.hero.document.querySelector(selector);
                    if (elem) {
                        const text = await elem.textContent;
                        const price = this.parsePrice(text);
                        if (price > 0) {
                            this.result.price = price;
                            break;
                        }
                    }
                } catch (e) {
                    continue;
                }
            }

            // Imagen
            const imgElem = await this.hero.document.querySelector('#landingImage');
            if (imgElem) {
                this.result.image = await imgElem.src;
            }

            // Descuento
            const discountElem = await this.hero.document.querySelector('.savingsPercentage');
            if (discountElem) {
                const text = await discountElem.textContent;
                this.result.discount = this.parseDiscount(text);
            }

        } catch (error) {
            console.error('✗ Error extrayendo Amazon:', error.message);
        }
    }

    async extractPcComponentes() {
        console.error('🛍️  Extrayendo datos de PcComponentes...');

        try {
            // Esperar carga y posible Cloudflare challenge
            await this.hero.waitForMillis(8000);
            await this.hero.waitForPaintingStable();

            // Verificar si hay Cloudflare challenge
            try {
                const bodyElement = await this.hero.document.body;
                if (bodyElement) {
                    const pageText = await bodyElement.textContent;
                    if (pageText && pageText.includes('Just a moment')) {
                        console.error('⚠️  Detectada protección Cloudflare, esperando resolución...');
                        await this.hero.waitForMillis(20000);
                        await this.hero.waitForPaintingStable();
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo verificar body:', e.message);
            }

            // Título
            try {
                const titleElem = await this.hero.document.querySelector('h1');
                if (titleElem) {
                    this.result.title = (await titleElem.textContent).trim();
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer título:', e.message);
            }

            // Precio actual - Obtener integer + decimals
            try {
                const priceIntegerElem = await this.hero.document.querySelector('#pdp-price-current-integer');
                if (priceIntegerElem) {
                    const fullPriceText = await priceIntegerElem.textContent;
                    this.result.price = this.parsePrice(fullPriceText);
                    if (this.result.price) {
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer precio:', e.message);
            }

            // Precio original (tachado)
            try {
                const originalPriceElem = await this.hero.document.querySelector('#pdp-price-original');
                if (originalPriceElem) {
                    const originalPriceText = await originalPriceElem.textContent;
                    this.result.original_price = this.parsePrice(originalPriceText);
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer precio original:', e.message);
            }

            // Descuento
            try {
                const discountElem = await this.hero.document.querySelector('#pdp-price-discount');
                if (discountElem) {
                    const discountText = await discountElem.textContent;
                    const discountMatch = discountText.match(/\((-?\d+)%\)/);
                    if (discountMatch) {
                        this.result.discount = Math.abs(parseInt(discountMatch[1]));
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer descuento:', e.message);
            }

            // Imagen
            try {
                const imgElem = await this.hero.document.querySelector('.swiperImage-wX1AA4');
                if (imgElem) {
                    let imgSrc = await imgElem.src;
                    // Si la URL empieza con //, agregar https:
                    if (imgSrc && imgSrc.startsWith('//')) {
                        imgSrc = 'https:' + imgSrc;
                    }
                    this.result.image = imgSrc;
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer imagen:', e.message);
            }

        } catch (error) {
            console.error('✗ Error extrayendo PcComponentes:', error.message);
        }
    }


    async extractCoolmod() {
        console.error('🛍️  Extrayendo datos de Coolmod...');

        try {
            // Esperar carga
            await this.hero.waitForMillis(2000);

            // Título - h1 genérico funciona
            try {
                const titleElem = await this.hero.document.querySelector('h1');
                if (titleElem) {
                    this.result.title = (await titleElem.textContent).trim();
                    console.error(`✓ Título: ${this.result.title.substring(0, 50)}...`);
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer título:', e.message);
            }

            // Precio - <span class="product_price int_price">
            try {
                const priceIntElem = await this.hero.document.querySelector('.product_price.int_price');
                const priceDecElem = await this.hero.document.querySelector('.dec_price');

                if (priceIntElem) {
                    const priceInt = await priceIntElem.textContent;
                    // Remover puntos de miles (ej: "1.399" → "1399")
                    let priceStr = priceInt.trim().replace(/\./g, '');

                    if (priceDecElem) {
                        const priceDec = await priceDecElem.textContent;
                        // Usar punto como separador decimal (ej: "1399" + "." + "95" → "1399.95")
                        priceStr += '.' + priceDec.trim();
                    }

                    this.result.price = parseFloat(priceStr);
                    console.error(`✓ Precio: €${this.result.price}`);
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer precio:', e.message);
            }


            // Precio original (tachado) - <p class="text-neutral-content line-through text-lg price-old-product">
            try {
                const originalPriceElem = await this.hero.document.querySelector('.price-old-product');
                if (originalPriceElem) {
                    const originalPriceText = await originalPriceElem.textContent;
                    // Limpiar texto y extraer número (ej: "819,95 €" → 819.95)
                    const cleanPrice = originalPriceText.replace('€', '').replace(/\s/g, '').trim();
                    const priceNum = cleanPrice.replace(/\./g, '').replace(',', '.');
                    this.result.original_price = parseFloat(priceNum);
                }
            } catch (e) {
                console.error('ℹ️  No hay precio original');
            }

            // Imagen - buscar primera imagen del producto
            try {
                // Las imágenes del producto tienen URLs como: /images/product/large/PROD-xxxxx_1.jpg
                const imgs = await this.hero.document.querySelectorAll('img[src*="/images/product/large/"]');
                const imgCount = await imgs.length;
                if (imgs && imgCount > 0) {
                    let imgSrc = await imgs[0].src;
                    // Si es relativa, agregar dominio
                    if (imgSrc && imgSrc.startsWith('/')) {
                        imgSrc = 'https://www.coolmod.com' + imgSrc;
                    }
                    // Filtrar solo .jpg, .png, .webp
                    if (imgSrc && (imgSrc.includes('.jpg') || imgSrc.includes('.png') || imgSrc.includes('.webp'))) {
                        this.result.image = imgSrc;
                        console.error(`✓ Imagen encontrada`);
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer imagen:', e.message);
            }

            // Descuento - opcional (puede no existir)
            try {
                // Buscar elementos con descuento/ahorro
                const discountElems = await this.hero.document.querySelectorAll('[class*="discount"], [class*="ahorro"]');
                for (const elem of discountElems) {
                    const text = await elem.textContent;
                    if (text && text.includes('%')) {
                        const discountMatch = text.match(/(\d+)\s*%/);
                        if (discountMatch) {
                            this.result.discount = parseInt(discountMatch[1]);
                            console.error(`✓ Descuento: ${this.result.discount}%`);
                            break;
                        }
                    }
                }
            } catch (e) {
                // No pasa nada si no hay descuento
                console.error('ℹ️  No hay descuento');

            }

            // Calcular descuento automáticamente si hay precio original pero no descuento
            if (this.result.original_price && this.result.price && !this.result.discount) {
                const discountPercent = Math.round(
                    ((this.result.original_price - this.result.price) / this.result.original_price) * 100
                );
                if (discountPercent > 0) {
                    this.result.discount = discountPercent;
                    console.error(`✓ Descuento calculado: ${this.result.discount}%`);
                }
            }

        } catch (error) {
            console.error('✗ Error extrayendo Coolmod:', error.message);
        }
    }
    async extractMediaMarkt() {
        console.error('🛒 Extrayendo datos de MediaMarkt...');

        try {
            // Esperar carga
            await this.hero.waitForMillis(3000);

            // Título - h1
            try {
                const titleElem = await this.hero.document.querySelector('h1');
                if (titleElem) {
                    this.result.title = (await titleElem.textContent).trim();
                    console.error(`✓ Título: ${this.result.title.substring(0, 50)}...`);
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer título:', e.message);
            }

            // Precio - data-test="branded-price-whole-value" y "branded-price-decimal-value"
            try {
                const priceWholeElem = await this.hero.document.querySelector('[data-test="branded-price-whole-value"]');
                const priceDecElem = await this.hero.document.querySelector('[data-test="branded-price-decimal-value"]');

                if (priceWholeElem) {
                    const priceWhole = await priceWholeElem.textContent;
                    // Remover la coma final (ej: "849," → "849")
                    let priceStr = priceWhole.trim().replace(/,\s*$/, '');

                    if (priceDecElem) {
                        const priceDec = await priceDecElem.textContent;
                        const decPart = priceDec.trim();
                        // Si el decimal no es "–" (guión), agregarlo
                        if (decPart && decPart !== '–' && decPart !== '-') {
                            priceStr += '.' + decPart;
                        }
                    }

                    this.result.price = parseFloat(priceStr);
                    console.error(`✓ Precio: €${this.result.price}`);
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer precio:', e.message);
            }

            // Imagen - .pdp-gallery-image (primera imagen)
            try {
                const imgs = await this.hero.document.querySelectorAll('img.pdp-gallery-image');
                const imgCount = await imgs.length;
                if (imgs && imgCount > 0) {
                    let imgSrc = await imgs[0].src;
                    if (imgSrc) {
                        this.result.image = imgSrc;
                        console.error(`✓ Imagen encontrada`);
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer imagen:', e.message);
            }

             // Precio original tachado - span.sc-94eb08bc-0.ftZQvh
            try {
                const originalPriceElem = await this.hero.document.querySelector('span.sc-94eb08bc-0.ftZQvh[aria-hidden="true"]');
                if (originalPriceElem) {
                    const text = await originalPriceElem.textContent;
                    if (text && text.includes('€')) {
                        const cleanPrice = text.replace(/€/g, '').replace(/\s/g, '').replace(/&nbsp;/g, '').trim();
                        const priceNum = cleanPrice.replace(/\./g, '').replace(/–/g, '').replace(/-/g, '').replace(',', '.');
                        const parsedPrice = parseFloat(priceNum);
                        if (!isNaN(parsedPrice) && parsedPrice > 0) {
                            this.result.original_price = parsedPrice;
                            console.error(`✓ Precio original: €${this.result.original_price}`);
                        }
                    }
                }
            } catch (e) {
                console.error('ℹ️  No hay precio original');
            }

            // Descuento - div.sc-5ee25a63-0.jqlBmB span
            try {
                const discountElem = await this.hero.document.querySelector('div.sc-5ee25a63-0.jqlBmB span');
                if (discountElem) {
                    const text = await discountElem.textContent;
                    if (text && text.includes('%')) {
                        const discountMatch = text.match(/(\d+)\s*%/);
                        if (discountMatch) {
                            this.result.discount = parseInt(discountMatch[1]);
                            console.error(`✓ Descuento: ${this.result.discount}%`);
                        }
                    }
                }
            } catch (e) {
                console.error('ℹ️  No hay descuento');
            }

            // Calcular descuento automáticamente si hay precio original pero no descuento
            if (this.result.original_price && this.result.price && !this.result.discount) {
                const discountPercent = Math.round(
                    ((this.result.original_price - this.result.price) / this.result.original_price) * 100
                );
                if (discountPercent > 0) {
                    this.result.discount = discountPercent;
                    console.error(`✓ Descuento calculado: ${this.result.discount}%`);
                }
            }

        } catch (error) {
            console.error('✗ Error extrayendo MediaMarkt:', error.message);
        }
    }

    async extractMercadona() {
        console.error('🛒 Extrayendo datos de Mercadona...');

        try {
            // Mercadona requiere código postal - intentar ingresar uno automáticamente
            await this.hero.waitForMillis(3000);

            // Verificar si pide código postal
            try {
                const postalInput = await this.hero.document.querySelector('input[data-testid="postal-code-checker-input"]');
                if (postalInput) {
                    console.error('⏳ Ingresando código postal...');
                    await this.hero.interact({ click: postalInput });
                    await this.hero.waitForMillis(500);
                    await this.hero.type('28001'); // Madrid
                    console.error('✓ Código postal: 28001');

                    await this.hero.waitForMillis(1000);

                    // Buscar y hacer click en el botón "Entrar"
                    const buttons = await this.hero.document.querySelectorAll('button[data-testid="button"]');
                    const btnCount = await buttons.length;

                    for (let i = 0; i < btnCount; i++) {
                        const btnText = await buttons[i].textContent;
                        if (btnText && btnText.includes('Entrar')) {
                            console.error('✓ Haciendo click en botón Entrar');
                            await this.hero.interact({ click: buttons[i] });
                            break;
                        }
                    }

                    // Esperar a que cargue la página con precios
                    console.error('⏳ Esperando carga de precios...');
                    await this.hero.waitForMillis(8000);
                    await this.hero.waitForPaintingStable();
                }
            } catch (e) {
                console.error('ℹ️  No requiere código postal o ya está configurado');
            }

            // Título - h1.title2-b.private-product-detail__description
            try {
                const titleElem = await this.hero.document.querySelector("h1.title2-b.private-product-detail__description");
                if (titleElem) {
                    const title = await titleElem.textContent;
                    if (title && title.trim()) {
                        this.result.title = title.trim();
                    }
                }
            } catch (e) {
                console.error("⚠️  No se pudo extraer título:", e.message);
            }

            // Título - h1.title2-b.private-product-detail__description
            try {
                const titleElem = await this.hero.document.querySelector("h1.title2-b.private-product-detail__description");
                if (titleElem) {
                    const title = await titleElem.textContent;
                    if (title && title.trim()) {
                        this.result.title = title.trim();
                    }
                }
            } catch (e) {
                console.error("⚠️  No se pudo extraer título:", e.message);
            }


            // Imagen
            try {
                const imgSelectors = [
                    'img.product-detail-image__image',
                    'img[data-testid="product-image"]',
                    '.product-detail-image img'
                ];

                for (const selector of imgSelectors) {
                    try {
                        const imgElem = await this.hero.document.querySelector(selector);
                        if (imgElem) {
                            const imgSrc = await imgElem.src;
                            if (imgSrc) {
                                this.result.image = imgSrc;
                                console.error('✓ Imagen encontrada');
                                break;
                            }
                        }
                    } catch (e) {}
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer imagen:', e.message);
            }

        } catch (error) {
            console.error('✗ Error extrayendo Mercadona:', error.message);
        }
    }
    async extractAliExpress() {
        console.error('🛒 Extrayendo datos de AliExpress...');

        try {
            await this.hero.waitForPaintingStable();
            await this.hero.waitForMillis(5000);

            // Título - h1[data-pl="product-title"]
            try {
                const titleSelectors = ['h1[data-pl="product-title"]', 'h1[data-spm-anchor-id]', 'h1'];
                for (const selector of titleSelectors) {
                    try {
                        const titleElem = await this.hero.document.querySelector(selector);
                        if (titleElem) {
                            const title = await titleElem.textContent;
                            if (title && title.trim()) {
                                this.result.title = title.trim();
                                console.error(`✓ Título: ${this.result.title.substring(0, 50)}...`);
                                break;
                            }
                        }
                    } catch (e) {}
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer título:', e.message);
            }

            // Imagen - buscar en elementos magnifier
            try {
                const imgSelectors = ['[class*="magnifier"] img', 'img[class*="magnifier"]', 'img[src*="aliexpress-media"]'];
                for (const selector of imgSelectors) {
                    try {
                        const imgs = await this.hero.document.querySelectorAll(selector);
                        const imgCount = await imgs.length;
                        if (imgCount > 0) {
                            for (let i = 0; i < imgCount; i++) {
                                const imgSrc = await imgs[i].src;
                                if (imgSrc && !imgSrc.startsWith('data:') && (imgSrc.includes('aliexpress') || imgSrc.includes('alicdn'))) {
                                    this.result.image = imgSrc;
                                    console.error('✓ Imagen encontrada');
                                    break;
                                }
                            }
                            if (this.result.image) break;
                        }
                    } catch (e) {}
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer imagen:', e.message);
            }

            // Precio actual - span.price-default--current--F8OlYIo
            try {
                const priceSelectors = ['span.price-default--current--F8OlYIo', 'span[class*="price-default--current"]', 'span[class*="price"][class*="current"]'];
                for (const selector of priceSelectors) {
                    try {
                        const priceElem = await this.hero.document.querySelector(selector);
                        if (priceElem) {
                            const priceText = await priceElem.textContent;
                            if (priceText && priceText.includes('€')) {
                                const cleanPrice = priceText.replace(/€/g, '').replace(/\s/g, '').trim();
                                const priceNum = cleanPrice.replace(',', '.');
                                this.result.price = parseFloat(priceNum);
                                console.error(`✓ Precio actual: €${this.result.price}`);
                                break;
                            }
                        }
                    } catch (e) {}
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer precio:', e.message);
            }

            // Precio anterior - span.price-default--original--CWcHOit con bdi dentro
            try {
                const originalPriceSelectors = ['span.price-default--original--CWcHOit bdi', 'span[class*="price-default--original"] bdi', 'span[class*="original"] bdi'];
                for (const selector of originalPriceSelectors) {
                    try {
                        const originalPriceElem = await this.hero.document.querySelector(selector);
                        if (originalPriceElem) {
                            const priceText = await originalPriceElem.textContent;
                            if (priceText && priceText.includes('€')) {
                                const cleanPrice = priceText.replace(/€/g, '').replace(/\s/g, '').trim();
                                const priceNum = cleanPrice.replace(',', '.');
                                const parsedPrice = parseFloat(priceNum);
                                if (!isNaN(parsedPrice) && (!this.result.price || parsedPrice > this.result.price)) {
                                    this.result.original_price = parsedPrice;
                                    console.error(`✓ Precio anterior: €${this.result.original_price}`);
                                    break;
                                }
                            }
                        }
                    } catch (e) {}
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer precio anterior:', e.message);
            }

            // Calcular descuento si hay precio original
            if (this.result.original_price && this.result.price && !this.result.discount) {
                const discountPercent = Math.round(((this.result.original_price - this.result.price) / this.result.original_price) * 100);
                if (discountPercent > 0) {
                    this.result.discount = discountPercent;
                    console.error(`✓ Descuento calculado: ${this.result.discount}%`);
                }
            }

        } catch (error) {
            console.error('✗ Error extrayendo AliExpress:', error.message);
        }
    }

    async extractConsum() {
        console.error('🛒 Extrayendo datos de Consum...');

        try {
            await this.hero.waitForPaintingStable();
            await this.hero.waitForMillis(12000); // Consum necesita más tiempo para cargar

            // Título - h1.u-title-3
            try {
                const titleElem = await this.hero.document.querySelector('h1.u-title-3');
                if (titleElem) {
                    const title = await titleElem.textContent;
                    if (title && title.trim()) {
                        this.result.title = title.trim();
                        console.error(`✓ Título: ${this.result.title}`);
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer título:', e.message);
            }

            // Imagen - img.image-component__image (buscar la del producto, no el logo ni promociones)
            try {
                const imgs = await this.hero.document.querySelectorAll('img.image-component__image');
                const imgCount = await imgs.length;

                if (imgCount > 0) {
                    for (let i = 0; i < imgCount; i++) {
                        const imgSrc = await imgs[i].src;
                        const imgAlt = await imgs[i].alt;

                        // Filtrar: debe contener "product" o "media" en la URL y no ser logo ni promoción
                        if (imgSrc &&
                            (imgSrc.includes('/product/') || imgSrc.includes('/media/')) &&
                            !imgSrc.includes('logo') &&
                            !imgSrc.includes('promotion') &&
                            !imgSrc.includes('oferta') &&
                            imgAlt !== 'promotion' &&
                            imgAlt !== 'picto oferta') {
                            this.result.image = imgSrc;
                            console.error('✓ Imagen encontrada');
                            break;
                        }
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer imagen:', e.message);
            }

            // Precio actual - span.product-info-price__price
            try {
                const priceElem = await this.hero.document.querySelector('span.product-info-price__price');
                if (priceElem) {
                    const priceText = await priceElem.textContent;
                    if (priceText && priceText.includes('€')) {
                        // Convertir "0,27 €" a 0.27
                        const cleanPrice = priceText.replace(/€/g, '').replace(/\s/g, '').replace(/&nbsp;/g, '').trim();
                        const priceNum = cleanPrice.replace(',', '.');
                        const parsedPrice = parseFloat(priceNum); if (!isNaN(parsedPrice) && parsedPrice > 0) { this.result.price = parsedPrice; }
                        console.error(`✓ Precio actual: €${this.result.price}`);
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer precio:', e.message);
            }

            // Precio anterior (oferta) - span.product-info-price__offer
            try {
                const offerElem = await this.hero.document.querySelector('span.product-info-price__offer');
                if (offerElem) {
                    const offerText = await offerElem.textContent;
                    if (offerText && offerText.includes('€')) {
                        // Convertir "0,31 €" a 0.31
                        const cleanPrice = offerText.replace(/€/g, '').replace(/\s/g, '').replace(/&nbsp;/g, '').trim();
                        const priceNum = cleanPrice.replace(',', '.');
                        const parsedPrice = parseFloat(priceNum);

                        // Verificar que sea mayor que el precio actual
                        if (!isNaN(parsedPrice) && (!this.result.price || parsedPrice > this.result.price)) {
                            this.result.original_price = parsedPrice;
                            console.error(`✓ Precio anterior: €${this.result.original_price}`);
                        }
                    }
                }
            } catch (e) {
                console.error('ℹ️  No hay precio anterior (producto sin oferta)');
            }

            // Calcular descuento si hay precio anterior
            if (this.result.original_price && this.result.price && !this.result.discount) {
                const discountPercent = Math.round(
                    ((this.result.original_price - this.result.price) / this.result.original_price) * 100
                );
                if (discountPercent > 0) {
                    this.result.discount = discountPercent;
                    console.error(`✓ Descuento calculado: ${this.result.discount}%`);
                }
            }

        } catch (error) {
            console.error('✗ Error extrayendo Consum:', error.message);
        }
    }

    async extractZara() {
        console.error('👗 Extrayendo datos de Zara...');

        try {
            await this.hero.waitForMillis(5000);
            await this.hero.waitForPaintingStable();

            // Nombre del producto - h1.product-detail-info__header-name
            try {
                const nameElem = await this.hero.document.querySelector('h1.product-detail-info__header-name[data-qa-qualifier="product-detail-info-name"]');
                if (nameElem) {
                    const name = await nameElem.textContent;
                    if (name && name.trim()) {
                        this.result.title = name.trim();
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer nombre:', e.message);
            }

            // Imagen del producto - img.media-image__image
            try {
                const imgElem = await this.hero.document.querySelector('img.media-image__image.media__wrapper--media');
                if (imgElem) {
                    const imgSrc = await imgElem.src;
                    if (imgSrc) {
                        this.result.image = imgSrc;
                        console.error('✓ Imagen encontrada');
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer imagen:', e.message);
            }

            // Precio actual (con descuento) - Buscar el precio más bajo
            try {
                const priceElems = await this.hero.document.querySelectorAll('span.money-amount__main');
                const priceCount = await priceElems.length;
                
                let prices = [];
                for (let i = 0; i < priceCount; i++) {
                    const priceText = await priceElems[i].textContent;
                    if (priceText && priceText.includes('EUR')) {
                        const price = this.parsePrice(priceText);
                        if (price && price > 0) {
                            prices.push(price);
                        }
                    }
                }

                if (prices.length > 0) {
                    // Si hay 2 precios, el menor es el precio actual y el mayor es el original
                    prices.sort((a, b) => a - b);
                    this.result.price = prices[0];
                    
                    if (prices.length > 1) {
                        this.result.original_price = prices[prices.length - 1];
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer precio:', e.message);
            }

            // Descuento - span.price-current__discount-percentage
            try {
                const discountElem = await this.hero.document.querySelector('span.price-current__discount-percentage[data-qa-qualifier="price-discount-percentage"]');
                if (discountElem) {
                    const discountText = await discountElem.textContent;
                    if (discountText) {
                        const discount = this.parseDiscount(discountText);
                        if (discount) {
                            this.result.discount = discount;
                        }
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer descuento:', e.message);
            }

            // Calcular descuento si tenemos precio original pero no descuento
            if (this.result.original_price && this.result.price && !this.result.discount) {
                const discountPercent = Math.round(
                    ((this.result.original_price - this.result.price) / this.result.original_price) * 100
                );
                if (discountPercent > 0) {
                    this.result.discount = discountPercent;
                }
            }

        } catch (error) {
            console.error('✗ Error extrayendo Zara:', error.message);
        }
    }

    async extractZalando() {
        console.error('👔 Extrayendo datos de Zalando...');

        try {
            await this.hero.waitForMillis(5000);
            await this.hero.waitForPaintingStable();

            // Nombre del producto - h1 con múltiples spans
            try {
                const h1Elem = await this.hero.document.querySelector('h1.voFjEy.SbJZ75.m3OCL3.HlZ_Tf');
                if (h1Elem) {
                    const nameText = await h1Elem.textContent;
                    if (nameText && nameText.trim()) {
                        this.result.title = nameText.trim();
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer nombre:', e.message);
            }

            // Imagen del producto
            try {
                const imgElem = await this.hero.document.querySelector('img.voFjEy._3ObVF2.m3OCL3._2Pvyxl[data-testid="product_gallery-hover-zoom-image-0"]');
                if (imgElem) {
                    const imgSrc = await imgElem.src;
                    if (imgSrc) {
                        this.result.image = imgSrc;
                        console.error('✓ Imagen encontrada');
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer imagen:', e.message);
            }

            // Extraer todos los precios (Zalando muestra varios spans)
            try {
                const priceElems = await this.hero.document.querySelectorAll('span.voFjEy');
                const priceCount = await priceElems.length;
                const prices = [];
                let discountText = null;

                for (let i = 0; i < priceCount; i++) {
                    const elem = priceElems[i];
                    const text = await elem.textContent;

                    // Si contiene % es el descuento
                    if (text && text.includes('%')) {
                        discountText = text;
                        continue;
                    }

                    // Si contiene € es un precio
                    if (text && text.includes('€')) {
                        const price = this.parsePrice(text);
                        if (price && price > 0) {
                            prices.push(price);
                        }
                    }
                }

                // El precio más bajo es el actual, el más alto es el original
                if (prices.length > 0) {
                    prices.sort((a, b) => a - b);
                    this.result.price = prices[0];

                    if (prices.length > 1) {
                        this.result.original_price = prices[prices.length - 1];
                    }
                }

                // Procesar descuento
                if (discountText) {
                    this.result.discount = this.parseDiscount(discountText);
                }

            } catch (e) {
                console.error('⚠️  Error extrayendo precios de Zalando:', e.message);
            }

            // Calcular descuento si tenemos precio original pero no descuento
            if (this.result.original_price && this.result.price && !this.result.discount) {
                const discountPercent = Math.round(
                    ((this.result.original_price - this.result.price) / this.result.original_price) * 100
                );
                if (discountPercent > 0) {
                    this.result.discount = discountPercent;
                }
            }

        } catch (error) {
            console.error('✗ Error extrayendo Zalando:', error.message);
        }
    }

    async extractTemu() {
        console.error('🛍️  Extrayendo datos de Temu usando JSON-LD...');

        try {
            await this.hero.waitForMillis(10000);
            await this.hero.waitForPaintingStable();

            // Buscar todos los scripts de tipo application/ld+json
            const scripts = await this.hero.document.querySelectorAll('script[type="application/ld+json"]');
            const scriptCount = await scripts.length;
            
            
            let productData = null;
            
            for (let i = 0; i < scriptCount; i++) {
                try {
                    const scriptContent = await scripts[i].textContent;
                    if (!scriptContent) continue;
                    
                    const jsonData = JSON.parse(scriptContent);
                    
                    // Buscar objeto con @type: "Product"
                    if (jsonData['@type'] === 'Product' || 
                        (jsonData['@graph'] && jsonData['@graph'].some(item => item['@type'] === 'Product'))) {
                        
                        productData = jsonData['@type'] === 'Product' ? jsonData : 
                                     jsonData['@graph'].find(item => item['@type'] === 'Product');
                        
                        console.error('✓ JSON-LD Product encontrado');
                        break;
                    }
                } catch (e) {
                    // JSON inválido o estructura diferente, continuar
                    continue;
                }
            }
            
            if (productData) {
                // Extraer nombre
                if (productData.name) {
                    this.result.title = productData.name.trim();
                }
                
                // Extraer precio
                if (productData.offers) {
                    const offers = Array.isArray(productData.offers) ? productData.offers[0] : productData.offers;
                    
                    if (offers.price) {
                        this.result.price = parseFloat(offers.price);
                    }
                    
                    // Precio original (si existe)
                    if (offers.priceSpecification && offers.priceSpecification.price) {
                        this.result.original_price = parseFloat(offers.priceSpecification.price);
                    }
                }
                
                // Extraer imagen
                if (productData.image) {
                    let imageUrl = null;
                    
                    if (typeof productData.image === 'string') {
                        imageUrl = productData.image;
                    } else if (Array.isArray(productData.image) && productData.image.length > 0) {
                        const firstImage = productData.image[0];
                        imageUrl = typeof firstImage === 'string' ? firstImage : 
                                  (firstImage.contentUrl || firstImage.url);
                    } else if (productData.image.contentUrl || productData.image.url) {
                        imageUrl = productData.image.contentUrl || productData.image.url;
                    }
                    
                    if (imageUrl) {
                        this.result.image = imageUrl;
                        console.error('✓ Imagen encontrada');
                    }
                }
                
                // Calcular descuento si tenemos ambos precios
                if (this.result.original_price && this.result.price && 
                    this.result.original_price > this.result.price) {
                    this.result.discount = Math.round(
                        ((this.result.original_price - this.result.price) / this.result.original_price) * 100
                    );
                }
            } else {
                console.error('⚠️  No se encontró JSON-LD con datos del producto');
            }

        } catch (error) {
            console.error('✗ Error extrayendo Temu:', error.message);
        }
    }
    async extractLego() {
        console.error('🧱 Extrayendo datos de LEGO...');

        try {
            await this.hero.waitForMillis(5000);
            await this.hero.waitForPaintingStable();

            // Nombre del producto
            try {
                const nameElem = await this.hero.document.querySelector('h1.ProductOverview_nameText__qLPqt[data-test="product-overview-name"]');
                if (nameElem) {
                    const nameText = await nameElem.textContent;
                    if (nameText && nameText.trim()) {
                        this.result.title = nameText.trim();
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer nombre:', e.message);
            }

            // Imagen del producto
            try {
                const imgElem = await this.hero.document.querySelector('img[src*="lego.com/cdn/cs/set/assets"]');
                if (imgElem) {
                    const imgSrc = await imgElem.src;
                    if (imgSrc) {
                        this.result.image = imgSrc;
                        console.error('✓ Imagen encontrada');
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer imagen:', e.message);
            }

            // Precio del producto
            try {
                const priceElem = await this.hero.document.querySelector('span.ds-heading-lg[data-test="product-price-display-price"]');
                if (priceElem) {
                    const priceText = await priceElem.textContent;
                    if (priceText) {
                        this.result.price = this.parsePrice(priceText);
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer precio:', e.message);
            }

        } catch (error) {
            console.error('✗ Error extrayendo LEGO:', error.message);
        }
    }

    async extractDecathlon() {
        console.error('🏃 Extrayendo datos de Decathlon...');

        try {
            await this.hero.waitForMillis(5000);
            await this.hero.waitForPaintingStable();

            // Nombre del producto
            try {
                const nameElem = await this.hero.document.querySelector('h1.vp-title-m');
                if (nameElem) {
                    const nameText = await nameElem.textContent;
                    if (nameText && nameText.trim()) {
                        this.result.title = nameText.trim();
                        console.error('✓ Nombre encontrado');
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer nombre:', e.message);
            }

            // Imagen del producto
            try {
                // Buscar imagen con srcset que contenga mediadecathlon y NO sea el logo
                const imgElems = await this.hero.document.querySelectorAll('img[srcset*="mediadecathlon.com"]');
                const imgCount = await imgElems.length;

                for (let i = 0; i < imgCount; i++) {
                    const imgElem = imgElems[i];
                    const src = await imgElem.src;

                    // Saltar si es el logo de Decathlon
                    if (src && src.includes('Decathlon%20logo')) {
                        continue;
                    }

                    const srcset = await imgElem.srcset;
                    if (srcset && !srcset.includes('logo')) {
                        const srcsetParts = srcset.split(',');
                        if (srcsetParts.length > 0) {
                            // Obtener la URL de mayor resolución
                            const lastPart = srcsetParts[srcsetParts.length - 1].trim();
                            const urlMatch = lastPart.match(/(https?:\/\/[^\s]+)/);
                            if (urlMatch) {
                                this.result.image = urlMatch[1];
                                console.error('✓ Imagen encontrada (srcset)');
                                break;
                            }
                        }
                    } else if (src && !src.includes('logo')) {
                        this.result.image = src;
                        console.error('✓ Imagen encontrada (src)');
                        break;
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer imagen:', e.message);
            }

            // Precio actual
            try {
                const priceElem = await this.hero.document.querySelector('span.notranslate.vp-price-amount.vp-price-amount--sale.vp-price-amount--large[data-part="amount"]');
                if (priceElem) {
                    const priceText = await priceElem.textContent;
                    if (priceText) {
                        this.result.price = this.parsePrice(priceText);
                        console.error('✓ Precio actual encontrado');
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer precio actual:', e.message);
            }

            // Precio original (anterior)
            try {
                const originalPriceElem = await this.hero.document.querySelector('s.notranslate.vp-price-barred-amount.vp-price-amount--large[data-part="amount"][aria-label="Precio anterior"]');
                if (originalPriceElem) {
                    const priceText = await originalPriceElem.textContent;
                    if (priceText) {
                        this.result.original_price = this.parsePrice(priceText);
                        console.error('✓ Precio original encontrado');
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer precio original:', e.message);
            }

            // Descuento
            try {
                const discountElem = await this.hero.document.querySelector('span.vp-price-discount[data-part="discount"]');
                if (discountElem) {
                    const discountText = await discountElem.textContent;
                    if (discountText) {
                        this.result.discount = this.parseDiscount(discountText);
                        console.error('✓ Descuento encontrado');
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer descuento:', e.message);
            }

        } catch (error) {
            console.error('✗ Error extrayendo Decathlon:', error.message);
        }
    }

    async extractMangoOutlet() {
        console.error('👔 Extrayendo datos de Mango Outlet...');

        try {
            await this.hero.waitForMillis(5000);
            await this.hero.waitForPaintingStable();

            // Nombre del producto
            try {
                const nameElem = await this.hero.document.querySelector('h1.ProductDetail_title__Go9C2.textHeadingL_className__KQ29Z');
                if (nameElem) {
                    const nameText = await nameElem.textContent;
                    if (nameText && nameText.trim()) {
                        this.result.title = nameText.trim();
                        console.error('✓ Nombre encontrado');
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer nombre:', e.message);
            }

            // Imagen del producto - Primera imagen de la galería
            try {
                const imgElem = await this.hero.document.querySelector('img.ImageGridItem_image__VVZxr');
                if (imgElem) {
                    const srcset = await imgElem.srcset;
                    const src = await imgElem.src;

                    // Extraer la imagen de mayor resolución del srcset
                    if (srcset) {
                        const srcsetParts = srcset.split(',');
                        if (srcsetParts.length > 0) {
                            // Obtener la última URL (mayor resolución)
                            const lastPart = srcsetParts[srcsetParts.length - 1].trim();
                            const urlMatch = lastPart.match(/(https?:\/\/[^\s]+)/);
                            if (urlMatch) {
                                this.result.image = urlMatch[1];
                                console.error('✓ Imagen encontrada (srcset)');
                            }
                        }
                    } else if (src) {
                        this.result.image = src;
                        console.error('✓ Imagen encontrada (src)');
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer imagen:', e.message);
            }

            // Precio original (tachado)
            try {
                const originalPriceElem = await this.hero.document.querySelector('span.SinglePrice_crossed__lNWfi.SinglePrice_center__SWK1D.textBodyM_className__v9jW9');
                if (originalPriceElem) {
                    const priceText = await originalPriceElem.textContent;
                    if (priceText) {
                        this.result.original_price = this.parsePrice(priceText);
                        console.error('✓ Precio original encontrado');
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer precio original:', e.message);
            }

            // Precio actual (precio final)
            try {
                const priceElem = await this.hero.document.querySelector('span.SinglePrice_center__SWK1D.textBodyM_className__v9jW9.SinglePrice_finalPrice__hZjhM');
                if (priceElem) {
                    const priceText = await priceElem.textContent;
                    if (priceText) {
                        this.result.price = this.parsePrice(priceText);
                        console.error('✓ Precio actual encontrado');
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer precio actual:', e.message);
            }

            // Descuento
            try {
                // Buscar el span que contenga el porcentaje de descuento
                const spans = await this.hero.document.querySelectorAll('span.textBodyM_className__v9jW9');
                const spanCount = await spans.length;

                for (let i = 0; i < spanCount; i++) {
                    const span = spans[i];
                    const text = await span.textContent;
                    if (text && text.includes('%')) {
                        this.result.discount = this.parseDiscount(text);
                        if (this.result.discount) {
                            console.error('✓ Descuento encontrado');
                            break;
                        }
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer descuento:', e.message);
            }

        } catch (error) {
            console.error('✗ Error extrayendo Mango Outlet:', error.message);
        }
    }

    async extractMichaelKors() {
        console.error('👜 Extrayendo datos de Michael Kors...');

        try {
            await this.hero.waitForMillis(5000);
            await this.hero.waitForPaintingStable();

            // Nombre del producto
            try {
                const nameElem = await this.hero.document.querySelector('h1.product-name.overflow-hidden');
                if (nameElem) {
                    const nameText = await nameElem.textContent;
                    if (nameText && nameText.trim()) {
                        this.result.title = nameText.trim();
                        console.error('✓ Nombre encontrado');
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer nombre:', e.message);
            }

            // Imagen del producto
            try {
                const imgElem = await this.hero.document.querySelector('img[src*="michaelkors.scene7.com"]');
                if (imgElem) {
                    const src = await imgElem.src;
                    if (src) {
                        this.result.image = src;
                        console.error('✓ Imagen encontrada');
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer imagen:', e.message);
            }

            // Precios - Buscar todos los elementos span.value
            try {
                const priceElems = await this.hero.document.querySelectorAll('span.value');
                const priceCount = await priceElems.length;
                const prices = [];

                for (let i = 0; i < priceCount; i++) {
                    const priceElem = priceElems[i];
                    const priceText = await priceElem.textContent;
                    if (priceText) {
                        const price = this.parsePrice(priceText);
                        if (price && price > 0) {
                            prices.push(price);
                        }
                    }
                }

                // Si hay dos precios, el primero es el original y el segundo el actual
                if (prices.length >= 2) {
                    this.result.original_price = prices[0];
                    this.result.price = prices[1];
                    console.error('✓ Precio original y actual encontrados');

                    // Extraer descuento del selector
                    try {
                        const discountElem = await this.hero.document.querySelector('span.default-price__discount');
                        if (discountElem) {
                            const discountText = await discountElem.textContent;
                            if (discountText) {
                                this.result.discount = this.parseDiscount(discountText);
                                console.error('✓ Descuento encontrado');
                            }
                        }
                    } catch (e) {
                        // Si no se encuentra, calcular descuento
                        if (this.result.original_price && this.result.price) {
                            const discountPercent = Math.round(((this.result.original_price - this.result.price) / this.result.original_price) * 100);
                            if (discountPercent > 0) {
                                this.result.discount = discountPercent;
                                console.error('✓ Descuento calculado');
                            }
                        }
                    }
                } else if (prices.length === 1) {
                    // Solo hay un precio, es el precio actual
                    this.result.price = prices[0];
                    console.error('✓ Precio actual encontrado');
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer precios:', e.message);
            }

        } catch (error) {
            console.error('✗ Error extrayendo Michael Kors:', error.message);
        }
    }

    async extractMango() {
        console.error('👗 Extrayendo datos de Mango...');

        try {
            await this.hero.waitForMillis(5000);
            await this.hero.waitForPaintingStable();

            // Nombre del producto
            try {
                const nameElem = await this.hero.document.querySelector('h1.ProductDetail_title__Go9C2.textHeadingL_className__KQ29Z');
                if (nameElem) {
                    const nameText = await nameElem.textContent;
                    if (nameText && nameText.trim()) {
                        this.result.title = nameText.trim();
                        console.error('✓ Nombre encontrado');
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer nombre:', e.message);
            }

            // Imagen del producto - Primera imagen de la galería
            try {
                const imgElem = await this.hero.document.querySelector('img.ImageGridItem_image__VVZxr');
                if (imgElem) {
                    const srcset = await imgElem.srcset;
                    const src = await imgElem.src;

                    // Extraer la imagen de mayor resolución del srcset
                    if (srcset) {
                        const srcsetParts = srcset.split(',');
                        if (srcsetParts.length > 0) {
                            // Obtener la última URL (mayor resolución)
                            const lastPart = srcsetParts[srcsetParts.length - 1].trim();
                            const urlMatch = lastPart.match(/(https?:\/\/[^\s]+)/);
                            if (urlMatch) {
                                this.result.image = urlMatch[1];
                                console.error('✓ Imagen encontrada (srcset)');
                            }
                        }
                    } else if (src) {
                        this.result.image = src;
                        console.error('✓ Imagen encontrada (src)');
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer imagen:', e.message);
            }

            // Precio actual
            try {
                const priceElem = await this.hero.document.querySelector('span.SinglePrice_center__SWK1D.textBodyM_className__v9jW9');
                if (priceElem) {
                    const priceText = await priceElem.textContent;
                    if (priceText) {
                        this.result.price = this.parsePrice(priceText);
                        console.error('✓ Precio encontrado');
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer precio:', e.message);
            }

        } catch (error) {
            console.error('✗ Error extrayendo Mango:', error.message);
        }
    }

    async extractIkea() {
        console.error('🛋️ Extrayendo datos de IKEA...');

        try {
            await this.hero.waitForMillis(5000);
            await this.hero.waitForPaintingStable();

            // Nombre del producto
            try {
                // Buscar el span que contiene el nombre del producto
                const nameElems = await this.hero.document.querySelectorAll('span');
                const nameCount = await nameElems.length;

                for (let i = 0; i < nameCount; i++) {
                    const nameElem = nameElems[i];
                    const nameText = await nameElem.textContent;

                    // El nombre suele contener "," y tiene un link dentro
                    if (nameText && nameText.includes(',') && nameText.length > 20 && nameText.length < 200) {
                        const hasLink = await nameElem.querySelector('a');
                        if (hasLink) {
                            this.result.title = nameText.trim().replace(/\s+/g, ' ');
                            console.error('✓ Nombre encontrado');
                            break;
                        }
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer nombre:', e.message);
            }

            // Imagen del producto
            try {
                const imgElem = await this.hero.document.querySelector('img.pip-image');
                if (imgElem) {
                    const src = await imgElem.src;
                    if (src) {
                        this.result.image = src;
                        console.error('✓ Imagen encontrada');
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer imagen:', e.message);
            }

            // Precios - IKEA: El contenedor padre tiene la estructura completa
            try {
                // El span con aria-hidden y notranslate contiene integer + decimal
                const priceSpans = await this.hero.document.querySelectorAll('span[aria-hidden="true"].notranslate');
                const priceCount = await priceSpans.length;
                const prices = [];

                for (let i = 0; i < priceCount; i++) {
                    const span = priceSpans[i];
                    const fullText = await span.textContent;

                    if (fullText) {
                        // parsePrice maneja "9,99" o "9.99" o "999" correctamente
                        const price = this.parsePrice(fullText);
                        if (price && price > 0) {
                            prices.push(price);
                        }
                    }
                }

                // Si hay múltiples precios, el primero suele ser el original y el segundo el actual
                if (prices.length >= 2) {
                    this.result.original_price = prices[0];
                    this.result.price = prices[1];
                    console.error('✓ Precio original y actual encontrados');
                } else if (prices.length === 1) {
                    this.result.price = prices[0];
                    console.error('✓ Precio actual encontrado');
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer precios:', e.message);
            }

            // Descuento
            try {
                // Intentar varios selectores para el descuento
                let discountElem = await this.hero.document.querySelector('span.pip-price-package__discount-text-c');
                
                if (!discountElem) {
                    // Buscar cualquier span que contenga "de descuento"
                    const spans = await this.hero.document.querySelectorAll('span');
                    const spanCount = await spans.length;
                    
                    for (let i = 0; i < spanCount; i++) {
                        const span = spans[i];
                        const text = await span.textContent;
                        if (text && text.includes('de descuento')) {
                            discountElem = span;
                            break;
                        }
                    }
                }
                
                if (discountElem) {
                    const discountText = await discountElem.textContent;
                    if (discountText) {
                        this.result.discount = this.parseDiscount(discountText);
                        console.error('✓ Descuento encontrado');
                    }
                }
            } catch (e) {
                console.error('⚠️  No se pudo extraer descuento:', e.message);
            }

        } catch (error) {
            console.error('✗ Error extrayendo IKEA:', error.message);
        }
    }


}

// Main
(async () => {
    if (process.argv.length < 3) {
        console.log(JSON.stringify({
            success: false,
            error: 'URL no proporcionada. Uso: node hero_scraper.js <URL>'
        }));
        process.exit(1);
    }

    const url = process.argv[2];
    const scraper = new HeroScraper(url);

    // Timeout de 60 segundos
    const timeoutPromise = new Promise((_, reject) => {
        setTimeout(() => reject(new Error('Timeout: El scraping tardó más de 60 segundos')), 60000);
    });

    try {
        const result = await Promise.race([
            scraper.scrape(),
            timeoutPromise
        ]);
        console.log(JSON.stringify(result));
    } catch (error) {
        console.log(JSON.stringify({
            success: false,
            error: error.message
        }));
    } finally {
        // Asegurar que Hero se cierre
        if (scraper.hero) {
            try {
                await scraper.hero.close();
            } catch (e) {
                // Ignorar errores al cerrar
            }
        }
        // Forzar salida del proceso
        process.exit(0);
    }
})();
