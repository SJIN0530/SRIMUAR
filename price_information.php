<?php
// price_information.php - 信息填写页面
session_start();
?>
<!DOCTYPE html>
<html lang="zh-MY">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>获取验证码 - SRI MUAR 价格查询</title>
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Microsoft YaHei', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }
        
        .auth-container {
            max-width: 500px;
            margin: 40px auto;
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        
        .auth-header {
            background: linear-gradient(135deg, #0056b3 0%, #003d82 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .auth-body {
            padding: 40px;
        }
        
        .btn-primary {
            background: #0056b3;
            border: none;
            padding: 14px;
            font-weight: 600;
            width: 100%;
            border-radius: 8px;
            font-size: 16px;
        }
        
        .btn-primary:hover {
            background: #004494;
        }
        
        .loading-spinner {
            display: none;
            text-align: center;
            margin: 15px 0;
        }
        
        .test-btn {
            width: 100%;
            padding: 10px;
            margin-top: 10px;
        }
        
        .privacy-notice {
            font-size: 13px;
            line-height: 1.5;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="auth-container">
            <div class="auth-header">
                <h2><i class="fas fa-key me-2"></i>获取验证码</h2>
                <p class="mb-0">验证后查看课程价格</p>
            </div>
            
            <div class="auth-body">
                <!-- 信息填写表单 -->
                <form id="requestCodeForm">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        验证码将发送到您的邮箱，用于查看价格。
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">身份证号码 (IC Number)</label>
                        <input type="text" name="ic_number" class="form-control" 
                               placeholder="输入12位身份证号码" maxlength="12" required>
                        <div class="form-text">
                            <i class="fas fa-exclamation-circle me-1"></i>
                            例如：901231011234（请输入您的真实身份证号码）
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">姓名 (Full Name)</label>
                        <input type="text" name="full_name" class="form-control" 
                               placeholder="请输入您的全名" required>
                        <div class="form-text">
                            <i class="fas fa-user me-1"></i>
                            请输入与身份证一致的姓名
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">邮箱地址 (Email Address)</label>
                        <input type="email" name="email" class="form-control" 
                               placeholder="请输入您常用的邮箱地址" required>
                        <div class="form-text">
                            <i class="fas fa-envelope me-1"></i>
                            验证码将发送到此邮箱，请确保邮箱正确并能正常接收邮件
                        </div>
                    </div>
                    
                    <!-- 隐私声明 -->
                    <div class="alert alert-light border mb-4">
                        <div class="d-flex">
                            <i class="fas fa-shield-alt text-primary me-2 mt-1"></i>
                            <div class="privacy-notice">
                                <strong>隐私保护声明：</strong>
                                我们承诺保护您的个人信息安全。身份证号码仅用于身份验证，邮箱仅用于发送验证码，我们不会将这些信息用于其他用途或分享给第三方。
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="submitBtn">
                        <i class="fas fa-paper-plane me-2"></i>发送验证码
                    </button>
                    
                    <div class="loading-spinner" id="loadingSpinner">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">发送中...</span>
                        </div>
                        <p class="mt-2">正在发送验证码...</p>
                    </div>
                    
                    <div id="successMessage" class="alert alert-success mt-3" style="display: none;">
                        <i class="fas fa-check-circle me-2"></i>
                        <span id="successText"></span>
                    </div>
                    
                    <div id="errorMessage" class="alert alert-danger mt-3" style="display: none;">
                        <i class="fas fa-exclamation-triangle me-2"></i>
                        <span id="errorText"></span>
                    </div>
                </form>
                
                <!-- 测试连接按钮 -->
                <button type="button" class="btn btn-outline-secondary mt-3" id="testConnection">
                    <i class="fas fa-bug me-2"></i>测试服务器连接
                </button>
                
                <div class="text-center mt-4">
                    <a href="verify_code.php" class="btn btn-outline-primary" id="goToVerifyBtn" style="display: none;">
                        <i class="fas fa-arrow-right me-2"></i>输入验证码
                    </a>
                    <a href="index.html" class="text-decoration-none d-block mt-2">
                        <i class="fas fa-arrow-left me-1"></i>返回首页
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('requestCodeForm');
            const submitBtn = document.getElementById('submitBtn');
            const loadingSpinner = document.getElementById('loadingSpinner');
            const successMessage = document.getElementById('successMessage');
            const errorMessage = document.getElementById('errorMessage');
            const successText = document.getElementById('successText');
            const errorText = document.getElementById('errorText');
            const goToVerifyBtn = document.getElementById('goToVerifyBtn');
            const testConnectionBtn = document.getElementById('testConnection');
            
            // IC号码只能输入数字
            const icInput = form.querySelector('input[name="ic_number"]');
            icInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 12);
            });
            
            // 自动格式化姓名输入（首字母大写）
            const nameInput = form.querySelector('input[name="full_name"]');
            nameInput.addEventListener('blur', function() {
                if (this.value.trim()) {
                    this.value = this.value.trim().replace(/\s+/g, ' ');
                }
            });
            
            // 邮箱验证
            const emailInput = form.querySelector('input[name="email"]');
            emailInput.addEventListener('blur', function() {
                const email = this.value.trim();
                if (email && !isValidEmail(email)) {
                    showError('请输入有效的邮箱地址');
                    this.focus();
                }
            });
            
            // 表单提交
            form.addEventListener('submit', async function(e) {
                e.preventDefault();
                
                // 验证IC号码长度
                if (icInput.value.length !== 12) {
                    showError('身份证号码必须是12位数字');
                    icInput.focus();
                    return;
                }
                
                // 验证姓名
                if (nameInput.value.trim().length < 2) {
                    showError('请输入有效的姓名');
                    nameInput.focus();
                    return;
                }
                
                // 验证邮箱
                const email = emailInput.value.trim();
                if (!email) {
                    showError('请输入邮箱地址');
                    emailInput.focus();
                    return;
                }
                
                if (!isValidEmail(email)) {
                    showError('请输入有效的邮箱地址');
                    emailInput.focus();
                    return;
                }
                
                // 显示加载状态
                submitBtn.style.display = 'none';
                loadingSpinner.style.display = 'block';
                errorMessage.style.display = 'none';
                
                try {
                    // 收集表单数据
                    const formData = new FormData(form);
                    
                    console.log('正在发送请求到 send_code.php...');
                    
                    // 发送请求 - 添加超时设置
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 10000); // 10秒超时
                    
                    const response = await fetch('send_code.php', {
                        method: 'POST',
                        body: formData,
                        signal: controller.signal
                    });
                    
                    clearTimeout(timeoutId);
                    
                    // 检查响应状态
                    if (!response.ok) {
                        throw new Error(`HTTP错误: ${response.status}`);
                    }
                    
                    // 解析响应
                    const data = await response.json();
                    console.log('服务器响应:', data);
                    
                    // 处理响应数据
                    loadingSpinner.style.display = 'none';
                    
                    if (data.success) {
                        // 成功
                        successText.textContent = data.message;
                        successMessage.style.display = 'block';
                        goToVerifyBtn.style.display = 'inline-block';
                        
                        // 显示验证码（测试用）
                        if (data.debug && data.debug.verification_code) {
                            alert('测试模式验证码: ' + data.debug.verification_code + '\n\n请将此验证码用于下一步验证。');
                        }
                        
                        // 3秒后自动跳转
                        setTimeout(() => {
                            window.location.href = 'verify_code.php';
                        }, 3000);
                        
                    } else {
                        // 失败
                        submitBtn.style.display = 'block';
                        errorText.textContent = data.message || '未知错误';
                        errorMessage.style.display = 'block';
                    }
                    
                } catch (error) {
                    console.error('请求失败:', error);
                    loadingSpinner.style.display = 'none';
                    submitBtn.style.display = 'block';
                    
                    if (error.name === 'AbortError') {
                        errorText.textContent = '请求超时，请检查网络连接';
                    } else {
                        errorText.textContent = '网络错误: ' + error.message;
                    }
                    errorMessage.style.display = 'block';
                }
            });
            
            // 测试服务器连接
            testConnectionBtn.addEventListener('click', async function() {
                try {
                    loadingSpinner.style.display = 'block';
                    testConnectionBtn.disabled = true;
                    
                    const response = await fetch('send_code.php?test=1');
                    const data = await response.json();
                    
                    loadingSpinner.style.display = 'none';
                    testConnectionBtn.disabled = false;
                    
                    alert('服务器连接正常！\n状态: ' + response.status + '\n消息: ' + data.message);
                } catch (error) {
                    loadingSpinner.style.display = 'none';
                    testConnectionBtn.disabled = false;
                    alert('连接失败: ' + error.message);
                }
            });
            
            // 邮箱验证函数
            function isValidEmail(email) {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return emailRegex.test(email);
            }
            
            function showError(message) {
                errorText.textContent = message;
                errorMessage.style.display = 'block';
                
                // 5秒后自动隐藏错误
                setTimeout(() => {
                    errorMessage.style.display = 'none';
                }, 5000);
            }
        });
    </script>
</body>
</html>