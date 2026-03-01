<?php
// 连接数据库
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "sri_muar";

// 创建连接
$conn = new mysqli($servername, $username, $password, $dbname);

// 检查连接
if ($conn->connect_error) {
    die("连接失败: " . $conn->connect_error);
}

// 获取评价列表 - 只查询需要的字段
$sql = "SELECT name, comment, rating, created_at FROM comments ORDER BY created_at DESC";
$result = $conn->query($sql);
$comments = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $comments[] = $row;
    }
}

// 获取统计数据
$stats_sql = "SELECT 
    COUNT(*) as total,
    AVG(rating) as avg_rating,
    SUM(CASE WHEN rating = 5 THEN 1 ELSE 0 END) as five_star
    FROM comments";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

$conn->close();
?>
<!DOCTYPE html>
<html lang="zh-MY">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>客户评价 - SRI MUAR 皇城驾驶学院</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 图标 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">

    <style>
        :root 
        {
            --primary-blue: #0056b3;
            --secondary-orange: #FF6B00;
            --light-gray: #f8f9fa;
            --dark-gray: #333333;
        }

        body 
        {
            font-family: 'Microsoft YaHei', 'Segoe UI', sans-serif;
            color: var(--dark-gray);
            padding-top: 10px;
            background-color: #f9f9f9;
        }

        .top-navbar 
        {
            background: white;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            padding: 15px 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            border-radius: 10px;
            margin: 0 15px;
        }

        .logo-container
        {
            display: flex;
            align-items: center;
            height: auto;
            padding: 0;
        }

        .logo-img
        {
            height: 120px;
            width: auto;
            object-fit: contain;
            transition: all 0.3s ease;
            max-width: 100%;
        }

        .main-nav 
        {
            display: flex;
            gap: 20px;
            list-style: none;
            margin: 0;
            padding: 0;
            align-items: center;
            flex-wrap: wrap;
        }

        .main-nav a 
        {
            color: var(--dark-gray);
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
            padding: 8px 12px;
            border-radius: 5px;
            white-space: nowrap;
        }

        .main-nav a:hover 
        {
            color: var(--primary-blue);
            background-color: rgba(0, 86, 179, 0.1);
        }

        .main-nav .active
        {
            color: var(--primary-blue);
            font-weight: 600;
            border-bottom: 3px solid var(--primary-blue);
        }

        .enroll-btn
        {
            background: var(--primary-blue);
            color: white !important;
            padding: 8px 20px !important;
            border-radius: 25px !important;
            margin-left: 10px;
        }

        .enroll-btn:hover
        {
            background: #004494 !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .admin-btn
        {
            background: #6c757d;
            color: white !important;
            padding: 8px 20px !important;
            border-radius: 25px !important;
            margin-left: 10px;
        }

        .page-header 
        {
            background: linear-gradient(135deg, var(--primary-blue), #0066cc);
            padding: 60px 0;
            color: white;
            margin-bottom: 40px;
        }

        .page-header h1 
        {
            font-weight: 700;
            margin-bottom: 15px;
        }

        .page-header p 
        {
            opacity: 0.9;
            max-width: 700px;
            margin: 0 auto;
        }

        .rating-form-container 
        {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin-bottom: 50px;
            border: 1px solid rgba(0, 86, 179, 0.1);
        }

        .form-title 
        {
            color: var(--primary-blue);
            margin-bottom: 30px;
            text-align: center;
            font-weight: 600;
        }

        .star-rating 
        {
            text-align: center;
            margin: 25px 0;
        }

        .star-rating h5 
        {
            color: var(--dark-gray);
            margin-bottom: 15px;
        }

        .rating-stars 
        {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-bottom: 20px;
        }

        .star 
        {
            font-size: 2.5rem;
            color: #ddd;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .star:hover,
        .star:hover ~ .star 
        {
            color: #ffd700;
        }

        .star.selected 
        {
            color: #ffd700;
        }

        .form-control, .form-select 
        {
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus 
        {
            border-color: var(--primary-blue);
            box-shadow: 0 0 0 0.2rem rgba(0, 86, 179, 0.25);
        }

        textarea.form-control 
        {
            min-height: 150px;
            resize: vertical;
        }

        .submit-btn 
        {
            background: var(--primary-blue);
            color: white;
            padding: 12px 40px;
            border-radius: 25px;
            border: none;
            font-weight: 500;
            transition: all 0.3s;
            display: block;
            margin: 30px auto 0;
            width: 100%;
            max-width: 300px;
        }

        .submit-btn:hover 
        {
            background: #004494;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .testimonials-section 
        {
            margin-bottom: 60px;
        }

        .section-title 
        {
            text-align: center;
            color: var(--primary-blue);
            margin-bottom: 40px;
            font-weight: 600;
            position: relative;
        }

        .section-title::after 
        {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: var(--secondary-orange);
        }

        .testimonial-card 
        {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            border: 1px solid rgba(0, 86, 179, 0.1);
            transition: transform 0.3s ease;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .testimonial-card:hover 
        {
            transform: translateY(-5px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .stars 
        {
            color: #FFD700;
            margin-bottom: 15px;
            font-size: 1.2rem;
        }

        .stars i 
        {
            margin-right: 2px;
        }

        .testimonial-content 
        {
            margin-bottom: 20px;
            line-height: 1.6;
            flex-grow: 1;
        }

        .testimonial-content p 
        {
            margin-bottom: 0;
            font-style: italic;
        }

        .testimonial-meta 
        {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }

        .testimonial-author 
        {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .author-avatar 
        {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: linear-gradient(45deg, var(--primary-blue), var(--secondary-orange));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
            font-size: 1.2rem;
        }

        .author-info h4 
        {
            margin: 0;
            font-size: 1.1rem;
            color: var(--primary-blue);
        }

        .testimonial-date 
        {
            color: #888;
            font-size: 0.85rem;
        }

        .filter-buttons 
        {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 30px;
            box-shadow: 0 3px 15px rgba(0,0,0,0.05);
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: center;
        }

        .filter-btn 
        {
            padding: 8px 20px;
            border: 2px solid var(--primary-blue);
            background: transparent;
            color: var(--primary-blue);
            border-radius: 25px;
            transition: all 0.3s;
        }

        .filter-btn:hover,
        .filter-btn.active 
        {
            background: var(--primary-blue);
            color: white;
        }

        .no-comments 
        {
            text-align: center;
            padding: 60px 20px;
            color: #666;
        }

        .no-comments i 
        {
            font-size: 4rem;
            color: #ddd;
            margin-bottom: 20px;
        }

        .stats-container 
        {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.05);
            margin-bottom: 40px;
        }

        .stat-item 
        {
            text-align: center;
            padding: 15px;
        }

        .stat-number 
        {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--primary-blue);
            margin-bottom: 10px;
        }

        .stat-label 
        {
            color: #666;
            font-size: 0.9rem;
        }

        footer 
        {
            background: #2c3e50;
            color: white;
            padding: 50px 0 20px 0;
        }

        footer h5 
        {
            color: var(--secondary-orange);
            margin-bottom: 20px;
            font-weight: 600;
            position: relative;
            padding-bottom: 10px;
        }

        footer h5::after 
        {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 3px;
            background: var(--secondary-orange);
        }

        .footer-links 
        {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .footer-links li 
        {
            margin-bottom: 12px;
        }

        .footer-links a 
        {
            color: #ddd;
            text-decoration: none;
            transition: all 0.3s;
            display: inline-block;
            padding-left: 0;
        }

        .footer-links a:hover 
        {
            color: white;
            padding-left: 8px;
        }

        .contact-info
        {
            line-height: 1.8;
        }

        .contact-info p 
        {
            margin-bottom: 12px;
            display: flex;
            align-items: flex-start;
            flex-wrap: nowrap;
        }

        .contact-info i 
        {
            margin-top: 5px;
            min-width: 24px;
            color: var(--secondary-orange);
            flex-shrink: 0;
        }

        .contact-info p:nth-child(2) 
        {
            align-items: center;
        }

        .contact-info p:nth-child(2) i 
        {
            margin-top: 3px;
        }

        .contact-info p:nth-child(2) a 
        {
            word-break: break-all;
        }

        .social-icons 
        {
            display: flex;
            gap: 15px;
            margin-top: 20px;
            justify-content: flex-start;
        }

        .social-icons a 
        {
            color: white;
            font-size: 1.2rem;
            transition: all 0.3s;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255,255,255,0.1);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .social-icons a:hover 
        {
            color: var(--secondary-orange);
            background: rgba(255,255,255,0.2);
            transform: translateY(-3px);
        }

        .back-to-top 
        {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 50px;
            height: 50px;
            background: var(--primary-blue);
            color: white;
            border: none;
            border-radius: 50%;
            cursor: pointer;
            font-size: 1.2rem;
            display: none;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            z-index: 999;
            transition: all 0.3s;
            opacity: 0;
        }

        .back-to-top:hover 
        {
            background: #004494;
            transform: translateY(-3px) scale(1.1);
            box-shadow: 0 6px 16px rgba(0,0,0,0.25);
        }

        @media (max-width: 768px) 
        {
            .admin-btn {
                margin-left: 5px;
                padding: 6px 15px !important;
            }

            .page-header 
            {
                padding: 40px 0;
            }

            .rating-form-container 
            {
                padding: 25px;
            }

            .logo-img
            {
                height: 100px;
            }

            .main-nav 
            {
                gap: 10px;
            }

            .main-nav a 
            {
                font-size: 0.9rem;
                padding: 6px 10px;
            }

            .stat-number 
            {
                font-size: 2rem;
            }

            .star 
            {
                font-size: 2rem;
            }

            .testimonial-meta 
            {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }

            .testimonial-date 
            {
                align-self: flex-end;
            }

            footer h5,
            .footer-links,
            .contact-info,
            .social-icons 
            {
                text-align: center;
            }
            
            footer h5::after 
            {
                left: 50%;
                transform: translateX(-50%);
            }
            
            .contact-info p 
            {
                justify-content: center;
                text-align: center;
            }
            
            .social-icons 
            {
                justify-content: center;
            }
        }

        @media (max-width: 576px) 
        {
            .logo-img
            {
                height: 80px;
            }

            .main-nav a
            {
                font-size: 0.8rem;
                padding: 4px 6px;
            }

            .enroll-btn,
            .admin-btn
            {
                padding: 6px 12px !important;
                font-size: 0.8rem;
            }
        }

        @media (min-width: 769px) 
        {
            footer h5,
            .footer-links,
            .contact-info,
            .social-icons 
            {
                text-align: left;
            }
            
            footer h5::after 
            {
                left: 0;
                transform: none;
            }
            
            .contact-info p 
            {
                justify-content: flex-start;
            }
            
            .social-icons 
            {
                justify-content: flex-start;
            }
        }
    </style>
</head>
<body>
    <!-- ==================== -->
    <!-- 顶部导航栏 -->
    <!-- ==================== -->
    <nav class="top-navbar">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-3">
                    <div class="logo-container">
                        <a href="index.php" class="d-flex align-items-center text-decoration-none">
                            <img src="logo.PNG" alt="SRI MUAR Logo" class="logo-img">
                        </a>
                    </div>
                </div>
                
                <div class="col-md-9">
                    <div class="nav-menu-container">
                        <ul class="main-nav">
                            <li><a href="index.php">首页</a></li>
                            <li><a href="courses.html">课程</a></li>
                            <li><a href="products.html">配套</a></li>
                            <li><a href="contact.html">联系我们</a></li>
                            <li><a href="aboutus.html">学院简介</a></li>
                            <li><a href="picture.html">学院图集</a></li>
                            <li><a href="comment.php" class="active">客户评价</a></li>
                            <li>
                                <a href="admin_login.html" class="admin-btn">
                                    <i class="fas fa-user-shield me-1"></i> 管理员
                                </a>
                            </li>
                            <li>
                                <a href="register.php" class="enroll-btn">
                                    <i class="fas fa-user-plus me-1"></i> 立即报名
                                </a>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <!-- ==================== -->
    <!-- 页面标题 -->
    <!-- ==================== -->
    <div class="page-header">
        <div class="container">
            <h1 class="text-center">客户评价</h1>
            <p class="text-center">听听我们学员的真实反馈和体验分享。您的评价对我们非常重要！</p>
        </div>
    </div>

    <!-- ==================== -->
    <!-- 评价统计 -->
    <!-- ==================== -->
    <div class="container">
        <div class="stats-container">
            <div class="row">
                <div class="col-md-4 col-6">
                    <div class="stat-item">
                        <div class="stat-number" id="totalComments"><?php echo $stats['total'] ?? 0; ?></div>
                        <div class="stat-label">总评价数</div>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="stat-item">
                        <div class="stat-number" id="averageRating"><?php echo number_format($stats['avg_rating'] ?? 0, 1); ?></div>
                        <div class="stat-label">平均评分</div>
                    </div>
                </div>
                <div class="col-md-4 col-6">
                    <div class="stat-item">
                        <div class="stat-number" id="fiveStarCount"><?php echo $stats['five_star'] ?? 0; ?></div>
                        <div class="stat-label">五星评价</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- ==================== -->
    <!-- 评价提交表单 - 移除了课程选择 -->
    <!-- ==================== -->
    <div class="container">
        <div class="rating-form-container">
            <h2 class="form-title">分享您的学习体验</h2>
            
            <form id="commentForm" action="save_comment.php" method="POST">
                <div class="row">
                    <div class="col-md-12 mb-3">
                        <label for="name" class="form-label">姓名 *</label>
                        <input type="text" class="form-control" id="name" name="name" required>
                    </div>
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">电子邮箱</label>
                    <input type="email" class="form-control" id="email" name="email">
                    <small class="text-muted">仅用于联系您核实评价，不会公开显示</small>
                </div>

                <!-- 星级评分 -->
                <div class="star-rating">
                    <h5>请为我们的服务评分 *</h5>
                    <div class="rating-stars">
                        <i class="fas fa-star star" data-value="1"></i>
                        <i class="fas fa-star star" data-value="2"></i>
                        <i class="fas fa-star star" data-value="3"></i>
                        <i class="fas fa-star star" data-value="4"></i>
                        <i class="fas fa-star star" data-value="5"></i>
                    </div>
                    <input type="hidden" id="rating" name="rating" required>
                    <div id="ratingText" class="text-muted">请选择评分</div>
                </div>

                <div class="mb-3">
                    <label for="comment" class="form-label">评价内容 *</label>
                    <textarea class="form-control" id="comment" name="comment" rows="5" 
                              placeholder="请分享您的学习体验、教练的教学质量、学院设施等方面的感受..." 
                              required></textarea>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">
                    <i class="fas fa-paper-plane me-2"></i> 提交评价
                </button>
            </form>
        </div>
    </div>

    <!-- ==================== -->
    <!-- 评价筛选和展示 - 移除了课程筛选 -->
    <!-- ==================== -->
    <div class="container testimonials-section">
        <h2 class="section-title">学员评价</h2>

        <!-- 简单筛选 - 只保留全部和五星 -->
        <div class="filter-buttons">
            <button class="filter-btn active" data-filter="all">全部评价</button>
            <button class="filter-btn" data-filter="5">五星评价</button>
        </div>

        <!-- 评价列表 -->
        <div class="row" id="commentsList">
            <?php if (empty($comments)): ?>
                <!-- 无评价提示将通过JavaScript显示 -->
            <?php else: ?>
                <?php foreach ($comments as $comment): ?>
                <div class="col-md-6 col-lg-4 comment-item" data-rating="<?php echo $comment['rating']; ?>">
                    <div class="testimonial-card">
                        <div class="stars">
                            <?php 
                            $rating = floatval($comment['rating']);
                            $fullStars = floor($rating);
                            $hasHalfStar = ($rating - $fullStars) >= 0.5;
                            
                            for ($i = 1; $i <= 5; $i++): 
                                if ($i <= $fullStars): ?>
                                    <i class="fas fa-star"></i>
                                <?php elseif ($i == $fullStars + 1 && $hasHalfStar): ?>
                                    <i class="fas fa-star-half-alt"></i>
                                <?php else: ?>
                                    <i class="far fa-star"></i>
                                <?php endif; 
                            endfor; ?>
                        </div>
                        <div class="testimonial-content">
                            <p>"<?php echo htmlspecialchars($comment['comment']); ?>"</p>
                        </div>
                        <div class="testimonial-meta">
                            <div class="testimonial-author">
                                <div class="author-avatar"><?php echo substr($comment['name'], 0, 1); ?></div>
                                <div class="author-info">
                                    <h4><?php echo htmlspecialchars($comment['name']); ?></h4>
                                </div>
                            </div>
                            <div class="testimonial-date"><?php echo date('Y-m-d', strtotime($comment['created_at'])); ?></div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- 无评价提示 -->
        <div id="noCommentsMessage" class="no-comments" style="<?php echo empty($comments) ? 'display: block;' : 'display: none;'; ?>">
            <i class="far fa-comment-alt"></i>
            <h4>暂无评价</h4>
            <p>成为第一个分享学习体验的学员吧！</p>
        </div>
    </div>

    <!-- ==================== -->
    <!-- 页脚 -->
    <!-- ==================== -->
    <footer>
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5>客户服务</h5>
                    <ul class="footer-links">
                        <li><a href="faq.html">常见问题 FAQ</a></li>
                        <li><a href="contact.html">联系我们</a></li>
                        <li><a href="refund.html">退款政策</a></li>
                        <li><a href="T&C.html">条款与细则</a></li>
                        <li><a href="admin_login.html">管理员登录</a></li>
                    </ul>
                </div>
                
                <div class="col-md-4 mb-4">
                    <h5>关于我们</h5>
                    <ul class="footer-links">
                        <li><a href="aboutus.html">学院简介</a></li>
                        <li><a href="courses.html">课程介绍</a></li>
                        <li><a href="picture.html">学院图集</a></li>
                        <li><a href="comment.php">客户评价</a></li>
                    </ul>
                </div>
                
                <div class="col-md-4 mb-4">
                    <h5>联系我们</h5>
                    <div class="contact-info">
                        <p><i class="fas fa-phone me-2"></i> 06-981 2000</p>
                        <p><i class="fas fa-envelope me-2"></i> im_srimuar@yahoo.com</p>
                        <p><i class="fas fa-clock me-2"></i> 营业时间: 8:00AM - 5:00PM</p>
                        <p>
                            <i class="fas fa-map-marker-alt me-2"></i> 
                            Lot 77, Parit Unas, Jalan Temenggong Ahmad, 84000 Muar, Johor
                        </p>
                    </div>
                    
                    <div class="social-icons mt-3">
                        <a href="https://share.google/r8hIeATbCeL7scPh3"><i class="fab fa-google"></i></a>
                        <a href="https://wa.me/60182629696"><i class="fab fa-whatsapp"></i></a>
                        <a href="https://www.facebook.com/imsrimuar/?locale=ms_MY"><i class="fab fa-facebook"></i></a>
                        <a href="https://www.instagram.com/imsrimuarhq?igsh=aDF0bWltdXVtZDdx"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
            </div>
            
            <div class="row mt-5 pt-3 border-top border-secondary">
                <div class="col-12 text-center">
                    <p class="mb-0">
                        &copy; 2020 SRI MUAR 皇城驾驶学院. 版权所有.
                        <span class="mx-2">|</span>
                        All right reserved 2020. By E-Driving Software Sdn Bhd
                    </p>
                </div>
            </div>
        </div>
    </footer>

    <!-- 返回顶部按钮 -->
    <button class="back-to-top" id="backToTop" title="返回顶部">
        <i class="fas fa-chevron-up"></i>
    </button>

    <!-- JavaScript -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // 从PHP传递数据到JavaScript
        const commentsFromDB = <?php echo json_encode($comments); ?>;
        let comments = commentsFromDB;

        // 初始化页面
        document.addEventListener('DOMContentLoaded', function() {
            initStarRating();
            setupFilterButtons();
            setupFormSubmission();
            setupBackToTop();
            
            // 导航栏激活状态
            setActiveNav();
        });

        // 星级评分系统
        function initStarRating() {
            const stars = document.querySelectorAll('.star');
            const ratingInput = document.getElementById('rating');
            const ratingText = document.getElementById('ratingText');
            
            stars.forEach(star => {
                star.addEventListener('click', function() {
                    const value = parseInt(this.getAttribute('data-value'));
                    ratingInput.value = value;
                    
                    stars.forEach((s, index) => {
                        if (index < value) {
                            s.classList.add('selected');
                            s.classList.remove('far');
                            s.classList.add('fas');
                        } else {
                            s.classList.remove('selected');
                            s.classList.add('far');
                            s.classList.remove('fas');
                        }
                    });
                    
                    const ratingTexts = ['非常差', '差', '一般', '好', '非常好'];
                    ratingText.textContent = `${value}星 - ${ratingTexts[value - 1]}`;
                });
                
                star.addEventListener('mouseover', function() {
                    const value = parseInt(this.getAttribute('data-value'));
                    stars.forEach((s, index) => {
                        if (index < value) {
                            s.style.color = '#ffd700';
                        }
                    });
                });
                
                star.addEventListener('mouseout', function() {
                    stars.forEach(s => {
                        if (!s.classList.contains('selected')) {
                            s.style.color = '#ddd';
                        }
                    });
                });
            });
        }

        // 设置筛选按钮
        function setupFilterButtons() {
            const filterButtons = document.querySelectorAll('.filter-btn');
            const commentItems = document.querySelectorAll('.comment-item');
            const noCommentsMessage = document.getElementById('noCommentsMessage');
            
            filterButtons.forEach(button => {
                button.addEventListener('click', function() {
                    filterButtons.forEach(btn => btn.classList.remove('active'));
                    this.classList.add('active');
                    
                    const filter = this.getAttribute('data-filter');
                    let visibleCount = 0;
                    
                    commentItems.forEach(item => {
                        const rating = parseFloat(item.getAttribute('data-rating'));
                        
                        if (filter === 'all') {
                            item.style.display = '';
                            visibleCount++;
                        } else if (filter === '5') {
                            if (rating === 5) {
                                item.style.display = '';
                                visibleCount++;
                            } else {
                                item.style.display = 'none';
                            }
                        }
                    });
                    
                    noCommentsMessage.style.display = visibleCount === 0 ? 'block' : 'none';
                });
            });
        }

        // 设置表单提交
        function setupFormSubmission() {
            const form = document.getElementById('commentForm');
            const submitBtn = document.getElementById('submitBtn');
            
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const rating = document.getElementById('rating').value;
                
                if (!rating) {
                    Swal.fire({
                        icon: 'warning',
                        title: '请选择评分',
                        text: '请为我们的服务打分',
                        confirmButtonColor: '#0056b3'
                    });
                    return;
                }
                
                // 禁用提交按钮防止重复提交
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> 提交中...';
                
                // 使用fetch API提交表单
                const formData = new FormData(form);
                
                fetch('save_comment.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire({
                            icon: 'success',
                            title: '提交成功！',
                            text: '感谢您的评价，您的反馈对我们非常重要。',
                            confirmButtonColor: '#0056b3'
                        }).then(() => {
                            // 刷新页面以显示新评论
                            window.location.reload();
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            title: '提交失败',
                            text: data.message || '请稍后重试',
                            confirmButtonColor: '#0056b3'
                        });
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> 提交评价';
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        icon: 'error',
                        title: '提交失败',
                        text: '网络错误，请稍后重试',
                        confirmButtonColor: '#0056b3'
                    });
                    submitBtn.disabled = false;
                    submitBtn.innerHTML = '<i class="fas fa-paper-plane me-2"></i> 提交评价';
                });
            });
        }

        // 设置返回顶部按钮
        function setupBackToTop() {
            const backToTopBtn = document.getElementById('backToTop');
            
            backToTopBtn.addEventListener('click', () => {
                window.scrollTo({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            window.addEventListener('scroll', function() {
                if (window.pageYOffset > 300) {
                    backToTopBtn.style.display = 'flex';
                    setTimeout(() => {
                        backToTopBtn.style.opacity = '1';
                    }, 10);
                } else {
                    backToTopBtn.style.opacity = '0';
                    setTimeout(() => {
                        if (window.pageYOffset <= 300) {
                            backToTopBtn.style.display = 'none';
                        }
                    }, 300);
                }
            });
        }

        // 设置导航栏激活状态
        function setActiveNav() {
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.main-nav a');
            
            navLinks.forEach(link => {
                const linkPage = link.getAttribute('href');
                if (linkPage === currentPage) {
                    link.classList.add('active');
                } else {
                    link.classList.remove('active');
                }
            });
        }
    </script>
</body>
</html>