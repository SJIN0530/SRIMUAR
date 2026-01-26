<?php
// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $history_file = 'history.html';
    
    // 创建空的记录文件
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
        .timestamp { font-size: 0.9rem; color: #6c757d; margin-bottom: 10px; }
    </style>
</head>
<body>
    <div class="container">
        <h1><i class="fas fa-user-clock me-2"></i>价格页面访问记录</h1>
        
        <div class="actions">
            <button id="refreshBtn" class="btn btn-primary">
                <i class="fas fa-sync-alt me-2"></i>刷新
            </button>
            <button id="clearAllBtn" class="btn btn-danger ms-2">
                <i class="fas fa-trash-alt me-2"></i>清空所有记录
            </button>
            <a href="index.html" class="btn btn-secondary ms-2">
                <i class="fas fa-home me-2"></i>返回首页
            </a>
        </div>
        
        <div class="timestamp text-muted mb-3">
            最后更新: ' . $current_time . '
        </div>
        
        <div class="table-responsive">
            <table class="table table-hover">
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
                    <!-- 记录将在这里显示 -->
                </tbody>
            </table>
        </div>
        
        <div class="no-records">
            <i class="fas fa-clipboard-list fa-3x mb-3"></i>
            <h4>暂无访问记录</h4>
            <p>当有客户查看价格页面时，记录会显示在这里</p>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            // 刷新按钮
            document.getElementById("refreshBtn").addEventListener("click", function() {
                location.reload();
            });
            
            // 清空所有记录
            document.getElementById("clearAllBtn").addEventListener("click", function() {
                if(confirm("确定要清空所有记录吗？此操作不可撤销！")) {
                    fetch("clear_history.php", {
                        method: "POST"
                    }).then(() => {
                        location.reload();
                    });
                }
            });
        });
    </script>
</body>
</html>';
    
    if (file_put_contents($history_file, $content) !== false) {
        echo '所有记录已清空';
    } else {
        http_response_code(500);
        echo '文件保存失败';
    }
} else {
    http_response_code(405);
    echo '方法不允许';
}
?>