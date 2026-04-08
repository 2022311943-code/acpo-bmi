<?php
// Prevent Caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();

// Redirect logged-in users to main.php
if (isset($_SESSION['user_id'])) {
    header("Location: main.php");
    exit;
}

$flash_msg = '';
if (isset($_SESSION['flash_error'])) {
    $flash_msg = $_SESSION['flash_error'];
    unset($_SESSION['flash_error']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Angeles City Police Offices - Health & BMI</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <!-- Agrandir font (place Agrandir-Bold.woff2 / .woff in fonts/ folder) -->
    <style>
        @font-face {
            font-family: 'Agrandir';
            src: url('fonts/Agrandir-Bold.woff2') format('woff2'),
                 url('fonts/Agrandir-Bold.woff') format('woff');
            font-weight: bold;
            font-style: normal;
        }
        .btn-acpo { background-color: #33AFFF; border-color: #33AFFF; }
        .btn-acpo:hover { background-color: #2a9ae6; border-color: #2a9ae6; }
        .hero-title { font-family: 'Agrandir', sans-serif; font-weight: bold; font-size: clamp(2rem, 5vw, 6rem); line-height: 1.2; color:rgb(255, 255, 255); max-width: 100%; }
        .hero-title .reveal-word {
            display: inline-block;
            opacity: 0;
            animation: revealWord 0.5s ease forwards;
        }
        .hero-title .reveal-word.d1 { animation-delay: 0.1s; }
        .hero-title .reveal-word.d2 { animation-delay: 0.25s; }
        .hero-title .reveal-word.d3 { animation-delay: 0.4s; }
        .hero-title .reveal-word.d4 { animation-delay: 0.55s; }
        .hero-title .reveal-word.d5 { animation-delay: 0.7s; }
        .hero-title .reveal-word.d6 { animation-delay: 0.85s; }
        @keyframes revealWord {
            from { opacity: 0; transform: translateY(12px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .hero-buttons-fade-in {
            opacity: 0;
            animation: fadeInButtons 0.6s ease 1s forwards;
        }
        @keyframes fadeInButtons {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .btn-hero { padding: 1rem 3rem; font-size: 1.15rem; }
        .btn-text-only {
            background: transparent; border: none; color: #fff;
            position: relative; display: inline-block;
        }
        .btn-text-only::after {
            content: ''; position: absolute; left: 0; bottom: 0;
            height: 3px; width: 0; background: currentColor;
            transition: width 0.35s ease;
        }
        .btn-text-only:hover::after { width: 100%; }
        .btn-text-only:hover { background: transparent; border: none; color: #fff; opacity: 1; }
        .nav-link-underline {
            position: relative; display: inline-block;
        }
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
        .main-bg {
            position: relative;
            background-image: url('images/bg.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-color: #fff;
            min-height: calc(100vh - 180px);
        }
        .main-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background-color: rgba(71, 71, 71, 0.6);
            z-index: 0;
        }
        .main-bg .container,
        .main-bg .container-fluid {
            position: relative;
            z-index: 1;
        }
        /* Header / Nav - same text style as brand */
        .acpo-blue { background-color: #1700ad !important; }
        .acpo-nav .navbar-brand { display: flex; align-items: center; }
        .acpo-header-text {
            font-weight: 700;
            font-size: 1.2rem;
            color: #ffffff;
            font-family: inherit;
            line-height: 1.2;
            letter-spacing: 0.02em;
        }
        .acpo-nav .acpo-header-text { color: #ffffff !important; }
        .acpo-nav .nav-link.acpo-header-text { padding: 0.5rem 0; color: #ffffff !important; }
        .acpo-nav .nav-link.acpo-header-text:hover { color: #e0e0e0 !important; }
        .acpo-nav .navbar-nav { gap: 2.5rem !important; }
        .acpo-nav .navbar-brand { white-space: normal; } /* Allow wrapping on small screens */
        @media (min-width: 992px) {
            .acpo-nav .navbar-nav { gap: 5rem !important; }
        }
        /* Responsive: mobile & tablet */
        @media (max-width: 991.98px) {
            .hero-title { font-size: clamp(1.75rem, 7vw, 3rem); }
            .btn-hero { padding: 1rem 2.5rem; font-size: 1.25rem; }
            .separator-or { font-size: 1.25rem; }
            .acpo-nav .navbar-brand .acpo-header-text,
            .acpo-nav .nav-link.acpo-header-text { font-size: 1rem !important; }
            .acpo-nav .navbar-brand img { height: 50px !important; }
            .main-bg { min-height: calc(100vh - 140px); }
        }
        @media (max-width: 575.98px) {
            .hero-title { font-size: clamp(1.5rem, 6vw, 2.25rem); }
            .btn-hero { padding: 0.9rem 2rem; font-size: 1.1rem; }
            .separator-or { font-size: 1.1rem; }
            .acpo-nav .navbar-brand .acpo-header-text,
            .acpo-nav .nav-link.acpo-header-text { font-size: 0.8rem !important; }
            .acpo-nav .navbar-brand img { height: 36px !important; }
            .acpo-nav .navbar-brand { max-width: calc(100% - 70px); }
            .acpo-nav .bi-list { font-size: 1.8rem !important; }
            @media (max-width: 400px) {
                .acpo-brand-text {
                    display: none;
                }
            }
        }
        .separator-or {
            color:rgb(255, 255, 255) !important;
            font-size: 1.3rem;
            font-weight: bold;
            -webkit-text-fill-color:rgb(255, 255, 255);
        }
        .separator-hr {
            border: none !important;
            border-top: 3px solid rgb(255, 255, 255) !important;
            opacity: 1 !important;
        }
        .btn-text-only {
            background: transparent; border: none; color:rgb(255, 255, 255);
            position: relative; display: inline-block;
        }
        .btn-text-only:hover { background: transparent; border: none; color:rgb(255, 255, 255); opacity: 1; }

        /* ----- Transition Styles ----- */
        body {
            transition: background-color 0.8s cubic-bezier(0.4, 0, 0.2, 1), color 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .card, .acpo-nav, .user-sidebar, .offcanvas, .nav-link-underline, .btn-hero, .form-control, .form-select {
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

        /* ----- Dark Mode Compatibility ----- */
        [data-bs-theme="dark"] body {
            background-color: #121212 !important;
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .main-bg::before {
            background-color: rgba(0, 0, 0, 0.75) !important;
        }
        [data-bs-theme="dark"] .acpo-nav {
            background: #0d005e !important;
            border-bottom: none !important;
            box-shadow: none !important;
        }
        [data-bs-theme="dark"] .hero-title {
            color: #ffffff !important;
        }

        /* ----- Premium Modern Solar-Lunar Toggle ----- */
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
<body class="d-flex flex-column min-vh-100 overflow-x-hidden">
    <!-- Header -->
    <nav class="navbar navbar-expand-lg acpo-blue acpo-nav py-2 py-lg-3">
        <div class="container-fluid px-3 px-lg-4">
            <a href="index.php" class="navbar-brand d-flex align-items-center text-white text-decoration-none ms-0 ms-lg-5 ps-lg-5">
                <img src="images/pnplogo.png" alt="ACPO Logo" class="me-2" style="height: 60px; width: auto;">
                <img src="images/acpologo.png" alt="PNP Logo" class="me-2 me-md-3" style="height: 70px; width: auto;">
                <span class="acpo-header-text acpo-brand-text">ANGELES CITY POLICE OFFICE</span>
            </a>
            <button class="navbar-toggler border-0 px-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list fs-2 text-white"></i>
            </button>
            <div class="collapse navbar-collapse justify-content-end me-3 me-lg-5 pe-lg-5" id="navbarNav">
                <ul class="navbar-nav">
                    <li class="nav-item"><a href="index.php" class="nav-link nav-link-underline active-nav-link acpo-header-text">Home</a></li>
                    <li class="nav-item"><a href="about.php" class="nav-link nav-link-underline acpo-header-text">About</a></li>
                    <li class="nav-item"><a href="contact.php" class="nav-link nav-link-underline acpo-header-text">Contact</a></li>
                    <li class="nav-item ms-lg-3 d-flex align-items-center">
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

    <!-- Main Content -->
    <main class="flex-grow-1 d-flex flex-column main-bg w-100">
        <div class="container-fluid flex-grow-1 d-flex flex-column text-center py-4 py-lg-5 px-3 px-md-4 px-lg-5">
            <h1 class="hero-title fw-bold text-uppercase mb-0 mt-4 mt-lg-5 pt-4 pt-lg-5">
                <span class="reveal-word d1">Personnel</span> <span class="reveal-word d2">Health</span> <span class="reveal-word d3">AND</span> <span class="reveal-word d4">BMI</span><br>
                <span class="reveal-word d5">Monitoring</span> <span class="reveal-word d6">Record</span>
            </h1>
            <div class="flex-grow-1 d-flex flex-column align-items-center justify-content-center">
                <div class="d-flex flex-column align-items-center gap-4 hero-buttons-fade-in">
                    <a href="login.php" class="btn btn-text-only fw-semibold btn-hero">LOG IN</a>
                    <div class="d-flex align-items-center gap-4 w-100 my-2" style="max-width: 320px;">
                        <hr class="flex-grow-1 separator-hr">
                        <span class="separator-or">or</span>
                        <hr class="flex-grow-1 separator-hr">
                    </div>
                    <a href="login.php#register" class="btn btn-text-only fw-semibold btn-hero">REGISTER</a>
                </div>
            </div>
        </div>
    </main>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
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
                    
                    // Add transition class
                    document.body.classList.add('theme-transition-active');
                    
                    htmlElement.setAttribute('data-bs-theme', newTheme);
                    localStorage.setItem('theme', newTheme);
                    
                    // Remove transition class after animation
                    setTimeout(() => {
                        document.body.classList.remove('theme-transition-active');
                    }, 800);
                });
            }
        });
    </script>
</body>
    <?php if ($flash_msg): ?>
    <script>
        // "Not so fast" Note
        alert("<?php echo addslashes($flash_msg); ?>");
    </script>
    <?php endif; ?>
</body>
</html>
