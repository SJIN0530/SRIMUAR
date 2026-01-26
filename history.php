<?php
// history.php - 使用数据库存储访问记录
session_start();
require_once 'database.php';

// 设置马来西亚时区
date_default_timezone_set('Asia/Kuala_Lumpur');

// 检查数据库连接
$db = getDB();
if (!$db) {
    die("数据库连接失败，请检查配置！");
}

// 获取查询参数
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$page_type = isset($_GET['page_type']) ? $_GET['page_type'] : 'all';

// 处理删除请求
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_id'])) {
        $delete_id = intval($_POST['delete_id']);
        
        try {
            $stmt = $db->prepare("UPDATE price_access_logs SET is_active = 0 WHERE id = :id AND is_active = 1");
            $stmt->execute([':id' => $delete_id]);
            
            if ($stmt->rowCount() > 0) {
                $_SESSION['message'] = ['type' => 'success', 'text' => '记录已删除'];
            } else {
                $_SESSION['message'] = ['type' => 'danger', 'text' => '删除失败，记录不存在或已被删除'];
            }
        } catch (Exception $e) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => '删除失败: ' . $e->getMessage()];
        }
        
        header('Location: history.php');
        exit();
    }
    
    if (isset($_POST['clear_old'])) {
        try {
            // 删除30天前的记录
            $thirty_days_ago = date('Y-m-d H:i:s', strtotime('-30 days'));
            $stmt = $db->prepare("UPDATE price_access_logs SET is_active = 0 WHERE access_time < :cutoff_date AND is_active = 1");
            $stmt->execute([':cutoff_date' => $thirty_days_ago]);
            
            $deleted_count = $stmt->rowCount();
            $_SESSION['message'] = ['type' => 'success', 'text' => "已清除 {$deleted_count} 条30天前的记录"];
        } catch (Exception $e) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => '清除失败: ' . $e->getMessage()];
        }
        
        header('Location: history.php');
        exit();
    }
    
    if (isset($_POST['clear_all'])) {
        try {
            $stmt = $db->prepare("UPDATE price_access_logs SET is_active = 0 WHERE is_active = 1");
            $stmt->execute();
            
            $deleted_count = $stmt->rowCount();
            $_SESSION['message'] = ['type' => 'success', 'text' => "已清空 {$deleted_count} 条记录"];
        } catch (Exception $e) {
            $_SESSION['message'] = ['type' => 'danger', 'text' => '清空失败: ' . $e->getMessage()];
        }
        
        header('Location: history.php');
        exit();
    }
}

// 显示消息
$message = isset($_SESSION['message']) ? $_SESSION['message'] : null;
unset($_SESSION['message']);

// 构建查询条件
$where_conditions = ["is_active = 1"];
$params = [];

// 搜索条件
if ($search) {
    $where_conditions[] = "(ic_number LIKE :search OR full_name LIKE :search OR email LIKE :search)";
    $params[':search'] = "%{$search}%";
}

// 日期条件
if ($date_from) {
    $where_conditions[] = "DATE(access_time) >= :date_from";
    $params[':date_from'] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(access_time) <= :date_to";
    $params[':date_to'] = $date_to;
}

// 页面类型条件
if ($page_type && $page_type !== 'all') {
    $where_conditions[] = "vehicle_type = :vehicle_type";
    $params[':vehicle_type'] = $page_type;
}

// 构建完整查询
$where_sql = implode(' AND ', $where_conditions);
$sql = "SELECT * FROM price_access_logs WHERE {$where_sql} ORDER BY access_time DESC";

try {
    // 获取总记录数
    $count_sql = "SELECT COUNT(*) as total FROM price_access_logs WHERE {$where_sql}";
    $stmt = $db->prepare($count_sql);
    $stmt->execute($params);
    $total_result = $stmt->fetch();
    $total_visits = $total_result['total'];
    
    // 获取记录数据
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $filtered_records = $stmt->fetchAll();
} catch (Exception $e) {
    die("查询失败: " . $e->getMessage());
}

