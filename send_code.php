<?php
// send_code.php - 发送验证码到用户邮箱（修复版）
session_start();

// 开启错误报告（调试用）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置时区
date_default_timezone_set('Asia/Kuala_Lumpur');

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// 处理预检请求
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// 如果是测试请求
if (isset($_GET['test']) || (isset($_POST['test']) && $_POST['test'] == '1')) {
    echo json_encode([
        'success' => true,
        'message' => '服务器连接正常',
        'server_info' => [
            'php_version' => phpversion(),
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'session_id' => session_id(),
            'timestamp' => date('Y-m-d H:i:s')
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => '请使用POST方法提交'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取POST数据
$ic_number = isset($_POST['ic_number']) ? trim($_POST['ic_number']) : '';
$full_name = isset($_POST['full_name']) ? trim($_POST['full_name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';

// 验证必需字段
if (empty($ic_number) || empty($full_name) || empty($email)) {
    echo json_encode([
        'success' => false,
        'message' => '请填写所有必填字段',
        'debug' => [
            'ic_number' => $ic_number,
            'full_name' => $full_name,
            'email' => $email
        ]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 验证IC号码（12位数字）
if (!preg_match('/^\d{12}$/', $ic_number)) {
    echo json_encode([
        'success' => false,
        'message' => '身份证号码必须是12位数字',
        'debug' => ['ic_number' => $ic_number]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 验证邮箱格式
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode([
        'success' => false,
        'message' => '邮箱格式不正确',
        'debug' => ['email' => $email]
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 生成验证码（6位数字）
$verification_code = sprintf('%06d', random_int(0, 999999));

// 存储到SESSION
$_SESSION['verification_data'] = [
    'ic_number' => $ic_number,
    'full_name' => $full_name,
    'email' => $email,
    'verification_code' => $verification_code,
    'created_at' => time(),
    'attempts' => 0,
    'verified' => false
];

// 记录验证码（测试用）
error_log('[' . date('Y-m-d H:i:s') . '] 生成验证码: ' . $verification_code . ' 给邮箱: ' . $email);

// 准备响应数据
$responseData = [
    'success' => true,
    'message' => '验证码已生成并发送到 ' . $email,
    'session_id' => session_id(),
    'debug' => [
        'verification_code' => $verification_code, // 仅用于测试
        'email' => $email,
        'timestamp' => date('Y-m-d H:i:s'),
        'note' => '当前为测试模式，不会实际发送邮件'
    ]
];

// 输出JSON响应
echo json_encode($responseData, JSON_UNESCAPED_UNICODE);
exit;
?>