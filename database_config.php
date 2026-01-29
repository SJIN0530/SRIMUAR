<?php
// database_config.php - 数据库配置文件

class Database {
    private static $connection = null;
    
    // 数据库配置
    private static $host = '127.0.0.1'; // 或 'localhost'
    private static $dbname = 'sri_muar';
    private static $username = 'root';
    private static $password = ''; // XAMPP默认密码为空
    
    // 获取数据库连接
    public static function getConnection() {
        if (self::$connection === null) {
            try {
                // 创建PDO连接
                self::$connection = new PDO(
                    "mysql:host=" . self::$host . ";dbname=" . self::$dbname . ";charset=utf8mb4",
                    self::$username,
                    self::$password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                die("数据库连接失败: " . $e->getMessage());
            }
        }
        return self::$connection;
    }
    
    // 检查数据库连接
    public static function checkConnection() {
        try {
            $conn = self::getConnection();
            
            // 检查表是否存在
            $stmt = $conn->query("SHOW TABLES LIKE 'price_access_logs'");
            $tableExists = $stmt->rowCount() > 0;
            
            // 获取记录数
            $count = 0;
            if ($tableExists) {
                $stmt = $conn->query("SELECT COUNT(*) as total FROM price_access_logs");
                $result = $stmt->fetch();
                $count = $result['total'];
            }
            
            return [
                'success' => true,
                'message' => '数据库连接正常',
                'table_exists' => $tableExists,
                'count' => $count
            ];
        } catch (PDOException $e) {
            return [
                'success' => false,
                'message' => '数据库连接失败: ' . $e->getMessage()
            ];
        }
    }
}

// 测试连接（可选）
// $test = Database::getConnection();
// echo "数据库连接成功！";
?>