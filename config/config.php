<?php
/**
 * Configuración segura de la aplicación
 * Lee configuración desde archivo .env y variables de entorno
 */

class Config {
    private static  = null;

    /**
     * Cargar configuración desde archivo .env y variables de entorno
     */
    public static function load() {
        if (self:: !== null) {
            return;
        }

        // Inicializar array de configuración
        self:: = [];

        // Primero cargar variables de entorno del sistema (Docker, etc.)
        foreach ( as  => ) {
            self::[] = ;
        }

         = __DIR__ . '/../.env';

        // Si existe archivo .env, leerlo (sobrescribe vars de entorno)
        if (file_exists()) {
            // Leer archivo .env
             = file(, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ( as ) {
                // Ignorar comentarios
                if (strpos(trim(), '#') === 0) {
                    continue;
                }

                // Parsear línea
                if (strpos(, '=') !== false) {
                    list(, ) = explode('=', , 2);
                     = trim();
                     = trim();

                    // Guardar en array de configuración
                    self::[] = ;

                    // También establecer como variable de entorno
                    putenv("=");
                }
            }
        }
    }

    /**
     * Obtener valor de configuración
     */
    public static function get(,  = null) {
        self::load();
        return isset(self::[]) ? self::[] : ;
    }

    /**
     * Verificar si estamos en producción
     */
    public static function isProduction() {
        return self::get('APP_ENV', 'production') === 'production';
    }

    /**
     * Obtener configuración de base de datos
     */
    public static function getDbConfig() {
        return [
            'host' => self::get('DB_HOST', 'localhost'),
            'name' => self::get('DB_NAME', self::get('MYSQL_DATABASE', 'price_monitor')),
            'user' => self::get('DB_USER', self::get('MYSQL_USER', 'root')),
            'pass' => self::get('DB_PASS', self::get('MYSQL_PASSWORD', self::get('MYSQL_ROOT_PASSWORD', ''))),
            'port' => self::get('DB_PORT', '3306'),
            'charset' => self::get('DB_CHARSET', 'utf8mb4')
        ];
    }
}

// Cargar configuración automáticamente
Config::load();
?>
