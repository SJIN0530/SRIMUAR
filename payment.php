<?php
session_start();
date_default_timezone_set('Asia/Kuala_Lumpur');

// 检查是否有待支付的注册
if (!isset($_SESSION['payment_registration']) || !isset($_GET['ref'])) {
    header("Location: register.php");
    exit;
}

// 验证参考号是否匹配
if ($_SESSION['payment_registration']['payment_reference'] !== $_GET['ref']) {
    header("Location: register.php");
    exit;
}

$registration = $_SESSION['payment_registration'];
$payment_amount = $registration['payment_amount'];
$payment_reference = $registration['payment_reference'];

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'sri_muar');

// 处理收据上传
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 检查收据上传
    if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== 0) {
        $error = "请上传支付收据";
    } else {
        $file = $_FILES['receipt'];
        
        // 验证文件类型
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
        $file_type = mime_content_type($file['tmp_name']);
        
        if (!in_array($file_type, $allowed_types)) {
            $error = "只允许上传图片文件或PDF文件";
        } elseif ($file['size'] > 5 * 1024 * 1024) {
            $error = "文件大小不能超过5MB";
        } else {
            // 上传目录
            $upload_dir = "uploads/receipts/";
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // 生成唯一文件名
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $file_name = uniqid() . '_' . $payment_reference . '.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // 更新数据库
                $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                if ($conn->connect_error) {
                    $error = "数据库连接失败: " . $conn->connect_error;
                } else {
                    $conn->set_charset("utf8mb4");
                    
                    // 更新支付记录
                    $stmt = $conn->prepare("
                        UPDATE payment_records 
                        SET payment_status = 'paid', receipt_path = ?, payment_date = NOW() 
                        WHERE reference_number = ?
                    ");
                    $stmt->bind_param("ss", $file_path, $payment_reference);
                    
                    if ($stmt->execute()) {
                        // 更新注册记录的支付状态
                        $stmt2 = $conn->prepare("
                            UPDATE student_registrations 
                            SET payment_status = 'paid' 
                            WHERE payment_reference = ?
                        ");
                        $stmt2->bind_param("s", $payment_reference);
                        $stmt2->execute();
                        $stmt2->close();
                        
                        $success = "收据上传成功！您的注册已完成。";
                        unset($_SESSION['payment_registration']);
                    } else {
                        $error = "更新支付记录失败: " . $stmt->error;
                    }
                    
                    $stmt->close();
                    $conn->close();
                }
            } else {
                $error = "文件上传失败，请重试";
            }
        }
    }
}

