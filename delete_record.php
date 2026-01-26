<?php
// 启用错误报告
error_reporting(E_ALL);
ini_set('display_errors', 1);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $record_id = $_POST['record_id'] ?? '';
    
    if (empty($record_id)) {
        http_response_code(400);
        echo '无效的参数';
        exit();
    }
    
    $history_file = 'history.html';
    
    if (file_exists($history_file)) {
        $content = file_get_contents($history_file);
        
        // 转义特殊字符
        $record_id_escaped = preg_quote($record_id, '/');
        
        // 查找并删除整个tr元素
        $pattern = '/<tr[^>]*id="' . $record_id_escaped . '"[^>]*>.*?<\/tr>\s*/s';
        
        if (preg_match($pattern, $content)) {
            $content = preg_replace($pattern, '', $content);
            
            // 保存文件
            if (file_put_contents($history_file, $content) !== false) {
                echo '记录已删除';
            } else {
                http_response_code(500);
                echo '文件保存失败';
            }
        } else {
            http_response_code(404);
            echo '记录未找到';
        }
    } else {
        http_response_code(404);
        echo '记录文件不存在';
    }
} else {
    http_response_code(405);
    echo '方法不允许';
}
?>