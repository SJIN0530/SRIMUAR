<?php
// price_display.php - 修复版：刷新不创建新记录，退出重进才创建

session_start();

// ==== 数据库配置和函数 ====
class Database {
    private static $connection = null;
    
    private static $host = '127.0.0.1';
    private static $dbname = 'sri_muar';
    private static $username = 'root';
    private static $password = '';
    
    public static function getConnection() {
        if (self::$connection === null) {
            try {
                self::$connection = new PDO(
                    "mysql:host=" . self::$host . ";dbname=" . self::$dbname . ";charset=utf8mb4",
                    self::$username,
                    self::$password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
            } catch (PDOException $e) {
                die("数据库连接失败: " . $e->getMessage());
            }
        }
        return self::$connection;
    }
    
    public static function insertLog($ic, $name, $email, $page_type) {
        try {
            $conn = self::getConnection();
            $access_time = date('Y-m-d H:i:s');
            
            $sql = "INSERT INTO price_access_logs 
                    (ic_number, name, email, page_type, access_time, duration_seconds) 
                    VALUES (?, ?, ?, ?, ?, 0)";
            
            $stmt = $conn->prepare($sql);
            $stmt->execute([$ic, $name, $email, $page_type, $access_time]);
            
            return [
                'success' => true,
                'id' => $conn->lastInsertId(),
                'message' => '记录保存成功'
            ];
        } catch (PDOException $e) {
            error_log("数据库插入失败: " . $e->getMessage());
            return [
                'success' => false,
                'message' => '记录保存失败'
            ];
        }
    }
    
    public static function updateDuration($log_id, $duration_seconds) {
        try {
            $conn = self::getConnection();
            
            $sql = "UPDATE price_access_logs SET duration_seconds = ? WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->execute([$duration_seconds, $log_id]);
            
            return ['success' => true];
        } catch (PDOException $e) {
            error_log("更新停留时间失败: " . $e->getMessage());
            return ['success' => false];
        }
    }
}

// ==== 处理API请求 ====
if (isset($_GET['action'])) {
    $action = $_GET['action'];
    
    if ($action === 'update_duration' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        // 处理更新停留时间
        $log_id = $_POST['log_id'] ?? 0;
        $duration_seconds = $_POST['duration_seconds'] ?? 0;
        
        if ($log_id > 0 && is_numeric($duration_seconds) && $duration_seconds > 0) {
            $result = Database::updateDuration($log_id, $duration_seconds);
            header('Content-Type: application/json');
            echo json_encode($result);
        } else {
            header('Content-Type: application/json');
            echo json_encode(['success' => false]);
        }
        exit();
    }
    
    if ($action === 'end_session') {
        // 处理结束会话 - 清除所有会话标记
        unset($_SESSION['current_log_id']);
        unset($_SESSION['current_session_start']);
        unset($_SESSION['session_visit_token']);
        unset($_SESSION['session_verified']);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => '会话已结束']);
        exit();
    }
}

// ==== 主页面逻辑 ====

// 设置马来西亚时区
date_default_timezone_set('Asia/Kuala_Lumpur');

// 检查是否已验证且未过期
if (!isset($_SESSION['price_verification']) || 
    !$_SESSION['price_verification']['verified'] ||
    (time() - $_SESSION['price_verification']['verified_time']) > 600) {
    header('Location: price_information.php');
    exit();
}

// 检查价格类型
$type = $_SESSION['price_verification']['vehicle_type'];
$valid_types = ['car', 'motor'];
if (!in_array($type, $valid_types)) {
    $type = 'car';
}

// ==== 核心逻辑：检测是否是新访问 ====
$is_new_access = false;
$should_create_record = false;

// 获取用户信息
$ic = $_SESSION['price_verification']['ic'] ?? 'Unknown';
$name = $_SESSION['price_verification']['name'] ?? 'Unknown';
$email = $_SESSION['price_verification']['email'] ?? 'Unknown';

// 生成当前访问的唯一令牌（基于IC+验证时间）
$verification_token = $ic . '_' . ($_SESSION['price_verification']['verified_time'] ?? time());

