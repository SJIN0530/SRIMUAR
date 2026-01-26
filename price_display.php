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

// 记录访问开始时间
if (!isset($_SESSION['price_view_start_time'])) {
    $_SESSION['price_view_start_time'] = time();
    $_SESSION['price_view_page_type'] = $type;
    
    // 获取用户信息
    $ic = $_SESSION['price_verification']['ic'] ?? 'Unknown';
    $name = $_SESSION['price_verification']['name'] ?? 'Unknown';
    $email = $_SESSION['price_verification']['email'] ?? 'Unknown';
    $page_type = $type;
    $access_time = date('Y-m-d H:i:s'); // 使用马来西亚时间
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    // 生成唯一的记录ID
    $record_id = 'record_' . time() . '_' . rand(1000, 9999);
    $_SESSION['price_log_id'] = $record_id;
    
    // 创建HTML格式的记录
    $html_record = "
    <tr id='{$record_id}' class='log-record'>
        <td>{$access_time}</td>
        <td><span class='ic'>{$ic}</span></td>
        <td><span class='name'>{$name}</span></td>
        <td><span class='email'>{$email}</span></td>
        <td>
            <span class='badge " . ($page_type == 'car' ? 'car' : 'motor') . "'>
                " . ($page_type == 'car' ? '汽车价格' : '摩托车价格') . "
            </span>
        </td>
        <td>
            <span class='ip-address'>{$ip_address}</span>
        </td>
        <td>
            <span class='duration' data-record='{$record_id}' data-start='" . time() . "'>正在查看...</span>
        </td>
        <td>
            <button class='btn btn-sm btn-outline-danger delete-record' 
                    data-record='{$record_id}'>
                <i class='fas fa-trash'></i>
            </button>
        </td>
    </tr>
    ";
    
    // 保存到 history.html 文件
    saveToHistory($html_record);
    
    error_log("记录已保存到 history.html, ID: {$record_id}");
    echo "<!-- DEBUG: 记录已保存到 history.html, ID: {$record_id} -->\n";
} else {
    echo "<!-- DEBUG: 本会话中已记录访问 -->\n";
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

// 保存记录到文件的函数
function saveToHistory($html_record) {
    $history_file = 'history.html';
    
    // 如果文件不存在，创建新文件
    if (!file_exists($history_file)) {
        createHistoryFile($html_record);
        return;
    }
    
    // 读取现有内容
    $content = file_get_contents($history_file);
    
    // 查找 tbody 标签
    $tbody_start = strpos($content, '<tbody>');
    
    if ($tbody_start !== false) {
        // 在 tbody 开始标签后插入新记录
        $insert_position = $tbody_start + 7; // 7是 '<tbody>' 的长度
        $new_content = substr($content, 0, $insert_position) . 
                      $html_record . 
                      substr($content, $insert_position);
        
        // 保存文件
        file_put_contents($history_file, $new_content);
    } else {
        // 如果找不到 tbody，在表格后面添加
        $table_end = strpos($content, '</table>');
        if ($table_end !== false) {
            $new_content = substr($content, 0, $table_end) . 
                          $html_record . 
                          substr($content, $table_end);
            file_put_contents($history_file, $new_content);
        } else {
            // 在文件末尾添加
            file_put_contents($history_file, $content . $html_record, FILE_APPEND);
        }
    }
}

// 创建 history.html 文件的函数
function createHistoryFile($html_record = '') {
    $current_time = date('Y-m-d H:i:s');
    
    $content = '<!DOCTYPE html>
<html lang="zh-MY">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>访问记录 - SRI MUAR 皇城驾驶学院</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { font-family: "Microsoft YaHei", sans-serif; padding: 20px; background: #f8f9fa; }
        .container { max-width: 1400px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        h1 { color: #0056b3; margin-bottom: 30px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th { background: #0056b3; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f5f5f5; }
        .badge { padding: 5px 10px; border-radius: 15px; font-size: 0.85rem; font-weight: 600; }
        .badge.car { background: #dc3545; color: white; }
        .badge.motor { background: #28a745; color: white; }
        .ic { font-family: monospace; background: #f8f9fa; padding: 2px 5px; border-radius: 3px; }
        .name { color: #0056b3; font-weight: 600; }
        .email { color: #dc3545; }
        .ip-address { font-family: monospace; font-size: 0.85rem; color: #666; }
        .actions { margin-bottom: 20px; }
        .no-records { text-align: center; padding: 40px; color: #666; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-user-clock me-2"></i>价格页面访问记录</h1>
        
        <div class="actions">
            <button id="refreshBtn" class="btn btn-primary">
                <i class="fas fa-sync-alt me-2"></i>刷新
            </button>
            <a href="index.html" class="btn btn-secondary ms-2">
                <i class="fas fa-home me-2"></i>返回首页
            </a>
        </div>
        
        <div class="timestamp text-muted mb-3">
            最后更新: ' . $current_time . '
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>访问时间</th>
                    <th>IC身份证</th>
                    <th>姓名</th>
                    <th>Email</th>
                    <th>页面类型</th>
                    <th>IP地址</th>
                    <th>停留时间</th>
                    <th>操作</th>
                </tr>
            </thead>
            <tbody>
                ' . $html_record . '
            </tbody>
        </table>
        
        ' . (empty($html_record) ? '<div class="no-records">
            <i class="fas fa-clipboard-list fa-3x mb-3"></i>
            <h4>暂无访问记录</h4>
            <p>当有客户查看价格页面时，记录会显示在这里</p>
        </div>' : '') . '
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // 刷新按钮
            document.getElementById("refreshBtn").addEventListener("click", function() {
                location.reload();
            });
            
            // 删除记录
            document.querySelectorAll(".delete-record").forEach(btn => {
                btn.addEventListener("click", function() {
                    if(confirm("确定要删除这条记录吗？")) {
                        const recordId = this.getAttribute("data-record");
                        fetch("delete_record.php", {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/x-www-form-urlencoded",
                            },
                            body: "record_id=" + recordId
                        }).then(() => {
                            this.closest("tr").remove();
                            checkIfEmpty();
                        });
                    }
                });
            });
            
            // 更新停留时间显示
            updateDurationDisplays();
        });
        
        function checkIfEmpty() {
            const records = document.querySelectorAll(".log-record");
            if (records.length === 0) {
                location.reload();
            }
        }
        
        function updateDurationDisplays() {
            document.querySelectorAll(".duration").forEach(element => {
                const startTime = parseInt(element.getAttribute("data-start"));
                if (startTime && startTime > 0) {
                    const now = Math.floor(Date.now() / 1000);
                    const duration = now - startTime;
                    if (duration < 60) {
                        element.textContent = duration + "秒";
                    } else {
                        const minutes = Math.floor(duration / 60);
                        const seconds = duration % 60;
                        element.textContent = minutes + "分" + seconds + "秒";
                    }
                }
            });
        }
    </script>
</body>
</html>';
    
    file_put_contents('history.html', $content);
}
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
            display: none; /* 默认隐藏 */
        }
        
        .show-debug .debug-info {
            display: block;
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
    <!-- 调试模式开关 -->
    <div class="position-fixed top-0 end-0 m-3">
        <button class="btn btn-sm btn-secondary" onclick="document.body.classList.toggle('show-debug')">
            <i class="fas fa-bug"></i> 调试
        </button>
    </div>
    
    <!-- 调试信息 -->
    <div class="debug-info">
        <h6><i class="fas fa-bug me-2"></i>调试信息</h6>
        <p>Session ID: <?php echo session_id(); ?></p>
        <p>Session Data: <?php echo isset($_SESSION['price_verification']) ? '已设置' : '未设置'; ?></p>
        <p>Vehicle Type: <?php echo htmlspecialchars($type); ?></p>
        <p>Log ID: <?php echo isset($_SESSION['price_log_id']) ? $_SESSION['price_log_id'] : '未设置'; ?></p>
        <p>Access Time: <?php echo date('Y-m-d H:i:s'); ?></p>
        <p>Record Saved: <?php echo isset($_SESSION['price_log_id']) ? '是 (ID: ' . $_SESSION['price_log_id'] . ')' : '否'; ?></p>
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
                    <a href="history.html" class="btn btn-outline-info ms-2">
                        <i class="fas fa-history me-2"></i> 查看访问记录
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
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
        
        // 页面加载时启动计时器
        window.onload = function () {
            // 10分钟 = 600秒
            const duration = 600;
            const display = document.querySelector('#timer');
            
            if (display) {
                startTimer(duration, display);
            }
            
            // 记录页面加载时间
            window.pageLoadTime = Date.now();
            const recordId = '<?php echo $_SESSION["price_log_id"] ?? ""; ?>';
            
            // 页面卸载时更新停留时间
            window.addEventListener('beforeunload', function() {
                const duration = Math.floor((Date.now() - window.pageLoadTime) / 1000);
                
                if (recordId) {
                    // 更新停留时间
                    updateDuration(recordId, duration);
                }
            });
        };
        
        // 更新停留时间
        function updateDuration(recordId, duration) {
            const formData = new FormData();
            formData.append('record_id', recordId);
            formData.append('duration', duration);
            
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