<?php
// send_code.php - 发送验证码到邮箱

require 'phpmailer/src/PHPMailer.php';
require 'phpmailer/src/SMTP.php';
require 'phpmailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// 启动会话
session_start();

// 设置响应头
header('Content-Type: application/json');

// 检查是否POST请求
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => '无效请求']);
    exit;
}

// 获取用户数据
$ic_number = trim($_POST['ic_number'] ?? '');
$full_name = trim($_POST['full_name'] ?? '');
$email = trim($_POST['email'] ?? '');

// 简单验证
if (empty($ic_number) || empty($full_name) || empty($email)) {
    echo json_encode(['success' => false, 'message' => '请填写所有字段']);
    exit;
}

// 验证IC号码（12位数字）
if (!preg_match('/^\d{12}$/', $ic_number)) {
    echo json_encode(['success' => false, 'message' => '身份证号码必须是12位数字']);
    exit;
}

// 验证邮箱
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'message' => '无效的邮箱地址']);
    exit;
}

// 生成6位数字验证码
$verification_code = sprintf('%06d', mt_rand(0, 999999));

// 生成会话ID
$session_id = md5($email . time() . $verification_code);

// 存储验证信息到SESSION
$_SESSION['price_verification'] = [
    'session_id' => $session_id,
    'ic_number' => $ic_number,
    'full_name' => $full_name,
    'email' => $email,
    'code' => $verification_code,
    'created_at' => time(),
    'expires_at' => time() + (15 * 60), // 15分钟有效期
    'attempts' => 0 // 尝试次数
];

// 邮件服务器配置
$mail_host = 'smtp.gmail.com';     // 你的邮件服务器
$mail_username = 'siewjinstudent@gmail.com'; // 发件邮箱
$mail_password = 'buwm rhzu mrbt llis';        // 邮箱密码
$mail_from_name = 'SRI MUAR 皇城驾驶学院';

// 发送邮件
try {
    $mail = new PHPMailer(true);
    
    // 服务器设置
    $mail->isSMTP();
    $mail->Host = $mail_host;
    $mail->SMTPAuth = true;
    $mail->Username = $mail_username;
    $mail->Password = $mail_password;
    $mail->SMTPSecure = 'tls'; // 或 'ssl'
    $mail->Port = 587; // TLS用587，SSL用465
    
    // 字符编码
    $mail->CharSet = 'UTF-8';
    
    // 发件人
    $mail->setFrom($mail_username, $mail_from_name);
    
    // 收件人
    $mail->addAddress($email, $full_name);
    
    // 邮件内容
    $mail->isHTML(true);
    $mail->Subject = 'SRI MUAR 价格查询 - 验证码';
    
    // 邮件正文
    $mail->Body = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="utf-8">
        <title>SRI MUAR 验证码</title>
    </head>
    <body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333;">
        <div style="max-width: 600px; margin: 0 auto; padding: 20px;">
            <div style="background: #0056b3; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0;">
                <h1 style="margin: 0;">SRI MUAR 皇城驾驶学院</h1>
                <p style="margin: 5px 0 0;">价格查询验证码</p>
            </div>
            
            <div style="background: white; padding: 30px; border: 1px solid #ddd; border-top: none; border-radius: 0 0 10px 10px;">
                <h2>尊敬的 ' . htmlspecialchars($full_name) . '，</h2>
                
                <p>您正在申请查看 <strong>SRI MUAR 皇城驾驶学院</strong> 的课程价格表。</p>
                
                <div style="background: #f8f9fa; padding: 25px; border-radius: 10px; text-align: center; margin: 20px 0; border: 2px solid #0056b3;">
                    <h3 style="color: #0056b3; margin-top: 0;">您的验证码是：</h3>
                    <div style="font-size: 48px; font-weight: bold; color: #dc3545; letter-spacing: 10px; margin: 15px 0;">
                        ' . $verification_code . '
                    </div>
                    <p style="color: #666; margin-bottom: 0;">请使用此验证码查看价格</p>
                </div>
                
                <div style="background: #fff3cd; padding: 15px; border-radius: 5px; border-left: 4px solid #ffc107;">
                    <h4 style="margin-top: 0; color: #856404;">重要提示：</h4>
                    <ul style="margin-bottom: 0;">
                        <li>此验证码 <strong>15分钟内</strong>有效</li>
                        <li>验证码只能使用一次</li>
                        <li>请勿将此验证码告知他人</li>
                        <li>价格页面将在 <strong>10分钟</strong> 后自动关闭</li>
                    </ul>
                </div>
                
                <div style="border-top: 1px solid #eee; padding-top: 20px; margin-top: 20px; color: #666;">
                    <p><strong>SRI MUAR 皇城驾驶学院</strong></p>
                    <p>📞 电话: 06-981 2000<br>
                       📧 邮箱: im_srimuar@yahoo.com<br>
                       📍 地址: Lot 77, Parit Unas, Jalan Temenggong Ahmad, 84000 Muar, Johor</p>
                </div>
                
                <div style="border-top: 1px solid #eee; padding-top: 15px; margin-top: 20px; text-align: center; font-size: 12px; color: #999;">
                    <p>此邮件为系统自动发送，请勿直接回复。</p>
                    <p>© ' . date('Y') . ' SRI MUAR 皇城驾驶学院. 版权所有.</p>
                </div>
            </div>
        </div>
    </body>
    </html>
    ';
    
    // 发送邮件
    $mail->send();
    
    // 返回成功响应
    echo json_encode([
        'success' => true,
        'message' => '验证码已发送到 ' . $email,
        'session_id' => $session_id
    ]);
    
} catch (Exception $e) {
    // 发送失败，清理SESSION
    unset($_SESSION['price_verification']);
    
    echo json_encode([
        'success' => false,
        'message' => '邮件发送失败，请稍后重试'
    ]);
}
?>