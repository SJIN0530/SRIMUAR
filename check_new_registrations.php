<?php
// check_new_registrations.php
session_start();
require_once 'database_config.php';

// 获取参数
$last_check = isset($_GET['last_check']) ? intval($_GET['last_check']) : time();

// 获取数据库连接
$conn = Database::getConnection();

// 查询自上次检查以来的新注册
$sql = "SELECT 
            COUNT(*) as new_count,
            sr.id as reg_id,
            sr.name,
            sr.ic_number,
            sr.phone_number,
            sr.vehicle_type,
            sr.license_class,
            sr.registration_date
        FROM student_registrations sr
        WHERE sr.registration_date > FROM_UNIXTIME(?)
        ORDER BY sr.registration_date DESC";

$stmt = $conn->prepare($sql);
$stmt->execute([$last_check]);
$results = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 更新最后检查时间
$_SESSION['last_notification_check'] = time();

// 返回JSON响应
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'new_count' => count($results),
    'new_registrations' => $results,
    'last_check' => time()
]);
?>