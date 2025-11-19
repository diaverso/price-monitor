<?php
/**
 * Configuración segura de base de datos
 * Utiliza variables de entorno para mayor seguridad
 */

require_once __DIR__ . '/config.php';

class Database {
    private static $instance = null;
    private $connection;
    private $config;

    private function __construct() {
        try {
            // Obtener configuración desde .env
            $this->config = Config::getDbConfig();

            $dsn = sprintf(
                "mysql:host=%s;port=%s;dbname=%s;charset=%s",
                $this->config['host'],
                $this->config['port'],
                $this->config['name'],
                $this->config['charset']
            );

            $options = [
                // Modo de error: lanzar excepciones
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

                // Fetch mode por defecto
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

                // Deshabilitar emulación de prepared statements (más seguro)
                PDO::ATTR_EMULATE_PREPARES => false,

                // Usar conexión persistente para mejor rendimiento
                PDO::ATTR_PERSISTENT => false,

                // Timeout de conexión
                PDO::ATTR_TIMEOUT => 5
            ];

            $this->connection = new PDO(
                $dsn,
                $this->config['user'],
                $this->config['pass'],
                $options
            );

            // Log de conexión exitosa (solo en desarrollo)
            if (!Config::isProduction()) {
                error_log("Database connection established successfully");
            }

        } catch (PDOException $e) {
            // En producción, no mostrar detalles del error
            if (Config::isProduction()) {
                error_log("Database connection error: " . $e->getMessage());
                die("Error de conexión a la base de datos. Por favor contacta al administrador.");
            } else {
                die("Error de conexión: " . $e->getMessage());
            }
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        return $this->connection;
    }

    // Prevenir clonación y deserialización
    private function __clone() {}

    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}
?>
