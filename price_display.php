<?php
// 启用错误报告以便调试
error_reporting(E_ALL);
ini_set('display_errors', 1);

session_start();

// 设置马来西亚时区
date_default_timezone_set('Asia/Kuala_Lumpur');

// 检查是否已验证且未过期
if (!isset($_SESSION['price_verification']) || 
    !$_SESSION['price_verification']['verified'] ||
    (time() - $_SESSION['price_verification']['verified_time']) > 600) {
    header('Location: price_information.php');
    exit();
}

// 检查价格类型，使用session中的车辆类型
$type = $_SESSION['price_verification']['vehicle_type']; // car 或 motor

// 验证类型是否有效
$valid_types = ['car', 'motor'];
if (!in_array($type, $valid_types)) {
    $type = 'car'; // 默认显示汽车
}

// ==== 记录访问开始时间 - 只记录一次 ====
if (!isset($_SESSION['price_view_start_time'])) {
    $_SESSION['price_view_start_time'] = time();
    $_SESSION['price_view_page_type'] = $type;
    
    // 获取用户信息
    $ic = $_SESSION['price_verification']['ic'] ?? 'Unknown';
    $name = $_SESSION['price_verification']['name'] ?? 'Unknown';
    $email = $_SESSION['price_verification']['email'] ?? 'Unknown';
    $page_type = $type;
    $access_time = date('Y-m-d H:i:s'); // 使用马来西亚时间
    
    // ==== 显示调试信息 ====
    echo "<!-- ======= 调试信息开始 ======= -->\n";
    echo "<!-- 用户信息: IC=$ic, Name=$name, Email=$email -->\n";
    echo "<!-- 访问信息: Type=$page_type, Time=$access_time -->\n";
    
    // ==== 保存到数据库 ====
    try {
        // 数据库连接参数
        $host = '127.0.0.1';
        $dbname = 'sri_muar';
        $username = 'root';
        $password = '';
        
        echo "<!-- 尝试连接到数据库: host=$host, db=$dbname, user=$username -->\n";
        
        // 连接到MySQL服务器
        $db = new PDO("mysql:host=$host", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<!-- MySQL服务器连接成功 -->\n";
        
        // 检查数据库是否存在
        $stmt = $db->query("SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = '$dbname'");
        if ($stmt->rowCount() == 0) {
            echo "<!-- 数据库不存在，正在创建... -->\n";
            $db->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
            echo "<!-- 数据库 '$dbname' 创建成功 -->\n";
        }
        
        // 连接到具体数据库
        $db = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<!-- 数据库 '$dbname' 连接成功 -->\n";
        
        // 检查表是否存在
        $stmt = $db->query("SHOW TABLES LIKE 'price_access_logs'");
        if ($stmt->rowCount() == 0) {
            echo "<!-- 表不存在，正在创建... -->\n";
            
            // 创建简化的表结构（只包含必需字段）
            $createTableSQL = "CREATE TABLE IF NOT EXISTS `price_access_logs` (
                `id` INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
                `ic_number` VARCHAR(20) NOT NULL,
                `name` VARCHAR(100) NOT NULL,
                `email` VARCHAR(100),
                `page_type` VARCHAR(10) NOT NULL,
                `access_time` DATETIME NOT NULL,
                `duration_seconds` INT(11) DEFAULT 0,
                INDEX `idx_ic` (`ic_number`),
                INDEX `idx_time` (`access_time`),
                INDEX `idx_page_type` (`page_type`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
            
            $db->exec($createTableSQL);
            echo "<!-- 表 'price_access_logs' 创建成功 -->\n";
        }
        
        // 检查表结构
        $stmt = $db->query("DESCRIBE price_access_logs");
        $tableColumns = [];
        while ($row = $stmt->fetch()) {
            $tableColumns[] = $row['Field'];
        }
        echo "<!-- 表结构: " . implode(', ', $tableColumns) . " -->\n";
        
        // 准备插入语句 - 简化的字段
        $sql = "INSERT INTO price_access_logs 
                (ic_number, name, email, page_type, access_time, duration_seconds) 
                VALUES (:ic_number, :name, :email, :page_type, :access_time, 0)";
        
        echo "<!-- 准备执行SQL: $sql -->\n";
        
        $stmt = $db->prepare($sql);
        
        // 绑定参数 - 简化的字段
        $params = [
            ':ic_number' => $ic,
            ':name' => $name,
            ':email' => $email,
            ':page_type' => $page_type,
            ':access_time' => $access_time
        ];
        
        echo "<!-- 参数绑定: " . print_r($params, true) . " -->\n";
        
        // 执行插入
        $result = $stmt->execute($params);
        
        if ($result) {
            $lastInsertId = $db->lastInsertId();
            $affectedRows = $stmt->rowCount();
            
            $_SESSION['last_log_id'] = $lastInsertId;
            
            echo "<!-- ✅ 数据插入成功！ -->\n";
            echo "<!-- 插入ID: $lastInsertId -->\n";
            echo "<!-- 影响行数: $affectedRows -->\n";
            
            // 验证数据是否保存成功
            $checkSQL = "SELECT * FROM price_access_logs WHERE id = :id";
            $checkStmt = $db->prepare($checkSQL);
            $checkStmt->execute([':id' => $lastInsertId]);
            
            if ($checkStmt->rowCount() > 0) {
                $savedData = $checkStmt->fetch();
                echo "<!-- ✅ 验证成功！数据已保存到数据库 -->\n";
                echo "<!-- 保存的IC: " . $savedData['ic_number'] . " -->\n";
                echo "<!-- 保存的姓名: " . $savedData['name'] . " -->\n";
                echo "<!-- 保存的页面类型: " . $savedData['page_type'] . " -->\n";
                echo "<!-- 保存的时间: " . $savedData['access_time'] . " -->\n";
            } else {
                echo "<!-- ❌ 验证失败！数据可能未保存 -->\n";
            }
            
            // 显示当前表中的记录数
            $countStmt = $db->query("SELECT COUNT(*) as total FROM price_access_logs");
            $countResult = $countStmt->fetch();
            echo "<!-- 当前表中共有 " . $countResult['total'] . " 条记录 -->\n";
            
        } else {
            echo "<!-- ❌ 数据插入失败 -->\n";
            $errorInfo = $stmt->errorInfo();
            echo "<!-- 错误信息: " . print_r($errorInfo, true) . " -->\n";
        }
        
    } catch (PDOException $e) {
        echo "<!-- ❌ 数据库错误: " . htmlspecialchars($e->getMessage()) . " -->\n";
        echo "<!-- 错误代码: " . $e->getCode() . " -->\n";
        
        // 常见错误提示
        if ($e->getCode() == 1045) {
            echo "<!-- 提示: 数据库用户名或密码错误 -->\n";
        } elseif ($e->getCode() == 1049) {
            echo "<!-- 提示: 数据库不存在 -->\n";
        } elseif ($e->getCode() == 2002) {
            echo "<!-- 提示: 无法连接到MySQL服务器，请确保MySQL服务正在运行 -->\n";
        }
        
        error_log("价格页面数据库错误: " . $e->getMessage());
    } catch (Exception $e) {
        echo "<!-- ❌ 一般错误: " . htmlspecialchars($e->getMessage()) . " -->\n";
        error_log("价格页面错误: " . $e->getMessage());
    }
    
    echo "<!-- ======= 调试信息结束 ======= -->\n";
} else {
    echo "<!-- DEBUG: 本会话中已记录访问，不再重复记录 -->\n";
}

// 根据类型设置PDF文件
if ($type == 'car') {
    $pdf_file = 'Price-Kereta.pdf';
    $pdf_title = '汽车课程价格表';
    $page_title = '汽车价格';
    $vehicle_icon = 'fas fa-car';
    $vehicle_name = '汽车';
} else { // motor
    $pdf_file = 'Price-Motor.pdf';
    $pdf_title = '摩托车课程价格表';
    $page_title = '摩托车价格';
    $vehicle_icon = 'fas fa-motorcycle';
    $vehicle_name = '摩托车';
}

// 设置自动重定向（10分钟后）
header("Refresh: 600; url=index.html");
?>

<!DOCTYPE html>
<html lang="zh-MY">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $page_title; ?> - SRI MUAR 皇城驾驶学院</title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 图标 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Microsoft YaHei', 'Segoe UI', sans-serif;
            background-color: #f8f9fa;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
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
            -webkit-user-select: text;
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
        
        .price-highlight {
            background: #e8f4fd;
            border-left: 4px solid #0056b3;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
            -webkit-user-select: text;
            user-select: text;
        }
        
        .vehicle-type-badge {
            background: <?php echo $type == 'car' ? '#0056b3' : '#28a745'; ?>;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: inline-block;
            margin-left: 10px;
        }
        
        /* 调试样式 */
        .debug-info {
            background: #ffebee;
            border: 1px solid #ffcdd2;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-size: 12px;
            color: #c62828;
            display: <?php echo isset($_GET['debug']) ? 'block' : 'none'; ?>;
            white-space: pre-wrap;
            font-family: monospace;
        }
        
        .debug-toggle {
            position: fixed;
            bottom: 20px;
            right: 20px;
            z-index: 1000;
        }
        
        .debug-success {
            background: #e8f5e9;
            border: 1px solid #c8e6c9;
            color: #2e7d32;
            padding: 10px;
            margin: 10px 0;
            border-radius: 5px;
            font-size: 12px;
            display: none;
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
        }
    </style>
</head>
<body oncontextmenu="return false">
    <!-- 调试开关 -->
    <div class="debug-toggle">
        <button class="btn btn-sm btn-warning" onclick="toggleDebug()">
            <i class="fas fa-bug me-1"></i> 显示/隐藏调试
        </button>
        <button class="btn btn-sm btn-info mt-1" onclick="checkDatabase()">
            <i class="fas fa-database me-1"></i> 检查数据库
        </button>
    </div>
    
    <!-- 调试信息 -->
    <div class="debug-info" id="debugInfo">
        <h6><i class="fas fa-bug me-2"></i>调试信息</h6>
        <div id="debugContent">
            <!-- PHP调试信息会通过JavaScript添加到这里 -->
        </div>
        <hr>
        <p>Session ID: <?php echo session_id(); ?></p>
        <p>Session Data: <?php echo isset($_SESSION['price_verification']) ? '已设置' : '未设置'; ?></p>
        <p>Vehicle Type: <?php echo htmlspecialchars($type); ?></p>
        <p>DB Log ID: <?php echo isset($_SESSION['last_log_id']) ? $_SESSION['last_log_id'] : '未设置'; ?></p>
        <p>Access Time: <?php echo date('Y-m-d H:i:s'); ?></p>
    </div>
    
    <!-- 成功信息 -->
    <div class="debug-success" id="successInfo">
        <i class="fas fa-check-circle me-2"></i>
        数据保存成功！记录ID: <strong id="successLogId"></strong>
    </div>
    
    <!-- 头部 -->
    <div class="price-header text-center">
        <div class="container">
            <h1 class="display-5 fw-bold mb-3">
                <i class="<?php echo $vehicle_icon; ?> me-2"></i><?php echo $page_title; ?>
                <span class="vehicle-type-badge"><?php echo $vehicle_name; ?>价格表</span>
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
                    <p class="mb-0"><i class="<?php echo $vehicle_icon; ?> me-2"></i> 查看类型：<?php echo $page_title; ?></p>
                </div>
            </div>
            
            <!-- PDF显示区域 -->
            <div class="tab-content mt-4">
                <div id="single-price">
                    <h4 class="mb-4" style="color: #0056b3;">
                        <i class="<?php echo $vehicle_icon; ?> me-2"></i>
                        <?php echo $pdf_title; ?>
                    </h4>
                    
                    <div class="pdf-container">
                        <div class="pdf-title">
                            <i class="fas fa-file-pdf me-2 text-danger"></i>
                            <?php echo $pdf_file; ?>
                        </div>
                        <iframe src="<?php echo $pdf_file; ?>#toolbar=0" class="pdf-viewer" 
                                title="<?php echo $pdf_title; ?>"></iframe>
                    </div>
                    
                    <div class="price-highlight mt-4">
                        <h6><i class="fas fa-info-circle me-2"></i>
                            <?php echo $vehicle_name; ?>价格说明
                        </h6>
                        <ul class="mb-0">
                            <?php if ($type == 'car'): ?>
                                <li>手动挡(D)：可驾驶所有类型汽车</li>
                                <li>自动挡(DA)：只能驾驶自动挡汽车</li>
                                <li>包含理论课和实践课费用</li>
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
            </div>
            
            <!-- 重要提示 -->
            <div class="alert alert-warning mt-4">
                <h5 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>重要提示</h5>
                <ul class="mb-0">
                    <li>以上价格仅供参考，实际价格可能因政策调整而变更</li>
                    <li>所有价格以马来西亚令吉(RM)为单位</li>
                    <li>价格信息为机密内容，请勿截图或分享给他人</li>
                    <li>如需查看其他类型价格，请返回重新选择</li>
                </ul>
            </div>
            
            <!-- 操作按钮 -->
            <div class="row mt-4 no-print">
                <div class="col-md-12 text-center">
                    <a href="price_information.php" class="btn btn-outline-secondary">
                        <i class="fas fa-redo me-2"></i> 查看其他价格
                    </a>
                    <a href="index.html" class="btn btn-outline-primary ms-2">
                        <i class="fas fa-home me-2"></i> 返回首页
                    </a>
                    <a href="history.php" target="_blank" class="btn btn-outline-info ms-2">
                        <i class="fas fa-history me-2"></i> 查看访问记录
                    </a>
                    <button class="btn btn-outline-warning ms-2" onclick="forceSave()">
                        <i class="fas fa-save me-2"></i> 手动保存记录
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 显示/隐藏调试信息
        function toggleDebug() {
            const debugInfo = document.getElementById('debugInfo');
            debugInfo.style.display = debugInfo.style.display === 'none' ? 'block' : 'none';
        }
        
        // 从HTML注释中提取调试信息
        function extractDebugInfo() {
            const html = document.documentElement.outerHTML;
            const debugComments = [];
            
            // 查找所有包含DEBUG的注释
            const commentRegex = /<!--([\s\S]*?)-->/g;
            let match;
            
            while ((match = commentRegex.exec(html)) !== null) {
                if (match[1].includes('DEBUG') || match[1].includes('调试')) {
                    debugComments.push(match[1].trim());
                }
            }
            
            // 显示调试信息
            const debugContent = document.getElementById('debugContent');
            if (debugComments.length > 0) {
                debugContent.innerHTML = debugComments.join('<br>');
            }
            
            // 如果有成功消息，显示成功信息
            if (html.includes('✅ 数据插入成功！')) {
                const successInfo = document.getElementById('successInfo');
                const successLogId = document.getElementById('successLogId');
                
                // 提取数据库ID
                const logIdMatch = html.match(/插入ID:\s*(\d+)/);
                if (logIdMatch) {
                    successLogId.textContent = logIdMatch[1];
                }
                
                successInfo.style.display = 'block';
                setTimeout(() => {
                    successInfo.style.display = 'none';
                }, 5000);
            }
        }
        
        // 检查数据库连接
        function checkDatabase() {
            if (confirm('检查数据库连接和状态？')) {
                fetch('test_database.php')
                    .then(response => response.text())
                    .then(data => {
                        // 在新窗口打开测试结果
                        const newWindow = window.open('', '_blank');
                        newWindow.document.write(data);
                        newWindow.document.close();
                    })
                    .catch(error => {
                        alert('检查失败: ' + error);
                    });
            }
        }
        
        // 手动保存记录
        function forceSave() {
            if (confirm('手动保存当前访问记录到数据库？')) {
                fetch('manual_save.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'save_record',
                        ic: '<?php echo $_SESSION["price_verification"]["ic"] ?? ""; ?>',
                        name: '<?php echo $_SESSION["price_verification"]["name"] ?? ""; ?>',
                        email: '<?php echo $_SESSION["price_verification"]["email"] ?? ""; ?>',
                        page_type: '<?php echo $type; ?>'
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ 保存成功！\n数据库ID: ' + data.id);
                        location.reload();
                    } else {
                        alert('❌ 保存失败: ' + data.message);
                    }
                })
                .catch(error => {
                    alert('请求失败: ' + error);
                });
            }
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
                    display.style.color = "#dc3545";
                    
                    // 3秒后重定向到首页
                    setTimeout(function() {
                        window.location.href = 'index.html';
                    }, 3000);
                }
            }, 1000);
        }
        
        // 页面加载时启动
        window.onload = function () {
            // 提取和显示调试信息
            extractDebugInfo();
            
            // 10分钟 = 600秒
            const duration = 600;
            const display = document.querySelector('#timer');
            
            if (display) {
                startTimer(duration, display);
            }
            
            // 记录页面加载时间
            window.pageLoadTime = Date.now();
            const logId = '<?php echo isset($_SESSION["last_log_id"]) ? $_SESSION["last_log_id"] : 0; ?>';
            
            // 页面卸载时更新停留时间
            window.addEventListener('beforeunload', function() {
                const duration = Math.floor((Date.now() - window.pageLoadTime) / 1000);
                
                if (logId > 0) {
                    // 更新停留时间到数据库
                    updateDuration(logId, duration);
                }
            });
        };
        
        // 更新停留时间
        function updateDuration(logId, duration) {
            const formData = new FormData();
            formData.append('log_id', logId);
            formData.append('duration_seconds', duration);
            
            // 使用 navigator.sendBeacon 确保在页面关闭时也能发送
            if (navigator.sendBeacon) {
                navigator.sendBeacon('update_history.php', formData);
            } else {
                fetch('update_history.php', {
                    method: 'POST',
                    body: formData,
                    keepalive: true
                });
            }
        }
        
        // 防止右键菜单等
        document.addEventListener('contextmenu', function(e) {
            e.preventDefault();
            return false;
        });
    </script>
</body>
</html>