<?php
class Database {
    private static $instance = null;
    private $connection;

    private function __construct() {
        // Caminho correto para o arquivo de configuração
        $config = require __DIR__ . '/../config/database.php';

        // Correção das chaves do array
        $this->connection = new mysqli(
            $config['host'],
            $config['username'],
            $config['password'],
            $config['database'],
            $config['port']
        );

        if ($this->connection->connect_error) {
            error_log("Database connection failed: " . $this->connection->connect_error);
            die("Database connection error. Check logs for details.");
        }

        $this->connection->set_charset($config['charset']);
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

    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }

    public function query($sql) {
        $result = $this->connection->query($sql);
        if (!$result) {
            error_log("Query error: " . $this->connection->error);
        }
        return $result;
    }

    public function escape($string) {
        return $this->connection->real_escape_string($string);
    }

    public function getLastError() {
        return $this->connection->error;
    }

    public function getLastInsertId() {
        return $this->connection->insert_id;
    }
}
?>
