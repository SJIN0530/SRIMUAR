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

// 获取最新的10条评价用于首页轮播
$sql = "SELECT name, comment, rating, created_at 
        FROM comments 
        WHERE status = 'approved' 
        ORDER BY created_at DESC 
        LIMIT 10";
$result = $conn->query($sql);

$testimonialsData = [];
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        // 获取头像首字母
        $row['avatar'] = mb_substr($row['name'], 0, 1, 'utf-8');
        $testimonialsData[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="zh-MY">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SRI MUAR 皇城驾驶学院</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome 图标 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <!-- 设计方面 -->
    <style>
        :root 
        {
            --primary-blue: #0056b3;
            --secondary-orange: #FF6B00;
            --light-gray: #f8f9fa;
            --dark-gray: #333333;
        }

        /* ==================== */
        /* 上网报名横幅 */
        /* ==================== */
        .promo-banner 
        {
            background: linear-gradient(90deg, #FF6B00, #FF8C42, #FF6B00);
            color: white;
            padding: 12px 0;
            text-align: center;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(255, 107, 0, 0.3);
            z-index: 1001;
            animation: bannerPulse 2s infinite alternate;
        }

        .promo-banner::before 
        {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transform: translateX(-100%);
            animation: shine 3s infinite;
        }

        .promo-content 
        {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
            position: relative;
            z-index: 2;
        }

        .promo-icon 
        {
            font-size: 1.5rem;
            animation: bounce 2s infinite;
        }

        .promo-text 
        {
            font-size: 1.1rem;
            font-weight: 600;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .promo-btn 
        {
            background: white;
            color: #FF6B00 !important;
            padding: 6px 20px !important;
            border-radius: 25px !important;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            border: 2px solid white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.2);
        }

        .promo-btn:hover 
        {
            background: rgba(255,255,255,0.9);
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0,0,0,0.3);
            color: #FF4500 !important;
        }

        /* 横幅动画 */
        @keyframes bannerPulse 
        {
            0% 
            {
                box-shadow: 0 4px 12px rgba(255, 107, 0, 0.3);
            }
            100% 
            {
                box-shadow: 0 4px 20px rgba(255, 107, 0, 0.5);
            }
        }

        @keyframes shine 
        {
            100% 
            {
                transform: translateX(100%);
            }
        }

        @keyframes bounce 
        {
            0%, 100% 
            {
                transform: translateY(0);
            }
            50% 
            {
                transform: translateY(-5px);
            }
        }

        /* 响应式调整 */
        @media (max-width: 768px) 
        {
            .promo-content 
            {
                flex-direction: column;
                gap: 10px;
            }
            
            .promo-text 
            {
                font-size: 0.95rem;
            }
            
            .promo-btn 
            {
                padding: 5px 15px !important;
                font-size: 0.9rem;
            }
        }

        /* ==================== */
        /* 证书图片展示区 */
        /* ==================== */
        .certificates-section 
        {
            padding: 50px 0 60px 0;
            background: white;
        }

        .certificates-header 
        {
            text-align: center;
            margin-bottom: 30px;
        }

        .certificates-small-title 
        {
            display: inline-block;
            background: linear-gradient(135deg, var(--primary-blue) 0%, #003d82 100%);
            color: white;
            padding: 10px 25px;
            border-radius: 50px;
            font-size: 1.1rem;
            font-weight: 600;
            letter-spacing: 1px;
            box-shadow: 0 5px 15px rgba(0,86,179,0.2);
            border: 2px solid rgba(255,255,255,0.3);
        }

        .certificates-small-title i 
        {
            margin-right: 8px;
            color: #FFD700;
        }

        .certificates-container 
        {
            display: flex;
            flex-wrap: wrap;
            justify-content: space-between;
            gap: 30px;
        }

        .certificate-item 
        {
            flex: 0 0 calc(50% - 15px);
            background: white;
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 15px 40px rgba(0,0,0,0.12);
            border: 1px solid #e9ecef;
            transition: all 0.4s ease;
            padding: 25px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .certificate-item:hover 
        {
            transform: translateY(-10px);
            box-shadow: 0 20px 50px rgba(0,86,179,0.2);
            border-color: var(--primary-blue);
        }

        .certificate-image 
        {
            max-width: 100%;
            max-height: 350px;
            object-fit: contain;
            transition: transform 0.4s ease;
            border-radius: 8px;
        }

        .certificate-item:hover .certificate-image 
        {
            transform: scale(1.02);
        }

        /* 响应式调整 */
        @media (max-width: 768px) 
        {
            .certificates-container 
            {
                flex-direction: column;
            }
            
            .certificate-item 
            {
                flex: 0 0 100%;
            }
            
            .certificate-image 
            {
                max-height: 300px;
            }
            
            .certificates-small-title 
            {
                font-size: 1rem;
                padding: 8px 20px;
            }
        }

        @media (max-width: 576px) 
        {
            .certificate-image 
            {
                max-height: 250px;
            }
            
            .certificate-item 
            {
                padding: 20px;
            }
            
            .certificates-small-title 
            {
                font-size: 0.9rem;
                padding: 6px 16px;
            }
        }

        /* 基础样式 */
        body 
        {
            font-family: 'Microsoft YaHei', 'Segoe UI', sans-serif;
            color: var(--dark-gray);
            padding-top: 10px;
            background-color: #f9f9f9;
        }

        /* 顶部导航栏 */
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

        /* Logo 样式 */
        .logo-container
        {
            display: flex;
            align-items: center;
            height: auto;
            padding: 0;
        }

        .logo-img
        {
            height: 160px;
            width: auto;
            object-fit: contain;
            transition: all 0.3s ease;
            border: none;
            background: transparent;
            border-radius: 0;
            box-shadow: none;
            max-width: 100%;
        }

        /* 导航菜单容器 */
        .nav-menu-container
        {
            display: flex;
            align-items: center;
            height: 100px;
            padding-left: 0;
        }

        .text-primary
        {
            color: var(--primary-blue) !important;
        }

        .text-secondary-orange
        {
            color: var(--secondary-orange) !important;
        }

        @media(max-width: 992px)
        {
            .logo-container
            {
                justify-content: center;
                margin-bottom: 15px;
                height: auto;
            }

            .logo-img
            {
                height: 140px;
                width: auto;
                max-width: 100%;
            }

            .nav-menu-container
            {
                height: auto;
                justify-content: center;
            }

            .main-nav
            {
                justify-content: center;
                gap: 15px;
                padding-left: 0;
            }
        }

        @media(max-width: 768px)
        {
            .logo-img
            {
                height: 120px;
                width: auto;
                max-width: 100%;
            }

            .main-nav
            {
                gap: 8px;
            }

            .main-nav li:not(:last-child) a
            {
                font-size: 0.85rem;
                padding: 5px 8px;
            }
        }

        /* 主导航菜单 */
        .main-nav 
        {
            display: flex;
            gap: 25px;
            list-style: none;
            margin: 0;
            padding: 0;
            padding-left: 0;
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

        /* 立即报名按钮 */
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

        /* 管理员登录按钮 */
        .admin-btn
        {
            background: #6c757d;
            color: white !important;
            padding: 8px 20px !important;
            border-radius: 25px !important;
            margin-left: 10px;
            border: none;
            transition: all 0.3s;
        }

        .admin-btn:hover
        {
            background: #5a6268 !important;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        /* 课程分类按钮 */
        .course-categories 
        {
            background: url('background.jpg') no-repeat center center;
            background-size: cover;
            padding: 40px 0;
            margin-top: 20px;
            position: relative;
        }

        .course-categories::before 
        {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255,255,255,0.9);
            z-index: 1;
        }

        .course-categories .container 
        {
            position: relative;
            z-index: 2;
        }

        .category-btn 
        {
            background: white;
            border: 2px solid #ddd;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s;
            height: 100%;
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 3;
        }

        .category-btn:hover 
        {
            border-color: var(--primary-blue);
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,86,179,0.1);
        }

        .category-icon 
        {
            font-size: 3.5rem;
            color: var(--primary-blue);
            margin-bottom: 20px;
        }

        /* 图标展示区域 */
        .icon-showcase 
        {
            padding: 50px 0;
            background: white;
        }

        .icon-item 
        {
            text-align: center;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .icon-item i 
        {
            font-size: 3rem;
            color: var(--primary-blue);
            margin-bottom: 20px;
        }

        /* 学院地址地图部分 */
        .address-map-section 
        {
            padding: 80px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
        }

        .map-container 
        {
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            border: 3px solid white;
        }

        .map-title 
        {
            color: var(--primary-blue);
            margin-bottom: 10px;
            font-size: 1.8rem;
            font-weight: 600;
        }

        .map-subtitle 
        {
            color: var(--dark-gray);
            margin-bottom: 30px;
            font-size: 1.1rem;
        }

        .address-card 
        {
            background: white;
            border-radius: 10px;
            padding: 30px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            height: 100%;
        }

        .address-icon 
        {
            font-size: 2.5rem;
            color: var(--secondary-orange);
            margin-bottom: 20px;
        }

        .address-details 
        {
            line-height: 1.8;
        }

        .address-details p 
        {
            margin-bottom: 15px;
            display: flex;
            align-items: flex-start;
        }

        .address-details i 
        {
            margin-top: 5px;
            min-width: 24px;
            color: var(--primary-blue);
            margin-right: 10px;
        }

        .directions-btn 
        {
            background: var(--secondary-orange);
            color: white;
            padding: 12px 25px;
            border-radius: 25px;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            transition: all 0.3s;
            font-weight: 500;
            margin-top: 20px;
        }

        .directions-btn:hover 
        {
            background: #e55a00;
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(255,107,0,0.3);
            color: white;
        }

        /*学院的建筑物照片*/
        .photo-carousel-section
        {
            padding: 40px 0 60px 0;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            margin: 20px 0 40px 0;
        }

        .carousel-item img
        {
            width: 100%;
            height: 500px;
            object-fit: cover;
        }

        .carousel-caption
        {
            background: rgba(0, 0, 0, 0.6);
            padding: 15px 25px;
            border-radius: 10px;
            right: 10%;
            left: 10%;
            bottom: 30px;
        }

        .carousel-caption h5 
        {
            color: var(--secondary-orange);
            font-size: 1.5rem;
            margin-bottom: 5px;
        }

        .carousel-indicators button 
        {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background-color: rgba(255,255,255,0.5);
            border: 2px solid transparent;
        }

        .carousel-indicators button.active 
        {
            background-color: var(--primary-blue);
            border-color: white;
        }

        .carousel-control-prev,
        .carousel-control-next 
        {
            width: 50px;
            height: 50px;
            background: rgba(0,0,0,0.3);
            border-radius: 50%;
            top: 50%;
            transform: translateY(-50%);
            margin: 0 20px;
        }

        .carousel-control-prev:hover,
        .carousel-control-next:hover 
        {
            background: rgba(0,0,0,0.6);
        }

        /* ==================== */
        /* 客户评价轮播样式 - 修改版，不显示课程 */
        /* ==================== */
        .testimonials 
        {
            background: var(--light-gray);
            padding: 60px 0;
            overflow: hidden;
            position: relative;
        }

        .testimonials-container 
        {
            position: relative;
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            overflow: hidden;
        }

        .testimonials-title 
        {
            text-align: center;
            margin-bottom: 40px;
            color: var(--primary-blue);
            font-weight: 600;
        }

        .testimonials-track 
        {
            display: flex;
            gap: 30px;
            animation: slide 25s linear infinite;
            padding: 10px 0;
        }

        .testimonials-track:hover 
        {
            animation-play-state: paused;
        }

        .testimonial-card 
        {
            background: white;
            border-radius: 10px;
            padding: 25px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.05);
            min-width: 350px;
            flex-shrink: 0;
            transition: transform 0.3s ease;
            border: 1px solid rgba(0, 86, 179, 0.1);
            position: relative;
        }

        .testimonial-card:hover 
        {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
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

        .testimonial-text 
        {
            font-style: italic;
            line-height: 1.6;
            margin-bottom: 20px;
            color: var(--dark-gray);
            min-height: 80px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .testimonial-author 
        {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 10px;
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
            text-align: right;
            color: #999;
            font-size: 0.85rem;
            margin-top: 5px;
        }

        @keyframes slide 
        {
            0% 
            {
                transform: translateX(0);
            }
            100% 
            {
                transform: translateX(calc(-380px * <?php echo min(5, count($testimonialsData)); ?>));
            }
        }

        /* ==================== */
        /* 穿着礼仪守则 */
        /* ==================== */
        .dress-code-section 
        {
            background: white;
            padding: 40px 0;
        }

        .dress-code-title 
        {
            color: var(--primary-blue);
            text-align: center;
            margin-bottom: 30px;
            font-weight: 600;
        }

        .dress-code-container 
        {
            max-width: 800px;
            margin: 0 auto;
        }

        .dress-code-image 
        {
            width: 100%;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .dress-code-note 
        {
            text-align: center;
            color: #666;
            font-size: 0.9rem;
            margin-top: 15px;
        }

        /* 响应式调整 */
        @media (max-width: 768px) 
        {
            .testimonials-track 
            {
                gap: 20px;
                animation: slide 20s linear infinite;
            }
            
            .testimonial-card 
            {
                min-width: 280px;
                padding: 20px;
            }
            
            .dress-code-section 
            {
                padding: 30px 0;
            }
            
            .dress-code-title 
            {
                font-size: 1.5rem;
                margin-bottom: 20px;
            }
        }

        @media (max-width: 576px) 
        {
            .testimonials-track 
            {
                gap: 15px;
            }
            
            .testimonial-card 
            {
                min-width: 250px;
                padding: 15px;
            }
            
            .author-avatar 
            {
                width: 40px;
                height: 40px;
                font-size: 1rem;
            }
        }

        .testimonials::before,
        .testimonials::after 
        {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            width: 100px;
            z-index: 2;
            pointer-events: none;
        }

        .testimonials::before 
        {
            left: 0;
            background: linear-gradient(to right, var(--light-gray), transparent);
        }

        .testimonials::after 
        {
            right: 0;
            background: linear-gradient(to left, var(--light-gray), transparent);
        }

        @media (max-width: 768px) 
        {
            .testimonials::before,
            .testimonials::after 
            {
                width: 50px;
            }
        }

        /* 课程展示区 */
        .courses-section 
        {
            padding: 60px 0;
        }

        .course-card 
        {
            border: 1px solid #eee;
            border-radius: 10px;
            overflow: hidden;
            transition: transform 0.3s;
            height: 100%;
            text-align: center;
            background: white;
        }

        .course-card:hover 
        {
            transform: translateY(-10px);
            box-shadow: 0 15px 30px rgba(0,0,0,0.1);
        }

        .course-image 
        {
            height: 220px;
            overflow: hidden;
        }

        .motorcycle-image,
        .car-image
        {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* 页脚 */
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

        /* 响应式调整 */
        @media (max-width: 992px) 
        {
            .carousel-item img 
            {
                height: 400px;
            }
        }

        @media (max-width: 768px) 
        {
            .photo-carousel-section 
            {
                padding: 30px 0 40px 0;
            }
            
            .carousel-item img 
            {
                height: 300px;
            }
            
            .carousel-caption 
            {
                display: none !important;
            }
            
            .carousel-control-prev,
            .carousel-control-next 
            {
                width: 40px;
                height: 40px;
                margin: 0 10px;
            }

            .main-nav 
            {
                gap: 10px;
                flex-wrap: wrap;
            }
            
            .course-categories 
            {
                padding: 20px 0;
            }
            
            .category-btn 
            {
                padding: 15px;
            }

            .category-icon
            {
                font-size: 2.8rem;
            }

            .icon-item i
            {
                font-size: 2.5rem;
            }

            .course-image
            {
                height: 180px;
            }
            
            .address-map-section 
            {
                padding: 50px 0;
            }
            
            .address-card 
            {
                margin-top: 30px;
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
            
            .contact-info p:last-child 
            {
                align-items: center;
                justify-content: center;
                text-align: center;
            }
            
            .social-icons 
            {
                justify-content: center;
            }
            
            .footer-links a:hover 
            {
                padding-left: 0;
            }
            
            .contact-info p:nth-child(2) a 
            {
                word-break: break-word;
            }
        }

        @media (max-width: 576px) 
        {
            .carousel-item img 
            {
                height: 250px;
            }

            .logo-img
            {
                height: 100px;
                width: auto;
                max-width: 100%;
            }

            .main-nav
            {
                gap: 5px;
            }

            .main-nav li:not(:last-child) a
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
            
            .contact-info p:last-child 
            {
                flex-wrap: wrap;
                justify-content: center;
                align-items: center;
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

        /* 评价为空时显示 */
        .no-testimonials 
        {
            text-align: center;
            padding: 40px;
            background: white;
            border-radius: 10px;
            color: #666;
        }

        .no-testimonials i 
        {
            font-size: 3rem;
            color: #ddd;
            margin-bottom: 15px;
        }

        .view-all-link 
        {
            display: block;
            text-align: center;
            margin-top: 30px;
        }

        .view-all-link a 
        {
            color: var(--primary-blue);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s;
        }

        .view-all-link a:hover 
        {
            color: var(--secondary-orange);
        }
    </style>
</head>
<body>
    <!-- ==================== -->
    <!-- 上网报名横幅 -->
    <!-- ==================== -->
    <div class="promo-banner">
        <div class="container">
            <div class="promo-content">
                <div class="promo-icon">
                    <i class="fas fa-bullhorn"></i>
                </div>
                <div class="promo-text">
                    我们鼓励上网报名！立即在线注册，享受更快捷的报名流程和专属优惠！
                </div>
                <a href="register.php" class="promo-btn">
                    <i class="fas fa-mouse-pointer me-2"></i>立即在线报名
                </a>
            </div>
        </div>
    </div>

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
                            <li><a href="index.php" class="active">首页</a></li>
                            <li><a href="courses.html">课程</a></li>
                            <li><a href="products.html">配套</a></li>
                            <li><a href="contact.html">联系我们</a></li>
                            <li><a href="aboutus.html">学院简介</a></li>
                            <li><a href="picture.html">学院图集</a></li>
                            <li><a href="comment.php">客户评价</a></li>
                            <li>
                                <a href="admin_login.html" class="admin-btn">
                                    <i class="fas fa-user-shield me-1"></i> 管理员登录
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
    <!-- 课程分类 -->
    <!-- ==================== -->
    <section class="course-categories">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-md-5 col-sm-6 mb-4">
                    <div class="category-btn">
                        <div class="category-icon">
                            <i class="fas fa-motorcycle"></i>
                        </div>
                        <h3>摩托车课程</h3>
                        <p class="text-muted">B2 / B Full 驾驶执照</p>
                        <a href="#motorcycle-courses" class="btn btn-outline-primary mt-3">查看课程详情</a>
                    </div>
                </div>
                
                <div class="col-md-5 col-sm-6 mb-4">
                    <div class="category-btn">
                        <div class="category-icon">
                            <i class="fas fa-car"></i>
                        </div>
                        <h3>汽车课程</h3>
                        <p class="text-muted">手动挡 D / 自动挡 DA</p>
                        <a href="#car-courses" class="btn btn-outline-primary mt-3">查看课程详情</a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== -->
    <!-- 优势展示图标 -->
    <!-- ==================== -->
    <section class="icon-showcase">
        <div class="container">
            <div class="row text-center">
                <div class="col-md-3 col-6">
                    <div class="icon-item">
                        <i class="fas fa-road"></i>
                        <h5>宽敞训练场</h5>
                        <p class="small text-muted">7英亩训练场地</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="icon-item">
                        <i class="fas fa-chart-line"></i>
                        <h5>高通过率</h5>
                        <p class="small text-muted">考试一次通过</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="icon-item">
                        <i class="fas fa-users"></i>
                        <h5>经验教练</h5>
                        <p class="small text-muted">60+专业教练</p>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="icon-item">
                        <i class="fas fa-shuttle-van"></i>
                        <h5>交通接送</h5>
                        <p class="small text-muted">麻坡区接送</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== -->
    <!-- 证书图片展示区 -->
    <!-- ==================== -->
    <section class="certificates-section">
        <div class="container">
            <div class="certificates-header">
                <span class="certificates-small-title">
                    <i class="fas fa-certificate"></i> JPJ 官方认证文凭
                </span>
            </div>
            
            <div class="certificates-container">
                <div class="certificate-item">
                    <img src="sijil1.jpeg" alt="教练培训课程证书" class="certificate-image">
                </div>
                
                <div class="certificate-item">
                    <img src="sijil2.jpeg" alt="星级评级证书" class="certificate-image">
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== -->
    <!-- 学院照片轮播展示 -->
    <!-- ==================== -->
    <section class="photo-carousel-section">
        <div class="container">
            <h2 class="text-center mb-4">学院环境展示</h2>
            <p class="text-center text-muted mb-5">参观我们的7英亩宽敞训练场地和现代化设施</p>
            
            <div id="campusCarousel" class="carousel slide" data-bs-ride="carousel">
                <div class="carousel-indicators">
                    <button type="button" data-bs-target="#campusCarousel" data-bs-slide-to="0" class="active"></button>
                    <button type="button" data-bs-target="#campusCarousel" data-bs-slide-to="1"></button>
                    <button type="button" data-bs-target="#campusCarousel" data-bs-slide-to="2"></button>
                    <button type="button" data-bs-target="#campusCarousel" data-bs-slide-to="3"></button>
                    <button type="button" data-bs-target="#campusCarousel" data-bs-slide-to="4"></button>
                    <button type="button" data-bs-target="#campusCarousel" data-bs-slide-to="5"></button>
                    <button type="button" data-bs-target="#campusCarousel" data-bs-slide-to="6"></button>
                    <button type="button" data-bs-target="#campusCarousel" data-bs-slide-to="7"></button>
                    <button type="button" data-bs-target="#campusCarousel" data-bs-slide-to="8"></button>
                    <button type="button" data-bs-target="#campusCarousel" data-bs-slide-to="9"></button>
                </div>
                
                <div class="carousel-inner rounded-3 overflow-hidden">
                    <div class="carousel-item active">
                        <img src="BLOK A.JPG" alt="学院A座建筑物" class="d-block w-100">
                        <div class="carousel-caption d-none d-md-block">
                            <h5>学院A座主楼</h5>
                            <p>现代化的办公和教学设施</p>
                        </div>
                    </div>
                    
                    <div class="carousel-item">
                        <img src="BLOK A 1.JPG" alt="学院A座内部" class="d-block w-100">
                        <div class="carousel-caption d-none d-md-block">
                            <h5>学院A座内部</h5>
                            <p>舒适的服务和等候区域</p>
                        </div>
                    </div>
                    
                    <div class="carousel-item">
                        <img src="BLOK B.jpg" alt="学院B座建筑物" class="d-block w-100">
                        <div class="carousel-caption d-none d-md-block">
                            <h5>学院B座训练楼</h5>
                            <p>专门用于理论教学和模拟训练</p>
                        </div>
                    </div>
                    
                    <div class="carousel-item">
                        <img src="BLOK B2.jpg" alt="学院B座侧面" class="d-block w-100">
                        <div class="carousel-caption d-none d-md-block">
                            <h5>B座侧面视角</h5>
                            <p>宽敞明亮的训练空间</p>
                        </div>
                    </div>
                    
                    <div class="carousel-item">
                        <img src="BLOK C1.jpg" alt="学院C座建筑物" class="d-block w-100">
                        <div class="carousel-caption d-none d-md-block">
                            <h5>学院C座设施</h5>
                            <p>专业的驾驶训练设施</p>
                        </div>
                    </div>
                    
                    <div class="carousel-item">
                        <img src="BLOK C2.jpg" alt="学院C座细节" class="d-block w-100">
                        <div class="carousel-caption d-none d-md-block">
                            <h5>C座细节展示</h5>
                            <p>精心设计的训练场地</p>
                        </div>
                    </div>
                    
                    <div class="carousel-item">
                        <img src="BLOK C3.jpg" alt="学院C座全景" class="d-block w-100">
                        <div class="carousel-caption d-none d-md-block">
                            <h5>C座全景</h5>
                            <p>7英亩宽敞训练场地的一部分</p>
                        </div>
                    </div>
                    
                    <div class="carousel-item">
                        <img src="BLOK D1.jpg" alt="学院D座建筑物" class="d-block w-100">
                        <div class="carousel-caption d-none d-md-block">
                            <h5>学院D座设施</h5>
                            <p>高级训练和特殊课程区域</p>
                        </div>
                    </div>
                    
                    <div class="carousel-item">
                        <img src="BLOK D2.jpg" alt="学院D座侧景" class="d-block w-100">
                        <div class="carousel-caption d-none d-md-block">
                            <h5>D座侧景</h5>
                            <p>完善的后勤和车辆维护设施</p>
                        </div>
                    </div>
                    
                    <div class="carousel-item">
                        <img src="pintu.jpeg" alt="学院大门" class="d-block w-100">
                        <div class="carousel-caption d-none d-md-block">
                            <h5>学院大门入口</h5>
                            <p>欢迎光临SRI MUAR皇城驾驶学院</p>
                        </div>
                    </div>
                </div>
                
                <button class="carousel-control-prev" type="button" data-bs-target="#campusCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">上一张</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#campusCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">下一张</span>
                </button>
            </div>
        </div>
    </section>

    <!-- ==================== -->
    <!-- 客户评价 - 从数据库获取，不显示课程 -->
    <!-- ==================== -->
    <section class="testimonials">
        <div class="container">
            <h2 class="testimonials-title">学员真实评价</h2>
            
            <div class="testimonials-container">
                <?php if (empty($testimonialsData)): ?>
                    <div class="no-testimonials">
                        <i class="far fa-comment-alt"></i>
                        <h4>暂无评价</h4>
                        <p>成为第一个分享学习体验的学员吧！</p>
                    </div>
                <?php else: ?>
                    <div class="testimonials-track" id="testimonialsTrack">
                        <?php foreach ($testimonialsData as $testimonial): 
                            // 确保评分是数字类型
                            $rating = floatval($testimonial['rating']);
                            
                            // 生成星星HTML
                            $starsHTML = '';
                            $fullStars = floor($rating);
                            $hasHalfStar = ($rating - $fullStars) >= 0.5;
                            
                            for ($i = 1; $i <= 5; $i++) {
                                if ($i <= $fullStars) {
                                    $starsHTML .= '<i class="fas fa-star"></i>';
                                } elseif ($i == $fullStars + 1 && $hasHalfStar) {
                                    $starsHTML .= '<i class="fas fa-star-half-alt"></i>';
                                } else {
                                    $starsHTML .= '<i class="far fa-star"></i>';
                                }
                            }
                        ?>
                            <div class="testimonial-card">
                                <div class="stars"><?php echo $starsHTML; ?></div>
                                <p class="testimonial-text">"<?php echo htmlspecialchars($testimonial['comment']); ?>"</p>
                                <div class="testimonial-author">
                                    <div class="author-avatar"><?php echo htmlspecialchars($testimonial['avatar']); ?></div>
                                    <div class="author-info">
                                        <h4><?php echo htmlspecialchars($testimonial['name']); ?></h4>
                                    </div>
                                </div>
                                <div class="testimonial-date">
                                    <?php echo date('Y-m-d', strtotime($testimonial['created_at'])); ?>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="view-all-link">
                        <a href="comment.php">
                            查看全部评价 <i class="fas fa-arrow-right ms-1"></i>
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- ==================== -->
    <!-- 摩托车课程专区 -->
    <!-- ==================== -->
    <section id="motorcycle-courses" class="courses-section">
        <div class="container">
            <h2 class="text-center mb-5">摩托车课程</h2>
            
            <div class="row g-4 justify-content-center">
                <div class="col-md-5">
                    <div class="course-card">
                        <div class="course-image">
                            <img src="B2.png" alt="摩托车B2课程" class="motorcycle-image">
                        </div>
                        <div class="p-4">
                            <h4>摩托车 B2</h4>
                            <p class="text-muted">适合初学者，学习基本摩托车驾驶技能</p>
                            <ul class="list-unstyled mb-4 text-start">
                                <li><i class="fas fa-check text-success me-2"></i>基本平衡与控制训练</li>
                                <li><i class="fas fa-check text-success me-2"></i>道路安全知识教学</li>
                                <li><i class="fas fa-check text-success me-2"></i>交通规则理论学习</li>
                                <li><i class="fas fa-check text-success me-2"></i>考试技巧和模拟训练</li>
                                <li><i class="fas fa-check text-success me-2"></i>安全驾驶习惯培养</li>
                            </ul>
                            <a href="contact.html" class="btn btn-outline-primary w-100">咨询B2课程</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-5">
                    <div class="course-card">
                        <div class="course-image">
                            <img src="B.png" alt="摩托车B Full课程" class="motorcycle-image">
                        </div>
                        <div class="p-4">
                            <h4>摩托车 B Full</h4>
                            <p class="text-muted">大马力摩托车驾驶执照升级课程</p>
                            <ul class="list-unstyled mb-4 text-start">
                                <li><i class="fas fa-check text-success me-2"></i>大马力摩托车操控技巧</li>
                                <li><i class="fas fa-check text-success me-2"></i>高速行驶安全训练</li>
                                <li><i class="fas fa-check text-success me-2"></i>弯道技巧和平衡控制</li>
                                <li><i class="fas fa-check text-success me-2"></i>紧急情况处理训练</li>
                                <li><i class="fas fa-check text-success me-2"></i>长途驾驶注意事项</li>
                            </ul>
                            <a href="contact.html" class="btn btn-outline-danger w-100">咨询B Full升级</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== -->
    <!-- 汽车课程专区 -->
    <!-- ==================== -->
    <section id="car-courses" class="courses-section" style="background-color: #f8f9fa;">
        <div class="container">
            <h2 class="text-center mb-5">汽车课程</h2>
            
            <div class="row g-4 justify-content-center">
                <div class="col-md-5">
                    <div class="course-card">
                        <div class="course-image">
                            <img src="D.png" alt="手动挡汽车D课程" class="car-image">
                        </div>
                        <div class="p-4">
                            <h4>手动挡汽车 D</h4>
                            <p class="text-muted">传统手动挡汽车驾驶执照课程</p>
                            <ul class="list-unstyled mb-4 text-start">
                                <li><i class="fas fa-check text-success me-2"></i>学习离合器精准控制</li>
                                <li><i class="fas fa-check text-success me-2"></i>掌握换挡时机和技巧</li>
                                <li><i class="fas fa-check text-success me-2"></i>坡道起步和停车训练</li>
                                <li><i class="fas fa-check text-success me-2"></i>节油驾驶技巧学习</li>
                                <li><i class="fas fa-check text-success me-2"></i>可驾驶所有类型汽车</li>
                            </ul>
                            <a href="contact.html" class="btn btn-outline-success w-100">咨询手动挡课程</a>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-5">
                    <div class="course-card">
                        <div class="course-image">
                            <img src="D_auto.png" alt="自动挡汽车DA课程" class="car-image">
                        </div>
                        <div class="p-4">
                            <h4>自动挡汽车 DA</h4>
                            <p class="text-muted">现代自动挡汽车驾驶执照课程</p>
                            <ul class="list-unstyled mb-4 text-start">
                                <li><i class="fas fa-check text-success me-2"></i>操作简单，易于上手</li>
                                <li><i class="fas fa-check text-success me-2"></i>专注于道路驾驶技巧</li>
                                <li><i class="fas fa-check text-success me-2"></i>适合城市交通环境</li>
                                <li><i class="fas fa-check text-success me-2"></i>现代汽车主流配置</li>
                                <li><i class="fas fa-check text-success me-2"></i>女性学员普遍选择</li>
                            </ul>
                            <a href="contact.html" class="btn btn-outline-info w-100">咨询自动挡课程</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- ==================== -->
    <!-- 穿着礼仪守则 -->
    <!-- ==================== -->
    <section class="dress-code-section">
        <div class="container">
            <h2 class="dress-code-title">学员穿着礼仪守则</h2>
            <div class="dress-code-container">
                <img src="pakaian.jpeg" alt="穿着礼仪守则" class="dress-code-image">
                <p class="dress-code-note">
                    请所有学员在参加课程和考试时遵守以上穿着规定
                </p>
            </div>
        </div>
    </section>

    <!-- ==================== -->
    <!-- 学院地址地图部分 -->
    <!-- ==================== -->
    <section class="address-map-section">
        <div class="container">
            <div class="text-center mb-5">
                <h2 class="map-title">学院位置</h2>
                <p class="map-subtitle">欢迎亲临我们的驾驶学院，我们位于便利的位置，提供充足的停车位</p>
            </div>
            
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="map-container">
                        <iframe 
                            src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3196.2385197494975!2d102.61300621554684!3d1.991053198484263!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x31d1ba7130eae53f%3A0x7f9ef355fe30093b!2sPusat%20Latihan%20Memandu%20Sri%20Muar%20Sdn%20Bhd!5e0!3m2!1sen!2smy!4v1707255683921!5m2!1sen!2smy" 
                            width="100%" 
                            height="450" 
                            style="border:0;" 
                            allowfullscreen="" 
                            loading="lazy">
                        </iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>

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
                        <p><i class="fas fa-map-marker-alt me-2"></i> Lot 77, Parit Unas, Jalan Temenggong Ahmad, 84000 Muar, Johor</p>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() 
        {
            // 平滑滚动效果
            document.querySelectorAll('a[href^="#"]').forEach(anchor => 
            {
                anchor.addEventListener('click', function (e) 
                {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    if(targetId === '#') return;
                    
                    const targetElement = document.querySelector(targetId);
                    if(targetElement) 
                    {
                        window.scrollTo
                        ({
                            top: targetElement.offsetTop - 80,
                            behavior: 'smooth'
                        });
                    }
                });
            });
            
            // 返回顶部按钮
            const backToTopBtn = document.getElementById('backToTop');
            
            backToTopBtn.addEventListener('click', () => 
            {
                window.scrollTo
                ({
                    top: 0,
                    behavior: 'smooth'
                });
            });
            
            window.addEventListener('scroll', function() 
            {
                if (window.pageYOffset > 300) 
                {
                    backToTopBtn.style.display = 'flex';
                    setTimeout(() => 
                    {
                        backToTopBtn.style.opacity = '1';
                    }, 10);
                } else 
                {
                    backToTopBtn.style.opacity = '0';
                    setTimeout(() => 
                    {
                        if (window.pageYOffset <= 300) 
                        {
                            backToTopBtn.style.display = 'none';
                        }
                    }, 300);
                }
            });
            
            backToTopBtn.style.display = 'none';
            
            // 设置导航栏激活状态
            const currentPage = window.location.pathname.split('/').pop();
            const navLinks = document.querySelectorAll('.main-nav a');
            navLinks.forEach(link => 
            {
                const linkPage = link.getAttribute('href');
                if(linkPage === currentPage || 
                (currentPage === '' && linkPage === 'index.php')) 
                {
                    link.classList.add('active');
                } else 
                {
                    link.classList.remove('active');
                }
            });
        });
    </script>
</body>
</html>