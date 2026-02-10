# Price Monitor - AI Context

## Stack
PHP 8.3 + MariaDB + Node.js + Python | Docker (Ubuntu 24.04)

## Architecture
```
api/ ────────┐
             ├─→ services/ ──→ scrapers/ (Python/Node)
index.html ──┘
```

## Scraping Flow
1. **Entry**: `api/scrape.php` or `api/urls.php` → `services/ScraperService.php`
2. **Router**: `ScraperService.php` detects domain → selects scraper
3. **Scrapers**: PHP wrappers → exec() external process → parse JSON stdout

## Scraper Types
| Type | File | Tech | Use Case |
|------|------|------|----------|
| Native PHP | `services/*Scraper.php` | cURL | Fast, simple sites |
| Advanced | `AdvancedScraper.php` | Python Camoufox | Anti-bot (Fnac) |
| Hero | `HeroScraper.php` | Node.js Chrome | Fallback |

## Key Patterns
```php
// PHP wrapper pattern
$cmd = "HOME=/var/www python3 script.py $url 2>&1";
exec($cmd, $output, $code);
$json = parse_bottom_up($output); // Find first line starting with '{'
```

```python
# Camoufox: Set env BEFORE import!
os.environ['HOME'] = '/var/www'
os.environ['TMPDIR'] = '/var/www/.cache/camoufox/tmp'
from camoufox.sync_api import Camoufox  # Import AFTER env setup
```

## Store Coverage (18 scrapers)
Amazon, PcComponentes, Fnac, MediaMarkt, ElCorteIngles, Coolmod, Mercadona, AliExpress, Consum, Zara, Zalando, Temu, Lego, Ikea, Decathlon, Mango, MangoOutlet, MichaelKors

## Database
- `monitored_urls`: URL tracking
- `price_history`: Price snapshots
- `users`: Authentication

## Critical Files
- `services/ScraperService.php`: Main router (domain → scraper)
- `scrapers/advanced_scraper.py`: Camoufox (713MB, 5-8s)
- `scrapers/hero_scraper.js`: Node.js fallback (2000+ lines)
- `api/scrape.php`: Direct API endpoint (duplicates ScraperService logic)

## Known Issues
- `SeleniumScraper.php` is misnomer - calls Hero, not Selenium
- `api/scrape.php` + `ScraperService.php` have duplicated routing
- CRON uses old scrapers, not ScraperService

## Fnac Example (Fallback Chain)
1. `FnacScraper.php` (cURL) → 403 ❌
2. `AdvancedScraper('camoufox')` → 5-8s ✅
3. `HeroScraper` → Final fallback

## Environment Critical
- **Camoufox**: Env vars MUST precede import
- **Chrome**: crashpad_handler wrapper adds `--database=/tmp`
- **Both**: Need `HOME=/var/www` for cache access
