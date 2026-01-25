<?php
// 开启详细的错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 确保session正确启动
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 调试信息
error_log("===== OTP验证页面访问 =====");
error_log("Session ID: " . session_id());
error_log("Session数据: " . print_r($_SESSION, true));
error_log("POST数据: " . print_r($_POST, true));

// 检查是否有未过期的验证请求
if (!isset($_SESSION['price_verification'])) {
    error_log("错误: price_verification session不存在");
    header('Location: price_information.php');
    exit();
}

// 检查OTP是否过期
$otp_time = $_SESSION['price_verification']['otp_time'] ?? 0;
$current_time = time();
$time_diff = $current_time - $otp_time;

error_log("OTP时间: $otp_time, 当前时间: $current_time, 时间差: $time_diff 秒");

if ($time_diff > 600) {
    error_log("OTP已过期");
    session_destroy();
    header('Location: price_information.php?expired=true');
    exit();
}

// 处理OTP验证
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $entered_otp = trim($_POST['otp'] ?? '');
    $stored_otp = $_SESSION['price_verification']['otp'] ?? '';
    
    error_log("输入的OTP: '$entered_otp' (类型: " . gettype($entered_otp) . ")");
    error_log("存储的OTP: '$stored_otp' (类型: " . gettype($stored_otp) . ")");
    
    // 转换为字符串进行比较
    $entered_otp_str = (string)$entered_otp;
    $stored_otp_str = (string)$stored_otp;
    
    error_log("转换为字符串后:");
    error_log("输入OTP: '$entered_otp_str'");
    error_log("存储OTP: '$stored_otp_str'");
    
    // 去除可能的前导零
    $entered_otp_clean = ltrim($entered_otp_str, '0');
    $stored_otp_clean = ltrim($stored_otp_str, '0');
    
    error_log("去除前导零后:");
    error_log("输入OTP: '$entered_otp_clean'");
    error_log("存储OTP: '$stored_otp_clean'");
    
    if ($entered_otp_str === $stored_otp_str) {
        error_log("OTP验证成功 (字符串完全匹配)");
        $_SESSION['price_verification']['verified'] = true;
        $_SESSION['price_verification']['verified_time'] = time();
        header('Location: price_display.php');
        exit();
    } elseif ($entered_otp_clean === $stored_otp_clean) {
        error_log("OTP验证成功 (去除前导零后匹配)");
        $_SESSION['price_verification']['verified'] = true;
        $_SESSION['price_verification']['verified_time'] = time();
        header('Location: price_display.php');
        exit();
    } else {
        error_log("OTP验证失败: '$entered_otp_str' 不等于 '$stored_otp_str'");
        $error = "验证码不正确，请重新输入";
    }
}

// 检查是否重新发送
if (isset($_GET['resend'])) {
    // 重新生成OTP，不要使用旧的
    $new_otp = rand(100000, 999999);
    $_SESSION['price_verification']['otp'] = $new_otp;
    $_SESSION['price_verification']['otp_time'] = time();
    
    $email = $_SESSION['price_verification']['email'] ?? '';
    
    error_log("重新发送OTP: $new_otp 到 $email");
    
    // 检查PHPMailer文件是否存在
    $phpmailer_path = __DIR__ . '/phpmailer/';
    error_log("PHPMailer路径: $phpmailer_path");
    
    if (!file_exists($phpmailer_path . 'PHPMailer.php')) {
        error_log("错误: PHPMailer文件不存在");
        $error = "邮件系统配置错误，请联系管理员";
    } else {
        require_once $phpmailer_path . 'PHPMailer.php';
        require_once $phpmailer_path . 'SMTP.php';
        require_once $phpmailer_path . 'Exception.php';
        
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);
        
        try {
            error_log("开始发送邮件...");
            
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'siewjinstudent@gmail.com';
            $mail->Password = 'buwm rhzu mrbt llis';
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;
            
            // 调试选项
            $mail->SMTPDebug = 0; // 设为2查看更多调试信息
            $mail->Debugoutput = function($str, $level) {
                error_log("PHPMailer ($level): $str");
            };
            
            $mail->setFrom('siewjinstudent@gmail.com', 'PUSAT LATIHAN MEMANDU SRI MUAR');
            $mail->addAddress($email);
            $mail->addReplyTo('siewjinstudent@gmail.com', 'PUSAT LATIHAN MEMANDU SRI MUAR');
            
            $mail->isHTML(true);
            $mail->Subject = '重新发送验证码 - PUSAT LATIHAN MEMANDU SRI MUAR';
            $mail->Body = "
                <h2>重新发送验证码</h2>
                <p>您的新验证码是：<strong style='font-size: 24px; color: #FF6B00;'>$new_otp</strong></p>
                <p>此验证码10分钟内有效。</p>
                <hr>
                <p>谢谢，<br>SRI MUAR 皇城驾驶学院</p>
            ";
            $mail->AltBody = "您的新验证码是：$new_otp，10分钟内有效。";
            
            if ($mail->send()) {
                error_log("邮件发送成功");
                $success = "验证码已重新发送到您的邮箱";
            } else {
                throw new Exception('邮件发送失败');
            }
            
        } catch (Exception $e) {
            error_log("邮件发送失败: " . $mail->ErrorInfo);
            $error = "邮件发送失败，请稍后重试";
            
            // 备选方案：显示OTP在页面上
            $_SESSION['manual_otp'] = $new_otp;
            $_SESSION['show_manual_otp'] = true;
        }
    }
}