// 统计信息
try {
    // 独立客户数
    $sql = "SELECT COUNT(DISTINCT ic_number) as unique_customers FROM price_access_logs WHERE is_active = 1";
    $stmt = $db->query($sql);
    $result = $stmt->fetch();
    $unique_customer_count = $result['unique_customers'];
    
    // 今日访问次数
    $today = date('Y-m-d');
    $sql = "SELECT COUNT(*) as today_count FROM price_access_logs WHERE DATE(access_time) = :today AND is_active = 1";
    $stmt = $db->prepare($sql);
    $stmt->execute([':today' => $today]);
    $result = $stmt->fetch();
    $today_visits = $result['today_count'];
    
    // 页面类型统计
    $sql = "SELECT vehicle_type, COUNT(*) as count FROM price_access_logs WHERE is_active = 1 GROUP BY vehicle_type";
    $stmt = $db->query($sql);
    $page_stats = [];
    $car_visits = 0;
    $motor_visits = 0;
    while ($row = $stmt->fetch()) {
        if ($row['vehicle_type'] == 'car') {
            $car_visits = $row['count'];
        } elseif ($row['vehicle_type'] == 'motor') {
            $motor_visits = $row['count'];
        }
    }
    
    // 停留时间统计
    $sql = "SELECT 
            AVG(duration) as avg_duration,
            MAX(duration) as max_duration,
            SUM(duration) as total_duration
            FROM price_access_logs WHERE is_active = 1 AND duration > 0";
    $stmt = $db->query($sql);
    $duration_stats = $stmt->fetch();
    $avg_duration = round($duration_stats['avg_duration'] ?: 0);
    $max_duration = $duration_stats['max_duration'] ?: 0;
    
} catch (Exception $e) {
    // 如果统计查询失败，使用默认值
    $unique_customer_count = 0;
    $today_visits = 0;
    $car_visits = 0;
    $motor_visits = 0;
    $avg_duration = 0;
    $max_duration = 0;
}
?>

