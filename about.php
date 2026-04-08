<?php
session_start();
$is_logged_in = isset($_SESSION['user_id']);
$is_admin = isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About Us - Angeles City Police Office</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        body {
            transition: background-color 0.8s cubic-bezier(0.4, 0, 0.2, 1), color 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card, .acpo-nav, .user-sidebar, .offcanvas, .reveal-on-scroll, .form-control, .form-select {
            transition: all 0.8s cubic-bezier(0.4, 0, 0.2, 1) !important;
        }
        
        /* Theme reveal animation */
        .theme-transition-active {
            animation: theme-reveal 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        @keyframes theme-reveal {
            0% { opacity: 0.9; transform: scale(0.995); filter: brightness(1.2); }
            100% { opacity: 1; transform: scale(1); filter: brightness(1); }
        }

        .acpo-blue { background-color: #1700ad !important; }
        .acpo-nav .navbar-brand { display: flex; align-items: center; }
        .acpo-header-text {
            font-weight: 700;
            font-size: 1.2rem;
            color: #212529;
            font-family: inherit;
            line-height: 1.2;
            letter-spacing: 0.02em;
        }
        .acpo-nav {
            position: relative;
            z-index: 1030;
        }
        .acpo-nav .acpo-header-text { color: #ffffff !important; }
        .acpo-nav .nav-link.acpo-header-text { padding: 0.5rem 0; color: #ffffff !important; }
        .acpo-nav .nav-link.acpo-header-text:hover { color: #e0e0e0 !important; }
        .acpo-nav .navbar-nav { gap: 2.5rem !important; }
        @media (min-width: 992px) { .acpo-nav .navbar-nav { gap: 5rem !important; } }
        @media (max-width: 991.98px) {
            .acpo-nav .navbar-brand .acpo-header-text,
            .acpo-nav .nav-link.acpo-header-text {
                font-size: 0.9rem !important;
            }
            .acpo-nav .navbar-brand img {
                height: 45px !important;
            }
            /* Reduce brand spacing on smaller screens */
            .acpo-brand-container {
                margin-left: 0.5rem !important;
                padding-left: 0 !important;
            }
            .acpo-btn-container {
                margin-right: 0.5rem !important;
                padding-right: 0 !important;
            }
            .hamburger-icon {
                font-size: 2rem !important;
            }
        }
        @media (max-width: 575.98px) {
            .acpo-nav .navbar-brand .acpo-header-text,
            .acpo-nav .nav-link.acpo-header-text {
                font-size: 0.8rem !important;
            }
            .acpo-nav .navbar-brand img {
                height: 38px !important;
            }
            .hamburger-icon {
                font-size: 1.8rem !important;
            }
            /* Possible hide brand text if it's still too long on very narrow screens */
            @media (max-width: 400px) {
                .acpo-brand-text {
                    display: none;
                }
            }
        }
        .hamburger-icon {
            font-size: 2.8rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }
        .hamburger-icon:hover {
            color: #e0e0e0 !important;
        }
        .nav-link-underline { position: relative; display: inline-block; }
        .nav-link-underline::after {
            content: ''; position: absolute; left: 0; bottom: -2px;
            height: 3px; width: 0; background: currentColor;
            transition: width 0.35s ease;
        }
        .nav-link-underline:hover::after { width: 100%; }
        
        @keyframes activeLoop {
            0% { width: 20%; opacity: 0.5; }
            50% { width: 100%; opacity: 1; }
            100% { width: 20%; opacity: 0.5; }
        }
        .nav-link-underline.active-nav-link::after {
            content: '';
            position: absolute;
            left: 50%;
            transform: translateX(-50%);
            bottom: -2px;
            height: 3px;
            background: currentColor;
            animation: activeLoop 2.5s ease-in-out infinite;
        } 
        /* ----- Hamburger Menu Sidebar CSS ----- */
        .user-sidebar {
            width: 380px !important;
            border-left: none;
            background: linear-gradient(135deg, #0d005e 0%, #1700ad 100%) !important;
            box-shadow: -10px 0 30px rgba(0,0,0,0.5);
            color: #ffffff;
        }
        .user-sidebar .btn-close {
            filter: invert(1) grayscale(100%) brightness(200%);
            opacity: 0.8;
            transition: opacity 0.3s;
        }
        .user-sidebar .btn-close:hover {
            opacity: 1;
        }
        .user-sidebar .offcanvas-body {
            padding: 2.5rem 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .profile-placeholder {
            width: 100%;
            max-width: 130px;
            aspect-ratio: 1 / 1;
            margin-bottom: 1rem;
            border-radius: 50%;
            padding: 5px;
            background: rgba(255,255,255,0.1);
            box-shadow: 0 8px 25px rgba(0,0,0,0.3);
            transition: transform 0.3s ease;
        }
        .profile-placeholder:hover {
            transform: scale(1.05);
        }
        .profile-placeholder img {
            border: 3px solid #ffffff !important;
            border-radius: 50%;
        }
        .sidebar-name {
            font-family: 'Agrandir', sans-serif;
            font-weight: bold;
            font-size: 1.6rem;
            color: #ffffff;
            margin-bottom: 2.5rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            text-shadow: 0 2px 10px rgba(0,0,0,0.3);
            text-align: center;
        }
        .sidebar-links-container {
            display: flex;
            flex-direction: column;
            align-items: stretch;
            gap: 0.8rem;
            width: 100%;
            margin-bottom: auto;
        }
                .sidebar-links-container a {
            display: flex;
            align-items: center;
            padding: 12px 20px;
            color: rgba(255,255,255,0.85) !important;
            text-decoration: none;
            font-family: 'Agrandir', sans-serif;
            font-weight: 600;
            font-size: 1.1rem;
            border-radius: 12px;
            transition: all 0.3s ease;
            background: rgba(255,255,255,0.03);
            border: 1px solid rgba(255,255,255,0.05);
            margin-bottom: 8px;
        }
        .sidebar-links-container a i {
            font-size: 1.3rem;
            margin-right: 15px;
            color: #33AFFF;
            transition: transform 0.3s ease;
        }
        .sidebar-links-container a:hover, .sidebar-links-container a.active-sidebar-link {
            background: rgba(255,255,255,0.15);
            color: #ffffff !important;
            transform: translateX(5px);
            border-color: rgba(255,255,255,0.2);
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .sidebar-links-container a:hover i, .sidebar-links-container a.active-sidebar-link i {
            transform: scale(1.2);
            color: #fff;
        }
        .logout-btn-container {
            margin-top: 3rem;
            width: 100%;
            display: flex;
            justify-content: center;
        }
        .logout-btn {
            background: linear-gradient(135deg, #ff416c 0%, #ff4b2b 100%);
            color: #fff !important;
            border-radius: 50px;
            font-family: 'Agrandir', sans-serif;
            font-weight: bold;
            font-size: 1.1rem;
            padding: 12px 40px;
            text-decoration: none;
            text-transform: uppercase;
            letter-spacing: 1px;
            box-shadow: 0 8px 20px rgba(255, 65, 108, 0.4);
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
        }
        .logout-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(255, 65, 108, 0.6);
            color: #fff !important;
        }
        .logout-btn i {
            font-size: 1.3rem;
        }
        /* About Page Specific Styles */
        .about-header {
            background-image: url('images/bg.jpg');
            background-size: cover;
            background-position: center;
            background-color: #0d0a2e;
            position: relative;
            padding: 100px 0;
            color: white;
        }
        .about-header::before {
            content: '';
            position: absolute;
            inset: 0;
            background-color: rgba(71, 71, 71, 0.6);
            z-index: 0;
        }
        .about-header > .container {
            position: relative;
            z-index: 1;
        }
        .about-title {
            font-family: 'Agrandir', sans-serif;
            font-weight: bold;
            font-size: clamp(2.5rem, 5vw, 4rem);
            text-transform: uppercase;
            color:rgb(255, 255, 255);
        }
        .about-header .lead {
            color:rgb(255, 255, 255);
            font-weight: 600;
        }
        .text-acpo-primary { color: #1700ad; }
        .bg-acpo-primary { background-color: #1700ad; color: white; }

        /* Scroll Animation Styles */
        .reveal-on-scroll {
            opacity: 0;
            transform: translateY(50px);
            transition: opacity 0.8s ease-out, transform 0.8s ease-out;
            will-change: opacity, transform;
        }
        .reveal-on-scroll.is-visible {
            opacity: 1;
            transform: translateY(0);
        }
        /* Staggered delays for items in a row */
        .delay-100 { transition-delay: 0.1s; }
        .delay-200 { transition-delay: 0.2s; }
        .delay-300 { transition-delay: 0.3s; }
        .delay-300 { transition-delay: 0.3s; }

        /* Dark Mode Compatibility */
        [data-bs-theme="dark"] .bg-white {
            background-color: #1e1e1e !important;
            background: #1e1e1e !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .bg-light {
            background-color: #2d2d2d !important;
            background: #2d2d2d !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .card {
            background-color: #1e1e1e !important;
            background: #1e1e1e !important;
            border-color: #333 !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .text-black, [data-bs-theme="dark"] .text-dark, [data-bs-theme="dark"] .text-primary, [data-bs-theme="dark"] .text-secondary {
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] h1, [data-bs-theme="dark"] h2, [data-bs-theme="dark"] h3, [data-bs-theme="dark"] h4, [data-bs-theme="dark"] h5, [data-bs-theme="dark"] h6, [data-bs-theme="dark"] .acpo-brand-text {
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] [style*="color: #1700ad"], [data-bs-theme="dark"] [style*="color: rgb(23, 0, 173)"] {
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] .acpo-nav {
            border-bottom: none !important;
            box-shadow: none !important;
            background: #0d005e !important;
        }

                /* Premium Modern Solar-Lunar Toggle */
        .theme-switch-wrapper {
            display: flex;
            align-items: center;
        }
        .theme-switch {
            display: inline-block;
            height: 40px;
            position: relative;
            width: 76px;
            user-select: none;
        }
        .theme-switch input {
            display: none;
        }
        .theme-switch-track {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(8px);
            -webkit-backdrop-filter: blur(8px);
            border: 1.5px solid rgba(255, 255, 255, 0.2);
            border-radius: 50px;
            cursor: pointer;
            position: relative;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            width: 100%;
            height: 100%;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        [data-bs-theme="dark"] .theme-switch-track {
            background: rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.1);
            box-shadow: inset 0 2px 10px rgba(0,0,0,0.5);
        }
        .theme-switch-knob {
            background: #ffffff;
            border-radius: 50%;
            height: 32px;
            width: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 4px 10px rgba(0,0,0,0.2);
            position: absolute;
            left: 3px;
            top: 2.5px;
            z-index: 2;
        }
        [data-bs-theme="dark"] .theme-switch-knob {
            transform: translateX(36px);
            background: #2b2e38;
            box-shadow: 0 4px 15px rgba(0,0,0,0.4), 0 0 10px rgba(51, 175, 255, 0.3);
        }
        .theme-switch-knob i {
            font-size: 18px;
            transition: all 0.5s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .sun-icon {
            color: #f39c12;
            transform: rotate(0deg) scale(1);
        }
        .moon-icon {
            color: #33AFFF;
            position: absolute;
            opacity: 0;
            transform: rotate(-90deg) scale(0);
        }
        [data-bs-theme="dark"] .sun-icon {
            opacity: 0;
            transform: rotate(90deg) scale(0);
        }
        [data-bs-theme="dark"] .moon-icon {
            opacity: 1;
            transform: rotate(0deg) scale(1);
        }
        .theme-switch:hover .theme-switch-knob {
            transform: scale(1.05);
        }
        [data-bs-theme="dark"] .theme-switch:hover .theme-switch-knob {
            transform: translateX(36px) scale(1.05);
        }
        .theme-switch:active .theme-switch-knob {
            transform: scale(0.95);
        }
        [data-bs-theme="dark"] .theme-switch:active .theme-switch-knob {
            transform: translateX(36px) scale(0.95);
        }
    </style>
    <script>
        // Apply theme immediately to prevent flicker
        (function() {
            const savedTheme = localStorage.getItem('theme') || 'light';
            document.documentElement.setAttribute('data-bs-theme', savedTheme);
        })();
    </script>
</head>
<body class="d-flex flex-column min-vh-100 overflow-x-hidden bg-light">
    <!-- Header -->
    <?php if ($is_logged_in): ?>
        <nav class="navbar navbar-expand-lg acpo-blue acpo-nav py-2 py-lg-3">
            <div class="container-fluid px-2 px-lg-4">
                <a href="main.php" class="navbar-brand d-flex align-items-center text-white text-decoration-none ms-3 ms-lg-5 ps-lg-5 acpo-brand-container">
                    <img src="images/pnplogo.png" alt="ACPO Logo" class="me-2" style="height: 60px; width: auto;">
                    <img src="images/acpologo.png" alt="PNP Logo" class="me-2 me-md-3" style="height: 70px; width: auto;">
                    <span class="acpo-header-text acpo-brand-text">ANGELES CITY POLICE OFFICE</span>
                </a>
                <div class="d-flex align-items-center justify-content-end me-3 me-lg-5 pe-lg-5 acpo-btn-container">
                    <button class="btn border-0 p-0" type="button" data-bs-toggle="offcanvas" data-bs-target="#userSidebar" aria-controls="userSidebar" aria-label="Toggle user menu" style="cursor: pointer; position: relative; ">
                        <i class="bi bi-list text-white hamburger-icon" style="font-size: 2rem; cursor: pointer; position: relative; "></i>
                    </button>
                </div>
            </div>
        </nav>

        <!-- Hamburger Overlay Menu -->
        <div class="offcanvas offcanvas-end user-sidebar" tabindex="-1" id="userSidebar" aria-labelledby="userSidebarLabel">
            <div class="offcanvas-header pb-0 border-0">
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body pt-0">
                <!-- Profile Placeholder Image -->
                <div class="profile-placeholder d-flex justify-content-center">
                    <img src="images/placeholder.png" alt="Profile Background" class="img-fluid rounded-circle shadow-sm" onerror="this.onerror=null; this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' viewBox=\'0 0 200 200\'><circle cx=\'100\' cy=\'100\' r=\'100\' fill=\'%23e9ecef\'/><path d=\'M100 50 A25 25 0 1 0 100 100 A25 25 0 1 0 100 50 Z M100 110 C70 110 40 130 40 160 A60 60 0 0 0 160 160 C160 130 130 110 100 110 Z\' fill=\'%23adb5bd\'/></svg>'">
                </div>
                
                <h4 class="sidebar-name"><?php echo isset($_SESSION['name']) ? htmlspecialchars($_SESSION['name']) : 'User'; ?></h4>
                
                <!-- Dark Mode Switch in Sidebar -->
                <div class="d-flex justify-content-center mb-4">
                    <div class="theme-switch-wrapper">
                        <label class="theme-switch" for="theme-switch-checkbox">
                            <input type="checkbox" id="theme-switch-checkbox">
                            <div class="theme-switch-track">
                                <div class="theme-switch-knob">
                                    <i class="bi bi-sun-fill sun-icon"></i>
                                    <i class="bi bi-moon-stars-fill moon-icon"></i>
                                </div>
                            </div>
                        </label>
                    </div>
                </div>
                
                <div class="sidebar-links-container">
                    <a href="main.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'main.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-house-door"></i> HOME</a>
                    <a href="editor.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'editor.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-calculator"></i> BMI CALCULATOR</a>
                    <?php if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin'): ?>
                        <a href="admin_users.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'admin_users.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-people"></i> MANAGE ACCOUNTS</a>
                        <a href="audit_logs.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'audit_logs.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-shield-lock"></i> AUDIT LOGS</a>
                    <?php endif; ?>
                    <a href="about.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'about.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-info-circle"></i> ABOUT US</a>
                    <a href="contact.php" class="<?php echo basename($_SERVER['PHP_SELF']) == 'contact.php' ? 'active-sidebar-link' : ''; ?>"><i class="bi bi-envelope"></i> CONTACT US</a>
                    <div class="sidebar-divider"></div>
                    <a href="login.php?action=logout" class="logout-link text-danger"><i class="bi bi-box-arrow-right"></i> LOGOUT</a>
                </div>
            </div>
        </div>
    <?php else: ?>
        <nav class="navbar navbar-expand-lg acpo-blue acpo-nav py-2 py-lg-3">
            <div class="container-fluid px-3 px-lg-4">
                <a href="index.php" class="navbar-brand d-flex align-items-center text-white text-decoration-none ms-0 ms-lg-5 ps-lg-5">
                    <img src="images/pnplogo.png" alt="ACPO Logo" class="me-2" style="height: 60px; width: auto;">
                    <img src="images/acpologo.png" alt="PNP Logo" class="me-2 me-md-3" style="height: 70px; width: auto;">
                    <span class="acpo-header-text acpo-brand-text">ANGELES CITY POLICE OFFICE</span>
                </a>
                <button class="navbar-toggler border-0 px-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                    <i class="bi bi-list fs-1 text-white"></i>
                </button>
                <div class="collapse navbar-collapse justify-content-end me-3 me-lg-5 pe-lg-5" id="navbarNav">
                    <ul class="navbar-nav align-items-center">
                        <li class="nav-item"><a href="index.php" class="nav-link nav-link-underline acpo-header-text">Home</a></li>
                        <li class="nav-item"><a href="about.php" class="nav-link nav-link-underline active-nav-link acpo-header-text">About</a></li>
                        <li class="nav-item"><a href="contact.php" class="nav-link nav-link-underline acpo-header-text">Contact</a></li>
                        <li class="nav-item ms-lg-4 d-flex align-items-center mt-3 mt-lg-0">
                            <div class="theme-switch-wrapper">
                                <label class="theme-switch" for="theme-switch-checkbox">
                                    <input type="checkbox" id="theme-switch-checkbox">
                                    <div class="theme-switch-track">
                                        <div class="theme-switch-knob">
                                            <i class="bi bi-sun-fill sun-icon"></i>
                                            <i class="bi bi-moon-stars-fill moon-icon"></i>
                                        </div>
                                    </div>
                                </label>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    <?php endif; ?>

    <!-- Hero Section -->
    <header class="about-header text-center">
        <div class="container">
            <h1 class="about-title mb-3">About Us</h1>
            <p class="lead mb-0 fs-4">Personnel Health & BMI Monitoring Record</p>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow-1 py-5">
        <div class="container py-4">
            
            <!-- Mission & Vision Section -->
            <div class="row g-4 mb-5">
                <div class="col-md-6 reveal-on-scroll">
                    <div class="card h-100 border-0 shadow-sm rounded-4">
                        <div class="card-body p-4 p-lg-5">
                            <div class="d-flex align-items-center mb-4">
                                <div class="bg-acpo-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                    <i class="bi bi-bullseye fs-4"></i>
                                </div>
                                <h2 class="h3 fw-bold mb-0 text-acpo-primary">Our Mission</h2>
                            </div>
                            <p class="text-secondary fs-5" style="line-height: 1.8;">
                                The PNP shall enforce the law, prevent and control crimes, maintain peace and order, and ensure public safety and internal security with the active support of the community.
                            </p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6 reveal-on-scroll delay-100">
                    <div class="card h-100 border-0 shadow-sm rounded-4">
                        <div class="card-body p-4 p-lg-5">
                            <div class="d-flex align-items-center mb-4">
                                <div class="bg-acpo-primary rounded-circle d-flex align-items-center justify-content-center me-3" style="width: 50px; height: 50px;">
                                    <i class="bi bi-eye fs-4"></i>
                                </div>
                                <h2 class="h3 fw-bold mb-0 text-acpo-primary">Our Vision</h2>
                            </div>
                            <p class="text-secondary fs-5" style="line-height: 1.8;">
                            Imploring the aid of the Almighty, by 2030, We shall be a highly capable, effective, and credible police service working in partnership with a responsive community towards the attainment of a safer place to live, work and do business.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- About the System Section -->
            <div class="row align-items-center mb-5 py-5">
                <div class="col-lg-5 order-2 order-lg-1 reveal-on-scroll">
                    <div class="bg-white p-4 rounded-4 shadow-sm h-100 d-flex align-items-center justify-content-center" style="min-height: 350px;">
                        <i class="bi bi-heart-pulse text-acpo-primary" style="font-size: 8rem; opacity: 0.8;"></i>
                    </div>
                </div>
                <div class="col-lg-7 order-1 order-lg-2 ps-lg-5 mb-4 mb-lg-0 reveal-on-scroll delay-100">
                    <h2 class="fw-bold mb-4 text-acpo-primary display-6">About the System</h2>
                    <p class="text-secondary fs-5 mb-4" style="line-height: 1.8;">
                        The Personnel Health & BMI Monitoring Record is a dedicated platform designed for the Angeles City Police Office. Our goal is to ensure the health and physical readiness of our personnel through consistent monitoring and tracking of Body Mass Index (BMI) and overall health metrics.
                    </p>
                    <p class="text-secondary fs-5" style="line-height: 1.8;">
                        By maintaining accurate health records, we empower our officers to stay in peak physical condition, ready to serve and protect the community with excellence and integrity.
                    </p>
                </div>
            </div>

            <!-- Core Values -->
            <div class="text-center mb-5 pb-4">
                <h2 class="fw-bold mb-5 text-acpo-primary display-6 reveal-on-scroll">Our Core Values</h2>
                <div class="row g-4 justify-content-center">
                    <div class="col-md-6 col-lg-3 reveal-on-scroll delay-100">
                        <div class="card border-0 shadow-sm rounded-4 text-center py-4 h-100">
                            <div class="card-body">
                                <i class="bi bi-heart-fill text-acpo-primary mb-3" style="font-size: 3rem;"></i>
                                <h4 class="fw-bold text-dark mb-2" style="font-size: 1.3rem;">Maka-Diyos</h4>
                                <p class="text-secondary mb-0">(God-fearing)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 reveal-on-scroll delay-200">
                        <div class="card border-0 shadow-sm rounded-4 text-center py-4 h-100">
                            <div class="card-body">
                                <i class="bi bi-flag-fill text-acpo-primary mb-3" style="font-size: 3rem;"></i>
                                <h4 class="fw-bold text-dark mb-2" style="font-size: 1.3rem;">Maka-Bayan</h4>
                                <p class="text-secondary mb-0">(Nationalistic)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 reveal-on-scroll delay-300">
                        <div class="card border-0 shadow-sm rounded-4 text-center py-4 h-100">
                            <div class="card-body">
                                <i class="bi bi-people-fill text-acpo-primary mb-3" style="font-size: 3rem;"></i>
                                <h4 class="fw-bold text-dark mb-2" style="font-size: 1.3rem;">Maka-Tao</h4>
                                <p class="text-secondary mb-0">(Humane)</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6 col-lg-3 reveal-on-scroll" style="transition-delay: 0.4s;">
                        <div class="card border-0 shadow-sm rounded-4 text-center py-4 h-100">
                            <div class="card-body">
                                <i class="bi bi-tree-fill text-acpo-primary mb-3" style="font-size: 3rem;"></i>
                                <h4 class="fw-bold text-dark mb-2" style="font-size: 1.3rem;">Maka-Kalikasan</h4>
                                <p class="text-secondary mb-0">(Environment-friendly)</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry) => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add("is-visible");
                        // Optional: remove observer after animating in
                        // observer.unobserve(entry.target);
                    }
                });
            }, {
                threshold: 0.15,
                rootMargin: "0px 0px -50px 0px"
            });

            document.querySelectorAll(".reveal-on-scroll").forEach((el) => {
                observer.observe(el);
            });
        });
    </script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeSwitch = document.getElementById('theme-switch-checkbox');
            const htmlElement = document.documentElement;

            if (htmlElement.getAttribute('data-bs-theme') === 'dark') {
                if (themeSwitch) themeSwitch.checked = true;
            }

            if (themeSwitch) {
                themeSwitch.addEventListener('change', function() {
                    const newTheme = this.checked ? 'dark' : 'light';
                    
                    // Add animation class
                    document.body.classList.add('theme-transition-active');
                    
                    htmlElement.setAttribute('data-bs-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                    
                    // Remove animation class after it finishes
                    setTimeout(() => {
                        document.body.classList.remove('theme-transition-active');
                    }, 800);
                });
            }
        });
    </script>
</body>
</html>
