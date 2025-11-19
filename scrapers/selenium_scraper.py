#!/usr/bin/env python3
"""
Selenium Scraper Universal para Price Monitor
Ejecuta un navegador headless para extraer datos de páginas con protección anti-bot
"""

import sys
import json
import time
from selenium import webdriver
from selenium.webdriver.common.by import By
from selenium.webdriver.support.ui import WebDriverWait
from selenium.webdriver.support import expected_conditions as EC
from selenium.webdriver.chrome.options import Options
from selenium.webdriver.chrome.service import Service
import re


class UniversalSeleniumScraper:
    def __init__(self, url, headless=True):
        self.url = url
        self.headless = headless
        self.driver = None
        self.result = {
            'success': False,
            'error': None,
            'title': None,
            'price': None,
            'image': None,
            'discount': None,
            'original_price': None,
            'store': self.detect_store(url)
        }

    def detect_store(self, url):
        """Detecta la tienda según la URL"""
        if 'amazon' in url:
            return 'amazon'
        elif 'pccomponentes' in url:
            return 'pccomponentes'
        elif 'elcorteingles' in url:
            return 'elcorteingles'
        else:
            return 'unknown'

    def init_driver(self):
        """Inicializa Chrome (headless o con ventana según configuración)"""
        import random

        chrome_options = Options()

        # Solo agregar headless si está habilitado
        if self.headless:
            chrome_options.add_argument('--headless=new')
            print("🔇 Modo: Headless (sin ventana)", file=sys.stderr)
        else:
            print("🖥️  Modo: Con ventana (más difícil de detectar)", file=sys.stderr)

        chrome_options.add_argument('--no-sandbox')
        chrome_options.add_argument('--disable-dev-shm-usage')
        chrome_options.add_argument('--disable-gpu')
        chrome_options.add_argument('--disable-software-rasterizer')
        chrome_options.add_argument('--disable-extensions')
        chrome_options.add_argument('--window-size=1920,1080')
        chrome_options.add_argument('--disable-blink-features=AutomationControlled')

        # User agent realista y actualizado
        chrome_options.add_argument('--user-agent=Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/141.0.0.0 Safari/537.36')

        # Headers adicionales para parecer más real
        chrome_options.add_argument('--accept-language=es-ES,es;q=0.9')
        chrome_options.add_argument('--accept=text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8')

        # Puerto aleatorio para debugging (evita conflictos)
        debug_port = random.randint(9000, 9999)
        chrome_options.add_argument(f'--remote-debugging-port={debug_port}')

        chrome_options.add_experimental_option("excludeSwitches", ["enable-automation", "enable-logging"])
        chrome_options.add_experimental_option('useAutomationExtension', False)

        # Preferences para parecer más humano
        prefs = {
            "credentials_enable_service": False,
            "profile.password_manager_enabled": False,
            "profile.default_content_setting_values.notifications": 2
        }
        chrome_options.add_experimental_option("prefs", prefs)

        # Desactivar imágenes para ir más rápido (opcional)
        # prefs = {"profile.managed_default_content_settings.images": 2}
        # chrome_options.add_experimental_option("prefs", prefs)

        # Configurar el servicio de ChromeDriver
        import os
        script_dir = os.path.dirname(os.path.abspath(__file__))
        chromedriver_path = os.path.join(script_dir, 'chromedriver')

        # Usar chromedriver local si existe, sino buscar en sistema
        if os.path.exists(chromedriver_path):
            service = Service(executable_path=chromedriver_path)
        else:
            service = Service(executable_path='/usr/bin/chromedriver')

        # Detectar y configurar Chrome/Chromium binary location
        chrome_paths = [
            '/usr/bin/google-chrome',           # Google Chrome
            '/usr/bin/google-chrome-stable',    # Google Chrome Stable
            '/usr/bin/chromium-browser',        # Chromium (APT)
            '/usr/bin/chromium',                # Chromium alternativo
            '/snap/bin/chromium',               # Chromium Snap
            'C:/Program Files/Google/Chrome/Application/chrome.exe',  # Windows Chrome
            'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe'  # Windows Chrome x86
        ]

        chrome_binary = None
        for path in chrome_paths:
            if os.path.exists(path):
                chrome_binary = path
                break

        if chrome_binary:
            chrome_options.binary_location = chrome_binary
        # Si no encuentra ninguno, dejará que Selenium lo detecte automáticamente

        self.driver = webdriver.Chrome(service=service, options=chrome_options)
        self.driver.execute_script("Object.defineProperty(navigator, 'webdriver', {get: () => undefined})")

    def extract_amazon(self):
        """Extrae datos de Amazon"""
        try:
            # Título
            try:
                title = WebDriverWait(self.driver, 10).until(
                    EC.presence_of_element_located((By.ID, "productTitle"))
                )
                self.result['title'] = title.text.strip()
            except:
                pass

            # Precio actual
            price_selectors = [
                (By.CLASS_NAME, "a-price-whole"),
                (By.CSS_SELECTOR, ".a-price .a-offscreen"),
                (By.ID, "priceblock_ourprice"),
                (By.ID, "priceblock_dealprice")
            ]

            for selector_type, selector in price_selectors:
                try:
                    price_elem = self.driver.find_element(selector_type, selector)
                    price_text = price_elem.get_attribute('textContent') or price_elem.text
                    price = self.parse_price(price_text)
                    if price:
                        self.result['price'] = price
                        break
                except:
                    continue

            # Precio original (si hay descuento)
            try:
                original_price_elem = self.driver.find_element(By.CSS_SELECTOR, ".a-price.a-text-price span.a-offscreen")
                original_price = self.parse_price(original_price_elem.get_attribute('textContent'))
                if original_price and original_price > self.result['price']:
                    self.result['original_price'] = original_price
            except:
                pass

            # Descuento
            try:
                discount_elem = self.driver.find_element(By.CSS_SELECTOR, ".savingsPercentage")
                discount_text = discount_elem.text
                discount = self.parse_discount(discount_text)
                if discount:
                    self.result['discount'] = discount
            except:
                pass

            # Imagen
            try:
                img_elem = self.driver.find_element(By.ID, "landingImage")
                self.result['image'] = img_elem.get_attribute('src')
            except:
                try:
                    img_elem = self.driver.find_element(By.CSS_SELECTOR, "#imageBlock img")
                    self.result['image'] = img_elem.get_attribute('src')
                except:
                    pass

        except Exception as e:
            self.result['error'] = f"Error extrayendo Amazon: {str(e)}"

    def extract_pccomponentes(self):
        """Extrae datos de PcComponentes"""
        try:
            # Esperar a que cargue el contenido
            time.sleep(2)

            # Título
            try:
                title = self.driver.find_element(By.CSS_SELECTOR, "h1.h1, h1[data-name='product-title']")
                self.result['title'] = title.text.strip()
            except:
                pass

            # Precio
            price_selectors = [
                (By.CSS_SELECTOR, "#precio-main"),
                (By.CSS_SELECTOR, ".precio-main"),
                (By.CSS_SELECTOR, "[data-name='product-price']"),
                (By.CSS_SELECTOR, ".price")
            ]

            for selector_type, selector in price_selectors:
                try:
                    price_elem = self.driver.find_element(selector_type, selector)
                    price = self.parse_price(price_elem.text)
                    if price:
                        self.result['price'] = price
                        break
                except:
                    continue

            # Imagen
            try:
                img_elem = self.driver.find_element(By.CSS_SELECTOR, "#preview img, .product-image img, img[data-name='product-image']")
                self.result['image'] = img_elem.get_attribute('src')
            except:
                pass

        except Exception as e:
            self.result['error'] = f"Error extrayendo PcComponentes: {str(e)}"

    def extract_elcorteingles(self):
        """Extrae datos de El Corte Inglés"""
        try:
            # Esperar más tiempo para El Corte Inglés (tiene protección anti-bot Akamai)
            print("Esperando carga de El Corte Inglés...", file=sys.stderr)
            time.sleep(8)

            # Verificar si hay "Access Denied" o challenge
            page_source = self.driver.page_source
            if "Access Denied" in page_source or "akam" in page_source.lower():
                print("⚠️ Detectada protección Akamai, esperando más...", file=sys.stderr)
                time.sleep(10)  # Esperar más tiempo para que se resuelva el challenge

            # Título
            try:
                title = WebDriverWait(self.driver, 20).until(
                    EC.presence_of_element_located((By.ID, "product_detail_title"))
                )
                self.result['title'] = title.text.strip()
                print(f"✓ Título encontrado: {self.result['title'][:50]}...", file=sys.stderr)
            except:
                # Fallback: intentar otros selectores
                try:
                    title = self.driver.find_element(By.CSS_SELECTOR, "h1.product-title, h1")
                    self.result['title'] = title.text.strip()
                    print(f"✓ Título (fallback): {self.result['title'][:50]}...", file=sys.stderr)
                except:
                    print("❌ No se pudo encontrar título", file=sys.stderr)
                    pass

            # Precio (con descuento)
            try:
                price_elem = self.driver.find_element(By.CSS_SELECTOR, ".price-sale")
                price = self.parse_price(price_elem.text)
                if price:
                    self.result['price'] = price
            except:
                # Fallback: cualquier elemento con clase "price"
                try:
                    price_elem = self.driver.find_element(By.CSS_SELECTOR, "[class*='price']")
                    price = self.parse_price(price_elem.text)
                    if price:
                        self.result['price'] = price
                except:
                    pass

            # Descuento
            try:
                discount_elem = self.driver.find_element(By.CSS_SELECTOR, ".price-discount")
                discount = self.parse_discount(discount_elem.text)
                if discount:
                    self.result['discount'] = discount
            except:
                pass

            # Calcular precio original si hay descuento
            if self.result['discount'] and self.result['price']:
                self.result['original_price'] = round(
                    self.result['price'] / (1 - self.result['discount'] / 100), 2
                )

            # Imagen
            try:
                img_elem = self.driver.find_element(By.CSS_SELECTOR, "picture img")
                img_src = img_elem.get_attribute('src')
                if img_src and ('.jpg' in img_src or '.png' in img_src):
                    self.result['image'] = img_src
            except:
                pass

        except Exception as e:
            self.result['error'] = f"Error extrayendo El Corte Inglés: {str(e)}"

    def parse_price(self, text):
        """Extrae precio de un texto"""
        if not text:
            return None

        # Limpiar y extraer número
        text = text.replace('€', '').replace('EUR', '').strip()
        text = text.replace('.', '').replace(',', '.')

        match = re.search(r'(\d+\.?\d*)', text)
        if match:
            try:
                return float(match.group(1))
            except:
                return None
        return None

    def parse_discount(self, text):
        """Extrae porcentaje de descuento de un texto"""
        if not text:
            return None

        match = re.search(r'(\d+)\s*%', text)
        if match:
            try:
                return float(match.group(1))
            except:
                return None
        return None

    def scrape(self):
        """Ejecuta el scraping según la tienda detectada"""
        try:
            self.init_driver()
            self.driver.get(self.url)

            # Ejecutar extracción según tienda
            if self.result['store'] == 'amazon':
                self.extract_amazon()
            elif self.result['store'] == 'pccomponentes':
                self.extract_pccomponentes()
            elif self.result['store'] == 'elcorteingles':
                self.extract_elcorteingles()
            else:
                self.result['error'] = f"Tienda no soportada: {self.result['store']}"
                return self.result

            # Validar que al menos tengamos precio
            if self.result['price']:
                self.result['success'] = True
            else:
                self.result['error'] = "No se pudo extraer el precio"

        except Exception as e:
            self.result['error'] = f"Error general: {str(e)}"

        finally:
            if self.driver:
                self.driver.quit()

        return self.result


def main():
    if len(sys.argv) < 2:
        print(json.dumps({'success': False, 'error': 'URL no proporcionada'}))
        sys.exit(1)

    url = sys.argv[1]

    # Verificar si se pasa el flag --no-headless
    headless = True
    if len(sys.argv) > 2 and sys.argv[2] == '--no-headless':
        headless = False

    scraper = UniversalSeleniumScraper(url, headless=headless)
    result = scraper.scrape()

    print(json.dumps(result, ensure_ascii=False))


if __name__ == '__main__':
    main()
