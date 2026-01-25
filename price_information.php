<?php
session_start();

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证必填字段
    if (empty($_POST['ic']) || empty($_POST['name']) || empty($_POST['email'])) {
        $error = "请填写所有必填字段";
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $error = "请输入有效的电子邮件地址";
    } else {
        // 生成6位OTP
        $otp = rand(100000, 999999);
        
        // 存储数据到session
        $_SESSION['price_verification'] = [
            'ic' => $_POST['ic'],
            'name' => $_POST['name'],
            'email' => $_POST['email'],
            'otp' => $otp,
            'otp_time' => time(),
            'verified' => false
        ];
        
        // 发送OTP邮件
        require_once 'PHPMailer/PHPMailer.php';
        require_once 'PHPMailer/SMTP.php';
        require_once 'PHPMailer/Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            // 邮件服务器配置
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'siewjinstudent@gmail.com';
            $mail->Password = 'fhgk dbil mdnq nrss'; // 请替换为实际密码
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            
            // 收件人
            $mail->setFrom('siewjinstudent@gmail.com', 'SRI MUAR 皇城驾驶学院');
            $mail->addAddress($_POST['email']);
            
            // 邮件内容
            $mail->isHTML(true);
            $mail->Subject = 'PUSAT LATIHAN MEMANDU SRI MUAR';
            $mail->Body = "
                <h2>价格查看验证码</h2>
                <p>尊敬的用户，</p>
                <p>您正在请求查看SRI MUAR皇城驾驶学院的价格信息。</p>
                <p>您的验证码是：<strong style='font-size: 24px; color: #FF6B00;'>$otp</strong></p>
                <p>此验证码10分钟内有效。</p>
                <p>如果您没有请求查看价格，请忽略此邮件。</p>
                <hr>
                <p>谢谢，<br>SRI MUAR 皇城驾驶学院</p>
            ";
            $mail->AltBody = "您的验证码是：$otp，10分钟内有效。";
            
            $mail->send();
            
            // 重定向到OTP验证页面
            header('Location: price_otp.php');
            exit();
            
        } catch (Exception $e) {
            $error = "邮件发送失败: " . $mail->ErrorInfo;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-MY">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>查看价格 - SRI MUAR 皇城驾驶学院</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 图标 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Microsoft YaHei', 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
        }
        
        .price-header {
            background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }
        
        .form-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .form-control:focus {
            border-color: #0056b3;
            box-shadow: 0 0 0 0.25rem rgba(0, 86, 179, 0.25);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
        }
        
        .btn-primary:hover {
            background: linear-gradient(135deg, #004494 0%, #003d82 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 86, 179, 0.3);
        }
        
        .alert {
            border-radius: 10px;
            border: none;
        }
        
        .info-box {
            background: #e8f4fd;
            border-left: 4px solid #0056b3;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
        }
    </style>
</head>
<body>
    <!-- 头部 -->
    <div class="price-header text-center">
        <div class="container">
            <h1 class="display-5 fw-bold mb-3">
                <i class="fas fa-lock me-2"></i>查看价格信息
            </h1>
            <p class="lead mb-0">请输入您的信息以获取验证码查看价格</p>
        </div>
    </div>
    
    <!-- 表单容器 -->
    <div class="container">
        <div class="form-container">
            <!-- 信息提示 -->
            <div class="info-box">
                <h5 class="mb-2"><i class="fas fa-info-circle me-2"></i>重要提示</h5>
                <p class="mb-0">
                    为了保护价格信息的机密性，我们需要通过电子邮件向您发送一次性验证码。
                    验证码有效期为10分钟，过期后需要重新申请。
                </p>
            </div>
            
            <!-- 错误信息 -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- 表单 -->
            <form method="POST" action="">
                <div class="mb-4">
                    <label for="ic" class="form-label fw-bold">
                        <i class="fas fa-id-card me-1"></i> 身份证号码 (IC)
                    </label>
                    <input type="text" class="form-control form-control-lg" id="ic" name="ic" 
                           placeholder="请输入您的身份证号码" required>
                </div>
                
                <div class="mb-4">
                    <label for="name" class="form-label fw-bold">
                        <i class="fas fa-user me-1"></i> 填写名字
                    </label>
                    <input type="text" class="form-control form-control-lg" id="name" name="name" 
                           placeholder="请输入您的姓名" required>
                </div>
                
                <div class="mb-4">
                    <label for="email" class="form-label fw-bold">
                        <i class="fas fa-envelope me-1"></i> 电子邮件地址
                    </label>
                    <input type="email" class="form-control form-control-lg" id="email" name="email" 
                           placeholder="example@gmail.com" required>
                    <div class="form-text">验证码将发送到此邮箱</div>
                </div>
                
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-paper-plane me-2"></i> 发送验证码
                    </button>
                    <a href="index.html" class="btn btn-outline-secondary">
                        <i class="fas fa-home me-2"></i> 返回首页
                    </a>
                </div>
            </form>
            
            <hr class="my-4">
            
            <div class="text-center">
                <small class="text-muted">
                    <i class="fas fa-shield-alt me-1"></i>
                    我们承诺保护您的个人信息，不会用于其他用途
                </small>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 表单验证
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            
            form.addEventListener('submit', function(e) {
                const ic = document.getElementById('ic').value;
                const name = document.getElementById('name').value;
                const email = document.getElementById('email').value;
                
                // 简单的验证
                if (ic.trim() === '' || name.trim() === '' || email.trim() === '') {
                    e.preventDefault();
                    alert('请填写所有必填字段');
                    return false;
                }
                
                // 验证邮箱格式
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    e.preventDefault();
                    alert('请输入有效的电子邮件地址');
                    return false;
                }
                
                return true;
            });
        });
    </script>
</body>
</html>