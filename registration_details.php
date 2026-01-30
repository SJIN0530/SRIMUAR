<?php
// registration_details.php (简化版，移除审批功能)
session_start();
require_once 'database_config.php';

// 检查是否有ID参数
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("错误：未指定报名记录ID");
}

$registration_id = intval($_GET['id']);

// 获取数据库连接
$conn = Database::getConnection();

// 获取报名记录详情
$sql = "SELECT * FROM student_registrations WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->execute([$registration_id]);
$registration = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$registration) {
    die("错误：找不到指定的报名记录");
}

// 格式化日期
$registration_time = $registration['registration_time'] ? date('Y-m-d H:i:s', strtotime($registration['registration_time'])) : '未记录';

// 格式化课程类型
$vehicle_type_text = ($registration['vehicle_type'] == 'car') ? '汽车课程' : '摩托车课程';

// 格式化驾照状态
$has_license_text = ($registration['has_license'] == 'yes') ? '有' : '无';
$license_badge = ($registration['has_license'] == 'yes') ? 'badge-license-yes' : 'badge-license-no';

// 获取注册编号
$reg_id = str_pad($registration['id'], 6, '0', STR_PAD_LEFT);

// 检查文件是否存在
function fileExists($path) {
    if (empty($path)) return false;
    return file_exists($path);
}
?>

