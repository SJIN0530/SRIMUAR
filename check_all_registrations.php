<?php
// check_all_registrations.php
header('Content-Type: application/json');

session_start();
require_once 'database_config.php';

// 检查参数
if (!isset($_POST['ic_number'])) {
    echo json_encode(['registered_courses' => [], 'message' => '缺少参数']);
    exit;
}

$ic_number = $_POST['ic_number'];

// 获取数据库连接
$conn = Database::getConnection();

try {
    $sql = "SELECT vehicle_type FROM student_registrations WHERE ic_number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$ic_number]);
    
    $registered_courses = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $registered_courses[] = $row['vehicle_type'];
    }
    
    echo json_encode(['registered_courses' => $registered_courses]);
    
} catch (PDOException $e) {
    echo json_encode(['registered_courses' => [], 'message' => '查询错误']);
}
?>