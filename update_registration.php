<?php
// update_registration.php
header('Content-Type: application/json');

session_start();
require_once 'database_config.php';

// 检查是否是管理员（简化版本，您应该添加完整的身份验证）
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    echo json_encode(['success' => false, 'message' => '未授权访问']);
    exit;
}

// 检查参数
if (!isset($_POST['id']) || !isset($_POST['action'])) {
    echo json_encode(['success' => false, 'message' => '缺少必要参数']);
    exit;
}

$registration_id = intval($_POST['id']);
$action = $_POST['action'];

// 验证操作类型
$valid_actions = ['approve', 'reject', 'pending'];
if (!in_array($action, $valid_actions)) {
    echo json_encode(['success' => false, 'message' => '无效的操作类型']);
    exit;
}

// 获取数据库连接
$conn = Database::getConnection();

try {
    // 检查记录是否存在
    $check_sql = "SELECT id FROM student_registrations WHERE id = ?";
    $check_stmt = $conn->prepare($check_sql);
    $check_stmt->execute([$registration_id]);
    
    if (!$check_stmt->fetch()) {
        echo json_encode(['success' => false, 'message' => '找不到指定的报名记录']);
        exit;
    }
    
    // 确定新的状态
    $new_status = '';
    switch ($action) {
        case 'approve': $new_status = 'approved'; break;
        case 'reject': $new_status = 'rejected'; break;
        case 'pending': $new_status = 'pending'; break;
    }
    
    // 更新记录
    $update_sql = "UPDATE student_registrations SET status = ?, updated_time = NOW() WHERE id = ?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->execute([$new_status, $registration_id]);
    
    // 记录操作日志（可选）
    $log_sql = "INSERT INTO admin_logs (admin_id, action, target_id, details, created_at) 
                VALUES (?, ?, ?, ?, NOW())";
    $log_stmt = $conn->prepare($log_sql);
    $admin_id = $_SESSION['admin_id'] ?? 0;
    $action_text = "更新报名状态为: $new_status";
    $details = "报名ID: $registration_id 被更新为 $new_status";
    $log_stmt->execute([$admin_id, $action_text, $registration_id, $details]);
    
    echo json_encode(['success' => true, 'message' => '状态更新成功', 'new_status' => $new_status]);
    
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => '数据库错误: ' . $e->getMessage()]);
}
?>