<!DOCTYPE html>
<html lang="zh-MY">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>报名详情 #<?php echo $reg_id; ?> - SRI MUAR 皇城驾驶学院</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 图标 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --primary-blue: #0056b3;
            --secondary-orange: #FF6B00;
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

        .page-header {
            background: linear-gradient(135deg, var(--primary-blue) 0%, #003d82 100%);
            color: white;
            padding: 60px 20px 40px;
            text-align: center;
            margin-bottom: 30px;
            border-radius: 10px;
        }

        .detail-container {
            background: white;
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
        }

        .section-title {
            color: var(--primary-blue);
            padding-bottom: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
            font-weight: 600;
        }

        .section-title i {
            margin-right: 10px;
        }

        .info-card {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid var(--primary-blue);
        }

        .info-label {
            color: #666;
            font-weight: 500;
            margin-bottom: 5px;
            font-size: 0.9rem;
        }

        .info-value {
            color: var(--dark-gray);
            font-size: 1.1rem;
            font-weight: 600;
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

        .badge-license-yes {
            background: #17a2b8;
            color: white;
        }

        .badge-license-no {
            background: #6c757d;
            color: white;
        }

        .file-card {
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 20px;
            text-align: center;
            margin-bottom: 15px;
            transition: all 0.3s;
            min-height: 150px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .file-card:hover {
            border-color: var(--primary-blue);
            background: #f0f7ff;
            transform: translateY(-2px);
        }

        .file-icon {
            font-size: 2.5rem;
            color: #6c757d;
            margin-bottom: 10px;
        }

        .file-title {
            font-weight: 600;
            margin-bottom: 5px;
            color: var(--dark-gray);
        }

        .file-status {
            font-size: 0.85rem;
            margin-top: 10px;
        }

        .file-status.uploaded {
            color: #28a745;
        }

        .file-status.missing {
            color: #dc3545;
        }

        .image-preview {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            margin-top: 15px;
            display: none;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        @media (max-width: 768px) {
            .detail-container {
                padding: 20px;
            }
            
            .action-buttons {
                flex-direction: column;
            }
            
            .action-buttons .btn {
                width: 100%;
                margin-bottom: 10px;
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
                        <li><a href="history.php?tab=registrations">报名记录</a></li>
                        <li><a href="admin_login.html">管理员登录</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <!-- 页面标题 -->
    <section class="page-header">
        <div class="container">
            <h1><i class="fas fa-file-alt me-3"></i>报名详情</h1>
            <p>注册编号: <strong>REG<?php echo $reg_id; ?></strong></p>
        </div>
    </section>

    <!-- 主内容 -->
    <div class="container">
        <div class="detail-container">
            <!-- 基本信息 -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <h4 class="section-title"><i class="fas fa-user-circle"></i>基本信息</h4>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="info-card">
                        <div class="info-label">姓名</div>
                        <div class="info-value"><?php echo htmlspecialchars($registration['name']); ?></div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="info-card">
                        <div class="info-label">身份证号码</div>
                        <div class="info-value"><code><?php echo htmlspecialchars($registration['ic_number']); ?></code></div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="info-card">
                        <div class="info-label">课程类型</div>
                        <div class="info-value">
                            <span class="badge <?php echo $registration['vehicle_type'] == 'car' ? 'badge-car' : 'badge-motor'; ?>">
                                <?php echo $vehicle_type_text; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="info-card">
                        <div class="info-label">现有驾照</div>
                        <div class="info-value">
                            <span class="badge <?php echo $license_badge; ?>">
                                <?php echo $has_license_text; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="info-card">
                        <div class="info-label">注册编号</div>
                        <div class="info-value">REG<?php echo $reg_id; ?></div>
                    </div>
                </div>
                <div class="col-md-6 col-lg-4">
                    <div class="info-card">
                        <div class="info-label">注册时间</div>
                        <div class="info-value"><?php echo $registration_time; ?></div>
                    </div>
                </div>
            </div>

            <!-- 身份证文件 -->
            <div class="row mb-4">
                <div class="col-md-12">
                    <h4 class="section-title"><i class="fas fa-id-card"></i>身份证文件</h4>
                </div>
                <div class="col-md-6">
                    <div class="file-card" onclick="viewImage('<?php echo htmlspecialchars($registration['ic_front_path']); ?>', '身份证正面')">
                        <div class="file-icon">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <div class="file-title">身份证正面照片</div>
                        <div class="file-status <?php echo fileExists($registration['ic_front_path']) ? 'uploaded' : 'missing'; ?>">
                            <?php if (fileExists($registration['ic_front_path'])): ?>
                                <i class="fas fa-check-circle me-1"></i>已上传
                            <?php else: ?>
                                <i class="fas fa-exclamation-circle me-1"></i>文件缺失
                            <?php endif; ?>
                        </div>
                        <?php if (fileExists($registration['ic_front_path'])): ?>
                            <img src="<?php echo htmlspecialchars($registration['ic_front_path']); ?>" 
                                 class="image-preview" id="previewIcFront" 
                                 alt="身份证正面预览">
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="file-card" onclick="viewImage('<?php echo htmlspecialchars($registration['ic_back_path']); ?>', '身份证背面')">
                        <div class="file-icon">
                            <i class="fas fa-id-card"></i>
                        </div>
                        <div class="file-title">身份证背面照片</div>
                        <div class="file-status <?php echo fileExists($registration['ic_back_path']) ? 'uploaded' : 'missing'; ?>">
                            <?php if (fileExists($registration['ic_back_path'])): ?>
                                <i class="fas fa-check-circle me-1"></i>已上传
                            <?php else: ?>
                                <i class="fas fa-exclamation-circle me-1"></i>文件缺失
                            <?php endif; ?>
                        </div>
                        <?php if (fileExists($registration['ic_back_path'])): ?>
                            <img src="<?php echo htmlspecialchars($registration['ic_back_path']); ?>" 
                                 class="image-preview" id="previewIcBack" 
                                 alt="身份证背面预览">
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- 驾照文件（如果有） -->
            <?php if ($registration['has_license'] == 'yes'): ?>
            <div class="row mb-4">
                <div class="col-md-12">
                    <h4 class="section-title"><i class="fas fa-file-contract"></i>驾照文件</h4>
                </div>
                <div class="col-md-6">
                    <div class="file-card" onclick="viewImage('<?php echo htmlspecialchars($registration['license_front_path']); ?>', '驾照正面')">
                        <div class="file-icon">
                            <i class="fas fa-id-badge"></i>
                        </div>
                        <div class="file-title">驾照正面照片</div>
                        <div class="file-status <?php echo fileExists($registration['license_front_path']) ? 'uploaded' : 'missing'; ?>">
                            <?php if (fileExists($registration['license_front_path'])): ?>
                                <i class="fas fa-check-circle me-1"></i>已上传
                            <?php else: ?>
                                <i class="fas fa-exclamation-circle me-1"></i>文件缺失
                            <?php endif; ?>
                        </div>
                        <?php if (fileExists($registration['license_front_path'])): ?>
                            <img src="<?php echo htmlspecialchars($registration['license_front_path']); ?>" 
                                 class="image-preview" id="previewLicenseFront" 
                                 alt="驾照正面预览">
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="file-card" onclick="viewImage('<?php echo htmlspecialchars($registration['license_back_path']); ?>', '驾照背面')">
                        <div class="file-icon">
                            <i class="fas fa-id-badge"></i>
                        </div>
                        <div class="file-title">驾照背面照片</div>
                        <div class="file-status <?php echo fileExists($registration['license_back_path']) ? 'uploaded' : 'missing'; ?>">
                            <?php if (fileExists($registration['license_back_path'])): ?>
                                <i class="fas fa-check-circle me-1"></i>已上传
                            <?php else: ?>
                                <i class="fas fa-exclamation-circle me-1"></i>文件缺失
                            <?php endif; ?>
                        </div>
                        <?php if (fileExists($registration['license_back_path'])): ?>
                            <img src="<?php echo htmlspecialchars($registration['license_back_path']); ?>" 
                                 class="image-preview" id="previewLicenseBack" 
                                 alt="驾照背面预览">
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- 操作按钮（简化版） -->
            <div class="action-buttons">
                <a href="history.php?tab=registrations" class="btn btn-secondary">
                    <i class="fas fa-arrow-left me-2"></i>返回报名记录
                </a>
                
                <button onclick="downloadAllFiles()" class="btn btn-primary">
                    <i class="fas fa-download me-2"></i>下载所有文件
                </button>
                
                <button onclick="printPage()" class="btn btn-info">
                    <i class="fas fa-print me-2"></i>打印详情
                </button>
            </div>
        </div>
    </div>

    <!-- 图片查看模态框 -->
    <div class="modal fade" id="imageModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageModalLabel">图片预览</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <img src="" id="modalImage" class="img-fluid" alt="预览图片">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">关闭</button>
                    <button type="button" class="btn btn-primary" onclick="downloadCurrentImage()">
                        <i class="fas fa-download me-2"></i>下载图片
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript 库 -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // 当前查看的图片URL
        let currentImageUrl = '';
        
        // 查看图片
        function viewImage(imageUrl, title) {
            if (!imageUrl || imageUrl === '') {
                alert('文件不存在或路径为空');
                return;
            }
            
            currentImageUrl = imageUrl;
            document.getElementById('modalImage').src = imageUrl;
            document.getElementById('imageModalLabel').textContent = title;
            
            const imageModal = new bootstrap.Modal(document.getElementById('imageModal'));
            imageModal.show();
        }
        
        // 下载当前查看的图片
        function downloadCurrentImage() {
            if (!currentImageUrl) return;
            
            const link = document.createElement('a');
            link.href = currentImageUrl;
            const filename = currentImageUrl.split('/').pop();
            link.download = filename || 'image.jpg';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        // 下载所有文件
        function downloadAllFiles() {
            // 收集所有文件路径
            const files = [
                {url: '<?php echo $registration["ic_front_path"]; ?>', name: '身份证正面.jpg'},
                {url: '<?php echo $registration["ic_back_path"]; ?>', name: '身份证背面.jpg'}
            ];
            
            <?php if ($registration['has_license'] == 'yes'): ?>
                files.push({url: '<?php echo $registration["license_front_path"]; ?>', name: '驾照正面.jpg'});
                files.push({url: '<?php echo $registration["license_back_path"]; ?>', name: '驾照背面.jpg'});
            <?php endif; ?>
            
            // 简单方法：创建多个下载链接
            files.forEach((file, index) => {
                if (file.url && file.url !== '') {
                    setTimeout(() => {
                        const link = document.createElement('a');
                        link.href = file.url;
                        link.download = `REG<?php echo $reg_id; ?>_${file.name}`;
                        link.style.display = 'none';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    }, index * 500);
                }
            });
        }
        
        // 打印页面
        function printPage() {
            window.print();
        }
        
        // 页面加载时显示预览图片
        document.addEventListener('DOMContentLoaded', function() {
            // 添加点击预览功能
            document.querySelectorAll('.file-card').forEach(card => {
                card.style.cursor = 'pointer';
            });
            
            // 显示文件预览图
            const previewImages = document.querySelectorAll('.image-preview');
            previewImages.forEach(img => {
                if (img.src && img.src !== window.location.href) {
                    img.style.display = 'block';
                    
                    // 添加点击查看大图功能
                    img.parentElement.addEventListener('click', function(e) {
                        if (e.target.tagName === 'IMG') {
                            const title = this.querySelector('.file-title').textContent;
                            viewImage(e.target.src, title);
                        }
                    });
                }
            });
        });
    </script>
</body>
</html>