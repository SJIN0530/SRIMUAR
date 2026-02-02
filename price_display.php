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
$db_message = ''; // 添加变量用于显示数据库状态
$current_log_id = 0; // 用于JavaScript的日志ID

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
    $_SESSION['session_started'] = time(); // 记录会话开始时间
} elseif ($_SESSION['session_visit_token'] !== $verification_token) {
    // 访问令牌不匹配，可能是不同验证或重新验证
    $is_new_access = true;
    $should_create_record = true;
    $_SESSION['session_visit_token'] = $verification_token;
    $_SESSION['session_verified'] = true;
    $_SESSION['session_started'] = time(); // 记录会话开始时间
} else {
    // 相同的访问令牌，检查是否是刷新
    $is_new_access = false;
    $should_create_record = false;
}

// ==== 实际执行数据库插入 ====
if ($should_create_record) {
    $result = Database::insertLog($ic, $name, $email, $type);
    
    if ($result['success']) {
        // 保存日志ID到session
        $_SESSION['current_log_id'] = $result['id'];
        $current_log_id = $result['id'];
        $_SESSION['current_session_start'] = time(); // 记录页面访问开始时间
    } else {
        $db_message = '❌ 记录保存失败: ' . $result['message'];
        $current_log_id = isset($_SESSION['current_log_id']) ? $_SESSION['current_log_id'] : 0;
    }
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
    // 如果有当前日志ID，先更新停留时间
    if (isset($_SESSION['current_log_id']) && $current_log_id > 0) {
        // 计算总停留时间（会话开始到现在）
        $total_duration = time() - $_SESSION['session_started'];
        if ($total_duration > 0) {
            Database::updateDuration($current_log_id, $total_duration);
        }
    }
    
    // 清除当前访问的session数据
    unset($_SESSION['current_log_id']);
    unset($_SESSION['current_session_start']);
    unset($_SESSION['session_visit_token']);
    unset($_SESSION['session_started']);
    
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
        
        .db-info {
            background: #cce5ff;
            color: #004085;
        }
        
        .db-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .session-status {
            background: #e7f3fe;
            border-left: 4px solid #0056b3;
            padding: 10px;
            margin-bottom: 15px;
            border-radius: 5px;
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
            <?php 
                $db_status_class = '';
                if (strpos($db_message, '❌') !== false) {
                    $db_status_class = 'db-error';
                } elseif (strpos($db_message, '🔄') !== false) {
                    $db_status_class = 'db-info';
                } elseif (strpos($db_message, '⚠️') !== false) {
                    $db_status_class = 'db-warning';
                }
            ?>
            <div class="db-status <?php echo $db_status_class; ?>">
                <i class="fas fa-database me-2"></i>
                <?php echo htmlspecialchars($db_message); ?>
                <?php if ($is_new_access && isset($_SESSION['session_started'])): ?>
                    <br><small>会话开始时间: <?php echo date('H:i:s', $_SESSION['session_started']); ?></small>
                <?php elseif (isset($_SESSION['session_started'])): ?>
                    <br><small>会话已持续: <?php echo floor((time() - $_SESSION['session_started'])/60); ?>分<?php echo (time() - $_SESSION['session_started'])%60; ?>秒</small>
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
                    <a href="price_information.php" class="btn btn-outline-secondary me-2" onclick="return endSessionAndRedirect()">
                        <i class="fas fa-redo me-2"></i> 查看其他价格
                    </a>
                    <a href="index.html" class="btn btn-outline-primary me-2" onclick="return endSessionAndRedirect()">
                        <i class="fas fa-home me-2"></i> 返回首页
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 当前会话的日志ID
        const currentLogId = <?php echo $current_log_id; ?>;
        
        // 结束会话并重定向
        function endSessionAndRedirect() {
            const duration = Math.floor((Date.now() - window.pageLoadTime) / 1000);
            
            // 更新停留时间（如果当前页面有停留）
            if (currentLogId > 0 && duration > 0) {
                updateDuration(currentLogId, duration);
            }
            
            // 结束当前会话
            endCurrentSession();
            
            // 允许默认的链接行为
            return true;
        }
        
        // 刷新页面
        function refreshPage() {
            // 更新当前页面的停留时间
            if (currentLogId > 0) {
                const duration = Math.floor((Date.now() - window.pageLoadTime) / 1000);
                if (duration > 0) {
                    updateDuration(currentLogId, duration);
                }
            }
            
            // 刷新页面
            window.location.reload();
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
            
            // 使用sendBeacon或fetch发送数据
            if (navigator.sendBeacon) {
                navigator.sendBeacon('price_display.php?action=update_duration', formData);
                console.log('停留时间已更新 (Beacon):', duration, '秒');
            } else {
                fetch('price_display.php?action=update_duration', {
                    method: 'POST',
                    body: formData,
                    keepalive: true
                }).then(response => {
                    console.log('停留时间已更新 (Fetch):', duration, '秒');
                }).catch(error => {
                    console.error('更新停留时间失败:', error);
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
                
                // 警告颜色变化
                if (timer < 60) {
                    // 少于1分钟：红色警告
                    display.style.color = "#dc3545";
                    display.style.backgroundColor = "#f8d7da";
                    document.getElementById('timerWarning').style.background = "linear-gradient(135deg, #dc3545 0%, #c82333 100%)";
                } else if (timer < 180) {
                    // 少于3分钟：橙色警告
                    display.style.color = "#ff6b00";
                    display.style.backgroundColor = "#fff3cd";
                    document.getElementById('timerWarning').style.background = "linear-gradient(135deg, #ffc107 0%, #e0a800 100%)";
                }
                
                if (--timer < 0) {
                    clearInterval(interval);
                    display.textContent = "即将跳转...";
                    display.style.color = "#dc3545";
                    display.style.backgroundColor = "#f8d7da";
                    
                    // 更新总停留时间（从会话开始到现在）
                    if (currentLogId > 0) {
                        const totalDuration = Math.floor((Date.now() - window.sessionStartTime) / 1000);
                        if (totalDuration > 0) {
                            updateDuration(currentLogId, totalDuration);
                        }
                    }
                    
                    // 结束会话
                    endCurrentSession();
                    
                    // 3秒后跳转
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
            // 记录会话开始时间（从PHP传递）
            window.sessionStartTime = <?php echo $_SESSION['session_started'] ?? time(); ?> * 1000;
            
            console.log('页面加载完成，日志ID:', currentLogId);
            console.log('会话开始时间:', new Date(window.sessionStartTime).toLocaleTimeString());
            
            // 页面关闭时更新停留时间
            window.addEventListener('beforeunload', function(event) {
                const duration = Math.floor((Date.now() - window.pageLoadTime) / 1000);
                
                if (currentLogId > 0 && duration > 0) {
                    updateDuration(currentLogId, duration);
                }
                
                if (timerInterval) {
                    clearInterval(timerInterval);
                }
                
                // 对于某些浏览器，需要返回一个值以显示离开确认
                if (duration < 5) { // 如果停留时间很短
                    // 可选：显示确认对话框
                    // event.preventDefault();
                    // event.returnValue = '您刚刚访问这个页面，确定要离开吗？';
                }
            });
            
            // 页面隐藏时更新停留时间（切换标签页或最小化）
            document.addEventListener('visibilitychange', function() {
                if (document.hidden) {
                    // 页面被隐藏，记录离开时间
                    window.pageHiddenTime = Date.now();
                } else if (window.pageHiddenTime) {
                    // 页面恢复显示，计算隐藏期间的时间
                    const hiddenDuration = Math.floor((Date.now() - window.pageHiddenTime) / 1000);
                    console.log('页面被隐藏了', hiddenDuration, '秒');
                    // 可以根据需要调整计时器
                }
            });
        };
        
        // 防止右键菜单
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            alert('为了保护价格信息安全，右键菜单已被禁用。');
            return false;
        });
        
        // 防止键盘快捷键（Ctrl+C, Ctrl+U等）
        document.addEventListener('keydown', function(e) {
            // 禁用 Ctrl+S（保存页面）
            if (e.ctrlKey && e.key === 's') {
                e.preventDefault();
                alert('为了保护价格信息安全，保存功能已被禁用。');
                return false;
            }
            
            // 禁用 Ctrl+U（查看源代码）
            if (e.ctrlKey && e.key === 'u') {
                e.preventDefault();
                alert('为了保护价格信息安全，查看源代码功能已被禁用。');
                return false;
            }
            
            // 禁用 F12（开发者工具）
            if (e.key === 'F12') {
                e.preventDefault();
                alert('为了保护价格信息安全，开发者工具已被禁用。');
                return false;
            }
        });
    </script>
</body>
</html>