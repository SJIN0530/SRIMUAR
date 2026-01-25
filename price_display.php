<?php
session_start();

// 检查是否已验证且未过期
if (!isset($_SESSION['price_verification']) || 
    !$_SESSION['price_verification']['verified'] ||
    (time() - $_SESSION['price_verification']['verified_time']) > 600) {
    header('Location: price_information.php');
    exit();
}

// 检查价格类型
$type = $_GET['type'] ?? 'all'; // all, car, motor
$valid_types = ['all', 'car', 'motor'];
if (!in_array($type, $valid_types)) {
    $type = 'all';
}

// 根据类型设置PDF文件
if ($type == 'car') {
    $pdf_file = 'Price-Kereta.pdf';
    $pdf_title = '汽车课程价格表';
} elseif ($type == 'motor') {
    $pdf_file = 'Price-Motor.pdf';
    $pdf_title = '摩托车课程价格表';
} else {
    // 全部价格时显示两个PDF
    $pdf_files = [
        'car' => 'Price-Kereta.pdf',
        'motor' => 'Price-Motor.pdf'
    ];
}

// 设置自动重定向（10分钟后）
header("Refresh: 600; url=index.html");
?>

<!DOCTYPE html>
<html lang="zh-MY">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>价格信息 - SRI MUAR 皇城驾驶学院</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 图标 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Microsoft YaHei', 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
            -webkit-user-select: none; /* Chrome, Safari, Opera */
            -moz-user-select: none; /* Firefox */
            -ms-user-select: none; /* IE/Edge */
            user-select: none; /* Standard syntax */
            min-height: 100vh;
        }
        
        .price-header {
            background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
            color: white;
            padding: 60px 0;
            margin-bottom: 40px;
        }
        
        .price-container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 40px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            position: relative;
            margin-bottom: 40px;
        }
        
        .timer-warning {
            background: linear-gradient(135deg, #ff6b00 0%, #e55c00 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
            font-weight: 600;
            -webkit-user-select: text; /* 允许选择警告文字 */
            user-select: text;
        }
        
        .timer {
            font-size: 24px;
            font-weight: bold;
            background: white;
            color: #ff6b00;
            padding: 5px 15px;
            border-radius: 50px;
            display: inline-block;
            margin: 0 10px;
            -webkit-user-select: text;
            user-select: text;
        }
        
        .nav-tabs {
            border-bottom: 3px solid #0056b3;
        }
        
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 600;
            border: none;
            padding: 12px 30px;
            -webkit-user-select: none;
            user-select: none;
        }
        
        .nav-tabs .nav-link.active {
            background: #0056b3;
            color: white;
            border-radius: 8px 8px 0 0;
        }
        
        .pdf-container {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin: 20px 0;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            position: relative;
        }
        
        .pdf-viewer {
            width: 100%;
            height: 800px;
            border: none;
            -webkit-user-select: none;
            user-select: none;
        }
        
        .pdf-title {
            background: #f8f9fa;
            padding: 15px;
            border-bottom: 1px solid #e0e0e0;
            font-weight: 600;
            color: #0056b3;
            -webkit-user-select: text;
            user-select: text;
        }
        
        .pdf-controls {
            background: #f8f9fa;
            padding: 15px;
            border-top: 1px solid #e0e0e0;
            -webkit-user-select: none;
            user-select: none;
        }
        
        .pdf-section {
            margin-bottom: 40px;
            position: relative;
        }
        
        .pdf-section:last-child {
            margin-bottom: 0;
        }
        
        .btn-pdf {
            background: linear-gradient(135deg, #0056b3 0%, #004494 100%);
            border: none;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            color: white;
        }
        
        .btn-pdf:hover {
            background: linear-gradient(135deg, #004494 0%, #003d82 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0, 86, 179, 0.3);
        }
        
        .price-highlight {
            background: #e8f4fd;
            border-left: 4px solid #0056b3;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            -webkit-user-select: text;
            user-select: text;
        }
        
        .btn-print {
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            border: none;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 600;
            color: white;
        }
        
        .btn-print:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(40, 167, 69, 0.3);
        }
        
        @media print {
            .no-print {
                display: none !important;
            }
            
            .price-container {
                box-shadow: none;
                padding: 0;
            }
            
            .timer-warning {
                display: none !important;
            }
            
            .pdf-container {
                border: none;
                box-shadow: none;
            }
            
            body {
                -webkit-user-select: text !important;
                user-select: text !important;
            }
        }
        
        @media (max-width: 768px) {
            .pdf-viewer {
                height: 500px;
            }
            
            .nav-tabs .nav-link {
                padding: 10px 15px;
                font-size: 0.9rem;
            }
        }
        
        /* 禁用右键菜单 */
        body {
            -webkit-touch-callout: none;
        }
        
        /* 防止图片拖拽 */
        img {
            -webkit-user-drag: none;
            -khtml-user-drag: none;
            -moz-user-drag: none;
            -o-user-drag: none;
            user-drag: none;
        }
        
        /* 安全警告样式 */
        .security-warning {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            z-index: 9999;
            display: none;
            justify-content: center;
            align-items: center;
            text-align: center;
        }
    </style>
</head>
<body oncontextmenu="return false">
    <!-- 安全警告（不再自动显示，只作为备用） -->
    <div id="securityWarning" class="security-warning">
        <div class="container">
            <div class="alert alert-warning" style="max-width: 600px; margin: 0 auto;">
                <h4><i class="fas fa-exclamation-triangle me-2"></i>安全提醒</h4>
                <p>检测到可能影响页面安全的行为。请确保您没有打开开发者工具。</p>
                <div class="mt-3">
                    <button onclick="hideSecurityWarning()" class="btn btn-primary">
                        <i class="fas fa-check me-2"></i>确认并继续
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- 头部 -->
    <div class="price-header text-center">
        <div class="container">
            <h1 class="display-5 fw-bold mb-3">
                <i class="fas fa-money-bill-wave me-2"></i>课程价格信息
            </h1>
            <p class="lead mb-0">SRI MUAR 皇城驾驶学院 - 官方价格表</p>
        </div>
    </div>
    
    <!-- 价格容器 -->
    <div class="container">
        <!-- 倒计时警告 -->
        <div class="timer-warning no-print">
            <i class="fas fa-clock me-2"></i>
            价格信息将在 <span class="timer" id="timer">10:00</span> 后自动隐藏
            <div class="small mt-1">为了保护价格信息的机密性，此页面将在10分钟后自动关闭</div>
            <div class="small mt-2">
                <i class="fas fa-shield-alt me-1"></i>
                安全模式已启用：右键菜单和复制功能已被限制
            </div>
        </div>
        
        <div class="price-container">
            <!-- 用户信息 -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <h5><i class="fas fa-user me-2"></i> 用户信息</h5>
                    <p class="mb-1">身份证：<?php echo htmlspecialchars($_SESSION['price_verification']['ic']); ?></p>
                    <p class="mb-1">姓名：<?php echo htmlspecialchars($_SESSION['price_verification']['name']); ?></p>
                    <p class="mb-0">邮箱：<?php echo htmlspecialchars($_SESSION['price_verification']['email']); ?></p>
                </div>
                <div class="col-md-6 text-md-end">
                    <p class="mb-1"><i class="fas fa-calendar-alt me-2"></i> 查看时间：<?php echo date('Y-m-d H:i:s'); ?></p>
                    <p class="mb-0"><i class="fas fa-info-circle me-2"></i> 价格有效期：2025年12月31日</p>
                </div>
            </div>
            
            <!-- 价格类型标签页 -->
            <ul class="nav nav-tabs no-print" id="priceTabs">
                <li class="nav-item">
                    <a class="nav-link <?php echo $type == 'all' ? 'active' : ''; ?>" 
                       href="?type=all">全部价格</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $type == 'car' ? 'active' : ''; ?>" 
                       href="?type=car">汽车价格</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo $type == 'motor' ? 'active' : ''; ?>" 
                       href="?type=motor">摩托车价格</a>
                </li>
            </ul>
            
            <!-- PDF显示区域 -->
            <div class="tab-content mt-4">
                <?php if ($type == 'all'): ?>
                    <!-- 显示所有PDF -->
                    <div id="all-price">
                        <!-- 摩托车PDF -->
                        <div class="pdf-section">
                            <h4 class="mb-3" style="color: #0056b3;">
                                <i class="fas fa-motorcycle me-2"></i>摩托车课程价格表
                            </h4>
                            
                            <div class="pdf-container">
                                <div class="pdf-title">
                                    <i class="fas fa-file-pdf me-2 text-danger"></i>
                                    Price-Motor.pdf
                                </div>
                                <iframe src="Price-Motor.pdf#toolbar=0" class="pdf-viewer" 
                                        title="摩托车价格表PDF"></iframe>
                                <div class="pdf-controls text-center">
                                    <button onclick="zoomIn('motor-pdf')" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-search-plus"></i> 放大
                                    </button>
                                    <button onclick="zoomOut('motor-pdf')" class="btn btn-outline-secondary btn-sm mx-2">
                                        <i class="fas fa-search-minus"></i> 缩小
                                    </button>
                                    <button onclick="resetZoom('motor-pdf')" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-sync-alt"></i> 重置
                                    </button>
                                </div>
                            </div>
                            
                            <div class="price-highlight mt-3">
                                <h6><i class="fas fa-info-circle me-2"></i>摩托车价格说明</h6>
                                <ul class="mb-0">
                                    <li>B2：适合初学者，可驾驶250cc以下摩托车</li>
                                    <li>B Full：可驾驶所有排量摩托车</li>
                                    <li>已有D执照者报考B2，L/P费用不同</li>
                                    <li>B Full升级：已有B2执照升级到B Full</li>
                                </ul>
                            </div>
                        </div>
                        
                        <hr class="my-5">
                        
                        <!-- 汽车PDF -->
                        <div class="pdf-section">
                            <h4 class="mb-3" style="color: #0056b3;">
                                <i class="fas fa-car me-2"></i>汽车课程价格表
                            </h4>
                            
                            <div class="pdf-container">
                                <div class="pdf-title">
                                    <i class="fas fa-file-pdf me-2 text-danger"></i>
                                    Price-Kereta.pdf
                                </div>
                                <iframe src="Price-Kereta.pdf#toolbar=0" class="pdf-viewer" 
                                        title="汽车价格表PDF"></iframe>
                                <div class="pdf-controls text-center">
                                    <button onclick="zoomIn('car-pdf')" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-search-plus"></i> 放大
                                    </button>
                                    <button onclick="zoomOut('car-pdf')" class="btn btn-outline-secondary btn-sm mx-2">
                                        <i class="fas fa-search-minus"></i> 缩小
                                    </button>
                                    <button onclick="resetZoom('car-pdf')" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-sync-alt"></i> 重置
                                    </button>
                                </div>
                            </div>
                            
                            <div class="price-highlight mt-3">
                                <h6><i class="fas fa-info-circle me-2"></i>汽车价格说明</h6>
                                <ul class="mb-0">
                                    <li>手动挡(D)：可驾驶所有类型汽车</li>
                                    <li>自动挡(DA)：只能驾驶自动挡汽车</li>
                                    <li>所有配套不包括电脑化交通规则考试费用</li>
                                    <li>付款后恕不退款，报名前请仔细确认</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                
                <?php elseif ($type == 'car' || $type == 'motor'): ?>
                    <!-- 显示单个PDF -->
                    <div id="single-price">
                        <h4 class="mb-4" style="color: #0056b3;">
                            <i class="fas <?php echo $type == 'car' ? 'fa-car' : 'fa-motorcycle'; ?> me-2"></i>
                            <?php echo $type == 'car' ? '汽车课程价格表' : '摩托车课程价格表'; ?>
                        </h4>
                        
                        <div class="pdf-container">
                            <div class="pdf-title">
                                <i class="fas fa-file-pdf me-2 text-danger"></i>
                                <?php echo $pdf_file; ?>
                            </div>
                            <iframe src="<?php echo $pdf_file; ?>#toolbar=0" class="pdf-viewer" 
                                    title="<?php echo $pdf_title; ?>"></iframe>
                            <div class="pdf-controls text-center">
                                <button onclick="zoomIn('single-pdf')" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-search-plus"></i> 放大
                                </button>
                                <button onclick="zoomOut('single-pdf')" class="btn btn-outline-secondary btn-sm mx-2">
                                    <i class="fas fa-search-minus"></i> 缩小
                                </button>
                                <button onclick="resetZoom('single-pdf')" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-sync-alt"></i> 重置
                                </button>
                            </div>
                        </div>
                        
                        <div class="price-highlight mt-4">
                            <h6><i class="fas fa-info-circle me-2"></i>
                                <?php echo $type == 'car' ? '汽车' : '摩托车'; ?>价格说明
                            </h6>
                            <ul class="mb-0">
                                <?php if ($type == 'car'): ?>
                                    <li>手动挡(D)：可驾驶所有类型汽车</li>
                                    <li>自动挡(DA)：只能驾驶自动挡汽车</li>
                                <?php else: ?>
                                    <li>B2：适合初学者，可驾驶250cc以下摩托车</li>
                                    <li>B Full：可驾驶所有排量摩托车</li>
                                    <li>已有D执照者报考B2，L/P费用不同</li>
                                    <li>B Full升级：已有B2执照升级到B Full</li>
                                <?php endif; ?>
                                <li>所有配套不包括电脑化交通规则考试费用</li>
                                <li>付款后恕不退款，报名前请仔细确认</li>
                            </ul>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- 重要提示 -->
            <div class="alert alert-warning mt-4">
                <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>重要提示</h5>
                <ul class="mb-0">
                    <li>以上价格仅供参考，实际价格可能因政策调整而变更</li>
                    <li>所有价格以马来西亚令吉(RM)为单位</li>
                </ul>
            </div>
            
            <!-- 操作按钮 -->
            <div class="row mt-4 no-print">
                <div class="col-md-12 text-center">
                    <a href="price_information.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo me-2"></i> 重新查看其他价格
                    </a>
                    <a href="index.html" class="btn btn-outline-primary ms-2">
                        <i class="fas fa-home me-2"></i> 返回首页
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 简化版本，移除过度敏感的防截图功能
        
        // 基本安全防护
        document.addEventListener('DOMContentLoaded', function() {
            // 1. 禁用右键菜单
            document.addEventListener('contextmenu', function(e) {
                e.preventDefault();
                showToastWarning('右键菜单已被禁用');
                return false;
            });
            
            // 2. 禁用键盘快捷键（适度）
            document.addEventListener('keydown', function(e) {
                // 防止Ctrl+P打印（允许用户使用浏览器打印按钮）
                // 注释掉这部分，因为会干扰正常使用
                /*
                if ((e.ctrlKey || e.metaKey) && e.key === 'p') {
                    e.preventDefault();
                    showToastWarning('请使用页面提供的打印功能');
                    return false;
                }
                */
                
                // 防止Ctrl+S保存
                if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                    e.preventDefault();
                    showToastWarning('保存功能已被禁用');
                    return false;
                }
            });
            
            // 3. 防止拖拽
            document.addEventListener('dragstart', function(e) {
                if (e.target.tagName === 'IMG' || e.target.tagName === 'A') {
                    e.preventDefault();
                    return false;
                }
            });
            
            // 4. 移除敏感的背景水印（可能引起闪烁）
            // 已经在前面的CSS中移除
            
            // 5. 确保页面稳定加载
            window.addEventListener('load', function() {
                // 确保所有内容已加载
                console.log('页面加载完成，安全模式已启用');
            });
        });
        
        // 显示警告消息（温和版本）
        function showToastWarning(message) {
            // 检查是否已存在警告
            if (document.querySelector('.toast-warning')) {
                return;
            }
            
            const toast = document.createElement('div');
            toast.className = 'toast-warning';
            toast.innerHTML = `
                <div style="
                    position: fixed;
                    bottom: 20px;
                    right: 20px;
                    background: rgba(255, 107, 0, 0.9);
                    color: white;
                    padding: 12px 20px;
                    border-radius: 8px;
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                    z-index: 10000;
                    max-width: 300px;
                    animation: slideUp 0.3s ease;
                    font-size: 14px;
                    display: flex;
                    align-items: center;
                ">
                    <i class="fas fa-info-circle me-2"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(toast);
            
            // 3秒后自动移除
            setTimeout(() => {
                toast.style.animation = 'slideDown 0.3s ease';
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }, 3000);
        }
        
        // 隐藏安全警告
        function hideSecurityWarning() {
            document.getElementById('securityWarning').style.display = 'none';
        }
        
        // PDF缩放功能
        let zoomLevels = {
            'motor-pdf': 1.0,
            'car-pdf': 1.0,
            'single-pdf': 1.0
        };
        
        function zoomIn(pdfId) {
            const iframes = document.querySelectorAll('.pdf-viewer');
            iframes.forEach(iframe => {
                zoomLevels[pdfId] = Math.min(zoomLevels[pdfId] + 0.1, 2.0);
                iframe.style.transform = `scale(${zoomLevels[pdfId]})`;
                iframe.style.transformOrigin = 'top left';
            });
        }
        
        function zoomOut(pdfId) {
            const iframes = document.querySelectorAll('.pdf-viewer');
            iframes.forEach(iframe => {
                zoomLevels[pdfId] = Math.max(zoomLevels[pdfId] - 0.1, 0.5);
                iframe.style.transform = `scale(${zoomLevels[pdfId]})`;
                iframe.style.transformOrigin = 'top left';
            });
        }
        
        function resetZoom(pdfId) {
            const iframes = document.querySelectorAll('.pdf-viewer');
            iframes.forEach(iframe => {
                zoomLevels[pdfId] = 1.0;
                iframe.style.transform = 'scale(1)';
            });
        }
        
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
                    display.textContent = "即将跳转...";
                    
                    // 10分钟后重定向到首页
                    setTimeout(function() {
                        window.location.href = 'index.html';
                    }, 1000);
                }
            }, 1000);
        }
        
        // 页面加载时启动计时器
        window.onload = function () {
            // 计算剩余时间（10分钟 = 600秒）
            const verifiedTime = <?php echo $_SESSION['price_verification']['verified_time']; ?>;
            const currentTime = Math.floor(Date.now() / 1000);
            const elapsed = currentTime - verifiedTime;
            const remaining = Math.max(0, 600 - elapsed);
            
            const display = document.querySelector('#timer');
            if (display) {
                startTimer(remaining, display);
            }
            
            // 添加CSS动画
            const style = document.createElement('style');
            style.textContent = `
                @keyframes slideUp {
                    from { transform: translateY(100%); opacity: 0; }
                    to { transform: translateY(0); opacity: 1; }
                }
                @keyframes slideDown {
                    from { transform: translateY(0); opacity: 1; }
                    to { transform: translateY(100%); opacity: 0; }
                }
            `;
            document.head.appendChild(style);
            
            // 初始化PDF iframe
            const iframes = document.querySelectorAll('.pdf-viewer');
            iframes.forEach(iframe => {
                iframe.onload = function() {
                    console.log('PDF加载完成');
                    // 添加加载完成后的处理
                };
            });
        };
        
        // 标签页切换平滑滚动
        document.querySelectorAll('.nav-link').forEach(function(link) {
            link.addEventListener('click', function(e) {
                if (!this.classList.contains('active')) {
                    window.scrollTo({
                        top: 0,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // 防止页面被嵌入到iframe中
        if (window.top !== window.self) {
            // 只是记录，不强制重定向，避免影响正常使用
            console.log('页面被嵌入到iframe中');
        }
    </script>
</body>
</html>