<?php
// history.php
session_start();
require_once 'database_config.php';

// 启用错误报告（开发阶段）
error_reporting(E_ALL);
ini_set('display_errors', 1);

// 获取查询参数
$page_type = isset($_GET['page_type']) ? $_GET['page_type'] : 'all';
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : '';
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';
$record_type = isset($_GET['record_type']) ? $_GET['record_type'] : 'all';
$payment_status_filter = isset($_GET['payment_status']) ? $_GET['payment_status'] : 'all';
$action = isset($_GET['action']) ? $_GET['action'] : '';
$reg_id = isset($_GET['reg_id']) ? intval($_GET['reg_id']) : 0;

// 获取数据库连接
$conn = Database::getConnection();

// 执照类别映射
$license_classes = [
    'D' => 'D 驾照 (手动挡)',
    'DA' => 'DA 驾照 (自动挡)',
    'B2' => 'B2 驾照 (250cc及以下)',
    'B_Full' => 'B Full 驾照 (不限排量)',
    'B_Full_Tambah_kelas' => 'B Full - Tambah kelas (额外课程)'
];

// 支付状态映射
$payment_statuses = [
    'pending' => ['label' => '待支付', 'class' => 'warning'],
    'paid' => ['label' => '已支付', 'class' => 'success'],
    'failed' => ['label' => '支付失败', 'class' => 'danger'],
    'expired' => ['label' => '已过期', 'class' => 'secondary']
];

// 处理查看详情请求
if ($action == 'view_details' && $reg_id > 0) {
    $sql = "SELECT 
                sr.*,
                pr.payment_status as payment_status,
                pr.payment_amount,
                pr.payment_method,
                pr.receipt_path,
                pr.payment_date,
                pr.reference_number as payment_reference_number,
                pr.created_at as payment_created_at,
                pr.expiry_date as payment_expiry_date,
                cp.price as course_price,
                cp.description as course_description
            FROM student_registrations sr
            LEFT JOIN payment_records pr ON sr.payment_reference = pr.reference_number
            LEFT JOIN course_prices cp ON sr.vehicle_type = cp.vehicle_type AND sr.license_class = cp.license_class
            WHERE sr.id = ?";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute([$reg_id]);
    $registration_details = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // 获取执照类别文字
    if ($registration_details) {
        $registration_details['license_class_text'] = isset($license_classes[$registration_details['license_class']]) 
            ? $license_classes[$registration_details['license_class']] 
            : $registration_details['license_class'];
            
        // 获取支付状态文字
        $payment_status = $registration_details['payment_status'] ?? 'pending';
        $registration_details['payment_status_text'] = isset($payment_statuses[$payment_status]) 
            ? $payment_statuses[$payment_status]['label'] 
            : '未知状态';
        $registration_details['payment_status_class'] = isset($payment_statuses[$payment_status]) 
            ? $payment_statuses[$payment_status]['class'] 
            : 'secondary';
    }
}

// ==================== 新添加：检查新注册通知 ====================
// 检查是否有新注册的通知
$last_check_time = isset($_SESSION['last_notification_check']) ? $_SESSION['last_notification_check'] : time();
$current_time = time();

// 查询自上次检查以来的新注册
$new_registrations_sql = "SELECT 
                            sr.id as reg_id,
                            sr.name,
                            sr.ic_number,
                            sr.phone_number,
                            sr.vehicle_type,
                            sr.license_class,
                            sr.registration_date,
                            '新注册' as notification_type
                         FROM student_registrations sr
                         WHERE sr.registration_date > FROM_UNIXTIME(?)
                         ORDER BY sr.registration_date DESC
                         LIMIT 10";

$new_reg_stmt = $conn->prepare($new_registrations_sql);
$new_reg_stmt->execute([$last_check_time]);
$new_registrations = $new_reg_stmt->fetchAll(PDO::FETCH_ASSOC);

// 更新最后检查时间
$_SESSION['last_notification_check'] = $current_time;

// 查询今日注册总数
$today_reg_count_sql = "SELECT COUNT(*) as today_count 
                        FROM student_registrations 
                        WHERE DATE(registration_date) = CURDATE()";
$today_reg_count_stmt = $conn->query($today_reg_count_sql);
$today_reg_count = $today_reg_count_stmt->fetch(PDO::FETCH_ASSOC);
$today_registration_count = $today_reg_count['today_count'] ?? 0;

// 查询最新5个注册
$latest_registrations_sql = "SELECT 
                                sr.id as reg_id,
                                sr.name,
                                sr.ic_number,
                                sr.phone_number,
                                sr.vehicle_type,
                                sr.license_class,
                                sr.registration_date
                             FROM student_registrations sr
                             ORDER BY sr.registration_date DESC
                             LIMIT 5";
$latest_reg_stmt = $conn->query($latest_registrations_sql);
$latest_registrations = $latest_reg_stmt->fetchAll(PDO::FETCH_ASSOC);
// ==================== 新添加结束 ====================

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

// 获取注册记录统计信息 - 包含支付状态统计
$registration_stats_sql = "SELECT 
                            COUNT(*) as total_registrations,
                            COUNT(DISTINCT ic_number) as unique_registrants,
                            SUM(CASE WHEN vehicle_type = 'car' THEN 1 ELSE 0 END) as car_registrations,
                            SUM(CASE WHEN vehicle_type = 'motor' THEN 1 ELSE 0 END) as motor_registrations,
                            SUM(CASE WHEN has_license = 'yes' THEN 1 ELSE 0 END) as with_license,
                            SUM(CASE WHEN has_license = 'no' THEN 1 ELSE 0 END) as without_license,
                            SUM(CASE WHEN sr.payment_status = 'paid' THEN 1 ELSE 0 END) as paid_registrations,
                            SUM(CASE WHEN sr.payment_status = 'pending' THEN 1 ELSE 0 END) as pending_registrations,
                            SUM(CASE WHEN sr.payment_status = 'failed' THEN 1 ELSE 0 END) as failed_registrations
                          FROM student_registrations sr";

$reg_where = [];
$reg_params = [];

