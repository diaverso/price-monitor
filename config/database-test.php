<?php
/**
 * Archivo de prueba de conexión - NO usar en producción
 */

class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        try {
            // Conexión directa para pruebas
            $dsn = "mysql:host=localhost;port=3306;dbname=price_monitor;charset=utf8mb4";

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];

            $this->connection = new PDO(
                $dsn,
                'price_monitor_user',
                'MhTfmFhsq1flPATuv1dDLHc59cn+h1WAqqxPlMRnm1w=',
                $options
            );

            echo "Conexión exitosa a la base de datos\n";

        } catch (PDOException $e) {
            die("Error de conexión: " . $e->getMessage());
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

    private function __clone() {}
    public function __wakeup() {
        throw new \Exception("Cannot unserialize singleton");
    }
}
?>
