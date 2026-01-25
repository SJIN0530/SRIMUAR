<?php
// verify_code.php - 输入验证码页面
session_start();

// 检查是否有验证数据
if (!isset($_SESSION['verification_data'])) {
    // 如果没有验证数据，跳转到信息填写页面
    header('Location: price_information.php');
    exit;
}

$verification_data = $_SESSION['verification_data'];
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
            max-width: 450px;
            margin: 40px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .verify-header {
            background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);
            color: white;
            padding: 25px;
            text-align: center;
        }
        
        .verify-body {
            padding: 30px;
        }
        
        .code-input {
            width: 45px;
            height: 55px;
            font-size: 24px;
            text-align: center;
            font-weight: bold;
            border: 2px solid #ddd;
            border-radius: 8px;
            margin: 0 5px;
            transition: all 0.3s;
        }
        
        .code-input:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.25);
            outline: none;
            transform: scale(1.05);
        }
        
        .code-input-container {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin: 25px 0;
        }
        
        .timer {
            font-size: 14px;
            color: #666;
            margin-top: 10px;
            text-align: center;
        }
        
        .timer-expired {
            color: #dc3545;
            font-weight: bold;
        }
        
        .btn-success {
            background: #28a745;
            border: none;
            padding: 12px;
            font-weight: 600;
            width: 100%;
            border-radius: 8px;
            font-size: 16px;
            transition: all 0.3s;
        }
        
        .btn-success:hover {
            background: #218838;
            transform: translateY(-2px);
        }
        
        .btn-success:disabled {
            background: #6c757d;
            cursor: not-allowed;
        }
        
        .user-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            text-align: center;
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
                <div class="user-info">
                    <p class="mb-1"><i class="fas fa-user me-2"></i>姓名: <?php echo htmlspecialchars($verification_data['full_name']); ?></p>
                    <p class="mb-1"><i class="fas fa-envelope me-2"></i>邮箱: <?php echo htmlspecialchars($verification_data['email']); ?></p>
                    <p class="mb-0 text-muted small">请检查邮箱（包括垃圾邮件）</p>
                </div>
                
                <form id="verifyForm" action="verify_handler.php" method="POST">
                    <div class="text-center mb-3">
                        <p>请输入6位验证码：</p>
                    </div>
                    
                    <div class="code-input-container">
                        <input type="text" name="code1" class="code-input" maxlength="1" data-index="1">
                        <input type="text" name="code2" class="code-input" maxlength="1" data-index="2">
                        <input type="text" name="code3" class="code-input" maxlength="1" data-index="3">
                        <input type="text" name="code4" class="code-input" maxlength="1" data-index="4">
                        <input type="text" name="code5" class="code-input" maxlength="1" data-index="5">
                        <input type="text" name="code6" class="code-input" maxlength="1" data-index="6">
                    </div>
                    
                    <input type="hidden" name="full_code" id="fullCodeInput">
                    <input type="hidden" name="session_id" value="<?php echo session_id(); ?>">
                    
                    <div class="timer" id="timerDisplay">
                        验证码有效期：<span id="timer">10:00</span>
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
                
                <div id="successMessage" class="alert alert-success mt-3" style="display: none;">
                    <i class="fas fa-check-circle me-2"></i>
                    <span id="successText"></span>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const verifyForm = document.getElementById('verifyForm');
            const codeInputs = document.querySelectorAll('.code-input');
            const fullCodeInput = document.getElementById('fullCodeInput');
            const verifyBtn = document.getElementById('verifyBtn');
            const errorMessage = document.getElementById('errorMessage');
            const errorText = document.getElementById('errorText');
            const successMessage = document.getElementById('successMessage');
            const successText = document.getElementById('successText');
            const timerDisplay = document.getElementById('timerDisplay');
            const timerSpan = document.getElementById('timer');
            
            let timeLeft = 600; // 10分钟 = 600秒
            let timerInterval;
            
            // 自动跳转到下一个输入框
            function moveToNext(input) {
                const index = parseInt(input.dataset.index);
                const value = input.value;
                
                // 只允许数字
                input.value = value.replace(/\D/g, '');
                
                if (input.value && index < 6) {
                    const nextInput = document.querySelector(`input[data-index="${index + 1}"]`);
                    nextInput.focus();
                    nextInput.select();
                }
                
                updateFullCode();
                checkAllFilled();
            }
            
            // 处理退格键
            function handleBackspace(event, input) {
                const index = parseInt(input.dataset.index);
                
                if (event.key === 'Backspace' && !input.value && index > 1) {
                    event.preventDefault();
                    const prevInput = document.querySelector(`input[data-index="${index - 1}"]`);
                    prevInput.focus();
                    prevInput.value = '';
                    prevInput.select();
                    updateFullCode();
                    checkAllFilled();
                }
            }
            
            // 更新完整验证码
            function updateFullCode() {
                let fullCode = '';
                codeInputs.forEach(input => {
                    fullCode += input.value;
                });
                fullCodeInput.value = fullCode;
            }
            
            // 检查是否所有输入框都已填写
            function checkAllFilled() {
                let allFilled = true;
                codeInputs.forEach(input => {
                    if (!input.value) {
                        allFilled = false;
                    }
                });
                return allFilled;
            }
            
            // 开始倒计时
            function startTimer() {
                timerInterval = setInterval(() => {
                    const minutes = Math.floor(timeLeft / 60);
                    const seconds = timeLeft % 60;
                    
                    timerSpan.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
                    
                    if (timeLeft <= 60) {
                        timerDisplay.classList.add('timer-expired');
                    }
                    
                    if (timeLeft <= 0) {
                        clearInterval(timerInterval);
                        timerDisplay.innerHTML = '<span class="timer-expired">验证码已过期</span>';
                        verifyBtn.disabled = true;
                        verifyBtn.innerHTML = '<i class="fas fa-clock me-2"></i>验证码已过期';
                    }
                    
                    timeLeft--;
                }, 1000);
            }
            
            // 显示错误信息
            function showError(message) {
                errorText.textContent = message;
                errorMessage.style.display = 'block';
                successMessage.style.display = 'none';
                
                // 5秒后自动隐藏错误
                setTimeout(() => {
                    errorMessage.style.display = 'none';
                }, 5000);
            }
            
            // 显示成功信息
            function showSuccess(message) {
                successText.textContent = message;
                successMessage.style.display = 'block';
                errorMessage.style.display = 'none';
            }
            
            // 为每个输入框添加事件监听
            codeInputs.forEach(input => {
                input.addEventListener('input', function() {
                    moveToNext(this);
                });
                
                input.addEventListener('keydown', function(e) {
                    handleBackspace(e, this);
                    
                    // 允许复制粘贴
                    if ((e.ctrlKey || e.metaKey) && (e.key === 'v' || e.key === 'V')) {
                        setTimeout(() => {
                            const pastedText = this.value;
                            if (pastedText.length === 6 && /^\d+$/.test(pastedText)) {
                                // 如果是6位数字，自动填充
                                for (let i = 0; i < 6; i++) {
                                    codeInputs[i].value = pastedText[i];
                                }
                                codeInputs[5].focus();
                                updateFullCode();
                                checkAllFilled();
                            }
                        }, 10);
                    }
                });
                
                // 点击时选中内容
                input.addEventListener('click', function() {
                    this.select();
                });
            });
            
            // 自动聚焦到第一个输入框
            codeInputs[0].focus();
            
            // 开始倒计时
            startTimer();
            
            // 表单提交
            verifyForm.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                const fullCode = fullCodeInput.value;
                
                if (fullCode.length !== 6) {
                    showError('请输入完整的6位验证码');
                    codeInputs[0].focus();
                    return;
                }
                
                if (!/^\d{6}$/.test(fullCode)) {
                    showError('验证码必须是6位数字');
                    return;
                }
                
                // 禁用按钮防止重复提交
                verifyBtn.disabled = true;
                verifyBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>验证中...';
                
                try {
                    // 发送验证请求
                    const formData = new FormData(verifyForm);
                    
                    const response = await fetch('verify_handler.php', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const data = await response.json();
                    
                    if (data.success) {
                        // 验证成功
                        showSuccess('验证成功！正在跳转到价格页面...');
                        verifyBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>验证成功';
                        
                        // 3秒后跳转
                        setTimeout(() => {
                            window.location.href = 'price_display.php';
                        }, 2000);
                        
                    } else {
                        // 验证失败
                        verifyBtn.disabled = false;
                        verifyBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>验证并查看价格';
                        showError(data.message || '验证失败');
                        
                        // 清空输入框并重新聚焦
                        codeInputs.forEach(input => input.value = '');
                        codeInputs[0].focus();
                        updateFullCode();
                    }
                    
                } catch (error) {
                    console.error('验证请求失败:', error);
                    verifyBtn.disabled = false;
                    verifyBtn.innerHTML = '<i class="fas fa-check-circle me-2"></i>验证并查看价格';
                    showError('网络错误，请重试');
                }
            });
            
            // 自动填充测试验证码（仅用于测试）
            const urlParams = new URLSearchParams(window.location.search);
            const testCode = urlParams.get('testcode');
            if (testCode && testCode.length === 6 && /^\d+$/.test(testCode)) {
                for (let i = 0; i < 6; i++) {
                    codeInputs[i].value = testCode[i];
                }
                updateFullCode();
            }
        });
    </script>
</body>
</html>