<?php
// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'database.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $record_id = $_POST['record_id'] ?? '';
    $duration = intval($_POST['duration'] ?? 0);
    
    if (empty($record_id) || $duration <= 0) {
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => '无效的参数']);
        exit();
    }
    
    $db = getDB();
    
    if ($db) {
        try {
            // 更新停留时间
            $sql = "UPDATE price_access_logs SET duration = :duration WHERE record_id = :record_id AND is_active = 1";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                ':duration' => $duration,
                ':record_id' => $record_id
            ]);
            
            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true, 'message' => '停留时间已更新', 'duration' => $duration]);
            } else {
                echo json_encode(['success' => false, 'message' => '记录未找到或已删除']);
            }
        } catch (Exception $e) {
            error_log("更新停留时间失败: " . $e->getMessage());
            echo json_encode(['success' => false, 'message' => '更新失败: ' . $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => '数据库连接失败']);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => '方法不允许']);
}
?>