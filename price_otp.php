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

/**
 * 获取车辆类型文字 - 更新包含所有类型
 */
function getVehicleTypeText($vehicle_type) {
    $types = [
        'car' => '汽车价格 (D/DA驾照)',
        'motor' => '摩托车价格 (B2/B Full)',
        'gdl' => 'GDL货物驾驶执照价格',
        'trailer' => 'TRAILER拖格罗里价格',
        'lori' => 'Lori E级罗里价格',
        'psv_teksi' => 'PSV Teksi/E-Hailing价格',
        'psv_van' => 'PSV VAN/BAS MINI价格',
        'psv_bas' => 'PSV BAS价格',
        'traktor' => 'H挖泥机价格'
    ];
    
    return $types[$vehicle_type] ?? $vehicle_type;
}

/**
 * 获取车辆类型图标 - 更新包含所有类型
 */
function getVehicleTypeIcon($vehicle_type) {
    $icons = [
        'car' => 'fas fa-car',
        'motor' => 'fas fa-motorcycle',
        'gdl' => 'fas fa-truck',
        'trailer' => 'fas fa-truck',
        'lori' => 'fas fa-truck',
        'psv_teksi' => 'fas fa-taxi',
        'psv_van' => 'fas fa-shuttle-van',
        'psv_bas' => 'fas fa-bus',
        'traktor' => 'fas fa-tractor'
    ];
    
    return $icons[$vehicle_type] ?? 'fas fa-file-pdf';
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

// 获取当前车辆类型
$vehicle_type = $_SESSION['price_verification']['vehicle_type'] ?? 'car';
$vehicle_type_text = getVehicleTypeText($vehicle_type);
$vehicle_icon = getVehicleTypeIcon($vehicle_type);
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
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #0056b3;
        }
        
        .user-info-row {
            display: flex;
            align-items: center;
            margin-bottom: 12px;
        }
        
        .user-info-row:last-child {
            margin-bottom: 0;
        }
        
        .user-info-icon {
            width: 30px;
            color: #0056b3;
            font-size: 1.1rem;
        }
        
        .user-info-label {
            font-weight: 600;
            min-width: 80px;
            color: #333;
        }
        
        .user-info-value {
            color: #666;
        }
        
        .vehicle-type {
            background: #e8f4fd;
            padding: 5px 10px;
            border-radius: 20px;
            display: inline-block;
            font-size: 0.9rem;
        }
        
        .vehicle-type i {
            color: #0056b3;
            margin-right: 5px;
        }
        
        .timer {
            color: #ff6b00;
            font-weight: bold;
            font-size: 1.2rem;
            background: #fff3cd;
            padding: 3px 10px;
            border-radius: 20px;
        }
        
        .resend-link {
            cursor: pointer;
            color: #0056b0;
            text-decoration: none;
        }
        
        .resend-link:hover {
            text-decoration: underline;
        }
        
        .otp-input {
            font-size: 2rem;
            letter-spacing: 10px;
            text-align: center;
            font-weight: bold;
            border: 2px solid #dee2e6;
            transition: all 0.3s;
        }
        
        .otp-input:focus {
            border-color: #0056b3;
            box-shadow: 0 0 0 0.25rem rgba(0, 86, 179, 0.25);
        }
        
        .expired-message {
            color: #dc3545;
            font-weight: bold;
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
            <div class="user-info-row">
                <div class="user-info-icon">
                    <i class="fas fa-user"></i>
                </div>
                <span class="user-info-label">姓名：</span>
                <span class="user-info-value"><?php echo htmlspecialchars($_SESSION['price_verification']['name']); ?></span>
            </div>
            
            <div class="user-info-row">
                <div class="user-info-icon">
                    <i class="fas fa-envelope"></i>
                </div>
                <span class="user-info-label">邮箱：</span>
                <span class="user-info-value"><?php echo htmlspecialchars($_SESSION['price_verification']['email']); ?></span>
            </div>
            
            <div class="user-info-row">
                <div class="user-info-icon">
                    <i class="<?php echo $vehicle_icon; ?>"></i>
                </div>
                <span class="user-info-label">查看类型：</span>
                <span class="user-info-value">
                    <span class="vehicle-type">
                        <i class="<?php echo $vehicle_icon; ?>"></i>
                        <?php echo $vehicle_type_text; ?>
                    </span>
                </span>
            </div>
            
            <div class="user-info-row">
                <div class="user-info-icon">
                    <i class="fas fa-clock"></i>
                </div>
                <span class="user-info-label">有效期：</span>
                <span class="user-info-value">
                    <span class="timer" id="timer">01:00</span>
                </span>
            </div>
        </div>
        
        <!-- OTP表单 -->
        <form method="POST" action="" id="otpForm">
            <div class="mb-4">
                <label for="otp" class="form-label fw-bold">6位验证码</label>
                <input type="text" class="form-control form-control-lg otp-input" 
                       id="otp" name="otp" 
                       placeholder="000000" 
                       maxlength="6" 
                       pattern="\d{6}"
                       title="请输入6位数字验证码"
                       required>
                <div class="form-text text-center mt-2">
                    验证码已发送至您的邮箱，请注意查收
                </div>
            </div>
            
            <div class="mb-4 text-center">
                <small class="text-muted">
                    没有收到验证码？ 
                    <a href="price_information.php" class="resend-link" id="resendLink">重新发送</a>
                </small>
            </div>
            
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                    <i class="fas fa-check-circle me-2"></i> 验证并查看价格
                </button>
                <a href="price_information.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> 返回修改
                </a>
            </div>
        </form>
        
        <!-- 底部提示 -->
        <div class="text-center mt-4">
            <small class="text-muted">
                <i class="fas fa-info-circle me-1"></i>
                验证码1分钟内有效，请及时验证
            </small>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const otpInput = document.getElementById('otp');
            const form = document.getElementById('otpForm');
            const timerElement = document.getElementById('timer');
            const resendLink = document.getElementById('resendLink');
            const submitBtn = document.getElementById('submitBtn');
            
            // OTP输入框自动聚焦
            otpInput.focus();
            
            // 限制只能输入数字
            otpInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/\D/g, '');
                
                // 当输入6位数字时自动提交
                if (this.value.length === 6) {
                    // 可选：自动提交
                    // form.submit();
                }
            });
            
            // 监听粘贴事件
            otpInput.addEventListener('paste', function(e) {
                e.preventDefault();
                const pastedText = (e.clipboardData || window.clipboardData).getData('text');
                const numbers = pastedText.replace(/\D/g, '');
                if (numbers.length > 0) {
                    this.value = numbers.substring(0, 6);
                }
            });
            
            // 倒计时功能
            let timeLeft = 60; // 1分钟
            let timerExpired = false;
            
            function updateTimer() {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                
                timerElement.textContent = 
                    minutes.toString().padStart(2, '0') + ':' + 
                    seconds.toString().padStart(2, '0');
                
                if (timeLeft <= 0) {
                    timerElement.textContent = '已过期';
                    timerElement.classList.add('expired-message');
                    timerElement.classList.remove('timer');
                    clearInterval(timerInterval);
                    timerExpired = true;
                    
                    // 禁用提交按钮
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-clock me-2"></i> 验证码已过期';
                    
                    // 显示过期提示
                    const expiredAlert = document.createElement('div');
                    expiredAlert.className = 'alert alert-warning mt-3';
                    expiredAlert.innerHTML = '<i class="fas fa-exclamation-triangle me-2"></i>验证码已过期，请重新发送';
                    form.parentNode.insertBefore(expiredAlert, form);
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
                
                if (timerExpired) {
                    e.preventDefault();
                    alert('验证码已过期，请重新发送');
                    return false;
                }
                
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
                
                // 提交时禁用按钮防止重复提交
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> 验证中...';
                
                return true;
            });
            
            // 重新发送链接点击确认
            resendLink.addEventListener('click', function(e) {
                const confirmResend = confirm('确定要重新发送验证码吗？');
                if (!confirmResend) {
                    e.preventDefault();
                }
            });
            
            // 页面关闭前清除定时器
            window.addEventListener('beforeunload', function() {
                clearInterval(timerInterval);
            });
        });
    </script>
</body>
</html>