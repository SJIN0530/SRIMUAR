<?php
// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'database.php';

session_start();

header('Content-Type: application/json');

// 获取最后检查时间
$last_check = $_SESSION['last_history_check'] ?? 0;
$current_time = time();

$db = getDB();
$has_new = false;
$new_count = 0;

if ($db) {
    try {
        // 检查是否有新记录（最近30秒内）
        $check_time = date('Y-m-d H:i:s', $current_time - 30);
        
        $sql = "SELECT COUNT(*) as new_count FROM price_access_logs 
                WHERE created_at > :check_time AND is_active = 1";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([':check_time' => $check_time]);
        $result = $stmt->fetch();
        
        $new_count = $result['new_count'];
        $has_new = ($new_count > 0);
    } catch (Exception $e) {
        error_log("检查新记录失败: " . $e->getMessage());
    }
}

// 更新最后检查时间
$_SESSION['last_history_check'] = $current_time;

echo json_encode([
    'has_new' => $has_new,
    'new_count' => $new_count,
    'timestamp' => date('Y-m-d H:i:s')
]);
?>