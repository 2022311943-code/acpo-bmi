<?php
// Prevent Caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

session_start();
require_once 'db_connection.php';

// Redirect logged-in users to main.php
// (Unless they are actively logging out)
if (isset($_SESSION['user_id']) && !isset($_GET['action'])) {
    header("Location: main.php");
    exit;
}

    $error_msg = '';
    $success_msg = '';
    $show_register = false;

// Handle Logout
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    // Unset all of the session variables
    $_SESSION = array();

    // If it's desired to kill the session, also delete the session cookie.
    // Note: This will destroy the session, and not just the session data!
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }

    session_destroy();
    header("Location: login.php");
    exit;
}

// Handle Login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'login') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error_msg = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT id, username, password, role, name, status FROM users WHERE username = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password'])) {
            // Check Account Status
            if ($user['status'] === 'pending') {
                $error_msg = 'Account is pending approval. Please wait for admin activation.';
            } elseif ($user['status'] === 'disabled') {
                $error_msg = 'This account has been disabled. Contact the administrator.';
            } else {
                // Login Success
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['name'] = $user['name'];

                header("Location: main.php");
                exit;
            }
        } else {
            $error_msg = 'Invalid username or password.';
        }
    }
}

// Handle Register
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'register') {
    $show_register = true;
    $last_name = trim($_POST['last_name']);
    $first_name = trim($_POST['first_name']);
    $middle_name = trim($_POST['middle_name']);
    $suffix = trim($_POST['suffix']);
    $rank = trim($_POST['rank']);
    $gender = $_POST['gender'];
    $unit_input = $_POST['unit'] ?? '';
    $unit = is_array($unit_input) ? implode(", ", array_map('trim', array_filter($unit_input))) : trim($unit_input);
    $birthdayRaw = $_POST['birthday'];
    $birthday = null;
    try {
        $dt = new DateTime($birthdayRaw);
        $birthday = $dt->format('Y-m-d');
    } catch (Exception $e) {
        $birthday = $birthdayRaw;
    }
    $age = isset($_POST['age']) ? (int)$_POST['age'] : null;
    $username = trim($_POST['username']);
    $password = 'Password@1234';

    // Construct full name for legacy 'name' column
    $name_parts = [];
    if (!empty($last_name)) $name_parts[] = $last_name . ",";
    if (!empty($first_name)) $name_parts[] = $first_name;
    if (!empty($middle_name)) $name_parts[] = $middle_name;
    if (!empty($suffix)) $name_parts[] = $suffix;
    $name = trim(implode(" ", $name_parts));

    if ($age !== null && $age < 21) {
        $error_msg = 'You must be at least 21 years old to register.';
    } elseif (empty($last_name) || empty($first_name) || empty($rank) || empty($gender) || empty($birthday) || empty($username)) {
        $error_msg = 'All required fields must be filled.';
    } else {
        // Check if username exists
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            $error_msg = 'Username already taken.';
        } else {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, role, last_name, first_name, middle_name, suffix, name, rank, gender, unit, birthday, age, status) VALUES (?, ?, 'user', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");
            if ($stmt->execute([$username, $hashed_password, $last_name, $first_name, $middle_name, $suffix, $name, $rank, $gender, $unit, $birthday, $age])) {
                $success_msg = 'Registration successful! Please wait for admin approval to log in.';
                $show_register = false;
            } else {
                $error_msg = 'Registration failed. Please try again.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Log In / Register - Angeles City Police Office</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-T3c6CoIi6uLrA9TneNEoa7RxnatzjcDSCmG1MXxSR1GAsXEV/Dwwykc2MPK8M2HN" crossorigin="anonymous">
    
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    
    <style>
        @font-face {
            font-family: 'Agrandir';
            src: url('fonts/Agrandir-Bold.woff2') format('woff2'),
                 url('fonts/Agrandir-Bold.woff') format('woff');
            font-weight: bold;
            font-style: normal;
        }
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
        @media (min-width: 992px) { .acpo-nav .navbar-nav { gap: 5rem !important; } }
        @media (max-width: 991.98px) {
            .acpo-nav .navbar-brand .acpo-header-text,
            .acpo-nav .nav-link.acpo-header-text { font-size: 1rem !important; }
            .acpo-nav .navbar-brand img { height: 50px !important; }
        }
        @media (max-width: 575.98px) {
            .acpo-nav .navbar-brand .acpo-header-text,
            .acpo-nav .nav-link.acpo-header-text { font-size: 0.9rem !important; }
            .acpo-nav .navbar-brand img { height: 42px !important; }
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
        .login-page-bg {
            position: relative;
            background-image: url('images/bg.jpg');
            background-size: cover;
            background-position: center;
            background-color: #0d0a2e;
            min-height: calc(100vh - 120px);
        }
        .login-page-bg::before {
            content: '';
            position: absolute;
            inset: 0;
            background-color: rgba(71, 71, 71, 0.6);
            z-index: 0;
        }
        .login-page-bg .container-fluid { position: relative; z-index: 1; }
        .hero-title-login {
            font-family: 'Agrandir', sans-serif;
            font-weight: bold;
            font-size: clamp(2.25rem, 5.5vw, 4.5rem);
            line-height: 1.2;
            color: #fff;
            text-transform: uppercase;
        }
        .login-card {
            background: rgba(255, 255, 255, 0.92);
            border-radius: 1.5rem;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 420px;
            margin: 0 auto;
            position: relative;
            overflow: hidden;
            transition: height 0.4s ease;
        }
        .login-card .form-label { font-weight: 600; color: #333; font-size: 0.95rem; }
        .login-card .form-control {
            border-radius: 0.5rem;
            border: 1px solid #ced4da;
            padding: 0.75rem 0.9rem;
            font-size: 1rem;
        }
        .login-card .form-check-label { font-size: 0.95rem; color: #555; }
        .acpo-primary { background-color: #1700ad; border-color: #1700ad; color: #fff; }
        .acpo-primary:hover { background-color: #12008a; border-color: #12008a; color: #fff; }
        .text-acpo-primary { color: #1700ad; }
        .login-title { font-size: 1.5rem; font-weight: bold; color: #1700ad; text-transform: uppercase; }
        
        /* Slider & Animation Styles */
        .text-col, .card-col {
            transition: transform 0.6s cubic-bezier(0.25, 0.8, 0.25, 1), opacity 0.6s ease;
        }
        @media (min-width: 992px) {
            .is-register .text-col { transform: translateX(100%); }
            .is-register .card-col { transform: translateX(-100%); }
            .slide-in-start .text-col { transform: translateX(100%); opacity: 0; }
            .slide-in-start .card-col { transform: translateX(-100%); opacity: 0; }
            .slide-in-start-register .text-col { transform: translateX(0); opacity: 0; }
            .slide-in-start-register .card-col { transform: translateX(0); opacity: 0; }
        }
        
        .fade-block {
            transition: opacity 0.4s ease, visibility 0.4s ease;
            width: 100%;
        }
        .fade-block.inactive {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0;
            visibility: hidden;
        }
        .fade-block.active {
            position: relative;
            opacity: 1;
            visibility: visible; 
            transform: translate(0, 0);
        }
        .form-fade {
            padding: 2.5rem 2.75rem;
            width: 100%;
            transition: opacity 0.4s ease, visibility 0.4s ease;
        }
        .form-fade.inactive {
            position: absolute;
            top: 0;
            left: 0;
            opacity: 0;
            visibility: hidden;
        }
        .form-fade.active {
            position: relative;
            opacity: 1;
            visibility: visible;
        }
        
        /* Password Toggle Animation */
        .password-toggle {
            cursor: pointer;
            z-index: 10;
        }
        .password-toggle .transition-icon {
            transition: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
        }
        .password-toggle .icon-hidden {
            opacity: 0;
            transform: translate(-50%, -50%) scale(0) rotate(-90deg);
        }

        /* ----- Transition Styles ----- */
        body {
            transition: background-color 0.8s cubic-bezier(0.4, 0, 0.2, 1), color 0.8s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .login-card, .acpo-nav, .form-control, .form-select, .btn {
            transition: background-color 0.8s cubic-bezier(0.4, 0, 0.2, 1), color 0.8s cubic-bezier(0.4, 0, 0.2, 1), border-color 0.8s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.8s cubic-bezier(0.4, 0, 0.2, 1) !important;
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
        [data-bs-theme="dark"] .login-card {
            background: rgba(30, 30, 30, 0.95) !important;
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
            background: #0d005e !important;
        }
        [data-bs-theme="dark"] .login-card .form-control {
            background-color: #2d2d2d !important;
            border-color: #444 !important;
            color: #ffffff !important;
        }
        [data-bs-theme="dark"] .login-card .form-control::placeholder {
            color: #aaaaaa !important;
            opacity: 1;
        }
        [data-bs-theme="dark"] .login-card .form-label {
            color: #e0e0e0 !important;
        }
        [data-bs-theme="dark"] .login-card .form-check-label {
            color: #cccccc !important;
        }
        [data-bs-theme="dark"] .login-card p.text-secondary {
            color: #bbbbbb !important;
        }
        [data-bs-theme="dark"] .login-card a.text-acpo-primary {
            color: #33AFFF !important; /* brighter blue for dark mode */
            text-shadow: 0 0 10px rgba(51, 175, 255, 0.2);
        }
        [data-bs-theme="dark"] .login-card .alert-danger {
            background-color: rgba(220, 53, 69, 0.2) !important;
            border-color: rgba(220, 53, 69, 0.4) !important;
            color: #ff8e9a !important;
        }
        [data-bs-theme="dark"] .login-card .alert-success {
            background-color: rgba(25, 135, 84, 0.2) !important;
            border-color: rgba(25, 135, 84, 0.4) !important;
            color: #75f0a0 !important;
        }

        [data-bs-theme="dark"] .acpo-primary {
            background-color: #33AFFF !important;
            border-color: #33AFFF !important;
            color: #000000 !important;
        }
        [data-bs-theme="dark"] .acpo-primary:hover {
            background-color: #2a9ae6 !important;
            border-color: #2a9ae6 !important;
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
    <!-- Header (same as index) -->
    <nav class="navbar navbar-expand-lg acpo-blue acpo-nav py-2 py-lg-3">
        <div class="container-fluid px-3 px-lg-4">
            <a href="index.php" class="navbar-brand d-flex align-items-center text-white text-decoration-none ms-3 ms-lg-5 ps-lg-5">
                <img src="images/pnplogo.png" alt="ACPO Logo" class="me-2" style="height: 60px; width: auto;">
                <img src="images/acpologo.png" alt="PNP Logo" class="me-3" style="height: 70px; width: auto;">
                <span class="acpo-header-text">ANGELES CITY POLICE OFFICE</span>
            </a>
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
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

    <!-- Main: hero title and sliding login/register form -->
    <main class="flex-grow-1 login-page-bg w-100 d-flex align-items-center py-5">
        <div class="container-fluid px-4 px-lg-5 w-100">
            <div class="row align-items-center w-100 m-0 mx-auto" id="main-wrapper" style="max-width: 1200px;">
                <script>
                    var showRegisterOnLoad = <?php echo (isset($show_register) && $show_register) ? 'true' : 'false'; ?>;
                    var isSuccess = <?php echo !empty($success_msg) ? 'true' : 'false'; ?>;

                    if ((window.location.hash === '#register' && !isSuccess) || showRegisterOnLoad) {
                        document.getElementById('main-wrapper').classList.add('slide-in-start-register');
                    } else {
                        document.getElementById('main-wrapper').classList.add('slide-in-start');
                        if (window.location.hash === '#register') {
                            history.replaceState(null, null, window.location.pathname + window.location.search);
                        }
                    }
                </script>
                
                <!-- Text Column -->
                <div class="col-lg-6 text-center d-flex align-items-center justify-content-center order-2 order-lg-1 text-col position-relative" style="min-height: 400px; z-index: 1;">
                    <!-- Login Text -->
                    <div class="fade-block active" id="login-text">
                        <h1 class="hero-title-login mb-0">
                            PERSONNEL HEALTH<br>
                            AND BMI<br>
                            MONITORING<br>
                            RECORD
                        </h1>
                    </div>
                    <!-- Register Text -->
                    <div class="fade-block inactive" id="register-text">
                        <h1 class="hero-title-login mb-0" style="font-size: clamp(2rem, 4.5vw, 3.5rem);">
                            REGISTER TO ACCESS<br>
                            THE PERSONNEL HEALTH<br>
                            AND BMI MONITORING<br>
                            SYSTEM
                        </h1>
                    </div>
                </div>

                <!-- Card Column -->
                <div class="col-lg-6 d-flex align-items-center justify-content-center order-1 order-lg-2 card-col position-relative" style="z-index: 2;">
                    <div class="login-card">
                        
                        <!-- Login Form -->
                        <div class="form-fade active" id="login-form">
                            <h2 class="login-title mb-4 text-center">LOG IN</h2>
                            <?php if ($error_msg && !isset($_POST['action']) || (isset($_POST['action']) && $_POST['action'] === 'login' && $error_msg)): ?>
                                <div class="alert alert-danger text-center p-2 mb-3 small"><?php echo htmlspecialchars($error_msg); ?></div>
                            <?php endif; ?>
                            <?php if ($success_msg): ?>
                                <div class="alert alert-success text-center p-2 mb-3 small"><?php echo htmlspecialchars($success_msg); ?></div>
                            <?php endif; ?>
                            <form action="login.php" method="post">
                                <input type="hidden" name="action" value="login">
                                <div class="mb-3">
                                    <label for="username" class="form-label small fw-semibold">Username</label>
                                    <input type="text" class="form-control" id="username" name="username" placeholder="Username" required>
                                </div>
                                <div class="mb-3">
                                    <label for="password" class="form-label small fw-semibold">Password</label>
                                    <div class="position-relative">
                                        <input type="password" class="form-control pe-5" id="password" name="password" placeholder="Password" required>
                                        <span class="password-toggle position-absolute top-50 end-0 translate-middle-y me-3 text-secondary d-flex" style="width: 20px; height: 20px;" onclick="togglePassword('password', this)">
                                            <i class="bi bi-eye-slash transition-icon fs-5"></i>
                                            <i class="bi bi-eye transition-icon fs-5 icon-hidden"></i>
                                        </span>
                                    </div>
                                </div>
                                <div class="mb-4 form-check">
                                    <input type="checkbox" class="form-check-input" id="remember" name="remember">
                                    <label class="form-check-label small" for="remember">Remember me</label>
                                </div>
                                <button type="submit" class="btn acpo-primary w-100 py-2 py-lg-3 rounded-pill fw-bold text-uppercase" style="font-size: 1.05rem;">LOG IN</button>
                            </form>
                            <p class="mt-4 mb-0 text-center small text-secondary">
                                Don't have an account? <a href="#" id="show-register" class="text-acpo-primary fw-bold text-decoration-none">Register</a>
                            </p>
                        </div>

                        <!-- Register Form -->
                        <div class="form-fade inactive" id="register-form">
                            <h2 class="login-title mb-4 text-center">REGISTER</h2>
                            <?php if ($error_msg && isset($_POST['action']) && $_POST['action'] === 'register'): ?>
                                <div class="alert alert-danger text-center p-2 mb-3 small"><?php echo htmlspecialchars($error_msg); ?></div>
                            <?php endif; ?>
                            <form action="login.php" method="post" id="register-form-element" class="needs-validation" novalidate>
                                <input type="hidden" name="action" value="register">
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <input type="text" class="form-control" id="reg-last-name" name="last_name" placeholder="Last Name" required>
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" id="reg-first-name" name="first_name" placeholder="First Name" required>
                                    </div>
                                </div>
                                <div class="row mb-3">
                                    <div class="col-md-6 mb-3 mb-md-0">
                                        <input type="text" class="form-control" id="reg-middle-name" name="middle_name" placeholder="Middle Name">
                                    </div>
                                    <div class="col-md-6">
                                        <input type="text" class="form-control" id="reg-suffix" name="suffix" placeholder="Suffix (Jr, Sr, III)">
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <input class="form-control" list="pnp-ranks" id="reg-rank" name="rank" placeholder="Rank" required autocomplete="off">
                                    <datalist id="pnp-ranks">
                                        <!-- Commissioned Officers -->
                                        <option value="Police General (PGEN)">
                                        <option value="Police Lieutenant General (PLTGEN)">
                                        <option value="Police Major General (PMGEN)">
                                        <option value="Police Brigadier General (PBGEN)">
                                        <option value="Police Colonel (PCOL)">
                                        <option value="Police Lieutenant Colonel (PLTCOL)">
                                        <option value="Police Major (PMAJ)">
                                        <option value="Police Captain (PCPT)">
                                        <option value="Police Lieutenant (PLT)">
                                        <!-- Non-Commissioned Officers -->
                                        <option value="Police Executive Master Sergeant (PEMS)">
                                        <option value="Police Chief Master Sergeant (PCMS)">
                                        <option value="Police Senior Master Sergeant (PSMS)">
                                        <option value="Police Master Sergeant (PMSg)">
                                        <option value="Police Staff Sergeant (PSSg)">
                                        <option value="Police Corporal (PCpl)">
                                        <option value="Patrolman / Patrolwoman (Pat)">
                                    </datalist>
                                </div>
                                <div class="mb-3">
                                    <select class="form-control" id="reg-gender" name="gender" style="color: #6c757d;" onchange="this.style.color='#212529'" required>
                                        <option value="" disabled selected hidden>Sex</option>
                                        <option value="male" style="color: #212529;">Male</option>
                                        <option value="female" style="color: #212529;">Female</option>
                                    </select>
                                </div>
                                <div id="reg-unit-container" class="mb-3">
                                    <div class="d-flex align-items-center mb-2 unit-row">
                                        <input class="form-control" list="pnp-units" name="unit[]" placeholder="Unit/Office" required autocomplete="off">
                                        <button type="button" class="btn btn-outline-primary ms-2 rounded-circle d-flex align-items-center justify-content-center p-0" style="width: 32px; height: 32px; flex-shrink: 0;" onclick="addUnitRow()">
                                            <i class="bi bi-plus fs-5"></i>
                                        </button>
                                    </div>
                                    <datalist id="pnp-units">
                                        <option value="CHQ">
                                        <option value="PS1">
                                        <option value="PS2">
                                        <option value="PS3">
                                        <option value="PS4">
                                        <option value="PS5">
                                        <option value="PS6">
                                        <option value="CMFC">
                                        <option value="TPU">
                                        <option value="MPU">
                                        <option value="CARMU">
                                        <option value="CIU">
                                        <option value="AOMU">
                                        <option value="CCADU">
                                        <option value="ARDDO">
                                        <option value="CPPU">
                                        <option value="GSO">
                                        <option value="DEU">
                                        <option value="COMU">
                                        <option value="ODCDO">
                                    </datalist>
                                </div>
                                <div class="mb-3">
                                    <input type="text" class="form-control" id="reg-birthday" name="birthday" placeholder="MM/DD/YYYY" maxlength="10" required>
                                </div>
                                <div class="mb-3">
                                    <input type="text" class="form-control" id="reg-age" name="age" placeholder="Age" readonly style="background-color: #e9ecef;">
                                </div>
                                <div class="mb-3">
                                    <input type="text" class="form-control" id="reg-username" name="username" placeholder="Username" required>
                                </div>
                                <!-- Password field removed, default is Password@1234 -->
                                <button type="submit" class="btn acpo-primary w-100 py-2 py-lg-3 rounded-pill fw-bold text-uppercase" style="font-size: 1.05rem;">Register</button>
                            </form>
                            <p class="mt-4 mb-0 text-center small text-secondary">
                                Already have an account? <a href="#" id="show-login" class="text-acpo-primary fw-bold text-decoration-none">Log in</a>
                            </p>
                        </div>
                    </div>
                </div>

            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js" integrity="sha384-C6RzsynM9kWDrMNeT87bh95OGNyZPhcTNXj1NW7RuBCsyN/o0jlpcV8Qyq46cDfL" crossorigin="anonymous"></script>
    <script>
        function togglePassword(inputId, toggleEl) {
            const input = document.getElementById(inputId);
            const iconSlash = toggleEl.querySelector('.bi-eye-slash');
            const iconEye = toggleEl.querySelector('.bi-eye');
            
            if (input.type === 'password') {
                input.type = 'text';
                iconSlash.classList.add('icon-hidden');
                iconEye.classList.remove('icon-hidden');
            } else {
                input.type = 'password';
                iconEye.classList.add('icon-hidden');
                iconSlash.classList.remove('icon-hidden');
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const showRegister = document.getElementById('show-register');
            const showLogin = document.getElementById('show-login');
            const wrapper = document.getElementById('main-wrapper');
            const loginText = document.getElementById('login-text');
            const registerText = document.getElementById('register-text');
            const loginForm = document.getElementById('login-form');
            const registerForm = document.getElementById('register-form');
            const card = document.querySelector('.login-card');

            function switchView(toRegister) {
                // Update wrapper class for the sliding animation
                if (toRegister) {
                    wrapper.classList.add('is-register');
                    // Update URL hash to #register without page jump
                    history.replaceState(null, null, '#register');
                } else {
                    wrapper.classList.remove('is-register');
                    // Remove #register from URL without page jump
                    history.replaceState(null, null, window.location.pathname + window.location.search);
                }

                // Crossfade texts
                if (toRegister) {
                    loginText.classList.replace('active', 'inactive');
                    registerText.classList.replace('inactive', 'active');
                } else {
                    registerText.classList.replace('active', 'inactive');
                    loginText.classList.replace('inactive', 'active');
                }

                // Smoothly adjust the height of the card
                // 1. Get current height and set it fixed
                const currentHeight = card.offsetHeight;
                card.style.height = currentHeight + 'px';
                
                // 2. Toggle active classes
                if (toRegister) {
                    loginForm.classList.replace('active', 'inactive');
                    registerForm.classList.replace('inactive', 'active');
                } else {
                    registerForm.classList.replace('active', 'inactive');
                    loginForm.classList.replace('inactive', 'active');
                }

                // 3. Calculate new height
                const targetForm = toRegister ? registerForm : loginForm;
                
                // Temporarily make it position relative to get true height if it's not already
                const wasInactive = targetForm.classList.contains('inactive');
                if (wasInactive) {
                    targetForm.style.position = 'relative';
                    targetForm.style.visibility = 'hidden';
                    targetForm.style.display = 'block';
                }
                
                const newHeight = targetForm.offsetHeight;
                
                if (wasInactive) {
                    targetForm.style.position = '';
                    targetForm.style.visibility = '';
                    targetForm.style.display = '';
                }

                // 4. Force reflow and set new height to trigger transition
                card.offsetHeight; // reflow
                card.style.height = newHeight + 'px';

                // 5. Reset height to auto after transition completes
                setTimeout(() => {
                    card.style.height = 'auto';
                }, 400);
            }

            showRegister.addEventListener('click', function(e) {
                e.preventDefault();
                switchView(true);
            });

            showLogin.addEventListener('click', function(e) {
                e.preventDefault();
                switchView(false);
            });

            // Handle entrance animation on load
            if (window.location.hash === '#register' || showRegisterOnLoad) {
                // Pre-configure the form to register without animation
                loginText.classList.replace('active', 'inactive');
                registerText.classList.replace('inactive', 'active');
                loginForm.classList.replace('active', 'inactive');
                registerForm.classList.replace('inactive', 'active');
                
                // Trigger slide animation slightly after load
                setTimeout(() => {
                    wrapper.classList.remove('slide-in-start-register');
                    wrapper.classList.add('is-register');
                }, 50);
            } else {
                // Trigger slide animation for login
                setTimeout(() => {
                    wrapper.classList.remove('slide-in-start');
                }, 50);
            }

            // Standard Form Validation
            const registerFormElement = document.getElementById('register-form-element');

            // Age Calculation Logic
            const birthdayInput = document.getElementById('reg-birthday');
            const ageInput = document.getElementById('reg-age');

            if (birthdayInput && ageInput) {
                // Auto-format MM/DD/YYYY
                birthdayInput.addEventListener('input', function(e) {
                    let v = this.value.replace(/\D/g, '');
                    if (v.length > 8) v = v.substring(0, 8);
                    if (v.length > 4) {
                        this.value = v.substring(0, 2) + '/' + v.substring(2, 4) + '/' + v.substring(4, 8);
                    } else if (v.length > 2) {
                        this.value = v.substring(0, 2) + '/' + v.substring(2, 4);
                    } else {
                        this.value = v;
                    }
                });

                birthdayInput.addEventListener('change', function() {
                    const dob = new Date(this.value);
                    const today = new Date();
                    
                    if (isNaN(dob.getTime())) {
                        ageInput.value = '';
                        return;
                    }
                    
                    let age = today.getFullYear() - dob.getFullYear();
                    const monthDiff = today.getMonth() - dob.getMonth();
                    
                    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < dob.getDate())) {
                        age--;
                    }
                    
                    ageInput.value = age >= 0 ? age : 0;
                    
                    if (age < 21) {
                        this.setCustomValidity('You must be at least 21 years old to register.');
                        this.reportValidity();
                    } else {
                        this.setCustomValidity('');
                    }
                });
            }

            registerFormElement.addEventListener('submit', function(e) {
                if (!registerFormElement.checkValidity()) {
                    e.preventDefault();
                    e.stopPropagation();
                }
                registerFormElement.classList.add('was-validated');
            }, false);
        });

        function addUnitRow() {
            const container = document.getElementById('reg-unit-container');
            const dataListId = 'pnp-units';
            const newRow = document.createElement('div');
            newRow.className = 'd-flex align-items-center mb-2 unit-row';
            newRow.innerHTML = `
                <input class="form-control" list="${dataListId}" name="unit[]" placeholder="Unit/Office" required autocomplete="off">
                <button type="button" class="btn btn-outline-danger ms-2 rounded-circle d-flex align-items-center justify-content-center p-0" style="width: 32px; height: 32px; flex-shrink: 0;" onclick="removeUnitRow(this)">
                    <i class="bi bi-dash fs-5"></i>
                </button>
            `;
            // Insert before the datalist
            const datalist = document.getElementById(dataListId);
            container.insertBefore(newRow, datalist);
        }

        function removeUnitRow(button) {
            const row = button.closest('.unit-row');
            if (row) {
                row.remove();
            }
        }
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
</html>