<?php
// verify_handler.php - 处理验证码验证请求
session_start();

// 开启错误报告（生产环境应关闭）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置响应头
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// 记录请求信息（调试用）
error_log('[' . date('Y-m-d H:i:s') . '] 验证请求: ' . json_encode([
    'method' => $_SERVER['REQUEST_METHOD'],
    'post_data' => $_POST,
    'session_id' => session_id(),
    'session_data_exists' => isset($_SESSION['verification_data'])
]));

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => '请使用POST方法提交'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查是否有验证数据
if (!isset($_SESSION['verification_data'])) {
    error_log('验证失败：没有找到验证数据');
    
    echo json_encode([
        'success' => false,
        'message' => '验证会话已过期或不存在，请重新获取验证码。',
        'action' => 'redirect',
        'redirect_url' => 'price_information.php'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取验证数据
$verification_data = $_SESSION['verification_data'];

// 检查是否已经验证过
if (isset($verification_data['verified']) && $verification_data['verified'] === true) {
    error_log('验证失败：已经验证过了');
    
    echo json_encode([
        'success' => false,
        'message' => '您已经验证过了，请直接查看价格。',
        'action' => 'redirect',
        'redirect_url' => 'price_display.php'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查验证码是否已过期（10分钟）
$created_at = $verification_data['created_at'] ?? 0;
$current_time = time();
$time_elapsed = $current_time - $created_at;
$expiry_time = 600; // 10分钟 = 600秒

if ($time_elapsed > $expiry_time) {
    error_log('验证失败：验证码已过期，已过 ' . $time_elapsed . ' 秒');
    
    // 清除过期会话
    unset($_SESSION['verification_data']);
    
    echo json_encode([
        'success' => false,
        'message' => '验证码已过期，请在10分钟内完成验证。',
        'action' => 'redirect',
        'redirect_url' => 'price_information.php?error=expired'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查尝试次数
$attempts = $verification_data['attempts'] ?? 0;
$max_attempts = 5;

if ($attempts >= $max_attempts) {
    error_log('验证失败：尝试次数过多 - ' . $attempts . ' 次');
    
    echo json_encode([
        'success' => false,
        'message' => '验证失败次数过多，请重新获取验证码。',
        'action' => 'redirect',
        'redirect_url' => 'price_information.php?error=attempts_exceeded'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取提交的验证码
$full_code = $_POST['full_code'] ?? '';

if (empty($full_code)) {
    error_log('验证失败：验证码为空');
    
    echo json_encode([
        'success' => false,
        'message' => '请输入验证码。'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 验证验证码格式（6位数字）
if (!preg_match('/^\d{6}$/', $full_code)) {
    error_log('验证失败：验证码格式错误 - ' . $full_code);
    
    echo json_encode([
        'success' => false,
        'message' => '验证码必须是6位数字。'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取存储的验证码
$stored_code = $verification_data['verification_code'] ?? '';

error_log('验证对比：提交=' . $full_code . '，存储=' . $stored_code);

// 增加尝试次数
$_SESSION['verification_data']['attempts'] = $attempts + 1;
$new_attempts = $_SESSION['verification_data']['attempts'];

// 验证验证码
if ($full_code === $stored_code) {
    // 验证成功
    $_SESSION['verification_data']['verified'] = true;
    $_SESSION['verification_data']['verified_at'] = time();
    $_SESSION['verification_data']['verification_time'] = $current_time;
    
    error_log('验证成功：用户 ' . $verification_data['email'] . ' 验证成功');
    
    // 记录成功日志
    logSuccessVerification(
        $verification_data['email'],
        $verification_data['full_name'],
        $verification_data['ic_number'],
        session_id()
    );
    
    echo json_encode([
        'success' => true,
        'message' => '验证成功！正在跳转到价格页面...',
        'action' => 'redirect',
        'redirect_url' => 'price_display.php',
        'data' => [
            'email' => $verification_data['email'],
            'name' => $verification_data['full_name'],
            'attempts_used' => $new_attempts,
            'verification_time' => date('Y-m-d H:i:s'),
            'session_id' => session_id()
        ]
    ], JSON_UNESCAPED_UNICODE);
    
} else {
    // 验证失败
    $remaining_attempts = $max_attempts - $new_attempts;
    
    error_log('验证失败：验证码不匹配，剩余尝试次数 ' . $remaining_attempts);
    
    echo json_encode([
        'success' => false,
        'message' => '验证码不正确' . ($remaining_attempts > 0 ? '，剩余尝试次数: ' . $remaining_attempts : ''),
        'remaining_attempts' => $remaining_attempts,
        'attempts_used' => $new_attempts
    ], JSON_UNESCAPED_UNICODE);
}

exit;

// =================== 功能函数 ===================

/**
 * 记录成功验证
 */
function logSuccessVerification($email, $name, $ic_number, $session_id) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/successful_verifications.log';
    
    // 部分隐藏敏感信息
    $masked_ic = substr($ic_number, 0, 4) . '****' . substr($ic_number, -4);
    $masked_email = substr($email, 0, 3) . '***' . substr($email, strpos($email, '@'));
    
    $logEntry = sprintf(
        "[%s] [Session: %s]\n" .
        "用户: %s\n" .
        "邮箱: %s\n" .
        "身份证: %s\n" .
        "验证时间: %s\n" .
        "IP地址: %s\n" .
        str_repeat("=", 60) . "\n\n",
        date('Y-m-d H:i:s'),
        $session_id,
        $name,
        $masked_email,
        $masked_ic,
        date('Y-m-d H:i:s'),
        $_SERVER['REMOTE_ADDR'] ?? '未知'
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>