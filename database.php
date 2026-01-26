<?php
/**
 * 数据库连接配置文件
 */

class Database {
    private $host = 'localhost';
    private $db_name = 'sri_muar';
    private $username = 'root';  // 请根据实际情况修改
    private $password = '';      // 请根据实际情况修改
    private $conn;
    
    public function getConnection() {
        $this->conn = null;
        
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4",
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->conn->exec("SET NAMES utf8mb4");
        } catch(PDOException $e) {
            error_log("数据库连接失败: " . $e->getMessage());
            return false;
        }
        
        return $this->conn;
    }
    
    /**
     * 测试数据库连接
     */
    public static function testConnection() {
        $database = new Database();
        $conn = $database->getConnection();
        
        if ($conn) {
            echo "数据库连接成功！";
            $conn = null;
            return true;
        } else {
            echo "数据库连接失败！";
            return false;
        }
    }
}

// 全局数据库连接函数
function getDB() {
    static $db = null;
    
    if ($db === null) {
        $database = new Database();
        $db = $database->getConnection();
    }
    
    return $db;
}
?>