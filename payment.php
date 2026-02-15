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
$payment_reference = $registration['payment_reference'];

// 文件上传目录
$upload_dir = "uploads/receipts/";
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// 数据库配置
define('DB_HOST', 'localhost');
define('DB_USER', 'u326148221_sriuser');
define('DB_PASS', 'SriMuar@2026!');
define('DB_NAME', 'u326148221_sri_muar');

// 从数据库获取价格信息
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if ($conn->connect_error) {
    die("数据库连接失败: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// 获取支付记录信息
$stmt = $conn->prepare("
    SELECT full_price, deposit_price, payment_amount 
    FROM payment_records 
    WHERE reference_number = ?
");
$stmt->bind_param("s", $payment_reference);
$stmt->execute();
$stmt->bind_result($full_price, $deposit_price, $current_payment_amount);
$stmt->fetch();
$stmt->close();
$conn->close();

// 处理支付选择和收据上传
$error = '';
$success = '';
$payment_type = isset($_POST['payment_type']) ? $_POST['payment_type'] : 'deposit';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 检查支付类型
    if (!isset($_POST['payment_type'])) {
        $error = "请选择支付方式";
    }
    // 检查文件上传
    elseif (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
        $upload_error = $_FILES['receipt']['error'] ?? '没有文件';
        switch ($upload_error) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error = "文件太大，请上传小于5MB的文件";
                break;
            case UPLOAD_ERR_PARTIAL:
                $error = "文件只上传了一部分，请重新上传";
                break;
            case UPLOAD_ERR_NO_FILE:
                $error = "请选择要上传的收据文件";
                break;
            default:
                $error = "文件上传失败，请重试 (错误代码: " . $upload_error . ")";
        }
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
            // 生成唯一文件名
            $file_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $file_name = uniqid() . '_' . $payment_reference . '_receipt.' . $file_ext;
            $file_path = $upload_dir . $file_name;
            
            if (move_uploaded_file($file['tmp_name'], $file_path)) {
                // 根据支付类型计算支付金额
                if ($payment_type === 'full') {
                    $payment_amount = $full_price;
                    $payment_description = "全额支付";
                } else {
                    $payment_amount = $deposit_price;
                    $payment_description = "订金支付";
                }
                
                // 更新数据库
                $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
                if ($conn->connect_error) {
                    $error = "数据库连接失败: " . $conn->connect_error;
                } else {
                    $conn->set_charset("utf8mb4");
                    
                    // 更新支付记录
                    $stmt = $conn->prepare("
                        UPDATE payment_records 
                        SET payment_type = ?, payment_amount = ?, 
                            receipt_path = ?, payment_date = NOW(),
                            payment_status = 'paid'
                        WHERE reference_number = ?
                    ");
                    $stmt->bind_param("sdss", $payment_type, $payment_amount, $file_path, $payment_reference);
                    
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
                        
                        $success = "收据上传成功！您的{$payment_description}已完成。";
                        unset($_SESSION['payment_registration']);
                    } else {
                        $error = "更新支付记录失败: " . $stmt->error;
                        // 删除已上传的文件
                        if (file_exists($file_path)) {
                            unlink($file_path);
                        }
                    }
                    
                    $stmt->close();
                    $conn->close();
                }
            } else {
                $error = "文件保存失败，请检查上传目录权限";
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
        'B_Full' => 'B Full 驾照 (不限排量)',
        'B_Full_Tambah_kelas' => 'B Full - Tambah kelas (额外课程)'
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
            padding: 20px;
        }
        
        .payment-container {
            max-width: 900px;
            margin: 0 auto;
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
        
        .payment-options {
            margin: 30px 0;
        }
        
        .payment-option-card {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            padding: 20px;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 15px;
            display: block;
        }
        
        .payment-option-card:hover {
            border-color: #0056b3;
            background: #f0f7ff;
        }
        
        .payment-option-card.selected {
            border-color: #0056b3;
            background: #e8f4ff;
        }
        
        .payment-option-card.error-card {
            border-color: #dc3545;
            background: #fff5f5;
        }
        
        .payment-radio {
            display: none;
        }
        
        .payment-option-header {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .payment-icon {
            font-size: 24px;
            color: #0056b3;
            margin-right: 15px;
        }
        
        .payment-price {
            font-size: 1.5rem;
            font-weight: bold;
            color: #dc3545;
            text-align: right;
            flex: 1;
        }
        
        .payment-description {
            color: #666;
            font-size: 0.9rem;
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
        
        .qr-code img {
            width: 100%;
            height: auto;
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
            word-break: break-all;
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
        
        .upload-area.drag-over {
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
        
        .file-name {
            margin-top: 10px;
            padding: 5px;
            background: #e9ecef;
            border-radius: 5px;
            font-size: 0.9rem;
        }
        
        .preview-image {
            max-width: 100%;
            max-height: 200px;
            margin-top: 15px;
            border-radius: 5px;
            display: none;
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
        
        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .btn-outline-secondary {
            border-radius: 25px;
            padding: 12px 30px;
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
        
        .full-price-info {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .deposit-price-info {
            background: #d1ecf1;
            border-left: 4px solid #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin: 20px 0;
        }
        
        .required-badge {
            background-color: #dc3545;
            color: white;
            padding: 2px 8px;
            border-radius: 10px;
            font-size: 0.75rem;
            margin-left: 5px;
        }
        
        .error-message {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 5px;
            display: none;
        }
        
        .error-message.show {
            display: block;
        }
        
        .error-icon {
            color: #dc3545;
            margin-right: 5px;
        }
        
        .file-info {
            font-size: 0.85rem;
            color: #666;
            margin-top: 5px;
        }
        
        .instruction-box {
            background: #d1ecf1;
            border-left: 4px solid #0c5460;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            color: #0c5460;
        }
        
        .info-box {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            color: #856404;
        }
        
        @media (max-width: 768px) {
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
            
            .payment-price {
                font-size: 1.2rem;
            }
            
            .info-item {
                flex-direction: column;
            }
            
            .info-label {
                min-width: auto;
                margin-bottom: 5px;
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
            <p class="lead mb-0">请选择支付方式并完成支付</p>
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
                    <div class="step-label">选择支付</div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-label">上传收据</div>
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
                <div class="alert alert-success" role="alert">
                    <i class="fas fa-check-circle me-2"></i> <?php echo htmlspecialchars($success); ?>
                    <div class="mt-3">
                        <a href="register.php" class="btn btn-success me-2">
                            <i class="fas fa-user-plus me-1"></i> 注册新学员
                        </a>
                        <a href="index.php" class="btn btn-outline-secondary">
                            <i class="fas fa-home me-1"></i> 返回首页
                        </a>
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (empty($success)): ?>
                <!-- 支付说明 -->
                <div class="instruction-box">
                    <h6 class="mb-2"><i class="fas fa-exclamation-circle me-2"></i>支付说明</h6>
                    <ul class="mb-0">
                        <li>扫描下方二维码完成支付</li>
                        <li>支付后请截图保存收据</li>
                        <li>上传清晰的支付截图</li>
                        <li>支付审核通过后注册完成</li>
                    </ul>
                </div>
                
                <!-- 重要提示 -->
                <div class="info-box">
                    <h6 class="mb-2"><i class="fas fa-info-circle me-2"></i>重要提示</h6>
                    <ul class="mb-0">
                        <li>请确保支付金额正确</li>
                        <li>上传的收据必须清晰可见</li>
                        <li>审核时间：1-2个工作日</li>
                        <li>如有问题请联系客服</li>
                    </ul>
                </div>
                
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
                    <div class="info-item">
                        <div class="info-label">课程描述：</div>
                        <div class="info-value"><?php echo htmlspecialchars($registration['course_description']); ?></div>
                    </div>
                </div>
                
                <!-- 价格信息 -->
                <div class="full-price-info">
                    <h6><i class="fas fa-money-bill-wave me-2"></i>全额价格</h6>
                    <div style="font-size: 1.8rem; font-weight: bold; color: #dc3545;">
                        RM <?php echo number_format($full_price, 2); ?>
                    </div>
                    <p class="mb-0 mt-2">一次性支付全部课程费用</p>
                </div>
                
                <div class="deposit-price-info">
                    <h6><i class="fas fa-hand-holding-usd me-2"></i>订金价格</h6>
                    <div style="font-size: 1.8rem; font-weight: bold; color: #0c5460;">
                        RM <?php echo number_format($deposit_price, 2); ?>
                    </div>
                    <p class="mb-0 mt-2">先支付订金，剩余费用可在课程开始前到线下来支付</p>
                </div>
                
                <!-- 支付选择 -->
                <div class="payment-options">
                    <h5 class="mb-4"><i class="fas fa-credit-card me-2"></i>选择支付方式 <span class="required-badge">必选</span></h5>
                    
                    <form method="POST" action="" id="paymentForm" enctype="multipart/form-data">
                        <!-- 全额支付选项 -->
                        <label class="payment-option-card <?php echo ($payment_type === 'full') ? 'selected' : ''; ?>" id="fullPaymentCard">
                            <input type="radio" class="payment-radio" name="payment_type" value="full" 
                                   <?php echo ($payment_type === 'full') ? 'checked' : ''; ?>>
                            <div class="payment-option-header">
                                <div class="payment-icon">
                                    <i class="fas fa-check-circle"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">全额支付</h5>
                                    <p class="payment-description mb-0">一次性支付全部课程费用</p>
                                </div>
                                <div class="payment-price">
                                    RM <?php echo number_format($full_price, 2); ?>
                                </div>
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                支付全部费用，一次完成
                            </div>
                        </label>
                        
                        <!-- 订金支付选项 -->
                        <label class="payment-option-card <?php echo ($payment_type === 'deposit') ? 'selected' : ''; ?>" id="depositPaymentCard">
                            <input type="radio" class="payment-radio" name="payment_type" value="deposit" 
                                   <?php echo ($payment_type === 'deposit') ? 'checked' : ''; ?>>
                            <div class="payment-option-header">
                                <div class="payment-icon">
                                    <i class="fas fa-hand-holding-usd"></i>
                                </div>
                                <div>
                                    <h5 class="mb-1">订金支付</h5>
                                    <p class="payment-description mb-0">先支付订金保留名额</p>
                                </div>
                                <div class="payment-price">
                                    RM <?php echo number_format($deposit_price, 2); ?>
                                </div>
                            </div>
                            <div class="form-text">
                                <i class="fas fa-info-circle me-1"></i>
                                剩余 RM <?php echo number_format($full_price - $deposit_price, 2); ?> 可在课程开始前到线下来支付
                            </div>
                        </label>
                        
                        <div class="error-message" id="paymentTypeError">
                            <i class="fas fa-exclamation-circle error-icon"></i><span>请选择支付方式</span>
                        </div>
                        
                        <!-- 支付参考号 -->
                        <div class="reference-box">
                            <h6 class="text-center mb-2">支付参考号</h6>
                            <div class="reference-number text-center"><?php echo htmlspecialchars($payment_reference); ?></div>
                            <p class="text-muted small text-center mt-2">支付时请记录此参考号</p>
                        </div>
                        
                        <!-- 二维码支付 -->
                        <div class="qr-container">
                            <h5 class="mb-4"><i class="fas fa-qrcode me-2"></i>扫描二维码支付</h5>
                            <div class="qr-code">
                                <img src="duitnow-qr.jpeg" alt="DuitNow QR Code" class="img-fluid">
                            </div>
                            <p class="text-muted">
                                使用DuitNow、Touch 'n Go、Boost等电子钱包扫描二维码支付
                            </p>
                        </div>
                        
                        <!-- 收据上传 -->
                        <h5 class="mb-3 mt-4"><i class="fas fa-receipt me-2"></i>上传支付收据 <span class="required-badge">必需</span></h5>
                        
                        <div class="form-group">
                            <label class="form-label fw-bold">
                                支付收据/截图 <span class="required-badge">必需</span>
                            </label>
                            <div class="upload-area" id="receiptUploadArea" onclick="document.getElementById('receipt').click();">
                                <input type="file" class="file-input" id="receipt" name="receipt" accept="image/*,.pdf" style="display: none;">
                                <i class="fas fa-cloud-upload-alt"></i>
                                <h5>点击或拖拽上传支付收据</h5>
                                <p class="text-muted">请上传清晰的支付截图或收据照片</p>
                                <div class="file-name" id="receiptFileName"></div>
                                <img src="" class="preview-image" id="receiptPreview">
                            </div>
                            <div class="file-info">支持格式：JPG, PNG, GIF, PDF | 最大5MB</div>
                            <div class="error-message" id="receiptError">
                                <i class="fas fa-exclamation-circle error-icon"></i><span>请上传支付收据</span>
                            </div>
                        </div>
                        
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                <i class="fas fa-paper-plane me-2"></i> 提交收据完成注册
                            </button>
                            <a href="register.php" class="btn btn-outline-secondary">
                                <i class="fas fa-arrow-left me-2"></i> 返回修改信息
                            </a>
                        </div>
                    </form>
                </div>
                
                <hr class="my-4">
                
                <div class="alert alert-info">
                    <h6><i class="fas fa-exclamation-circle me-2"></i>重要提示</h6>
                    <ul class="mb-0">
                        <li>支付成功后请立即上传收据</li>
                        <li>上传收据后，管理员会审核您的支付</li>
                        <li>支付成功后，您的注册才算完成</li>
                        <li>如选择订金支付，剩余费用可在课程开始前到线下来支付</li>
                        <li>如有问题，请联系：06-981 2000</li>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        console.log('支付页面加载完成');
        
        // 获取元素
        const paymentForm = document.getElementById('paymentForm');
        const fullPaymentCard = document.getElementById('fullPaymentCard');
        const depositPaymentCard = document.getElementById('depositPaymentCard');
        const paymentRadios = document.querySelectorAll('.payment-radio');
        const paymentCards = document.querySelectorAll('.payment-option-card');
        const receiptInput = document.getElementById('receipt');
        const receiptUploadArea = document.getElementById('receiptUploadArea');
        const receiptFileName = document.getElementById('receiptFileName');
        const receiptPreview = document.getElementById('receiptPreview');
        const receiptError = document.getElementById('receiptError');
        const paymentTypeError = document.getElementById('paymentTypeError');
        const submitBtn = document.getElementById('submitBtn');
        
        // 支付方式选择
        paymentRadios.forEach(radio => {
            radio.addEventListener('change', function() {
                // 移除所有卡片的选择状态
                paymentCards.forEach(card => {
                    card.classList.remove('selected');
                    card.classList.remove('error-card');
                });
                
                // 添加当前选择卡片的样式
                const card = this.closest('.payment-option-card');
                if (card) {
                    card.classList.add('selected');
                }
                
                // 隐藏错误
                hideError(paymentTypeError);
            });
        });
        
        // 卡片点击选择
        fullPaymentCard.addEventListener('click', function(e) {
            e.preventDefault();
            const radio = this.querySelector('.payment-radio');
            radio.checked = true;
            radio.dispatchEvent(new Event('change'));
        });
        
        depositPaymentCard.addEventListener('click', function(e) {
            e.preventDefault();
            const radio = this.querySelector('.payment-radio');
            radio.checked = true;
            radio.dispatchEvent(new Event('change'));
        });
        
        // 文件上传区域点击
        if (receiptUploadArea && receiptInput) {
            receiptUploadArea.addEventListener('click', function(e) {
                // 已经通过 onclick 处理，这里不需要再处理
                console.log('点击上传区域');
            });
            
            // 文件选择变化
            receiptInput.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    console.log('已选择文件:', file.name);
                    
                    // 验证文件类型
                    const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
                    const fileType = file.type.toLowerCase();
                    
                    if (!allowedTypes.includes(fileType)) {
                        alert('只允许上传图片文件或PDF文件 (JPG, PNG, GIF, PDF)');
                        this.value = '';
                        receiptFileName.textContent = '';
                        receiptPreview.style.display = 'none';
                        return;
                    }
                    
                    // 验证文件大小 (5MB)
                    if (file.size > 5 * 1024 * 1024) {
                        alert('文件大小不能超过5MB');
                        this.value = '';
                        receiptFileName.textContent = '';
                        receiptPreview.style.display = 'none';
                        return;
                    }
                    
                    // 显示文件名
                    receiptFileName.textContent = '已选择: ' + file.name + ' (' + (file.size / 1024).toFixed(2) + ' KB)';
                    
                    // 预览图片（如果是图片）
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            receiptPreview.src = e.target.result;
                            receiptPreview.style.display = 'block';
                        };
                        reader.readAsDataURL(file);
                    } else {
                        receiptPreview.style.display = 'none';
                    }
                    
                    // 更新上传区域样式
                    receiptUploadArea.style.borderColor = '#0056b3';
                    receiptUploadArea.style.backgroundColor = '#e8f4ff';
                    
                    // 隐藏错误
                    if (receiptError) receiptError.style.display = 'none';
                } else {
                    receiptFileName.textContent = '';
                    receiptPreview.style.display = 'none';
                    receiptUploadArea.style.borderColor = '#ddd';
                    receiptUploadArea.style.backgroundColor = '#f8f9fa';
                }
            });
            
            // 拖拽功能
            receiptUploadArea.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('drag-over');
            });
            
            receiptUploadArea.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('drag-over');
            });
            
            receiptUploadArea.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('drag-over');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    receiptInput.files = files;
                    receiptInput.dispatchEvent(new Event('change'));
                }
            });
        }
        
        // 显示错误消息
        function showError(errorElement, message) {
            if (!errorElement) return;
            const spanElement = errorElement.querySelector('span');
            if (spanElement) {
                spanElement.textContent = message;
            }
            errorElement.style.display = 'block';
            
            // 如果错误是关于支付方式，高亮支付卡片
            if (errorElement === paymentTypeError) {
                paymentCards.forEach(card => {
                    card.classList.add('error-card');
                });
            }
        }
        
        // 隐藏错误消息
        function hideError(errorElement) {
            if (!errorElement) return;
            errorElement.style.display = 'none';
            
            // 如果隐藏的是支付方式错误，移除卡片错误样式
            if (errorElement === paymentTypeError) {
                paymentCards.forEach(card => {
                    card.classList.remove('error-card');
                });
            }
        }
        
        // 验证支付方式
        function validatePaymentType() {
            const selectedPayment = document.querySelector('input[name="payment_type"]:checked');
            
            if (!selectedPayment) {
                showError(paymentTypeError, '请选择支付方式（全额或订金）');
                return false;
            }
            
            hideError(paymentTypeError);
            return true;
        }
        
        // 验证收据文件
        function validateReceipt() {
            if (!receiptInput.files || receiptInput.files.length === 0) {
                showError(receiptError, '请上传支付收据');
                receiptUploadArea.style.borderColor = '#dc3545';
                receiptUploadArea.style.backgroundColor = '#fff5f5';
                return false;
            }
            
            const file = receiptInput.files[0];
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
            const fileType = file.type.toLowerCase();
            
            if (!allowedTypes.includes(fileType)) {
                showError(receiptError, '只允许上传图片文件或PDF文件');
                return false;
            }
            
            if (file.size > 5 * 1024 * 1024) {
                showError(receiptError, '文件大小不能超过5MB');
                return false;
            }
            
            hideError(receiptError);
            receiptUploadArea.style.borderColor = '#28a745';
            receiptUploadArea.style.backgroundColor = '#d4edda';
            return true;
        }
        
        // 表单提交验证
        if (paymentForm) {
            paymentForm.addEventListener('submit', function(e) {
                e.preventDefault();
                console.log('表单提交验证');
                
                let isValid = true;
                
                // 验证支付方式
                if (!validatePaymentType()) {
                    isValid = false;
                }
                
                // 验证收据文件
                if (!validateReceipt()) {
                    isValid = false;
                }
                
                if (!isValid) {
                    // 滚动到第一个错误
                    const firstError = document.querySelector('.error-message[style*="display: block"]');
                    if (firstError) {
                        firstError.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    } else {
                        const errorCard = document.querySelector('.error-card');
                        if (errorCard) {
                            errorCard.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        }
                    }
                    return false;
                }
                
                // 获取选中的支付方式
                const selectedPayment = document.querySelector('input[name="payment_type"]:checked');
                const paymentType = selectedPayment.value === 'full' ? '全额支付' : '订金支付';
                const paymentAmount = selectedPayment.value === 'full' ? 
                    'RM <?php echo number_format($full_price, 2); ?>' : 
                    'RM <?php echo number_format($deposit_price, 2); ?>';
                
                // 确认对话框
                const confirmMsg = `确认提交${paymentType}收据？\n支付金额：${paymentAmount}\n\n提交后管理员会审核您的支付。`;
                
                if (confirm(confirmMsg)) {
                    console.log('用户确认提交');
                    
                    // 禁用按钮
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>正在提交...';
                    
                    // 提交表单
                    this.submit();
                }
                
                return false;
            });
        }
        
        console.log('所有事件监听器已绑定');
    });
    </script>
</body>
</html>