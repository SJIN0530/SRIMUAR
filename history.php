<?php
// history.php
session_start();
require_once 'database_config.php';

// 获取查询参数
$page_type = isset($_GET['page_type']) ? $_GET['page_type'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// 获取数据库连接
$conn = Database::getConnection();

// 构建查询条件
$where_conditions = [];
$params = [];

if ($page_type && $page_type !== 'all') {
    $where_conditions[] = "page_type = ?";
    $params[] = $page_type;
}

if ($date_from) {
    $where_conditions[] = "access_time >= ?";
    $params[] = $date_from . ' 00:00:00';
}

if ($date_to) {
    $where_conditions[] = "access_time <= ?";
    $params[] = $date_to . ' 23:59:59';
}

if ($search) {
    $where_conditions[] = "(ic_number LIKE ? OR name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 获取统计信息
$stats_sql = "SELECT 
                COUNT(*) as total_visits,
                COUNT(DISTINCT ic_number) as unique_customers,
                AVG(duration_seconds) as avg_duration,
                MAX(duration_seconds) as max_duration,
                SUM(CASE WHEN page_type = 'motor' THEN 1 ELSE 0 END) as motor_visits,
                SUM(CASE WHEN page_type = 'car' THEN 1 ELSE 0 END) as car_visits,
                SUM(CASE WHEN page_type = 'all' THEN 1 ELSE 0 END) as all_visits
              FROM price_access_logs $where_sql";

$stmt = $conn->prepare($stats_sql);
$stmt->execute($params);
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// 获取今日访问次数
$today_sql = "SELECT COUNT(*) as today_visits FROM price_access_logs WHERE DATE(access_time) = CURDATE()";
$today_stmt = $conn->query($today_sql);
$today_result = $today_stmt->fetch();
$today_visits = $today_result ? $today_result['today_visits'] : 0;

// 获取数据用于表格显示
$sql = "SELECT * FROM price_access_logs $where_sql ORDER BY access_time DESC";
$stmt = $conn->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
    
    <!-- 数据表格插件 -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    
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
            background-color: #f5f7fa;
        }

        .top-navbar {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            margin-bottom: 20px;
        }

        .logo-img {
            height: 120px;
            width: auto;
            object-fit: contain;
        }

        .main-nav {
            display: flex;
            gap: 20px;
            list-style: none;
            margin: 0;
            padding: 0;
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

        .page-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #003d82 100%);
            color: white;
            padding: 60px 20px 40px;
            text-align: center;
            margin-bottom: 30px;
            border-radius: 10px;
        }

        .page-header h1 {
            font-size: 2.5rem;
            margin-bottom: 15px;
            font-weight: 700;
        }

        .stat-cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            font-size: 2.2rem;
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

        .data-table {
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
        }

        .dataTables_wrapper {
            padding: 20px;
        }

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

        .badge-all {
            background: #FFD700;
            color: #333;
        }

        .filter-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }

        .db-status {
            background: #d4edda;
            color: #155724;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: 500;
        }

        .db-error {
            background: #f8d7da;
            color: #721c24;
        }

        @media (max-width: 768px) {
            .page-header h1 {
                font-size: 2rem;
            }
            
            .stat-cards {
                grid-template-columns: 1fr;
            }
            
            .main-nav {
                gap: 10px;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- 顶部导航栏 -->
    <nav class="top-navbar">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <div class="logo-container">
                        <a href="index.html">
                            <img src="logo.PNG" alt="SRI MUAR Logo" class="logo-img"
                                onerror="this.onerror=null;this.src='logo.png';">
                        </a>
                    </div>
                </div>
                
                <div class="col-md-9">
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
    </nav>

    <!-- 页面标题 -->
    <section class="page-header">
        <div class="container">
            <h1><i class="fas fa-user-clock me-3"></i>价格页面访问记录</h1>
            <p>查看客户查看价格信息的详细记录</p>
        </div>
    </section>

    <!-- 主内容 -->
    <div class="container">
        <?php
        // 显示数据库连接状态
        $db_check = Database::checkConnection();
        if (!$db_check['success']) {
            echo '<div class="db-status db-error">';
            echo '<i class="fas fa-exclamation-triangle me-2"></i>' . $db_check['message'];
            echo '<br><small>请确保：1. MySQL服务正在运行 2. 数据库已创建</small>';
            echo '</div>';
        } else {
            echo '<div class="db-status">';
            echo '<i class="fas fa-check-circle me-2"></i>数据库连接正常';
            
            // 显示总记录数
            $count_sql = "SELECT COUNT(*) as total FROM price_access_logs";
            $count_stmt = $conn->query($count_sql);
            $count_result = $count_stmt->fetch();
            echo ' | 共有 ' . ($count_result['total'] ?? 0) . ' 条记录';
            
            echo '</div>';
        }
        ?>

        <!-- 统计卡片 -->
        <div class="stat-cards">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-eye"></i>
                </div>
                <div class="stat-number"><?php echo number_format($stats['total_visits'] ?? 0); ?></div>
                <div class="stat-label">总访问次数</div>
                <div class="stat-sub">今日: <?php echo $today_visits; ?> 次</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #28a745 0%, #218838 100%);">
                    <i class="fas fa-users"></i>
                </div>
                <div class="stat-number"><?php echo number_format($stats['unique_customers'] ?? 0); ?></div>
                <div class="stat-label">独立客户</div>
                <div class="stat-sub">
                    <?php 
                    if (($stats['unique_customers'] ?? 0) > 0) {
                        echo '平均访问: ' . round(($stats['total_visits'] ?? 0) / ($stats['unique_customers'] ?? 1), 1) . '次';
                    } else {
                        echo '暂无数据';
                    }
                    ?>
                </div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                    <i class="fas fa-clock"></i>
                </div>
                <div class="stat-number">
                    <?php 
                    $avg_duration = $stats['avg_duration'] ?? 0;
                    if ($avg_duration < 60) {
                        echo round($avg_duration) . '秒';
                    } else {
                        echo round($avg_duration / 60) . '分';
                    }
                    ?>
                </div>
                <div class="stat-label">平均停留时间</div>
                <div class="stat-sub">最长: <?php echo $stats['max_duration'] ?? 0; ?>秒</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon" style="background: linear-gradient(135deg, #6c757d 0%, #545b62 100%);">
                    <i class="fas fa-chart-pie"></i>
                </div>
                <div class="stat-number">
                    <?php echo number_format($stats['motor_visits'] ?? 0); ?> / <?php echo number_format($stats['car_visits'] ?? 0); ?>
                </div>
                <div class="stat-label">摩托/汽车访问</div>
                <div class="stat-sub">全部价格: <?php echo number_format($stats['all_visits'] ?? 0); ?></div>
            </div>
        </div>

        <!-- 搜索框 -->
        <div class="filter-container">
            <form method="GET" action="history.php" class="row g-3 align-items-center">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control" 
                               placeholder="搜索身份证、姓名或Email..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-filter"></i></span>
                        <select name="page_type" class="form-select">
                            <option value="all" <?php echo $page_type == 'all' ? 'selected' : ''; ?>>所有页面</option>
                            <option value="motor" <?php echo $page_type == 'motor' ? 'selected' : ''; ?>>摩托车</option>
                            <option value="car" <?php echo $page_type == 'car' ? 'selected' : ''; ?>>汽车</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>筛选
                        </button>
                        <?php if ($search || $page_type != 'all' || $date_from || $date_to): ?>
                            <a href="history.php" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>重置
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>

        <!-- 日期筛选 -->
        <div class="filter-container">
            <h5><i class="fas fa-calendar-alt me-2"></i>日期范围筛选</h5>
            <form method="GET" action="history.php" class="row g-3 align-items-center">
                <div class="col-md-3">
                    <label class="form-label">开始日期</label>
                    <input type="date" name="date_from" value="<?php echo htmlspecialchars($date_from); ?>" 
                           class="form-control">
                </div>
                <div class="col-md-3">
                    <label class="form-label">结束日期</label>
                    <input type="date" name="date_to" value="<?php echo htmlspecialchars($date_to); ?>" 
                           class="form-control">
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary mt-4">
                        <i class="fas fa-calendar-check me-2"></i>应用日期范围
                    </button>
                </div>
                <?php if ($search): ?>
                    <input type="hidden" name="search" value="<?php echo htmlspecialchars($search); ?>">
                <?php endif; ?>
                <?php if ($page_type && $page_type != 'all'): ?>
                    <input type="hidden" name="page_type" value="<?php echo htmlspecialchars($page_type); ?>">
                <?php endif; ?>
            </form>
        </div>

        <!-- 数据表格 -->
        <div class="data-table">
            <h4 class="mb-4"><i class="fas fa-table me-2"></i>客户访问记录</h4>
            
            <table id="visitsTable" class="table table-hover">
                <thead>
                    <tr>
                        <th>序号</th>
                        <th>访问时间</th>
                        <th>身份证号码</th>
                        <th>姓名</th>
                        <th>Email</th>
                        <th>页面类型</th>
                        <th>停留时间</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) > 0): ?>
                        <?php foreach ($logs as $index => $log): ?>
                            <?php
                            // 格式化页面类型标签
                            $page_labels = [
                                'motor' => '摩托车',
                                'car' => '汽车',
                                'all' => '全部价格'
                            ];
                            $page_label = $page_labels[$log['page_type']] ?? '未知';
                            
                            // 格式化停留时间
                            $duration = $log['duration_seconds'] ?? 0;
                            if ($duration < 60) {
                                $duration_display = $duration . '秒';
                            } else {
                                $minutes = floor($duration / 60);
                                $seconds = $duration % 60;
                                $duration_display = $minutes . '分' . $seconds . '秒';
                            }
                            
                            // 设置标签样式
                            $badge_class = '';
                            switch ($log['page_type']) {
                                case 'motor': $badge_class = 'badge-motor'; break;
                                case 'car': $badge_class = 'badge-car'; break;
                                case 'all': $badge_class = 'badge-all'; break;
                                default: $badge_class = 'bg-secondary';
                            }
                            ?>
                            <tr>
                                <td><?php echo $index + 1; ?></td>
                                <td>
                                    <?php 
                                    if ($log['access_time']) {
                                        echo date('Y-m-d H:i:s', strtotime($log['access_time']));
                                    } else {
                                        echo 'N/A';
                                    }
                                    ?>
                                </td>
                                <td><code><?php echo htmlspecialchars($log['ic_number'] ?? ''); ?></code></td>
                                <td><strong><?php echo htmlspecialchars($log['name'] ?? ''); ?></strong></td>
                                <td><small><?php echo htmlspecialchars($log['email'] ?? ''); ?></small></td>
                                <td>
                                    <span class="badge <?php echo $badge_class; ?>">
                                        <?php echo $page_label; ?>
                                    </span>
                                </td>
                                <td><?php echo $duration_display; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="7" class="text-center py-5">
                                <div class="text-muted">
                                    <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                                    <p class="mb-0">没有找到访问记录</p>
                                    <small>请确保数据库中已有访问数据</small>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
            
            <!-- 操作按钮 -->
            <div class="mt-4 text-center">
                <a href="history.php" class="btn btn-primary me-2">
                    <i class="fas fa-sync-alt me-2"></i>刷新数据
                </a>
                <button onclick="exportData()" class="btn btn-success">
                    <i class="fas fa-download me-2"></i>导出数据
                </button>
            </div>
        </div>
    </div>

    <!-- JavaScript 库 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        $(document).ready(function() {
            // 初始化DataTable
            $('#visitsTable').DataTable({
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "全部"]],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/zh-HANS.json'
                },
                order: [[1, 'desc']],
                responsive: true
            });
        });
        
        // 导出数据
        function exportData() {
            // 创建CSV数据
            let csv = [];
            let headers = ["序号", "访问时间", "身份证号码", "姓名", "Email", "页面类型", "停留时间"];
            csv.push(headers.join(","));
            
            // 获取表格数据
            $('#visitsTable tbody tr').each(function(index) {
                let row = [];
                $(this).find('td').each(function() {
                    let text = $(this).text().trim();
                    text = text.replace(/<\/?[^>]+(>|$)/g, "");
                    text = text.replace(/,/g, "，");
                    row.push('"' + text + '"');
                });
                csv.push(row.join(","));
            });
            
            // 下载CSV文件
            let csvContent = "data:text/csv;charset=utf-8," + csv.join("\n");
            let encodedUri = encodeURI(csvContent);
            let link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", "访问记录_" + new Date().toISOString().slice(0,10) + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>
</html>