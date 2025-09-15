<?php
// Database configuration
class Database {
    private $host = 'localhost';
    private $db_name = 'text_labeling_system';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    public $pdo;

    public function getConnection() {
        $this->pdo = null;

        try {
            $dsn = "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=" . $this->charset;
            
            $options = array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            );

            $this->pdo = new PDO($dsn, $this->username, $this->password, $options);
            
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
            die();
        }

        return $this->pdo;
    }
}

// Create global PDO connection
try {
    $database = new Database();
    $pdo = $database->getConnection();
    
    // Test connection
    if (!$pdo) {
        throw new Exception("Failed to create PDO connection");
    }
    
} catch (Exception $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>