if ($date_from) {
    $reg_where[] = "sr.registration_date >= ?";
    $reg_params[] = $date_from . ' 00:00:00';
}

if ($date_to) {
    $reg_where[] = "sr.registration_date <= ?";
    $reg_params[] = date('Y-m-d', strtotime($date_to . ' +1 day')) . ' 00:00:00';
}

if ($payment_status_filter != 'all' && in_array($payment_status_filter, ['pending', 'paid', 'failed', 'expired'])) {
    $reg_where[] = "sr.payment_status = ?";
    $reg_params[] = $payment_status_filter;
}

if (!empty($reg_where)) {
    $registration_stats_sql .= " WHERE " . implode(' AND ', $reg_where);
}

$reg_stmt = $conn->prepare($registration_stats_sql);
$reg_stmt->execute($reg_params);
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

// 获取今日支付完成次数
$today_paid_sql = "SELECT COUNT(*) as today_paid 
                   FROM student_registrations 
                   WHERE DATE(registration_date) = CURDATE() AND payment_status = 'paid'";
$today_paid_stmt = $conn->query($today_paid_sql);
$today_paid_result = $today_paid_stmt->fetch();
$today_paid = $today_paid_result ? $today_paid_result['today_paid'] : 0;

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

// 提取各个执照类别的百分比为单独变量
$d_count = $license_percentages['D'] ?? 0;
$da_count = $license_percentages['DA'] ?? 0;
$b2_count = $license_percentages['B2'] ?? 0;
$bfull_count = $license_percentages['B_Full'] ?? 0;
$bfull_tambah_count = $license_percentages['B_Full_Tambah_kelas'] ?? 0;

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
    $reg_select_params = [];
    
    if ($search) {
        $reg_where_conditions[] = "(sr.ic_number LIKE ? OR sr.name LIKE ? OR sr.phone_number LIKE ?)";
        $reg_select_params[] = "%$search%";
        $reg_select_params[] = "%$search%";
        $reg_select_params[] = "%$search%";
    }
    
    if ($date_from) {
        $reg_where_conditions[] = "sr.registration_date >= ?";
        $reg_select_params[] = $date_from . ' 00:00:00';
    }
    
    if ($date_to) {
        $reg_where_conditions[] = "sr.registration_date <= ?";
        $reg_select_params[] = date('Y-m-d', strtotime($date_to . ' +1 day')) . ' 00:00:00';
    }
    
    if ($payment_status_filter != 'all' && in_array($payment_status_filter, ['pending', 'paid', 'failed', 'expired'])) {
        $reg_where_conditions[] = "sr.payment_status = ?";
        $reg_select_params[] = $payment_status_filter;
    }
    
    $reg_where_sql = !empty($reg_where_conditions) ? 'WHERE ' . implode(' AND ', $reg_where_conditions) : '';
    
    $reg_sql = "SELECT 
                    sr.*,
                    pr.payment_status,
                    pr.payment_amount,
                    pr.payment_method,
                    pr.receipt_path,
                    pr.payment_date,
                    pr.reference_number as payment_reference,
                    pr.expiry_date as payment_expiry_date,
                    'registration' as record_type
                FROM student_registrations sr
                LEFT JOIN payment_records pr ON sr.payment_reference = pr.reference_number
                $reg_where_sql 
                ORDER BY sr.registration_date DESC";
    
    $reg_stmt = $conn->prepare($reg_sql);
    $reg_stmt->execute($reg_select_params);
    $registrations = $reg_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 添加执照类别文字到注册记录
    foreach ($registrations as &$reg) {
        $reg['license_class_text'] = isset($license_classes[$reg['license_class']]) 
            ? $license_classes[$reg['license_class']] 
            : $reg['license_class'];
            
        // 添加支付状态信息
        $payment_status = $reg['payment_status'] ?? 'pending';
        $reg['payment_status_text'] = isset($payment_statuses[$payment_status]) 
            ? $payment_statuses[$payment_status]['label'] 
            : '未知状态';
        $reg['payment_status_class'] = isset($payment_statuses[$payment_status]) 
            ? $payment_statuses[$payment_status]['class'] 
            : 'secondary';
    }
}

