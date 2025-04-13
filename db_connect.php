<?php
require_once 'config.php';

class Database {
    private $connection;
    private static $instance;

    private function __construct() {
        try {
            $this->connection = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
            $this->connection->set_charset("utf8mb4");
            
            if ($this->connection->connect_error) {
                throw new Exception("Veritabanı bağlantı hatası: " . $this->connection->connect_error);
            }
        } catch (Exception $e) {
            error_log($e->getMessage());
            die("Veritabanına bağlanırken bir hata oluştu. Lütfen daha sonra tekrar deneyin.");
        }
    }

    // Singleton desen - yalnızca bir veritabanı bağlantısını güvence altına alır
    public static function getInstance() {
        if (!self::$instance) {
            self::$instance = new Database();
        }
        return self::$instance;
    }

    // Veritabanı bağlantısını döndürür
    public function getConnection() {
        return $this->connection;
    }

    // SQL sorgusu hazırlama ve yürütme
    public function prepare($sql) {
        return $this->connection->prepare($sql);
    }

    // Sorgu güvenli hale getirme (SQL enjeksiyonuna karşı koruma)
    public function escape($value) {
        return $this->connection->real_escape_string($value);
    }

    // Son eklenen kaydın ID'sini al
    public function lastInsertId() {
        return $this->connection->insert_id;
    }

    // Etkilenen satır sayısını al
    public function affectedRows() {
        return $this->connection->affected_rows;
    }

    // Veritabanı bağlantısını kapatma
    public function close() {
        if ($this->connection) {
            $this->connection->close();
            self::$instance = null;
        }
    }

    // SQL hatası bilgisini al
    public function error() {
        return $this->connection->error;
    }

    // Sorgu çalıştırma ve sonuçları dizi olarak döndürme
    public function query($sql) {
        $result = $this->connection->query($sql);
        
        if (!$result) {
            error_log("Sorgu hatası: " . $this->connection->error . " için sorgu: " . $sql);
            return false;
        }
        
        if ($result === true) {
            return true;
        }

        $data = [];
        while ($row = $result->fetch_assoc()) {
            $data[] = $row;
        }
        
        $result->free();
        return $data;
    }

    // Bir tek satırı döndürme
    public function queryOne($sql) {
        $result = $this->connection->query($sql);
        
        if (!$result) {
            error_log("Sorgu hatası: " . $this->connection->error . " için sorgu: " . $sql);
            return false;
        }
        
        $row = $result->fetch_assoc();
        $result->free();
        
        return $row;
    }

    // Değeri döndürme (tek bir değer beklendiğinde)
    public function queryValue($sql) {
        $result = $this->connection->query($sql);
        
        if (!$result) {
            error_log("Sorgu hatası: " . $this->connection->error . " için sorgu: " . $sql);
            return false;
        }
        
        $row = $result->fetch_row();
        $result->free();
        
        return $row ? $row[0] : null;
    }
}
?>