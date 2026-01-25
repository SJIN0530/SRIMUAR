<?php
// send_email.php

// 开启错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置时区
date_default_timezone_set('Asia/Kuala_Lumpur');

// 接收POST数据
$data = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 尝试从JSON获取数据
    $json_input = file_get_contents('php://input');
    if (!empty($json_input)) {
        $data = json_decode($json_input, true);
    }
    
    // 如果JSON为空，尝试从表单获取
    if (!$data && !empty($_POST)) {
        $data = $_POST;
    }
    
    if (!$data) {
        die(json_encode([
            'success' => false, 
            'message' => '没有收到数据',
            'debug' => ['method' => $_SERVER['REQUEST_METHOD'], 'input' => $json_input]
        ]));
    }
    
    // 提取数据
    $name = htmlspecialchars(trim($data['name'] ?? ''));
    $phone = htmlspecialchars(trim($data['phone'] ?? ''));
    $email = htmlspecialchars(trim($data['email'] ?? ''));
    $course = htmlspecialchars(trim($data['course'] ?? ''));
    $message = htmlspecialchars(trim($data['message'] ?? ''));
    $contact_method = htmlspecialchars(trim($data['contact_method'] ?? $data['contact-method'] ?? 'whatsapp'));
    
    // 验证必填字段
    if (empty($name) || empty($phone) || empty($course) || empty($message)) {
        die(json_encode([
            'success' => false, 
            'message' => '请填写所有必填字段',
            'debug' => ['name' => $name, 'phone' => $phone, 'course' => $course, 'message' => $message]
        ]));
    }
    
    // 课程映射
    $courseMapping = [
        'motorcycle-b2' => '摩托车 B2 课程',
        'motorcycle-bfull' => '摩托车 B Full 课程',
        'car-manual' => '汽车 D（手动挡）课程',
        'car-auto' => '汽车 DA（自动挡）课程',
        'not-sure' => '还不确定，需要咨询'
    ];
    
    $courseName = $courseMapping[$course] ?? $course;
    
    // 联系方式映射
    $contactMapping = [
        'whatsapp' => 'WhatsApp',
        'email' => '电子邮件'
    ];
    
    $contactName = $contactMapping[$contact_method] ?? $contact_method;
    
    // 发送邮件到 siewjin05@gmail.com
    $to = "siewjin05@gmail.com";
    $subject = "SRI MUAR 新咨询单 - " . $name . " (" . date('m-d H:i') . ")";
    
    // 构建邮件内容（HTML格式）
    $emailContent = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>SRI MUAR 新咨询单</title>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f5f5f5; margin: 0; padding: 20px; }
            .email-container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
            .header { background: linear-gradient(135deg, #0056b3 0%, #003d82 100%); color: white; padding: 25px; text-align: center; }
            .header h1 { margin: 0; font-size: 22px; }
            .content { padding: 25px; }
            .section { margin-bottom: 20px; padding-bottom: 15px; border-bottom: 1px solid #eee; }
            .section-title { color: #0056b3; font-size: 16px; font-weight: bold; margin-bottom: 10px; }
            .info-item { margin-bottom: 8px; }
            .label { font-weight: bold; color: #555; min-width: 100px; display: inline-block; }
            .message-box { background: #f8f9fa; padding: 15px; border-radius: 5px; border-left: 4px solid #0056b3; margin-top: 10px; }
            .action-box { background: #e8f4ff; padding: 15px; border-radius: 5px; margin-top: 20px; }
            .footer { text-align: center; padding: 15px; background: #f8f9fa; color: #666; font-size: 12px; border-top: 1px solid #eee; }
        </style>
    </head>
    <body>
        <div class="email-container">
            <div class="header">
                <h1>🚗 SRI MUAR 皇城驾驶学院</h1>
                <p>新客户咨询单</p>
            </div>
            
            <div class="content">
                <div class="section">
                    <div class="section-title">📋 咨询单信息</div>
                    <div class="info-item">
                        <span class="label">咨询编号：</span>
                        SRM-' . date('Ymd') . rand(1000, 9999) . '
                    </div>
                    <div class="info-item">
                        <span class="label">提交时间：</span>
                        ' . date('Y-m-d H:i:s') . '
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">👤 客户资料</div>
                    <div class="info-item">
                        <span class="label">姓名：</span>
                        ' . $name . '
                    </div>
                    <div class="info-item">
                        <span class="label">电话：</span>
                        ' . $phone . '
                    </div>
                    <div class="info-item">
                        <span class="label">邮箱：</span>
                        ' . ($email ?: '<span style="color:#999">未提供</span>') . '
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">📚 咨询详情</div>
                    <div class="info-item">
                        <span class="label">课程：</span>
                        ' . $courseName . '
                    </div>
                    <div class="info-item">
                        <span class="label">首选联系：</span>
                        ' . $contactName . '
                    </div>
                </div>
                
                <div class="section">
                    <div class="section-title">💬 咨询内容</div>
                    <div class="message-box">
                        ' . nl2br($message) . '
                    </div>
                </div>
                
                <div class="action-box">
                    <h3 style="margin-top: 0; color: #0056b3;">📞 建议操作</h3>
                    <p><strong>立即联系客户：</strong></p>
                    <ul>
                        <li><strong>通过' . $contactName . '联系</strong></li>
                        <li><a href="https://wa.me/6' . preg_replace('/[^0-9]/', '', $phone) . '" style="color: #0056b3; text-decoration: none;">📱 点击打开 WhatsApp</a></li>
                        <li><a href="tel:' . $phone . '" style="color: #0056b3; text-decoration: none;">📞 点击拨打电话</a></li>
                        ' . ($email ? '<li><a href="mailto:' . $email . '" style="color: #0056b3; text-decoration: none;">📧 发送邮件</a></li>' : '') . '
                    </ul>
                </div>
            </div>
            
            <div class="footer">
                <p>来源：SRI MUAR 网站联系表单 | IP：' . $_SERVER['REMOTE_ADDR'] . ' | 时间：' . date('Y-m-d H:i:s') . '</p>
            </div>
        </div>
    </body>
    </html>';
    
    // 纯文本版本
    $textContent = "🚗 SRI MUAR 皇城驾驶学院 - 新咨询单 🚗\n\n";
    $textContent .= "📅 提交时间: " . date('Y-m-d H:i:s') . "\n\n";
    $textContent .= "👤 客户资料\n";
    $textContent .= "姓名: " . $name . "\n";
    $textContent .= "电话: " . $phone . "\n";
    $textContent .= "邮箱: " . ($email ?: '未提供') . "\n\n";
    $textContent .= "📚 咨询详情\n";
    $textContent .= "课程: " . $courseName . "\n";
    $textContent .= "联系方式: " . $contactName . "\n\n";
    $textContent .= "💬 咨询内容\n" . $message . "\n\n";
    $textContent .= "📞 建议操作\n";
    $textContent .= "1. 立即通过" . $contactName . "联系客户\n";
    $textContent .= "2. WhatsApp: https://wa.me/6" . preg_replace('/[^0-9]/', '', $phone) . "\n";
    $textContent .= "3. 电话: " . $phone . "\n\n";
    $textContent .= "🌐 来源信息\n";
    $textContent .= "IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
    $textContent .= "时间: " . date('Y-m-d H:i:s') . "\n";
    
    // 邮件头
    $headers = "From: SRI MUAR Website <noreply@srimuar.com>\r\n";
    $headers .= "Reply-To: " . ($email ?: "noreply@srimuar.com") . "\r\n";
    $headers .= "Cc: im_srimuar@yahoo.com\r\n"; // 抄送到第二个邮箱
    $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    
    // 尝试发送邮件
    try {
        // 方法1：使用PHP mail()函数
        if (mail($to, $subject, $emailContent, $headers)) {
            
            // 保存到日志文件（无论邮件是否成功都保存）
            $logEntry = "[" . date('Y-m-d H:i:s') . "]\n";
            $logEntry .= "姓名: " . $name . "\n";
            $logEntry .= "电话: " . $phone . "\n";
            $logEntry .= "邮箱: " . ($email ?: '未提供') . "\n";
            $logEntry .= "课程: " . $courseName . "\n";
            $logEntry .= "联系方式: " . $contactName . "\n";
            $logEntry .= "信息: " . substr($message, 0, 200) . "...\n";
            $logEntry .= "IP: " . $_SERVER['REMOTE_ADDR'] . "\n";
            $logEntry .= "状态: 邮件已发送\n";
            $logEntry .= str_repeat("-", 50) . "\n";
            
            file_put_contents('contact_log.txt', $logEntry, FILE_APPEND);
            
            // 返回成功响应
            echo json_encode([
                'success' => true, 
                'message' => '邮件已成功发送到 siewjin05@gmail.com！我们会尽快联系您。',
                'data' => [
                    'name' => $name,
                    'phone' => $phone,
                    'course' => $courseName,
                    'contact_method' => $contactName
                ]
            ]);
            
        } else {
            // 邮件发送失败，保存到备份文件
            saveToBackupFile($name, $phone, $email, $courseName, $message, $contactName);
            
            echo json_encode([
                'success' => false, 
                'message' => '邮件发送暂时失败，但您的咨询已保存。请直接联系我们：06-981 2000',
                'backup' => true
            ]);
        }
        
    } catch (Exception $e) {
        // 保存错误信息
        saveToBackupFile($name, $phone, $email, $courseName, $message, $contactName, $e->getMessage());
        
        echo json_encode([
            'success' => false, 
            'message' => '系统错误，但您的咨询已保存。请直接联系我们：06-981 2000',
            'error' => $e->getMessage()
        ]);
    }
    
} else {
    echo json_encode([
        'success' => false, 
        'message' => '无效的请求方法'
    ]);
}

// 保存到备份文件的函数
function saveToBackupFile($name, $phone, $email, $courseName, $message, $contactName, $error = '') {
    $backupFile = 'inquiries_backup.txt';
    $logEntry = "[" . date('Y-m-d H:i:s') . "]\n";
    $logEntry .= "姓名: " . $name . "\n";
    $logEntry .= "电话: " . $phone . "\n";
    $logEntry .= "邮箱: " . ($email ?: '未提供') . "\n";
    $logEntry .= "课程: " . $courseName . "\n";
    $logEntry .= "联系方式: " . $contactName . "\n";
    $logEntry .= "信息: " . $message . "\n";
    $logEntry .= "IP: " . ($_SERVER['REMOTE_ADDR'] ?? '未知') . "\n";
    if ($error) {
        $logEntry .= "错误: " . $error . "\n";
    }
    $logEntry .= str_repeat("=", 60) . "\n\n";
    
    file_put_contents($backupFile, $logEntry, FILE_APPEND | LOCK_EX);
}
?>