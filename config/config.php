<?php
/**
 * Configuración segura de la aplicación
 * Lee configuración desde archivo .env y variables de entorno
 */

class Config {
    private static $config = null;

    /**
     * Cargar configuración desde archivo .env y variables de entorno
     */
    public static function load() {
        if (self::$config !== null) {
            return;
        }

        // Inicializar array de configuración
        self::$config = [];

        // Primero cargar variables de entorno del sistema (Docker, etc.)
        foreach ($_SERVER as $key => $value) {
            self::$config[$key] = $value;
        }

        $envFile = __DIR__ . '/../.env';

        // Si existe archivo .env, leerlo (sobrescribe vars de entorno)
        if (file_exists($envFile)) {
            // Leer archivo .env
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

            foreach ($lines as $line) {
                // Ignorar comentarios
                if (strpos(trim($line), '#') === 0) {
                    continue;
                }

                // Parsear línea
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);

                    // Guardar en array de configuración
                    self::$config[$key] = $value;

                    // También establecer como variable de entorno
                    putenv("$key=$value");
                }
            }
        }
    }

    /**
     * Obtener valor de configuración
     */
    public static function get($key, $default = null) {
        self::load();
        return isset(self::$config[$key]) ? self::$config[$key] : $default;
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
