<?php
// check_registration.php
header('Content-Type: application/json');

session_start();

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sri_muar');

// 检查参数
if (!isset($_POST['ic_number']) || !isset($_POST['vehicle_type']) || !isset($_POST['license_class'])) {
    echo json_encode(['exists' => false, 'message' => '缺少参数']);
    exit;
}

$ic_number = $_POST['ic_number'];
$vehicle_type = $_POST['vehicle_type'];
$license_class = $_POST['license_class'];

// 创建数据库连接
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    echo json_encode(['exists' => false, 'message' => '数据库连接失败']);
    exit;
}
$conn->set_charset("utf8mb4");

try {
    $sql = "SELECT id FROM student_registrations WHERE ic_number = ? AND vehicle_type = ? AND license_class = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sss", $ic_number, $vehicle_type, $license_class);
    $stmt->execute();
    $stmt->store_result();
    
    $exists = $stmt->num_rows > 0;
    
    echo json_encode(['exists' => $exists]);
    
    $stmt->close();
    $conn->close();
    
} catch (Exception $e) {
    echo json_encode(['exists' => false, 'message' => '查询错误']);
}
?>