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
                        <div class="form-text">例如：901231011234</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-bold">姓名 (Full Name)</label>
                        <input type="text" name="full_name" class="form-control" 
                               placeholder="请输入您的全名" required>
                    </div>
                    
                    <div class="mb-4">
                        <label class="form-label fw-bold">邮箱地址 (Email Address)</label>
                        <input type="email" name="email" class="form-control" 
                               placeholder="example@gmail.com" required>
                        <div class="form-text">验证码将发送到此邮箱</div>
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
            
            // IC号码只能输入数字
            const icInput = form.querySelector('input[name="ic_number"]');
            icInput.addEventListener('input', function() {
                this.value = this.value.replace(/\D/g, '').slice(0, 12);
            });
            
            // 表单提交
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // 验证IC号码长度
                if (icInput.value.length !== 12) {
                    showError('身份证号码必须是12位数字');
                    return;
                }
                
                // 显示加载状态
                submitBtn.style.display = 'none';
                loadingSpinner.style.display = 'block';
                errorMessage.style.display = 'none';
                
                // 收集表单数据
                const formData = new FormData(form);
                
                // 发送请求
                fetch('send_code.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    loadingSpinner.style.display = 'none';
                    
                    if (data.success) {
                        // 成功
                        successText.textContent = data.message;
                        successMessage.style.display = 'block';
                        goToVerifyBtn.style.display = 'inline-block';
                        
                        // 存储session ID到localStorage
                        if (data.session_id) {
                            localStorage.setItem('price_session_id', data.session_id);
                        }
                        
                        // 3秒后自动跳转
                        setTimeout(() => {
                            window.location.href = 'verify_code.php';
                        }, 3000);
                        
                    } else {
                        // 失败
                        submitBtn.style.display = 'block';
                        errorText.textContent = data.message;
                        errorMessage.style.display = 'block';
                    }
                })
                .catch(error => {
                    loadingSpinner.style.display = 'none';
                    submitBtn.style.display = 'block';
                    errorText.textContent = '网络错误，请稍后重试';
                    errorMessage.style.display = 'block';
                    console.error('Error:', error);
                });
            });
            
            function showError(message) {
                errorText.textContent = message;
                errorMessage.style.display = 'block';
            }
        });
    </script>
</body>
</html>