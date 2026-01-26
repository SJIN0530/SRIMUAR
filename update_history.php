<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['record_id']) && isset($_POST['duration'])) {
    $record_id = $_POST['record_id'];
    $duration = intval($_POST['duration']);
    
    $history_file = 'history.html';
    
    if (file_exists($history_file)) {
        $content = file_get_contents($history_file);
        
        // 格式化停留时间
        if ($duration < 60) {
            $duration_display = $duration . '秒';
        } else {
            $minutes = floor($duration / 60);
            $seconds = $duration % 60;
            $duration_display = $minutes . '分' . $seconds . '秒';
        }
        
        // 查找并替换停留时间
        $pattern = '/<span class=\'duration\' data-record=\'' . preg_quote($record_id, '/') . '\'[^>]*>正在查看\.\.\.<\/span>/';
        $replacement = '<span class=\'duration\' data-record=\'' . $record_id . '\' data-start="0">' . $duration_display . '</span>';
        
        $content = preg_replace($pattern, $replacement, $content);
        
        // 保存文件
        file_put_contents($history_file, $content);
        
        echo '停留时间已更新';
    }
}
?>