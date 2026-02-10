# Price Monitor - Quickstart

## Local Development
```bash
cd docker
docker compose -f docker-compose.local.yml up -d
```
Access: http://localhost:8080

## Production
```bash
cd docker
docker compose up -d
```

## Adding URLs
1. Open web interface
2. Paste product URL
3. System auto-detects store and scrapes

## Supported Stores (18)
Amazon, PcComponentes, Fnac, MediaMarkt, ElCorteIngles, Coolmod, Mercadona, AliExpress, Consum, Zara, Zalando, Temu, Lego, Ikea, Decathlon, Mango, MangoOutlet, MichaelKors

## Troubleshooting

### Camoufox not working
Check: `docker exec -u www-data price-monitor ls -la /var/www/.cache/camoufox/`
Should exist with www-data ownership.

### Chrome crashpad errors
Wrapper exists at: `/var/www/.cache/ulixee/chrome/*/chrome_crashpad_handler`

### Scraping fails
Check logs: `docker logs price-monitor`
Or: `docker exec price-monitor tail -f /var/log/apache2/error.log`

## Architecture
See `AI_CONTEXT.md` for technical details.
