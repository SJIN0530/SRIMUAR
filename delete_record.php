<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_id'])) {
    $record_id = $_POST['record_id'];
    $history_file = 'history.html';
    
    if (file_exists($history_file)) {
        $content = file_get_contents($history_file);
        
        // 查找并删除对应的记录行
        $pattern = '/<tr id=\'' . preg_quote($record_id, '/') . '\'[^>]*>.*?<\/tr>\s*/s';
        $content = preg_replace($pattern, '', $content);
        
        // 清理多余的空行
        $content = preg_replace('/\n\s*\n/', "\n", $content);
        
        file_put_contents($history_file, $content);
        echo '记录已删除';
    }
}
?>