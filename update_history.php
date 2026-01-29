<?php
// update_history.php - 更新停留时间
session_start();

// 数据库连接
$host = '127.0.0.1';
$dbname = 'sri_muar';
$username = 'root';
$password = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $log_id = $_POST['log_id'] ?? 0;
    $duration_seconds = $_POST['duration_seconds'] ?? 0;
    
    if ($log_id > 0 && is_numeric($duration_seconds)) {
        try {
            $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // 更新停留时间
            $sql = "UPDATE price_access_logs SET duration_seconds = ? WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([$duration_seconds, $log_id]);
            
            echo json_encode(['success' => true, 'updated' => $stmt->rowCount()]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'error' => '无效的参数']);
    }
} else {
    echo json_encode(['success' => false, 'error' => '无效的请求方法']);
}
?>