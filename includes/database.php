<?php
// Set PHP timezone to Philippines
date_default_timezone_set('Asia/Manila');

// Database configuration
class Database {
    private $host = 'localhost';
    private $db_name = 'attendance_system';
    private $username = 'root';  // Change this to your MySQL username
    private $password = 'muning0328';      // Change this to your MySQL password
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Set MySQL timezone to Philippines
            $this->conn->exec("SET time_zone = '+08:00'");
            
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }
}
?>