<!DOCTYPE html>
<html lang="zh-MY">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>访问记录 - SRI MUAR 皇城驾驶学院</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 图标 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
            background-color: #f5f7fa;
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
            margin: 0 15px;
        }

        .logo-container {
            display: flex;
            align-items: center;
            height: auto;
            padding: 0;
        }

        .logo-img {
            height: 160px;
            width: auto;
            object-fit: contain;
            transition: all 0.3s ease;
        }

        /* 主导航菜单 */
        .main-nav {
            display: flex;
            gap: 25px;
            list-style: none;
            margin: 0;
            padding: 0;
            padding-left: 0;
            align-items: center;
            flex-wrap: wrap;
        }

        .main-nav a {
            color: var(--dark-gray);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            padding: 8px 12px;
            border-radius: 5px;
            white-space: nowrap;
        }

        .main-nav a:hover {
            color: var(--primary-blue);
            background-color: rgba(0, 86, 179, 0.1);
        }

        .main-nav .active {
            color: var(--primary-blue);
            font-weight: 600;
            border-bottom: 3px solid var(--primary-blue);
        }

        /* 页面标题 */
        .page-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #003d82 100%);
            color: white;
            padding: 80px 20px 50px;
            text-align: center;
            margin: 0 auto 30px;
            width: 90%;
            max-width: 2000px;
            border-radius: 10px;
            box-sizing: border-box;
        }

        .page-header h1 {
            font-size: 3rem;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .page-header p {
            font-size: 1.2rem;
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto;
        }

        /* 仪表板样式 */
        .dashboard-container {
            padding: 0 20px 50px;
            max-width: 1200px;
            margin: 0 auto;
        }

        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            margin-bottom: 30px;
        }

        .dashboard-title {
            color: var(--primary-blue);
            font-weight: 600;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid #eee;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .stat-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.08);
            text-align: center;
            transition: all 0.3s ease;
            border-top: 4px solid var(--primary-blue);
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--primary-blue) 0%, #004494 100%);
            color: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 15px;
            font-size: 1.5rem;
        }

        .stat-number {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 5px;
        }

        .stat-label {
            color: #666;
            font-size: 1rem;
            font-weight: 500;
        }

        .stat-sub {
            font-size: 0.9rem;
            color: #888;
            margin-top: 5px;
        }

        /* 过滤器样式 */
        .filter-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }

        .filter-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-btn {
            background: white;
            border: 2px solid #dee2e6;
            color: #495057;
            padding: 8px 20px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s;
            cursor: pointer;
        }

        .filter-btn:hover {
            border-color: var(--primary-blue);
            color: var(--primary-blue);
        }

        .filter-btn.active {
            background: var(--primary-blue);
            border-color: var(--primary-blue);
            color: white;
        }

        .date-filter {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        /* 标签样式 */
        .badge {
            padding: 6px 12px;
            font-weight: 600;
            border-radius: 20px;
            font-size: 0.85rem;
        }

        .badge-motor {
            background: #28a745;
            color: white;
        }

        .badge-car {
            background: #dc3545;
            color: white;
        }

        /* 表格样式 */
        .data-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        /* 个人信息样式 */
        .user-info {
            font-size: 0.9rem;
        }
        
        .user-info .ic {
            color: #666;
            font-family: monospace;
        }
        
        .user-info .name {
            font-weight: 600;
            color: var(--primary-blue);
        }
        
        .user-info .email {
            color: #dc3545;
        }
        
        /* 搜索框样式 */
        .search-container {
            margin-bottom: 20px;
        }
        
        .search-box {
            max-width: 400px;
        }
        
        /* 操作按钮样式 */
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 20px;
        }
        
        /* 页脚样式 */
        footer {
            background: #2c3e50;
            color: white;
            padding: 50px 0 20px 0;
            margin-top: 50px;
        }

        /* 响应式设计 */
        @media (max-width: 992px) {
            .page-header {
                padding: 60px 0 40px;
            }
            
            .page-header h1 {
                font-size: 2.5rem;
            }
            
            .stat-cards {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .logo-img {
                height: 120px;
            }
            
            .main-nav {
                gap: 10px;
                justify-content: center;
            }
            
            .page-header h1 {
                font-size: 2rem;
            }
            
            .stat-cards {
                grid-template-columns: 1fr;
            }
            
            .filter-group {
                flex-direction: column;
                align-items: stretch;
            }
            
            .date-filter {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏 -->
    <nav class="top-navbar">
        <div class="container">
            <div class="row align-items-center">
                <!-- Logo -->
                <div class="col-md-3">
                    <div class="logo-container">
                        <a href="index.html" class="d-flex align-items-center text-decoration-none">
                            <img src="logo.PNG" alt="SRI MUAR Logo" class="logo-img"
                                onerror="this.onerror=null;this.src='logo.png';">
                        </a>
                    </div>
                </div>
                
                <!-- 导航菜单 -->
                <div class="col-md-9">
                    <div class="nav-menu-container">
                        <ul class="main-nav">
                            <li><a href="index.html">首页</a></li>
                            <li><a href="courses.html">课程</a></li>
                            <li><a href="products.html">配套</a></li>
                            <li><a href="contact.html">联系我们</a></li>
                            <li><a href="aboutus.html">学院简介</a></li>
                            <li><a href="picture.html">学院图集</a></li>
                            <li><a href="history.php" class="active">访问记录</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- 页面标题 -->
    <section class="page-header">
        <div class="container">
            <h1><i class="fas fa-user-clock me-3"></i>价格页面访问记录</h1>
            <p>查看客户查看价格信息的详细记录 - 数据库版本</p>
        </div>
    </section>

    <!-- 仪表板内容 -->
    <div class="dashboard-container">
        <div class="container">
            <!-- 消息提示 -->
            <?php if ($message): ?>
                <div class="alert alert-<?php echo $message['type']; ?> alert-dismissible fade show" role="alert">
                    <?php echo htmlspecialchars($message['text']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- 统计卡片 -->
            <div class="stat-cards">
                <div class="stat-card">
                    <div class="stat-icon">
                        <i class="fas fa-eye"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($total_visits); ?></div>
                    <div class="stat-label">总访问次数</div>
                    <div class="stat-sub">今日: <?php echo $today_visits; ?> 次</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #28a745 0%, #218838 100%);">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="stat-number"><?php echo number_format($unique_customer_count); ?></div>
                    <div class="stat-label">独立客户</div>
                    <div class="stat-sub">平均访问: <?php echo $unique_customer_count > 0 ? round($total_visits / $unique_customer_count, 1) : 0; ?>次</div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                        <i class="fas fa-clock"></i>
                    </div>
                    <div class="stat-number">
                        <?php 
                        if ($avg_duration < 60) {
                            echo $avg_duration . '秒';
                        } else {
                            echo round($avg_duration / 60) . '分';
                        }
                        ?>
                    </div>
                    <div class="stat-label">平均停留时间</div>
                    <div class="stat-sub">最长: 
                        <?php 
                        if ($max_duration < 60) {
                            echo $max_duration . '秒';
                        } else {
                            echo floor($max_duration / 60) . '分' . ($max_duration % 60) . '秒';
                        }
                        ?>
                    </div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-icon" style="background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div class="stat-number"><?php echo $motor_visits; ?>/<?php echo $car_visits; ?></div>
                    <div class="stat-label">摩托/汽车访问</div>
                    <div class="stat-sub">总数: <?php echo $total_visits; ?></div>
                </div>
            </div>

            <!-- 搜索框 -->
            <div class="search-container">
                <form method="GET" action="history.php" class="d-flex">
                    <input type="text" name="search" class="form-control search-box" 
                           placeholder="搜索IC、姓名或Email..." value="<?php echo htmlspecialchars($search); ?>">
                    <button type="submit" class="btn btn-primary ms-2">
                        <i class="fas fa-search"></i> 搜索
                    </button>
                    <?php if ($search): ?>
                        <a href="history.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-times"></i> 清除搜索
                        </a>
                    <?php endif; ?>
                </form>
            </div>

            <!-- 过滤器 -->
            <div class="filter-container">
                <form method="GET" action="history.php" class="filter-group">
                    <div>
                        <span class="me-2">页面类型:</span>
                        <button type="submit" name="page_type" value="all" 
                                class="filter-btn <?php echo $page_type == 'all' ? 'active' : ''; ?>">
                            全部
                        </button>
                        <button type="submit" name="page_type" value="motor" 
                                class="filter-btn <?php echo $page_type == 'motor' ? 'active' : ''; ?>">
                            摩托车
                        </button>
                        <button type="submit" name="page_type" value="car" 
                                class="filter-btn <?php echo $page_type == 'car' ? 'active' : ''; ?>">
                            汽车
                        </button>
                        <?php if ($search): ?>
                            <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                        <?php endif; ?>
                    </div>
                    
                    <div class="date-filter">
                        <span class="me-2">时间范围:</span>
                        <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                               class="form-control form-control-sm" style="width: 150px;">
                        <span>至</span>
                        <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                               class="form-control form-control-sm" style="width: 150px;">
                        <button type="submit" class="btn btn-primary btn-sm ms-2">应用</button>
                        <a href="history.php" class="btn btn-secondary btn-sm">重置</a>
                    </div>
                </form>
            </div>

            <!-- 数据表格 -->
            <div class="dashboard-card">
                <h3 class="dashboard-title">
                    <i class="fas fa-table"></i> 客户访问记录
                    <span class="badge bg-secondary ms-2"><?php echo count($filtered_records); ?> 条记录</span>
                </h3>
                
                <?php if (count($filtered_records) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>序号</th>
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
                            <?php foreach ($filtered_records as $index => $record): ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td><?php echo htmlspecialchars($record['access_time']); ?></td>
                                <td>
                                    <div class="user-info">
                                        <span class="ic"><?php echo htmlspecialchars($record['ic_number']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <span class="name"><?php echo htmlspecialchars($record['full_name']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <span class="email"><?php echo htmlspecialchars($record['email']); ?></span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge <?php echo $record['vehicle_type'] == 'car' ? 'badge-car' : 'badge-motor'; ?>">
                                        <?php echo $record['vehicle_type'] == 'car' ? '汽车价格' : '摩托车价格'; ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="ip-address"><?php echo htmlspecialchars($record['ip_address']); ?></span>
                                </td>
                                <td>
                                    <?php 
                                    if ($record['duration'] < 60) {
                                        echo $record['duration'] . '秒';
                                    } else {
                                        $minutes = floor($record['duration'] / 60);
                                        $seconds = $record['duration'] % 60;
                                        echo $minutes . '分' . $seconds . '秒';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-danger delete-record" 
                                            data-id="<?php echo $record['id']; ?>"
                                            data-name="<?php echo htmlspecialchars($record['full_name']); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-clipboard-list fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">没有找到访问记录</h4>
                    <p class="text-muted">
                        <?php if ($search || $date_from || $date_to || $page_type !== 'all'): ?>
                            请尝试修改搜索条件
                        <?php else: ?>
                            还没有任何客户访问记录
                        <?php endif; ?>
                    </p>
                </div>
                <?php endif; ?>
                
                <!-- 操作按钮 -->
                <div class="action-buttons">
                    <a href="history.php" class="btn btn-primary me-2">
                        <i class="fas fa-sync-alt me-2"></i>刷新数据
                    </a>
                    <button type="button" class="btn btn-outline-warning me-2" data-bs-toggle="modal" data-bs-target="#clearOldModal">
                        <i class="fas fa-trash-alt me-2"></i>清除30天前数据
                    </button>
                    <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#clearAllModal">
                        <i class="fas fa-trash me-2"></i>清空所有记录
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- 清除旧数据模态框 -->
    <div class="modal fade" id="clearOldModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle text-warning me-2"></i>清除旧数据</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>确定要清除30天前的访问记录吗？此操作不可撤销！</p>
                    <div class="alert alert-warning">
                        <i class="fas fa-info-circle me-2"></i>
                        这将从数据库中删除30天前的记录（标记为已删除）
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <form method="POST" action="">
                        <input type="hidden" name="clear_old" value="1">
                        <button type="submit" class="btn btn-danger">确定清除</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- 清空所有记录模态框 -->
    <div class="modal fade" id="clearAllModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title text-danger"><i class="fas fa-exclamation-triangle me-2"></i>确认清空</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="lead">您确定要清空所有访问记录吗？</p>
                    <p class="text-muted">此操作将删除所有 <?php echo $total_visits; ?> 条记录，且不可恢复！</p>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        警告：此操作将从数据库中删除所有记录！
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">取消</button>
                    <form method="POST" action="">
                        <input type="hidden" name="clear_all" value="1">
                        <button type="submit" class="btn btn-danger">确认清空</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <footer>
        <!-- 版权信息 -->
        <div class="row mt-5 pt-3 border-top border-secondary">
            <div class="col-12 text-center">
                <p class="mb-0">
                    &copy; 2020 SRI MUAR 皇城驾驶学院. 版权所有.
                    <span class="mx-2">|</span>
                    All right reserved 2020. By E-Driving Software Sdn Bhd
                </p>
            </div>
        </div>
    </footer>

    <!-- JavaScript 库 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // 删除单条记录
            document.querySelectorAll('.delete-record').forEach(btn => {
                btn.addEventListener('click', function() {
                    const id = this.getAttribute('data-id');
                    const name = this.getAttribute('data-name');
                    
                    if (confirm(`确定要删除 ${name} 的记录吗？`)) {
                        const form = document.createElement('form');
                        form.method = 'POST';
                        form.action = '';
                        
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = 'delete_id';
                        input.value = id;
                        
                        form.appendChild(input);
                        document.body.appendChild(form);
                        form.submit();
                    }
                });
            });
            
            // 自动刷新（每30秒检查一次）
            let refreshTimer;
            function checkForUpdates() {
                fetch('check_new_records.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.has_new) {
                            // 如果有新数据，显示提示
                            const refreshBtn = document.querySelector('.btn-primary i.fa-sync-alt');
                            if (refreshBtn) {
                                const btn = refreshBtn.closest('a');
                                btn.classList.add('btn-warning');
                                btn.innerHTML = '<i class="fas fa-bell me-2"></i>有新数据，点击刷新';
                            }
                        }
                    })
                    .catch(error => console.error('检查更新失败:', error));
            }
            
            // 启动自动检查
            refreshTimer = setInterval(checkForUpdates, 30000); // 每30秒检查一次
            
            // 页面离开时清除定时器
            window.addEventListener('beforeunload', function() {
                clearInterval(refreshTimer);
            });
        });
    </script>
</body>
</html>