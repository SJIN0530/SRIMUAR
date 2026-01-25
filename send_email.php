<?php
// send_code.php - 发送验证码到用户邮箱

// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置时区
date_default_timezone_set('Asia/Kuala_Lumpur');

// 启动会话
session_start();

// 配置设置
$config = [
    'sender_email' => 'siewjinstudent@gmail.com',    // 发件人邮箱（你的邮箱）
    'sender_name' => 'SRI MUAR 皇城驾驶学院',        // 发件人名称
    'rate_limit_time' => 60,                          // 1分钟
    'max_submissions' => 3,
    'verification_code_expiry' => 600,                // 验证码有效期10分钟
    'test_mode' => false,                            // 设为true时不真实发送邮件
    'enable_logging' => true
];

// 设置响应头
header('Content-Type: application/json; charset=utf-8');

// 检查请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false, 
        'message' => '请使用POST方法提交',
        'debug' => $_SERVER
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查频率限制
if (!checkRateLimit($config['rate_limit_time'], $config['max_submissions'])) {
    http_response_code(429);
    echo json_encode([
        'success' => false, 
        'message' => '请求过于频繁，请稍后再试。'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 获取请求数据
$data = getRequestData();
if (!$data) {
    echo json_encode([
        'success' => false, 
        'message' => '没有收到数据'
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 提取并验证数据
$ic_number = cleanInput($data['ic_number'] ?? '');
$full_name = cleanInput($data['full_name'] ?? '');
$email = cleanInput($data['email'] ?? '');

// 验证数据
$validation = validateData($ic_number, $full_name, $email);
if (!$validation['success']) {
    echo json_encode([
        'success' => false, 
        'message' => $validation['message'],
        'errors' => $validation['errors']
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

// 生成验证码（6位数字）
$verification_code = generateVerificationCode();

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

// 记录到日志
if ($config['enable_logging']) {
    logVerification($ic_number, $full_name, $email, $verification_code, 'GENERATED');
}

// 发送验证码邮件
if ($config['test_mode']) {
    // 测试模式
    $mail_sent = true;
    $status = 'TEST MODE';
    $response_message = '测试模式：验证码已生成，邮件发送已禁用。验证码：' . $verification_code;
} else {
    // 真实发送模式
    $mail_sent = sendVerificationEmail(
        $email,                    // 收件人：用户填写的邮箱
        $full_name,                // 收件人姓名
        $verification_code,        // 验证码
        $config['sender_email'],   // 发件人：你的邮箱
        $config['sender_name']     // 发件人名称
    );
    
    $status = $mail_sent ? 'SENT' : 'FAILED';
    $response_message = $mail_sent 
        ? '验证码已发送到您的邮箱，请在10分钟内完成验证。' 
        : '邮件发送失败，请检查邮箱地址是否正确。';
}

// 记录发送状态
if ($config['enable_logging']) {
    logVerification($ic_number, $full_name, $email, $verification_code, $status);
}

// 返回响应
if ($mail_sent) {
    echo json_encode([
        'success' => true,
        'message' => $response_message,
        'session_id' => session_id(),
        'data' => [
            'email' => maskEmail($email), // 部分隐藏邮箱保护隐私
            'expires_in' => $config['verification_code_expiry'] / 60 . '分钟'
        ],
        'debug' => $config['test_mode'] ? [
            'verification_code' => $verification_code,
            'test_mode' => true
        ] : null
    ], JSON_UNESCAPED_UNICODE);
} else {
    echo json_encode([
        'success' => false,
        'message' => $response_message,
        'session_id' => session_id(),
        'alternative_contact' => '如有问题，请联系我们：06-981 2000'
    ], JSON_UNESCAPED_UNICODE);
}

exit;

// =================== 功能函数 ===================

/**
 * 获取请求数据
 */
function getRequestData() {
    $data = [];
    
    // 尝试从JSON获取数据
    $json_input = file_get_contents('php://input');
    if (!empty($json_input)) {
        $data = json_decode($json_input, true) ?? [];
    }
    
    // 如果JSON为空，尝试从表单获取
    if (empty($data) && !empty($_POST)) {
        $data = $_POST;
    }
    
    return $data;
}

/**
 * 清理输入数据
 */
function cleanInput($input) {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    return $input;
}

/**
 * 检查频率限制
 */
function checkRateLimit($timeLimit, $maxSubmissions) {
    $sessionKey = 'code_requests';
    
    if (!isset($_SESSION[$sessionKey])) {
        $_SESSION[$sessionKey] = [
            'count' => 0,
            'first_time' => time()
        ];
    }
    
    $data = $_SESSION[$sessionKey];
    
    // 如果超过时间限制，重置计数器
    if (time() - $data['first_time'] > $timeLimit) {
        $_SESSION[$sessionKey] = [
            'count' => 1,
            'first_time' => time()
        ];
        return true;
    }
    
    // 检查是否超过限制
    if ($data['count'] >= $maxSubmissions) {
        return false;
    }
    
    $_SESSION[$sessionKey]['count']++;
    return true;
}

/**
 * 验证数据
 */
function validateData($ic_number, $full_name, $email) {
    $errors = [];
    
    // 验证身份证号码（12位数字）
    if (empty($ic_number)) {
        $errors['ic_number'] = '身份证号码不能为空';
    } elseif (!preg_match('/^\d{12}$/', $ic_number)) {
        $errors['ic_number'] = '身份证号码必须是12位数字';
    }
    
    // 验证姓名
    if (empty($full_name)) {
        $errors['full_name'] = '姓名不能为空';
    } elseif (strlen($full_name) > 50) {
        $errors['full_name'] = '姓名不能超过50个字符';
    }
    
    // 验证邮箱
    if (empty($email)) {
        $errors['email'] = '邮箱地址不能为空';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = '邮箱格式不正确';
    }
    
    return [
        'success' => empty($errors),
        'message' => empty($errors) ? '数据验证通过' : '请检查以下错误',
        'errors' => $errors
    ];
}

/**
 * 生成验证码（6位数字）
 */
function generateVerificationCode() {
    return sprintf('%06d', random_int(0, 999999));
}

/**
 * 发送验证码邮件
 */
function sendVerificationEmail($to_email, $to_name, $verification_code, $from_email, $from_name) {
    // 检查邮件功能是否可用
    if (!function_exists('mail')) {
        error_log('邮件功能不可用');
        return false;
    }
    
    // 邮件主题
    $subject = "SRI MUAR 验证码 - " . $verification_code;
    
    // 构建邮件内容
    $email_data = buildVerificationEmail($to_name, $verification_code);
    
    // 清理邮件头，防止注入
    $subject = cleanEmailHeader($subject);
    $from_email = cleanEmailHeader($from_email);
    $from_name = cleanEmailHeader($from_name);
    
    // 创建边界
    $boundary = md5(time());
    
    // 构建邮件头
    $headers = [];
    $headers[] = "From: {$from_name} <{$from_email}>";
    $headers[] = "Reply-To: {$from_email}";
    $headers[] = "X-Mailer: PHP/" . phpversion();
    $headers[] = "MIME-Version: 1.0";
    $headers[] = "Content-Type: multipart/alternative; boundary=\"{$boundary}\"";
    $headers[] = "X-Priority: 1 (High)";
    $headers[] = "Importance: High";
    
    // 构建邮件体
    $body = "--{$boundary}\r\n";
    $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($email_data['text_content']));
    
    $body .= "--{$boundary}\r\n";
    $body .= "Content-Type: text/html; charset=UTF-8\r\n";
    $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
    $body .= chunk_split(base64_encode($email_data['html_content']));
    
    $body .= "--{$boundary}--";
    
    $headers = implode("\r\n", $headers);
    
    try {
        $result = mail($to_email, $subject, $body, $headers);
        
        if ($result) {
            error_log("验证码邮件成功发送到: {$to_email}, 验证码: {$verification_code}");
        } else {
            error_log("验证码邮件发送失败到: {$to_email}");
        }
        
        return $result;
    } catch (Exception $e) {
        error_log("邮件发送异常: " . $e->getMessage());
        return false;
    }
}

/**
 * 构建验证码邮件内容
 */
function buildVerificationEmail($name, $verification_code) {
    $current_time = date('Y-m-d H:i:s');
    $expiry_time = date('Y-m-d H:i:s', time() + 600); // 10分钟后
    
    $html_content = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SRI MUAR 验证码</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; line-height: 1.6; color: #333; background: #f7f9fc; margin: 0; padding: 20px; }
        .email-container { max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; overflow: hidden; box-shadow: 0 5px 30px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #0056b3 0%, #003d82 100%); color: white; padding: 30px; text-align: center; }
        .header h1 { margin: 0 0 10px 0; font-size: 24px; font-weight: 600; }
        .header p { margin: 0; opacity: 0.9; }
        .content { padding: 30px; }
        .verification-box { background: linear-gradient(135deg, #f8fafc 0%, #e8f4ff 100%); border: 2px solid #0056b3; border-radius: 12px; padding: 30px; text-align: center; margin: 20px 0; }
        .verification-code { font-size: 48px; font-weight: 700; color: #0056b3; letter-spacing: 10px; margin: 20px 0; font-family: 'Courier New', monospace; }
        .expiry-info { background: #fff8e1; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; border-radius: 8px; }
        .footer { text-align: center; padding: 20px; background: #f8f9fa; color: #666; font-size: 13px; border-top: 1px solid #eaeaea; line-height: 1.5; }
        .warning { color: #d32f2f; font-weight: 600; }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>🚗 SRI MUAR 皇城驾驶学院</h1>
            <p>课程价格查询验证码</p>
        </div>
        
        <div class="content">
            <p>尊敬的 <strong>{$name}</strong>，</p>
            
            <p>您正在查询 SRI MUAR 皇城驾驶学院的课程价格信息。请使用以下验证码完成验证：</p>
            
            <div class="verification-box">
                <h3 style="margin-top: 0; color: #0056b3;">您的验证码</h3>
                <div class="verification-code">{$verification_code}</div>
                <p>请在10分钟内使用此验证码完成验证</p>
            </div>
            
            <div class="expiry-info">
                <h4 style="margin-top: 0; color: #ff9800;">⏰ 验证码有效期</h4>
                <p><strong>生成时间：</strong>{$current_time}</p>
                <p><strong>过期时间：</strong>{$expiry_time}</p>
            </div>
            
            <div style="margin: 25px 0; padding: 20px; background: #f0f7ff; border-radius: 8px;">
                <h4 style="margin-top: 0; color: #0056b3;">🔒 安全提示</h4>
                <ul style="margin: 10px 0; padding-left: 20px; color: #555;">
                    <li>此验证码仅用于查询课程价格</li>
                    <li>请勿将此验证码分享给他人</li>
                    <li>如果您没有请求此验证码，请忽略此邮件</li>
                    <li>验证码将在10分钟后自动失效</li>
                </ul>
            </div>
            
            <p>如需帮助，请联系我们：</p>
            <ul style="color: #555;">
                <li>📞 电话：06-981 2000</li>
                <li>📧 邮箱：info@srimuar.com</li>
                <li>📍 地址：No. 123, Jalan Mawar, Taman Sri Muar, 84000 Muar, Johor</li>
            </ul>
        </div>
        
        <div class="footer">
            <p class="warning">⚠️ 请勿回复此邮件，此邮箱仅用于发送验证码。</p>
            <p>发送时间：{$current_time}</p>
            <p>© " . date('Y') . " SRI MUAR 皇城驾驶学院 - 专业驾驶培训</p>
        </div>
    </div>
</body>
</html>
HTML;

    $text_content = "SRI MUAR 皇城驾驶学院 - 验证码\n";
    $text_content .= "========================================\n\n";
    $text_content .= "尊敬的 {$name}，\n\n";
    $text_content .= "您正在查询 SRI MUAR 皇城驾驶学院的课程价格信息。\n";
    $text_content .= "请使用以下验证码完成验证：\n\n";
    $text_content .= "验证码：{$verification_code}\n\n";
    $text_content .= "有效期：10分钟\n";
    $text_content .= "生成时间：{$current_time}\n";
    $text_content .= "过期时间：{$expiry_time}\n\n";
    $text_content .= "安全提示：\n";
    $text_content .= "- 此验证码仅用于查询课程价格\n";
    $text_content .= "- 请勿将此验证码分享给他人\n";
    $text_content .= "- 如果您没有请求此验证码，请忽略此邮件\n";
    $text_content .= "- 验证码将在10分钟后自动失效\n\n";
    $text_content .= "如需帮助，请联系我们：\n";
    $text_content .= "电话：06-981 2000\n";
    $text_content .= "邮箱：info@srimuar.com\n";
    $text_content .= "地址：No. 123, Jalan Mawar, Taman Sri Muar, 84000 Muar, Johor\n\n";
    $text_content .= "⚠️ 请勿回复此邮件，此邮箱仅用于发送验证码。\n";
    $text_content .= "发送时间：{$current_time}\n";

    return [
        'html_content' => $html_content,
        'text_content' => $text_content
    ];
}

/**
 * 清理邮件头，防止注入
 */
function cleanEmailHeader($str) {
    return str_replace(["\r", "\n", "\t"], '', $str);
}

/**
 * 记录验证码到日志
 */
function logVerification($ic_number, $full_name, $email, $verification_code, $status) {
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/verifications.log';
    
    $masked_ic = substr($ic_number, 0, 4) . '****' . substr($ic_number, -4);
    $masked_email = maskEmail($email);
    
    $logEntry = sprintf(
        "[%s] [%s]\n" .
        "用户: %s | IC: %s | 邮箱: %s\n" .
        "验证码: %s\n" .
        "IP: %s\n" .
        str_repeat("-", 60) . "\n",
        date('Y-m-d H:i:s'),
        $status,
        $full_name,
        $masked_ic,
        $masked_email,
        $verification_code,
        $_SERVER['REMOTE_ADDR'] ?? '未知'
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

/**
 * 部分隐藏邮箱
 */
function maskEmail($email) {
    if (empty($email)) return '未提供';
    
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    
    $username = $parts[0];
    $domain = $parts[1];
    
    if (strlen($username) <= 2) {
        $masked_username = substr($username, 0, 1) . '*';
    } else {
        $masked_username = substr($username, 0, 2) . '***' . substr($username, -1);
    }
    
    return $masked_username . '@' . $domain;
}

/**
 * 保存到备份文件
 */
function saveToBackupFile($consultationId, $name, $phone, $email, $courseName, $message, $contactName, $error = '') {
    $backupDir = __DIR__ . '/backups';
    if (!is_dir($backupDir)) {
        mkdir($backupDir, 0755, true);
    }
    
    $backupFile = $backupDir . '/verification_backup_' . date('Y-m') . '.txt';
    
    $logEntry = sprintf(
        "[%s] [备份]\n" .
        "用户: %s | IC: %s\n" .
        "邮箱: %s\n" .
        "IP: %s\n" .
        "错误: %s\n" .
        str_repeat("=", 70) . "\n\n",
        date('Y-m-d H:i:s'),
        $name,
        $ic_number,
        $email,
        $_SERVER['REMOTE_ADDR'] ?? '未知',
        $error ?: '无'
    );
    
    file_put_contents($backupFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>