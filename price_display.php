<?php
// price_display.php - 价格显示页面
session_start();

// 开启错误报告（生产环境应关闭）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 设置时区
date_default_timezone_set('Asia/Kuala_Lumpur');

// 检查用户是否已验证
function checkVerification() {
    if (!isset($_SESSION['verification_data']) || 
        !isset($_SESSION['verification_data']['verified']) || 
        $_SESSION['verification_data']['verified'] !== true) {
        
        // 记录未授权访问尝试
        error_log('未授权访问 price_display.php - IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '未知'));
        
        return [
            'authorized' => false,
            'message' => '请先完成验证',
            'redirect' => 'price_information.php'
        ];
    }
    
    // 检查验证是否过期（30分钟）
    $verified_at = $_SESSION['verification_data']['verified_at'] ?? 0;
    $current_time = time();
    $session_lifetime = 1800; // 30分钟
    
    if (($current_time - $verified_at) > $session_lifetime) {
        // 清除过期会话
        unset($_SESSION['verification_data']);
        
        return [
            'authorized' => false,
            'message' => '会话已过期，请重新验证',
            'redirect' => 'price_information.php?error=session_expired'
        ];
    }
    
    return [
        'authorized' => true,
        'user_info' => $_SESSION['verification_data']
    ];
}

// 执行验证检查
$auth_check = checkVerification();
if (!$auth_check['authorized']) {
    header('Location: ' . $auth_check['redirect']);
    exit;
}

// 获取用户信息
$user_info = $auth_check['user_info'];

// 计算剩余时间（秒）
$verified_at = $user_info['verified_at'];
$session_end_time = $verified_at + 1800; // 30分钟后
$remaining_time = max(0, $session_end_time - time());

// 记录成功访问
error_log('用户 ' . $user_info['email'] . ' 成功访问价格页面，剩余时间: ' . $remaining_time . '秒');

// 为前端提供的数据
$js_user_data = [
    'email' => $user_info['email'],
    'full_name' => $user_info['full_name'],
    'verified_at' => date('Y-m-d H:i:s', $verified_at),
    'session_end_time' => date('Y-m-d H:i:s', $session_end_time),
    'remaining_time' => $remaining_time,
    'session_id' => substr(session_id(), 0, 10) . '...'
];

// 生成查询编号
$query_id = 'SRM-' . date('Ymd') . '-' . rand(1000, 9999);
?>
<!DOCTYPE html>
<html lang="zh-MY">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>价格查询 - SRI MUAR 皇城驾驶学院</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 图标 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- PDF.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>
    
    <!-- 设计样式 -->
    <style>
        :root {
            --primary-blue: #0056b3;
            --secondary-orange: #FF6B00;
            --light-gray: #f8f9fa;
            --dark-gray: #333333;
        }

        body {
            font-family: 'Microsoft YaHei', 'Segoe UI', sans-serif;
            color: var(--dark-gray);
            padding-top: 10px;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }

        /* 顶部导航栏 */
        .top-navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-radius: 10px;
            margin: 0 15px 30px 15px;
        }

        .logo-img {
            height: 160px;
            width: auto;
            object-fit: contain;
        }

        /* 欢迎信息 */
        .welcome-card {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
            padding: 40px 0;
            text-align: center;
            margin-bottom: 50px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        /* PDF 查看器 */
        .pdf-viewer-section {
            padding: 20px 0 60px;
            min-height: 500px;
        }

        .pdf-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            display: none;
        }

        .pdf-container.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .pdf-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
        }

        .pdf-viewer {
            width: 100%;
            height: 600px;
            border: 1px solid #ddd;
            border-radius: 8px;
            overflow: auto;
            background: #f9f9f9;
        }

        /* 倒计时显示 */
        .countdown-display {
            position: fixed;
            top: 20px;
            right: 20px;
            background: var(--primary-blue);
            color: white;
            padding: 12px 20px;
            border-radius: 25px;
            font-weight: bold;
            z-index: 1001;
            box-shadow: 0 3px 15px rgba(0,0,0,0.2);
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .countdown-warning {
            background: #dc3545;
            animation: pulse 1s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }

        /* 会话信息 */
        .session-info {
            background: #e8f4ff;
            border-radius: 10px;
            padding: 15px;
            margin: 20px 0;
            border-left: 4px solid #0056b3;
        }
        
        .session-info small {
            color: #666;
        }

        /* 价格选择按钮 */
        .price-buttons {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }
        
        .price-btn {
            padding: 15px 30px;
            border: none;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .price-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .price-btn.active {
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .price-btn.motor-btn {
            background: #28a745;
            color: white;
        }
        
        .price-btn.car-btn {
            background: #dc3545;
            color: white;
        }
        
        .price-controls {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 15px;
            margin-top: 20px;
        }
        
        .pdf-control-btn {
            background: #0056b3;
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .pdf-control-btn:hover {
            background: #004494;
            transform: scale(1.1);
        }
        
        .page-info {
            font-weight: 600;
            color: #555;
            min-width: 80px;
            text-align: center;
        }
    </style>
</head>
<body>
    <!-- 倒计时显示 -->
    <div id="countdownDisplay" class="countdown-display">
        <i class="fas fa-clock"></i>
        <span>剩余时间: <span id="countdownTimer">30:00</span></span>
    </div>

    <!-- 顶部导航栏 -->
    <nav class="top-navbar">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <div class="logo-container">
                        <a href="index.html" class="d-flex align-items-center text-decoration-none">
                            <img src="logo.PNG" alt="SRI MUAR Logo" class="logo-img">
                        </a>
                    </div>
                </div>
                
                <div class="col-md-9">
                    <div class="nav-menu-container">
                        <ul class="main-nav" style="display: flex; gap: 20px; list-style: none; padding: 0; margin: 0; flex-wrap: wrap;">
                            <li><a href="index.html" style="color: #0056b3; text-decoration: none;">首页</a></li>
                            <li><a href="courses.html" style="color: #0056b3; text-decoration: none;">课程</a></li>
                            <li><a href="products.html" style="color: #0056b3; text-decoration: none;">配套</a></li>
                            <li><a href="contact.html" style="color: #0056b3; text-decoration: none;">联系我们</a></li>
                            <li><a href="aboutus.html" style="color: #0056b3; text-decoration: none;">学院简介</a></li>
                            <li><a href="picture.html" style="color: #0056b3; text-decoration: none;">学院图集</a></li>
                            <li><a href="price_display.php" style="color: #0056b3; text-decoration: none; font-weight: bold;">价格查询</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- 欢迎信息 -->
    <div class="welcome-card">
        <div class="container">
            <h2><i class="fas fa-graduation-cap me-2"></i>欢迎查看课程价格</h2>
            <p class="mb-0">您好，<?php echo htmlspecialchars($user_info['full_name']); ?>！</p>
            <div class="session-info">
                <p class="mb-1"><i class="fas fa-user-check me-2"></i>已验证用户：<?php echo htmlspecialchars($user_info['email']); ?></p>
                <p class="mb-0"><i class="fas fa-id-card me-2"></i>查询编号：<?php echo $query_id; ?></p>
                <small><i class="fas fa-info-circle me-1"></i>您的价格查看权限将在30分钟后过期</small>
            </div>
        </div>
    </div>

    <!-- 价格选择按钮 -->
    <section class="pdf-viewer-section">
        <div class="container">
            <div class="price-buttons">
                <button class="price-btn motor-btn active" id="motorBtn">
                    <i class="fas fa-motorcycle"></i> 摩托车价格
                </button>
                <button class="price-btn car-btn" id="carBtn">
                    <i class="fas fa-car"></i> 汽车价格
                </button>
            </div>
            
            <!-- PDF查看器 -->
            <div class="pdf-container active" id="singlePdfViewer">
                <div class="pdf-header">
                    <h3 id="pdfTitle">摩托车价格表</h3>
                    <div class="price-controls">
                        <button class="pdf-control-btn" id="prevPage">
                            <i class="fas fa-chevron-left"></i>
                        </button>
                        <span class="page-info" id="pageInfo">页面: 1/1</span>
                        <button class="pdf-control-btn" id="nextPage">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                </div>
                <div class="pdf-viewer" id="singlePdfViewerCanvas">
                    <!-- PDF将在这里渲染 -->
                </div>
                <div class="text-center mt-3">
                    <a href="price_information.php" class="btn btn-outline-primary me-2">
                        <i class="fas fa-redo me-1"></i>重新查询
                    </a>
                    <a href="logout.php" class="btn btn-outline-danger">
                        <i class="fas fa-sign-out-alt me-1"></i>安全退出
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 用户数据（从PHP传递）
        const userData = <?php echo json_encode($js_user_data, JSON_UNESCAPED_UNICODE); ?>;
        const remainingTime = <?php echo $remaining_time; ?>;
        
        // PDF文件路径
        const pdfFiles = {
            motor: 'Price-Motor.pdf',
            car: 'Price-Kereta.pdf'
        };
        
        // 全局变量
        let currentPdf = null;
        let currentPage = 1;
        let totalPages = 1;
        let countdownInterval;
        
        // DOM元素
        const countdownDisplay = document.getElementById('countdownDisplay');
        const countdownTimer = document.getElementById('countdownTimer');
        const motorBtn = document.getElementById('motorBtn');
        const carBtn = document.getElementById('carBtn');
        const pdfTitle = document.getElementById('pdfTitle');
        const pageInfo = document.getElementById('pageInfo');
        const prevPageBtn = document.getElementById('prevPage');
        const nextPageBtn = document.getElementById('nextPage');
        const pdfCanvas = document.getElementById('singlePdfViewerCanvas');
        
        // 更新按钮状态
        function updateButtonStates(activeBtn) {
            [motorBtn, carBtn].forEach(btn => {
                btn.classList.remove('active');
                btn.style.opacity = '0.8';
            });
            activeBtn.classList.add('active');
            activeBtn.style.opacity = '1';
        }
        
        // 开始倒计时
        function startCountdown() {
            let timeLeft = remainingTime;
            
            function updateCountdown() {
                if (timeLeft <= 0) {
                    clearInterval(countdownInterval);
                    countdownDisplay.classList.add('countdown-warning');
                    countdownTimer.textContent = '00:00';
                    
                    // 显示过期提示
                    setTimeout(() => {
                        alert('您的会话已过期，请重新验证');
                        window.location.href = 'price_information.php';
                    }, 1000);
                    
                    return;
                }
                
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                
                countdownTimer.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                // 最后5分钟警告
                if (timeLeft < 300) {
                    countdownDisplay.classList.add('countdown-warning');
                }
                
                timeLeft--;
            }
            
            updateCountdown();
            countdownInterval = setInterval(updateCountdown, 1000);
        }
        
        // 加载PDF
        async function loadPdf(pdfUrl) {
            try {
                // 设置PDF.js工作路径
                if (typeof pdfjsLib !== 'undefined') {
                    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.worker.min.js';
                }
                
                // 添加时间戳防止缓存
                const timestamp = new Date().getTime();
                const urlWithTimestamp = `${pdfUrl}?t=${timestamp}`;
                
                console.log('正在加载PDF:', urlWithTimestamp);
                
                const loadingTask = pdfjsLib.getDocument(urlWithTimestamp);
                const pdf = await loadingTask.promise;
                
                return {
                    pdf: pdf,
                    totalPages: pdf.numPages
                };
            } catch (error) {
                console.error('加载PDF失败:', error);
                pdfCanvas.innerHTML = `
                    <div style="text-align: center; padding: 50px;">
                        <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3"></i>
                        <h4>无法加载PDF文件</h4>
                        <p>文件路径: ${pdfUrl}</p>
                        <p>错误信息: ${error.message}</p>
                        <button onclick="showPdf('motor')" class="btn btn-primary mt-3">
                            <i class="fas fa-redo me-1"></i>重试加载
                        </button>
                    </div>
                `;
                return null;
            }
        }
        
        // 渲染PDF页面
        async function renderPdfPage(pdf, pageNumber) {
            try {
                const page = await pdf.getPage(pageNumber);
                const scale = 1.5;
                const viewport = page.getViewport({ scale: scale });
                
                // 创建Canvas
                const canvas = document.createElement('canvas');
                const context = canvas.getContext('2d');
                canvas.height = viewport.height;
                canvas.width = viewport.width;
                canvas.style.width = '100%';
                canvas.style.height = 'auto';
                canvas.className = 'pdf-page';
                
                // 渲染PDF页面到Canvas
                const renderContext = {
                    canvasContext: context,
                    viewport: viewport
                };
                
                await page.render(renderContext).promise;
                
                // 清除容器并添加Canvas
                pdfCanvas.innerHTML = '';
                pdfCanvas.appendChild(canvas);
                
            } catch (error) {
                console.error('渲染PDF页面失败:', error);
                pdfCanvas.innerHTML = `
                    <div style="text-align: center; padding: 50px;">
                        <i class="fas fa-exclamation-triangle fa-3x text-danger mb-3"></i>
                        <h4>渲染页面失败</h4>
                        <p>${error.message}</p>
                    </div>
                `;
            }
        }
        
        // 显示PDF
        async function showPdf(pdfType) {
            // 更新标题
            pdfTitle.textContent = pdfType === 'motor' ? '摩托车价格表' : '汽车价格表';
            
            // 显示加载状态
            pdfCanvas.innerHTML = `
                <div style="text-align: center; padding: 100px;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">正在加载...</span>
                    </div>
                    <p class="mt-3">正在加载PDF文件...</p>
                </div>
            `;
            
            // 加载PDF
            const result = await loadPdf(pdfFiles[pdfType]);
            if (!result) return;
            
            currentPdf = result.pdf;
            totalPages = result.totalPages;
            currentPage = 1;
            
            // 渲染第一页
            await renderPdfPage(currentPdf, currentPage);
            
            // 更新页面信息
            pageInfo.textContent = `页面: ${currentPage}/${totalPages}`;
            
            // 更新按钮状态
            updateButtonStates(pdfType === 'motor' ? motorBtn : carBtn);
            
            // 更新页面信息
            updatePageInfo();
        }
        
        // 更新页面信息
        function updatePageInfo() {
            pageInfo.textContent = `页面: ${currentPage}/${totalPages}`;
        }
        
        // 设置翻页事件
        function setupPdfControls() {
            prevPageBtn.onclick = async () => {
                if (currentPage > 1 && currentPdf) {
                    currentPage--;
                    await renderPdfPage(currentPdf, currentPage);
                    updatePageInfo();
                }
            };
            
            nextPageBtn.onclick = async () => {
                if (currentPage < totalPages && currentPdf) {
                    currentPage++;
                    await renderPdfPage(currentPdf, currentPage);
                    updatePageInfo();
                }
            };
        }
        
        // 键盘控制
        function setupKeyboardControls() {
            document.addEventListener('keydown', (e) => {
                if (e.key === 'ArrowLeft') {
                    prevPageBtn.click();
                } else if (e.key === 'ArrowRight') {
                    nextPageBtn.click();
                }
            });
        }
        
        // 检查PDF文件是否存在
        async function checkPdfFiles() {
            try {
                const motorResponse = await fetch(pdfFiles.motor, { method: 'HEAD' });
                const carResponse = await fetch(pdfFiles.car, { method: 'HEAD' });
                
                if (!motorResponse.ok) {
                    console.warn('摩托车PDF文件不存在:', pdfFiles.motor);
                }
                if (!carResponse.ok) {
                    console.warn('汽车PDF文件不存在:', pdfFiles.car);
                }
            } catch (error) {
                console.error('检查PDF文件失败:', error);
            }
        }
        
        // 页面加载完成
        document.addEventListener('DOMContentLoaded', function() {
            console.log('用户数据:', userData);
            console.log('PDF文件:', pdfFiles);
            
            // 开始倒计时
            startCountdown();
            
            // 设置PDF控制
            setupPdfControls();
            setupKeyboardControls();
            
            // 检查PDF文件
            checkPdfFiles();
            
            // 按钮事件监听
            motorBtn.addEventListener('click', () => showPdf('motor'));
            carBtn.addEventListener('click', () => showPdf('car'));
            
            // 默认显示摩托车价格
            showPdf('motor');
            
            // 30分钟后自动登出
            setTimeout(() => {
                window.location.href = 'logout.php';
            }, 30 * 60 * 1000);
        });
        
        // 页面关闭或刷新时提示
        window.addEventListener('beforeunload', function(e) {
            if (remainingTime > 60) { // 剩余时间大于1分钟才提示
                e.preventDefault();
                e.returnValue = '您确定要离开吗？您的会话将在30分钟后过期。';
            }
        });
    </script>
</body>
</html>