// 获取执照类别文字
function getLicenseClassText($license_class) {
    $classes = [
        'D' => 'D 驾照 (手动挡)',
        'DA' => 'DA 驾照 (自动挡)',
        'B2' => 'B2 驾照 (250cc及以下)',
        'B_Full' => 'B Full 驾照 (不限排量)'
    ];
    
    return $classes[$license_class] ?? $license_class;
}
?>
<!DOCTYPE html>
<html lang="zh-MY">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>支付报名费 - SRI MUAR 皇城驾驶学院</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 图标 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-blue: #0056b3;
            --secondary-orange: #FF6B00;
        }
        
        body {
            font-family: 'Microsoft YaHei', 'Segoe UI', sans-serif;
            background: #f8f9fa;
        }
        
        .payment-container {
            max-width: 900px;
            margin: 50px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .payment-header {
            background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
            color: white;
            padding: 40px;
            text-align: center;
        }
        
        .payment-body {
            padding: 40px;
        }
        
        .registration-info {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 30px;
        }
        
        .info-item {
            display: flex;
            margin-bottom: 10px;
        }
        
        .info-label {
            font-weight: bold;
            min-width: 150px;
            color: #666;
        }
        
        .info-value {
            flex: 1;
        }
        
        .qr-container {
            text-align: center;
            margin: 30px 0;
            padding: 30px;
            border: 2px dashed #ddd;
            border-radius: 10px;
            background: #fff;
        }
        
        .qr-code {
            max-width: 300px;
            margin: 0 auto 20px;
        }
        
        .amount-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .amount {
            font-size: 2rem;
            font-weight: bold;
            color: #dc3545;
            text-align: center;
        }
        
        .reference-box {
            background: #d4edda;
            border-left: 4px solid #28a745;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .reference-number {
            font-family: monospace;
            font-size: 1.2rem;
            font-weight: bold;
            color: #155724;
        }
        
        .upload-area {
            border: 2px dashed #ddd;
            border-radius: 10px;
            padding: 30px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 20px;
        }
        
        .upload-area:hover {
            border-color: #0056b3;
            background: #e8f4ff;
        }
        
        .upload-area i {
            font-size: 48px;
            color: #6c757d;
            margin-bottom: 15px;
        }
        
        .file-input {
            display: none;
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
        
        .steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }
        
        .steps::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 10%;
            right: 10%;
            height: 2px;
            background: #e9ecef;
            z-index: 1;
        }
        
        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }
        
        .step-number {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: bold;
            margin: 0 auto 10px;
        }
        
        .step.active .step-number {
            background: var(--primary-blue);
            color: white;
        }
        
        .step.completed .step-number {
            background: #28a745;
            color: white;
        }
        
        .step-label {
            font-size: 0.9rem;
            color: #6c757d;
        }
        
        .step.active .step-label {
            color: var(--primary-blue);
            font-weight: bold;
        }
        
        @media (max-width: 768px) {
            .payment-container {
                margin: 20px auto;
            }
            
            .payment-header,
            .payment-body {
                padding: 20px;
            }
            
            .steps {
                flex-direction: column;
                gap: 20px;
            }
            
            .steps::before {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="payment-container">
        <div class="payment-header">
            <h1 class="display-6 fw-bold mb-3">
                <i class="fas fa-credit-card me-2"></i>支付报名费
            </h1>
            <p class="lead mb-0">请完成支付以完成注册</p>
        </div>
        
        <div class="payment-body">
            <!-- 步骤指示器 -->
            <div class="steps">
                <div class="step completed">
                    <div class="step-number">
                        <i class="fas fa-check"></i>
                    </div>
                    <div class="step-label">填写信息</div>
                </div>
                <div class="step active">
                    <div class="step-number">2</div>
                    <div class="step-label">支付费用</div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-label">完成注册</div>
                </div>
            </div>
            
            <!-- 错误信息 -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> <?php echo htmlspecialchars($error); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <!-- 成功信息 -->
            <?php if (!empty($success)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
                    <div class="mt-3">
                        <a href="register.php" class="btn btn-success me-2">
                            <i class="fas fa-user-plus me-1"></i> 注册新学员
                        </a>
                        <a href="index.html" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-1"></i> 返回首页
                        </a>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            
            <?php if (empty($success)): ?>
                <!-- 注册信息 -->
                <div class="registration-info">
                    <h5 class="mb-3"><i class="fas fa-info-circle me-2"></i>注册信息</h5>
                    <div class="info-item">
                        <div class="info-label">姓名：</div>
                        <div class="info-value"><?php echo htmlspecialchars($registration['name']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">身份证：</div>
                        <div class="info-value"><?php echo htmlspecialchars($registration['ic_number']); ?></div>
                    </div>
                    <div class="info-item">
                        <div class="info-label">课程：</div>
                        <div class="info-value">
                            <?php 
                            $vehicleText = $registration['vehicle_type'] == 'car' ? '汽车' : '摩托车';
                            $licenseText = getLicenseClassText($registration['license_class']);
                            echo htmlspecialchars($vehicleText . '课程 (' . $licenseText . ')');
                            ?>
                        </div>
                    </div>
                </div>
                
                <!-- 支付金额 -->
                <div class="amount-box">
                    <h6 class="text-center mb-2">支付金额</h6>
                    <div class="amount">RM <?php echo number_format($payment_amount, 2); ?></div>
                    <p class="text-center mb-0"><?php echo htmlspecialchars($registration['course_description']); ?></p>
                </div>
                
                <!-- 支付参考号 -->
                <div class="reference-box">
                    <h6 class="text-center mb-2">支付参考号</h6>
                    <div class="reference-number text-center"><?php echo htmlspecialchars($payment_reference); ?></div>
                    <p class="text-center mb-0 mt-2">请在支付备注中填写此参考号</p>
                </div>
                
                <!-- 二维码支付 -->
                <div class="qr-container">
                    <h5 class="mb-4"><i class="fas fa-qrcode me-2"></i>扫描二维码支付</h5>
                    <div class="qr-code">
                        <img src="duitnow-qr.jpeg" alt="DuitNow QR Code" class="img-fluid">
                    </div>
                    <p class="text-muted">
                        使用DuitNow或支持的电子钱包扫描二维码支付<br>
                        支付时请备注参考号：<strong><?php echo $payment_reference; ?></strong>
                    </p>
                </div>
                
                <!-- 收据上传 -->
                <form method="POST" action="" enctype="multipart/form-data" id="receiptForm">
                    <h5 class="mb-3"><i class="fas fa-receipt me-2"></i>上传支付收据</h5>
                    
                    <div class="mb-3">
                        <label for="receipt" class="form-label fw-bold">支付收据/截图</label>
                        <div class="upload-area" id="uploadArea">
                            <input type="file" class="file-input" id="receipt" name="receipt" accept="image/*,.pdf" required>
                            <i class="fas fa-cloud-upload-alt"></i>
                            <h5>点击或拖拽上传支付收据</h5>
                            <p class="text-muted">请上传清晰的支付截图或收据照片</p>
                            <div class="file-name" id="fileName"></div>
                        </div>
                        <div class="form-text">支持格式：JPG, PNG, GIF, PDF | 最大5MB</div>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">
                            <i class="fas fa-paper-plane me-2"></i> 提交收据完成注册
                        </button>
                        <a href="register.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i> 返回修改信息
                        </a>
                    </div>
                </form>
                
                <hr class="my-4">
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-exclamation-circle me-2"></i>重要提示</h6>
                    <ul class="mb-0">
                        <li>请务必在支付备注中填写支付参考号：<strong><?php echo $payment_reference; ?></strong></li>
                        <li>支付成功后请立即上传收据</li>
                        <li>上传收据后，管理员会审核您的支付</li>
                        <li>支付确认后，您的注册才算完成</li>
                        <li>如有问题，请联系：06-981 2000</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const receiptInput = document.getElementById('receipt');
            const uploadArea = document.getElementById('uploadArea');
            const fileName = document.getElementById('fileName');
            const receiptForm = document.getElementById('receiptForm');
            
            // 文件上传处理
            receiptInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    fileName.textContent = file.name;
                    uploadArea.classList.add('drag-over');
                    
                    // 3秒后移除高亮
                    setTimeout(() => {
                        uploadArea.classList.remove('drag-over');
                    }, 3000);
                }
            });
            
            // 拖拽功能
            uploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('drag-over');
            });
            
            uploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('drag-over');
            });
            
            uploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('drag-over');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    receiptInput.files = files;
                    receiptInput.dispatchEvent(new Event('change'));
                }
            });
            
            // 表单提交验证
            receiptForm.addEventListener('submit', function(e) {
                const file = receiptInput.files[0];
                if (!file) {
                    e.preventDefault();
                    alert('请上传支付收据');
                    return false;
                }
                
                const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
                if (!allowedTypes.includes(file.type)) {
                    e.preventDefault();
                    alert('只允许上传图片文件或PDF文件');
                    return false;
                }
                
                if (file.size > 5 * 1024 * 1024) {
                    e.preventDefault();
                    alert('文件大小不能超过5MB');
                    return false;
                }
                
                const confirmMessage = '确认提交收据？提交后管理员会审核您的支付。';
                if (!confirm(confirmMessage)) {
                    e.preventDefault();
                    return false;
                }
                
                // 显示加载状态
                const submitBtn = this.querySelector('button[type="submit"]');
                const originalText = submitBtn.innerHTML;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>正在提交...';
                submitBtn.disabled = true;
                
                return true;
            });
        });
    </script>
</body>
</html>