// 检查是否有有效的访问令牌
if (!isset($_SESSION['session_visit_token'])) {
    // 没有访问令牌，这是全新访问
    $is_new_access = true;
    $should_create_record = true;
    $_SESSION['session_visit_token'] = $verification_token;
    $_SESSION['session_verified'] = true;
} elseif ($_SESSION['session_visit_token'] !== $verification_token) {
    // 访问令牌不匹配，可能是不同验证或重新验证
    $is_new_access = true;
    $should_create_record = true;
    $_SESSION['session_visit_token'] = $verification_token;
    $_SESSION['session_verified'] = true;
} else {
    // 相同的访问令牌，检查是否是刷新
    $is_new_access = false;
    $should_create_record = false;
}

// ==== 计算剩余时间 ====
$total_session_time = 600; // 10分钟 = 600秒

// 确保有访问开始时间
if (!isset($_SESSION['current_session_start'])) {
    $_SESSION['current_session_start'] = time();
}

$session_start_time = $_SESSION['current_session_start'];
$current_time = time();
$elapsed_time = $current_time - $session_start_time;
$remaining_time = $total_session_time - $elapsed_time;

// 如果时间已用完，重定向到首页
if ($remaining_time <= 0) {
    // 清除当前访问的session数据
    unset($_SESSION['current_log_id']);
    unset($_SESSION['current_session_start']);
    unset($_SESSION['session_visit_token']);
    header('Location: index.html');
    exit();
}

// 根据类型设置PDF文件
if ($type == 'car') {
    $pdf_file = 'Price-Kereta.pdf';
    $pdf_title = '汽车课程价格表';
    $page_title = '汽车价格';
    $vehicle_icon = 'fas fa-car';
    $vehicle_name = '汽车';
} else {
    $pdf_file = 'Price-Motor.pdf';
    $pdf_title = '摩托车课程价格表';
    $page_title = '摩托车价格';
    $vehicle_icon = 'fas fa-motorcycle';
    $vehicle_name = '摩托车';
}
?>

