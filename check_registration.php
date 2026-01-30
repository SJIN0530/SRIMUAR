<?php
// check_registration.php
header('Content-Type: application/json');

session_start();
require_once 'database_config.php';

// 检查参数
if (!isset($_POST['ic_number']) || !isset($_POST['vehicle_type'])) {
    echo json_encode(['exists' => false, 'message' => '缺少参数']);
    exit;
}

$ic_number = $_POST['ic_number'];
$vehicle_type = $_POST['vehicle_type'];

// 获取数据库连接
$conn = Database::getConnection();

try {
    $sql = "SELECT id FROM student_registrations WHERE ic_number = ? AND vehicle_type = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$ic_number, $vehicle_type]);
    
    $exists = $stmt->fetch() ? true : false;
    
    echo json_encode(['exists' => $exists]);
    
} catch (PDOException $e) {
    echo json_encode(['exists' => false, 'message' => '查询错误']);
}
?>