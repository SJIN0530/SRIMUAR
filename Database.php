<?php
// Database.php
class Database {
    private static $connection = null;
    
    public static function getConnection() {
        if (self::$connection === null) {
            $host = 'localhost';
            $dbname = 'your_database_name'; // 替换为你的数据库名
            $username = 'root'; // 默认XAMPP用户名
            $password = ''; // 默认XAMPP密码为空
            
            try {
                self::$connection = new PDO(
                    "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
                    $username,
                    $password,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
            } catch (PDOException $e) {
                die("数据库连接失败: " . $e->getMessage());
            }
        }
        return self::$connection;
    }
    
    public static function checkConnection() {
        try {
            $conn = self::getConnection();
            return [
                'success' => true,
                'message' => '数据库连接正常'
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => '数据库连接错误: ' . $e->getMessage()
            ];
        }
    }
}
?>