<?php
// history.php
session_start();
require_once 'database_config.php';

// 获取查询参数
$page_type = isset($_GET['page_type']) ? $_GET['page_type'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$record_type = isset($_GET['record_type']) ? $_GET['record_type'] : 'all';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$reg_id = isset($_GET['reg_id']) ? intval($_GET['reg_id']) : 0;

// 获取数据库连接
$conn = Database::getConnection();

// 执照类别映射
$license_classes = [
    'D' => 'D 驾照 (手动挡)',
    'DA' => 'DA 驾照 (自动挡)',
    'B2' => 'B2 驾照 (250cc及以下)',
    'B_Full' => 'B Full 驾照 (不限排量)'
];

// 处理查看详情请求
if ($action == 'view_details' && $reg_id > 0) {
    $sql = "SELECT * FROM student_registrations WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->execute([$reg_id]);
    $registration_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 获取执照类别文字
    if ($registration_details) {
        $registration_details['license_class_text'] = isset($license_classes[$registration_details['license_class']]) 
            ? $license_classes[$registration_details['license_class']] 
            : $registration_details['license_class'];
    }
}

// 构建访问记录查询条件
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
    $params[] = date('Y-m-d', strtotime($date_to . ' +1 day')) . ' 00:00:00';
}

if ($search) {
    $where_conditions[] = "(ic_number LIKE ? OR name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// 获取访问记录统计信息
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

// 获取注册记录统计信息 - 修改为包含执照类别统计
$registration_stats_sql = "SELECT 
                            COUNT(*) as total_registrations,
                            COUNT(DISTINCT ic_number) as unique_registrants,
                            SUM(CASE WHEN vehicle_type = 'car' THEN 1 ELSE 0 END) as car_registrations,
                            SUM(CASE WHEN vehicle_type = 'motor' THEN 1 ELSE 0 END) as motor_registrations,
                            SUM(CASE WHEN has_license = 'yes' THEN 1 ELSE 0 END) as with_license,
                            SUM(CASE WHEN has_license = 'no' THEN 1 ELSE 0 END) as without_license
                          FROM student_registrations";

if ($date_from || $date_to) {
    $reg_where = [];
    $reg_params = [];
    
    if ($date_from) {
        $reg_where[] = "registration_date >= ?";
        $reg_params[] = $date_from . ' 00:00:00';
    }
    
    if ($date_to) {
        $reg_where[] = "registration_date <= ?";
        $reg_params[] = date('Y-m-d', strtotime($date_to . ' +1 day')) . ' 00:00:00';
    }
    
    if (!empty($reg_where)) {
        $registration_stats_sql .= " WHERE " . implode(' AND ', $reg_where);
        $reg_stmt = $conn->prepare($registration_stats_sql);
        $reg_stmt->execute($reg_params);
    } else {
        $reg_stmt = $conn->query($registration_stats_sql);
    }
} else {
    $reg_stmt = $conn->query($registration_stats_sql);
}

$registration_stats = $reg_stmt->fetch(PDO::FETCH_ASSOC);

// 获取今日访问次数
$today_sql = "SELECT COUNT(*) as today_visits FROM price_access_logs WHERE DATE(access_time) = CURDATE()";
$today_stmt = $conn->query($today_sql);
$today_result = $today_stmt->fetch();
$today_visits = $today_result ? $today_result['today_visits'] : 0;

// 获取今日注册次数
$today_reg_sql = "SELECT COUNT(*) as today_registrations FROM student_registrations WHERE DATE(registration_date) = CURDATE()";
$today_reg_stmt = $conn->query($today_reg_sql);
$today_reg_result = $today_reg_stmt->fetch();
$today_registrations = $today_reg_result ? $today_reg_result['today_registrations'] : 0;

// 获取执照类别详细统计
$license_class_stats_sql = "SELECT 
                            license_class,
                            COUNT(*) as count
                           FROM student_registrations
                           GROUP BY license_class";
$license_class_stmt = $conn->query($license_class_stats_sql);
$license_class_stats = $license_class_stmt->fetchAll(PDO::FETCH_ASSOC);

// 计算各类执照的百分比
$total_registrations = $registration_stats['total_registrations'] ?? 0;
$license_percentages = [];
foreach ($license_class_stats as $stat) {
    if ($total_registrations > 0) {
        $percentage = round(($stat['count'] / $total_registrations) * 100, 1);
    } else {
        $percentage = 0;
    }
    $license_percentages[$stat['license_class']] = $percentage;
}

// 获取数据用于表格显示 - 根据记录类型
$logs = [];
$registrations = [];

// 如果是访问记录或全部记录
if ($record_type == 'all' || $record_type == 'visits') {
    $sql = "SELECT *, 'visit' as record_type FROM price_access_logs $where_sql ORDER BY access_time DESC";
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// 如果是注册记录或全部记录
if ($record_type == 'all' || $record_type == 'registrations') {
    $reg_where_conditions = [];
    $reg_params = [];
    
    if ($search) {
        $reg_where_conditions[] = "(ic_number LIKE ? OR name LIKE ? OR phone_number LIKE ?)";
        $reg_params[] = "%$search%";
        $reg_params[] = "%$search%";
        $reg_params[] = "%$search%";
    }
    
    if ($date_from) {
        $reg_where_conditions[] = "registration_date >= ?";
        $reg_params[] = $date_from . ' 00:00:00';
    }
    
    if ($date_to) {
        $reg_where_conditions[] = "registration_date <= ?";
        $reg_params[] = date('Y-m-d', strtotime($date_to . ' +1 day')) . ' 00:00:00';
    }
    
    $reg_where_sql = !empty($reg_where_conditions) ? 'WHERE ' . implode(' AND ', $reg_where_conditions) : '';
    
    $reg_sql = "SELECT *, 'registration' as record_type FROM student_registrations $reg_where_sql ORDER BY registration_date DESC";
    $reg_stmt = $conn->prepare($reg_sql);
    $reg_stmt->execute($reg_params);
    $registrations = $reg_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 添加执照类别文字到注册记录
    foreach ($registrations as &$reg) {
        $reg['license_class_text'] = isset($license_classes[$reg['license_class']]) 
            ? $license_classes[$reg['license_class']] 
            : $reg['license_class'];
    }
}

// 合并记录（如果是全部记录类型）
$all_records = [];
if ($record_type == 'all') {
    $all_records = array_merge($logs, $registrations);
    usort($all_records, function($a, $b) {
        $timeA = $a['record_type'] == 'visit' ? $a['access_time'] : $a['registration_date'];
        $timeB = $b['record_type'] == 'visit' ? $b['access_time'] : $b['registration_date'];
        return strtotime($timeB) - strtotime($timeA);
    });
}
?>

<!DOCTYPE html>
<html lang="zh-MY">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>访问与注册记录 - SRI MUAR 皇城驾驶学院</title>

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
            --success-green: #28a745;
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

        .stat-card.registration {
            border-top-color: var(--success-green);
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

        .stat-icon.registration {
            background: linear-gradient(135deg, var(--success-green) 0%, #218838 100%);
        }

        .stat-number {
            font-size: 2.2rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 5px;
        }

        .stat-number.registration {
            color: var(--success-green);
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

        .badge-visit {
            background: var(--primary-blue);
            color: white;
        }

        .badge-registration {
            background: var(--success-green);
            color: white;
        }

        .badge-license-yes {
            background: #17a2b8;
            color: white;
        }

        .badge-license-no {
            background: #6c757d;
            color: white;
        }

        .badge-license-class {
            background: #6f42c1;
            color: white;
            font-size: 0.75rem;
            margin-top: 3px;
            display: inline-block;
        }

        .badge-license-D {
            background: #6610f2;
            color: white;
        }

        .badge-license-DA {
            background: #e83e8c;
            color: white;
        }

        .badge-license-B2 {
            background: #fd7e14;
            color: white;
        }

        .badge-license-B_Full {
            background: #20c997;
            color: white;
        }

        .filter-container {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
        }

        .record-type-tabs {
            margin-bottom: 20px;
        }

        .record-type-tabs .nav-link {
            font-weight: 500;
            padding: 10px 20px;
            margin-right: 10px;
            border-radius: 25px;
            border: 1px solid #ddd;
            color: #666;
        }

        .record-type-tabs .nav-link.active {
            background: var(--primary-blue);
            color: white;
            border-color: var(--primary-blue);
        }

        .record-type-tabs .nav-link.registration-tab.active {
            background: var(--success-green);
            border-color: var(--success-green);
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

        .record-info {
            padding: 8px;
            margin-bottom: 5px;
            border-radius: 5px;
            border-left: 4px solid var(--primary-blue);
            background: #f8f9fa;
        }

        .record-info.registration {
            border-left-color: var(--success-green);
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
            
            .record-type-tabs .nav-link {
                padding: 8px 15px;
                font-size: 0.9rem;
            }
        }

        .file-link {
            color: var(--primary-blue);
            text-decoration: none;
        }

        .file-link:hover {
            text-decoration: underline;
        }

        .registration-details {
            margin-top: 10px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
            font-size: 0.9rem;
        }

        /* 详情模态框样式 */
        .modal-lg-custom {
            max-width: 90%;
        }

        .detail-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #003d82 100%);
            color: white;
            padding: 20px;
            border-radius: 10px 10px 0 0;
        }

        .detail-body {
            padding: 20px;
        }

        .detail-section {
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #eee;
        }

        .detail-section:last-child {
            border-bottom: none;
        }

        .detail-title {
            color: var(--primary-blue);
            font-weight: 600;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #eee;
        }

        .info-row {
            margin-bottom: 10px;
            display: flex;
        }

        .info-label {
            font-weight: 600;
            min-width: 150px;
            color: #555;
        }

        .info-value {
            color: #333;
            flex: 1;
        }

        .photo-container {
            text-align: center;
            margin-bottom: 15px;
        }

        .photo-container img {
            max-width: 100%;
            max-height: 200px;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 5px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }

        .photo-label {
            margin-top: 5px;
            font-size: 0.9rem;
            color: #666;
        }

        .no-print {
            display: block;
        }

        /* 执照类别筛选器 */
        .license-filter {
            margin-bottom: 15px;
        }

        .license-badge-filter {
            display: inline-flex;
            align-items: center;
            margin-right: 8px;
            margin-bottom: 8px;
            cursor: pointer;
            border: 1px solid #dee2e6;
            border-radius: 20px;
            padding: 4px 12px;
            transition: all 0.3s;
        }

        .license-badge-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .license-badge-filter.active {
            border-width: 2px;
            border-color: #0056b3;
        }

        .license-badge-filter .badge {
            margin-right: 5px;
        }

        /* 执照类别统计进度条 */
        .license-progress {
            height: 8px;
            margin-top: 5px;
            border-radius: 4px;
            overflow: hidden;
        }

        .license-progress-bar {
            height: 100%;
        }

        .license-progress-D { background-color: #6610f2; }
        .license-progress-DA { background-color: #e83e8c; }
        .license-progress-B2 { background-color: #fd7e14; }
        .license-progress-B_Full { background-color: #20c997; }

        /* 打印样式 */
        @media print {
            body * {
                visibility: hidden;
            }
            
            #printContent, #printContent * {
                visibility: visible !important;
            }
            
            #printContent {
                position: absolute !important;
                left: 0 !important;
                top: 0 !important;
                width: 100% !important;
                padding: 20px;
            }
            
            .no-print {
                display: none !important;
            }
            
            .photo-container {
                page-break-inside: avoid;
            }
            
            .photo-container img {
                max-height: 120px;
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
            <h1><i class="fas fa-history me-3"></i>访问与注册记录</h1>
            <p>查看客户访问价格信息和学员注册的详细记录</p>
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
            $reg_count_sql = "SELECT COUNT(*) as total FROM student_registrations";
            $reg_count_stmt = $conn->query($reg_count_sql);
            $reg_count_result = $reg_count_stmt->fetch();
            
            echo ' | 访问记录: ' . ($count_result['total'] ?? 0) . ' 条';
            echo ' | 注册记录: ' . ($reg_count_result['total'] ?? 0) . ' 条';
            
            echo '</div>';
        }
        ?>

        <!-- 记录类型标签 -->
        <div class="filter-container">
            <ul class="nav nav-tabs record-type-tabs" id="recordTypeTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?php echo $record_type == 'all' ? 'active' : ''; ?>" 
                       href="?record_type=all&<?php echo http_build_query(array_merge($_GET, ['record_type' => null])); ?>">
                        <i class="fas fa-layer-group me-2"></i>全部记录
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link <?php echo $record_type == 'visits' ? 'active' : ''; ?>" 
                       href="?record_type=visits&<?php echo http_build_query(array_merge($_GET, ['record_type' => null])); ?>">
                        <i class="fas fa-eye me-2"></i>访问记录
                    </a>
                </li>
                <li class="nav-item" role="presentation">
                    <a class="nav-link registration-tab <?php echo $record_type == 'registrations' ? 'active' : ''; ?>" 
                       href="?record_type=registrations&<?php echo http_build_query(array_merge($_GET, ['record_type' => null])); ?>">
                        <i class="fas fa-user-plus me-2"></i>注册记录
                    </a>
                </li>
            </ul>
        </div>

        <!-- 统计卡片 -->
        <div class="stat-cards">
            <?php if ($record_type == 'all' || $record_type == 'visits'): ?>
            <!-- 访问统计 -->
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
            <?php endif; ?>
            
            <?php if ($record_type == 'all' || $record_type == 'registrations'): ?>
            <!-- 注册统计 -->
            <div class="stat-card registration">
                <div class="stat-icon registration">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="stat-number registration"><?php echo number_format($registration_stats['total_registrations'] ?? 0); ?></div>
                <div class="stat-label">总注册人数</div>
                <div class="stat-sub">今日: <?php echo $today_registrations; ?> 人</div>
            </div>
            
            <div class="stat-card registration">
                <div class="stat-icon registration" style="background: linear-gradient(135deg, #17a2b8 0%, #138496 100%);">
                    <i class="fas fa-id-card"></i>
                </div>
                <div class="stat-number registration"><?php echo number_format($registration_stats['unique_registrants'] ?? 0); ?></div>
                <div class="stat-label">独立注册人</div>
                <div class="stat-sub">
                    <?php 
                    if (($registration_stats['unique_registrants'] ?? 0) > 0) {
                        echo '平均注册: ' . round(($registration_stats['total_registrations'] ?? 0) / ($registration_stats['unique_registrants'] ?? 1), 1) . '门课程';
                    } else {
                        echo '暂无数据';
                    }
                    ?>
                </div>
            </div>
            
            <div class="stat-card registration">
                <div class="stat-icon registration" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
                    <i class="fas fa-car"></i>
                </div>
                <div class="stat-number registration">
                    <?php echo number_format($registration_stats['car_registrations'] ?? 0); ?> / <?php echo number_format($registration_stats['motor_registrations'] ?? 0); ?>
                </div>
                <div class="stat-label">汽车/摩托注册</div>
                <div class="stat-sub">比例: <?php 
                    $total = ($registration_stats['car_registrations'] ?? 0) + ($registration_stats['motor_registrations'] ?? 0);
                    if ($total > 0) {
                        $car_percent = round((($registration_stats['car_registrations'] ?? 0) / $total) * 100);
                        $motor_percent = round((($registration_stats['motor_registrations'] ?? 0) / $total) * 100);
                        echo $car_percent . '% : ' . $motor_percent . '%';
                    } else {
                        echo '0% : 0%';
                    }
                ?></div>
            </div>
            
            <div class="stat-card registration">
                <div class="stat-icon registration" style="background: linear-gradient(135deg, #6610f2 0%, #560bd0 100%);">
                    <i class="fas fa-id-badge"></i>
                </div>
                <div class="stat-number registration">
                    <?php echo number_format($registration_stats['with_license'] ?? 0); ?> / <?php echo number_format($registration_stats['without_license'] ?? 0); ?>
                </div>
                <div class="stat-label">有驾照/无驾照</div>
                <div class="stat-sub">比例: <?php 
                    $total = ($registration_stats['with_license'] ?? 0) + ($registration_stats['without_license'] ?? 0);
                    if ($total > 0) {
                        $with_percent = round((($registration_stats['with_license'] ?? 0) / $total) * 100);
                        $without_percent = round((($registration_stats['without_license'] ?? 0) / $total) * 100);
                        echo $with_percent . '% : ' . $without_percent . '%';
                    } else {
                        echo '0% : 0%';
                    }
                ?></div>
            </div>
            
            <!-- 新增：执照类别统计 -->
            <div class="stat-card registration">
                <div class="stat-icon registration" style="background: linear-gradient(135deg, #20c997 0%, #17a2b8 100%);">
                    <i class="fas fa-graduation-cap"></i>
                </div>
                <div class="stat-number registration">
                    <?php 
                    $d_count = $license_percentages['D'] ?? 0;
                    $da_count = $license_percentages['DA'] ?? 0;
                    $b2_count = $license_percentages['B2'] ?? 0;
                    $bfull_count = $license_percentages['B_Full'] ?? 0;
                    echo round($d_count + $da_count) . '% / ' . round($b2_count + $bfull_count) . '%';
                    ?>
                </div>
                <div class="stat-label">汽车/摩托执照</div>
                <div class="stat-sub">
                    汽车: D(<?php echo $d_count; ?>%) DA(<?php echo $da_count; ?>%)<br>
                    摩托: B2(<?php echo $b2_count; ?>%) B Full(<?php echo $bfull_count; ?>%)
                </div>
            </div>
            
            <?php endif; ?>
        </div>

        <!-- 执照类别筛选器（仅显示在注册记录页面） -->
        <?php if ($record_type == 'all' || $record_type == 'registrations'): ?>
        <div class="filter-container license-filter">
            <h5><i class="fas fa-filter me-2"></i>执照类别筛选</h5>
            <div class="d-flex flex-wrap">
                <div class="license-badge-filter <?php echo (!isset($_GET['license_class']) || $_GET['license_class'] == 'all') ? 'active' : ''; ?>" 
                     onclick="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['license_class' => 'all'])); ?>'">
                    <span class="badge badge-registration">全部</span> 全部执照类别
                </div>
                <div class="license-badge-filter <?php echo (isset($_GET['license_class']) && $_GET['license_class'] == 'D') ? 'active' : ''; ?>" 
                     onclick="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['license_class' => 'D'])); ?>'">
                    <span class="badge badge-license-D">D</span> 汽车手动挡
                </div>
                <div class="license-badge-filter <?php echo (isset($_GET['license_class']) && $_GET['license_class'] == 'DA') ? 'active' : ''; ?>" 
                     onclick="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['license_class' => 'DA'])); ?>'">
                    <span class="badge badge-license-DA">DA</span> 汽车自动挡
                </div>
                <div class="license-badge-filter <?php echo (isset($_GET['license_class']) && $_GET['license_class'] == 'B2') ? 'active' : ''; ?>" 
                     onclick="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['license_class' => 'B2'])); ?>'">
                    <span class="badge badge-license-B2">B2</span> 摩托(250cc)
                </div>
                <div class="license-badge-filter <?php echo (isset($_GET['license_class']) && $_GET['license_class'] == 'B_Full') ? 'active' : ''; ?>" 
                     onclick="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['license_class' => 'B_Full'])); ?>'">
                    <span class="badge badge-license-B_Full">B Full</span> 摩托(不限)
                </div>
            </div>
            
            <!-- 执照类别统计进度条 -->
            <?php if ($total_registrations > 0): ?>
            <div class="mt-3">
                <small class="text-muted d-block mb-2">执照类别分布:</small>
                <div class="license-progress">
                    <?php if (isset($license_percentages['D'])): ?>
                    <div class="license-progress-bar license-progress-D" style="width: <?php echo $license_percentages['D']; ?>%"></div>
                    <?php endif; ?>
                    <?php if (isset($license_percentages['DA'])): ?>
                    <div class="license-progress-bar license-progress-DA" style="width: <?php echo $license_percentages['DA']; ?>%"></div>
                    <?php endif; ?>
                    <?php if (isset($license_percentages['B2'])): ?>
                    <div class="license-progress-bar license-progress-B2" style="width: <?php echo $license_percentages['B2']; ?>%"></div>
                    <?php endif; ?>
                    <?php if (isset($license_percentages['B_Full'])): ?>
                    <div class="license-progress-bar license-progress-B_Full" style="width: <?php echo $license_percentages['B_Full']; ?>%"></div>
                    <?php endif; ?>
                </div>
                <div class="d-flex justify-content-between mt-1">
                    <small class="text-muted">
                        D: <?php echo $license_percentages['D'] ?? 0; ?>% | 
                        DA: <?php echo $license_percentages['DA'] ?? 0; ?>%
                    </small>
                    <small class="text-muted">
                        B2: <?php echo $license_percentages['B2'] ?? 0; ?>% | 
                        B Full: <?php echo $license_percentages['B_Full'] ?? 0; ?>%
                    </small>
                </div>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- 搜索框 -->
        <div class="filter-container">
            <form method="GET" action="history.php" class="row g-3 align-items-center">
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-search"></i></span>
                        <input type="text" name="search" class="form-control" 
                               placeholder="搜索身份证、姓名..." 
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                </div>
                <?php if ($record_type == 'all' || $record_type == 'visits'): ?>
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-filter"></i></span>
                        <select name="page_type" class="form-select">
                            <option value="all" <?php echo $page_type == 'all' ? 'selected' : ''; ?>>所有页面</option>
                            <option value="motor" <?php echo $page_type == 'motor' ? 'selected' : ''; ?>>摩托车</option>
                            <option value="car" <?php echo $page_type == 'car' ? 'selected' : ''; ?>>汽车</option>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>筛选
                        </button>
                        <?php if ($search || $page_type != 'all' || $date_from || $date_to): ?>
                            <a href="history.php?record_type=<?php echo $record_type; ?>" class="btn btn-secondary">
                                <i class="fas fa-times me-2"></i>重置
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <?php if ($record_type): ?>
                    <input type="hidden" name="record_type" value="<?php echo $record_type; ?>">
                <?php endif; ?>
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
                <?php if ($record_type): ?>
                    <input type="hidden" name="record_type" value="<?php echo $record_type; ?>">
                <?php endif; ?>
            </form>
        </div>

        <!-- 数据表格 -->
        <div class="data-table">
            <?php if ($record_type == 'all'): ?>
                <h4 class="mb-4"><i class="fas fa-layer-group me-2"></i>全部记录</h4>
            <?php elseif ($record_type == 'visits'): ?>
                <h4 class="mb-4"><i class="fas fa-eye me-2"></i>访问记录</h4>
            <?php else: ?>
                <h4 class="mb-4"><i class="fas fa-user-plus me-2"></i>学员注册记录</h4>
            <?php endif; ?>
            
            <?php if ($record_type == 'all'): ?>
                <!-- 显示所有记录 -->
                <table id="allRecordsTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th>类型</th>
                            <th>时间</th>
                            <th>身份证号码</th>
                            <th>姓名</th>
                            <th>联系方式</th>
                            <th>详情</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($all_records) > 0): ?>
                            <?php foreach ($all_records as $index => $record): ?>
                                <?php
                                $is_registration = $record['record_type'] == 'registration';
                                ?>
                                <tr>
                                    <td>
                                        <?php if ($is_registration): ?>
                                            <span class="badge badge-registration">
                                                <i class="fas fa-user-plus me-1"></i>注册
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-visit">
                                                <i class="fas fa-eye me-1"></i>访问
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        if ($is_registration) {
                                            echo date('Y-m-d H:i:s', strtotime($record['registration_date']));
                                        } else {
                                            echo date('Y-m-d H:i:s', strtotime($record['access_time']));
                                        }
                                        ?>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($record['ic_number'] ?? ''); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($record['name'] ?? ''); ?></strong></td>
                                    <td>
                                        <?php if ($is_registration): ?>
                                            <small><?php echo htmlspecialchars($record['phone_number'] ?? ''); ?></small>
                                        <?php else: ?>
                                            <small><?php echo htmlspecialchars($record['email'] ?? ''); ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_registration): ?>
                                            <?php
                                            $vehicle_type_text = $record['vehicle_type'] == 'car' ? '汽车' : '摩托车';
                                            $license_text = $record['has_license'] == 'yes' ? '有驾照' : '无驾照';
                                            ?>
                                            <span class="badge <?php echo $record['vehicle_type'] == 'motor' ? 'badge-motor' : 'badge-car'; ?>">
                                                <?php echo $vehicle_type_text; ?>
                                            </span>
                                            <br>
                                            <span class="badge <?php echo $record['has_license'] == 'yes' ? 'badge-license-yes' : 'badge-license-no'; ?>">
                                                <?php echo $license_text; ?>
                                            </span>
                                            <br>
                                            <span class="badge <?php echo 'badge-license-' . $record['license_class']; ?>">
                                                <?php echo $record['license_class_text'] ?? $record['license_class']; ?>
                                            </span>
                                        <?php else: ?>
                                            <?php
                                            $page_labels = [
                                                'motor' => '摩托车',
                                                'car' => '汽车',
                                                'all' => '全部价格'
                                            ];
                                            $page_label = $page_labels[$record['page_type']] ?? '未知';
                                            ?>
                                            <span class="badge <?php echo $record['page_type'] == 'motor' ? 'badge-motor' : ($record['page_type'] == 'car' ? 'badge-car' : 'badge-all'); ?>">
                                                <?php echo $page_label; ?>
                                            </span>
                                            <br>
                                            <small>停留: <?php echo $record['duration_seconds'] ?? 0; ?>秒</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_registration): ?>
                                            <button class="btn btn-sm btn-info view-details-btn" 
                                                    data-reg-id="<?php echo $record['id']; ?>"
                                                    onclick="viewRegistrationDetails(<?php echo $record['id']; ?>)">
                                                <i class="fas fa-eye me-1"></i>查看
                                            </button>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-clipboard-list fa-3x mb-3"></i>
                                        <p class="mb-0">没有找到任何记录</p>
                                        <small>请确保数据库中已有数据</small>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php elseif ($record_type == 'visits'): ?>
                <!-- 显示访问记录 -->
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
                                $page_labels = [
                                    'motor' => '摩托车',
                                    'car' => '汽车',
                                    'all' => '全部价格'
                                ];
                                $page_label = $page_labels[$log['page_type']] ?? '未知';
                                $duration = $log['duration_seconds'] ?? 0;
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <?php 
                                        echo date('Y-m-d H:i:s', strtotime($log['access_time']));
                                        ?>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($log['ic_number'] ?? ''); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($log['name'] ?? ''); ?></strong></td>
                                    <td><small><?php echo htmlspecialchars($log['email'] ?? ''); ?></small></td>
                                    <td>
                                        <span class="badge <?php echo $log['page_type'] == 'motor' ? 'badge-motor' : ($log['page_type'] == 'car' ? 'badge-car' : 'badge-all'); ?>">
                                            <?php echo $page_label; ?>
                                        </span>
                                    </td>
                                    <td><?php echo $duration; ?>秒</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-eye fa-3x mb-3"></i>
                                        <p class="mb-0">没有找到访问记录</p>
                                        <small>请确保数据库中已有访问数据</small>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <!-- 显示注册记录 -->
                <table id="registrationsTable" class="table table-hover">
                    <thead>
                        <tr>
                            <th>注册ID</th>
                            <th>注册时间</th>
                            <th>身份证号码</th>
                            <th>姓名</th>
                            <th>电话号码</th>
                            <th>课程类型</th>
                            <th>执照类别</th>
                            <th>有无驾照</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($registrations) > 0): ?>
                            <?php foreach ($registrations as $index => $reg): ?>
                                <?php
                                $vehicle_type_text = $reg['vehicle_type'] == 'car' ? '汽车' : '摩托车';
                                $license_text = $reg['has_license'] == 'yes' ? '有驾照' : '无驾照';
                                $license_badge = $reg['has_license'] == 'yes' ? 'badge-license-yes' : 'badge-license-no';
                                
                                // 执照类别徽章
                                $license_class_badge = 'badge-license-' . $reg['license_class'];
                                $license_class_text = $reg['license_class_text'] ?? $reg['license_class'];
                                ?>
                                <tr>
                                    <td>
                                        <strong>REG<?php echo str_pad($reg['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                    </td>
                                    <td>
                                        <?php 
                                        echo date('Y-m-d H:i:s', strtotime($reg['registration_date']));
                                        ?>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($reg['ic_number']); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($reg['name']); ?></strong></td>
                                    <td><?php echo htmlspecialchars($reg['phone_number']); ?></td>
                                    <td>
                                        <span class="badge <?php echo $reg['vehicle_type'] == 'motor' ? 'badge-motor' : 'badge-car'; ?>">
                                            <?php echo $vehicle_type_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $license_class_badge; ?>">
                                            <?php echo $license_class_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $license_badge; ?>">
                                            <?php echo $license_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-info view-details-btn" 
                                                data-reg-id="<?php echo $reg['id']; ?>"
                                                onclick="viewRegistrationDetails(<?php echo $reg['id']; ?>)">
                                            <i class="fas fa-eye me-1"></i>查看详情
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5">
                                    <div class="text-muted">
                                        <i class="fas fa-user-plus fa-3x mb-3"></i>
                                        <p class="mb-0">没有找到注册记录</p>
                                        <small>请确保数据库中已有注册数据</small>
                                    </div>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            <?php endif; ?>
            
            <!-- 操作按钮 -->
            <div class="mt-4 text-center">
                <a href="history.php?record_type=<?php echo $record_type; ?>" class="btn btn-primary me-2">
                    <i class="fas fa-sync-alt me-2"></i>刷新数据
                </a>
                <button onclick="exportData()" class="btn btn-success">
                    <i class="fas fa-download me-2"></i>导出数据
                </button>
            </div>
        </div>
    </div>

    <!-- 注册详情模态框 -->
    <?php if (isset($registration_details)): ?>
    <div class="modal fade show" id="registrationModal" tabindex="-1" aria-labelledby="registrationModalLabel" aria-hidden="false" style="display: block; background: rgba(0,0,0,0.5);">
    <?php else: ?>
    <div class="modal fade" id="registrationModal" tabindex="-1" aria-labelledby="registrationModalLabel" aria-hidden="true">
    <?php endif; ?>
        <div class="modal-dialog modal-lg modal-lg-custom">
            <div class="modal-content">
                <div class="detail-header">
                    <h5 class="modal-title" id="registrationModalLabel">
                        <i class="fas fa-user-circle me-2"></i>学员注册详情
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close" onclick="closeModal()"></button>
                </div>
                <div class="modal-body detail-body" id="modalBody">
                    <?php if (isset($registration_details)): ?>
                    <div class="print-content" id="printContent">
                        <!-- 打印按钮 -->
                        <div class="text-end mb-3 no-print">
                            <button class="btn btn-primary" onclick="printRegistration()">
                                <i class="fas fa-print me-2"></i>打印
                            </button>
                            <button class="btn btn-secondary" onclick="closeModal()">
                                <i class="fas fa-times me-2"></i>关闭
                            </button>
                        </div>
                        
                        <!-- 基本信息 -->
                        <div class="detail-section">
                            <h5 class="detail-title">
                                <i class="fas fa-user me-2"></i>基本信息
                            </h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">注册编号:</span>
                                        <span class="info-value">
                                            <strong>REG<?php echo str_pad($registration_details['id'], 6, '0', STR_PAD_LEFT); ?></strong>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">姓名:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($registration_details['name']); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">身份证号码:</span>
                                        <span class="info-value"><code><?php echo htmlspecialchars($registration_details['ic_number']); ?></code></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">电话号码:</span>
                                        <span class="info-value"><?php echo htmlspecialchars($registration_details['phone_number']); ?></span>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">注册时间:</span>
                                        <span class="info-value"><?php echo date('Y-m-d H:i:s', strtotime($registration_details['registration_date'])); ?></span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">课程类型:</span>
                                        <span class="info-value">
                                            <?php 
                                            $vehicle_type_text = $registration_details['vehicle_type'] == 'car' ? '汽车课程' : '摩托车课程';
                                            $badge_class = $registration_details['vehicle_type'] == 'car' ? 'badge-car' : 'badge-motor';
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $vehicle_type_text; ?></span>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">执照类别:</span>
                                        <span class="info-value">
                                            <?php 
                                            $license_badge_class = 'badge-license-' . $registration_details['license_class'];
                                            ?>
                                            <span class="badge <?php echo $license_badge_class; ?>">
                                                <?php echo $registration_details['license_class_text'] ?? $registration_details['license_class']; ?>
                                            </span>
                                        </span>
                                    </div>
                                    <div class="info-row">
                                        <span class="info-label">有无驾照:</span>
                                        <span class="info-value">
                                            <?php 
                                            $license_text = $registration_details['has_license'] == 'yes' ? '有现有驾照 (L牌/P牌)' : '无现有驾照';
                                            $badge_class = $registration_details['has_license'] == 'yes' ? 'badge-license-yes' : 'badge-license-no';
                                            ?>
                                            <span class="badge <?php echo $badge_class; ?>"><?php echo $license_text; ?></span>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 文件信息 -->
                        <div class="detail-section">
                            <h5 class="detail-title">
                                <i class="fas fa-file-alt me-2"></i>上传文件
                            </h5>
                            <div class="row">
                                <!-- 身份证照片 -->
                                <div class="col-md-6">
                                    <h6>身份证照片:</h6>
                                    <div class="row">
                                        <div class="col-6">
                                            <div class="photo-container">
                                                <?php if (file_exists($registration_details['ic_front_path'])): ?>
                                                    <img src="<?php echo htmlspecialchars($registration_details['ic_front_path']); ?>" 
                                                         alt="身份证正面" class="img-thumbnail">
                                                    <div class="photo-label">身份证正面</div>
                                                    <div class="mt-2 no-print">
                                                        <a href="<?php echo htmlspecialchars($registration_details['ic_front_path']); ?>" 
                                                           target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-external-link-alt"></i> 查看原图
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-danger">
                                                        <i class="fas fa-times-circle fa-3x"></i>
                                                        <div class="photo-label">文件未找到</div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-6">
                                            <div class="photo-container">
                                                <?php if (file_exists($registration_details['ic_back_path'])): ?>
                                                    <img src="<?php echo htmlspecialchars($registration_details['ic_back_path']); ?>" 
                                                         alt="身份证背面" class="img-thumbnail">
                                                    <div class="photo-label">身份证背面</div>
                                                    <div class="mt-2 no-print">
                                                        <a href="<?php echo htmlspecialchars($registration_details['ic_back_path']); ?>" 
                                                           target="_blank" class="btn btn-sm btn-outline-primary">
                                                            <i class="fas fa-external-link-alt"></i> 查看原图
                                                        </a>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-danger">
                                                        <i class="fas fa-times-circle fa-3x"></i>
                                                        <div class="photo-label">文件未找到</div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- 驾照照片（如果有） -->
                                <div class="col-md-6">
                                    <?php if ($registration_details['has_license'] == 'yes'): ?>
                                        <h6>驾照照片:</h6>
                                        <div class="row">
                                            <div class="col-6">
                                                <div class="photo-container">
                                                    <?php if (file_exists($registration_details['license_front_path'])): ?>
                                                        <img src="<?php echo htmlspecialchars($registration_details['license_front_path']); ?>" 
                                                             alt="驾照正面" class="img-thumbnail">
                                                        <div class="photo-label">驾照正面</div>
                                                        <div class="mt-2 no-print">
                                                            <a href="<?php echo htmlspecialchars($registration_details['license_front_path']); ?>" 
                                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-external-link-alt"></i> 查看原图
                                                            </a>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-warning">
                                                            <i class="fas fa-exclamation-triangle fa-3x"></i>
                                                            <div class="photo-label">文件未找到</div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-6">
                                                <div class="photo-container">
                                                    <?php if (file_exists($registration_details['license_back_path'])): ?>
                                                        <img src="<?php echo htmlspecialchars($registration_details['license_back_path']); ?>" 
                                                             alt="驾照背面" class="img-thumbnail">
                                                        <div class="photo-label">驾照背面</div>
                                                        <div class="mt-2 no-print">
                                                            <a href="<?php echo htmlspecialchars($registration_details['license_back_path']); ?>" 
                                                               target="_blank" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-external-link-alt"></i> 查看原图
                                                            </a>
                                                        </div>
                                                    <?php else: ?>
                                                        <div class="text-warning">
                                                            <i class="fas fa-exclamation-triangle fa-3x"></i>
                                                            <div class="photo-label">文件未找到</div>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <h6>驾照信息:</h6>
                                        <div class="alert alert-info no-print">
                                            <i class="fas fa-info-circle me-2"></i>
                                            该学员没有现有驾照，无需上传驾照照片。
                                        </div>
                                        <div class="text-muted">
                                            无驾照
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                        
                        <!-- 操作按钮 -->
                        <div class="detail-section no-print">
                            <div class="text-center">
                                <button class="btn btn-primary me-2" onclick="printRegistration()">
                                    <i class="fas fa-print me-2"></i>打印信息
                                </button>
                                <button class="btn btn-success me-2" onclick="downloadAllImages()">
                                    <i class="fas fa-download me-2"></i>下载所有图片
                                </button>
                                <button class="btn btn-secondary" onclick="closeModal()">
                                    <i class="fas fa-times me-2"></i>关闭
                                </button>
                            </div>
                        </div>
                    </div>
                    <?php else: ?>
                        <div class="text-center py-5">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">加载中...</span>
                            </div>
                            <p class="mt-3">正在加载注册信息...</p>
                        </div>
                    <?php endif; ?>
                </div>
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
            $('table').DataTable({
                pageLength: 10,
                lengthMenu: [[10, 25, 50, 100, -1], [10, 25, 50, 100, "全部"]],
                language: {
                    url: 'https://cdn.datatables.net/plug-ins/1.13.6/i18n/zh-HANS.json'
                },
                order: [[1, 'desc']],
                responsive: true,
                columnDefs: [
                    { orderable: false, targets: -1 } // 最后一列不排序（操作列）
                ]
            });
            
            // 如果URL中有查看详情的参数，打开模态框
            <?php if (isset($registration_details)): ?>
                var modal = new bootstrap.Modal(document.getElementById('registrationModal'));
                modal.show();
            <?php endif; ?>
        });
        
        // 查看注册详情
        function viewRegistrationDetails(regId) {
            window.location.href = 'history.php?action=view_details&reg_id=' + regId + '&record_type=<?php echo $record_type; ?>';
        }
        
        // 关闭模态框
        function closeModal() {
            var modal = bootstrap.Modal.getInstance(document.getElementById('registrationModal'));
            if (modal) {
                modal.hide();
            }
            // 移除URL中的查看参数
            var url = new URL(window.location.href);
            url.searchParams.delete('action');
            url.searchParams.delete('reg_id');
            window.history.replaceState({}, '', url);
        }
        
        // 下载所有图片
        function downloadAllImages() {
            <?php if (isset($registration_details)): ?>
                // 创建文件数组
                const files = [];
                
                // 添加身份证图片
                <?php if (file_exists($registration_details['ic_front_path'])): ?>
                    files.push({
                        url: '<?php echo htmlspecialchars($registration_details['ic_front_path']); ?>',
                        name: '身份证正面_<?php echo htmlspecialchars($registration_details['name']); ?>.jpg'
                    });
                <?php endif; ?>
                
                <?php if (file_exists($registration_details['ic_back_path'])): ?>
                    files.push({
                        url: '<?php echo htmlspecialchars($registration_details['ic_back_path']); ?>',
                        name: '身份证背面_<?php echo htmlspecialchars($registration_details['name']); ?>.jpg'
                    });
                <?php endif; ?>
                
                // 添加驾照图片（如果有）
                <?php if ($registration_details['has_license'] == 'yes'): ?>
                    <?php if (file_exists($registration_details['license_front_path'])): ?>
                        files.push({
                            url: '<?php echo htmlspecialchars($registration_details['license_front_path']); ?>',
                            name: '驾照正面_<?php echo htmlspecialchars($registration_details['name']); ?>.jpg'
                        });
                    <?php endif; ?>
                    
                    <?php if (file_exists($registration_details['license_back_path'])): ?>
                        files.push({
                            url: '<?php echo htmlspecialchars($registration_details['license_back_path']); ?>',
                            name: '驾照背面_<?php echo htmlspecialchars($registration_details['name']); ?>.jpg'
                        });
                    <?php endif; ?>
                <?php endif; ?>
                
                // 检查是否有文件
                if (files.length === 0) {
                    alert('没有找到可下载的文件');
                    return;
                }
                
                // 显示确认对话框
                if (confirm('确定要下载 ' + files.length + ' 个文件吗？')) {
                    // 创建下载链接
                    files.forEach((file, index) => {
                        setTimeout(() => {
                            const link = document.createElement('a');
                            link.href = file.url;
                            link.download = file.name;
                            link.target = '_blank';
                            document.body.appendChild(link);
                            link.click();
                            document.body.removeChild(link);
                        }, index * 500); // 延迟下载，避免浏览器阻止
                    });
                    
                    alert('文件下载已开始，请检查浏览器下载列表。');
                }
            <?php endif; ?>
        }
        
        // 打印注册信息
        function printRegistration() {
            // 获取需要打印的内容
            var printContent = document.getElementById('printContent').innerHTML;
            
            // 创建一个新窗口
            var printWindow = window.open('', '_blank', 'width=1000,height=700');
            
            // 写入打印内容
            printWindow.document.write(`
                <!DOCTYPE html>
                <html>
                <head>
                    <title>学员注册信息 - SRI MUAR 皇城驾驶学院</title>
                    <style>
                        body {
                            font-family: Arial, sans-serif;
                            margin: 20px;
                            color: #333;
                        }
                        .print-header {
                            text-align: center;
                            margin-bottom: 20px;
                            padding-bottom: 10px;
                            border-bottom: 2px solid #0056b3;
                        }
                        .print-header h2 {
                            color: #0056b3;
                            margin: 0 0 5px 0;
                            font-size: 20px;
                        }
                        .print-header h3 {
                            margin: 0 0 10px 0;
                            font-size: 16px;
                        }
                        .print-header p {
                            margin: 5px 0;
                            font-size: 14px;
                            color: #666;
                        }
                        .detail-section {
                            margin-bottom: 20px;
                            page-break-inside: avoid;
                        }
                        .detail-title {
                            color: #0056b3;
                            font-weight: bold;
                            border-bottom: 1px solid #ccc;
                            padding-bottom: 5px;
                            margin-bottom: 15px;
                        }
                        .info-row {
                            margin-bottom: 8px;
                            display: flex;
                        }
                        .info-label {
                            font-weight: bold;
                            min-width: 120px;
                        }
                        .row {
                            display: flex;
                            flex-wrap: wrap;
                        }
                        .col-md-6 {
                            width: 50%;
                            box-sizing: border-box;
                        }
                        h6 {
                            margin-top: 15px;
                            margin-bottom: 10px;
                            font-size: 14px;
                            color: #333;
                        }
                        .photo-container {
                            text-align: center;
                            margin-bottom: 15px;
                        }
                        .photo-container img {
                            max-width: 100%;
                            max-height: 150px;
                            border: 1px solid #ddd;
                            border-radius: 5px;
                            padding: 5px;
                            background: white;
                        }
                        .photo-label {
                            margin-top: 5px;
                            font-size: 12px;
                            color: #666;
                        }
                        .badge {
                            display: inline-block;
                            padding: 4px 8px;
                            font-size: 12px;
                            font-weight: bold;
                            border-radius: 15px;
                            margin-right: 5px;
                        }
                        .badge-car {
                            background-color: #dc3545;
                            color: white;
                        }
                        .badge-motor {
                            background-color: #28a745;
                            color: white;
                        }
                        .badge-license-yes {
                            background-color: #17a2b8;
                            color: white;
                        }
                        .badge-license-no {
                            background-color: #6c757d;
                            color: white;
                        }
                        @media print {
                            body {
                                margin: 10px;
                            }
                            .photo-container img {
                                max-height: 120px;
                            }
                        }
                    </style>
                </head>
                <body>
                    <div class="print-header">
                        <h2>SRI MUAR 皇城驾驶学院</h2>
                        <h3>学员注册信息详情</h3>
                        <p>打印时间: ${new Date().toLocaleString()}</p>
                    </div>
                    
                    ${printContent}
                </body>
                </html>
            `);
            
            printWindow.document.close();
            
            // 等待内容加载完成后打印
            setTimeout(function() {
                printWindow.focus();
                printWindow.print();
                printWindow.close();
            }, 1000);
        }
        
        // 导出数据
        function exportData() {
            // 创建CSV数据
            let csv = [];
            let tableId = '';
            
            // 根据当前显示的表确定表头
            if ($('#allRecordsTable').length) {
                tableId = '#allRecordsTable';
                let headers = ["类型", "时间", "身份证号码", "姓名", "联系方式", "详情"];
                csv.push(headers.join(","));
            } else if ($('#visitsTable').length) {
                tableId = '#visitsTable';
                let headers = ["序号", "访问时间", "身份证号码", "姓名", "Email", "页面类型", "停留时间"];
                csv.push(headers.join(","));
            } else if ($('#registrationsTable').length) {
                tableId = '#registrationsTable';
                let headers = ["注册ID", "注册时间", "身份证号码", "姓名", "电话号码", "课程类型", "执照类别", "有无驾照"];
                csv.push(headers.join(","));
            }
            
            // 获取表格数据（排除操作列）
            $(tableId + ' tbody tr').each(function(index) {
                let row = [];
                $(this).find('td').each(function(index, cell) {
                    // 跳过操作列（最后一列）
                    if ($(this).find('.view-details-btn').length === 0) {
                        let text = $(this).text().trim();
                        text = text.replace(/<\/?[^>]+(>|$)/g, "");
                        text = text.replace(/,/g, "，");
                        row.push('"' + text + '"');
                    }
                });
                csv.push(row.join(","));
            });
            
            // 确定文件名
            let filename = "";
            let recordType = "<?php echo $record_type; ?>";
            switch(recordType) {
                case 'all': filename = "全部记录"; break;
                case 'visits': filename = "访问记录"; break;
                case 'registrations': filename = "注册记录"; break;
                default: filename = "记录";
            }
            
            // 下载CSV文件
            let csvContent = "data:text/csv;charset=utf-8,\uFEFF" + csv.join("\n");
            let encodedUri = encodeURI(csvContent);
            let link = document.createElement("a");
            link.setAttribute("href", encodedUri);
            link.setAttribute("download", filename + "_" + new Date().toISOString().slice(0,10) + ".csv");
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // 监听模态框关闭事件
        document.getElementById('registrationModal').addEventListener('hidden.bs.modal', function () {
            closeModal();
        });
    </script>
</body>
</html>