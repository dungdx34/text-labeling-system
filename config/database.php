<?php
/**
 * Database Configuration - Fixed for MariaDB compatibility
 * File: config/database.php
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'text_labeling_system';
    private $username = 'root';
    private $password = '';
    private $charset = 'utf8mb4';
    private $port = 3306;
    public $conn;

    /**
     * Database connection with error handling
     */
    public function getConnection() {
        $this->conn = null;

        try {
            // DSN for MariaDB/MySQL
            $dsn = "mysql:host=" . $this->host . 
                   ";port=" . $this->port .
                   ";dbname=" . $this->db_name . 
                   ";charset=" . $this->charset;
            
            // PDO options for better compatibility
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ];

            $this->conn = new PDO($dsn, $this->username, $this->password, $options);
            
            // Set SQL mode for better compatibility
            $this->conn->exec("SET sql_mode = 'STRICT_TRANS_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO'");
            
        } catch(PDOException $exception) {
            error_log("Connection error: " . $exception->getMessage());
            
            // More detailed error for development
            if (defined('DEBUG') && DEBUG === true) {
                throw new Exception("Database connection failed: " . $exception->getMessage());
            } else {
                throw new Exception("Database connection failed. Please check your configuration.");
            }
        }

        return $this->conn;
    }

    /**
     * Test database connection
     */
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            if ($conn) {
                $stmt = $conn->query("SELECT 1 as test");
                $result = $stmt->fetch();
                return $result['test'] == 1;
            }
        } catch (Exception $e) {
            error_log("Database test failed: " . $e->getMessage());
            return false;
        }
        return false;
    }

    /**
     * Check if all required tables exist
     */
    public function checkTables() {
        try {
            $conn = $this->getConnection();
            
            $required_tables = [
                'users', 'documents', 'labeling_tasks', 'document_groups',
                'document_group_items', 'sentence_selections', 'writing_styles',
                'edited_summaries', 'reviews', 'activity_logs', 'system_settings'
            ];
            
            $existing_tables = [];
            $missing_tables = [];
            
            $stmt = $conn->query("SHOW TABLES");
            while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
                $existing_tables[] = $row[0];
            }
            
            foreach ($required_tables as $table) {
                if (!in_array($table, $existing_tables)) {
                    $missing_tables[] = $table;
                }
            }
            
            return [
                'existing' => $existing_tables,
                'missing' => $missing_tables,
                'all_present' => empty($missing_tables)
            ];
            
        } catch (Exception $e) {
            error_log("Table check failed: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Get database version and type
     */
    public function getDatabaseInfo() {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->query("SELECT VERSION() as version");
            $result = $stmt->fetch();
            
            return [
                'version' => $result['version'],
                'type' => (stripos($result['version'], 'mariadb') !== false) ? 'MariaDB' : 'MySQL'
            ];
            
        } catch (Exception $e) {
            error_log("Database info check failed: " . $e->getMessage());
            return ['error' => $e->getMessage()];
        }
    }

    /**
     * Execute safe query with error handling
     */
    public function safeQuery($sql, $params = []) {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query error: " . $e->getMessage() . " SQL: " . $sql);
            throw new Exception("Database query failed: " . $e->getMessage());
        }
    }

    /**
     * Check if specific view exists and works
     */
    public function checkView($viewName) {
        try {
            $conn = $this->getConnection();
            $stmt = $conn->query("SELECT COUNT(*) as count FROM information_schema.views WHERE table_schema = '{$this->db_name}' AND table_name = '{$viewName}'");
            $result = $stmt->fetch();
            
            if ($result['count'] > 0) {
                // Test if view actually works
                $stmt = $conn->query("SELECT * FROM {$viewName} LIMIT 1");
                return true;
            }
            return false;
            
        } catch (Exception $e) {
            error_log("View check failed for {$viewName}: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Initialize database with proper charset and collation
     */
    public function initializeDatabase() {
        try {
            // Connect without specifying database first
            $dsn = "mysql:host=" . $this->host . ";port=" . $this->port . ";charset=" . $this->charset;
            $conn = new PDO($dsn, $this->username, $this->password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            ]);
            
            // Create database if it doesn't exist
            $conn->exec("CREATE DATABASE IF NOT EXISTS `{$this->db_name}` 
                        DEFAULT CHARACTER SET utf8mb4 
                        COLLATE utf8mb4_unicode_ci");
            
            // Use the database
            $conn->exec("USE `{$this->db_name}`");
            
            return true;
            
        } catch (Exception $e) {
            error_log("Database initialization failed: " . $e->getMessage());
            return false;
        }
    }
}

// Auto-configuration and testing
if (basename($_SERVER['PHP_SELF']) == 'database.php') {
    // This code runs when the file is accessed directly for testing
    header('Content-Type: application/json');
    
    try {
        $db = new Database();
        
        $result = [
            'status' => 'success',
            'connection' => $db->testConnection(),
            'database_info' => $db->getDatabaseInfo(),
            'tables' => $db->checkTables(),
            'views' => [
                'user_performance' => $db->checkView('user_performance'),
                'daily_stats' => $db->checkView('daily_stats'), 
                'monthly_stats' => $db->checkView('monthly_stats')
            ],
            'timestamp' => date('Y-m-d H:i:s')
        ];
        
        echo json_encode($result, JSON_PRETTY_PRINT);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode([
            'status' => 'error',
            'message' => $e->getMessage(),
            'timestamp' => date('Y-m-d H:i:s')
        ], JSON_PRETTY_PRINT);
    }
}
?>