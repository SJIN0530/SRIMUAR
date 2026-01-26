<?php
session_start();

// 检查是否从价格信息页面跳转过来
if (!isset($_SESSION['price_verification'])) {
    header('Location: price_information.php');
    exit();
}

// 检查OTP是否已过期（1分钟）
if (isset($_SESSION['price_verification']['otp_time'])) {
    $otp_time = $_SESSION['price_verification']['otp_time'];
    if (time() - $otp_time > 60) { // 60秒 = 1分钟
        $error = "验证码已过期，请重新获取";
        unset($_SESSION['price_verification']);
    }
}

// 处理OTP验证
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty($_POST['otp'])) {
        $error = "请输入验证码";
    } else {
        $user_otp = $_POST['otp'];
        $stored_otp = $_SESSION['price_verification']['otp'];
        
        if ($user_otp == $stored_otp) {
            // 验证成功
            $_SESSION['price_verification']['verified'] = true;
            $_SESSION['price_verification']['verified_time'] = time();
            
            // 重定向到价格显示页面
            header('Location: price_display.php');
            exit();
        } else {
            $error = "验证码不正确";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="zh-MY">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>验证OTP - SRI MUAR 皇城驾驶学院</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 图标 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Microsoft YaHei', 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .otp-container {
            max-width: 500px;
            width: 100%;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }
        
        .otp-header {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .otp-icon {
            font-size: 48px;
            color: #0056b3;
            margin-bottom: 15px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
            font-weight: 600;
            width: 100%;
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
        
        .user-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
        }
        
        .timer {
            color: #ff6b00;
            font-weight: bold;
        }
        
        .resend-link {
            cursor: pointer;
            color: #0056b0;
            text-decoration: none;
        }
        
        .resend-link:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="otp-container">
        <!-- 头部 -->
        <div class="otp-header">
            <div class="otp-icon">
                <i class="fas fa-shield-alt"></i>
            </div>
            <h3 class="fw-bold">邮箱验证</h3>
            <p class="text-muted">请输入发送到您邮箱的6位验证码</p>
        </div>
        
        <!-- 错误信息 -->
        <?php if (isset($error)): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- 用户信息 -->
        <div class="user-info">
            <p class="mb-1"><strong>姓名：</strong><?php echo htmlspecialchars($_SESSION['price_verification']['name']); ?></p>
            <p class="mb-1"><strong>邮箱：</strong><?php echo htmlspecialchars($_SESSION['price_verification']['email']); ?></p>
            <p class="mb-1"><strong>查看类型：</strong>
                <?php 
                $vehicle_type = $_SESSION['price_verification']['vehicle_type'];
                echo ($vehicle_type == 'car') ? '汽车价格' : '摩托车价格';
                ?>
            </p>
            <p class="mb-0"><strong>有效期：</strong><span class="timer" id="timer">01:00</span></p>
        </div>
        
        <!-- OTP表单 -->
        <form method="POST" action="" id="otpForm">
            <div class="mb-3">
                <label for="otp" class="form-label fw-bold">6位验证码</label>
                <input type="text" class="form-control form-control-lg text-center" 
                       id="otp" name="otp" 
                       placeholder="000000" 
                       maxlength="6" 
                       pattern="\d{6}"
                       title="请输入6位数字验证码"
                       required>
            </div>
            
            <div class="mb-3 text-center">
                <small class="text-muted">
                    没有收到验证码？ 
                    <a href="price_information.php" class="resend-link" id="resendLink">重新发送</a>
                </small>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg">
                    <i class="fas fa-check-circle me-2"></i> 验证并查看价格
                </button>
                <a href="price_information.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> 返回修改
                </a>
            </div>
        </form>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const otpInput = document.getElementById('otp');
            const form = document.getElementById('otpForm');
            const timerElement = document.getElementById('timer');
            const resendLink = document.getElementById('resendLink');
            
            // OTP输入框自动聚焦
            otpInput.focus();
            
            // 限制只能输入数字
            otpInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/\D/g, '');
            });
            
            // 倒计时功能
            let timeLeft = 60; // 1分钟
            
            function updateTimer() {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                
                timerElement.textContent = 
                    minutes.toString().padStart(2, '0') + ':' + 
                    seconds.toString().padStart(2, '0');
                
                if (timeLeft <= 0) {
                    timerElement.textContent = '已过期';
                    timerElement.style.color = '#dc3545';
                    clearInterval(timerInterval);
                } else {
                    timeLeft--;
                }
            }
            
            // 初始更新
            updateTimer();
            
            // 每秒更新一次
            const timerInterval = setInterval(updateTimer, 1000);
            
            // 表单验证
            form.addEventListener('submit', function(e) {
                const otp = otpInput.value.trim();
                
                if (!otp) {
                    e.preventDefault();
                    alert('请输入验证码');
                    otpInput.focus();
                    return false;
                }
                
                if (!/^\d{6}$/.test(otp)) {
                    e.preventDefault();
                    alert('验证码必须是6位数字');
                    otpInput.focus();
                    return false;
                }
                
                return true;
            });
        });
    </script>
</body>
</html>