<!DOCTYPE html>
<html lang="zh-MY">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SRI MUAR 皇城驾驶学院</title>
    
    <!-- 防止浏览器缓存 -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Microsoft YaHei', 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
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
            padding: 30px;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 40px;
        }
        
        .timer-warning {
            background: linear-gradient(135deg, #ff6b00 0%, #e55c00 100%);
            color: white;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 600;
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
        }
        
        .session-info {
            background: #e9ecef;
            padding: 8px 15px;
            border-radius: 8px;
            margin-bottom: 10px;
            font-size: 14px;
            color: #6c757d;
            text-align: center;
        }
        
        .pdf-container {
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            overflow: hidden;
            margin: 20px 0;
        }
        
        .pdf-viewer {
            width: 100%;
            height: 700px;
            border: none;
        }
        
        .db-status {
            background: #d4edda;
            color: #155724;
            padding: 10px 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: 500;
        }
        
        .db-error {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <!-- 头部 -->
    <div class="price-header text-center">
        <div class="container">
            <h1 class="display-5 fw-bold mb-3">
                <i class="<?php echo $vehicle_icon; ?> me-2"></i><?php echo $page_title; ?>
            </h1>
            <p class="lead mb-0">SRI MUAR 皇城驾驶学院 - 官方价格表</p>
        </div>
    </div>
    
    <!-- 主内容 -->
    <div class="container">
        <!-- 数据库状态 -->
        <?php if (!empty($db_message)): ?>
            <div class="db-status <?php echo strpos($db_message, '✅') !== false ? '' : 'db-error'; ?>">
                <i class="fas fa-database me-2"></i>
                <?php echo htmlspecialchars($db_message); ?>
                <?php if ($is_new_access): ?>
                    <br><small>访问开始时间: <?php echo date('H:i:s', $session_start_time); ?></small>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <!-- 倒计时警告 -->
        <div class="timer-warning" id="timerWarning">
            <i class="fas fa-clock me-2"></i>
            价格信息将在 <span class="timer" id="timer">
                <?php 
                    $minutes = floor($remaining_time / 60);
                    $seconds = $remaining_time % 60;
                    echo sprintf('%02d:%02d', $minutes, $seconds);
                ?>
            </span> 后自动隐藏
            <div class="small mt-1">
                为了保护价格信息的机密性，此页面将在10分钟后自动关闭
            </div>
        </div>
        
        <div class="price-container" id="mainContent">
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
                    <p class="mb-0"><i class="<?php echo $vehicle_icon; ?> me-2"></i> 查看类型：<?php echo $page_title; ?></p>
                </div>
            </div>
            
            <!-- PDF显示区域 -->
            <h4 class="mb-3" style="color: #0056b3;">
                <i class="<?php echo $vehicle_icon; ?> me-2"></i>
                <?php echo $pdf_title; ?>
            </h4>
            
            <div class="pdf-container">
                <iframe src="<?php echo $pdf_file; ?>#toolbar=0" class="pdf-viewer" 
                        title="<?php echo $pdf_title; ?>"></iframe>
            </div>
            
            <!-- 操作按钮 -->
            <div class="row mt-4">
                <div class="col-md-12 text-center">
                    <a href="price_information.php" class="btn btn-outline-secondary me-2" onclick="endSessionAndRedirect()">
                        <i class="fas fa-redo me-2"></i> 查看其他价格
                    </a>
                    <a href="index.html" class="btn btn-outline-primary me-2" onclick="endSessionAndRedirect()">
                        <i class="fas fa-home me-2"></i> 返回首页
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 结束会话并重定向
        function endSessionAndRedirect() {
            const logId = '<?php echo isset($_SESSION["current_log_id"]) ? $_SESSION["current_log_id"] : 0; ?>';
            const duration = Math.floor((Date.now() - window.pageLoadTime) / 1000);
            
            // 更新停留时间
            if (logId > 0 && duration > 0) {
                updateDuration(logId, duration);
            }
            
            // 结束当前会话
            endCurrentSession();
            
            return true;
        }
        
        // 结束当前会话
        function endCurrentSession() {
            fetch('price_display.php?action=end_session')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        console.log('当前会话已结束');
                    }
                })
                .catch(error => console.error('结束会话失败:', error));
        }
        
        // 更新停留时间
        function updateDuration(logId, duration) {
            const formData = new FormData();
            formData.append('log_id', logId);
            formData.append('duration_seconds', duration);
            
            if (navigator.sendBeacon) {
                navigator.sendBeacon('price_display.php?action=update_duration', formData);
            } else {
                fetch('price_display.php?action=update_duration', {
                    method: 'POST',
                    body: formData,
                    keepalive: true
                });
            }
        }
        
        // 倒计时功能
        function startTimer(initialSeconds, display) {
            let timer = initialSeconds, minutes, seconds;
            
            const interval = setInterval(function () {
                minutes = parseInt(timer / 60, 10);
                seconds = parseInt(timer % 60, 10);
                
                minutes = minutes < 10 ? "0" + minutes : minutes;
                seconds = seconds < 10 ? "0" + seconds : seconds;
                
                display.textContent = minutes + ":" + seconds;
                
                if (--timer < 0) {
                    clearInterval(interval);
                    display.textContent = "即将跳转...";
                    display.style.color = "#dc3545";
                    
                    endCurrentSession();
                    
                    setTimeout(function() {
                        window.location.href = 'index.html';
                    }, 3000);
                }
            }, 1000);
            
            return interval;
        }
        
        // 页面加载时启动
        let timerInterval;
        window.onload = function () {
            const display = document.querySelector('#timer');
            
            if (display) {
                const timeText = display.textContent.trim();
                const parts = timeText.split(':');
                const minutes = parseInt(parts[0]);
                const seconds = parseInt(parts[1]);
                const totalSeconds = minutes * 60 + seconds;
                
                timerInterval = startTimer(totalSeconds, display);
            }
            
            // 记录页面加载时间
            window.pageLoadTime = Date.now();
            const logId = '<?php echo isset($_SESSION["current_log_id"]) ? $_SESSION["current_log_id"] : 0; ?>';
            
            // 页面关闭时更新停留时间
            window.addEventListener('beforeunload', function() {
                const duration = Math.floor((Date.now() - window.pageLoadTime) / 1000);
                
                if (logId > 0 && duration > 0) {
                    updateDuration(logId, duration);
                }
                
                if (timerInterval) {
                    clearInterval(timerInterval);
                }
            });
        };
        
        // 防止右键菜单
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });
    </script>
</body>
</html>