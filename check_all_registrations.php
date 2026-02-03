<?php
// check_all_registrations.php
header('Content-Type: application/json');

session_start();

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sri_muar');

// 检查参数
if (!isset($_POST['ic_number'])) {
    echo json_encode(['registered_courses' => [], 'message' => '缺少参数']);
    exit;
}

$ic_number = $_POST['ic_number'];

// 创建数据库连接
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['registered_courses' => [], 'message' => '数据库连接失败']);
    exit;
}
$conn->set_charset("utf8mb4");

try {
    $sql = "SELECT vehicle_type, license_class FROM student_registrations WHERE ic_number = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $ic_number);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $registered_courses = [];
    while ($row = $result->fetch_assoc()) {
        $registered_courses[] = $row;
    }
    
    echo json_encode(['registered_courses' => $registered_courses]);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['registered_courses' => [], 'message' => '查询错误']);
}
?>