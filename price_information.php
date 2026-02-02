<?php
session_start();

// 设置马来西亚时区
date_default_timezone_set('Asia/Kuala_Lumpur');

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证必填字段
    if (empty($_POST['ic']) || empty($_POST['name']) || empty($_POST['email']) || empty($_POST['vehicle_type'])) {
        $error = "请填写所有必填字段";
    } elseif (!filter_var($_POST['email'], FILTER_VALIDATE_EMAIL)) {
        $error = "请输入有效的电子邮件地址";
    } else {
        // 验证IC号码
        $ic = $_POST['ic'];
        $name = $_POST['name'];
        $vehicle_type = $_POST['vehicle_type']; // car 或 motor
        
        // 验证IC格式
        if (!validateMalaysianICFormat($ic)) {
            $error = "请输入有效的马来西亚身份证号码格式 (如: 000000-00-0000)";
        } else {
            // 生成6位OTP
            $otp = rand(100000, 999999);
            
            // 存储数据到session
            $_SESSION['price_verification'] = [
                'ic' => $ic,
                'name' => $name,
                'email' => $_POST['email'],
                'vehicle_type' => $vehicle_type, // 新增：车辆类型 (car 或 motor)
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
                $mail->Password = 'fhgk dbil mdnq nrss';
                $mail->SMTPSecure = 'tls';
                $mail->Port = 587;
                
                // 设置字符集
                $mail->CharSet = 'UTF-8';
                $mail->Encoding = 'base64';
                
                // 收件人
                $mail->setFrom('siewjinstudent@gmail.com', 'SRI MUAR 皇城驾驶学院', 0);
                $mail->addAddress($_POST['email']);
                
                // 邮件内容
                $mail->isHTML(true);
                $mail->Subject = '=?UTF-8?B?' . base64_encode('价格查看验证码 - SRI MUAR 皇城驾驶学院') . '?=';
                
                // 车辆类型文本
                $vehicle_type_text = ($vehicle_type == 'car') ? '汽车价格' : '摩托车价格';
                
                $mail->Body = "
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <meta charset=\"UTF-8\">
                        <title>价格查看验证码 - SRI MUAR 皇城驾驶学院</title>
                        <style>
                            body { font-family: Arial, sans-serif; color: #333; line-height: 1.6; }
                            .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                            .header { background: #0056b3; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
                            .content { padding: 30px; background: #f9f9f9; }
                            .otp-code { 
                                font-size: 36px; 
                                font-weight: bold; 
                                color: #ff6b00; 
                                text-align: center;
                                margin: 25px 0;
                                padding: 20px;
                                background: #fff;
                                border: 3px solid #ff6b00;
                                border-radius: 10px;
                                letter-spacing: 10px;
                            }
                            .footer { 
                                margin-top: 30px; 
                                padding-top: 20px; 
                                border-top: 2px solid #ddd; 
                                text-align: center; 
                                color: #666; 
                                font-size: 14px;
                            }
                            .warning { 
                                background: #fff3cd; 
                                border: 1px solid #ffeaa7; 
                                padding: 15px; 
                                border-radius: 5px; 
                                margin: 20px 0;
                            }
                            .vehicle-info {
                                background: #e8f4fd;
                                border: 1px solid #b6d4fe;
                                padding: 15px;
                                border-radius: 5px;
                                margin: 15px 0;
                            }
                        </style>
                    </head>
                    <body>
                        <div class=\"container\">
                            <div class=\"header\">
                                <h2>SRI MUAR 皇城驾驶学院</h2>
                                <p>价格查看验证码</p>
                            </div>
                            
                            <div class=\"content\">
                                <h3>尊敬的" . htmlspecialchars($name) . "，您好！</h3>
                                
                                <p>您正在请求查看SRI MUAR皇城驾驶学院的价格信息。</p>
                                
                                <div class=\"vehicle-info\">
                                    <p><strong>查看类型：</strong>" . htmlspecialchars($vehicle_type_text) . "</p>
                                    <p><strong>身份证：</strong>" . htmlspecialchars($ic) . "</p>
                                </div>
                                
                                <p>您的验证码是：</p>
                                
                                <div class=\"otp-code\">" . chunk_split($otp, 3, ' ') . "</div>
                                
                                <div class=\"warning\">
                                    <p><strong>重要提示：</strong></p>
                                    <ul>
                                        <li>此验证码仅<strong>1分钟</strong>内有效</li>
                                        <li>请勿将此验证码透露给他人</li>
                                        <li>如果您没有请求查看价格，请忽略此邮件</li>
                                        <li>验证码仅可用于查看" . htmlspecialchars($vehicle_type_text) . "</li>
                                    </ul>
                                </div>
                                
                                <p>感谢您选择SRI MUAR皇城驾驶学院！</p>
                            </div>
                            
                            <div class=\"footer\">
                                <p><strong>SRI MUAR 皇城驾驶学院</strong><br>
                                Lot 77, Parit Unas, Jalan Temenggong Ahmad, 84000 Muar, Johor<br>
                                电话: 06-981 2000 | 邮箱: im_srimuar@yahoo.com</p>
                                <p><em>此邮件为系统自动发送，请勿直接回复</em></p>
                            </div>
                        </div>
                    </body>
                    </html>
                ";
                
                $mail->AltBody = "SRI MUAR 皇城驾驶学院\n\n" .
                               "尊敬的" . $name . "，您好！\n\n" .
                               "您正在请求查看SRI MUAR皇城驾驶学院的价格信息。\n\n" .
                               "查看类型：" . $vehicle_type_text . "\n" .
                               "身份证：" . $ic . "\n\n" .
                               "您的验证码是：" . $otp . "\n\n" .
                               "重要提示：\n" .
                               "- 此验证码仅1分钟内有效\n" .
                               "- 请勿将此验证码透露给他人\n" .
                               "- 如果您没有请求查看价格，请忽略此邮件\n" .
                               "- 验证码仅可用于查看" . $vehicle_type_text . "\n\n" .
                               "感谢您选择SRI MUAR皇城驾驶学院！\n\n" .
                               "SRI MUAR 皇城驾驶学院\n" .
                               "Lot 77, Parit Unas, Jalan Temenggong Ahmad, 84000 Muar, Johor\n" .
                               "电话: 06-981 2000 | 邮箱: im_srimuar@yahoo.com\n\n" .
                               "此邮件为系统自动发送，请勿直接回复";
                
                $mail->send();
                
                // 重定向到OTP验证页面
                header('Location: price_otp.php');
                exit();
                
            } catch (Exception $e) {
                $error = "邮件发送失败: " . $mail->ErrorInfo;
            }
        }
    }
}

/**
 * 验证马来西亚身份证号码格式 (12位带连字符: XXXXXX-XX-XXXX)
 */
function validateMalaysianICFormat($ic) {
    // 格式验证：YYMMDD-XX-XXXX (12位数字，带连字符)
    $pattern = '/^\d{6}-\d{2}-\d{4}$/';
    
    if (!preg_match($pattern, $ic)) {
        return false;
    }
    
    // 移除连字符
    $clean_ic = str_replace('-', '', $ic);
    
    // 验证出生日期部分 (前6位: YYMMDD)
    $year = substr($clean_ic, 0, 2);   // YY (出生年份后两位)
    $month = substr($clean_ic, 2, 2);  // MM (月份)
    $day = substr($clean_ic, 4, 2);    // DD (日期)
    
    // 验证年份 (00-99)
    if ($year < 0 || $year > 99) {
        return false;
    }
    
    // 验证月份 (01-12)
    if ($month < 1 || $month > 12) {
        return false;
    }
    
    // 验证日期 (根据月份)
    $days_in_month = [
        1 => 31, 2 => 29, 3 => 31, 4 => 30, 5 => 31, 6 => 30,
        7 => 31, 8 => 31, 9 => 30, 10 => 31, 11 => 30, 12 => 31
    ];
    
    if ($day < 1 || $day > $days_in_month[(int)$month]) {
        return false;
    }
    
    // 验证2月闰年
    if ($month == 2 && $day == 29) {
        $full_year = (int)$year + 2000; // 假设是2000年后的出生年份
        if (!($full_year % 4 == 0 && ($full_year % 100 != 0 || $full_year % 400 == 0))) {
            return false;
        }
    }
    
    return true;
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
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            color: #721c24;
        }
        
        .ic-hint {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        
        .vehicle-options {
            margin-bottom: 20px;
        }
        
        .vehicle-card {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: white;
            height: 180px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .vehicle-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        
        .vehicle-card.selected {
            border-color: #0056b3;
            background: #f0f7ff;
        }
        
        .vehicle-icon {
            font-size: 48px;
            color: #0056b3;
            margin-bottom: 15px;
        }
        
        .vehicle-radio {
            display: none;
        }
        
        .instruction-box {
            background: #d1ecf1;
            border-left: 4px solid #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            color: #0c5460;
        }
        
        .error-message {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 5px;
            display: none;
        }
        
        .error-icon {
            color: #dc3545;
            margin-right: 5px;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-control.is-invalid {
            border-color: #dc3545;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='12' height='12' fill='none' stroke='%23dc3545' viewBox='0 0 12 12'%3e%3ccircle cx='6' cy='6' r='4.5'/%3e%3cpath stroke-linejoin='round' d='M5.8 3.6h.4L6 6.5z'/%3e%3ccircle cx='6' cy='8.2' r='.6' fill='%23dc3545' stroke='none'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .form-control.is-invalid:focus {
            border-color: #dc3545;
            box-shadow: 0 0 0 0.25rem rgba(220, 53, 69, 0.25);
        }
        
        .form-control.is-valid {
            border-color: #28a745;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' width='8' height='8' viewBox='0 0 8 8'%3e%3cpath fill='%2328a745' d='M2.3 6.73L.6 4.53c-.4-1.04.46-1.4 1.1-.8l1.1 1.4 3.4-3.8c.6-.63 1.6-.27 1.2.7l-4 4.6c-.43.5-.8.4-1.1.1z'/%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right calc(0.375em + 0.1875rem) center;
            background-size: calc(0.75em + 0.375rem) calc(0.75em + 0.375rem);
        }
        
        .form-control.is-valid:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 0.25rem rgba(40, 167, 69, 0.25);
        }
        
        .validation-feedback {
            display: flex;
            align-items: center;
            margin-top: 5px;
        }
        
        .error-card {
            border-color: #dc3545 !important;
            background-color: #fff5f5 !important;
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
            <p class="lead mb-0">请选择您要查看的价格类型并填写信息</p>
        </div>
    </div>
    
    <!-- 表单容器 -->
    <div class="container">
        <div class="form-container">
            <!-- 信息提示 -->
            <div class="info-box">
                <h5 class="mb-2" style="color: red;">
                    <i class="fas fa-info-circle me-2"></i>重要提示
                </h5>
                <p class="mb-2">
                    请输入正确的马来西亚身份证号码格式：<strong>950101-01-1234</strong>
                </p>
                <p class="mb-0">
                    格式说明：出生日期(YYMMDD)-州代码-序列号
                </p>
            </div>
            
            <!-- 操作说明 -->
            <div class="instruction-box">
                <h6 class="mb-2"><i class="fas fa-exclamation-circle me-2"></i>操作说明</h6>
                <p class="mb-0">请选择您要查看的价格类型：<strong>汽车</strong> 或 <strong>摩托车</strong>。<br>每次只能查看一种类型的价格表。</p>
            </div>
            
            <!-- 错误信息 -->
            <?php if (isset($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- 表单 -->
            <form method="POST" action="" id="priceForm" novalidate>
                <!-- 车辆类型选择 -->
                <div class="mb-4">
                    <label class="form-label fw-bold mb-3">
                        <i class="fas fa-car me-1"></i> 请选择要查看的价格类型 *
                    </label>
                    <div class="row vehicle-options">
                        <div class="col-md-6 mb-3">
                            <label class="vehicle-card" id="card-car">
                                <input type="radio" class="vehicle-radio" name="vehicle_type" value="car" required>
                                <div class="vehicle-icon">
                                    <i class="fas fa-car"></i>
                                </div>
                                <h5>汽车价格</h5>
                                <p class="text-muted small">查看汽车(D/DA)课程价格</p>
                            </label>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="vehicle-card" id="card-motor">
                                <input type="radio" class="vehicle-radio" name="vehicle_type" value="motor" required>
                                <div class="vehicle-icon">
                                    <i class="fas fa-motorcycle"></i>
                                </div>
                                <h5>摩托车价格</h5>
                                <p class="text-muted small">查看摩托车(B2/B Full)课程价格</p>
                            </label>
                        </div>
                    </div>
                    <div class="error-message" id="vehicleTypeError">
                        <i class="fas fa-exclamation-circle error-icon"></i><span>请选择车辆类型</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="ic" class="form-label fw-bold">
                        <i class="fas fa-id-card me-1"></i> 身份证号码 (IC) *
                    </label>
                    <input type="text" class="form-control form-control-lg" id="ic" name="ic" 
                        placeholder="YYMMDD-XX-XXXX" 
                        pattern="\d{6}-\d{2}-\d{4}"
                        title="格式: YYMMDD-XX-XXXX (如: 950101-01-1234)"
                        required>
                    <div class="ic-hint">格式：出生日期(YYMMDD)-州代码-序列号</div>
                    <div class="error-message" id="icError">
                        <i class="fas fa-exclamation-circle error-icon"></i><span id="icErrorText">请输入有效的马来西亚身份证号码格式</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="name" class="form-label fw-bold">
                        <i class="fas fa-user me-1"></i> 填写名字 *
                    </label>
                    <input type="text" class="form-control form-control-lg" id="name" name="name" 
                           placeholder="请输入您的全名" required>
                    <div class="error-message" id="nameError">
                        <i class="fas fa-exclamation-circle error-icon"></i><span>请输入您的全名</span>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="email" class="form-label fw-bold">
                        <i class="fas fa-envelope me-1"></i> 电子邮件地址 *
                    </label>
                    <input type="email" class="form-control form-control-lg" id="email" name="email" 
                           placeholder="example@gmail.com" required>
                    <div class="form-text">验证码将发送到此邮箱</div>
                    <div class="error-message" id="emailError">
                        <i class="fas fa-exclamation-circle error-icon"></i><span>请输入有效的电子邮件地址</span>
                    </div>
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
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('priceForm');
            const icInput = document.getElementById('ic');
            const nameInput = document.getElementById('name');
            const emailInput = document.getElementById('email');
            const vehicleCards = document.querySelectorAll('.vehicle-card');
            
            // 错误消息元素
            const vehicleTypeError = document.getElementById('vehicleTypeError');
            const icError = document.getElementById('icError');
            const nameError = document.getElementById('nameError');
            const emailError = document.getElementById('emailError');
            
            // IC号码格式自动添加连字符
            icInput.addEventListener('input', function(e) {
                let value = e.target.value.replace(/\D/g, '');
                
                if (value.length > 6 && value.length <= 8) {
                    value = value.substr(0, 6) + '-' + value.substr(6);
                } else if (value.length > 8) {
                    value = value.substr(0, 6) + '-' + value.substr(6, 2) + '-' + value.substr(8, 4);
                }
                
                e.target.value = value;
                
                // 如果有值则验证
                if (value.trim()) {
                    validateIC();
                } else {
                    // 清空时不显示错误
                    icInput.classList.remove('is-invalid', 'is-valid');
                    hideError(icError);
                }
            });
            
            // 名字输入验证
            nameInput.addEventListener('input', function() {
                const value = this.value.trim();
                if (value) {
                    validateName();
                } else {
                    // 清空时不显示错误
                    nameInput.classList.remove('is-invalid', 'is-valid');
                    hideError(nameError);
                }
            });
            
            // 邮箱输入验证 - 修复这里
            emailInput.addEventListener('input', function() {
                const value = this.value.trim();
                if (value) {
                    validateEmail();
                } else {
                    // 清空时不显示错误
                    emailInput.classList.remove('is-invalid', 'is-valid');
                    hideError(emailError);
                }
            });
            
            // 车辆选择卡片点击效果
            vehicleCards.forEach(card => {
                card.addEventListener('click', function() {
                    // 移除所有选择
                    vehicleCards.forEach(c => {
                        c.classList.remove('selected');
                        c.classList.remove('error-card');
                    });
                    
                    // 添加当前选择
                    this.classList.add('selected');
                    
                    // 设置radio按钮
                    const radio = this.querySelector('.vehicle-radio');
                    if (radio) {
                        radio.checked = true;
                        hideError(vehicleTypeError);
                    }
                });
            });
            
            // IC失焦时验证
            icInput.addEventListener('blur', function() {
                const value = this.value.trim();
                if (value) {
                    validateIC();
                }
            });
            
            // 名字失焦时验证
            nameInput.addEventListener('blur', function() {
                const value = this.value.trim();
                if (value) {
                    validateName();
                }
            });
            
            // 邮箱失焦时验证
            emailInput.addEventListener('blur', function() {
                const value = this.value.trim();
                if (value) {
                    validateEmail();
                }
            });
            
            // 验证IC格式函数
            function validateIC() {
                const ic = icInput.value.trim();
                const icErrorText = document.getElementById('icErrorText');
                
                // 清空时直接返回false，但不显示错误
                if (!ic) {
                    icInput.classList.remove('is-invalid', 'is-valid');
                    hideError(icError);
                    return false;
                }
                
                // 验证格式
                const icPattern = /^\d{6}-\d{2}-\d{4}$/;
                if (!icPattern.test(ic)) {
                    showError(icError, '格式应为：YYMMDD-XX-XXXX (如: 950101-01-1234)');
                    icInput.classList.add('is-invalid');
                    icInput.classList.remove('is-valid');
                    return false;
                }
                
                // 验证IC中的出生日期 (YYMMDD格式)
                const cleanIC = ic.replace(/-/g, '');
                const year = parseInt(cleanIC.substr(0, 2));   // YY
                const month = parseInt(cleanIC.substr(2, 2));  // MM
                const day = parseInt(cleanIC.substr(4, 2));    // DD
                
                // 验证月份
                if (month < 1 || month > 12) {
                    showError(icError, '身份证号码中的月份无效 (应为01-12)');
                    icInput.classList.add('is-invalid');
                    icInput.classList.remove('is-valid');
                    return false;
                }
                
                // 验证日期
                const daysInMonth = [31, 29, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
                if (day < 1 || day > daysInMonth[month - 1]) {
                    showError(icError, '身份证号码中的日期无效');
                    icInput.classList.add('is-invalid');
                    icInput.classList.remove('is-valid');
                    return false;
                }
                
                // 验证闰年2月29日
                if (month === 2 && day === 29) {
                    const full_year = year + 2000; // 假设是2000年后的出生年份
                    if (!(full_year % 4 == 0 && (full_year % 100 != 0 || full_year % 400 == 0))) {
                        showError(icError, '非闰年不能有2月29日');
                        icInput.classList.add('is-invalid');
                        icInput.classList.remove('is-valid');
                        return false;
                    }
                }
                
                // 验证通过
                hideError(icError);
                icInput.classList.remove('is-invalid');
                icInput.classList.add('is-valid');
                return true;
            }
            
            // 验证名字函数
            function validateName() {
                const name = nameInput.value.trim();
                
                // 清空时直接返回false，但不显示错误
                if (!name) {
                    nameInput.classList.remove('is-invalid', 'is-valid');
                    hideError(nameError);
                    return false;
                }
                
                if (name.length < 2) {
                    showError(nameError, '名字至少需要2个字符');
                    nameInput.classList.add('is-invalid');
                    nameInput.classList.remove('is-valid');
                    return false;
                }
                
                // 验证通过
                hideError(nameError);
                nameInput.classList.remove('is-invalid');
                nameInput.classList.add('is-valid');
                return true;
            }
            
            // 验证邮箱函数 - 修复这里
            function validateEmail() {
                const email = emailInput.value.trim();
                
                // 清空时直接返回false，但不显示错误
                if (!email) {
                    emailInput.classList.remove('is-invalid', 'is-valid');
                    hideError(emailError);
                    return false;
                }
                
                // 验证邮箱格式
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!emailRegex.test(email)) {
                    showError(emailError, '请输入有效的电子邮件地址 (如: example@gmail.com)');
                    emailInput.classList.add('is-invalid');
                    emailInput.classList.remove('is-valid');
                    return false;
                }
                
                // 验证通过
                hideError(emailError);
                emailInput.classList.remove('is-invalid');
                emailInput.classList.add('is-valid');
                return true;
            }
            
            // 验证车辆类型
            function validateVehicleType() {
                const vehicleSelected = document.querySelector('input[name="vehicle_type"]:checked');
                
                if (!vehicleSelected) {
                    // 给车辆卡片添加错误样式
                    vehicleCards.forEach(card => {
                        card.classList.add('error-card');
                    });
                    showError(vehicleTypeError, '请选择车辆类型');
                    return false;
                }
                
                // 移除错误样式
                vehicleCards.forEach(card => {
                    card.classList.remove('error-card');
                });
                hideError(vehicleTypeError);
                return true;
            }
            
            // 显示错误消息
            function showError(errorElement, message) {
                const spanElement = errorElement.querySelector('span');
                if (spanElement) {
                    spanElement.textContent = message;
                }
                errorElement.style.display = 'flex';
            }
            
            // 隐藏错误消息
            function hideError(errorElement) {
                errorElement.style.display = 'none';
            }
            
            // 表单提交验证
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                let isValid = true;
                
                // 验证所有字段
                if (!validateVehicleType()) isValid = false;
                if (!validateIC()) isValid = false;
                if (!validateName()) isValid = false;
                if (!validateEmail()) isValid = false;
                
                if (!isValid) {
                    // 滚动到第一个错误字段
                    const firstError = form.querySelector('.error-message[style*="display: flex"]');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                    return false;
                }
                
                // 确认选择
                const vehicleType = document.querySelector('input[name="vehicle_type"]:checked');
                const vehicleTypeText = vehicleType.value === 'car' ? '汽车价格' : '摩托车价格';
                const confirmMessage = `您选择查看：${vehicleTypeText}\n验证码将发送到：${emailInput.value}\n确认提交吗？`;
                
                if (!confirm(confirmMessage)) {
                    return false;
                }
                
                // 提交表单
                this.submit();
            });
        });
    </script>
</body>
</html>