// 如果邮件发送失败，检查是否有手动OTP
if (isset($_SESSION['show_manual_otp']) && $_SESSION['show_manual_otp']) {
    $manual_otp = $_SESSION['manual_otp'] ?? '';
    error_log("显示手动OTP: $manual_otp");
}
?>

<!DOCTYPE html>
<html lang="zh-MY">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>验证码验证 - SRI MUAR 皇城驾驶学院</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 图标 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Microsoft YaHei', 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
        }
        
        .otp-header {
            background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }
        
        .otp-container {
            max-width: 500px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .otp-input {
            font-size: 32px;
            text-align: center;
            letter-spacing: 10px;
            height: 70px;
            border-radius: 10px;
            border: 2px solid #0056b3;
        }
        
        .timer-box {
            background: #fff8e1;
            border: 2px dashed #ffc107;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .timer {
            font-size: 24px;
            font-weight: bold;
            color: #ff6b00;
        }
        
        .btn-verify {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            font-size: 18px;
        }
        
        .btn-resend {
            background: linear-gradient(135deg, #ff6b00 0%, #e55c00 100%);
            border: none;
            padding: 10px 20px;
            border-radius: 25px;
        }
        
        .manual-otp-box {
            background: #d4edda;
            border: 2px solid #28a745;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .manual-otp {
            font-size: 32px;
            font-weight: bold;
            color: #155724;
            letter-spacing: 5px;
            margin: 10px 0;
        }
    </style>
</head>
<body>
    <!-- 头部 -->
    <div class="otp-header text-center">
        <div class="container">
            <h1 class="display-5 fw-bold mb-3">
                <i class="fas fa-key me-2"></i>输入验证码
            </h1>
            <p class="lead mb-0">
                验证码已发送到：<?php echo htmlspecialchars($_SESSION['price_verification']['email'] ?? ''); ?>
            </p>
            <p class="small mb-0">
                姓名：<?php echo htmlspecialchars($_SESSION['price_verification']['name'] ?? ''); ?>
            </p>
        </div>
    </div>
    
    <!-- OTP容器 -->
    <div class="container">
        <div class="otp-container">
            <!-- 成功信息 -->
            <?php if (isset($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- 错误信息 -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- 手动显示OTP -->
            <?php if (isset($_SESSION['show_manual_otp']) && $_SESSION['show_manual_otp'] && isset($_SESSION['manual_otp'])): ?>
                <div class="manual-otp-box">
                    <h5><i class="fas fa-key me-2"></i>您的验证码</h5>
                    <div class="manual-otp"><?php echo $_SESSION['manual_otp']; ?></div>
                    <p class="text-muted mb-0">请复制此验证码并在下方输入</p>
                    <p class="text-danger small mt-2"><i class="fas fa-exclamation-circle me-1"></i>此验证码10分钟内有效</p>
                </div>
                <?php 
                // 清除手动OTP显示标志，但保留OTP值
                unset($_SESSION['show_manual_otp']);
                ?>
            <?php endif; ?>
            
            <!-- 倒计时 -->
            <div class="timer-box">
                <div class="mb-2">
                    <i class="fas fa-clock me-2"></i>验证码有效期剩余：
                </div>
                <div class="timer" id="timer">10:00</div>
                <small class="text-muted">过期后验证码将失效</small>
            </div>
            
            <!-- 调试信息（仅开发时显示） -->
            <?php if (isset($_GET['debug'])): ?>
                <div class="alert alert-info">
                    <h6>调试信息：</h6>
                    <p>存储的OTP: <?php echo $_SESSION['price_verification']['otp'] ?? '未设置'; ?></p>
                    <p>OTP时间: <?php echo date('Y-m-d H:i:s', $_SESSION['price_verification']['otp_time'] ?? 0); ?></p>
                    <p>Session ID: <?php echo session_id(); ?></p>
                </div>
            <?php endif; ?>
            
            <!-- OTP表单 -->
            <form method="POST" action="">
                <div class="mb-4">
                    <label for="otp" class="form-label fw-bold mb-3">
                        <i class="fas fa-lock me-2"></i> 请输入6位验证码
                    </label>
                    <input type="text" class="form-control otp-input" id="otp" name="otp" 
                           maxlength="6" pattern="[0-9]{6}" required autofocus
                           placeholder="000000">
                    <div class="form-text text-center mt-2">
                        请查看您的电子邮件获取验证码
                        <?php if (isset($_SESSION['manual_otp'])): ?>
                            <br><span class="text-danger">或使用上面显示的验证码</span>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="d-grid gap-2 mb-3">
                    <button type="submit" class="btn btn-verify">
                        <i class="fas fa-check-circle me-2"></i> 验证并查看价格
                    </button>
                </div>
                
                <div class="text-center">
                    <a href="?resend=true" class="btn btn-resend">
                        <i class="fas fa-redo me-2"></i> 重新发送验证码
                    </a>
                </div>
            </form>
            
            <hr class="my-4">
            
            <div class="text-center">
                <a href="price_information.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> 返回修改信息
                </a>
                <a href="index.html" class="btn btn-outline-secondary ms-2">
                    <i class="fas fa-home me-2"></i> 返回首页
                </a>
                <!-- 调试链接 -->
                <a href="?debug=true" class="btn btn-outline-info ms-2">
                    <i class="fas fa-bug me-2"></i> 调试
                </a>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 倒计时功能
        function startTimer(duration, display) {
            let timer = duration, minutes, seconds;
            
            const interval = setInterval(function () {
                minutes = parseInt(timer / 60, 10);
                seconds = parseInt(timer % 60, 10);
                
                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;
                
                display.textContent = minutes + ":" + seconds;
                
                if (--timer < 0) {
                    clearInterval(interval);
                    display.textContent = "已过期";
                    display.style.color = "#dc3545";
                    
                    // 过期后重定向
                    setTimeout(function() {
                        window.location.href = 'price_information.php?expired=true';
                    }, 2000);
                }
            }, 1000);
        }
        
        // OTP输入自动跳转
        document.getElementById('otp').addEventListener('input', function(e) {
            // 只允许数字输入
            this.value = this.value.replace(/[^0-9]/g, '');
            
            if (this.value.length === 6) {
                // 可以自动提交，但让用户手动点击更好
                // document.querySelector('form').submit();
            }
        });
        
        // 页面加载时启动计时器
        window.onload = function () {
            // 计算剩余时间（10分钟 = 600秒）
            const otpTime = <?php echo $_SESSION['price_verification']['otp_time'] ?? time(); ?>;
            const currentTime = Math.floor(Date.now() / 1000);
            const elapsed = currentTime - otpTime;
            const remaining = Math.max(0, 600 - elapsed);
            
            const display = document.querySelector('#timer');
            if (display) {
                startTimer(remaining, display);
            }
            
            // 自动聚焦到OTP输入框
            document.getElementById('otp').focus();
        };
    </script>
</body>
</html>