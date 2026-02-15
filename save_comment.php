<?php
header('Content-Type: application/json');

// 数据库配置
$servername = "localhost";
$username = "u326148221_sriuser";
$password = "SriMuar@2026!";
$dbname = "u326148221_sri_muar";

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    echo json_encode([
        'success' => false,
        'message' => '数据库连接失败'
    ]);
    exit;
}

// 设置字符集
$conn->set_charset("utf8mb4");

// 获取POST数据 - 移除了course字段
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$rating = isset($_POST['rating']) ? floatval($_POST['rating']) : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

// 验证数据
$errors = [];

if (empty($name)) {
    $errors[] = '姓名不能为空';
}

if ($rating < 1 || $rating > 5) {
    $errors[] = '评分必须在1-5之间';
}

if (empty($comment)) {
    $errors[] = '评价内容不能为空';
}

if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = '邮箱格式不正确';
}

if (!empty($errors)) {
    echo json_encode([
        'success' => false,
        'message' => implode('，', $errors)
    ]);
    exit;
}

// 准备SQL语句 - 移除了course字段
$sql = "INSERT INTO comments (name, email, rating, comment) VALUES (?, ?, ?, ?)";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    echo json_encode([
        'success' => false,
        'message' => '系统错误，请稍后重试'
    ]);
    exit;
}

$stmt->bind_param("ssds", $name, $email, $rating, $comment);

// 执行插入
if ($stmt->execute()) {
    echo json_encode([
        'success' => true,
        'message' => '评价提交成功',
        'id' => $stmt->insert_id
    ]);
} else {
    echo json_encode([
        'success' => false,
        'message' => '保存失败：' . $stmt->error
    ]);
}

$stmt->close();
$conn->close();
?>