// 合并记录（如果是全部记录类型） - 修复这里的问题
$all_records = [];
if ($record_type == 'all') {
    // 首先处理访问记录
    foreach ($logs as $log) {
        // 确保所有必需的字段都存在
        $record = array_merge($log, [
            'record_type' => 'visit',
            'access_time' => $log['access_time'] ?? null,
            'ic_number' => $log['ic_number'] ?? '',
            'name' => $log['name'] ?? '',
            'email' => $log['email'] ?? '',
            'page_type' => $log['page_type'] ?? 'all',
            'duration_seconds' => $log['duration_seconds'] ?? 0
        ]);
        $all_records[] = $record;
    }
    
    // 然后处理注册记录
    foreach ($registrations as $reg) {
        $record = array_merge($reg, [
            'record_type' => 'registration',
            'registration_date' => $reg['registration_date'] ?? null,
            'ic_number' => $reg['ic_number'] ?? '',
            'name' => $reg['name'] ?? '',
            'phone_number' => $reg['phone_number'] ?? '',
            'vehicle_type' => $reg['vehicle_type'] ?? '',
            'license_class' => $reg['license_class'] ?? '',
            'has_license' => $reg['has_license'] ?? 'no',
            'payment_status' => $reg['payment_status'] ?? 'pending'
        ]);
        $all_records[] = $record;
    }
    
    // 按时间排序
    usort($all_records, function($a, $b) {
        $timeA = isset($a['record_type']) && $a['record_type'] == 'visit' 
            ? ($a['access_time'] ?? '1970-01-01 00:00:00') 
            : ($a['registration_date'] ?? '1970-01-01 00:00:00');
            
        $timeB = isset($b['record_type']) && $b['record_type'] == 'visit' 
            ? ($b['access_time'] ?? '1970-01-01 00:00:00') 
            : ($b['registration_date'] ?? '1970-01-01 00:00:00');
            
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
    
    <!-- 新添加：通知提示音 -->
    <audio id="notificationSound" preload="auto">
        <source src="https://assets.mixkit.co/sfx/preview/mixkit-correct-answer-tone-2870.mp3" type="audio/mpeg">
    </audio>
    
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

        .stat-card.payment {
            border-top-color: #ffc107;
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

        .stat-icon.payment {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);
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

        .stat-number.payment {
            color: #ffc107;
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

        .badge-license-B_Full_Tambah_kelas 
        {
            background: #e83e8c;
            color: white;
        }

        .badge-payment {
            padding: 5px 10px;
            font-size: 0.8rem;
            font-weight: bold !important;
        }

        .badge-payment-pending {
            background-color: #ffc107;
            color: #212529;
        }

        .badge-payment-paid {
            background-color: #28a745;
            color: white;
        }

        .badge-payment-failed {
            background-color: #dc3545;
            color: white;
        }

        .badge-payment-expired {
            background-color: #6c757d;
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
            max-width: 95%;
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

        /* 支付状态筛选器 */
        .payment-filter {
            margin-bottom: 15px;
        }

        .payment-badge-filter {
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

        .payment-badge-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .payment-badge-filter.active {
            border-width: 2px;
            border-color: #0056b3;
        }

        .payment-badge-filter .badge {
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
        .license-progress-B_Full_Tambah_kelas { background-color: #e83e8c; }

        /* 支付金额样式 */
        .payment-amount {
            font-weight: bold;
            color: #dc3545;
        }

        .payment-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
            font-size: 0.85rem;
        }

        .payment-reference {
            font-family: monospace;
            color: #0066cc;
        }

        /* 收据查看样式 */
        .receipt-container {
            text-align: center;
            margin-top: 15px;
        }

        .receipt-container img {
            max-width: 100%;
            max-height: 300px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .receipt-actions {
            margin-top: 15px;
        }

        /* ==================== 修改的打印样式 ==================== */
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
                font-size: 14px;
            }
            
            .no-print {
                display: none !important;
            }
            
            .detail-section {
                page-break-inside: avoid;
                margin-bottom: 20px;
                padding-bottom: 15px;
            }
            
            /* 收据部分：整页显示，放得更大 */
            .receipt-section {
                page-break-before: always;
                margin-top: 30px;
            }
            
            .receipt-container {
                text-align: center;
                margin: 20px auto;
                max-width: 100%;
            }
            
            .receipt-container img {
                max-height: 500px !important; /* 收据图片更大 */
                width: auto !important;
                margin: 0 auto;
                display: block;
            }
            
            /* 证件照片部分：IC和驾照照片一起显示 */
            .ic-photos-section,
            .license-photos-section {
                margin-top: 20px;
            }
            
            .ic-photos-row,
            .license-photos-row {
                display: flex;
                justify-content: space-between;
                margin-top: 20px;
            }
            
            .ic-photo-container,
            .license-photo-container {
                width: 48% !important;
                text-align: center;
            }
            
            .ic-photo-container img,
            .license-photo-container img {
                max-height: 400px !important; /* 照片大小统一 */
                width: 100% !important;
                object-fit: contain;
            }
            
            /* 基本信息部分 */
            .basic-info-section {
                font-size: 16px;
            }
            
            .info-row {
                margin-bottom: 8px;
            }
            
            .info-label {
                min-width: 120px;
                font-weight: bold;
            }
            
            .info-value {
                font-weight: normal;
            }
            
            /* 调整边距 */
            .detail-title {
                font-size: 18px;
                padding-bottom: 8px;
                margin-bottom: 10px;
            }
            
            /* 移除不必要的元素 */
            .btn, .badge {
                border: none !important;
                background: none !important;
                color: #000 !important;
                padding: 0 !important;
            }
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

        /* 支付状态筛选器 */
        .payment-filter {
            margin-bottom: 15px;
        }

        .payment-badge-filter {
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

        .payment-badge-filter:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .payment-badge-filter.active {
            border-width: 2px;
            border-color: #0056b3;
        }

        .payment-badge-filter .badge {
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

        /* 支付金额样式 */
        .payment-amount {
            font-weight: bold;
            color: #dc3545;
        }

        .payment-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 5px;
            margin-top: 5px;
            font-size: 0.85rem;
        }

        .payment-reference {
            font-family: monospace;
            color: #0066cc;
        }

        /* 收据查看样式 */
        .receipt-container {
            text-align: center;
            margin-top: 15px;
        }

        .receipt-container img {
            max-width: 100%;
            max-height: 300px;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
        }

        .receipt-actions {
            margin-top: 15px;
        }

        /* 新添加：通知区域样式 */
        .notification-panel {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.05);
            border-left: 5px solid #28a745;
        }

        .notification-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .notification-badge {
            background-color: #dc3545;
            color: white;
            border-radius: 50%;
            width: 24px;
            height: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 12px;
            font-weight: bold;
        }

        .notification-item {
            padding: 10px;
            margin-bottom: 8px;
            background-color: #f8f9fa;
            border-radius: 5px;
            border-left: 3px solid #28a745;
            transition: all 0.3s;
        }

        .notification-item:hover {
            background-color: #e9ecef;
            transform: translateX(5px);
        }

        .notification-time {
            font-size: 0.8rem;
            color: #6c757d;
        }

        .notification-controls {
            display: flex;
            gap: 10px;
            margin-top: 10px;
        }

        .notification-sound-btn {
            padding: 5px 15px;
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            font-size: 0.9rem;
        }

        .notification-sound-btn.active {
            background-color: #28a745;
        }

        /* 新注册弹窗样式 */
        .new-registration-alert {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            max-width: 400px;
            background: linear-gradient(135deg, #28a745 0%, #218838 100%);
            color: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            animation: slideIn 0.5s ease-out;
            border-left: 5px solid #ffc107;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .alert-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .alert-title {
            font-weight: bold;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .alert-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            opacity: 0.8;
        }

        .alert-close:hover {
            opacity: 1;
        }

        .alert-body {
            font-size: 0.95rem;
        }

        .alert-footer {
            margin-top: 10px;
            font-size: 0.85rem;
            opacity: 0.9;
        }

        /* 标题闪烁效果 */
        .title-flash {
            animation: blink 1s infinite;
        }

        @keyframes blink {
            0% { color: #dc3545; }
            50% { color: #0056b3; }
            100% { color: #dc3545; }
        }

        /* 实时更新指示器 */
        .realtime-indicator {
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: #28a745;
            color: white;
            border-radius: 20px;
            padding: 5px 15px;
            font-size: 0.8rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            z-index: 999;
        }

        .realtime-dot {
            display: inline-block;
            width: 8px;
            height: 8px;
            background-color: #ffc107;
            border-radius: 50%;
            animation: pulse 1.5s infinite;
            margin-right: 5px;
        }

        @keyframes pulse {
            0% { transform: scale(0.8); opacity: 0.5; }
            50% { transform: scale(1.2); opacity: 1; }
            100% { transform: scale(0.8); opacity: 0.5; }
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
            <h1 id="pageTitle"><i class="fas fa-history me-3"></i>访问与注册记录</h1>
            <p>查看客户访问价格信息和学员注册的详细记录</p>
        </div>
    </section>

    <!-- 主内容 -->
    <div class="container">
        <!-- 新添加：通知面板 -->
        <div class="notification-panel" id="notificationPanel">
            <div class="notification-header">
                <h5><i class="fas fa-bell me-2"></i>实时通知</h5>
                <div class="notification-controls">
                    <button class="notification-sound-btn" id="toggleSoundBtn" onclick="toggleNotificationSound()">
                        <i class="fas fa-volume-up me-1"></i>声音: <span id="soundStatus">开启</span>
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="clearNotifications()">
                        <i class="fas fa-trash-alt me-1"></i>清空通知
                    </button>
                </div>
            </div>
            
            <!-- 今日统计 -->
            <div class="notification-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <i class="fas fa-calendar-day text-primary me-2"></i>
                        <strong>今日统计:</strong>
                    </div>
                    <div class="notification-badge"><?php echo $today_registration_count; ?></div>
                </div>
                <div class="mt-2">
                    <small class="text-muted">今日已有 <strong><?php echo $today_registration_count; ?></strong> 人注册</small>
                </div>
            </div>
            
            <!-- 最新注册 -->
            <?php if (count($latest_registrations) > 0): ?>
            <div class="notification-item">
                <div>
                    <i class="fas fa-clock text-warning me-2"></i>
                    <strong>最新注册:</strong>
                </div>
                <div class="mt-2">
                    <?php foreach ($latest_registrations as $index => $reg): ?>
                        <?php if ($index < 3): ?>
                        <div class="mb-1">
                            <small>
                                <i class="fas fa-user-circle text-secondary me-1"></i>
                                <?php echo htmlspecialchars($reg['name']); ?> 
                                <span class="badge badge-sm <?php echo $reg['vehicle_type'] == 'car' ? 'badge-car' : 'badge-motor'; ?>">
                                    <?php echo $reg['vehicle_type'] == 'car' ? '汽车' : '摩托'; ?>
                                </span>
                                <span class="text-muted notification-time">
                                    <?php echo date('H:i', strtotime($reg['registration_date'])); ?>
                                </span>
                            </small>
                        </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

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
            
            <div class="stat-card payment">
                <div class="stat-icon payment">
                    <i class="fas fa-credit-card"></i>
                </div>
                <div class="stat-number payment"><?php echo number_format($registration_stats['paid_registrations'] ?? 0); ?></div>
                <div class="stat-label">已支付</div>
                <div class="stat-sub">
                    待支付: <?php echo number_format($registration_stats['pending_registrations'] ?? 0); ?> | 
                    失败: <?php echo number_format($registration_stats['failed_registrations'] ?? 0); ?>
                </div>
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
                    摩托: B2(<?php echo $b2_count; ?>%) B Full(<?php echo $bfull_count; ?>%) B Full - T(<?php echo $bfull_tambah_count; ?>%)
                </div>
            </div>
            
            <?php endif; ?>
        </div>

        <!-- 支付状态筛选器（仅显示在注册记录页面） -->
        <?php if ($record_type == 'all' || $record_type == 'registrations'): ?>
        <div class="filter-container payment-filter">
            <h5><i class="fas fa-filter me-2"></i>支付状态筛选</h5>
            <div class="d-flex flex-wrap">
                <div class="payment-badge-filter <?php echo ($payment_status_filter == 'all') ? 'active' : ''; ?>" 
                     onclick="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['payment_status' => 'all'])); ?>'">
                    <span class="badge badge-registration">全部</span> 全部支付状态
                </div>
                <div class="payment-badge-filter <?php echo ($payment_status_filter == 'paid') ? 'active' : ''; ?>" 
                     onclick="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['payment_status' => 'paid'])); ?>'">
                    <span class="badge badge-payment-paid">已支付</span> 已支付
                </div>
                <div class="payment-badge-filter <?php echo ($payment_status_filter == 'pending') ? 'active' : ''; ?>" 
                     onclick="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['payment_status' => 'pending'])); ?>'">
                    <span class="badge badge-payment-pending">待支付</span> 待支付
                </div>
                <div class="payment-badge-filter <?php echo ($payment_status_filter == 'failed') ? 'active' : ''; ?>" 
                     onclick="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['payment_status' => 'failed'])); ?>'">
                    <span class="badge badge-payment-failed">失败</span> 支付失败
                </div>
                <div class="payment-badge-filter <?php echo ($payment_status_filter == 'expired') ? 'active' : ''; ?>" 
                     onclick="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['payment_status' => 'expired'])); ?>'">
                    <span class="badge badge-payment-expired">过期</span> 已过期
                </div>
            </div>
        </div>
        <?php endif; ?>

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
                <div class="license-badge-filter <?php echo (isset($_GET['license_class']) && $_GET['license_class'] == 'B_Full_Tambah_kelas') ? 'active' : ''; ?>" 
                    onclick="window.location.href='?<?php echo http_build_query(array_merge($_GET, ['license_class' => 'B_Full_Tambah_kelas'])); ?>'">
                    <span class="badge badge-license-B_Full_Tambah_kelas">B Full - T</span> B Full - Tambah kelas
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
                    <?php if (isset($license_percentages['B_Full_Tambah_kelas'])): ?>
                    <div class="license-progress-bar license-progress-B_Full_Tambah_kelas" style="width: <?php echo $license_percentages['B_Full_Tambah_kelas']; ?>%"></div>
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
                        B Full - T: <?php echo $license_percentages['B_Full_Tambah_kelas'] ?? 0; ?>%
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
                <?php if ($record_type == 'all' || $record_type == 'registrations'): ?>
                <div class="col-md-3">
                    <div class="input-group">
                        <span class="input-group-text"><i class="fas fa-credit-card"></i></span>
                        <select name="payment_status" class="form-select">
                            <option value="all" <?php echo $payment_status_filter == 'all' ? 'selected' : ''; ?>>所有支付状态</option>
                            <option value="paid" <?php echo $payment_status_filter == 'paid' ? 'selected' : ''; ?>>已支付</option>
                            <option value="pending" <?php echo $payment_status_filter == 'pending' ? 'selected' : ''; ?>>待支付</option>
                            <option value="failed" <?php echo $payment_status_filter == 'failed' ? 'selected' : ''; ?>>支付失败</option>
                            <option value="expired" <?php echo $payment_status_filter == 'expired' ? 'selected' : ''; ?>>已过期</option>
                        </select>
                    </div>
                </div>
                <?php endif; ?>
                <div class="col-md-3">
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-filter me-2"></i>筛选
                        </button>
                        <?php if ($search || $page_type != 'all' || $date_from || $date_to || $payment_status_filter != 'all'): ?>
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
                <?php if ($payment_status_filter != 'all'): ?>
                    <input type="hidden" name="payment_status" value="<?php echo htmlspecialchars($payment_status_filter); ?>">
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
                            <th>支付状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($all_records) > 0): ?>
                            <?php foreach ($all_records as $index => $record): ?>
                                <?php
                                $is_registration = isset($record['record_type']) && $record['record_type'] == 'registration';
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
                                            echo date('Y-m-d H:i:s', strtotime($record['registration_date'] ?? '1970-01-01 00:00:00'));
                                        } else {
                                            echo date('Y-m-d H:i:s', strtotime($record['access_time'] ?? '1970-01-01 00:00:00'));
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
                                            $vehicle_type_text = isset($record['vehicle_type']) && $record['vehicle_type'] == 'car' ? '汽车' : '摩托车';
                                            $license_text = isset($record['has_license']) && $record['has_license'] == 'yes' ? '有驾照' : '无驾照';
                                            ?>
                                            <span class="badge <?php echo (isset($record['vehicle_type']) && $record['vehicle_type'] == 'motor') ? 'badge-motor' : 'badge-car'; ?>">
                                                <?php echo $vehicle_type_text; ?>
                                            </span>
                                            <br>
                                            <span class="badge <?php echo (isset($record['has_license']) && $record['has_license'] == 'yes') ? 'badge-license-yes' : 'badge-license-no'; ?>">
                                                <?php echo $license_text; ?>
                                            </span>
                                            <br>
                                            <?php if (isset($record['license_class'])): ?>
                                            <span class="badge <?php echo 'badge-license-' . $record['license_class']; ?>">
                                                <?php echo isset($record['license_class_text']) ? $record['license_class_text'] : $record['license_class']; ?>
                                            </span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <?php
                                            $page_labels = [
                                                'motor' => '摩托车',
                                                'car' => '汽车',
                                                'all' => '全部价格'
                                            ];
                                            $page_label = isset($record['page_type']) ? ($page_labels[$record['page_type']] ?? '未知') : '未知';
                                            ?>
                                            <span class="badge <?php 
                                                echo (isset($record['page_type']) && $record['page_type'] == 'motor') ? 'badge-motor' : 
                                                     ((isset($record['page_type']) && $record['page_type'] == 'car') ? 'badge-car' : 'badge-all'); ?>">
                                                <?php echo $page_label; ?>
                                            </span>
                                            <br>
                                            <small>停留: <?php echo $record['duration_seconds'] ?? 0; ?>秒</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_registration): ?>
                                            <?php if (isset($record['payment_status_class']) && isset($record['payment_status_text'])): ?>
                                            <span class="badge badge-payment badge-payment-<?php echo $record['payment_status_class']; ?>">
                                                <?php echo $record['payment_status_text']; ?>
                                            </span>
                                            <?php endif; ?>
                                            <?php if (isset($record['payment_amount'])): ?>
                                                <br>
                                                <small>RM <?php echo number_format($record['payment_amount'], 2); ?></small>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_registration && isset($record['id'])): ?>
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
                                <td colspan="8" class="text-center py-5">
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
                                $page_label = isset($log['page_type']) ? ($page_labels[$log['page_type']] ?? '未知') : '未知';
                                $duration = $log['duration_seconds'] ?? 0;
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <?php 
                                        echo date('Y-m-d H:i:s', strtotime($log['access_time'] ?? '1970-01-01 00:00:00'));
                                        ?>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($log['ic_number'] ?? ''); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($log['name'] ?? ''); ?></strong></td>
                                    <td><small><?php echo htmlspecialchars($log['email'] ?? ''); ?></small></td>
                                    <td>
                                        <span class="badge <?php 
                                            echo (isset($log['page_type']) && $log['page_type'] == 'motor') ? 'badge-motor' : 
                                                 ((isset($log['page_type']) && $log['page_type'] == 'car') ? 'badge-car' : 'badge-all'); ?>">
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
                            <th>支付状态</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($registrations) > 0): ?>
                            <?php foreach ($registrations as $index => $reg): ?>
                                <?php
                                $vehicle_type_text = isset($reg['vehicle_type']) && $reg['vehicle_type'] == 'car' ? '汽车' : '摩托车';
                                $license_text = isset($reg['has_license']) && $reg['has_license'] == 'yes' ? '有驾照' : '无驾照';
                                $license_badge = isset($reg['has_license']) && $reg['has_license'] == 'yes' ? 'badge-license-yes' : 'badge-license-no';
                                
                                // 执照类别徽章
                                $license_class_badge = isset($reg['license_class']) ? 'badge-license-' . $reg['license_class'] : '';
                                $license_class_text = $reg['license_class_text'] ?? ($reg['license_class'] ?? '');
                                
                                // 支付状态信息
                                $payment_status_class = isset($reg['payment_status_class']) ? 'badge-payment-' . $reg['payment_status_class'] : '';
                                ?>
                                <tr>
                                    <td>
                                        <strong>REG<?php echo isset($reg['id']) ? str_pad($reg['id'], 6, '0', STR_PAD_LEFT) : ''; ?></strong>
                                    </td>
                                    <td>
                                        <?php 
                                        echo date('Y-m-d H:i:s', strtotime($reg['registration_date'] ?? '1970-01-01 00:00:00'));
                                        ?>
                                    </td>
                                    <td><code><?php echo htmlspecialchars($reg['ic_number'] ?? ''); ?></code></td>
                                    <td><strong><?php echo htmlspecialchars($reg['name'] ?? ''); ?></strong></td>
                                    <td><?php echo htmlspecialchars($reg['phone_number'] ?? ''); ?></td>
                                    <td>
                                        <span class="badge <?php echo (isset($reg['vehicle_type']) && $reg['vehicle_type'] == 'motor') ? 'badge-motor' : 'badge-car'; ?>">
                                            <?php echo $vehicle_type_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($license_class_badge) && !empty($license_class_text)): ?>
                                        <span class="badge <?php echo $license_class_badge; ?>">
                                            <?php echo $license_class_text; ?>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $license_badge; ?>">
                                            <?php echo $license_text; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (isset($reg['payment_status_text']) && !empty($payment_status_class)): ?>
                                        <span class="badge badge-payment <?php echo $payment_status_class; ?>">
                                            <?php echo $reg['payment_status_text']; ?>
                                        </span>
                                        <?php endif; ?>
                                        <?php if (isset($reg['payment_amount'])): ?>
                                            <div class="payment-amount">
                                                RM <?php echo number_format($reg['payment_amount'], 2); ?>
                                            </div>
                                            <?php if (isset($reg['payment_reference'])): ?>
                                                <small class="text-muted">参考号: <?php echo $reg['payment_reference']; ?></small>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if (isset($reg['id'])): ?>
                                        <button class="btn btn-sm btn-info view-details-btn" 
                                                data-reg-id="<?php echo $reg['id']; ?>"
                                                onclick="viewRegistrationDetails(<?php echo $reg['id']; ?>)">
                                            <i class="fas fa-eye me-1"></i>查看详情
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="10" class="text-center py-5">
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
                        <div class="detail-section basic-info-section">
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
                        
                        <!-- 支付信息 -->
                        <div class="detail-section">
                            <h5 class="detail-title">
                                <i class="fas fa-credit-card me-2"></i>支付信息
                            </h5>
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="info-row">
                                        <span class="info-label">支付状态:</span>
                                        <span class="info-value">
                                            <span class="badge badge-payment badge-payment-<?php echo $registration_details['payment_status_class']; ?>">
                                                <?php echo $registration_details['payment_status_text']; ?>
                                            </span>
                                        </span>
                                    </div>
                                    <?php if ($registration_details['payment_amount']): ?>
                                    <div class="info-row">
                                        <span class="info-label">支付金额:</span>
                                        <span class="info-value payment-amount">
                                            RM <?php echo number_format($registration_details['payment_amount'], 2); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($registration_details['course_description']): ?>
                                    <div class="info-row">
                                        <span class="info-label">课程费用:</span>
                                        <span class="info-value">
                                            <?php echo htmlspecialchars($registration_details['course_description']); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($registration_details['payment_method']): ?>
                                    <div class="info-row">
                                        <span class="info-label">支付方式:</span>
                                        <span class="info-value">
                                            <?php echo htmlspecialchars($registration_details['payment_method']); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-6">
                                    <?php if ($registration_details['payment_reference_number']): ?>
                                    <div class="info-row">
                                        <span class="info-label">支付参考号:</span>
                                        <span class="info-value payment-reference">
                                            <?php echo htmlspecialchars($registration_details['payment_reference_number']); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($registration_details['payment_date']): ?>
                                    <div class="info-row">
                                        <span class="info-label">支付时间:</span>
                                        <span class="info-value">
                                            <?php echo date('Y-m-d H:i:s', strtotime($registration_details['payment_date'])); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($registration_details['payment_created_at']): ?>
                                    <div class="info-row">
                                        <span class="info-label">创建时间:</span>
                                        <span class="info-value">
                                            <?php echo date('Y-m-d H:i:s', strtotime($registration_details['payment_created_at'])); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if ($registration_details['payment_expiry_date']): ?>
                                    <div class="info-row">
                                        <span class="info-label">过期时间:</span>
                                        <span class="info-value">
                                            <?php echo date('Y-m-d H:i:s', strtotime($registration_details['payment_expiry_date'])); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- 收据查看（如果有） -->
                            <?php if ($registration_details['receipt_path'] && file_exists($registration_details['receipt_path'])): ?>
                            <div class="receipt-container mt-3 receipt-section">
                                <h6><i class="fas fa-receipt me-2"></i>支付收据</h6>
                                <img src="<?php echo htmlspecialchars($registration_details['receipt_path']); ?>" 
                                     alt="支付收据" class="img-thumbnail">
                                <div class="receipt-actions no-print">
                                    <a href="<?php echo htmlspecialchars($registration_details['receipt_path']); ?>" 
                                       target="_blank" class="btn btn-sm btn-outline-primary me-2">
                                        <i class="fas fa-external-link-alt"></i> 查看原图
                                    </a>
                                    <a href="<?php echo htmlspecialchars($registration_details['receipt_path']); ?>" 
                                       download="收据_<?php echo htmlspecialchars($registration_details['name']); ?>.jpg" 
                                       class="btn btn-sm btn-outline-success">
                                        <i class="fas fa-download"></i> 下载收据
                                    </a>
                                </div>
                            </div>
                            <?php elseif ($registration_details['payment_status'] == 'paid'): ?>
                                <div class="alert alert-warning mt-3">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    此注册标记为已支付，但未找到收据文件。
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- 文件信息 -->
                        <div class="detail-section">
                            <h5 class="detail-title">
                                <i class="fas fa-file-image me-2"></i>证件照片
                            </h5>
                            
                            <!-- IC照片部分 -->
                            <div class="ic-photos-section">
                                <h6 class="mt-3 mb-3">
                                    <i class="fas fa-id-card me-2"></i>身份证照片
                                </h6>
                                <div class="row ic-photos-row">
                                    <!-- 身份证正面 -->
                                    <div class="col-md-6 ic-photo-container">
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
                                    <!-- 身份证背面 -->
                                    <div class="col-md-6 ic-photo-container">
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
                            
                            <!-- 驾照照片部分（如果有） -->
                            <?php if ($registration_details['has_license'] == 'yes'): ?>
                            <div class="license-photos-section mt-4">
                                <h6 class="mt-3 mb-3">
                                    <i class="fas fa-id-card me-2"></i>驾照照片
                                </h6>
                                <div class="row license-photos-row">
                                    <!-- 驾照正面 -->
                                    <div class="col-md-6 license-photo-container">
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
                                    <!-- 驾照背面 -->
                                    <div class="col-md-6 license-photo-container">
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
                            </div>
                            <?php else: ?>
                                <div class="alert alert-info no-print mt-4">
                                    <i class="fas fa-info-circle me-2"></i>
                                    该学员没有现有驾照，无需上传驾照照片。
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- 操作按钮 -->
                        <div class="detail-section no-print">
                            <div class="text-center">
                                <button class="btn btn-primary me-2" onclick="printRegistration()">
                                    <i class="fas fa-print me-2"></i>打印信息
                                </button>
                                <button class="btn btn-success me-2" onclick="downloadAllImages()">
                                    <i class="fas fa-download me-2"></i>下载所有文件
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

    <!-- 实时更新指示器 -->
    <div class="realtime-indicator" id="realtimeIndicator">
        <span class="realtime-dot"></span> 实时更新中...
    </div>

    <!-- JavaScript 库 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // ==================== 新添加：通知系统 ====================
        let notificationSoundEnabled = true;
        let notificationCheckInterval = null;
        let titleFlashInterval = null;
        let originalTitle = document.title;
        let hasNewRegistration = false;
        
        // 初始化通知系统
        function initNotificationSystem() {
            // 请求通知权限（如果浏览器支持）
            if ("Notification" in window) {
                if (Notification.permission === "default") {
                    Notification.requestPermission();
                }
            }
            
            // 检查是否有新注册（PHP端已处理）
            <?php if (count($new_registrations) > 0): ?>
                showNewRegistrationNotification();
            <?php endif; ?>
            
            // 开始定时检查新注册
            startNotificationChecker();
            
            // 检查页面是否在前台
            document.addEventListener('visibilitychange', function() {
                if (!document.hidden) {
                    // 页面变为可见时，停止标题闪烁
                    stopTitleFlash();
                }
            });
        }
        
        // 显示新注册通知
        function showNewRegistrationNotification() {
            <?php foreach ($new_registrations as $reg): ?>
                const alertDiv = document.createElement('div');
                alertDiv.className = 'new-registration-alert';
                alertDiv.innerHTML = `
                    <div class="alert-header">
                        <div class="alert-title">
                            <i class="fas fa-user-plus"></i>
                            新学员注册!
                        </div>
                        <button class="alert-close" onclick="this.parentElement.parentElement.remove()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                    <div class="alert-body">
                        <strong>${<?php echo json_encode($reg['name']); ?>}</strong> 刚刚注册了
                        <span class="badge ${<?php echo $reg['vehicle_type'] == 'car' ? "'badge-car'" : "'badge-motor'"; ?>}">
                            ${<?php echo $reg['vehicle_type'] == 'car' ? "'汽车'" : "'摩托'"; ?>}
                        </span>
                        课程
                    </div>
                    <div class="alert-footer">
                        <i class="fas fa-clock"></i> ${<?php echo json_encode(date('H:i', strtotime($reg['registration_date']))); ?>}
                        <button class="btn btn-sm btn-outline-light ms-2" onclick="viewRegistrationDetails(${<?php echo $reg['reg_id']; ?>}); this.parentElement.parentElement.parentElement.remove()">
                            <i class="fas fa-eye"></i> 查看详情
                        </button>
                    </div>
                `;
                
                document.body.appendChild(alertDiv);
                
                // 5秒后自动移除通知
                setTimeout(() => {
                    if (alertDiv.parentNode) {
                        alertDiv.remove();
                    }
                }, 5000);
            <?php endforeach; ?>
            
            // 播放通知声音
            if (notificationSoundEnabled) {
                playNotificationSound();
            }
            
            // 如果有新注册，闪烁标题
            hasNewRegistration = true;
            startTitleFlash();
            
            // 发送桌面通知（如果允许）
            sendDesktopNotification();
        }
        
        // 播放通知声音
        function playNotificationSound() {
            const sound = document.getElementById('notificationSound');
            if (sound) {
                sound.currentTime = 0;
                sound.play().catch(e => console.log("声音播放失败:", e));
            }
        }
        
        // 发送桌面通知
        function sendDesktopNotification() {
            if (!("Notification" in window)) return;
            
            if (Notification.permission === "granted") {
                const notification = new Notification("新学员注册!", {
                    body: "有新的学员注册了您的课程，请查看详情。",
                    icon: "https://cdn-icons-png.flaticon.com/512/3135/3135715.png",
                    tag: "new-registration"
                });
                
                notification.onclick = function() {
                    window.focus();
                    notification.close();
                };
            }
        }
        
        // 开始标题闪烁
        function startTitleFlash() {
            if (titleFlashInterval) clearInterval(titleFlashInterval);
            
            let isOriginal = true;
            titleFlashInterval = setInterval(() => {
                if (document.hidden) {
                    document.title = isOriginal ? 
                        "【新注册!】访问与注册记录 - SRI MUAR" : 
                        originalTitle;
                    isOriginal = !isOriginal;
                }
            }, 1000);
        }
        
        // 停止标题闪烁
        function stopTitleFlash() {
            if (titleFlashInterval) {
                clearInterval(titleFlashInterval);
                titleFlashInterval = null;
                document.title = originalTitle;
                hasNewRegistration = false;
            }
        }
        
        // 开始定时检查新注册
        function startNotificationChecker() {
            // 每30秒检查一次新注册
            notificationCheckInterval = setInterval(checkNewRegistrations, 30000);
        }
        
        // 检查新注册（AJAX请求）
        function checkNewRegistrations() {
            // 更新实时指示器状态
            updateRealtimeIndicator();
            
            $.ajax({
                url: 'check_new_registrations.php',
                type: 'GET',
                data: {
                    last_check: <?php echo $last_check_time; ?>
                },
                success: function(response) {
                    if (response.new_count > 0) {
                        // 有新注册，显示通知
                        showNewRegistrationNotification();
                    }
                },
                error: function() {
                    console.log("检查新注册失败");
                }
            });
        }
        
        // 更新实时指示器
        function updateRealtimeIndicator() {
            const indicator = document.getElementById('realtimeIndicator');
            if (indicator) {
                indicator.style.backgroundColor = '#28a745';
                setTimeout(() => {
                    indicator.style.backgroundColor = '#6c757d';
                }, 1000);
            }
        }
        
        // 切换通知声音
        function toggleNotificationSound() {
            notificationSoundEnabled = !notificationSoundEnabled;
            const btn = document.getElementById('toggleSoundBtn');
            const status = document.getElementById('soundStatus');
            
            if (notificationSoundEnabled) {
                btn.classList.add('active');
                status.textContent = '开启';
            } else {
                btn.classList.remove('active');
                status.textContent = '关闭';
            }
            
            // 保存设置到localStorage
            localStorage.setItem('notificationSound', notificationSoundEnabled);
        }
        
        // 清空通知
        function clearNotifications() {
            // 移除所有通知弹窗
            document.querySelectorAll('.new-registration-alert').forEach(alert => alert.remove());
            // 停止标题闪烁
            stopTitleFlash();
        }
        
        // ==================== 原有功能 ====================
        
        $(document).ready(function() {
            // 初始化通知系统
            initNotificationSystem();
            
            // 恢复通知声音设置
            const savedSoundSetting = localStorage.getItem('notificationSound');
            if (savedSoundSetting !== null) {
                notificationSoundEnabled = savedSoundSetting === 'true';
                const btn = document.getElementById('toggleSoundBtn');
                const status = document.getElementById('soundStatus');
                
                if (notificationSoundEnabled) {
                    btn.classList.add('active');
                    status.textContent = '开启';
                } else {
                    btn.classList.remove('active');
                    status.textContent = '关闭';
                }
            }
            
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
        
        // 下载所有文件
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
                
                // 添加收据图片（如果有）
                <?php if ($registration_details['receipt_path'] && file_exists($registration_details['receipt_path'])): ?>
                    files.push({
                        url: '<?php echo htmlspecialchars($registration_details['receipt_path']); ?>',
                        name: '支付收据_<?php echo htmlspecialchars($registration_details['name']); ?>.jpg'
                    });
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
                            font-size: 14px;
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
                            font-size: 16px;
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
                        .basic-info-section {
                            font-size: 16px;
                        }
                        h6 {
                            margin-top: 15px;
                            margin-bottom: 10px;
                            font-size: 14px;
                            color: #333;
                        }
                        
                        /* 收据部分样式 */
                        .receipt-section {
                            page-break-before: always;
                            text-align: center;
                            margin: 30px 0;
                        }
                        
                        .receipt-container img {
                            max-height: 500px !important;
                            width: auto !important;
                            margin: 0 auto;
                            display: block;
                        }
                        
                        /* 证件照片部分样式 */
                        .ic-photos-section,
                        .license-photos-section {
                            margin-top: 20px;
                        }
                        
                        .ic-photos-row,
                        .license-photos-row {
                            display: flex;
                            justify-content: space-between;
                            margin-top: 20px;
                        }
                        
                        .ic-photo-container,
                        .license-photo-container {
                            width: 48% !important;
                            text-align: center;
                        }
                        
                        .ic-photo-container img,
                        .license-photo-container img {
                            max-height: 400px !important;
                            width: 100% !important;
                            object-fit: contain;
                        }
                        
                        .photo-label {
                            margin-top: 10px;
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
                        .payment-amount {
                            font-weight: bold;
                            color: #dc3545;
                        }
                        
                        @media print {
                            body {
                                margin: 10px;
                            }
                            .detail-section {
                                margin-bottom: 15px;
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
                let headers = ["类型", "时间", "身份证号码", "姓名", "联系方式", "详情", "支付状态"];
                csv.push(headers.join(","));
            } else if ($('#visitsTable').length) {
                tableId = '#visitsTable';
                let headers = ["序号", "访问时间", "身份证号码", "姓名", "Email", "页面类型", "停留时间"];
                csv.push(headers.join(","));
            } else if ($('#registrationsTable').length) {
                tableId = '#registrationsTable';
                let headers = ["注册ID", "注册时间", "身份证号码", "姓名", "电话号码", "课程类型", "执照类别", "有无驾照", "支付状态", "支付金额", "支付参考号"];
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
        
        // 页面卸载时清理定时器
        window.addEventListener('beforeunload', function() {
            if (notificationCheckInterval) clearInterval(notificationCheckInterval);
            if (titleFlashInterval) clearInterval(titleFlashInterval);
        });
    </script>
</body>
</html>