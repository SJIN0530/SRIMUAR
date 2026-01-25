<?php
// verify_code.php - 输入验证码页面
session_start();
?>
<!DOCTYPE html>
<html lang="zh-MY">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>输入验证码 - SRI MUAR 价格查询</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Microsoft YaHei', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .verify-container {
            max-width: 400px;
            margin: 40px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .verify-header {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .verify-body {
            padding: 40px;
        }
        
        .code-input {
            width: 50px;
            height: 60px;
            font-size: 24px;
            text-align: center;
            font-weight: bold;
            border: 2px solid #ddd;
            border-radius: 8px;
            margin: 0 5px;
        }
        
        .code-input:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.25);
            outline: none;
        }
        
        .code-input-container {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin: 30px 0;
        }
        
        .timer {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
        }
        
        .timer-expired {
            color: #dc3545;
            font-weight: bold;
        }
        
        .btn-success {
            background: #28a745;
            border: none;
            padding: 14px;
            font-weight: 600;
            width: 100%;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .btn-success:hover {
            background: #218838;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="verify-container">
            <div class="verify-header">
                <h2><i class="fas fa-lock me-2"></i>输入验证码</h2>
                <p class="mb-0">请输入邮件中的6位验证码</p>
            </div>
            
            <div class="verify-body">
                <?php if (isset($_SESSION['price_verification'])): ?>
                    <div class="text-center mb-4">
                        <p class="mb-2">验证码已发送到：</p>
                        <h5 class="text-primary"><?php echo htmlspecialchars($_SESSION['price_verification']['email']); ?></h5>
                        <p class="text-muted small mt-2">请检查邮箱（包括垃圾邮件）</p>
                    </div>
                    
                    <form id="verifyForm" action="verify.php" method="POST">
                        <div class="code-input-container">
                            <?php for ($i = 1; $i <= 6; $i++): ?>
                                <input type="text" 
                                       name="code[]" 
                                       class="code-input" 
                                       maxlength="1" 
                                       data-index="<?php echo $i; ?>"
                                       oninput="moveToNext(this)"
                                       onkeydown="handleBackspace(event, this)">
                            <?php endfor; ?>
                        </div>
                        
                        <input type="hidden" name="session_id" value="<?php echo $_SESSION['price_verification']['session_id']; ?>">
                        <input type="hidden" name="full_code" id="fullCodeInput">
                        
                        <div class="timer text-center" id="timerDisplay">
                            验证码有效期：<span id="timer">15:00</span>
                        </div>
                        
                        <button type="submit" class="btn btn-success mt-3" id="verifyBtn">
                            <i class="fas fa-check-circle me-2"></i>验证并查看价格
                        </button>
                        
                        <div class="text-center mt-3">
                            <a href="price_information.php" class="text-decoration-none">
                                <i class="fas fa-redo me-1"></i>重新获取验证码
                            </a>
                        </div>
                    </form>
                    
                    <div id="errorMessage" class="alert alert-danger mt-3" style="display: none;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span id="errorText"></span>
                    </div>
                    
                <?php else: ?>
                    <div class="alert alert-warning text-center">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        没有找到验证信息
                    </div>
                    <div class="text-center mt-3">
                        <a href="price_information.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left me-2"></i>返回获取验证码
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script>
        // 自动跳转到下一个输入框
        function moveToNext(input) {
            const index = parseInt(input.dataset.index);
            const value = input.value;
            
            // 只允许数字
            input.value = value.replace(/\D/g, '');
            
            if (input.value && index < 6) {
                const nextInput = document.querySelector(`input[data-index="${index + 1}"]`);
                nextInput.focus();
            }
            
            updateFullCode();
        }
        
        // 处理退格键
        function handleBackspace(event, input) {
            const index = parseInt(input.dataset.index);
            
            if (event.key === 'Backspace' && !input.value && index > 1) {
                const prevInput = document.querySelector(`input[data-index="${index - 1}"]`);
                prevInput.focus();
                prevInput.value = '';
                updateFullCode();
            }
        }
        
        // 更新完整验证码
        function updateFullCode() {
            let fullCode = '';
            for (let i = 1; i <= 6; i++) {
                const input = document.querySelector(`input[data-index="${i}"]`);
                fullCode += input.value;
            }
            document.getElementById('fullCodeInput').value = fullCode;
        }
        
        // 倒计时
        function startTimer() {
            let timeLeft = 15 * 60; // 15分钟
            
            const timerInterval = setInterval(() => {
                const minutes = Math.floor(timeLeft / 60);
                const seconds = timeLeft % 60;
                
                document.getElementById('timer').textContent = 
                    `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                
                if (timeLeft <= 0) {
                    clearInterval(timerInterval);
                    document.getElementById('timerDisplay').innerHTML = 
                        '<span class="timer-expired">验证码已过期</span>';
                    document.getElementById('verifyBtn').disabled = true;
                }
                
                timeLeft--;
            }, 1000);
        }
        
        // 表单提交验证
        document.addEventListener('DOMContentLoaded', function() {
            const verifyForm = document.getElementById('verifyForm');
            const errorMessage = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            
            if (verifyForm) {
                // 开始倒计时
                startTimer();
                
                // 自动聚焦到第一个输入框
                document.querySelector('input[data-index="1"]').focus();
                
                // 表单提交
                verifyForm.addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    const fullCode = document.getElementById('fullCodeInput').value;
                    
                    if (fullCode.length !== 6) {
                        showError('请输入完整的6位验证码');
                        return;
                    }
                    
                    // 禁用按钮防止重复提交
                    document.getElementById('verifyBtn').disabled = true;
                    document.getElementById('verifyBtn').innerHTML = 
                        '<i class="fas fa-spinner fa-spin me-2"></i>验证中...';
                    
                    // 提交表单
                    fetch('verify.php', {
                        method: 'POST',
                        body: new FormData(verifyForm)
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // 验证成功，跳转到价格页面
                            window.location.href = 'price.php';
                        } else {
                            // 验证失败
                            document.getElementById('verifyBtn').disabled = false;
                            document.getElementById('verifyBtn').innerHTML = 
                                '<i class="fas fa-check-circle me-2"></i>验证并查看价格';
                            showError(data.message);
                            
                            // 清空输入框
                            for (let i = 1; i <= 6; i++) {
                                document.querySelector(`input[data-index="${i}"]`).value = '';
                            }
                            document.querySelector('input[data-index="1"]').focus();
                        }
                    })
                    .catch(error => {
                        document.getElementById('verifyBtn').disabled = false;
                        document.getElementById('verifyBtn').innerHTML = 
                            '<i class="fas fa-check-circle me-2"></i>验证并查看价格';
                        showError('网络错误，请重试');
                        console.error('Error:', error);
                    });
                });
            }
            
            function showError(message) {
                errorText.textContent = message;
                errorMessage.style.display = 'block';
                setTimeout(() => {
                    errorMessage.style.display = 'none';
                }, 5000);
            }
        });
    </script>
</body>
</html>