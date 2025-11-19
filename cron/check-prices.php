#!/usr/bin/php
<?php
/**
 * Script CRON para verificar precios de URLs monitorizadas
 *
 * FUNCIONAMIENTO AUTOMÁTICO:
 * - Este script se ejecuta automáticamente 3 veces al día (09:00, 14:00, 00:00)
 * - Verifica TODAS las URLs de TODOS los usuarios en la base de datos
 * - Compara el precio actual con el precio objetivo y descuento objetivo
 * - Envía notificaciones SOLO cuando se alcanza el precio objetivo o descuento objetivo
 * - NO notifica por bajadas de precio que no alcancen los objetivos configurados
 * - Usa scraper específico para PcComponentes (extrae título, imagen, precio, descuento)
 *
 * CONFIGURACIÓN EN CRONTAB (horario español):
 * 0 9 * * * /usr/bin/php /var/www/html/price-monitor/cron/check-prices.php
 * 0 14 * * * /usr/bin/php /var/www/html/price-monitor/cron/check-prices.php
 * 0 0 * * * /usr/bin/php /var/www/html/price-monitor/cron/check-prices.php
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../services/PriceScraper.php';
require_once __DIR__ . '/../services/PcComponentesScraper.php';
require_once __DIR__ . '/../services/NotificationService.php';

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║       MONITOR DE PRECIOS - VERIFICACIÓN AUTOMÁTICA        ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "[" . date('Y-m-d H:i:s') . "] Iniciando verificación de precios...\n\n";

 = Database::getInstance()->getConnection();

// Obtener TODAS las URLs activas de TODOS los usuarios
 = ->prepare("
    SELECT mu.*, u.email, u.username
    FROM monitored_urls mu
    JOIN users u ON mu.user_id = u.id
    WHERE mu.status = 'active'
    ORDER BY mu.last_checked ASC
");
->execute();
 = ->fetchAll();

echo "📊 URLs activas en la base de datos: " . count() . "\n";
echo "👥 El sistema verificará automáticamente todos los enlaces de todos los usuarios\n";
echo str_repeat("-", 60) . "\n\n";

 = 0;
 = 0;
 = 0;

foreach ( as ) {
     = ['id'];
     = ['url'];
     = ['product_name'] ?: 'Producto';
     = ['current_price'];
     = ['target_price'];

    echo "\n[{}] Verificando: {}\n";
    echo "  URL: {}\n";

    // Detectar si es PcComponentes y usar scraper específico
    if (PcComponentesScraper::isPcComponentesURL()) {
        echo "  🔍 Detectado: PcComponentes - Usando scraper especializado\n";
         = new PcComponentesScraper(, );
         = ->extractProductInfo();

        // Si tiene título, actualizar el nombre del producto
        if (isset(['product_name']) && ['product_name']) {
             = ['product_name'];
        }

        // Mostrar información extra si está disponible
        if (isset(['discount']) && ['discount']) {
            echo "  🏷️ Descuento: {['discount']}%\n";
        }
        if (isset(['product_image']) && ['product_image']) {
            echo "  🖼️ Imagen extraída\n";
        }

    } else {
        // Usar scraper genérico para otras tiendas
         = PriceScraper::extractPrice();
    }

    if (!['success']) {
        echo "  ❌ Error: " . ['error'] . "\n";
        ++;

        // Actualizar estado a error
         = ->prepare("
            UPDATE monitored_urls
            SET status = 'error', last_checked = NOW()
            WHERE id = ?
        ");
        ->execute([]);

        continue;
    }

     = ['price'];
    echo "  💰 Precio encontrado: €{}\n";

    // Actualizar precio en la base de datos
     = ->prepare("
        UPDATE monitored_urls
        SET current_price = ?, last_checked = NOW(), status = 'active'
        WHERE id = ?
    ");
    ->execute([, ]);

    // Guardar en historial con información adicional del scraping
     = ->prepare("
        INSERT INTO price_history (url_id, price, scraping_method, extraction_time_ms)
        VALUES (?, ?, ?, ?)
    ");
    ->execute([
        ,
        ,
        ['method'] ?? 'generic',
        ['extraction_time_ms'] ?? null
    ]);

    // Verificar si el precio alcanzó el objetivo
    // Solo notificar si:
    // 1. El precio actual alcanza el objetivo ($newPrice <= $targetPrice)
    // 2. Y el precio anterior estaba SOBRE el objetivo ($oldPrice > $targetPrice) o es la primera vez ($oldPrice === null)
    // Esto evita notificar múltiples veces si el precio ya estaba en el objetivo y sigue bajando
    if ( <=  && ( === null ||  > )) {
        echo "  🎉 ¡Precio objetivo alcanzado! Enviando notificaciones...\n";

        // Obtener métodos de notificación
         = ->prepare("
            SELECT method, contact_info
            FROM notification_methods
            WHERE url_id = ? AND is_active = 1
        ");
        ->execute([]);
         = ->fetchAll();

        foreach ( as ) {
            echo "  📤 Enviando notificación por {['method']} a {['contact_info']}...\n";

             = NotificationService::send(
                ['method'],
                ['contact_info'],
                ,
                 ?? ,
                ,
                
            );

            // Registrar el envío
             = ->prepare("
                INSERT INTO notifications_log (url_id, method, old_price, new_price, status, error_message)
                VALUES (?, ?, ?, ?, ?, ?)
            ");
            ->execute([
                ,
                ['method'],
                ,
                ,
                 ? 'sent' : 'failed',
                 ? null : 'Error al enviar notificación'
            ]);

            if () {
                echo "  ✅ Notificación enviada\n";
                ++;
            } else {
                echo "  ❌ Error al enviar notificación\n";
            }
        }
    } else if ( <= ) {
        echo "  ✓ Precio en objetivo pero ya se había notificado anteriormente\n";
    } else if ( !== null &&  < ) {
        echo "  📉 Precio bajó (€{} → €{}) pero aún no alcanza el objetivo (€{})\n";
    } else if ( !== null &&  > ) {
        echo "  📈 Precio subió (€{} → €{}) - Por encima del objetivo (€{})\n";
    } else {
        echo "  ℹ️ Sin cambios significativos\n";
    }

    ++;

    // Pequeña pausa para no sobrecargar los servidores
    sleep(2);
}

echo "\n" . str_repeat("=", 50) . "\n";
echo "Resumen de ejecución:\n";
echo "  ✓ URLs procesadas: {}\n";
echo "  ✗ Errores: {}\n";
echo "  📧 Notificaciones enviadas: {}\n";
echo "  Finalizado: " . date('Y-m-d H:i:s') . "\n";
echo str_repeat("=", 50) . "\n";
?>
